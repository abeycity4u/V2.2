<?php
/**
 * Revenue allocation helpers.
 *
 * A pooled sale remains one immutable source sale. Allocation rows are only
 * reporting ownership: they never duplicate or rewrite the source revenue.
 */
require_once __DIR__ . '/layer_egg_inventory.php';

function sales_allocation_status(PDO $pdo, int $farmId, int $saleId, float $saleTotal): array
{
    $stmt = $pdo->prepare("SELECT COALESCE(SUM(allocated_amount),0) FROM sales_allocations WHERE farm_id=? AND sale_id=?");
    $stmt->execute([$farmId, $saleId]);
    $allocated = (float)$stmt->fetchColumn();
    $epsilon = 0.01;
    if ($allocated <= $epsilon) $status = 'unallocated';
    elseif ($allocated + $epsilon < $saleTotal) $status = 'partial';
    else $status = 'allocated';
    return ['status'=>$status,'allocated_amount'=>$allocated,'unallocated_amount'=>max(0,$saleTotal-$allocated)];
}

function sales_clear_allocations(PDO $pdo, int $farmId, int $saleId): void
{
    $stmt = $pdo->prepare("DELETE FROM sales_allocations WHERE farm_id=? AND sale_id=?");
    $stmt->execute([$farmId,$saleId]);
}

/**
 * Auto-allocate a pooled Layer egg sale from the UNSOLD egg pool, not merely
 * production recorded on the sale date. This correctly handles delayed and
 * partial sales while excluding rearing/non-producing cycles naturally.
 */
function sales_auto_allocate_layer_egg(PDO $pdo, int $farmId, int $saleId, string $saleDate, float $saleTotal, ?int $userId=null): array
{
    $saleStmt = $pdo->prepare("SELECT quantity FROM sales_records WHERE id=? AND farm_id=? LIMIT 1");
    $saleStmt->execute([$saleId,$farmId]);
    $saleQty = (float)$saleStmt->fetchColumn();
    sales_clear_allocations($pdo,$farmId,$saleId);

    if ($saleTotal <= 0 || $saleQty <= 0) {
        return ['status'=>'unallocated','allocated_amount'=>0.0,'cycles'=>0,'reason'=>'Sale quantity or total is zero.'];
    }

    $pool = layer_egg_pool_before_sale($pdo,$farmId,$saleDate,$saleId);
    $eligible = array_values(array_filter($pool, static fn($row)=>(float)$row['available_crates'] > 0.0001));
    $totals = layer_egg_pool_totals($eligible);
    $available = (float)$totals['available_crates'];
    if ($available <= 0.0001) {
        return ['status'=>'unallocated','allocated_amount'=>0.0,'cycles'=>0,'reason'=>'No unsold Layer egg production is available for this sale date.','available_quantity'=>0.0,'sale_quantity'=>$saleQty];
    }
    if ($saleQty > $available + 0.01) {
        return ['status'=>'unallocated','allocated_amount'=>0.0,'cycles'=>count($eligible),'reason'=>sprintf('Recorded unsold egg stock is %.2f crates, below the %.2f crates sold.',$available,$saleQty),'available_quantity'=>$available,'sale_quantity'=>$saleQty];
    }

    $insert = $pdo->prepare("INSERT INTO sales_allocations
        (farm_id,sale_id,cycle_id,allocation_percent,allocated_quantity,allocation_unit,allocated_amount,allocation_basis,notes,created_by)
        VALUES (?,?,?,?,?,'crate',?,'layer_unsold_egg_pool',?,?)");
    $remainingAmount = round($saleTotal,2);
    $remainingQty = round($saleQty,4);
    $last = count($eligible)-1;
    foreach ($eligible as $i=>$row) {
        $share = (float)$row['available_crates'] / $available;
        $qty = $i===$last ? $remainingQty : round($saleQty*$share,4);
        $amount = $i===$last ? $remainingAmount : round($saleTotal*$share,2);
        $remainingQty = round($remainingQty-$qty,4);
        $remainingAmount = round($remainingAmount-$amount,2);
        $insert->execute([
            $farmId,$saleId,(int)$row['cycle_id'],round($share*100,4),$qty,$amount,
            'Auto-allocated from each Layer cycle\'s unsold egg pool as of '.$saleDate,
            $userId ?: null,
        ]);
    }
    return ['status'=>'allocated','allocated_amount'=>round($saleTotal,2),'allocated_quantity'=>round($saleQty,4),'cycles'=>count($eligible),'reason'=>'Allocated from accumulated unsold Layer egg production.','available_before'=>$available];
}

function sales_refresh_automatic_allocation(PDO $pdo, int $farmId, int $saleId, ?int $userId=null): array
{
    $stmt=$pdo->prepare("SELECT id,sale_date,farm_type,production_type,cycle_id,product_type,total_amount FROM sales_records WHERE id=? AND farm_id=? LIMIT 1");
    $stmt->execute([$saleId,$farmId]);
    $sale=$stmt->fetch(PDO::FETCH_ASSOC);
    if(!$sale) throw new RuntimeException('Sale was not found for allocation.');
    if(!empty($sale['cycle_id'])) {
        sales_clear_allocations($pdo,$farmId,$saleId);
        return ['status'=>'direct','allocated_amount'=>(float)$sale['total_amount'],'cycles'=>1,'reason'=>'Sale is directly assigned to a cycle.'];
    }
    if($sale['farm_type']==='poultry' && strtolower((string)$sale['production_type'])==='layer' && layer_egg_is_sale_product($sale['product_type']??null)) {
        return sales_auto_allocate_layer_egg($pdo,$farmId,$saleId,(string)$sale['sale_date'],(float)$sale['total_amount'],$userId);
    }
    sales_clear_allocations($pdo,$farmId,$saleId);
    return ['status'=>'unallocated','allocated_amount'=>0.0,'cycles'=>0,'reason'=>'This shared sale has no automatic allocation basis.'];
}

/**
 * Rebuild pooled Layer egg allocations chronologically from a date. Call this
 * whenever a Layer Daily Record or Layer egg sale on/after that date changes.
 */
function sales_rebuild_layer_egg_allocations(PDO $pdo, int $farmId, string $fromDate='1000-01-01', ?int $userId=null): array
{
    $stmt=$pdo->prepare("SELECT id,product_type FROM sales_records
                         WHERE farm_id=? AND farm_type='poultry' AND production_type='layer'
                           AND cycle_id IS NULL AND sale_date>=?
                         ORDER BY sale_date,id");
    $stmt->execute([$farmId,$fromDate]);
    $rebuilt=$allocated=$unallocated=0;
    foreach($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        if(!layer_egg_is_sale_product($row['product_type']??null)) continue;
        $result=sales_refresh_automatic_allocation($pdo,$farmId,(int)$row['id'],$userId);
        $rebuilt++;
        if(($result['status']??'')==='allocated') $allocated++; else $unallocated++;
    }
    return ['rebuilt'=>$rebuilt,'allocated'=>$allocated,'unallocated'=>$unallocated];
}
