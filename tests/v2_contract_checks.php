<?php
function v2assert($ok,$msg){if(!$ok)throw new RuntimeException($msg);}
$root=dirname(__DIR__);
$helpers=file_get_contents($root.'/api/api_helpers.php');
v2assert(strpos($helpers,"require_http_method('POST')")===false || strpos($helpers,'require_csrf_token')!==false,'API helper must retain CSRF helper.');
foreach(['api/delete_sale.php','api/delete_expense.php','api/update_stock.php'] as $f){$s=file_get_contents($root.'/'.$f);v2assert(strpos($s,"require_http_method('POST')")!==false,"$f must require POST");v2assert(strpos($s,'require_csrf_token()')!==false,"$f must require CSRF");}
$r=file_get_contents($root.'/ruminant/ruminant_daily_record.php');v2assert(strpos($r,'verify_csrf_token')!==false,'Ruminant daily mutations must require CSRF.');v2assert(strpos($r,'count($activeCycles) > 1')!==false,'Ruminant daily records must enforce cycle selection when ambiguous.');
$m=file_get_contents($root.'/migrations/012_v2_foundation_and_ruminants.sql');foreach(['v2_audit_log','ruminant_animals','ruminant_animal_weights','ruminant_health_events'] as $t)v2assert(strpos($m,$t)!==false,"Migration must create $t.");
$v=file_get_contents($root.'/ruminant/animal_registry.php');v2assert(strpos($v,'farm_id=?')!==false,'Animal registry must scope queries to the current farm.');v2assert(strpos($v,'csrf_token')!==false,'Animal registry must use CSRF.');

$pc=file_get_contents($root.'/management/production_cycles.php');
v2assert(strpos($pc,'WHERE farm_id = ? AND cycle_code = ? LIMIT 1')!==false,'Production cycle creation must pre-check duplicate codes within the active farm.');
v2assert(strpos($pc,'Your other entries have been preserved')!==false,'Duplicate cycle errors must preserve the Create Cycle form state.');
v2assert(strpos($pc,'$e->errorInfo[1]')!==false,'Production cycle creation must safely catch a race-condition duplicate database error.');
v2assert(strpos($pc, "\$errorMessage = 'We could not load the production cycle data right now.")!==false,'Production cycle page must not expose raw database exceptions.');
v2assert(strpos($pc,"farm_id, cycle_code")!==false,'Production cycle inserts must remain tenant-scoped.');

echo "V2 contract checks passed.\n";
