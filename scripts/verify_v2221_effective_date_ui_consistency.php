<?php
$root = dirname(__DIR__);
$checks = [];
function check221($condition, $message) { global $checks; $checks[] = [$condition, $message]; echo ($condition ? 'PASS: ' : 'FAIL: ') . $message . PHP_EOL; }
$stock = file_get_contents($root . '/lib/stock_service.php');
$inventory = file_get_contents($root . '/inventory.php');
$notify = file_get_contents($root . '/navbar_head.php');
$layer = file_get_contents($root . '/poultry/layer_expenses.php');
$broiler = file_get_contents($root . '/poultry/broiler_expenses.php');
$ruminant = file_get_contents($root . '/ruminant/ruminant_expenses.php');
check221(strpos($stock, '$tx[\'transaction_date\']') !== false && strpos($stock, "date('Y-m-d'),\n        \$remark") === false, 'stock reversal uses original effective business date');
check221(strpos($layer, 'id="editExpenseCycle"') !== false && strpos($layer, 'data-cycle=') !== false, 'Layer Add/Edit expense cycle attribution is consistent');
check221(strpos($broiler, 'id="editExpenseCycle"') !== false && strpos($broiler, 'data-cycle=') !== false, 'Broiler Add/Edit expense cycle attribution is consistent');
check221(strpos($ruminant, 'id="editExpenseProduction"') !== false && strpos($ruminant, 'id="editExpenseCycle"') !== false && strpos($ruminant, 'refreshEditRuminantExpenseCycles') !== false, 'Ruminant Edit Expense preserves dependent Production Type and Cycle');
check221(strpos($inventory, 'transaction_count') !== false && strpos($inventory, 'Protected history') !== false, 'inactive inventory with audit history is protected instead of offering impossible purge');
check221(strpos($inventory, 'Usage Classification') !== false && strpos($inventory, 'General / Non-feed item') !== false, 'inventory classification wording covers feed and non-feed stock');
check221(strpos($notify, "type === 'success' ? 2500") !== false, 'success notifications auto-dismiss promptly');
$failed = array_filter($checks, fn($x) => !$x[0]);
if ($failed) { echo 'V2.2.21 verification failed: ' . count($failed) . ' check(s).' . PHP_EOL; exit(1); }
echo 'V2.2.21 verification passed: ' . count($checks) . ' check(s).' . PHP_EOL;
