<?php require_once(dirname(__DIR__) . '/init.php'); ?>
<?php
require_once(__DIR__ . '/../config.php');
requireLogin();

header('Content-Type: application/json');

$type = $_GET['type'] ?? 'profit_loss';
$period = $_GET['period'] ?? 'month';

switch ($type) {
    case 'profit_loss':
        echo json_encode(getProfitLossData($period));
        break;
    
    case 'sales':
        echo json_encode(getSalesData($period));
        break;
    
    case 'expenses':
        echo json_encode(getExpenseData($period));
        break;
    
    case 'stock':
        echo json_encode(getStockData($period));
        break;
    
    case 'production':
        echo json_encode(getProductionData($period));
        break;
    
    default:
        echo json_encode(['error' => 'Invalid chart type']);
}

function getProfitLossData($period) {
    global $pdo;
    $farmId = requireCurrentFarmId();
    
    $dateFormat = $period == 'year' ? '%Y' : '%Y-%m';
    $limit = $period == 'year' ? 12 : 30;
    
    $query = "SELECT period,
                SUM(total_sales) AS total_sales,
                SUM(total_expenses) AS total_expenses,
                SUM(total_sales) - SUM(total_expenses) AS net_profit
              FROM (
                  SELECT DATE_FORMAT(sale_date, ?) AS period,
                         SUM(total_amount) AS total_sales, 0 AS total_expenses
                  FROM sales_records
                  WHERE farm_id = ? AND sale_date >= DATE_SUB(CURDATE(), INTERVAL ? DAY)
                  GROUP BY DATE_FORMAT(sale_date, ?)
                  UNION ALL
                  SELECT DATE_FORMAT(expense_date, ?) AS period,
                         0 AS total_sales, SUM(amount * unit) AS total_expenses
                  FROM farm_expenses
                  WHERE farm_id = ? AND expense_date >= DATE_SUB(CURDATE(), INTERVAL ? DAY)
                  GROUP BY DATE_FORMAT(expense_date, ?)
              ) totals
              GROUP BY period
              ORDER BY period";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute([$dateFormat, $farmId, $limit, $dateFormat, $dateFormat, $farmId, $limit, $dateFormat]);
    $data = $stmt->fetchAll();
    
    $labels = [];
    $values = [];
    
    foreach ($data as $row) {
        $labels[] = $period == 'year' ? $row['period'] : date('M Y', strtotime($row['period'] . '-01'));
        $values[] = $row['net_profit'];
    }
    
    return ['labels' => $labels, 'values' => $values];
}

function getSalesData($period) {
    global $pdo;
    $farmId = requireCurrentFarmId();
    
    $dateFormat = $period == 'year' ? '%Y' : '%Y-%m';
    $limit = $period == 'year' ? 12 : 30;
    
    $query = "SELECT 
                DATE_FORMAT(sale_date, ?) as period,
                farm_type,
                SUM(total_amount) as total_sales
              FROM sales_records
              WHERE farm_id = ? AND sale_date >= DATE_SUB(CURDATE(), INTERVAL ? DAY)
              GROUP BY DATE_FORMAT(sale_date, ?), farm_type
              ORDER BY period";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute([$dateFormat, $farmId, $limit, $dateFormat]);
    $data = $stmt->fetchAll();
    
    $labels = [];
    $poultry = [];
    $ruminant = [];
    $currentPeriod = null;
    
    foreach ($data as $row) {
        if ($row['period'] != $currentPeriod) {
            $labels[] = $period == 'year' ? $row['period'] : date('M Y', strtotime($row['period'] . '-01'));
            $currentPeriod = $row['period'];
            $poultry[] = 0;
            $ruminant[] = 0;
        }
        
        $index = count($labels) - 1;
        if ($row['farm_type'] == 'poultry') {
            $poultry[$index] = $row['total_sales'];
        } else {
            $ruminant[$index] = $row['total_sales'];
        }
    }
    
    return ['labels' => $labels, 'poultry' => $poultry, 'ruminant' => $ruminant];
}

