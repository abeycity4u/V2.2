<?php
require_once(dirname(__DIR__) . '/init.php');
require_once(__DIR__ . '/../config.php');
require_once(__DIR__ . '/../includes/financial.php');
require_once(__DIR__ . '/../lib/stock_reporting.php');
require_once(__DIR__ . '/../lib/stock_costing.php');
require_once(__DIR__ . '/../lib/attribution.php');
requireLogin();
requireBusinessReportAccess();

$farmId = requireCurrentFarmId();
$access = getUserFarmType();
$canChoose = isPlatformOwner() || hasRole('farm_admin','sales_rep');
$farmType = $canChoose ? ($_GET['farm_type'] ?? 'all') : ($access === 'all' ? 'all' : $access);
if (!in_array($farmType, ['all','poultry','ruminant','general'], true)) $farmType = 'all';

$productionType = strtolower(trim((string)($_GET['production_type'] ?? 'all')));
$productionOptions = $farmType === 'all' ? [] : attribution_production_types($farmType);
if ($productionType !== 'all' && !isset($productionOptions[$productionType])) $productionType = 'all';

$period = ($_GET['period'] ?? 'monthly') === 'daily' ? 'daily' : 'monthly';
$selectedDate = preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['date'] ?? '') ? $_GET['date'] : date('Y-m-d');
$month = preg_match('/^\d{4}-\d{2}$/', $_GET['month'] ?? '') ? $_GET['month'] : date('Y-m');
if ($period === 'daily') {
    $start = $selectedDate;
    $end = $selectedDate;
    $periodLabel = date('j M Y', strtotime($selectedDate));
} else {
    $start = $month . '-01';
    $end = date('Y-m-t', strtotime($start));
    $periodLabel = date('F Y', strtotime($start));
}

$cycleId = (int)($_GET['cycle_id'] ?? 0);
$cyclesStmt = $pdo->prepare("SELECT id,cycle_code,farm_type,production_type,status FROM production_cycles WHERE farm_id=? ORDER BY start_date DESC,id DESC");
$cyclesStmt->execute([$farmId]);
$cycles = $cyclesStmt->fetchAll(PDO::FETCH_ASSOC);
$visibleCycles = array_values(array_filter($cycles, static function(array $cycle) use ($farmType, $productionType): bool {
    if ($farmType !== 'all' && strtolower((string)$cycle['farm_type']) !== $farmType) return false;
    if ($productionType !== 'all' && strtolower((string)$cycle['production_type']) !== $productionType) return false;
    return true;
}));
$validCycleIds = array_map('intval', array_column($visibleCycles,'id'));
if ($cycleId && !in_array($cycleId, $validCycleIds, true)) $cycleId = 0;

$summary = getProfitabilitySummary($pdo, $farmId, $start, $end, $farmType, $cycleId ?: null, $productionType === 'all' ? null : $productionType);

// Cycle-level profitability deliberately excludes pooled revenue until it is
// allocated. Surface the amount still waiting for allocation so a zero/low
// cycle revenue cannot be mistaken for a complete result.
$unallocatedPooledRevenue = 0.0;
$allocatedSharedRevenue = 0.0;
if ($cycleId) {
    $selectedCycle = null;
    foreach ($cycles as $cycle) {
        if ((int)$cycle['id'] === $cycleId) { $selectedCycle = $cycle; break; }
    }
    if ($selectedCycle) {
        $pooledSql = "SELECT COALESCE(SUM(GREATEST(s.total_amount - COALESCE(a.allocated_amount,0),0)),0)
                      FROM sales_records s
                      LEFT JOIN (
                          SELECT farm_id,sale_id,SUM(allocated_amount) allocated_amount
                          FROM sales_allocations GROUP BY farm_id,sale_id
                      ) a ON a.farm_id=s.farm_id AND a.sale_id=s.id
                      WHERE s.farm_id=? AND s.sale_date BETWEEN ? AND ?
                        AND s.cycle_id IS NULL AND s.farm_type=? AND s.production_type=?";
        $pooledStmt = $pdo->prepare($pooledSql);
        $pooledStmt->execute([$farmId,$start,$end,strtolower((string)$selectedCycle['farm_type']),strtolower((string)$selectedCycle['production_type'])]);
        $unallocatedPooledRevenue = (float)$pooledStmt->fetchColumn();

        $sharedIncludedSql = "SELECT COALESCE(SUM(sa.allocated_amount),0)
                              FROM sales_allocations sa
                              JOIN sales_records s ON s.id=sa.sale_id AND s.farm_id=sa.farm_id
                              WHERE sa.farm_id=? AND sa.cycle_id=? AND s.cycle_id IS NULL
                                AND s.sale_date BETWEEN ? AND ?";
        $sharedIncludedStmt = $pdo->prepare($sharedIncludedSql);
        $sharedIncludedStmt->execute([$farmId,$cycleId,$start,$end]);
        $allocatedSharedRevenue = (float)$sharedIncludedStmt->fetchColumn();
    }
}

