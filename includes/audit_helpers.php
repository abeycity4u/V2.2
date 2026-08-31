<?php
if (!function_exists('audit_log_event')) {
    function audit_log_event(string $action,string $entityType,$entityId=null,array $details=[]): void {
        global $pdo;
        try {
            if(!isset($pdo) || !($pdo instanceof PDO)) return;
            $farmId=function_exists('getCurrentFarmId')?getCurrentFarmId():null;
            $userId=function_exists('getCurrentUserId')?getCurrentUserId():($_SESSION['user_id']??null);
            $stmt=$pdo->prepare('INSERT INTO v2_audit_log (farm_id,user_id,action,entity_type,entity_id,details_json,ip_address,user_agent,created_at) VALUES (?,?,?,?,?,?,?,?,NOW())');
            $stmt->execute([$farmId,$userId,$action,$entityType,$entityId===null?null:(string)$entityId,json_encode($details,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),$_SERVER['REMOTE_ADDR']??null,substr($_SERVER['HTTP_USER_AGENT']??'',0,500)]);
        } catch(Throwable $e) { if(function_exists('log_app_error')) log_app_error('audit_log_failed',['error'=>$e->getMessage(),'action'=>$action,'entity'=>$entityType]); }
    }
}
?>
