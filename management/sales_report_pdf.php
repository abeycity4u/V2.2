<?php require_once(dirname(__DIR__) . '/init.php'); ?>
<?php
require_once(__DIR__ . '/../config.php');
require_once(__DIR__ . '/../includes/pdf/PdfReportService.php');
require_once(__DIR__ . '/../lib/attribution.php');
requireLogin();
requireBusinessReportAccess();

$tenantFarmId = requireCurrentFarmId();
$canChooseFarmType = isPlatformOwner() || hasRole('farm_admin', 'sales_rep');
$userFarmType = getUserFarmType();

$reportMode = ($_GET['report_mode'] ?? 'monthly') === 'yearly' ? 'yearly' : 'monthly';
$month = $_GET['month'] ?? date('Y-m');
$year = $_GET['year'] ?? date('Y');
if ($reportMode === 'yearly') {
    $year = date('Y', strtotime($year . '-01-01'));
    $startDate = $year . '-01-01';
    $endDate = $year . '-12-31';
    $periodLabel = $year;
} else {
    $month = date('Y-m', strtotime($month . '-01'));
    $startDate = $month . '-01';
    $endDate = date('Y-m-t', strtotime($startDate));
    $periodLabel = date('F Y', strtotime($startDate));
}

$salesOnlyScope = enabledFarmTypes() === [] && farmHasModule('sales') && (isPlatformOwner() || hasRole('farm_admin', 'sales_rep', 'viewer'));
$requestedFarmType = $canChooseFarmType ? ($_GET['farm_type'] ?? null) : $userFarmType;
if ($requestedFarmType === 'both' && count(accessibleFarmTypes()) === 2) $requestedFarmType = 'all';
if ($salesOnlyScope) $farmType = 'general';
elseif ($requestedFarmType === 'general' && in_array('general', allowedSalesFarmTypes(), true)) $farmType = 'general';
else $farmType = normalizeFarmType($requestedFarmType, true, false, $canChooseFarmType);

$productionType = strtolower(trim((string)($_GET['production_type'] ?? 'all')));
if ($farmType !== 'all') {
    $opts = attribution_production_types($farmType);
    if ($productionType !== 'all' && !isset($opts[$productionType])) $productionType = 'all';
} else {
    $productionType = 'all';
}
$selectedCustomer = trim((string)($_GET['customer'] ?? ''));

$sql = "SELECT s.*, u.full_name AS seller, pc.cycle_code
        FROM sales_records s
        LEFT JOIN production_cycles pc ON pc.id=s.cycle_id AND pc.farm_id=s.farm_id
        LEFT JOIN users u ON u.id=s.user_id AND u.farm_id=s.farm_id
        WHERE s.farm_id=? AND s.sale_date BETWEEN ? AND ?";
