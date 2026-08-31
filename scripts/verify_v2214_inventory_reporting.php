<?php
$root = dirname(__DIR__);
$failures = [];

function check_v2214(bool $condition, string $label, array &$failures): void {
    if ($condition) {
        echo "PASS: {$label}\n";
    } else {
        echo "FAIL: {$label}\n";
        $failures[] = $label;
    }
}

$inventory = file_get_contents($root . '/inventory.php');
check_v2214(strpos($inventory, "lib/stock_service.php") !== false, 'inventory loads canonical stock service', $failures);

$reporting = file_get_contents($root . '/lib/stock_reporting.php');
check_v2214(strpos($reporting, "is_reversed") !== false && strpos($reporting, "reversal_of_id") !== false,
    'effective summary excludes reversed originals and reversal rows', $failures);

foreach (['poultry/layer_feeds.php','poultry/broiler_feeds.php','ruminant/ruminant_feeds_record.php'] as $file) {
    $src = file_get_contents($root . '/' . $file);
    check_v2214(strpos($src, 'stock_effective_movement_summary($transactions)') !== false,
        basename($file) . ' uses canonical effective summary', $failures);
    check_v2214(strpos($src, 'stock_effective_summary_unit_label($transactions)') !== false,
        basename($file) . ' uses effective summary units', $failures);
}

require_once $root . '/lib/stock_reporting.php';
$rows = [
    ['transaction_type'=>'received','quantity'=>100,'is_reversed'=>0,'reversal_of_id'=>null,'unit'=>'Bgs'],
    ['transaction_type'=>'used','quantity'=>20,'is_reversed'=>1,'reversal_of_id'=>null,'unit'=>'Bgs'],
    ['transaction_type'=>'received','quantity'=>20,'is_reversed'=>0,'reversal_of_id'=>2,'unit'=>'Bgs'],
    ['transaction_type'=>'used','quantity'=>15,'is_reversed'=>0,'reversal_of_id'=>null,'unit'=>'Bgs'],
];
$summary = stock_effective_movement_summary($rows);
check_v2214($summary['received'] === 100.0 && $summary['used'] === 15.0 && $summary['balance'] === 85.0,
    'reversal pair does not inflate monthly received/used cards', $failures);

if ($failures) {
    exit(1);
}