function getExpenseData($period) {
    global $pdo;
    $farmId = requireCurrentFarmId();
    
    $dateFormat = $period == 'year' ? '%Y' : '%Y-%m';
    $limit = $period == 'year' ? 12 : 30;
    
    $query = "SELECT
                category,
                SUM(amount * unit) as total_amount
              FROM farm_expenses
              WHERE farm_id = ? AND expense_date >= DATE_SUB(CURDATE(), INTERVAL ? DAY)
              GROUP BY category
              ORDER BY total_amount DESC";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute([$farmId, $limit]);
    $data = $stmt->fetchAll();
    
    $labels = [];
    $values = [];
    
    foreach ($data as $row) {
        $labels[] = ucfirst($row['category']);
        $values[] = $row['total_amount'];
    }
    
    return ['labels' => $labels, 'values' => $values];
}

function getStockData($period) {
    global $pdo;
    $farmId = requireCurrentFarmId();
    $limit = $period == 'week' ? 7 : 30;
    $dateLimit = date('Y-m-d', strtotime("-{$limit} days"));

    // Reconstruct physical stock from the complete posted event stream.
    // Reversed originals remain in the ledger because their linked reversal
    // cancels them; filtering them out would double-count restorations.
    $query = "SELECT t.stock_item_id, s.item_name, t.transaction_type, t.quantity,
                     t.transaction_date, t.created_at, t.id
              FROM stock_transactions t
              JOIN stock_items s ON t.stock_item_id = s.id AND s.farm_id = t.farm_id
              WHERE t.farm_id = ?
              ORDER BY t.created_at, t.id";
    $stmt = $pdo->prepare($query);
    $stmt->execute([$farmId]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $running = [];
    $points = [];
    $names = [];
    foreach ($rows as $row) {
        $itemId = (int)$row['stock_item_id'];
        if (!isset($running[$itemId])) $running[$itemId] = 0.0;
        $running[$itemId] += $row['transaction_type'] === 'received' ? (float)$row['quantity'] : -(float)$row['quantity'];
        $names[$itemId] = $row['item_name'];
        $date = (string)$row['transaction_date'];
        if ($date >= $dateLimit) {
            $points[$itemId][$date] = round($running[$itemId], 2);
        }
    }

    $dates = [];
    foreach ($points as $itemPoints) {
        foreach (array_keys($itemPoints) as $date) $dates[$date] = true;
    }
    $dates = array_keys($dates);
    sort($dates);

    $datasets = [];
    foreach ($points as $itemId => $itemPoints) {
        $series = [];
        $last = null;
        foreach ($dates as $date) {
            if (array_key_exists($date, $itemPoints)) $last = $itemPoints[$date];
            $series[] = $last;
        }
        $datasets[] = [
            'label' => $names[$itemId],
            'data' => $series,
            'fill' => false
        ];
    }

    return [
        'labels' => array_map(static fn($date) => date('d M', strtotime($date)), $dates),
        'datasets' => $datasets
    ];
}

function getProductionData($period) {
    global $pdo;
    $farmId = requireCurrentFarmId();
    
    $limit = $period == 'month' ? 30 : 7;
    
    $query = "SELECT 
                record_date,
                egg_production,
                laying_rate
              FROM layer_daily_records
              WHERE farm_id = ? AND record_date >= DATE_SUB(CURDATE(), INTERVAL ? DAY)
              ORDER BY record_date";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute([$farmId, $limit]);
    $data = $stmt->fetchAll();
    
    $labels = [];
    $eggs = [];
    $rates = [];
    
    foreach ($data as $row) {
        $labels[] = date('d M', strtotime($row['record_date']));
        $eggs[] = $row['egg_production'];
        $rates[] = $row['laying_rate'];
    }
    
    return ['labels' => $labels, 'eggs' => $eggs, 'rates' => $rates];
}
?>
