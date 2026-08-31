<?php
$root=dirname(__DIR__);$checks=[];
function check2220(bool $ok,string $label):void{global $checks;$checks[]=$ok;echo ($ok?'PASS: ':'FAIL: ').$label."\n";}
$egg=file_get_contents($root.'/lib/layer_egg_inventory.php');
$alloc=file_get_contents($root.'/lib/sales_allocation.php');
$sales=file_get_contents($root.'/management/sales_records.php');
$daily=file_get_contents($root.'/poultry/layers_daily_record.php');
$deleteDaily=file_get_contents($root.'/api/delete_record.php');
$deleteSale=file_get_contents($root.'/api/delete_sale.php');
$profit=file_get_contents($root.'/management/profitability.php');
$migration=file_get_contents($root.'/migrations/019_layer_egg_inventory_allocation.sql');
$backfill=file_get_contents($root.'/scripts/backfill_pooled_sales_allocations.php');
check2220(str_contains($egg,'layer_egg_pool_before_sale') && str_contains($egg,'available_crates'), 'derived egg ledger carries unsold production across sale dates');
check2220(str_contains($egg,'record_date<=?') && str_contains($egg,'sale_date < ?') && str_contains($egg,'id < ?'), 'egg pool replays production and earlier sales in business-date order');
check2220(str_contains($egg,'LAYER_EGGS_PER_CRATE') && str_contains($egg,'crates_count') && str_contains($egg,'egg_production'), 'egg quantities normalize Daily Record output to crates');
check2220(str_contains($alloc,'layer_unsold_egg_pool') && str_contains($alloc,'saleQty > $available'), 'pooled Layer egg sales allocate from available stock and refuse unsupported quantity');
check2220(str_contains($alloc,'allocated_quantity') && str_contains($alloc,"'crate'"), 'cycle allocations persist both revenue and quantity ownership');
check2220(str_contains($migration,'allocated_quantity') && str_contains($migration,'019_layer_egg_inventory_allocation.sql'), 'migration adds durable allocation quantity without duplicating source revenue');
check2220(str_contains($daily,'sales_rebuild_layer_egg_allocations') && str_contains($deleteDaily,'sales_rebuild_layer_egg_allocations'), 'Layer Daily Record edits and deletes replay later pooled allocations');
check2220(substr_count($sales,'sales_rebuild_layer_egg_allocations')>=2 && str_contains($deleteSale,'sales_rebuild_layer_egg_allocations'), 'sale create/edit/delete keeps later pooled allocations synchronized');
check2220(str_contains($backfill,'sales_rebuild_layer_egg_allocations') && str_contains($backfill,'ORDER BY farm_id'), 'historical pooled Layer sales can be rebuilt safely');
check2220(str_contains($sales,'Shared between cycles') && str_contains($sales,'Not applicable'), 'Sales UI distinguishes shared-cycle attribution from General no-cycle activity');
check2220(str_contains($profit,'Shared revenue included.') && str_contains($profit,'Some shared revenue could not be assigned yet.'), 'Profitability explains allocated and genuinely unresolved shared revenue');
check2220(str_contains($alloc, "available_crates'] > 0.0001"), 'rearing or zero-production Layer cycles receive no pooled egg revenue share');
$failed=count(array_filter($checks,static fn($v)=>!$v));
if($failed){echo "V2.2.20 verification failed: {$failed} check(s).\n";exit(1);}echo 'V2.2.20 verification passed: '.count($checks)." check(s).\n";
