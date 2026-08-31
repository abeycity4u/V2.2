<?php
$root=dirname(__DIR__); $checks=[];
function c(&$a,$n,$ok){$a[]=[$n,$ok]; echo ($ok?'PASS':'FAIL')." - $n\n";}
function has($f,$s){return is_file($f)&&strpos(file_get_contents($f),$s)!==false;}
foreach(['poultry/layer_feeds.php','ruminant/ruminant_feeds_record.php'] as $f){
 c($checks,"$f has server ledger_view",has("$root/$f",'$ledgerView ='));
 c($checks,"$f filters display transactions server-side",has("$root/$f",'$displayTransactions ='));
 c($checks,"$f renders display transactions",has("$root/$f",'foreach ($displayTransactions as $trans)'));
 c($checks,"$f uses server links",has("$root/$f",'ledger_view=operational')&&has("$root/$f",'ledger_view=audit'));
}
c($checks,'Role limit message reports exact role and usage',has("$root/management/users.php",'account(s) already have this role'));
c($checks,'Add User shows used/max role counts',has("$root/management/users.php",'/<?php echo (int)$tenantRoleLimits[$code]; ?> used'));
$fail=count(array_filter($checks,fn($x)=>!$x[1])); echo "\n".(count($checks)-$fail).'/'.count($checks)." checks passed.\n"; exit($fail?1:0);
