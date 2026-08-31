<?php
$root = dirname(__DIR__);
$failures = [];
$required = [
    '/migrations/014_v22_financial_foundation.sql',
    '/includes/financial.php',
    '/management/profitability.php',
];
foreach ($required as $file) if (!is_file($root.$file)) $failures[]='Missing '.$file;
$sql = file_get_contents($root.'/migrations/014_v22_financial_foundation.sql');
foreach (['cycle_id','unit_cost','total_cost','financial_allocations','financial_settings'] as $needle) {
    if (strpos($sql,$needle)===false) $failures[]='Migration missing '.$needle;
}
$financial = file_get_contents($root.'/includes/financial.php');
foreach (['sales_records','farm_expenses','stock_transactions','financial_allocations','feed_consumption_cost'] as $needle) {
    if (strpos($financial,$needle)===false) $failures[]='Financial helper missing '.$needle;
}
if ($failures) { fwrite(STDERR, implode(PHP_EOL,$failures).PHP_EOL); exit(1); }
echo "V2.2 financial foundation checks passed.".PHP_EOL;
