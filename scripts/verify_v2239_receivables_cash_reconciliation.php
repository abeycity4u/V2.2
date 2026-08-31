<?php
$root=dirname(__DIR__); $checks=[];
$must=function($ok,$msg)use(&$checks){$checks[]=[$ok,$msg];};
$s=file_get_contents($root.'/lib/sales_receivables.php');
$m=file_get_contents($root.'/management/sales_records.php');
$d=file_get_contents($root.'/api/delete_sale.php');
$g=file_get_contents($root.'/migrations/023_sales_receivable_cash_snapshot.sql');
$must(str_contains($g,'payment_received DECIMAL(14,2)'), 'migration adds immutable sale-time cash snapshot');
$must(str_contains($s,"payment_received']"), 'receivable state prefers cash snapshot');
$must(str_contains($s,'$debt=max(0.0,$newTotal-$upfront)'), 'credit is derived from revised sale less upfront cash');
$must(str_contains($s,"['payments'];"), 'overpayment guard includes later settlements');
$must(str_contains($m,'edit_payment_received'), 'edit form submits verified upfront cash');
$must(str_contains($m,'Later Debt Payments'), 'edit form exposes later settlements');
$must(str_contains($m,'Overpayment detected'), 'edit UI warns before unsafe reduction');
$must(str_contains($m,'payment_received, customer_name'), 'new sales persist upfront snapshot');
$must(str_contains($d,'receivable_assert_sale_deletable'), 'sale deletion remains settlement-protected');
$must(str_contains($m,'selectedCustomerGrandTotalPaid = $selectedCustomerUpfrontPayments + $selectedCustomerTotalPayments'), 'paid summary includes upfront plus debt settlements');
foreach($checks as [$ok,$msg]) echo ($ok?'PASS':'FAIL')." - $msg\n";
$fail=count(array_filter($checks,fn($x)=>!$x[0])); echo "Result: ".(count($checks)-$fail)."/".count($checks)." passed\n"; exit($fail?1:0);
