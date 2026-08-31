<?php
$root=dirname(__DIR__); $checks=[];
function c(&$a,$n,$ok){$a[]=[$n,(bool)$ok];}
function has($f,$s){return is_file($f)&&strpos(file_get_contents($f),$s)!==false;}
$users=$root.'/management/users.php'; $nav=$root.'/navbar.php';
c($checks,'Farm Admin explicitly excluded from specialist quota',has($users,"u.user_type <> 'farm_admin'"));
c($checks,'Quota remains exact used >= max',has($users,'if($used >= $max)'));
c($checks,'Poultry parent uses child-aware visibility',has($nav,'$showPoultryMenu ='));
c($checks,'Poultry expenses can expose Poultry parent',has($nav,"|| hasPermission(\$_SESSION['user_type'], 'poultry_expenses')"));
c($checks,'Ruminant parent uses child-aware visibility',has($nav,'$showRuminantMenu ='));
c($checks,'Ruminant expenses can expose Ruminant parent',has($nav,"|| hasPermission(\$_SESSION['user_type'], 'ruminant_expenses')"));
c($checks,'Layer expense stays in Poultry menu',has($nav,'> Layer Expenses</a>'));
c($checks,'Broiler expense stays in Poultry menu',has($nav,'> Broiler Expenses</a>'));
c($checks,'Ruminant expense stays in Ruminant menu',has($nav,'> Expenses</a>'));
c($checks,'Duplicate Layer Expense Entry removed',!has($nav,'Layer Expense Entry'));
c($checks,'Duplicate Broiler Expense Entry removed',!has($nav,'Broiler Expense Entry'));
c($checks,'Duplicate Ruminant Expense Entry removed',!has($nav,'Ruminant Expense Entry'));
$fail=0; foreach($checks as [$n,$ok]){echo ($ok?'PASS':'FAIL')." - $n\n"; if(!$ok)$fail++;} echo "\n".(count($checks)-$fail).'/'.count($checks)." checks passed.\n"; exit($fail?1:0);
