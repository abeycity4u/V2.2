<?php
$root = dirname(__DIR__);
$checks = [];
function check222($condition, $message) { global $checks; $checks[] = [$condition, $message]; echo ($condition ? 'PASS: ' : 'FAIL: ') . $message . PHP_EOL; }
$inventory = file_get_contents($root . '/inventory.php');
$category = file_get_contents($root . '/inventory/delete_category.php');
$notify = file_get_contents($root . '/navbar_head.php');
$main = file_get_contents($root . '/assets/js/main.js');
$layer = file_get_contents($root . '/poultry/layer_expenses.php');
$broiler = file_get_contents($root . '/poultry/broiler_expenses.php');
$ruminant = file_get_contents($root . '/ruminant/ruminant_expenses.php');

check222(strpos($inventory, "remarks = 'Initial stock entry'") !== false && strpos($inventory, '$setupOnly') !== false, 'setup-only opening stock is distinguished from operational audit history');
check222(strpos($inventory, 'DELETE FROM stock_transactions WHERE stock_item_id = ? AND farm_id = ?') !== false && strpos($inventory, 'Inventory item deleted successfully.') !== false, 'new setup-only inventory item can be removed cleanly');
check222(strpos($inventory, 'operational stock history, so it must remain archived') !== false && strpos($inventory, 'Protected history') !== false, 'genuine operational stock history remains protected');
check222(strpos($inventory, 'catch (RuntimeException $e)') !== false && strpos($inventory, 'still assigned to') !== false, 'category-in-use rejection is handled without HTTP 500');
check222(strpos($category, 'catch (RuntimeException $e)') !== false, 'standalone category deletion also handles business-rule rejection');
check222(strpos($inventory, 'AppConfirm.ask') !== false && strpos($inventory, "title: 'Delete inventory item?'") !== false, 'Inventory Ledger deletion uses modern platform confirmation');
check222(strpos($inventory, 'Usage Classification') !== false && strpos($inventory, 'General / Non-feed item') !== false, 'inventory usage classification remains appropriate for feed and non-feed items');
check222(strpos($notify, "type === 'success' ? 2500") !== false && strpos($main, "type === 'success' ? 2500") !== false, 'green success notifications dismiss in about 2.5 seconds');
check222(substr_count($layer, '<label>Production Type</label>') >= 2 && strpos($layer, 'name="production_type" value="layer"') !== false, 'Layer Add/Edit Expense use the same controlled Production Type field');
check222(substr_count($broiler, '<label>Production Type</label>') >= 2 && strpos($broiler, 'name="production_type" value="broiler"') !== false, 'Broiler Add/Edit Expense use the same controlled Production Type field');
check222(strpos($ruminant, 'id="editExpenseProduction"') !== false && strpos($ruminant, 'refreshEditRuminantExpenseCycles') !== false, 'Ruminant Edit Expense keeps editable Production Type with dependent Cycle filtering');

$failed = array_filter($checks, fn($x) => !$x[0]);
if ($failed) { echo 'V2.2.22 verification failed: ' . count($failed) . ' check(s).' . PHP_EOL; exit(1); }
echo 'V2.2.22 verification passed: ' . count($checks) . ' check(s).' . PHP_EOL;
