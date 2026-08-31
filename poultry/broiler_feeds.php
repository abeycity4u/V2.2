<?php require_once(dirname(__DIR__) . '/init.php'); ?>
<?php
require_once(__DIR__ . '/../config.php');
require_once(__DIR__ . '/../lib/stock_transaction_display.php');
require_once(__DIR__ . '/../lib/stock_reporting.php');
require_once(__DIR__ . '/../lib/attribution.php');
require_once(__DIR__ . '/../lib/manual_feed_transactions.php');
requireLogin();

// Check access
$isOwner = isPlatformOwner() || hasRole('farm_admin');
if (!checkAccess('poultry') && !$isOwner) {
    header('Location: dashboard.php');
    exit();
}

$tenantFarmId = requireCurrentFarmId();
$month = $_GET['month'] ?? date('Y-m');
$yearMonth = date('Y-m', strtotime($month));
$ledgerView = (($_GET['ledger_view'] ?? 'operational') === 'audit') ? 'audit' : 'operational';
$monthSelectorDate = date('Y-m-d', strtotime($yearMonth . '-' . min((int)date('d'), (int)date('t', strtotime($yearMonth . '-01')))));
$startDate = date('Y-m-01', strtotime($yearMonth));
$endDate = date('Y-m-t', strtotime($yearMonth));

// Get feed transactions for the month
$query = "SELECT t.*, s.item_name, s.unit, u.full_name, pc.cycle_code, pc.production_type AS cycle_production_type
          FROM stock_transactions t
          JOIN stock_items s ON t.stock_item_id = s.id
          LEFT JOIN production_cycles pc ON pc.id=t.cycle_id AND pc.farm_id=t.farm_id
          LEFT JOIN users u ON t.user_id = u.id AND u.farm_id = t.farm_id
          WHERE t.farm_id = ? AND s.farm_id = ? AND t.transaction_date BETWEEN ? AND ?
          AND s.farm_type IN ('poultry', 'both')
          AND s.feed_category = 'broiler'
          ORDER BY t.transaction_date DESC, t.id DESC";
$stmt = $pdo->prepare($query);
$stmt->execute([$tenantFarmId, $tenantFarmId, $startDate, $endDate]);
$transactions = $stmt->fetchAll();
enrichStockTransactionDisplaySnapshots($pdo, $tenantFarmId, $transactions);
$displayTransactions = $ledgerView === 'audit' ? $transactions : array_values(array_filter($transactions, static fn($tx) => empty($tx['is_reversed']) && empty($tx['reversal_of_id'])));

// Get current poultry feed stock
$stockQuery = "SELECT * FROM stock_items
               WHERE farm_id = ? AND farm_type IN ('poultry', 'both')
               AND feed_category = 'broiler'
               AND is_active = 1
               ORDER BY current_stock ASC";
$stockStmt = $pdo->prepare($stockQuery);
$stockStmt->execute([$tenantFarmId]);
$feedItems = $stockStmt->fetchAll();

// Active production cycles available for financial cost allocation.
$cycleStmt = $pdo->prepare("SELECT id, cycle_code, production_type, start_date FROM production_cycles WHERE farm_id = ? AND status = 'active' AND farm_type = 'poultry' AND production_type = 'broiler' ORDER BY start_date DESC, id DESC");
$cycleStmt->execute([$tenantFarmId]);
$activeCycles = $cycleStmt->fetchAll(PDO::FETCH_ASSOC);


// Handle new transaction
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_transaction'])) {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        http_response_code(419);
        exit('Invalid request token.');
    }
    $itemId = (int)($_POST['feed_item'] ?? 0);
    $type = 'used';
    $quantity = (float)($_POST['quantity'] ?? 0);
    $cycleId = (int)($_POST['cycle_id'] ?? 0);
    $date = trim((string)($_POST['transaction_date'] ?? ''));
    $dateObject = DateTime::createFromFormat('Y-m-d', $date);
    $redirectMonth = $dateObject ? $dateObject->format('Y-m') : date('Y-m');
    if ($itemId <= 0 || $quantity <= 0 || !$dateObject || $dateObject->format('Y-m-d') !== $date) {
        $_SESSION['error'] = 'Please select a feed item, enter a quantity greater than zero, and provide a valid date.';
        header("Location: broiler_feeds.php?month={$redirectMonth}");
        exit();
    }
    try {
        $pdo->beginTransaction();
        create_manual_feed_transaction($pdo, $tenantFarmId, $itemId, $type, $quantity, $date,
            trim((string)($_POST['remarks'] ?? '')), $cycleId > 0 ? $cycleId : null,
            'poultry', 'broiler', (int)$_SESSION['user_id']);
        $pdo->commit();
        $_SESSION['success'] = 'Feed transaction recorded successfully!';
        header("Location: broiler_feeds.php?month={$redirectMonth}");
        exit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        $_SESSION['error'] = safeUserExceptionMessage($e, 'The feed transaction could not be completed.');
        header("Location: broiler_feeds.php?month={$redirectMonth}");
        exit();
    }
}

