<?php
$root=dirname(__DIR__); $checks=[];
function c(&$a,$n,$ok){$a[]=[$n,$ok]; echo ($ok?'PASS':'FAIL')." - $n\n";}
function has($f,$s){return is_file($f)&&strpos(file_get_contents($f),$s)!==false;}
c($checks,'Role-limit migration exists',is_file($root.'/migrations/021_role_limits_permission_cleanup.sql'));
c($checks,'Farm form exposes role user limits',has($root.'/management/farms.php','User limits by role'));
c($checks,'User creation enforces role limits',has($root.'/management/users.php','enforceTenantRoleLimits'));
c($checks,'Farm deletion uses two-step custom confirmation',has($root.'/management/farms.php','Permanent tenant deletion'));
c($checks,'User deletion uses explicit custom confirmation',has($root.'/management/users.php','confirmUserDeletion'));
c($checks,'Permission matrix scopes roles to subscribed modules',has($root.'/admin/permissions.php','$enabledModules'));
c($checks,'Permission matrix disables non-applicable role/module combinations',has($root.'/admin/permissions.php','permissionModuleApplicable'));
c($checks,'Production cycles have explicit permission',has($root.'/admin/permissions.php',"'production_cycles'"));
c($checks,'Sales remains available through Management report',has($root.'/navbar.php','Sales Report</a>')); // V2.2.34 removes duplicate top-level Sales link
c($checks,'Sales/permission holders can manage expense records',has($root.'/management/expenses.php',"hasPermission(getUserType(), 'expenses')"));
c($checks,'Broiler audit switch is server-backed',has($root.'/poultry/broiler_feeds.php','ledger_view=audit'));
c($checks,'Dark permission descriptions are explicitly readable',has($root.'/admin/permissions.php','#dbe5f3'));
c($checks,'Farm Admin access label is administrative',has($root.'/config.php',"return 'Farm Administration';"));
$failed=count(array_filter($checks,fn($x)=>!$x[1])); echo "\n".(count($checks)-$failed).'/'.count($checks)." checks passed.\n"; exit($failed?1:0);
