<?php
/**
 * Rebuild pooled Layer egg revenue chronologically from accumulated unsold
 * egg production. Safe to run repeatedly: automatic allocation rows are
 * rebuilt from source Daily Records and Sales Records.
 *
 * Usage:
 *   php scripts/backfill_pooled_sales_allocations.php
 *   php scripts/backfill_pooled_sales_allocations.php --farm-id=3
 */
require_once dirname(__DIR__) . '/init.php';
require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/lib/sales_allocation.php';
if (PHP_SAPI !== 'cli') { http_response_code(403); exit("CLI only.\n"); }

$farmId=null;
foreach(array_slice($argv,1) as $arg) if(str_starts_with($arg,'--farm-id=')) $farmId=(int)substr($arg,10);
$sql="SELECT DISTINCT farm_id FROM sales_records WHERE farm_type='poultry' AND production_type='layer'";
$params=[];
if($farmId && $farmId>0){$sql.=" AND farm_id=?";$params[]=$farmId;}
$sql.=" ORDER BY farm_id";
$stmt=$pdo->prepare($sql);$stmt->execute($params);
$farms=array_map('intval',$stmt->fetchAll(PDO::FETCH_COLUMN));
$totalRebuilt=$totalAllocated=$totalUnallocated=0;
foreach($farms as $fid){
    $result=sales_rebuild_layer_egg_allocations($pdo,$fid,'1000-01-01',null);
    $totalRebuilt+=(int)$result['rebuilt'];$totalAllocated+=(int)$result['allocated'];$totalUnallocated+=(int)$result['unallocated'];
    echo "Farm {$fid}: rebuilt={$result['rebuilt']} allocated={$result['allocated']} unallocated={$result['unallocated']}\n";
}
echo "Processed {$totalRebuilt} pooled Layer egg sale(s); {$totalAllocated} allocated, {$totalUnallocated} unallocated.\n";
