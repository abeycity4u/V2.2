<?php
$root = dirname(__DIR__);
$files = [
    'layer' => file_get_contents($root . '/poultry/layer_feeds.php'),
    'broiler' => file_get_contents($root . '/poultry/broiler_feeds.php'),
    'ruminant' => file_get_contents($root . '/ruminant/ruminant_feeds_record.php'),
];
$checks = [];
foreach ($files as $name => $src) {
    $checks[$name . ' server-backed audit link'] = strpos($src, 'ledger_view=audit') !== false;
    $checks[$name . ' server-backed display selection'] = strpos($src, '$ledgerView === \'audit\' ? $transactions') !== false;
    $checks[$name . ' no stale DataTables ext.search'] = strpos($src, '$.fn.dataTable.ext.search.push') === false;
    $checks[$name . ' zero usage normalization'] = strpos($src, "((float)\$monthlySummary['used'] > 0 ? '-' : '')") !== false;
}
$pass = 0;
foreach ($checks as $label => $ok) {
    echo ($ok ? '[PASS] ' : '[FAIL] ') . $label . PHP_EOL;
    if ($ok) $pass++;
}
echo 'Passed ' . $pass . '/' . count($checks) . PHP_EOL;
exit($pass === count($checks) ? 0 : 1);
