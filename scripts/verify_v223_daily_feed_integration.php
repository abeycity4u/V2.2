<?php
$root = dirname(__DIR__);
$checks = [
    'migrations/015_daily_feed_integration.sql',
    'lib/daily_feed_sync.php',
    'poultry/layers_daily_record.php',
    'poultry/broiler_daily_record.php',
    'ruminant/ruminant_daily_record.php',
    'api/delete_record.php',
];
foreach ($checks as $file) {
    if (!is_file($root . '/' . $file)) { fwrite(STDERR, "Missing: $file\n"); exit(1); }
}
$expect = [
    'poultry/layers_daily_record.php' => ['feed_item_id', 'sync_daily_feed_usage'],
    'poultry/broiler_daily_record.php' => ['feed_item_id', 'sync_daily_feed_usage'],
    'ruminant/ruminant_daily_record.php' => ['feed_item_id', 'feed_consumption_unit', 'sync_daily_feed_usage'],
    'api/delete_record.php' => ['delete_daily_feed_usage'],
    'lib/daily_feed_sync.php' => ['sync_daily_feed_usage', 'delete_daily_feed_usage', 'source_type', 'source_id'],
    'migrations/015_daily_feed_integration.sql' => ['layer_daily_records', 'broiler_daily_records', 'ruminant_daily_records', 'feed_item_id'],
];
foreach ($expect as $file => $needles) {
    $text = file_get_contents($root . '/' . $file);
    foreach ($needles as $needle) {
        if (strpos($text, $needle) === false) { fwrite(STDERR, "Missing '$needle' in $file\n"); exit(1); }
    }
}
foreach (['poultry/layers_daily_record.php','poultry/broiler_daily_record.php','ruminant/ruminant_daily_record.php'] as $file) {
    $text=file_get_contents($root.'/'.$file);
    if (substr_count($text, 'name="feed_item_id"') !== 1) { fwrite(STDERR, "Expected one daily feed selector in $file\n"); exit(1); }
}
echo "V2.2 daily feed integration contract passed.\n";
