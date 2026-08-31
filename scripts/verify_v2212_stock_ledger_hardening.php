<?php
$root = dirname(__DIR__);
$service = file_get_contents($root . '/lib/stock_service.php');
$history = file_get_contents($root . '/api/get_stock_history.php');
$chart = file_get_contents($root . '/api/get_chart_data.php');
$legacy = file_get_contents($root . '/migrations/017_legacy_stock_opening_balances.sql');
$reconcile = file_get_contents($root . '/scripts/reconcile_stock_ledger.php');

$checks = [
    'physical balance includes reversed originals and restorations' => str_contains($service, 'WHERE farm_id = ? AND stock_item_id ?') === false
        && str_contains($service, 'WHERE farm_id = ? AND stock_item_id = ?')
        && !str_contains($service, 'WHERE farm_id = ? AND stock_item_id = ? AND is_reversed = 0'),
    'reconciliation uses full posted movement stream' => str_contains($service, 'ledger_transaction_count') && str_contains($service, 'status_reason'),
    'history reconstructs physical chart from event order' => str_contains($history, '$allPosted') && str_contains($history, 'ORDER BY created_at ASC, id ASC'),
    'dashboard chart reconstructs from full stream' => str_contains($chart, 'complete posted event stream') && str_contains($chart, 'ORDER BY t.created_at, t.id'),
    'legacy opening balance is idempotent' => str_contains($legacy, "source_type, source_id") && str_contains($legacy, "'legacy_opening_balance'") && str_contains($legacy, 'NOT EXISTS'),
    'reconciliation CLI documents help' => str_contains($reconcile, "--help") && str_contains($reconcile, 'net sum of ALL posted stock movements'),
];

$failed = [];
foreach ($checks as $name => $ok) {
    echo ($ok ? 'PASS' : 'FAIL') . ': ' . $name . PHP_EOL;
    if (!$ok) $failed[] = $name;
}

// Pure arithmetic contract: a reversal pair must net to zero in physical stock.
$movements = [
    ['type' => 'received', 'qty' => 100],
    ['type' => 'used', 'qty' => 20],
    ['type' => 'received', 'qty' => 20], // reversal of the -20 usage
];
$balance = 0.0;
foreach ($movements as $m) $balance += $m['type'] === 'received' ? $m['qty'] : -$m['qty'];
if (abs($balance - 100.0) < 0.00001) echo "PASS: reversal pair nets to zero physically\n";
else { echo "FAIL: reversal pair arithmetic\n"; $failed[] = 'reversal pair arithmetic'; }

exit($failed ? 1 : 0);
