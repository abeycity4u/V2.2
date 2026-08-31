<?php
$root=dirname(__DIR__);$checks=[];function c($n,$ok){global $checks;$checks[]=[$n,$ok];echo ($ok?'PASS':'FAIL')." - $n\n";}
$r=file_get_contents($root.'/lib/sales_receivables.php');$s=file_get_contents($root.'/management/sales_records.php');$d=file_get_contents($root.'/api/delete_sale.php');
c('central receivables helper exists',str_contains($r,'receivable_sync_sale_edit'));
c('edit syncs receivable',str_contains($s,'receivable_sync_sale_edit'));
c('edit is transactional',str_contains($s,'$pdo->beginTransaction()')&&str_contains($s,'$pdo->commit()'));
c('overpayment edit protected',str_contains($r,'payments already received'));
c('sale ledger note refreshed',str_contains($r,'Total Payment: %s - Upfront: %s'));
c('customer rename follows ledger',str_contains($r,'UPDATE customer_ledger_entries SET customer_name=?'));
c('delete protects allocated payments',str_contains($d,'receivable_assert_sale_deletable'));
c('safe delete removes orphan sale ledger',str_contains($d,'DELETE FROM customer_ledger_entries'));
$bad=array_filter($checks,fn($x)=>!$x[1]);exit($bad?1:0);