// Handle transaction deletion (owner only)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_transaction']) && $isOwner) {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        http_response_code(419);
        exit('Invalid request token.');
    }
    $transactionId = (int)($_POST['transaction_id'] ?? 0);
    try {
        $pdo->beginTransaction();
        $stmt = $pdo->prepare("SELECT transaction_date FROM stock_transactions WHERE id = ? AND farm_id = ? AND farm_type = ?");
        $stmt->execute([$transactionId, $tenantFarmId, 'poultry']);
        $transaction = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$transaction) throw new RuntimeException('Transaction not found.');
        delete_manual_feed_transaction($pdo, $tenantFarmId, $transactionId, (int)$_SESSION['user_id'], 'poultry');
        $pdo->commit();
        $_SESSION['success'] = 'Transaction deleted and stock restored successfully.';
        $redirectMonth = date('Y-m', strtotime($transaction['transaction_date']));
        header("Location: broiler_feeds.php?month={$redirectMonth}");
        exit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        $_SESSION['error'] = safeUserExceptionMessage($e, 'The feed transaction could not be completed.');
    }
}

// Handle transaction edit (owner only)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_transaction']) && $isOwner) {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        http_response_code(419);
        exit('Invalid request token.');
    }
    $transactionId = (int)($_POST['transaction_id'] ?? 0);
    $newItemId = (int)($_POST['feed_item'] ?? 0);
    $newType = trim((string)($_POST['transaction_type'] ?? 'used'));
    $newQuantity = (float)($_POST['quantity'] ?? 0);
    $newCycleId = (int)($_POST['cycle_id'] ?? 0);
    $newDate = trim((string)($_POST['transaction_date'] ?? ''));
    $newRemarks = trim((string)($_POST['remarks'] ?? ''));
    $dateObject = DateTime::createFromFormat('Y-m-d', $newDate);
    $redirectMonth = $dateObject ? $dateObject->format('Y-m') : date('Y-m');
    try {
        if ($newItemId <= 0 || $newQuantity <= 0 || !in_array($newType, ['received','used'], true) || !$dateObject || $dateObject->format('Y-m-d') !== $newDate) {
            throw new RuntimeException('Please provide a valid feed item, transaction type, positive quantity, and date.');
        }
        $pdo->beginTransaction();
        edit_manual_feed_transaction($pdo, $tenantFarmId, $transactionId, $newItemId, $newType, $newQuantity,
            $newDate, $newRemarks, $newCycleId > 0 ? $newCycleId : null,
            'poultry', 'broiler', (int)$_SESSION['user_id']);
        $pdo->commit();
        $_SESSION['success'] = 'Transaction updated. The original movement was preserved and reversed in the ledger.';
        header("Location: broiler_feeds.php?month={$redirectMonth}");
        exit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        $_SESSION['error'] = safeUserExceptionMessage($e, 'The feed transaction could not be completed.');
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
   <?php include(__DIR__ . '/../navbar_head.php'); ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Broiler Feeds Record - Renee Farms</title>
    <link rel="stylesheet" href="https://cdn.datatables.net/1.11.5/css/dataTables.bootstrap5.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/responsive/2.2.9/css/responsive.bootstrap5.min.css">
    <style>
        .stock-card {
            transition: transform 0.2s;
        }
        .stock-card:hover {
            transform: translateY(-5px);
        }
    
        /* V2.2.13: keep the three feed ledgers visually aligned and prevent
           DataTables from collapsing the header when action/origin labels are wide. */
        #feedsTable {
            width: 100% !important;
            min-width: 1320px;
        }
        #feedsTable thead th {
            background-color: #198754;
            color: #ffffff;
            white-space: nowrap;
            vertical-align: middle;
        }
        #feedsTable tbody td {
            vertical-align: middle;
        }
        #feedsTable_wrapper {
            width: 100%;
        }
    </style>
