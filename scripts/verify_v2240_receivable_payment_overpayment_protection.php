<?php
$root=dirname(__DIR__); $checks=[];
$must=function($ok,$msg)use(&$checks){$checks[]=[$ok,$msg];};
$m=file_get_contents($root.'/management/sales_records.php');
$must(str_contains($m, '$customerOutstanding = 0.0;'), 'general payments calculate allocatable customer outstanding');
$must(str_contains($m, 'This customer has no outstanding balance to settle.'), 'zero-balance customer payments are rejected server-side');
$must(str_contains($m, 'Payment is greater than customer outstanding balance'), 'general overpayments are rejected server-side');
$must(str_contains($m, 'Payment is greater than selected sale outstanding'), 'specific-sale overpayments remain rejected server-side');
$must(str_contains($m, 'No advance payment was recorded.'), 'unallocated remainder fails closed instead of becoming advance credit');
$must(!str_contains($m, 'Advance payment (no open sale to allocate)'), 'legacy negative-receivable advance insertion path is removed');
$must(str_contains($m, 'Outstanding available to settle:'), 'payment modal shows available outstanding');
$must(str_contains($m, "selectedCustomerBalance <= 0) ? 'disabled'"), 'payment UI disables zero-balance submission for selected customer');
foreach($checks as [$ok,$msg]) echo ($ok?'PASS':'FAIL')." - $msg\n";
$fail=count(array_filter($checks,fn($x)=>!$x[0])); echo "Result: ".(count($checks)-$fail)."/".count($checks)." passed\n"; exit($fail?1:0);
