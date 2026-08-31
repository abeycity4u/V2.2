<?php
require_once __DIR__ . '/stock_costing.php';
require_once __DIR__ . '/attribution.php';
/**
 * Canonical inventory ledger service.
 *
 * Every stock mutation must pass through this file. `transaction_date` is the
 * business/effective date; `created_at` is the actual posting/event time.
 * Ledger rows are append-only once posted. Corrections are represented by a
 * linked reversal row instead of mutating/deleting the original movement.
 */
function stock_apply_movement(
    PDO $pdo,
    int $farmId,
    int $itemId,
    string $type,
    float $quantity,
    string $transactionDate,
    ?string $remarks,
    ?int $userId,
    ?string $farmType = null,
    ?string $feedCategory = null,
    ?int $cycleId = null,
    ?string $sourceType = null,
    ?int $sourceId = null,
    ?float $incomingUnitCost = null
): int {
    $quantity = round($quantity, 2);
    if (!in_array($type, ['received', 'used'], true)) {
        throw new RuntimeException('Invalid stock transaction type.');
    }
    if ($quantity <= 0 || !is_finite($quantity)) {
        throw new RuntimeException('Quantity must be greater than zero.');
    }

    $itemSql = "SELECT * FROM stock_items WHERE id = ? AND farm_id = ?";
    $params = [$itemId, $farmId];
    if ($farmType !== null) {
        $itemSql .= " AND farm_type IN (?, 'both')";
        $params[] = $farmType;
    }
    if ($feedCategory !== null) {
        $itemSql .= " AND feed_category = ?";
        $params[] = $feedCategory;
    }
    $itemSql .= " FOR UPDATE";
    $stmt = $pdo->prepare($itemSql);
    $stmt->execute($params);
    $item = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$item) {
        throw new RuntimeException('The selected inventory item is not available for this farm/module.');
    }
    if ((int)$item['is_active'] !== 1) {
        throw new RuntimeException('The selected inventory item is inactive. Please select an active item.');
    }

    $previous = round((float)$item['current_stock'], 2);
    $unitCost = (float)($item['unit_cost'] ?? 0);
    $newUnitCost = $unitCost;

    if ($type === 'used') {
        if ($quantity > $previous + 0.00001) {
            throw new RuntimeException(
                'Insufficient stock. Available: ' .
                rtrim(rtrim(number_format($previous, 2, '.', ''), '0'), '.') .
                ' ' . $item['unit']
            );
        }
        $newStock = round($previous - $quantity, 2);
    } else {
        $newStock = round($previous + $quantity, 2);
        if ($incomingUnitCost !== null && $incomingUnitCost > 0) {
            $newUnitCost = $newStock > 0
                ? (($previous * $unitCost) + ($quantity * $incomingUnitCost)) / $newStock
                : $incomingUnitCost;
            $newUnitCost = round($newUnitCost, 4);
        }
    }

    $pdo->prepare("UPDATE stock_items SET current_stock = ?, unit_cost = ? WHERE id = ? AND farm_id = ?")
        ->execute([$newStock, $newUnitCost, $itemId, $farmId]);

    // Receipts preserve their actual purchase price. Usage preserves the
    // weighted-average cost that existed on the business date, so editing an
    // older Daily Record after a later price change does not rewrite history.
    if ($type === 'received') {
        $snapshotUnitCost = $incomingUnitCost !== null ? (float)$incomingUnitCost : $unitCost;
    } else {
        $snapshotUnitCost = stock_historical_unit_cost($pdo, $farmId, $itemId, $transactionDate, $unitCost);
    }
    $snapshotUnitCost = round(max(0.0, $snapshotUnitCost), 4);
    $totalCost = round($quantity * $snapshotUnitCost, 2);

    // Snapshot operational ownership as well as cost. Inventory items can be
    // shared; the movement says which operation/cycle actually consumed them.
    $movementFarmType = $farmType ?: (string)$item['farm_type'];
    if ($movementFarmType === 'both') {
        $movementFarmType = $farmType ?: 'general';
    }
    $productionType = attribution_normalize_production_type(
        $movementFarmType,
        $feedCategory === 'ruminant' ? 'shared' : $feedCategory
    );
    if (($cycleId ?? 0) > 0) {
        $cycleStmt = $pdo->prepare("SELECT production_type, farm_type FROM production_cycles WHERE id=? AND farm_id=? LIMIT 1");
        $cycleStmt->execute([(int)$cycleId, $farmId]);
        if ($cycle = $cycleStmt->fetch(PDO::FETCH_ASSOC)) {
            $productionType = strtolower((string)$cycle['production_type']);
            $movementFarmType = strtolower((string)$cycle['farm_type']);
        }
    }
    $attributionScope = attribution_scope($cycleId, $movementFarmType, $productionType);

    $insert = $pdo->prepare("INSERT INTO stock_transactions
        (farm_id, cycle_id, stock_item_id, transaction_type, quantity, unit_cost, total_cost,
         previous_stock, new_stock, transaction_date, remarks, user_id, farm_type, production_type,
         attribution_scope, source_type, source_id)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $insert->execute([
        $farmId,
        $cycleId,
        $itemId,
        $type,
        $quantity,
        $snapshotUnitCost,
        $totalCost,
        $previous,
        $newStock,
        $transactionDate,
        $remarks,
        $userId,
        $movementFarmType,
        $productionType,
        $attributionScope,
        $sourceType,
        $sourceId,
    ]);
    $transactionId = (int)$pdo->lastInsertId();

    // For manual movements the transaction id is the durable source id.
    if ($sourceType !== null && $sourceId === null) {
        $pdo->prepare("UPDATE stock_transactions SET source_id = ? WHERE id = ? AND farm_id = ?")
            ->execute([$transactionId, $transactionId, $farmId]);
    }

    // Keep the current inventory valuation aligned with the effective ledger.
    // Legacy uncosted rows are deliberately left untouched by the helper.
    stock_recalculate_current_unit_cost($pdo, $farmId, $itemId);

    return $transactionId;
}

