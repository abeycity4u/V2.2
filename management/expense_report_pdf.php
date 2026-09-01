<?php require_once(dirname(__DIR__) . '/init.php'); ?>
<?php
require_once(__DIR__ . '/../config.php');
require_once(__DIR__ . '/../includes/pdf/PdfReportService.php');
require_once(__DIR__ . '/../includes/functions.php');
require_once(__DIR__ . '/../lib/attribution.php');
requireLogin();
if (!isPlatformOwner() && !hasRole('farm_admin') && !hasPermission(getUserType(), 'expenses')) { header('Location: ' . BASE_URL . '/no_access.php'); exit(); }
$tenantFarmId = requireCurrentFarmId();
$userFarmType = getUserFarmType();
$canChooseFarmType = isPlatformOwner() || hasRole('farm_admin', 'sales_rep');

$reportMode = ($_GET['report_mode'] ?? 'monthly') === 'yearly' ? 'yearly' : 'monthly';
$month = $_GET['month'] ?? date('Y-m');
$year = $_GET['year'] ?? date('Y');
if ($reportMode === 'yearly') {
    $year = date('Y', strtotime($year . '-01-01'));
    $startDate = $year . '-01-01';
    $endDate = $year . '-12-31';
    $periodLabel = $year;
} else {
    $selectedMonth = date('Y-m', strtotime($month . '-01'));
    $startDate = date('Y-m-01', strtotime($selectedMonth . '-01'));
    $endDate = date('Y-m-t', strtotime($selectedMonth . '-01'));
    $periodLabel = date('F Y', strtotime($selectedMonth));
}
$requestedFarmType = $canChooseFarmType ? ($_GET['farm_type'] ?? null) : $userFarmType;
if ($requestedFarmType === 'both' && count(accessibleFarmTypes()) === 2) $requestedFarmType = 'all';
if ($requestedFarmType === 'general') $farmType='general';
else $farmType = normalizeFarmType($requestedFarmType, true, false, $canChooseFarmType);
$productionType = strtolower(trim((string)($_GET['production_type'] ?? 'all')));
$productionOptions = $farmType === 'all' ? [] : attribution_production_types($farmType);
if ($productionType !== 'all' && !isset($productionOptions[$productionType])) $productionType='all';
$category = $_GET['category'] ?? 'all';

$where = "WHERE e.farm_id=? AND e.expense_date BETWEEN ? AND ?";
$params = [$tenantFarmId,$startDate,$endDate];
if ($farmType === '') $where .= " AND 1=0";
elseif ($farmType !== 'all') {
    if ($farmType === 'general') $where .= " AND e.farm_type='general'";
    else { $where .= " AND (e.farm_type=? OR e.farm_type='both')"; $params[]=$farmType; }
}
if ($productionType !== 'all') { $where .= " AND e.production_type=?"; $params[]=$productionType; }
if ($category !== 'all') { $where .= " AND e.category=?"; $params[]=$category; }
$stmt=$pdo->prepare("SELECT e.*,u.full_name FROM farm_expenses e LEFT JOIN users u ON u.id=e.user_id AND u.farm_id=e.farm_id {$where} ORDER BY e.expense_date DESC,e.id DESC");
$stmt->execute($params); $expenses=$stmt->fetchAll(PDO::FETCH_ASSOC);
$totalExpenses=0.0; $categoryTotals=[]; $farmTypeTotals=[];
foreach($expenses as $expense){
    $line=(float)($expense['amount']??0)*(float)($expense['unit']??1);
    $totalExpenses += $line;
    $categoryTotals[$expense['category']] = ($categoryTotals[$expense['category']]??0)+$line;
    $farmTypeTotals[$expense['farm_type']] = ($farmTypeTotals[$expense['farm_type']]??0)+$line;
}
ob_start();
?>
<!doctype html><html lang="en"><head><meta charset="UTF-8"><title>Expense Report - Renee Farms</title></head><body>
<h2>Expense Report - <?php echo htmlspecialchars($periodLabel); ?></h2>
<table class="table" style="margin-bottom:12px"><thead><tr><th>Total Expenses</th><th>Farm Scope</th><th>Production Type</th><th>Category</th></tr></thead><tbody><tr><td>₦<?php echo number_format($totalExpenses,2); ?></td><td><?php echo htmlspecialchars($farmType==='all'?'All Farms':ucfirst($farmType)); ?></td><td><?php echo htmlspecialchars($productionType==='all'?'All Production Types':ucfirst($productionType)); ?></td><td><?php echo htmlspecialchars($category==='all'?'All Categories':ucfirst($category)); ?></td></tr></tbody></table>
<h3>Breakdown</h3>
<table class="table" style="margin-bottom:12px"><thead><tr><th>Farm Type</th><th>Total</th><th>Category</th><th>Total</th></tr></thead><tbody>
<?php $fk=array_keys($farmTypeTotals);$fv=array_values($farmTypeTotals);$ck=array_keys($categoryTotals);$cv=array_values($categoryTotals);$n=max(count($fk),count($ck),1);for($i=0;$i<$n;$i++): ?>
<tr><td><?php echo isset($fk[$i])?htmlspecialchars(ucfirst((string)$fk[$i])):''; ?></td><td><?php echo isset($fv[$i])?'₦'.number_format((float)$fv[$i],2):''; ?></td><td><?php echo isset($ck[$i])?htmlspecialchars(ucfirst((string)$ck[$i])):''; ?></td><td><?php echo isset($cv[$i])?'₦'.number_format((float)$cv[$i],2):''; ?></td></tr>
<?php endfor; ?>
</tbody></table>
<h3>Detailed Expenses</h3>
<table class="table"><thead><tr><th>Date</th><th>Farm Type</th><th>Production Type</th><th>Category</th><th>Unit</th><th>Amount</th><th>Total</th><th>Description</th><th>Recorded By</th></tr></thead><tbody>
<?php if(!$expenses): ?><tr><td colspan="9">No expenses recorded for this period.</td></tr>
<?php else: foreach($expenses as $expense): $line=(float)($expense['amount']??0)*(float)($expense['unit']??1); ?>
<tr><td><?php echo htmlspecialchars(date('d/m/Y',strtotime((string)$expense['expense_date']))); ?></td><td><?php echo htmlspecialchars(ucfirst((string)$expense['farm_type'])); ?></td><td><?php echo htmlspecialchars(ucfirst((string)($expense['production_type']??'--'))); ?></td><td><?php echo htmlspecialchars(ucfirst((string)$expense['category'])); ?></td><td><?php echo number_format((float)($expense['unit']??1),2); ?></td><td>₦<?php echo number_format((float)($expense['amount']??0),2); ?></td><td>₦<?php echo number_format($line,2); ?></td><td><?php echo htmlspecialchars((string)($expense['description']?:'--')); ?></td><td><?php echo htmlspecialchars((string)($expense['full_name']?:'--')); ?></td></tr>
<?php endforeach; endif; ?></tbody></table>
</body></html>
<?php
$html=ob_get_clean()?:'';
$service=new PdfReportService();
$service->streamHtml($html,'expense-report-'.strtolower(str_replace(' ','-',$periodLabel)).'.pdf','landscape','Expense Report - '.$periodLabel);
