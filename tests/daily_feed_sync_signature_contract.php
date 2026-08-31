<?php
$sync = file_get_contents(__DIR__ . '/../lib/daily_feed_sync.php');
$failed = [];
if (strpos($sync, 'string $transactionDate, string $farmType, string $feedCategory, string $sourceType') === false) {
    $failed[] = 'sync signature includes transaction date before farm/category';
}
if (strpos($sync, 'stock_apply_movement(') === false || strpos($sync, '$transactionDate,') === false || strpos($sync, 'Daily record feed consumption') === false) {
    $failed[] = 'canonical stock service receives the supplied transaction date';
}
foreach ([
    'poultry/layers_daily_record.php',
    'poultry/broiler_daily_record.php',
    'ruminant/ruminant_daily_record.php'
] as $file) {
    $s = file_get_contents(__DIR__ . '/../' . $file);
    if (strpos($s, '$cycleIdForSave, $recordDate,') === false) {
        $failed[] = $file . ' does not pass transaction date to sync';
    }
}
if ($failed) {
    fwrite(STDERR, "Daily feed sync signature contract failed:\n- " . implode("\n- ", $failed) . "\n");
    exit(1);
}
echo "Daily feed sync signature contract passed.\n";