</head>
<body class="poultry-page">
    <?php include(__DIR__ . '/../navbar.php'); ?>
    
    <div class="container-fluid mt-4 poultry-shell">
        <div class="row">
            <div class="col-12">
                <div class="card poultry-panel">
                    <div class="card-header poultry-hero d-flex justify-content-between align-items-center flex-wrap gap-2">
                        <h4 class="mb-0">
                            <i class="bi bi-basket"></i> 
                            Broiler Feeds Record - <?php echo date('F Y', strtotime($yearMonth)); ?>
                        </h4>
                        <div class="d-flex flex-wrap gap-2">
                            <input type="date" class="form-control js-calendar-input" id="monthSelector" 
                                   value="<?php echo $monthSelectorDate; ?>" style="width: 200px;">
                            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addTransactionModal">
                                <i class="bi bi-plus-circle"></i> New Transaction
                            </button>
                        </div>
                    </div>
                    
                    <!-- Current Stock Summary -->
                    <div class="card-body">
                        <div class="smart-poultry-note p-3 mb-4 d-flex gap-3 align-items-start">
                            <i class="bi bi-stars fs-4"></i>
                            <div>
                                <div class="fw-bold">Feed stock intelligence</div>
                                <div class="small">These stock cards can power reorder alerts, feed conversion tracking and batch consumption forecasting.</div>
                            </div>
                        </div>
                        <h5 class="mb-3">Current Feed Stock</h5>
                        <div class="row mb-4">
                            <?php foreach ($feedItems as $item): 
                                $stockPercent = ($item['current_stock'] / ($item['min_stock_level'] * 2)) * 100;
                                $cardClass = $item['current_stock'] <= $item['min_stock_level'] ? 'border-danger' : 
                                            ($stockPercent <= 50 ? 'border-warning' : 'border-success');
                            ?>
                            <div class="col-md-3 mb-3 d-flex">
                                <div class="card stock-card <?php echo $cardClass; ?> h-100 w-100">
                                    <div class="card-body text-center">
                                        <h6 class="card-title"><?php echo $item['item_name']; ?></h6>
                                        <div class="mb-2">
                                            <span class="display-6 fw-bold <?php echo $item['current_stock'] <= $item['min_stock_level'] ? 'text-danger' : 'text-success'; ?>">
                                                <?php echo $item['current_stock']; ?>
                                            </span>
                                            <small class="text-muted d-block"><?php echo $item['unit']; ?></small>
                                        </div>
                                        <div class="progress" style="height: 8px;">
                                            <div class="progress-bar <?php echo $item['current_stock'] <= $item['min_stock_level'] ? 'bg-danger' : 'bg-success'; ?>" 
                                                 style="width: <?php echo min($stockPercent, 100); ?>%"></div>
                                        </div>
                                        <small class="text-muted">Min: <?php echo $item['min_stock_level']; ?></small>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        
                        <!-- Monthly Summary -->
                        <?php
                        $monthlySummary = stock_effective_movement_summary($transactions);
                        $summaryUnitLabel = stock_effective_summary_unit_label($transactions);
                        ?>
                        
                        <div class="row mb-4">
                            <div class="col-md-4">
                                <div class="card bg-success text-white">
                                    <div class="card-body text-center">
                                        <h6>Actual Received</h6>
                                        <h3>+<?php echo number_format($monthlySummary['received'], 2); ?></h3>
                                        <small><?php echo htmlspecialchars($summaryUnitLabel); ?></small>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="card bg-danger text-white">
                                    <div class="card-body text-center">
                                        <h6>Effective Used</h6>
                                        <h3><?php echo ((float)$monthlySummary['used'] > 0 ? '-' : ''); ?><?php echo number_format(abs((float)$monthlySummary['used']), 2); ?></h3>
                                        <small><?php echo htmlspecialchars($summaryUnitLabel); ?></small>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="card bg-info text-white">
                                    <div class="card-body text-center">
                                        <h6>Effective Net Change</h6>
                                        <h3><?php echo $monthlySummary['balance'] >= 0 ? '+' : ''; ?><?php echo number_format($monthlySummary['balance'], 2); ?></h3>
                                        <small><?php echo htmlspecialchars($summaryUnitLabel); ?></small>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Transactions Table -->
                        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-2">
                            <div><h5 class="mb-0">Monthly Transactions</h5><div class="small text-muted feed-view-note">Operational View shows current valid activity. Full Audit includes reversed originals and restoration rows.</div></div>
                            <div class="btn-group btn-group-sm feed-audit-toggle" role="group" aria-label="Transaction view"><a class="btn <?php echo $ledgerView==='operational'?'btn-primary active':'btn-outline-primary'; ?>" href="?month=<?php echo urlencode($yearMonth); ?>&ledger_view=operational">Operational View</a><a class="btn <?php echo $ledgerView==='audit'?'btn-primary active':'btn-outline-primary'; ?>" href="?month=<?php echo urlencode($yearMonth); ?>&ledger_view=audit">Full Audit</a></div>
                        </div>
                        <div class="table-responsive">
                            <table id="feedsTable" class="table table-striped table-hover align-middle poultry-table">
                                <thead class="table-dark">
                                    <tr>
                                        <th>Date</th>
                                        <th>Feed Item</th>
                                        <th>Type</th>
                                        <th>Quantity</th>
                                        <th>Previous Stock</th>
                                        <th>New Stock</th>
                                        <th>Unit</th>
                                        <th>Production Type</th>
                                        <th>Cycle</th>
                                        <th>Remarks</th>
                                        <th>Origin</th>
                                        <th>Recorded By</th>
                                        <?php if ($isOwner): ?>
                                            <th>Actions</th>
                                        <?php endif; ?>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($displayTransactions)): ?>
                                    <tr>
                                        <td colspan="<?php echo $isOwner ? '11' : '10'; ?>" class="text-center text-muted py-4">
                                            <i class="bi bi-inbox display-4 d-block mb-2"></i>
                                            No transactions recorded for this month
                                        </td>
                                    </tr>
                                    <?php else: ?>
                                        <?php foreach ($displayTransactions as $trans): ?>
                                        <tr class="<?php echo (!empty($trans['is_reversed']) || !empty($trans['reversal_of_id'])) ? 'feed-audit-row' : 'feed-operational-row'; ?>">
                                            <td>
                                                <span class="badge bg-secondary">
                                                    <?php echo date('d/m/Y', strtotime($trans['transaction_date'])); ?>
                                                </span>
                                            </td>
                                            <td><?php echo $trans['item_name']; ?></td>
                                            <td>
                                                <span class="badge bg-<?php echo $trans['transaction_type'] == 'received' ? 'success' : 'danger'; ?>">
                                                    <?php echo strtoupper($trans['transaction_type']); ?>
                                                </span>
                                                <?php if (!empty($trans['is_reversed'])): ?><span class="badge bg-secondary ms-1">Reversed</span><?php elseif (!empty($trans['reversal_of_id'])): ?><span class="badge bg-info text-dark ms-1">Restoration</span><?php endif; ?>
                                            </td>
                                            <td>
                                                <span class="fw-bold <?php echo $trans['transaction_type'] == 'received' ? 'text-success' : 'text-danger'; ?>">
                                                    <?php echo $trans['transaction_type'] == 'received' ? '⬆ +' : '⬇ -'; ?>
                                                    <?php echo number_format((float)$trans['quantity'], 2); ?>
                                                </span>
                                            </td>
                                            <td><?php echo number_format((float)$trans['display_previous_stock'], 2); ?></td>
                                            <td class="fw-bold"><?php echo number_format((float)$trans['display_new_stock'], 2); ?></td>
                                        <td><?php echo htmlspecialchars($trans['unit']); ?></td>
                                            <td><span class="badge bg-light text-dark border"><?php echo htmlspecialchars(attribution_label($trans['production_type'] ?? $trans['cycle_production_type'] ?? null)); ?></span></td>
                                            <td><?php echo !empty($trans['cycle_code']) ? htmlspecialchars($trans['cycle_code']) : '<span class="text-muted">Pooled / Unallocated</span>'; ?></td>
                                            <td>
                                                <?php if ($trans['remarks']): ?>
                                                <small class="text-muted"><?php echo htmlspecialchars($trans['remarks'], ENT_QUOTES); ?></small>
                                                <?php else: ?>
                                                <span class="text-muted">--</span>
                                                <?php endif; ?>
                                            </td>
                                            <td><?php if (!empty($trans['source_type']) && str_starts_with((string)$trans['source_type'], 'daily_')): ?><span class="badge bg-primary">Daily Record</span><?php else: ?><span class="badge bg-secondary">Stock / Manual</span><?php endif; ?></td>
                                            <td>
                                                <small><?php echo htmlspecialchars($trans['full_name'] ?? 'System'); ?></small>
                                            </td>
                                            <?php if ($isOwner): ?>
                                            <td>
                                                <div class="d-flex gap-2 align-items-center">
                                                    <?php if (!empty($trans['is_reversed'])): ?>
                                                        <span class="badge bg-light text-secondary border" title="This Daily Record movement has been reversed and is kept for audit history.">Reversed — Daily Record</span>
                                                    <?php elseif (!empty($trans['reversal_of_id'])): ?>
                                                        <span class="badge bg-light text-info border" title="This is an automatic restoration linked to a Daily Record edit or deletion.">Restoration — Daily Record</span>
                                                    <?php elseif (!empty($trans['source_type']) && str_starts_with((string)$trans['source_type'], 'daily_')): ?>
                                                        <span class="badge bg-light text-primary border" title="Managed from the daily record">Managed by Daily Record</span>
                                                    <?php else: ?>
                                                    <button
                                                        type="button"
                                                        class="btn btn-sm btn-outline-primary edit-transaction"
                                                        data-id="<?php echo $trans['id']; ?>"
                                                        data-date="<?php echo date('Y-m-d', strtotime($trans['transaction_date'])); ?>"
                                                        data-item="<?php echo $trans['stock_item_id']; ?>"
                                                        data-type="<?php echo $trans['transaction_type']; ?>"
                                                        data-cycle="<?php echo (int)($trans['cycle_id'] ?? 0); ?>"
                                                        data-quantity="<?php echo number_format((float)$trans['quantity'], 2); ?>"
                                                        data-remarks="<?php echo htmlspecialchars($trans['remarks'], ENT_QUOTES); ?>"
                                                    >
                                                        <i class="bi bi-pencil-square"></i>
                                                    </button>
                                                    <form method="POST" data-confirm="Delete this transaction? This action cannot be undone." data-confirm-title="Delete feed transaction?" data-confirm-button="Delete">
                                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrf_token(), ENT_QUOTES); ?>">
                                                        <input type="hidden" name="transaction_id" value="<?php echo $trans['id']; ?>">
                                                        <button type="submit" name="delete_transaction" class="btn btn-sm btn-outline-danger">
                                                            <i class="bi bi-trash"></i>
                                                        </button>
                                                    </form>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                            <?php endif; ?>
                                        </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Edit Transaction Modal -->
    <div class="modal fade" id="editTransactionModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST">
                                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrf_token(), ENT_QUOTES); ?>">
                    <input type="hidden" name="transaction_id" id="editTransactionId">
                    <div class="modal-header">
                        <h5 class="modal-title">Edit Feed Transaction</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label>Date</label>
                            <input type="date" name="transaction_date" id="editTransactionDate" class="form-control" required>
                        </div>

                        <div class="mb-3">
                            <label>Feed Item</label>
                            <select name="feed_item" id="editFeedItem" class="form-select" required>
                                <option value="">Select Feed</option>
                                <?php foreach ($feedItems as $item): ?>
                                <option value="<?php echo $item['id']; ?>">
                                    <?php echo $item['item_name']; ?>
                                    (Current: <?php echo $item['current_stock']; ?> <?php echo $item['unit']; ?>)
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label>Production Cycle <span class="text-muted">(optional)</span></label>
                            <select name="cycle_id" id="editCycleId" class="form-select">
                                <option value="0">Not assigned</option>
                                <?php foreach ($activeCycles as $cycle): ?>
                                <option value="<?php echo (int)$cycle['id']; ?>"><?php echo htmlspecialchars($cycle['cycle_code'] . ' — ' . $cycle['production_type']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label>Transaction Type</label>
                                <select name="transaction_type" id="editTransactionType" class="form-select" required>
                                    <option value="used">⬇ Used Stock (-)</option>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label>Quantity</label>
                                <input type="number" name="quantity" id="editQuantity" class="form-control"
                                       step="0.01" min="0.01" required>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label>Remarks</label>
                            <input type="text" name="remarks" id="editRemarks" class="form-control"
                                   placeholder="Optional remarks">
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="edit_transaction" class="btn btn-primary">Update Transaction</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Add Transaction Modal -->
    <div class="modal fade" id="addTransactionModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST">
                                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrf_token(), ENT_QUOTES); ?>">
                    <div class="modal-header">
                        <h5 class="modal-title">Record Feed Transaction</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Date</label>
                            <input type="date" name="transaction_date" class="form-control"
                                   value="<?php echo date('Y-m-d'); ?>" required>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Feed Item</label>
                            <select name="feed_item" class="form-select" required>
                                <option value="">Select Feed</option>
                                <?php foreach ($feedItems as $item): ?>
                                <option value="<?php echo $item['id']; ?>">
                                    <?php echo $item['item_name']; ?>
                                    (Available: <?php echo $item['current_stock']; ?> <?php echo $item['unit']; ?>)
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Production Cycle <span class="text-muted">(optional)</span></label>
                            <select name="cycle_id" class="form-select">
                                <option value="0">Not assigned</option>
                                <?php foreach ($activeCycles as $cycle): ?>
                                <option value="<?php echo (int)$cycle['id']; ?>"><?php echo htmlspecialchars($cycle['cycle_code'] . ' — ' . $cycle['production_type']); ?></option>
                                <?php endforeach; ?>
                            </select>
                            <small class="text-muted">Assigning a cycle lets V2.2 calculate production cost and profitability accurately.</small>
                        </div>

                        <div class="row align-items-end g-3">
                            <div class="col-md-6">
                                <label class="form-label">Transaction Type</label>
                                <select class="form-select" disabled>
                                    <option selected>⬇ Used Stock (-)</option>
                                </select>
                                <input type="hidden" name="transaction_type" value="used">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Quantity <small class="text-danger">(will be deducted)</small></label>
                                <input type="number" name="quantity" class="form-control" step="0.01" min="0.01" required>
                            </div>
                        </div>

                        <div class="mt-3">
                            <div class="alert alert-info mb-3 py-2">
                                To add stock, use the <strong>Update Stock</strong> action.
                            </div>
                            <label class="form-label">Remarks</label>
                            <input type="text" name="remarks" class="form-control"
                                   placeholder="e.g., From supplier, For broiler house A, etc.">
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="add_transaction" class="btn btn-primary">
                            <i class="bi bi-check-circle"></i> Save Transaction
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

     <script src="<?php echo BASE_URL; ?><?php echo versioned_asset('/assets/vendor/jquery/jquery.min.js'); ?>"></script>
    <script src="<?php echo BASE_URL; ?><?php echo versioned_asset('/assets/vendor/bootstrap5/js/bootstrap.bundle.min.js'); ?>"></script>
    <script src="<?php echo BASE_URL; ?><?php echo versioned_asset('/assets/vendor/datatables/js/jquery.dataTables.min.js'); ?>"></script>
    <script src="<?php echo BASE_URL; ?><?php echo versioned_asset('/assets/vendor/datatables/js/dataTables.bootstrap5.min.js'); ?>"></script>
    <script src="<?php echo BASE_URL; ?><?php echo versioned_asset('/assets/vendor/datatables-responsive/js/dataTables.responsive.min.js'); ?>"></script>
    <script src="<?php echo BASE_URL; ?><?php echo versioned_asset('/assets/js/main.js'); ?>"></script>
 <script src="<?php echo BASE_URL; ?>/assets/js/edit-modal.js"></script>

    <!-- <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.11.5/js/dataTables.bootstrap5.min.js"></script>
    <script src="https://cdn.datatables.net/responsive/2.2.9/js/dataTables.responsive.min.js"></script>
    <script src="https://cdn.datatables.net/responsive/2.2.9/js/responsive.bootstrap5.min.js"></script> -->
    <script>
    $(document).ready(function() {
        const feedTable = $('#feedsTable').DataTable({
            autoWidth: false,
            order: [[0, 'desc']],
            pageLength: 25,
            responsive: true,
            columnDefs: [
                { responsivePriority: 1, targets: 0 },
                { responsivePriority: 2, targets: -1 }
            ]
        });
        
        // Month selector
        $('#monthSelector').change(function() {
            window.location.href = 'broiler_feeds.php?month=' + this.value.substring(0, 7) + '&ledger_view=<?php echo $ledgerView; ?>';
        });
        
        // Show messages

        $('.edit-transaction').on('click', function() {
            const button = $(this);
            $('#editTransactionId').val(button.data('id'));
            $('#editTransactionDate').val(button.data('date'));
            $('#editFeedItem').val(button.data('item'));
            $('#editTransactionType').val(button.data('type'));
            $('#editCycleId').val(button.data('cycle') || 0);
            $('#editQuantity').val(button.data('quantity'));
            $('#editRemarks').val(button.data('remarks'));
            bootstrap.Modal.getOrCreateInstance(document.getElementById('editTransactionModal')).show();
        });
    });
    
    </script>
</body>
</html>
