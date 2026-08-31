<?php
require_once __DIR__ . '/../lib/stock_reporting.php';
require_once __DIR__ . '/../lib/stock_costing.php';
require_once __DIR__ . '/../lib/attribution.php';
/**
 * Traceable profitability engine.
 *
 * Attribution hierarchy: farm -> farm type -> production type -> cycle.
 * A production-type transaction may intentionally have no cycle (pooled sale,
 * shared production-type activity). Cycle reports only use directly assigned
 * rows plus explicit allocation rows, preventing invented precision.
 */
if (!function_exists('getProfitabilitySummary')) {
function getProfitabilitySummary(
    PDO $pdo,
    int $farmId,
    string $startDate,
    string $endDate,
    ?string $farmType = null,
    ?int $cycleId = null,
    ?string $productionType = null
): array {
    $farmType = $farmType ?: 'all';
    $productionType = strtolower(trim((string)$productionType));
    if ($productionType === 'all') $productionType = '';

    // Revenue: exact source records at farm/production level. At cycle level,
    // include direct cycle sales plus any explicit allocation of pooled sales.
    if ($cycleId) {
        $salesSql = "SELECT COALESCE(SUM(total_amount),0) FROM sales_records WHERE farm_id=? AND sale_date BETWEEN ? AND ? AND cycle_id=?";
        $salesParams = [$farmId,$startDate,$endDate,$cycleId];
        if ($farmType !== 'all') { $salesSql .= " AND farm_type=?"; $salesParams[]=$farmType; }
        if ($productionType !== '') { $salesSql .= " AND production_type=?"; $salesParams[]=$productionType; }
        $stmt=$pdo->prepare($salesSql); $stmt->execute($salesParams); $revenue=(float)$stmt->fetchColumn();

        $allocSql = "SELECT COALESCE(SUM(sa.allocated_amount),0)
                     FROM sales_allocations sa
                     JOIN sales_records s ON s.id=sa.sale_id AND s.farm_id=sa.farm_id
                     JOIN production_cycles pc ON pc.id=sa.cycle_id AND pc.farm_id=sa.farm_id
                     WHERE sa.farm_id=? AND sa.cycle_id=? AND s.sale_date BETWEEN ? AND ?";
        $allocParams=[$farmId,$cycleId,$startDate,$endDate];
        if ($farmType !== 'all') { $allocSql .= " AND pc.farm_type=?"; $allocParams[]=$farmType; }
        if ($productionType !== '') { $allocSql .= " AND pc.production_type=?"; $allocParams[]=$productionType; }
        $stmt=$pdo->prepare($allocSql); $stmt->execute($allocParams); $revenue += (float)$stmt->fetchColumn();
    } else {
        $salesSql = "SELECT COALESCE(SUM(total_amount),0) FROM sales_records WHERE farm_id=? AND sale_date BETWEEN ? AND ?";
        $salesParams=[$farmId,$startDate,$endDate];
        if ($farmType !== 'all') { $salesSql .= " AND farm_type=?"; $salesParams[]=$farmType; }
        if ($productionType !== '') { $salesSql .= " AND production_type=?"; $salesParams[]=$productionType; }
        $stmt=$pdo->prepare($salesSql); $stmt->execute($salesParams); $revenue=(float)$stmt->fetchColumn();
    }

    $expenseRows=[];
    if ($cycleId) {
        $expenseSql="SELECT category,COALESCE(SUM(amount * unit),0) total FROM farm_expenses WHERE farm_id=? AND expense_date BETWEEN ? AND ? AND cycle_id=?";
        $expenseParams=[$farmId,$startDate,$endDate,$cycleId];
        if ($farmType !== 'all') {
            $expenseSql .= $farmType === 'general' ? " AND farm_type='general'" : " AND (farm_type=? OR farm_type='both')";
            if ($farmType !== 'general') $expenseParams[]=$farmType;
        }
        if ($productionType !== '') { $expenseSql.=" AND production_type=?"; $expenseParams[]=$productionType; }
        $expenseSql.=" GROUP BY category";
        $stmt=$pdo->prepare($expenseSql); $stmt->execute($expenseParams);
        foreach($stmt->fetchAll(PDO::FETCH_KEY_PAIR) as $cat=>$amount) $expenseRows[$cat]=(float)$amount;

        $allocSql="SELECT e.category,COALESCE(SUM(fa.allocated_amount),0) total
                   FROM financial_allocations fa
                   JOIN farm_expenses e ON e.id=fa.expense_id AND e.farm_id=fa.farm_id
                   JOIN production_cycles pc ON pc.id=fa.cycle_id AND pc.farm_id=fa.farm_id
                   WHERE fa.farm_id=? AND fa.cycle_id=? AND e.expense_date BETWEEN ? AND ?";
        $allocParams=[$farmId,$cycleId,$startDate,$endDate];
        if ($farmType !== 'all') { $allocSql.=" AND pc.farm_type=?"; $allocParams[]=$farmType; }
        if ($productionType !== '') { $allocSql.=" AND pc.production_type=?"; $allocParams[]=$productionType; }
        $allocSql.=" GROUP BY e.category";
        $stmt=$pdo->prepare($allocSql); $stmt->execute($allocParams);
        foreach($stmt->fetchAll(PDO::FETCH_KEY_PAIR) as $cat=>$amount) $expenseRows[$cat]=($expenseRows[$cat]??0)+(float)$amount;
    } else {
        $expenseSql="SELECT category,COALESCE(SUM(amount * unit),0) total FROM farm_expenses WHERE farm_id=? AND expense_date BETWEEN ? AND ?";
        $expenseParams=[$farmId,$startDate,$endDate];
        if ($farmType !== 'all') {
            $expenseSql .= $farmType === 'general' ? " AND farm_type='general'" : " AND (farm_type=? OR farm_type='both')";
            if ($farmType !== 'general') $expenseParams[]=$farmType;
        }
        if ($productionType !== '') { $expenseSql.=" AND production_type=?"; $expenseParams[]=$productionType; }
        $expenseSql.=" GROUP BY category";
        $stmt=$pdo->prepare($expenseSql); $stmt->execute($expenseParams); $expenseRows=$stmt->fetchAll(PDO::FETCH_KEY_PAIR);
    }

    // Compatibility contract: transaction_type='used' AND is_reversed = 0 AND reversal_of_id IS NULL
    $effectiveStockSql=stock_effective_sql_predicate();
    $feedItemSql=stock_feed_item_sql_predicate('s','c');
    $feedSql="SELECT COALESCE(SUM(t.total_cost),0)
              FROM stock_transactions t
              JOIN stock_items s ON s.id=t.stock_item_id AND s.farm_id=t.farm_id
              LEFT JOIN inventory_categories c ON c.id=s.category_id AND c.farm_id=s.farm_id
              WHERE t.farm_id=? AND t.transaction_type='used' AND {$effectiveStockSql}
                AND {$feedItemSql} AND t.transaction_date BETWEEN ? AND ? AND t.total_cost IS NOT NULL";
    $feedParams=[$farmId,$startDate,$endDate];
    if ($farmType !== 'all') { $feedSql.=" AND t.farm_type=?"; $feedParams[]=$farmType; }
    if ($productionType !== '') { $feedSql.=" AND t.production_type=?"; $feedParams[]=$productionType; }
    if ($cycleId) { $feedSql.=" AND t.cycle_id=?"; $feedParams[]=$cycleId; }
    $stmt=$pdo->prepare($feedSql); $stmt->execute($feedParams); $feedCost=(float)$stmt->fetchColumn();

    // Feed purchases are cash-flow records, not an additional operating cost
    // when consumed-feed snapshots are present.
    $cashFeedSql="SELECT COALESCE(SUM(amount * unit),0) FROM farm_expenses WHERE farm_id=? AND expense_date BETWEEN ? AND ? AND category='feeds'";
    $cashFeedParams=[$farmId,$startDate,$endDate];
    if ($farmType !== 'all') {
        $cashFeedSql .= $farmType === 'general' ? " AND farm_type='general'" : " AND (farm_type=? OR farm_type='both')";
        if ($farmType !== 'general') $cashFeedParams[]=$farmType;
    }
    if ($productionType !== '') { $cashFeedSql.=" AND production_type=?"; $cashFeedParams[]=$productionType; }
    if ($cycleId) { $cashFeedSql.=" AND cycle_id=?"; $cashFeedParams[]=$cycleId; }
    $stmt=$pdo->prepare($cashFeedSql); $stmt->execute($cashFeedParams); $cashFeed=(float)$stmt->fetchColumn();

    $nonFeedExpenses=0.0;
    foreach($expenseRows as $category=>$amount) if($category!=='feeds') $nonFeedExpenses+=(float)$amount;
    $totalCost=$nonFeedExpenses+$feedCost;
    return [
        'revenue'=>$revenue,
        'feed_consumption_cost'=>$feedCost,
        'non_feed_expenses'=>$nonFeedExpenses,
        'total_operating_cost'=>$totalCost,
        'profit'=>$revenue-$totalCost,
        'cash_feed_expenses'=>$cashFeed,
        'expense_breakdown'=>$expenseRows,
    ];
}}
