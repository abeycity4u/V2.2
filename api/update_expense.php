<?php require_once(dirname(__DIR__) . '/init.php'); ?>
<?php
require_once(__DIR__ . '/../config.php');
require_once(__DIR__ . '/api_helpers.php');
requireLogin();
require_once(__DIR__ . '/../includes/functions.php');
require_once(__DIR__ . '/../lib/attribution.php');
require_http_method('POST');
require_csrf_token();
require_rate_limit('update_expense', 60, 60);

if (!isPlatformOwner() && !hasRole('farm_admin') && !hasPermission(getUserType(), 'expenses')) {
    send_json(['success' => false, 'error' => 'Unauthorized: Only owners can edit expenses.'], 403);
}

$requiredFields = ['expense_id', 'expense_date', 'farm_type', 'category', 'amount', 'unit'];

foreach ($requiredFields as $field) {
    if (empty($_POST[$field])) {
        send_json(['success' => false, 'error' => 'Missing required field: ' . $field], 400);
    }
}

$expenseId = $_POST['expense_id'];
$expenseDate = $_POST['expense_date'];
$farmType = $_POST['farm_type'];
if (!in_array($farmType, array_unique(array_merge(allowedFarmTypes(), ['general'])), true)) {
    send_json(['success' => false, 'error' => 'That farm type is not enabled for this farm.'], 422);
}
$poultryCategory = $_POST['poultry_category'] ?? null;
$category = $_POST['category'];
$amount = $_POST['amount'];
$unit = $_POST['unit'];
$description = $_POST['description'] ?? '';

try {
    $farmId=requireCurrentFarmId();
    $existingStmt=$pdo->prepare("SELECT production_type,cycle_id FROM farm_expenses WHERE id=? AND farm_id=? LIMIT 1");
    $existingStmt->execute([$expenseId,$farmId]);
    $existing=$existingStmt->fetch(PDO::FETCH_ASSOC) ?: [];
    $requestedProduction=$_POST['production_type'] ?? ($existing['production_type'] ?? null);
    if ($farmType==='poultry' && in_array((string)$poultryCategory,['layer','broiler'],true)) $requestedProduction=$poultryCategory;
    $productionType=attribution_normalize_production_type($farmType,$requestedProduction);
    $cycleId=(int)($_POST['cycle_id'] ?? ($existing['cycle_id'] ?? 0));
    if ($cycleId>0) attribution_validate_cycle($pdo,$farmId,$cycleId,$farmType,$productionType);
    $scope=attribution_scope($cycleId>0?$cycleId:null,$farmType,$productionType);
    $stmt = $pdo->prepare("UPDATE farm_expenses
                           SET expense_date=?, farm_type=?, production_type=?, attribution_scope=?, cycle_id=?, poultry_category=?, category=?, amount=?, unit=?, description=?
                           WHERE id=? AND farm_id=?");
    $stmt->execute([$expenseDate,$farmType,$productionType,$scope,$cycleId>0?$cycleId:null,$poultryCategory,$category,$amount,$unit,$description,$expenseId,$farmId]);

    $_SESSION['success'] = 'Expense updated successfully.'; send_json(['success' => true, 'message' => 'Expense updated successfully']);
} catch (Exception $e) {
    log_app_error('update_expense_failed', ['error' => safe_api_exception_message($e, 'The expense could not be updated.'), 'expense_id' => $expenseId ?? null]);
    send_json(['success' => false, 'error' => safe_api_exception_message($e, 'The expense could not be updated.')], 500);
}
?>
