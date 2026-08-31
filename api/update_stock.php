<?php require_once(dirname(__DIR__) . '/init.php'); ?>
<?php
require_once(__DIR__ . '/../config.php');
require_once(__DIR__ . '/api_helpers.php');
require_once(__DIR__ . '/../includes/audit_helpers.php');
require_once(__DIR__ . '/../lib/stock_service.php');
requireLogin();
require_http_method('POST');
require_csrf_token();
require_rate_limit('update_stock', 80, 60);

$data = json_input();
if (!isset($data['item_id'], $data['type'], $data['quantity'])) {
    send_json(['success' => false, 'error' => 'Missing required fields'], 400);
}

try {
    $farmId = requireCurrentFarmId();
    $itemId = (int)$data['item_id'];
    $type = (string)$data['type'];
    $quantity = (float)$data['quantity'];
    if ($itemId <= 0 || !is_finite($quantity) || $quantity <= 0 || $quantity > 100000000) {
        throw new RuntimeException('Quantity must be a positive number within the allowed range.');
    }
    if (!in_array($type, ['received', 'used'], true)) {
        throw new RuntimeException('Invalid transaction type.');
    }

    $pdo->beginTransaction();
    $itemStmt = $pdo->prepare("SELECT * FROM stock_items WHERE id = ? AND farm_id = ? FOR UPDATE");
    $itemStmt->execute([$itemId, $farmId]);
    $item = $itemStmt->fetch(PDO::FETCH_ASSOC);
    if (!$item) throw new RuntimeException('Inventory item not found.');

    $newStock = null;
    // The canonical service performs the authoritative balance update.
    $txId = stock_apply_movement(
        $pdo,
        $farmId,
        $itemId,
        $type,
        $quantity,
        trim((string)($data['transaction_date'] ?? date('Y-m-d'))),
        $data['remarks'] ?? null,
        (int)$_SESSION['user_id'],
        null,
        null,
        null,
        'inventory_api',
        null,
        $type === 'received' && isset($data['unit_cost']) && $data['unit_cost'] !== ''
            ? (float)$data['unit_cost']
            : null
    );

    $stockStmt = $pdo->prepare("SELECT current_stock FROM stock_items WHERE id = ? AND farm_id = ?");
    $stockStmt->execute([$itemId, $farmId]);
    $newStock = (float)$stockStmt->fetchColumn();

    audit_log_event('stock_update', 'stock_item', $itemId, [
        'transaction_id' => $txId,
        'transaction_type' => $type,
        'quantity' => $quantity,
        'new_stock' => $newStock,
        'source' => 'inventory_api'
    ]);

    $pdo->commit();
    send_json([
        'success' => true,
        'message' => 'Stock updated successfully',
        'transaction_id' => $txId,
        'new_stock' => $newStock
    ]);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    log_app_error('update_stock_failed', ['error' => safe_api_exception_message($e, 'The inventory stock could not be updated.'), 'payload' => $data]);
    send_json([
        'success' => false,
        'error' => safe_api_exception_message($e, 'The inventory stock could not be updated.')
    ], 400);
}
?>
