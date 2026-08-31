<?php require_once(dirname(__DIR__) . '/init.php'); ?>
<?php
require_once(__DIR__ . '/../config.php');
require_once(__DIR__ . '/api_helpers.php');
require_once(__DIR__ . '/../includes/audit_helpers.php');
requireLogin(); require_http_method('POST'); require_csrf_token(); require_rate_limit('delete_expense',20,60);
if (!isPlatformOwner() && !hasRole('farm_admin') && !hasPermission(getUserType(), 'expenses')) send_json(['success'=>false,'error'=>'You do not have permission to delete expense records.'],403);
$id=$_POST['id']??null;
if(!$id || !ctype_digit((string)$id)) send_json(['success'=>false,'error'=>'A valid record ID is required.'],400);
$farmId=requireCurrentFarmId();
try {
 $pdo->beginTransaction();
 $find=$pdo->prepare('SELECT * FROM farm_expenses WHERE id=? AND farm_id=? FOR UPDATE');
 $find->execute([(int)$id,$farmId]); $row=$find->fetch(PDO::FETCH_ASSOC);
 if(!$row) { $pdo->rollBack(); send_json(['success'=>false,'error'=>'Record not found.'],404); }
 audit_log_event('delete','expense',$id,['before'=>$row]);
 $stmt=$pdo->prepare('DELETE FROM farm_expenses WHERE id=? AND farm_id=?'); $stmt->execute([(int)$id,$farmId]);
 $pdo->commit(); $_SESSION['success'] = 'Expense record deleted successfully.'; send_json(['success'=>true,'message'=>'Expense record deleted successfully.']);
} catch(Throwable $e) { if($pdo->inTransaction()) $pdo->rollBack(); log_app_error('delete_expense_failed',['error'=>$e->getMessage(),'id'=>$id]); send_json(['success'=>false,'error'=>'Unable to delete the record.'],500); }
?>