// Use the same effective-transaction predicate as feed movement summaries and feed-cost reporting.
// Compatibility contract: transaction_type='used' AND is_reversed = 0 AND reversal_of_id IS NULL
$effectiveStockSql = stock_effective_sql_predicate();
$feedItemSql = stock_feed_item_sql_predicate('s', 'c');
$uncostedSql = "SELECT COUNT(*)
                FROM stock_transactions t
                JOIN stock_items s ON s.id=t.stock_item_id AND s.farm_id=t.farm_id
                LEFT JOIN inventory_categories c ON c.id=s.category_id AND c.farm_id=s.farm_id
                WHERE t.farm_id=? AND t.transaction_type='used' AND {$effectiveStockSql}
                  AND {$feedItemSql} AND t.transaction_date BETWEEN ? AND ? AND t.total_cost IS NULL";
$uncostedParams = [$farmId, $start, $end];
if ($farmType && $farmType !== 'all') { $uncostedSql .= " AND t.farm_type=?"; $uncostedParams[] = $farmType; }
if ($productionType !== 'all') { $uncostedSql .= " AND t.production_type=?"; $uncostedParams[] = $productionType; }
if ($cycleId) { $uncostedSql .= " AND t.cycle_id=?"; $uncostedParams[] = $cycleId; }
$hasUncosted = $pdo->prepare($uncostedSql);
$hasUncosted->execute($uncostedParams);
$uncosted = (int)$hasUncosted->fetchColumn();

