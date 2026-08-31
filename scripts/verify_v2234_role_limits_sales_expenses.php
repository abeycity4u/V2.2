<?php
$root=dirname(__DIR__); $checks=[];
function c(&$a,$n,$ok){$a[]=[$n,(bool)$ok];}
function has($f,$s){return is_file($f)&&strpos(file_get_contents($f),$s)!==false;}
c($checks,'Role count is tenant-scoped with EXISTS',has($root.'/management/users.php',"WHERE u.farm_id=? AND u.user_type <> 'farm_admin' AND (EXISTS"));
c($checks,'Zero role limit blocks accounts',has($root.'/management/users.php','accounts are disabled for this farm'));
c($checks,'Direct top-level Sales nav removed',!has($root.'/navbar.php','<i class="bi bi-receipt"></i> Sales</a>'));
c($checks,'Management keeps Sales Report',has($root.'/navbar.php','Sales Report</a>'));
c($checks,'Layer expense remains navigable without Management duplication',has($root.'/navbar.php','> Layer Expenses</a>') && !has($root.'/navbar.php','Layer Expense Entry'));
c($checks,'Broiler expense remains navigable without Management duplication',has($root.'/navbar.php','> Broiler Expenses</a>') && !has($root.'/navbar.php','Broiler Expense Entry'));
c($checks,'Ruminant expense remains navigable without Management duplication',has($root.'/navbar.php','> Expenses</a>') && !has($root.'/navbar.php','Ruminant Expense Entry'));
c($checks,'Sales can be delegated poultry expenses in permission UI',has($root.'/admin/permissions.php',"['poultry_manager','sales_rep']"));
c($checks,'Permission save accepts sales expense delegation',has($root.'/admin/permissions_save.php',"['poultry_manager','sales_rep']"));
c($checks,'Layer expense backend honors permission',has($root.'/poultry/layer_expenses.php',"hasPermission(\$_SESSION['user_type'], 'poultry_expenses')"));
c($checks,'Broiler expense backend honors permission',has($root.'/poultry/broiler_expenses.php',"hasPermission(\$_SESSION['user_type'], 'poultry_expenses')"));
c($checks,'Ruminant expense backend honors permission',has($root.'/ruminant/ruminant_expenses.php',"hasPermission(\$_SESSION['user_type'], 'ruminant_expenses')"));
c($checks,'Sales expense defaults migration exists',is_file($root.'/migrations/022_sales_expense_delegation.sql'));
$fail=0; foreach($checks as [$n,$ok]){echo ($ok?'PASS':'FAIL')." - $n\n"; if(!$ok)$fail++;} echo count($checks)." checks; $fail failure(s).\n"; exit($fail?1:0);
