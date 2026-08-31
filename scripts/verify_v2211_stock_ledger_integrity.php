<?php
$root = dirname(__DIR__);
$service = file_get_contents($root . '/lib/stock_service.php');
$daily = file_get_contents($root . '/lib/daily_feed_sync.php');
$manual = file_get_contents($root . '/lib/manual_feed_transactions.php');
$migration = file_get_contents($root . '/migrations/016_stock_ledger_integrity.sql');
$legacyMigration = file_get_contents($root . '/migrations/017_legacy_stock_opening_balances.sql');
$history = file_get_contents($root . '/api/get_stock_history.php');
$chart = file_get_contents($root . '/api/get_chart_data.php');
$dashboard = file_get_contents($root . '/dashboard.php');
$inventory = file_get_contents($root . '/inventory.php');
$financial = file_get_contents($root . '/includes/financial.php');
$profitability = file_get_contents($root . '/management/profitability.php');

$checks = [
    'canonical service exists' => str_contains($service, 'function stock_apply_movement') && str_contains($service, 'function stock_reverse_transaction'),
    'ledger reconciliation exists' => str_contains($service, 'function stock_reconciliation') && str_contains($service, 'difference'),
    'daily feed uses canonical service' => str_contains($daily, 'stock_apply_movement(') && str_contains($daily, 'stock_reverse_transaction('),
    'manual feed uses canonical service' => str_contains($manual, 'stock_apply_movement(') && str_contains($manual, 'stock_reverse_transaction('),
    'manual deletion is reversal based' => str_contains($manual, 'delete_manual_feed_transaction') && str_contains($manual, 'stock_reverse_transaction('),
    'ledger indexes added' => str_contains($migration, 'idx_stock_tx_item_event') && str_contains($migration, 'idx_stock_tx_item_effective'),
    'legacy opening balances are protected' => str_contains($legacyMigration, 'legacy_opening_balance') && str_contains($legacyMigration, 'NOT EXISTS'),
    'history uses full physical ledger for balances' => str_contains($history, 'stock_expected_balance') && str_contains($history, 'allPosted'),
    'dashboard excludes reversed originals' => str_contains($dashboard, 't.is_reversed = 0'),
    'chart reconstructs physical balance from full event stream' => str_contains($chart, 'Reconstruct physical stock') && str_contains($chart, 'ORDER BY t.created_at, t.id'),
    'inventory no longer purges ledger rows' => !str_contains($inventory, 'DELETE FROM stock_transactions WHERE stock_item_id = ? AND farm_id = ?'),
    'financial feed cost excludes reversal rows' => str_contains($financial, 'is_reversed = 0 AND reversal_of_id IS NULL'),
    'profitability uncosted check excludes reversal rows' => str_contains($profitability, 'is_reversed = 0 AND reversal_of_id IS NULL'),
];
$failed = [];
foreach ($checks as $name => $ok) {
    echo ($ok ? 'PASS' : 'FAIL') . ': ' . $name . PHP_EOL;
    if (!$ok) $failed[] = $name;
}

foreach (glob($root . '/lib/*.php') as $file) {
    $out = []; $code = 0;
    exec('php -l ' . escapeshellarg($file) . ' 2>&1', $out, $code);
    if ($code !== 0) {
        echo "FAIL: PHP syntax {$file}\n" . implode("\n", $out) . PHP_EOL;
        $failed[] = $file;
    }
}

exit($failed ? 1 : 0);