$toggleParams = ['farm_type' => $farmType, 'production_type' => $productionType, 'cycle_id' => $cycleId];
$dailyUrl = '?' . http_build_query(array_merge($toggleParams, ['period' => 'daily', 'date' => $period === 'daily' ? $selectedDate : date('Y-m-d')]));
$monthlyUrl = '?' . http_build_query(array_merge($toggleParams, ['period' => 'monthly', 'month' => $period === 'monthly' ? $month : substr($selectedDate, 0, 7)]));
?>
<!doctype html>
<html lang="en"><head><?php include(__DIR__.'/../navbar_head.php'); ?><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Profitability - Farm Platform</title></head><body>
<?php include(__DIR__.'/../navbar.php'); ?>
<div class="container-fluid py-4">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <div><h2 class="mb-1">Profitability</h2><p class="text-muted mb-0">Traceable operating profitability using effective feed consumption and recorded business activity.</p></div>
        <a class="btn btn-outline-secondary" href="<?php echo BASE_URL; ?>/management/reports.php">Back to Analytics</a>
    </div>

    <div class="btn-group mb-4" role="group" aria-label="Profitability period">
        <a href="<?php echo htmlspecialchars($dailyUrl); ?>" class="btn <?php echo $period === 'daily' ? 'btn-primary' : 'btn-outline-primary'; ?>">Daily</a>
        <a href="<?php echo htmlspecialchars($monthlyUrl); ?>" class="btn <?php echo $period === 'monthly' ? 'btn-primary' : 'btn-outline-primary'; ?>">Monthly</a>
    </div>

    <form class="card card-body mb-4" method="get">
        <input type="hidden" name="period" value="<?php echo htmlspecialchars($period); ?>">
        <div class="row g-3 align-items-end">
            <div class="col-md-3">
                <?php if ($period === 'daily'): ?>
                    <label class="form-label">Date</label><input type="date" name="date" class="form-control" value="<?php echo htmlspecialchars($selectedDate); ?>">
                <?php else: ?>
                    <label class="form-label">Month</label><input type="month" name="month" class="form-control" value="<?php echo htmlspecialchars($month); ?>">
                <?php endif; ?>
            </div>
            <div class="col-md-2"><label class="form-label">Farm type</label><select name="farm_type" id="profitFarmType" class="form-select" <?php echo $canChoose?'':'disabled'; ?>><option value="all" <?php echo $farmType==='all'?'selected':''; ?>>All</option><option value="poultry" <?php echo $farmType==='poultry'?'selected':''; ?>>Poultry</option><option value="ruminant" <?php echo $farmType==='ruminant'?'selected':''; ?>>Ruminant</option><option value="general" <?php echo $farmType==='general'?'selected':''; ?>>General</option></select><?php if(!$canChoose): ?><input type="hidden" name="farm_type" value="<?php echo htmlspecialchars($farmType); ?>"><?php endif; ?></div>
            <div class="col-md-3"><label class="form-label">Production type</label><select name="production_type" id="profitProductionType" class="form-select"><option value="all">All production types</option><?php foreach($productionOptions as $value=>$label): ?><option value="<?php echo htmlspecialchars($value); ?>" <?php echo $productionType===$value?'selected':''; ?>><?php echo htmlspecialchars($label); ?></option><?php endforeach; ?></select></div>
            <div class="col-md-3"><label class="form-label">Production cycle (optional)</label><select name="cycle_id" id="profitCycleId" class="form-select"><option value="0"><?php echo $productionType !== 'all' ? 'All ' . htmlspecialchars(attribution_label($productionType)) . ' cycles' : 'All cycles'; ?></option><?php foreach($visibleCycles as $c): ?><option value="<?php echo (int)$c['id']; ?>" <?php echo $cycleId===(int)$c['id']?'selected':''; ?>><?php echo htmlspecialchars($c['cycle_code'].' — '.$c['production_type'].' ('.$c['status'].')'); ?></option><?php endforeach; ?></select></div>
            <div class="col-md-1"><button class="btn btn-primary w-100">Apply</button></div>
        </div>
        <div class="col-12 profitability-filter-help"><i class="bi bi-info-circle me-1"></i>Production type narrows Layer, Broiler or ruminant species; choose a cycle only when you need cycle-level analysis.</div>
    </form>

    <?php if ($cycleId && $allocatedSharedRevenue > 0.009): ?>
        <div class="alert alert-info d-flex gap-2 align-items-start">
            <i class="bi bi-diagram-3"></i>
            <div><strong>Shared revenue included.</strong> ₦<?php echo number_format($allocatedSharedRevenue,2); ?> from combined sales has been assigned to this cycle from recorded production ownership.</div>
        </div>
    <?php endif; ?>
    <?php if ($cycleId && $unallocatedPooledRevenue > 0.009): ?>
        <div class="alert alert-warning d-flex align-items-start gap-2" role="alert">
            <i class="bi bi-exclamation-triangle-fill mt-1"></i>
            <div><strong>Some shared revenue could not be assigned yet.</strong> ₦<?php echo number_format($unallocatedPooledRevenue,2); ?> remains at the production-type level. For Layer egg sales, check that Daily Records support enough unsold egg stock for the quantity sold.</div>
        </div>
    <?php endif; ?>

    <div class="d-flex justify-content-between align-items-center mb-2"><h5 class="mb-0"><?php echo $period === 'daily' ? 'Daily Analysis' : 'Monthly Analysis'; ?></h5><span class="text-muted"><?php echo htmlspecialchars($periodLabel); ?></span></div>
    <div class="row g-3 mb-4">
        <div class="col-md-3"><div class="card h-100"><div class="card-body"><div class="text-muted">Revenue</div><h3 class="mt-2">₦<?php echo number_format($summary['revenue'],2); ?></h3></div></div></div>
        <div class="col-md-3"><div class="card h-100"><div class="card-body"><div class="text-muted">Feed consumed</div><h3 class="mt-2">₦<?php echo number_format($summary['feed_consumption_cost'],2); ?></h3></div></div></div>
        <div class="col-md-3"><div class="card h-100"><div class="card-body"><div class="text-muted">Other operating cost</div><h3 class="mt-2">₦<?php echo number_format($summary['non_feed_expenses'],2); ?></h3></div></div></div>
        <div class="col-md-3"><div class="card h-100"><div class="card-body"><div class="text-muted">Operating result</div><h3 class="mt-2 <?php echo $summary['profit']>=0?'text-success':'text-danger'; ?>">₦<?php echo number_format($summary['profit'],2); ?></h3></div></div></div>
    </div>

    <?php if($uncosted>0): ?><div class="alert alert-warning"><strong>Cost data notice:</strong> <?php echo $uncosted; ?> effective feed-use transaction(s) in this period have no cost snapshot. They are excluded from feed-consumption cost so the platform does not invent a cost.</div><?php endif; ?>

    <div class="row g-4">
        <div class="col-lg-7"><div class="card"><div class="card-header fw-semibold">How this result is calculated</div><div class="card-body"><dl class="row mb-0"><dt class="col-7">Revenue from sales</dt><dd class="col-5 text-end">₦<?php echo number_format($summary['revenue'],2); ?></dd><dt class="col-7">Feed consumed (cost snapshot)</dt><dd class="col-5 text-end">₦<?php echo number_format($summary['feed_consumption_cost'],2); ?></dd><dt class="col-7">Other operating expenses</dt><dd class="col-5 text-end">₦<?php echo number_format($summary['non_feed_expenses'],2); ?></dd><hr><dt class="col-7">Operating result</dt><dd class="col-5 text-end fw-bold">₦<?php echo number_format($summary['profit'],2); ?></dd></dl><p class="small text-muted mt-3 mb-0">Feed purchases are tracked as cash expenses (₦<?php echo number_format($summary['cash_feed_expenses'],2); ?>) but are not added again to consumed-feed cost. Feed profitability is consumption-based, preventing purchase-day distortion and double counting.</p></div></div></div>
        <div class="col-lg-5"><div class="card"><div class="card-header fw-semibold">Reporting integrity</div><div class="card-body"><p class="mb-2">Daily and monthly views use the same calculation engine. Monthly is the sum of activity inside the selected month; Daily limits that engine to one date.</p><p class="mb-0 text-muted small">Reversed originals and restoration/reversal rows remain available in the audit ledger but are excluded from operational feed quantity and profitability calculations. Feed usage is valued from its transaction cost snapshot, so a later inventory price change does not rewrite historical profit. Production type and cycle attribution keep Layer, Broiler and ruminant species costs separated; pooled activity remains explicit instead of being guessed.</p></div></div></div>
    </div>
