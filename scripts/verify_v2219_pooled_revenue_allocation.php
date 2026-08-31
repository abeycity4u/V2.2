<?php
$root = dirname(__DIR__);
$checks = [];
function check2219(bool $ok, string $label): void { global $checks; $checks[]=$ok; echo ($ok?'PASS: ':'FAIL: ').$label."\n"; }

$allocation = file_get_contents($root.'/lib/sales_allocation.php');
$sales = file_get_contents($root.'/management/sales_records.php');
$profit = file_get_contents($root.'/management/profitability.php');
$financial = file_get_contents($root.'/includes/financial.php');
$backfill = file_get_contents($root.'/scripts/backfill_pooled_sales_allocations.php');

check2219(str_contains($allocation,'sales_auto_allocate_layer_egg') && (str_contains($allocation,'layer_daily_output') || str_contains($allocation,'layer_unsold_egg_pool')), 'pooled Layer egg sales use Daily Record output allocation');
check2219((str_contains($allocation,'crates_count') && str_contains($allocation,'egg_production')) || str_contains($allocation,'layer_egg_inventory.php'), 'Layer allocation has crate-first and egg-output fallback basis');
check2219(str_contains($allocation,'sales_clear_allocations') && str_contains($allocation,"cycle_id"), 'allocation rebuild is idempotent and direct-cycle safe');
check2219(str_contains($sales,"require_once(__DIR__ . '/../lib/sales_allocation.php')") && substr_count($sales,'sales_refresh_automatic_allocation') >= 2, 'new and edited sales refresh automatic allocations');
check2219(str_contains($sales,"editSaleProductionType") && str_contains($sales,"prefix === 'edit'") && str_contains($sales,"refreshSaleAttribution('edit', String(data.production"), 'Edit Sale repopulates Production Type and Cycle with actual edit control IDs');
check2219(str_contains($sales,'Allocated <?php echo number_format($allocationPct') && str_contains($sales,'Unallocated</span>'), 'Sales ledger exposes allocation status');
check2219(str_contains($financial,'FROM sales_allocations sa') && str_contains($financial,'$revenue += (float)$stmt->fetchColumn();'), 'cycle profitability includes explicit pooled-sale allocations');
check2219((str_contains($profit,'Cycle revenue may be understated') || str_contains($profit,'Some shared revenue could not be assigned yet.')) && str_contains($profit,'unallocatedPooledRevenue'), 'cycle profitability warns about remaining pooled revenue');
check2219(str_contains($backfill,'sales_rebuild_layer_egg_allocations') || (str_contains($backfill,'sales_refresh_automatic_allocation') && str_contains($backfill,"cycle_id IS NULL")), 'existing pooled Layer egg sales can be safely backfilled');

$failed = count(array_filter($checks, static fn($v)=>!$v));
if ($failed) { echo "V2.2.19 verification failed: {$failed} check(s).\n"; exit(1); }
echo 'V2.2.19 verification passed: '.count($checks)." check(s).\n";
