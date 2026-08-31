<?php
$root = dirname(__DIR__);
$checks = [
    $root . '/lib/daily_feed_sync.php' => ['Restored from Daily Record edit', 'Restored from Daily Record deletion', 'source_type', 'source_id'],
    $root . '/poultry/layers_daily_record.php' => ['data-feed-item-id', "feedItemId: '#feedItemId'"],
    $root . '/poultry/broiler_daily_record.php' => ['data-feed-item-id', "feedItemId: '#feedItemId'"],
    $root . '/ruminant/ruminant_daily_record.php' => ['data-feed-item-id', "feedItemId: '#feedItemId'"],
    $root . '/ruminant/ruminant_feeds_record.php' => ['Monthly Transactions'],
];
foreach ($checks as $file => $needles) {
    if (!is_file($file)) { fwrite(STDERR, "Missing file: {$file}\n"); exit(1); }
    $text = file_get_contents($file);
    foreach ($needles as $needle) {
        if (strpos($text, $needle) === false) { fwrite(STDERR, "Missing contract text '{$needle}' in {$file}\n"); exit(1); }
    }
}
foreach (glob($root . '/*.php') as $file) {
    $out=[]; $code=0; exec('php -l ' . escapeshellarg($file) . ' 2>&1', $out, $code);
    if ($code !== 0) { fwrite(STDERR, implode("\n", $out) . "\n"); exit(1); }
}
echo "V2.2.9 feed ledger consistency contract passed.\n";
