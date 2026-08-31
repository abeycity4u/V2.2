<?php
$root = dirname(__DIR__);
$checks = [];
function check2218($ok,$label){ global $checks; $checks[]=$ok; echo ($ok?'PASS: ':'FAIL: ').$label.PHP_EOL; }
$profit=file_get_contents($root.'/management/profitability.php');
$sales=file_get_contents($root.'/management/sales_records.php');
$expenses=file_get_contents($root.'/management/expenses.php');
check2218(strpos($profit,'rebuildProduction')!==false,'profitability Farm Type rebuilds Production Type');
check2218(strpos($profit,'rebuildCycles')!==false,'profitability Production Type rebuilds Production Cycle');
check2218(strpos($profit,"production === 'all' || c.production_type === production")!==false,'profitability cycles are constrained by Production Type');
check2218(strpos($profit,"farm === 'all' || c.farm_type === farm")!==false,'profitability cycles are constrained by Farm Type');
check2218(strpos($sales,'refreshReportProductionTypes')!==false,'sales report Farm Type rebuilds Production Type');
check2218(strpos($expenses,'refreshExpenseProductionTypes')!==false,'expense report Farm Type rebuilds Production Type');
check2218(strpos($expenses,"#productionTypeFilter, #categoryFilter")!==false,'expense Production Type filter triggers reporting refresh');
if (in_array(false,$checks,true)) exit(1);
echo 'V2.2.18 verification passed: '.count($checks).' check(s).'.PHP_EOL;