$params = [$tenantFarmId, $startDate, $endDate];
if ($farmType === '') {
    $sql .= " AND 1=0";
} elseif ($farmType !== 'all') {
    $sql .= " AND s.farm_type=?";
    $params[] = $farmType;
}
if ($productionType !== 'all') {
    $sql .= " AND s.production_type=?";
    $params[] = $productionType;
}
$sql .= " ORDER BY s.sale_date DESC,s.id DESC";
$stmt = $pdo->prepare($sql); $stmt->execute($params); $sales = $stmt->fetchAll(PDO::FETCH_ASSOC);
$totalSales = 0.0;
foreach ($sales as $row) $totalSales += (float)($row['total_amount'] ?? ((float)$row['quantity'] * (float)$row['unit_price']);
$transactionCount = count($sales);

$ledger = [];
$outstanding = 0.0;
$totalCredit = 0.0;
$debtSettlements = 0.0;
$upfront = 0.0;
$totalPaid = 0.0;
if ($selectedCustomer !== '') {
    $ls = $pdo->prepare("SELECT l.*, COALESCE(u.full_name,'Farm User') AS recorded_by
        FROM customer_ledger_entries l
        LEFT JOIN users u ON u.id=l.user_id AND u.farm_id=l.farm_id
        WHERE l.farm_id=? AND l.customer_name=?
        ORDER BY l.entry_date ASC,l.id ASC");
    $ls->execute([$tenantFarmId,$selectedCustomer]);
    $ledger = $ls->fetchAll(PDO::FETCH_ASSOC);
    foreach ($ledger as $entry) {
        $amount = (float)$entry['amount'];
        $outstanding += $amount;
        if (($entry['entry_type'] ?? '') === 'sale') $totalCredit += max(0,$amount);
        if (($entry['entry_type'] ?? '') === 'payment') $debtSettlements += abs($amount);
    }
    $us = $pdo->prepare("SELECT COALESCE(SUM(COALESCE(payment_received,0)),0) FROM sales_records WHERE farm_id=? AND customer_name=?");
    $us->execute([$tenantFarmId,$selectedCustomer]);
    $upfront = (float)$us->fetchColumn();
    $totalPaid = $upfront + $debtSettlements;
}

ob_start();
?>
<!doctype html><html lang="en"><head><meta charset="UTF-8"><title>Sales Report - Renee Farms</title></head><body>
<h2>Sales Report - <?php echo htmlspecialchars($periodLabel); ?></h2>
<table class="table" style="margin-bottom:12px">
<thead><tr><th>Total Sales</th><th>Transactions</th><th>Farm Scope</th><th>Production Type</th></tr></thead>
<tbody><tr><td>₦<?php echo number_format($totalSales,2); ?></td><td><?php echo $transactionCount; ?></td><td><?php echo htmlspecialchars($farmType==='all'?'All Farms':ucfirst($farmType)); ?></td><td><?php echo htmlspecialchars($productionType==='all'?'All Production Types':ucfirst($productionType)); ?></td></tr></tbody>
</table>
<?php if ($selectedCustomer !== ''): ?>
<h3>Customer Debt Management — <?php echo htmlspecialchars($selectedCustomer); ?></h3>
<table class="table" style="margin-bottom:12px"><thead><tr><th>Current Outstanding</th><th>Total Credit Taken</th><th>Total Paid (Upfront + Debt)</th><th>Debt Settlements</th></tr></thead>
<tbody><tr><td>₦<?php echo number_format($outstanding,2); ?></td><td>₦<?php echo number_format($totalCredit,2); ?></td><td>₦<?php echo number_format($totalPaid,2); ?></td><td>₦<?php echo number_format($debtSettlements,2); ?></td></tr></tbody></table>
<h4>Debt Ledger</h4>
<table class="table" style="margin-bottom:14px"><thead><tr><th>Date</th><th>Type</th><th>Description</th><th>Amount (₦)</th><th>Running Balance (₦)</th><th>Recorded By</th></tr></thead><tbody>
<?php if (!$ledger): ?><tr><td colspan="6">No debt ledger entries for this customer.</td></tr>
<?php else: $running=0.0; foreach($ledger as $entry): $running+=(float)$entry['amount']; ?>
<tr><td><?php echo htmlspecialchars(date('d/m/Y',strtotime((string)$entry['entry_date']))); ?></td><td><?php echo htmlspecialchars(ucfirst((string)$entry['entry_type'])); ?></td><td><?php echo htmlspecialchars((string)($entry['notes']??'--')); ?></td><td><?php echo number_format((float)$entry['amount'],2); ?></td><td><?php echo number_format($running,2); ?></td><td><?php echo htmlspecialchars((string)($entry['recorded_by']??'--')); ?></td></tr>
<?php endforeach; endif; ?></tbody></table>
<?php endif; ?>
<h3>Sales Records</h3>
<table class="table"><thead><tr><th>Date</th><th>Farm Type</th><th>Production Type</th><th>Cycle</th><th>Product</th><th>Qty</th><th>Unit Price</th><th>Total Amount</th><th>Customer</th><th>Remarks</th><th>Recorded By</th></tr></thead><tbody>
<?php if (!$sales): ?><tr><td colspan="11">No sales records for this period.</td></tr>
<?php else: foreach($sales as $sale): $rowTotal=(float)($sale['total_amount'] ?? ((float)$sale['quantity']*(float)$sale['unit_price']); ?>
<tr><td><?php echo htmlspecialchars(date('d/m/Y',strtotime((string)$sale['sale_date']))); ?></td><td><?php echo htmlspecialchars(ucfirst((string)$sale['farm_type'])); ?></td><td><?php echo htmlspecialchars(ucfirst((string)($sale['production_type']??'--'))); ?></td><td><?php echo htmlspecialchars((string)($sale['cycle_code'] ?: 'Shared / Unassigned')); ?></td><td><?php echo htmlspecialchars((string)$sale['product_type']); ?></td><td><?php echo number_format((float)$sale['quantity'],2); ?></td><td>₦<?php echo number_format((float)$sale['unit_price'],2); ?></td><td>₦<?php echo number_format($rowTotal,2); ?></td><td><?php echo htmlspecialchars((string)($sale['customer_name']?:'--')); ?></td><td><?php echo htmlspecialchars((string)($sale['remarks']?:'--')); ?></td><td><?php echo htmlspecialchars((string)($sale['seller']?:'--')); ?></td></tr>
<?php endforeach; endif; ?></tbody></table>
</body></html>
<?php
$html = ob_get_clean() ?: '';
$service = new PdfReportService();
$service->streamHtml($html, 'sales-report-' . strtolower(str_replace(' ','-',$periodLabel)) . '.pdf', 'landscape', 'Sales Report - ' . $periodLabel);
