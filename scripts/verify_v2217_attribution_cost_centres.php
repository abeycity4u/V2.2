<?php
$root=dirname(__DIR__); $checks=[];
function c17(bool $ok,string $label):void{global $checks;$checks[]=[$ok,$label];echo($ok?'PASS: ':'FAIL: ').$label.PHP_EOL;}
$m=file_get_contents($root.'/migrations/018_attribution_cost_centres.sql');
$a=file_get_contents($root.'/lib/attribution.php');
$s=file_get_contents($root.'/management/sales_records.php');
$p=file_get_contents($root.'/management/profitability.php');
$f=file_get_contents($root.'/includes/financial.php');
$stock=file_get_contents($root.'/lib/stock_service.php');
$layer=file_get_contents($root.'/poultry/layer_feeds.php');
$broiler=file_get_contents($root.'/poultry/broiler_feeds.php');
$rum=file_get_contents($root.'/ruminant/ruminant_feeds_record.php');
$rexp=file_get_contents($root.'/ruminant/ruminant_expenses.php');
c17(str_contains($m,'ADD COLUMN production_type')&&str_contains($m,'attribution_scope')&&str_contains($m,'sales_allocations'),'migration creates durable attribution fields and future sales allocation table');
c17(str_contains($a,"'poultry'")&&str_contains($a,"'ruminant'")&&str_contains($a,"'general'")&&str_contains($a,'attribution_validate_cycle'),'shared attribution helper covers Poultry, Ruminant and General');
c17(str_contains($s,'name="production_type"')&&str_contains($s,'name="cycle_id"')&&(str_contains($s,'Pooled / multiple cycles')||str_contains($s,'Shared between cycles')),'sales capture production type and optional pooled/cycle attribution');
c17(str_contains($s,"value=\"general\"")&&str_contains($s,'productionTypeFilter'),'sales reporting preserves General and production-type filtering');
c17(str_contains($p,'Production type')&&str_contains($p,'General')&&str_contains($p,'production_type'),'profitability filters Farm Type -> Production Type -> Cycle including General');
c17(str_contains($f,'production_type=?')&&str_contains($f,'sales_allocations')&&str_contains($f,'financial_allocations'),'profitability engine respects production type and explicit cycle allocations');
c17(str_contains($stock,'production_type')&&str_contains($stock,'attribution_scope'),'stock movements snapshot operational ownership');
foreach(['Layer'=>$layer,'Broiler'=>$broiler,'Ruminant'=>$rum] as $name=>$src)c17(str_contains($src,'Production Type')&&str_contains($src,'Pooled / Unallocated')&&str_contains($src,'cycle_code'),"$name feed ledger shows production type and consuming cycle");
c17(str_contains($rexp,'ruminantExpenseProduction')&&str_contains($rexp,'Production Cycle (optional)'),'ruminant expenses can distinguish species or remain shared');
$failed=count(array_filter($checks,fn($x)=>!$x[0])); if($failed){echo"V2.2.17 verification failed: $failed check(s).\n";exit(1);} echo 'V2.2.17 verification passed: '.count($checks).' check(s).'.PHP_EOL;
