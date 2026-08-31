<?php require_once(dirname(__DIR__) . '/init.php'); ?>
<?php
require_once(__DIR__ . '/../config.php');
require_once(__DIR__ . '/api_helpers.php');
require_once(__DIR__ . '/../lib/daily_feed_sync.php');
require_once(__DIR__ . '/../lib/sales_allocation.php');
requireLogin();
require_http_method('POST');
require_csrf_token();
require_rate_limit('delete_record', 30, 60);

$type = $_POST['type'] ?? null;
$id = $_POST['id'] ?? null;
if (!$type || !$id) {
    send_json(['success' => false, 'error' => 'type and id are required'], 400);
}

if (($type === 'layer' || $type === 'broiler') && !checkAccess('poultry')) {
    send_json(['success' => false, 'error' => 'Unauthorized for poultry records'], 403);
}

try {
    $farmId = requireCurrentFarmId();
    $pdo->beginTransaction();
    if ($type === 'layer') {
        $recordStmt = $pdo->prepare("SELECT record_date FROM layer_daily_records WHERE id=? AND farm_id=? LIMIT 1");
        $recordStmt->execute([(int)$id,$farmId]);
        $layerRecordDate = $recordStmt->fetchColumn();
        $stmt = $pdo->prepare("DELETE FROM layer_daily_records WHERE id = ? AND farm_id = ?");
        $sourceType = 'daily_layer_record';
    } elseif ($type === 'broiler') {
        $stmt = $pdo->prepare("DELETE FROM broiler_daily_records WHERE id = ? AND farm_id = ?");
        $sourceType = 'daily_broiler_record';
    } else {
        $pdo->rollBack();
        send_json(['success' => false, 'error' => 'Unsupported record type'], 400);
    }

    // Restore inventory consumed by the daily record before removing the record.
    delete_daily_feed_usage($pdo, $farmId, (int)$id, $sourceType);
    $stmt->execute([$id, $farmId]);
    if ($stmt->rowCount() !== 1) {
        $pdo->rollBack();
        send_json(['success' => false, 'error' => 'Record was not found or could not be deleted.'], 404);
    }
    if ($type === 'layer' && !empty($layerRecordDate)) {
        sales_rebuild_layer_egg_allocations($pdo, $farmId, (string)$layerRecordDate, (int)($_SESSION['user_id'] ?? 0));
    }
    $pdo->commit();
    $_SESSION['success'] = 'Record deleted successfully.';
    send_json(['success' => true, 'message' => 'Record deleted successfully']);
} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    log_app_error('delete_record_failed', ['error' => safe_api_exception_message($e, 'The record could not be deleted.'), 'type' => $type, 'id' => $id]);
    send_json(['success' => false, 'error' => safe_api_exception_message($e, 'The record could not be deleted.')], 400);
}
?>
