<?php
/**
 * Layer egg production-to-sales inventory.
 *
 * Canonical quantity is crates. Daily Records already store crates_count and
 * egg_production; when crates_count is unavailable we convert eggs at 30/crate.
 * This ledger is derived from source records, so edits/deletes can be replayed
 * without maintaining a second mutable stock balance.
 */

if (!defined('LAYER_EGGS_PER_CRATE')) {
    define('LAYER_EGGS_PER_CRATE', 30.0);
}

function layer_egg_is_sale_product(?string $product): bool
{
    $product = strtolower(trim((string)$product));
    return $product !== '' && (str_contains($product, 'egg') || in_array($product, ['crate','crates','egg crate','egg crates'], true));
}

function layer_egg_daily_crates_sql(): string
{
    return "CASE WHEN COALESCE(ldr.crates_count,0) > 0
                 THEN ldr.crates_count
                 ELSE COALESCE(ldr.egg_production,0) / " . LAYER_EGGS_PER_CRATE . " END";
}

/**
 * Return available unsold egg crates by Layer cycle immediately before a sale.
 * Production for the sale date is available to the pool. Prior sales are
 * ordered by business date then id. Direct-cycle sales deduct from that cycle;
 * pooled sales deduct using their persisted allocation quantities.
 */
function layer_egg_pool_before_sale(PDO $pdo, int $farmId, string $saleDate, int $saleId): array
{
    $qtyExpr = layer_egg_daily_crates_sql();
    $prodSql = "SELECT pc.id AS cycle_id, pc.cycle_code,
                       COALESCE(SUM({$qtyExpr}),0) AS produced_crates
                FROM production_cycles pc
                LEFT JOIN layer_daily_records ldr
                  ON ldr.cycle_id=pc.id AND ldr.farm_id=pc.farm_id AND ldr.record_date<=?
                WHERE pc.farm_id=? AND pc.farm_type='poultry' AND LOWER(pc.production_type)='layer'
                GROUP BY pc.id,pc.cycle_code";
    $stmt = $pdo->prepare($prodSql);
    $stmt->execute([$saleDate,$farmId]);
    $pool = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $pool[(int)$row['cycle_id']] = [
            'cycle_id'=>(int)$row['cycle_id'],
            'cycle_code'=>(string)$row['cycle_code'],
            'produced_crates'=>(float)$row['produced_crates'],
            'sold_crates'=>0.0,
            'available_crates'=>(float)$row['produced_crates'],
        ];
    }

    $sales = $pdo->prepare("SELECT id,sale_date,cycle_id,product_type,quantity
                            FROM sales_records
                            WHERE farm_id=? AND farm_type='poultry' AND production_type='layer'
                              AND (sale_date < ? OR (sale_date=? AND id < ?))
                            ORDER BY sale_date,id");
    $sales->execute([$farmId,$saleDate,$saleDate,$saleId]);
    $allocStmt = $pdo->prepare("SELECT cycle_id,allocation_percent,allocated_quantity
                                FROM sales_allocations WHERE farm_id=? AND sale_id=? ORDER BY cycle_id");
    foreach ($sales->fetchAll(PDO::FETCH_ASSOC) as $sale) {
        if (!layer_egg_is_sale_product($sale['product_type'] ?? null)) continue;
        $saleQty = max(0.0,(float)$sale['quantity']);
        $directCycle = (int)($sale['cycle_id'] ?? 0);
        if ($directCycle > 0) {
            if (isset($pool[$directCycle])) {
                $pool[$directCycle]['sold_crates'] += $saleQty;
                $pool[$directCycle]['available_crates'] -= $saleQty;
            }
            continue;
        }
        $allocStmt->execute([$farmId,(int)$sale['id']]);
        foreach ($allocStmt->fetchAll(PDO::FETCH_ASSOC) as $allocation) {
            $cycleId = (int)$allocation['cycle_id'];
            if (!isset($pool[$cycleId])) continue;
            $allocatedQty = $allocation['allocated_quantity'] !== null
                ? (float)$allocation['allocated_quantity']
                : ($saleQty * ((float)$allocation['allocation_percent'] / 100.0));
            $pool[$cycleId]['sold_crates'] += $allocatedQty;
            $pool[$cycleId]['available_crates'] -= $allocatedQty;
        }
    }

    foreach ($pool as &$row) {
        $row['available_crates'] = round(max(0.0,(float)$row['available_crates']),4);
        $row['produced_crates'] = round((float)$row['produced_crates'],4);
        $row['sold_crates'] = round((float)$row['sold_crates'],4);
    }
    unset($row);
    return $pool;
}

function layer_egg_pool_totals(array $pool): array
{
    $produced = $sold = $available = 0.0;
    foreach ($pool as $row) {
        $produced += (float)($row['produced_crates'] ?? 0);
        $sold += (float)($row['sold_crates'] ?? 0);
        $available += (float)($row['available_crates'] ?? 0);
    }
    return [
        'produced_crates'=>round($produced,4),
        'sold_crates'=>round($sold,4),
        'available_crates'=>round($available,4),
    ];
}
