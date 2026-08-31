<?php require_once(dirname(__DIR__) . '/init.php'); ?>
<?php
require_once(__DIR__ . '/../config.php');
require_once(__DIR__ . '/../includes/pdf/PdfReportService.php');
requireLogin();
requireBusinessReportAccess();
$tenantFarmId = requireCurrentFarmId();
$customer = trim((string)($_GET['customer'] ?? ''));
if ($customer === '') { http_response_code(400); exit('Customer is required.'); }

$stmt = $pdo->prepare("SELECT cle.*, COALESCE(u.full_name, 'Farm User') AS recorded_by
    FROM customer_ledger_entries cle
    LEFT JOIN users u ON u.id = cle.user_id AND u.farm_id = cle.farm_id
    WHERE cle.farm_id = ? AND cle.customer_name = ?
    ORDER BY cle.entry_date ASC, cle.id ASC");
$stmt->execute([$tenantFarmId, $customer]);
$ledger = $stmt->fetchAll(PDO::FETCH_ASSOC);
$credit = 0.0; $debtPayments = 0.0; $balance = 0.0;
foreach ($ledger as $row) {
    $amount=(float)($row['amount']??0); $balance += $amount;
    if (($row['entry_type']??'')==='sale') $credit += max(0,$amount);
    if (($row['entry_type']??'')==='payment') $debtPayments += abs($amount);
}
$upfrontStmt=$pdo->prepare("SELECT COALESCE(SUM(COALESCE(s.payment_received,0)),0)
    FROM sales_records s WHERE s.farm_id=? AND s.id IN (
        SELECT DISTINCT sale_id FROM customer_ledger_entries
        WHERE farm_id=? AND customer_name=? AND sale_id IS NOT NULL
    )");
$upfrontStmt->execute([$tenantFarmId,$tenantFarmId,$customer]);
$upfront=(float)$upfrontStmt->fetchColumn(); $totalPaid=$upfront+$debtPayments;
ob_start(); ?>
<!doctype html><html lang="en"><head><meta charset="UTF-8"><title>Customer Debt History - Renee Farms</title></head><body>
<div class="card"><div class="card-header"><h2>Customer Debt History</h2><div><?php echo htmlspecialchars($customer); ?></div></div><div class="card-body">
<table class="table" style="margin-bottom:12px"><thead><tr><th>Current Outstanding</th><th>Total Credit Taken</th><th>Total Paid (Upfront + Debt)</th><th>Debt Settlements</th></tr></thead>
<tbody><tr><td>₦<?php echo number_format($balance,2); ?></td><td>₦<?php echo number_format($credit,2); ?></td><td>₦<?php echo number_format($totalPaid,2); ?></td><td>₦<?php echo number_format($debtPayments,2); ?></td></tr></tbody></table>
<h4>Ledger History</h4><table class="table"><thead><tr><th>Date</th><th>Type</th><th>Description</th><th>Amount (₦)</th><th>Running Balance (₦)</th><th>Recorded By</th></tr></thead><tbody>
<?php if (!$ledger): ?><tr><td colspan="6">No debt ledger entries for this customer.</td></tr>
<?php else: $running=0.0; foreach($ledger as $row): $running+=(float)$row['amount']; ?>
<tr><td><?php echo htmlspecialchars(date('d/m/Y',strtotime((string)$row['entry_date']))); ?></td><td><?php echo htmlspecialchars(ucfirst((string)$row['entry_type'])); ?></td><td><?php echo htmlspecialchars((string)($row['notes']??'')); ?></td><td><?php echo number_format((float)$row['amount'],2); ?></td><td><?php echo number_format($running,2); ?></td><td><?php echo htmlspecialchars((string)$row['recorded_by']); ?></td></tr>
<?php endforeach; endif; ?></tbody></table></div></div></body></html>
<?php $html=ob_get_clean()?:''; $service=new PdfReportService();
$service->streamHtml($html,'debt-history-'.preg_replace('/[^A-Za-z0-9_-]+/','-',strtolower($customer)).'.pdf','landscape','Customer Debt History - '.$customer);
