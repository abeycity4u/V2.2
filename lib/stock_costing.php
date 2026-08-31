<?php
require_once __DIR__ . '/stock_reporting.php';

/**
 * V2.2.16 historical inventory costing helpers.
 *
 * Stock transactions already carry immutable unit_cost / total_cost snapshots.
 * These helpers reconstruct a weighted-average cost basis from effective
 * (non-reversed) ledger rows so back-dated corrections do not accidentally use
 * today's replacement cost.
 */
if (!function_exists('stock_feed_item_sql_predicate')) {
    function stock_feed_item_sql_predicate(string $itemAlias = 's', string $categoryAlias = 'c'): string
    {
        $item = rtrim($itemAlias, '.');
        $category = rtrim($categoryAlias, '.');
        return "({$item}.feed_category IN ('layer','broiler','ruminant') OR LOWER(COALESCE({$category}.category_name,'')) IN ('feed','feeds'))";
    }
}

if (!function_exists('stock_weighted_cost_replay')) {
    function stock_weighted_cost_replay(PDO $pdo, int $farmId, int $itemId, ?string $throughDate = null): array
    {
        $effective = stock_effective_sql_predicate('t');
        $sql = "SELECT t.id,t.transaction_type,t.quantity,t.unit_cost,t.total_cost,t.transaction_date,t.created_at
                FROM stock_transactions t
                WHERE t.farm_id=? AND t.stock_item_id=? AND {$effective}";
        $params = [$farmId, $itemId];
        if ($throughDate !== null) {
            $sql .= " AND t.transaction_date <= ?";
            $params[] = $throughDate;
        }
        $sql .= " ORDER BY t.transaction_date ASC, t.created_at ASC, t.id ASC";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);

        $quantity = 0.0;
        $value = 0.0;
        $uncosted = 0;
        $invalidQuantity = false;
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $q = round((float)$row['quantity'], 4);
            if ($q <= 0) continue;
            if ($row['transaction_type'] === 'received') {
                if ($row['unit_cost'] === null) {
                    $uncosted++;
                    $quantity += $q;
                    continue;
                }
                $quantity += $q;
                $value += $q * (float)$row['unit_cost'];
            } elseif ($row['transaction_type'] === 'used') {
                $quantity -= $q;
                if ($quantity < -0.00005) $invalidQuantity = true;
                if ($row['total_cost'] !== null) {
                    $value -= (float)$row['total_cost'];
                } elseif ($row['unit_cost'] !== null) {
                    $value -= $q * (float)$row['unit_cost'];
                } else {
                    $uncosted++;
                }
            }
        }

        if (abs($quantity) < 0.00005) $quantity = 0.0;
        if (abs($value) < 0.005) $value = 0.0;
        $invalidValue = $value < -0.005;
        $complete = $uncosted === 0 && !$invalidQuantity && !$invalidValue;
        $unitCost = ($quantity > 0 && $complete) ? ($value / $quantity) : null;
        return [
            'quantity' => round($quantity, 4),
            'value' => round($value, 2),
            'unit_cost' => $unitCost === null ? null : round($unitCost, 4),
            'uncosted_rows' => $uncosted,
            'invalid_quantity' => $invalidQuantity,
            'invalid_value' => $invalidValue,
            'complete' => $complete,
        ];
    }
}

if (!function_exists('stock_historical_unit_cost')) {
    function stock_historical_unit_cost(PDO $pdo, int $farmId, int $itemId, string $throughDate, float $fallback): float
    {
        $replay = stock_weighted_cost_replay($pdo, $farmId, $itemId, $throughDate);
        if ($replay['complete'] && $replay['unit_cost'] !== null) {
            return (float)$replay['unit_cost'];
        }
        // Legacy rows created before cost snapshots may be incomplete. In that
        // case preserve the known current valuation rather than inventing a cost.
        return round(max(0.0, $fallback), 4);
    }
}

if (!function_exists('stock_recalculate_current_unit_cost')) {
    function stock_recalculate_current_unit_cost(PDO $pdo, int $farmId, int $itemId): bool
    {
        $itemStmt = $pdo->prepare("SELECT current_stock,unit_cost FROM stock_items WHERE id=? AND farm_id=? FOR UPDATE");
        $itemStmt->execute([$itemId, $farmId]);
        $item = $itemStmt->fetch(PDO::FETCH_ASSOC);
        if (!$item) return false;

        $replay = stock_weighted_cost_replay($pdo, $farmId, $itemId, null);
        if (!$replay['complete']) return false;
        if (abs((float)$replay['quantity'] - (float)$item['current_stock']) > 0.005) return false;

        $newCost = (float)$item['unit_cost'];
        if ((float)$item['current_stock'] <= 0.00005) {
            $newCost = 0.0;
        } elseif ($replay['unit_cost'] !== null) {
            $newCost = (float)$replay['unit_cost'];
        }
        $pdo->prepare("UPDATE stock_items SET unit_cost=? WHERE id=? AND farm_id=?")
            ->execute([round($newCost, 4), $itemId, $farmId]);
        return true;
    }
}