function stock_reverse_transaction(
    PDO $pdo,
    int $farmId,
    int $transactionId,
    string $reason,
    ?int $userId = null,
    ?string $sourceType = null,
    ?int $sourceId = null
): int {
    $stmt = $pdo->prepare("SELECT * FROM stock_transactions WHERE id = ? AND farm_id = ? FOR UPDATE");
    $stmt->execute([$transactionId, $farmId]);
    $tx = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$tx) {
        throw new RuntimeException('Stock transaction not found.');
    }
    if (!empty($tx['is_reversed'])) {
        throw new RuntimeException('This stock transaction has already been reversed.');
    }
    if (!empty($tx['reversal_of_id'])) {
        throw new RuntimeException('A reversal transaction cannot be reversed again.');
    }

    $itemStmt = $pdo->prepare("SELECT * FROM stock_items WHERE id = ? AND farm_id = ? FOR UPDATE");
    $itemStmt->execute([(int)$tx['stock_item_id'], $farmId]);
    $item = $itemStmt->fetch(PDO::FETCH_ASSOC);
    if (!$item) {
        throw new RuntimeException('The inventory item linked to this transaction no longer exists.');
    }

    $previous = round((float)$item['current_stock'], 2);
    $quantity = round((float)$tx['quantity'], 2);
    $reverseType = $tx['transaction_type'] === 'received' ? 'used' : 'received';

    if ($reverseType === 'used' && $quantity > $previous + 0.00001) {
        throw new RuntimeException('This correction cannot be completed because it would create negative stock.');
    }
    $newStock = $reverseType === 'received'
        ? round($previous + $quantity, 2)
        : round($previous - $quantity, 2);

    $pdo->prepare("UPDATE stock_items SET current_stock = ? WHERE id = ? AND farm_id = ?")
        ->execute([$newStock, (int)$item['id'], $farmId]);

    $remark = trim($reason) !== '' ? $reason : 'Stock transaction reversal';
    $reversalSourceType = $sourceType ?? (($tx['source_type'] ?: 'stock') . '_reversal');
    $reversalSourceId = $sourceId ?? (int)$tx['id'];
    $insert = $pdo->prepare("INSERT INTO stock_transactions
        (farm_id, cycle_id, stock_item_id, transaction_type, quantity, unit_cost, total_cost,
         previous_stock, new_stock, transaction_date, remarks, user_id, farm_type, production_type,
         attribution_scope, source_type, source_id, is_reversed, reversal_of_id, reversed_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0, ?, NULL)");
    $insert->execute([
        $farmId,
        $tx['cycle_id'] ?? null,
        (int)$item['id'],
        $reverseType,
        $quantity,
        (float)($tx['unit_cost'] ?? 0),
        round($quantity * (float)($tx['unit_cost'] ?? 0), 2),
        $previous,
        $newStock,
        $tx['transaction_date'],
        $remark,
        $userId,
        $tx['farm_type'] ?? $item['farm_type'],
        $tx['production_type'] ?? 'shared',
        $tx['attribution_scope'] ?? (($tx['cycle_id'] ?? null) ? 'cycle' : 'farm'),
        $reversalSourceType,
        $reversalSourceId,
        (int)$tx['id'],
    ]);
    $reversalId = (int)$pdo->lastInsertId();

    $pdo->prepare("UPDATE stock_transactions
        SET is_reversed = 1, reversal_of_id = ?, reversed_at = NOW()
        WHERE id = ? AND farm_id = ? AND is_reversed = 0")
        ->execute([$reversalId, (int)$tx['id'], $farmId]);

    // Re-price remaining stock from effective audit history. This matters when
    // a correction is posted after newer stock arrived at a different price.
    stock_recalculate_current_unit_cost($pdo, $farmId, (int)$item['id']);

    return $reversalId;
}

/**
 * Reconstruct the physical inventory balance from every posted movement.
 * Reversal pairs intentionally net to zero: the original movement remains
 * part of the physical ledger and its linked reversal cancels it.
 */
function stock_expected_balance(PDO $pdo, int $farmId, int $itemId): float
{
    $stmt = $pdo->prepare("SELECT COALESCE(SUM(CASE WHEN transaction_type = 'received' THEN quantity ELSE -quantity END), 0)
        FROM stock_transactions
        WHERE farm_id = ? AND stock_item_id = ?");
    $stmt->execute([$farmId, $itemId]);
    return round((float)$stmt->fetchColumn(), 2);
}

function stock_reconciliation(PDO $pdo, int $farmId, ?int $itemId = null): array
{
    $sql = "SELECT s.id, s.item_name, s.unit, s.current_stock,
                   COALESCE(SUM(CASE WHEN t.transaction_type = 'received' THEN t.quantity ELSE -t.quantity END), 0) AS ledger_stock,
                   COUNT(t.id) AS ledger_transaction_count
            FROM stock_items s
            LEFT JOIN stock_transactions t
              ON t.stock_item_id = s.id AND t.farm_id = s.farm_id
            WHERE s.farm_id = ?";
    $params = [$farmId];
    if ($itemId !== null) {
        $sql .= " AND s.id = ?";
        $params[] = $itemId;
    }
    $sql .= " GROUP BY s.id, s.item_name, s.unit, s.current_stock ORDER BY s.item_name";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows as &$row) {
        $row['current_stock'] = round((float)$row['current_stock'], 2);
        $row['ledger_stock'] = round((float)$row['ledger_stock'], 2);
        $row['ledger_transaction_count'] = (int)$row['ledger_transaction_count'];
        $row['difference'] = round($row['current_stock'] - $row['ledger_stock'], 2);
        $row['status'] = abs($row['difference']) < 0.005 ? 'reconciled' : 'mismatch';
        $row['status_reason'] = $row['status'] === 'reconciled'
            ? ($row['ledger_transaction_count'] > 0 ? 'ledger matches current stock' : 'zero stock / no posted movements')
            : 'current stock does not reconcile with posted ledger movements';
    }
    unset($row);
    return $rows;
}
