<?php
/**
 * Read-only inventory integrity report.
 *
 * Usage:
 *   php scripts/reconcile_stock_ledger.php
 *   php scripts/reconcile_stock_ledger.php --farm-id=3
 *   php scripts/reconcile_stock_ledger.php --strict
 *   php scripts/reconcile_stock_ledger.php --help
 *
 * This script deliberately does NOT repair balances. A mismatch must be
 * investigated first because legacy data may contain a bad or missing
 * transaction. Use the report to identify the affected item(s).
 */
require_once dirname(__DIR__) . '/init.php';
require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/lib/stock_service.php';

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("CLI only.\n");
}

$farmId = null;
$strict = false;
$help = false;
foreach (array_slice($argv, 1) as $arg) {
    if (str_starts_with($arg, '--farm-id=')) {
        $farmId = (int)substr($arg, 10);
    } elseif ($arg === '--strict') {
        $strict = true;
    } elseif ($arg === '--help' || $arg === '-h') {
        $help = true;
    }
}

if ($help) {
    echo "Usage: php scripts/reconcile_stock_ledger.php [--farm-id=N] [--strict] [--help]\n";
    echo "Compares stock_items.current_stock with the net sum of ALL posted stock movements.\n";
    echo "Reversal pairs are included so the original movement and its compensating reversal net to zero.\n";
    exit(0);
}

$sql = "SELECT id, name FROM farms";
$params = [];
if ($farmId !== null && $farmId > 0) {
    $sql .= " WHERE id = ?";
    $params[] = $farmId;
}
$sql .= " ORDER BY id";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$farms = $stmt->fetchAll(PDO::FETCH_ASSOC);

$total = 0;
$mismatches = 0;
foreach ($farms as $farm) {
    $rows = stock_reconciliation($pdo, (int)$farm['id']);
    foreach ($rows as $row) {
        $total++;
        if ($row['status'] === 'mismatch') {
            $mismatches++;
            echo "MISMATCH farm={$farm['id']} ({$farm['name']}) item={$row['id']} {$row['item_name']} "
               . "current={$row['current_stock']} {$row['unit']} ledger={$row['ledger_stock']} "
               . "difference={$row['difference']}\n";
        }
    }
}

echo "Checked {$total} stock item(s); {$mismatches} mismatch(es).\n";
if ($strict && $mismatches > 0) exit(2);
exit(0);
