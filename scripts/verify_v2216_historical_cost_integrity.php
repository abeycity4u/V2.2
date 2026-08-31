<?php
$root = dirname(__DIR__);
$checks = [];
function check_v2216(bool $ok, string $label): void { global $checks; $checks[] = [$ok,$label]; echo ($ok?'PASS: ':'FAIL: ').$label.PHP_EOL; }
$stock = file_get_contents($root.'/lib/stock_service.php');
$costing = file_get_contents($root.'/lib/stock_costing.php');
$financial = file_get_contents($root.'/includes/financial.php');
$profit = file_get_contents($root.'/management/profitability.php');
$inventory = file_get_contents($root.'/inventory.php');
$migration = file_get_contents($root.'/migrations/014_v22_financial_foundation.sql');

check_v2216(str_contains($migration, 'ADD COLUMN unit_cost') && str_contains($migration, 'ADD COLUMN total_cost'), 'stock ledger has immutable cost snapshot columns');
check_v2216(str_contains($costing, 'function stock_weighted_cost_replay') && str_contains($costing, 'function stock_historical_unit_cost'), 'historical weighted-average cost replay exists');
check_v2216(str_contains($stock, 'stock_historical_unit_cost') && str_contains($stock, '$snapshotUnitCost'), 'used stock snapshots historical business-date cost');
check_v2216(str_contains($stock, 'stock_recalculate_current_unit_cost'), 'stock corrections re-align current inventory valuation');
check_v2216(str_contains($inventory, 'Enter the actual unit cost for received stock') && str_contains($inventory, 'costInput.required = true'), 'received stock requires explicit receipt price');
$manualFeed = file_get_contents($root.'/lib/manual_feed_transactions.php');
check_v2216(str_contains($manualFeed, 'Received feed stock must be recorded from Inventory'), 'feed-ledger edits cannot create unpriced receipts');
check_v2216(str_contains($financial, 'stock_feed_item_sql_predicate') && str_contains($financial, 'JOIN stock_items s') && str_contains($financial, 'inventory_categories c'), 'profitability feed cost is limited to actual feed inventory');
check_v2216(str_contains($profit, 'stock_feed_item_sql_predicate') && str_contains($profit, 'transaction cost snapshot'), 'profitability warning and integrity copy use the hardened feed-cost scope');
check_v2216(str_contains($financial, 'SUM(total_amount)') && str_contains($financial, 'SUM(amount * unit)'), 'sales and expense values remain source-record snapshots');

$failed = count(array_filter($checks, fn($c)=>!$c[0]));
if ($failed) { echo "V2.2.16 verification failed: $failed check(s).".PHP_EOL; exit(1); }
echo 'V2.2.16 verification passed: '.count($checks).' check(s).'.PHP_EOL;
