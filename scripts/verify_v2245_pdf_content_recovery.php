<?php
$root=dirname(__DIR__); $checks=[]; $must=function(bool $ok,string $msg)use(&$checks){$checks[]=[$ok,$msg];};
$service=file_get_contents($root.'/includes/pdf/PdfReportService.php');
$must(!str_contains($service,'stripActionsColumns('),'central PDF engine no longer rewrites full HTML through DOMDocument');
$must(str_contains($service,"preg_replace('#<script"),'central PDF engine still strips scripts safely');
foreach(['poultry/layer_expenses.php','poultry/broiler_expenses.php','ruminant/ruminant_expenses.php'] as $file){$src=file_get_contents($root.'/'.$file);$must(str_contains($src,'<th class="no-print">Actions</th>'),"$file marks Actions no-print");}
$sales=file_get_contents($root.'/management/sales_records.php');
$must(str_contains($sales,'PDF Debt History'),'Sales exposes PDF Debt History');
$must(str_contains($sales,'debt_history_pdf.php?customer='),'Debt history uses application PDF endpoint');
$must(!str_contains($sales,"$('#printDebtBtn').on('click'"),'old browser debt print handler removed');
$debt=file_get_contents($root.'/management/debt_history_pdf.php');
$must(str_contains($debt,'PdfReportService'),'Debt history uses central PDF service');
$must(str_contains($debt,'customer_ledger_entries'),'Debt history reads canonical ledger');
$must(str_contains($debt,'payment_received'),'Debt history includes upfront cash');
$fail=0; foreach($checks as [$ok,$msg]){echo($ok?'PASS':'FAIL')." - $msg\n";if(!$ok)$fail++;} echo 'Result: '.(count($checks)-$fail).'/'.count($checks)." passed\n"; exit($fail?1:0);
