<?php
$root=dirname(__DIR__); $checks=[];
function c(&$a,$n,$ok){$a[]=[$n,(bool)$ok];}
function has($f,$s){return is_file($f)&&strpos(file_get_contents($f),$s)!==false;}
$fn=$root.'/includes/functions.php'; $nav=$root.'/navbar.php';
c($checks,'Sales role exception exists for poultry expenses',has($fn,'$delegatedPoultryExpense = $module === \'poultry_expenses\' && hasRole(\'sales_rep\')'));
c($checks,'Sales role exception exists for ruminant expenses',has($fn,'$delegatedRuminantExpense = $module === \'ruminant_expenses\' && hasRole(\'sales_rep\')'));
c($checks,'Generic poultry role guard respects delegated expense exception',has($fn,'!hasRole(\'poultry_manager\') && !$delegatedPoultryExpense'));
c($checks,'Generic ruminant role guard respects delegated expense exception',has($fn,'!hasRole(\'ruminant_manager\') && !$delegatedRuminantExpense'));
c($checks,'Poultry parent can be exposed by expense permission',has($nav,'hasPermission($_SESSION[\'user_type\'], \'poultry_expenses\')'));
c($checks,'Ruminant parent can be exposed by expense permission',has($nav,'hasPermission($_SESSION[\'user_type\'], \'ruminant_expenses\')'));
c($checks,'Layer expense backend honors delegated permission',has($root.'/poultry/layer_expenses.php','hasPermission($_SESSION[\'user_type\'], \'poultry_expenses\')'));
c($checks,'Broiler expense backend honors delegated permission',has($root.'/poultry/broiler_expenses.php','hasPermission($_SESSION[\'user_type\'], \'poultry_expenses\')'));
c($checks,'Ruminant expense backend honors delegated permission',has($root.'/ruminant/ruminant_expenses.php','hasPermission($_SESSION[\'user_type\'], \'ruminant_expenses\')'));
$fail=0; foreach($checks as [$n,$ok]){echo ($ok?'PASS':'FAIL')." - $n\n"; if(!$ok)$fail++;} echo "\n".(count($checks)-$fail).'/'.count($checks)." checks passed.\n"; exit($fail?1:0);
