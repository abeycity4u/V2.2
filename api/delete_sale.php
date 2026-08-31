<?php require_once(dirname(__DIR__) . '/init.php'); ?>
<?php
require_once(__DIR__ . '/../config.php');
require_once(__DIR__ . '/api_helpers.php');
require_once(__DIR__ . '/../includes/audit_helpers.php');
require_once(__DIR__ . '/../lib/sales_allocation.php');
require_once(__DIR__ . '/../lib/sales_receivables.php');
requireLogin(); require_http_method('POST'); require_csrf_token(); require_rate_limit('delete_sale',20,60);
if (!isPlatformOwner() && !hasRole('farm_admin')) send_json(['success'=>false,'error'=>'You do not have permission to delete sale records.'],403);
$id=$_POST['id']??null;
if(!$id || !ctype_digit((string)$id)) send_json(['success'=>false,'error'=>'A valid record ID is required.'],400);
$farmId=requireCurrentFarmId();
try {
 $pdo->beginTransaction();
 $find=$pdo->prepare('SELECT * FROM sales_records WHERE id=? AND farm_id=? FOR UPDATE');
 $find->execute([(int)$id,$farmId]); $row=$find->fetch(PDO::FETCH_ASSOC);
 if(!$row) { $pdo->rollBack(); send_json(['success'=>false,'error'=>'Record not found.'],404); }
 receivable_assert_sale_deletable($pdo,$farmId,(int)$id);
 $pdo->prepare("DELETE FROM customer_ledger_entries WHERE farm_id=? AND sale_id=?")->execute([$farmId,(int)$id]);
 audit_log_event('delete','sale',$id,['before'=>$row]);
 $stmt=$pdo->prepare('DELETE FROM sales_records WHERE id=? AND farm_id=?'); $stmt->execute([(int)$id,$farmId]);
 if (($row['farm_type'] ?? '') === 'poultry' && strtolower((string)($row['production_type'] ?? '')) === 'layer' && layer_egg_is_sale_product($row['product_type'] ?? null)) {
     sales_rebuild_layer_egg_allocations($pdo,$farmId,(string)$row['sale_date'],(int)($_SESSION['user_id'] ?? 0));
 }
 $pdo->commit(); $_SESSION['success'] = 'Sale record deleted successfully.'; send_json(['success'=>true,'message'=>'Sale record deleted successfully.']);
} catch(Throwable $e) { if($pdo->inTransaction()) $pdo->rollBack(); log_app_error('delete_sale_failed',['error'=>$e->getMessage(),'id'=>$id]); send_json(['success'=>false,'error'=>'Unable to delete the record.'],500); }
?>