</div>
<script>
(function () {
    const productionTypes = {
        all: {},
        poultry: {layer:'Layer', broiler:'Broiler', shared:'Shared / Unallocated Poultry'},
        ruminant: {cattle:'Cattle', goat:'Goat', sheep:'Sheep', other:'Other', shared:'Shared / Unallocated Ruminant'},
        general: {general:'General / Other Farm Income'}
    };
    const cycles = <?php echo json_encode($cycles, JSON_UNESCAPED_SLASHES); ?>;
    const farmSelect = document.getElementById('profitFarmType');
    const productionSelect = document.getElementById('profitProductionType');
    const cycleSelect = document.getElementById('profitCycleId');
    if (!farmSelect || !productionSelect || !cycleSelect) return;

    function rebuildCycles(selectedCycle) {
        const farm = farmSelect.value;
        const production = productionSelect.value;
        cycleSelect.innerHTML = '';
        const allCycleLabel = production !== 'all' ? `All ${production.replace(/_/g,' ')} cycles` : 'All cycles';
        cycleSelect.add(new Option(allCycleLabel, '0'));
        cycles.filter(c => (farm === 'all' || c.farm_type === farm) &&
                           (production === 'all' || c.production_type === production))
              .forEach(c => cycleSelect.add(new Option(`${c.cycle_code} — ${c.production_type} (${c.status})`, String(c.id))));
        const wanted = String(selectedCycle || '0');
        cycleSelect.value = Array.from(cycleSelect.options).some(o => o.value === wanted) ? wanted : '0';
    }

    function rebuildProduction(selectedProduction, selectedCycle) {
        const farm = farmSelect.value;
        productionSelect.innerHTML = '';
        productionSelect.add(new Option('All production types', 'all'));
        Object.entries(productionTypes[farm] || {}).forEach(([value,label]) => productionSelect.add(new Option(label,value)));
        const wanted = String(selectedProduction || 'all');
        productionSelect.value = Array.from(productionSelect.options).some(o => o.value === wanted) ? wanted : 'all';
        rebuildCycles(selectedCycle);
    }

    farmSelect.addEventListener('change', () => rebuildProduction('all', 0));
    productionSelect.addEventListener('change', () => rebuildCycles(0));
    rebuildProduction(<?php echo json_encode($productionType); ?>, <?php echo (int)$cycleId; ?>);
})();
</script>
</body></html>
