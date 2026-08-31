<?php
$root = dirname(__DIR__);
$checks = [];
function check_v2215(bool $ok, string $label): void { global $checks; $checks[] = [$ok,$label]; echo ($ok?'PASS: ':'FAIL: ').$label.PHP_EOL; }
$reporting = file_get_contents($root.'/lib/stock_reporting.php');
$financial = file_get_contents($root.'/includes/financial.php');
$profit = file_get_contents($root.'/management/profitability.php');
$layer = file_get_contents($root.'/poultry/layer_feeds.php');
$broiler = file_get_contents($root.'/poultry/broiler_feeds.php');
$ruminant = file_get_contents($root.'/ruminant/ruminant_feeds_record.php');
check_v2215(str_contains($reporting, 'function stock_effective_sql_predicate'), 'shared effective-stock SQL predicate exists');
check_v2215(str_contains($financial, 'stock_effective_sql_predicate()'), 'profitability feed cost uses shared effective-stock predicate');
check_v2215(str_contains($profit, 'stock_effective_sql_predicate()'), 'uncosted-feed check uses shared effective-stock predicate');
check_v2215(str_contains($profit, "'daily'") && str_contains($profit, "'monthly'") && str_contains($profit, 'getProfitabilitySummary'), 'profitability supports daily and monthly views through one calculation engine');
foreach (['Layer'=>$layer,'Broiler'=>$broiler,'Ruminant'=>$ruminant] as $name=>$src) {
    check_v2215(str_contains($src, "'⬆ +' : '⬇ -'"), "$name feed ledger uses consistent movement arrows");
}
$failed = count(array_filter($checks, fn($c)=>!$c[0]));
if ($failed) { echo "V2.2.15 verification failed: $failed check(s).".PHP_EOL; exit(1); }
echo 'V2.2.15 verification passed: '.count($checks).' check(s).'.PHP_EOL;
