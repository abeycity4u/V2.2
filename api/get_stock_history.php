<?php require_once(dirname(__DIR__) . '/init.php'); ?>
<?php
require_once(__DIR__ . '/../config.php');
require_once(__DIR__ . '/../lib/stock_service.php');
requireLogin();
header('Content-Type: application/json');

if (!isset($_GET['item_id'])) {
    echo json_encode(['error' => 'Item ID required']);
    exit;
}

try {
    $farmId = requireCurrentFarmId();
    $itemId = (int)$_GET['item_id'];
    $days = max(1, min(3650, (int)($_GET['days'] ?? 30)));
    $dateLimit = date('Y-m-d', strtotime("-{$days} days"));

    $itemStmt = $pdo->prepare("SELECT * FROM stock_items WHERE id = ? AND farm_id = ?");
    $itemStmt->execute([$itemId, $farmId]);
    $item = $itemStmt->fetch(PDO::FETCH_ASSOC);
    if (!$item) {
        echo json_encode(['error' => 'Inventory item not found.']);
        exit;
    }

    // Keep the full audit trail. Physical balances use every posted movement;
    // reversal pairs cancel each other mathematically. Active-only filtering is
    // reserved for operational consumption/cost summaries.
    $query = "SELECT t.*, s.item_name, s.unit, u.full_name
              FROM stock_transactions t
              JOIN stock_items s ON t.stock_item_id = s.id AND s.farm_id = t.farm_id
              LEFT JOIN users u ON t.user_id = u.id AND u.farm_id = t.farm_id
              WHERE t.stock_item_id = ? AND t.farm_id = ? AND t.transaction_date >= ?
              ORDER BY t.created_at DESC, t.id DESC";
    $stmt = $pdo->prepare($query);
    $stmt->execute([$itemId, $farmId, $dateLimit]);
    $transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $activeOperational = array_values(array_filter($transactions, static fn($tx) =>
        (int)($tx['is_reversed'] ?? 0) === 0 && (int)($tx['reversal_of_id'] ?? 0) === 0
    ));

    $summary = [
        'total_received' => 0,
        'total_used' => 0,
        'transaction_count' => count($transactions),
        'active_transaction_count' => count($activeOperational),
        'last_transaction' => $transactions[0] ?? null,
    ];
    foreach ($activeOperational as $tx) {
        if ($tx['transaction_type'] === 'received') $summary['total_received'] += (float)$tx['quantity'];
        else $summary['total_used'] += (float)$tx['quantity'];
    }
    $summary['total_received'] = round($summary['total_received'], 2);
    $summary['total_used'] = round($summary['total_used'], 2);

    $expected = stock_expected_balance($pdo, $farmId, $itemId);
    $current = round((float)$item['current_stock'], 2);
    $summary['ledger_expected_stock'] = $expected;
    $summary['stock_difference'] = round($current - $expected, 2);
    $summary['integrity_status'] = abs($summary['stock_difference']) < 0.005 ? 'reconciled' : 'mismatch';

    // Build a physically accurate chart from the complete event stream. We do
    // not trust new_stock snapshots because backdated records and reversals can
    // make those snapshots non-linear by business date. created_at/id is the
    // authoritative posting order for reconstructing the running balance.
    $allStmt = $pdo->prepare("SELECT id, transaction_type, quantity, transaction_date, created_at
                              FROM stock_transactions
                              WHERE stock_item_id = ? AND farm_id = ?
                              ORDER BY created_at ASC, id ASC");
    $allStmt->execute([$itemId, $farmId]);
    $allPosted = $allStmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([

        'transactions' => $transactions,
        'current_stock' => $current,
        'chart_data' => prepareChartData($allPosted, $dateLimit),
        'summary' => $summary,
        'integrity' => [
            'status' => $summary['integrity_status'],
            'current_stock' => $current,
            'ledger_expected_stock' => $expected,
            'difference' => $summary['stock_difference'],
        ],
    ]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => function_exists('safe_api_exception_message')
        ? safe_api_exception_message($e, 'Unable to load stock history.')
        : 'Unable to load stock history.']);
}

function prepareChartData(array $allPostedTransactions, string $dateLimit): array
{
    $running = 0.0;
    $pointsByDate = [];
    foreach ($allPostedTransactions as $trans) {
        $qty = (float)$trans['quantity'];
        $running += $trans['transaction_type'] === 'received' ? $qty : -$qty;
        $date = (string)$trans['transaction_date'];
        if ($date >= $dateLimit) {
            // Multiple posted events can share a business date. The latest
            // posted event for that date is the end-of-day physical balance.
            $pointsByDate[$date] = round($running, 2);
        }
    }
    ksort($pointsByDate);
    $labels = array_map(static fn($date) => date('d M', strtotime($date)), array_keys($pointsByDate));
    return [
        'labels' => $labels,
        'datasets' => [[
            'label' => 'Stock Level',
            'data' => array_values($pointsByDate),
            'borderColor' => 'rgb(75, 192, 192)',
            'backgroundColor' => 'rgba(75, 192, 192, 0.2)',
            'fill' => true
        ]]
    ];
}
?>
