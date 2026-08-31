<?php
/**
 * Canonical sales/receivables integrity helpers.
 * payment_received on sales_records is the sale-time cash snapshot.
 * Customer ledger sale rows are derived receivables; payment rows are cash facts.
 */
function receivable_sale_state(PDO $pdo,int $farmId,int $saleId): array {
    $s=$pdo->prepare("SELECT id,quantity,unit_price,payment_received,customer_name,sale_date,product_type FROM sales_records WHERE id=? AND farm_id=? LIMIT 1");
    $s->execute([$saleId,$farmId]);
    $sale=$s->fetch(PDO::FETCH_ASSOC);
    if(!$sale) throw new RuntimeException('Sale record not found.');

    $l=$pdo->prepare("SELECT * FROM customer_ledger_entries WHERE farm_id=? AND sale_id=? ORDER BY id");
    $l->execute([$farmId,$saleId]);
    $rows=$l->fetchAll(PDO::FETCH_ASSOC);
    $saleEntry=null; $payments=0.0;
    foreach($rows as $r){
        if(($r['entry_type']??'')==='sale' && !$saleEntry) $saleEntry=$r;
        elseif(($r['entry_type']??'')==='payment') $payments+=abs((float)$r['amount']);
    }

    $oldTotal=(float)$sale['quantity']*(float)$sale['unit_price'];
    $oldDebt=$saleEntry?(float)$saleEntry['amount']:0.0;
    $hasCashSnapshot=$sale['payment_received']!==null;
    $upfront=$hasCashSnapshot ? (float)$sale['payment_received'] : max(0.0,$oldTotal-$oldDebt);
    $outstanding=max(0.0,$oldDebt-$payments);
    return compact('sale','saleEntry','payments','oldTotal','oldDebt','upfront','outstanding','hasCashSnapshot');
}

function receivable_sync_sale_edit(PDO $pdo,int $farmId,int $saleId,float $newTotal,string $customer,string $date,string $product,float $qty,int $userId,?float $postedUpfront=null): array {
    $st=receivable_sale_state($pdo,$farmId,$saleId);
    $upfront=$postedUpfront===null ? $st['upfront'] : $postedUpfront;
    if($upfront < -0.00001 || $upfront > $newTotal+0.00001) throw new RuntimeException('Upfront payment must be between ₦0.00 and the revised sale total.');
    $paid=$upfront+$st['payments'];
    if($newTotal+0.00001<$paid) throw new RuntimeException('Cannot reduce this sale below payments already received (₦'.number_format($paid,2).'). Resolve or reverse the excess payment first.');

    $hasHistory=$st['saleEntry'] || $st['payments']>0.00001;
    if($hasHistory && $customer==='') throw new RuntimeException('Customer name is required because this sale has receivable history.');

    $debt=max(0.0,$newTotal-$upfront);
    $outstanding=max(0.0,$debt-$st['payments']);

    if($st['saleEntry']) {
        $note=sprintf('Sale | %s - %s Qty | Total Payment: %s - Upfront: %s',$product,number_format($qty,2),number_format($newTotal,2),number_format($upfront,2));
        $u=$pdo->prepare("UPDATE customer_ledger_entries SET customer_name=?,entry_date=?,amount=?,notes=? WHERE id=? AND farm_id=?");
        $u->execute([$customer,$date,$debt,$note,(int)$st['saleEntry']['id'],$farmId]);
    } elseif($debt>0.00001) {
        if($customer==='') throw new RuntimeException('Customer name is required for a credit/partial sale.');
        $note=sprintf('Sale | %s - %s Qty | Total Payment: %s - Upfront: %s',$product,number_format($qty,2),number_format($newTotal,2),number_format($upfront,2));
        $u=$pdo->prepare("INSERT INTO customer_ledger_entries (farm_id,customer_name,entry_date,entry_type,amount,sale_id,notes,user_id) VALUES (?,?,?,'sale',?,?,?,?)");
        $u->execute([$farmId,$customer,$date,$debt,$saleId,$note,$userId]);
    }

    if($customer!==($st['sale']['customer_name']??'')) {
        $u=$pdo->prepare("UPDATE customer_ledger_entries SET customer_name=? WHERE farm_id=? AND sale_id=?");
        $u->execute([$customer,$farmId,$saleId]);
    }
    $u=$pdo->prepare("UPDATE sales_records SET payment_received=? WHERE id=? AND farm_id=?");
    $u->execute([$upfront,$saleId,$farmId]);
    return ['upfront'=>$upfront,'payments'=>$st['payments'],'credit'=>$debt,'outstanding'=>$outstanding,'legacy_reconciled'=>!$st['hasCashSnapshot']];
}

function receivable_assert_sale_deletable(PDO $pdo,int $farmId,int $saleId): void {
    $q=$pdo->prepare("SELECT COUNT(*) FROM customer_ledger_entries WHERE farm_id=? AND sale_id=? AND entry_type='payment'");
    $q->execute([$farmId,$saleId]);
    if((int)$q->fetchColumn()>0) throw new RuntimeException('This credit sale has debt payments attached. Reverse or reassign those payments before deleting the sale.');
}
