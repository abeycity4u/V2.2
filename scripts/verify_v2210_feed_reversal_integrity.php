<?php
$root = dirname(__DIR__);
$sync = file_get_contents($root . '/lib/daily_feed_sync.php');
$service = file_get_contents($root . '/lib/stock_service.php');
$financial = file_get_contents($root . '/includes/financial.php');
$profitability = file_get_contents($root . '/management/profitability.php');
$migration = file_get_contents($root . '/migrations/015_feed_reversal_audit.sql');

$checks = [
    'schema has reversal state' => str_contains($migration, 'is_reversed TINYINT(1) NOT NULL DEFAULT 0'),
    'schema links reversal' => str_contains($migration, 'reversal_of_id INT NULL'),
    'active daily usage lookup excludes reversed rows' => str_contains($sync, "transaction_type = 'used' AND is_reversed = 0"),
    'original transaction is preserved' => str_contains($service, 'SET is_reversed = 1, reversal_of_id = ?, reversed_at = NOW()'),
    'reversal is linked to original' => str_contains($service, 'reversal_of_id') && str_contains($service, '(int)$tx[\'id\']'),
    'edit reversal is append-only' => !str_contains($sync, 'DELETE FROM stock_transactions WHERE id = ? AND farm_id = ?'),
    'financial cost ignores reversed usage and reversals' => str_contains($financial, "transaction_type='used' AND is_reversed = 0 AND reversal_of_id IS NULL"),
    'uncosted check ignores reversed usage and reversals' => str_contains($profitability, "transaction_type='used' AND is_reversed = 0 AND reversal_of_id IS NULL"),
];
$failed = [];
foreach ($checks as $name => $ok) {
    echo ($ok ? 'PASS' : 'FAIL') . ': ' . $name . PHP_EOL;
    if (!$ok) $failed[] = $name;
}
exit($failed ? 1 : 0);
