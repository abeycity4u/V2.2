<?php
$root = dirname(__DIR__);
$sync = file_get_contents($root . '/lib/daily_feed_sync.php');
$checks = [
    "function sync_daily_feed_usage" => strpos($sync, 'string $feedCategory') !== false,
    "layer category" => strpos(file_get_contents($root . '/poultry/layers_daily_record.php'), "'poultry', 'layer', 'daily_layer_record'") !== false,
    "broiler category" => strpos(file_get_contents($root . '/poultry/broiler_daily_record.php'), "'poultry', 'broiler', 'daily_broiler_record'") !== false,
    "ruminant category" => strpos(file_get_contents($root . '/ruminant/ruminant_daily_record.php'), "'ruminant', 'ruminant', 'daily_ruminant_record'") !== false,
    "category parameter used" => strpos($sync, '$feedCategory') !== false,
];
foreach ($checks as $name=>$ok) { if (!$ok) { fwrite(STDERR, "FAILED: $name\n"); exit(1); } }
echo "Daily feed category contract passed.\n";
