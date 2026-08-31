<?php require_once(dirname(__DIR__) . '/init.php'); ?>
<?php
require_once(__DIR__ . '/../config.php');
require_once(__DIR__ . '/../includes/pdf/PdfReportService.php');
require_once(__DIR__ . '/../includes/functions.php');
require_once(__DIR__ . '/../lib/attribution.php');
requireLogin();
$pdfRequested = pdf_report_is_requested();
if ($pdfRequested) { pdf_report_begin(); }

// Check access
if (!checkAccess('ruminant') && !hasPermission($_SESSION['user_type'], 'ruminant_expenses')) {
    header('Location: ' . BASE_URL . '/no_access.php');
    exit();
}

$canManageExpenses = isPlatformOwner() || hasRole('farm_admin') || hasPermission($_SESSION['user_type'], 'ruminant_expenses');
$tenantFarmId = requireCurrentFarmId();
$expenseCycleStmt = $pdo->prepare("SELECT id,cycle_code,production_type,status FROM production_cycles WHERE farm_id=? AND farm_type='ruminant' ORDER BY start_date DESC,id DESC");
$expenseCycleStmt->execute([$tenantFarmId]);
$expenseCycles = $expenseCycleStmt->fetchAll(PDO::FETCH_ASSOC);

$month = $_GET['month'] ?? date('Y-m');
$yearMonth = date('Y-m', strtotime($month));
$monthSelectorDate = date('Y-m-d', strtotime($yearMonth . '-' . min((int)date('d'), (int)date('t', strtotime($yearMonth . '-01')))));
$startDate = date('Y-m-01', strtotime($yearMonth . '-01'));
$endDate = date('Y-m-t', strtotime($yearMonth . '-01'));

// Get expenses for the month
$query = "SELECT e.*, u.full_name
          FROM farm_expenses e
          LEFT JOIN users u ON e.user_id = u.id AND u.farm_id = e.farm_id
          WHERE e.farm_id = ? AND e.expense_date BETWEEN ? AND ?
          AND e.farm_type IN ('ruminant', 'both')
          ORDER BY e.expense_date DESC";
$stmt = $pdo->prepare($query);
$stmt->execute([$tenantFarmId, $startDate, $endDate]);
$expenses = $stmt->fetchAll();

// Calculate category totals
$categoryTotals = [
    'feeds' => 0,
    'medication' => 0,
    'salary' => 0,
    'logistic' => 0,
    'fuel' => 0,
    'misc' => 0
];

foreach ($expenses as $expense) {
    if (!isset($categoryTotals[$expense['category']])) {
        $categoryTotals[$expense['category']] = 0;
    }

    $lineTotal = ($expense['amount'] ?? 0) * ($expense['unit'] ?? 1);
    $categoryTotals[$expense['category']] += $lineTotal;
}

$totalExpenses = array_sum($categoryTotals);

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_expense'])) {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        http_response_code(419);
        exit('Invalid request token.');
    }
    $expenseDate = trim((string)($_POST['expense_date'] ?? ''));
    $dateObject = DateTime::createFromFormat('Y-m-d', $expenseDate);
    $amount = (float)($_POST['amount'] ?? 0);
    $unit = (float)($_POST['unit'] ?? 1);
    $expenseCategory = trim((string)($_POST['category'] ?? ''));
    $allowedCategories = ['feeds', 'medication', 'salary', 'logistic', 'fuel', 'misc'];
    if (!$dateObject || $dateObject->format('Y-m-d') !== $expenseDate || $amount <= 0 || $unit <= 0 || !in_array($expenseCategory, $allowedCategories, true)) {
        $_SESSION['error'] = 'Please provide a valid date, category, amount and quantity greater than zero.';
        header("Location: ruminant_expenses.php?month=" . date('Y-m', strtotime($expenseDate ?: 'now')));
        exit();
    }

    $productionType = attribution_normalize_production_type('ruminant', $_POST['production_type'] ?? 'shared');
    $cycleId = (int)($_POST['cycle_id'] ?? 0);
    if ($cycleId > 0) {
        try { attribution_validate_cycle($pdo, $tenantFarmId, $cycleId, 'ruminant', $productionType); }
        catch (RuntimeException $e) {
            $_SESSION['error']=$e->getMessage();
            header("Location: ruminant_expenses.php?month=" . date('Y-m', strtotime($expenseDate)));
            exit();
        }
    }
    $scope = attribution_scope($cycleId > 0 ? $cycleId : null, 'ruminant', $productionType);
    $stmt = $pdo->prepare("INSERT INTO farm_expenses
        (farm_id, expense_date, farm_type, production_type, attribution_scope, cycle_id, category, amount, unit, description, user_id)
        VALUES (?, ?, 'ruminant', ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([
        $tenantFarmId, $expenseDate, $productionType, $scope, $cycleId > 0 ? $cycleId : null,
        $expenseCategory, $amount, $unit, trim((string)($_POST['description'] ?? '')), $_SESSION['user_id']
    ]);

    $_SESSION['success'] = "Ruminant expense recorded successfully!";
    $redirectMonth = date('Y-m', strtotime($expenseDate));
    header("Location: ruminant_expenses.php?month=" . $redirectMonth);
    exit();
}
$pdfReportUrl = pdf_report_current_url();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <?php include(__DIR__ . '/../navbar_head.php'); ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ruminant Expenses Record - Renee Farms</title>
</head>
<body class="ruminant-page">
    <?php include(__DIR__ . '/../navbar.php'); ?>

    <div class="container-fluid mt-4 poultry-shell">

        <div class="row">
            <div class="col-12">
                <div class="card poultry-panel">
                    <div class="card-header poultry-hero d-flex justify-content-between align-items-center flex-wrap gap-2">
                        <h4 class="mb-0">
                            <i class="bi bi-cash-coin"></i> 
                            Ruminant Expenses Record - <?php echo date('F Y', strtotime($yearMonth)); ?>
                        </h4>
                        <div class="d-flex flex-wrap gap-2">
                            <input type="date" class="form-control js-calendar-input" id="monthSelector" 
                                   value="<?php echo $monthSelectorDate; ?>" style="width: 200px;">
                            <a class="btn btn-outline-primary" href="<?php echo htmlspecialchars($pdfReportUrl); ?>" target="_blank"><i class="bi bi-file-earmark-pdf"></i> PDF Report</a>
                            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addExpenseModal">
                                <i class="bi bi-plus-circle"></i> Add Expense
                            </button>
                        </div>
                    </div>
                    
                    <div class="card-body">
                        <div class="smart-poultry-note p-3 mb-4 d-flex gap-3 align-items-start">
                            <i class="bi bi-stars fs-4"></i>
                            <div>
                                <div class="fw-bold">Ruminant cost intelligence</div>
                                <div class="small">Expense categories can support cost-per-head, budget variance and unusual-spend alerts.</div>
                            </div>
                        </div>
                        <!-- Expense Summary -->
                        <div class="row mb-4">
                            <?php foreach ($categoryTotals as $category => $total): 
                                if ($total > 0):
                            ?>
                            <div class="col-md-2 mb-3">
                                <div class="card border-<?php 
                                    switch($category) {
                                        case 'feeds': echo 'primary'; break;
                                        case 'medication': echo 'success'; break;
                                        case 'salary': echo 'warning'; break;
                                        case 'logistic': echo 'info'; break;
                                        case 'fuel': echo 'secondary'; break;
                                        default: echo 'dark';
                                    }
                                ?>">
                                    <div class="card-body text-center">
                                        <h6 class="card-title text-uppercase"><?php echo $category; ?></h6>
                                        <h4 class="text-danger">₦<?php echo number_format($total, 2); ?></h4>
                                    </div>
                                </div>
                            </div>
                            <?php endif; endforeach; ?>
                        </div>
                        
                        <!-- Total Expenses Card -->
                        <div class="card bg-danger text-white mb-4">
                            <div class="card-body text-center">
                                <h2>TOTAL RUMINANT EXPENSES: ₦<?php echo number_format($totalExpenses, 2); ?></h2>
                                <small>For <?php echo date('F Y', strtotime($yearMonth)); ?></small>
                            </div>
                        </div>
                        
                        <!-- Detailed Expenses Table -->
                        <h5>Detailed Expenses</h5>
                        <div class="table-responsive">
                            <table class="table table-striped table-hover poultry-table">
                                <thead class="table-dark">
                                    <tr>
                                        <th>Date</th>
                                        <th>Category</th>
                                        <th>Unit</th>
                                        <th>Amount (₦/unit)</th>
                                        <th>Total (₦)</th>
                                        <th>Description</th>
                                        <th>Recorded By</th>
                                        <?php if ($canManageExpenses): ?>
                                        <th>Actions</th>
                                        <?php endif; ?>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($expenses)): ?>
                                    <tr>
                                        <td colspan="<?php echo $canManageExpenses ? 8 : 7; ?>" class="text-center text-muted py-4">
                                            <i class="bi bi-receipt display-4 d-block mb-2"></i>
                                            No expenses recorded for this month
                                        </td>
                                    </tr>
                                    <?php else: ?>
                                        <?php foreach ($expenses as $expense): ?>
                                        <tr>
                                            <td>
                                                <strong><?php echo date('d/m/Y', strtotime($expense['expense_date'])); ?></strong>
                                            </td>
                                            <td>
                                                <span class="badge bg-<?php
                                                    switch($expense['category']) {
                                                        case 'feeds': echo 'primary'; break;
                                                        case 'medication': echo 'success'; break;
                                                        case 'salary': echo 'warning'; break;
                                                        case 'logistic': echo 'info'; break;
                                                        case 'fuel': echo 'secondary'; break;
                                                        default: echo 'dark';
                                                    }
                                                ?>">
                                                    <?php echo ucfirst($expense['category']); ?>
                                                </span>
                                            </td>
                                            <td><?php echo number_format($expense['unit'] ?? 1, 2); ?></td>
                                            <td class="text-danger fw-bold">
                                                ₦<?php echo number_format($expense['amount'], 2); ?>
                                            </td>
                                            <td class="text-danger fw-bold">
                                                ₦<?php echo number_format(($expense['amount'] ?? 0) * ($expense['unit'] ?? 1), 2); ?>
                                            </td>
                                            <td>
                                                <?php echo $expense['description'] ?: '--'; ?>
                                            </td>
                                            <td>
                                                <small><?php echo $expense['full_name']; ?></small>
                                            </td>
                                            <?php if ($canManageExpenses): ?>
                                            <td>
                                                <button class="btn btn-sm btn-outline-primary edit-expense-btn"
                                                        data-id="<?php echo $expense['id']; ?>"
                                                        data-date="<?php echo $expense['expense_date']; ?>"
                                                        data-category="<?php echo $expense['category']; ?>"
                                                        data-amount="<?php echo $expense['amount']; ?>"
                                                        data-unit="<?php echo $expense['unit'] ?? 1; ?>"
                                                        data-description="<?php echo htmlspecialchars($expense['description'] ?? '', ENT_QUOTES); ?>"
                                                        data-production-type="<?php echo htmlspecialchars($expense['production_type'] ?? 'shared', ENT_QUOTES); ?>"
                                                        data-cycle="<?php echo (int)($expense['cycle_id'] ?? 0); ?>"
                                                        >
                                                    <i class="bi bi-pencil"></i>
                                                </button>
                                                <button class="btn btn-sm btn-outline-danger"
                                                        onclick="deleteExpense(<?php echo $expense['id']; ?>)">
                                                    <i class="bi bi-trash"></i>
                                                </button>
                                            </td>
                                            <?php endif; ?>
                                        </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                                <tfoot class="table-secondary">
                                    <tr>
                                        <td colspan="4"><strong>TOTAL</strong></td>
                                        <td class="text-danger fw-bold">₦<?php echo number_format($totalExpenses, 2); ?></td>
                                        <td colspan="<?php echo $canManageExpenses ? 3 : 2; ?>"></td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php if ($canManageExpenses): ?>
    <!-- Edit Expense Modal -->
    <div class="modal fade" id="editExpenseModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form id="editExpenseForm">
                    <div class="modal-header">
                        <h5 class="modal-title">Edit Expense</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="expense_id" id="editExpenseId">
                        <input type="hidden" name="farm_type" value="ruminant">
                        <div class="mb-3">
                            <label>Date</label>
                            <input type="date" name="expense_date" id="editExpenseDate" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label>Production Type</label>
                            <select name="production_type" id="editExpenseProduction" class="form-select" required>
                                <option value="cattle">Cattle</option><option value="goat">Goat</option><option value="sheep">Sheep</option><option value="other">Other</option><option value="shared">Shared Ruminant</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label>Production Cycle (optional)</label>
                            <select name="cycle_id" id="editExpenseCycle" class="form-select"><option value="0">Shared between ruminant cycles</option></select>
                            <small class="text-muted">Choose a matching species cycle only when this expense belongs directly to it.</small>
                        </div>
                        <div class="mb-3">
                            <label>Category</label>
                            <select name="category" id="editCategory" class="form-select" required>
                                <option value="feeds">Feeds</option>
                                <option value="medication">Medication</option>
                                <option value="salary">Salary</option>
                                <option value="logistic">Logistic</option>
                                <option value="fuel">Fuel</option>
                                <option value="misc">Misc</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label>Unit</label>
                            <input type="number" name="unit" id="editUnit" class="form-control" step="0.01" min="0.01" required>
                        </div>
                        <div class="mb-3">
                            <label>Amount (₦)</label>
                            <input type="number" name="amount" id="editAmount" class="form-control" step="0.01" min="0.01" required>
                        </div>
                        <div class="mb-3">
                            <label>Description</label>
                            <textarea name="description" id="editDescription" class="form-control" rows="3"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Save Changes</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Add Expense Modal -->
    <div class="modal fade" id="addExpenseModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrf_token(), ENT_QUOTES); ?>">
                    <div class="modal-header">
                        <h5 class="modal-title">Record Ruminant Expense</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label>Date</label>
                            <input type="date" name="expense_date" class="form-control" 
                                   value="<?php echo date('Y-m-d'); ?>" required>
                        </div>
                        
                        <div class="mb-3">
                            <label>Production Type</label>
                            <select name="production_type" id="ruminantExpenseProduction" class="form-select" required>
                                <option value="cattle">Cattle</option><option value="goat">Goat</option><option value="sheep">Sheep</option><option value="other">Other</option><option value="shared" selected>Shared / Unallocated Ruminant</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label>Production Cycle (optional)</label>
                            <select name="cycle_id" id="ruminantExpenseCycle" class="form-select"><option value="0">Shared between ruminant cycles</option></select>
                            <small class="text-muted">A shared feed or expense can remain unallocated; choose a species cycle only when known.</small>
                        </div>
                        <div class="mb-3">
                            <label>Category</label>
                            <select name="category" class="form-select" required>
                                <option value="feeds">Feeds</option>
                                <option value="medication">Medication</option>
                                <option value="salary">Salary</option>
                                <option value="logistic">Logistic</option>
                                <option value="fuel">Fuel</option>
                                <option value="misc">Miscellaneous</option>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label>Unit</label>
                            <input type="number" name="unit" class="form-control" step="0.01" min="0.01" value="1" required>
                        </div>

                        <div class="mb-3">
                            <label>Amount (₦)</label>
                            <input type="number" name="amount" class="form-control"
                                   step="0.01" min="0.01" required>
                        </div>
                        
                        <div class="mb-3">
                            <label>Description</label>
                            <textarea name="description" class="form-control" rows="3" 
                                      placeholder="Describe the expense (e.g., Cattle feed purchase, Veterinary services, etc.)"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="add_expense" class="btn btn-primary">Save Expense</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <script>
    const ruminantExpenseCycles = <?php echo json_encode($expenseCycles, JSON_UNESCAPED_SLASHES); ?>;
    function refreshRuminantExpenseCycles() {
        const p=document.getElementById('ruminantExpenseProduction');
        const c=document.getElementById('ruminantExpenseCycle');
        if(!p||!c) return;
        c.innerHTML='<option value="0">Shared between ruminant cycles</option>';
        ruminantExpenseCycles.filter(x => x.production_type===p.value).forEach(x => c.add(new Option(`${x.cycle_code} — ${x.status}`, String(x.id))));
    }
    document.addEventListener('DOMContentLoaded',()=>{ refreshRuminantExpenseCycles(); document.getElementById('ruminantExpenseProduction')?.addEventListener('change',refreshRuminantExpenseCycles); });
    function refreshEditRuminantExpenseCycles(selectedCycle = 0) {
        const p=document.getElementById('editExpenseProduction');
        const c=document.getElementById('editExpenseCycle');
        if(!p||!c) return;
        c.innerHTML='<option value="0">Shared between ruminant cycles</option>';
        ruminantExpenseCycles.filter(x => x.production_type===p.value).forEach(x => c.add(new Option(`${x.cycle_code} — ${x.status}`, String(x.id))));
        c.value = String(selectedCycle || 0);
        if (c.value !== String(selectedCycle || 0)) c.value = '0';
    }
    document.addEventListener('DOMContentLoaded',()=>{ document.getElementById('editExpenseProduction')?.addEventListener('change',()=>refreshEditRuminantExpenseCycles(0)); });
    </script>
    <script src="<?php echo BASE_URL; ?><?php echo versioned_asset('/assets/vendor/jquery/jquery.min.js'); ?>"></script>
    <script src="<?php echo BASE_URL; ?><?php echo versioned_asset('/assets/vendor/bootstrap5/js/bootstrap.bundle.min.js'); ?>"></script>
    <script src="<?php echo BASE_URL; ?><?php echo versioned_asset('/assets/vendor/datatables/js/jquery.dataTables.min.js'); ?>"></script>
    <script src="<?php echo BASE_URL; ?><?php echo versioned_asset('/assets/vendor/datatables/js/dataTables.bootstrap5.min.js'); ?>"></script>
    <script src="<?php echo BASE_URL; ?><?php echo versioned_asset('/assets/vendor/datatables-responsive/js/dataTables.responsive.min.js'); ?>"></script>
    <script src="<?php echo BASE_URL; ?><?php echo versioned_asset('/assets/js/main.js'); ?>"></script>

 <script src="<?php echo BASE_URL; ?>/assets/js/edit-modal.js"></script>
    <script>
    // Month selector
    document.getElementById('monthSelector').addEventListener('change', function() {
        window.location.href = 'ruminant_expenses.php?month=' + this.value.substring(0, 7);
    });

    <?php if ($canManageExpenses): ?>
    attachEditModal({
        buttonSelector: '.edit-expense-btn',
        modalSelector: '#editExpenseModal',
        fieldMap: {
            id: '#editExpenseId',
            date: '#editExpenseDate',
            category: '#editCategory',
            amount: '#editAmount',
            unit: '#editUnit',
            description: '#editDescription',
            productionType: '#editExpenseProduction'
        }
    });

    document.querySelectorAll('.edit-expense-btn').forEach(btn => btn.addEventListener('click', () => {
        requestAnimationFrame(() => {
            const production=document.getElementById('editExpenseProduction');
            if (production) production.value = btn.dataset.productionType || 'shared';
            refreshEditRuminantExpenseCycles(parseInt(btn.dataset.cycle || '0', 10));
        });
    }));

    document.getElementById('editExpenseForm').addEventListener('submit', async function(e) {
        e.preventDefault();
        const formData = new FormData(this);
        formData.append('csrf_token', '<?php echo csrf_token(); ?>');

        try {
            const response = await fetch('../api/update_expense.php', {
                method: 'POST',
                body: formData
            });
            const result = await parseJsonResponse(response);

            if (result.success) {
                location.reload();
            } else {
                AppNotify.error((result.error || result.message || 'Unable to update expense'));
            }
        } catch (error) {
            AppNotify.error('Network error: ' + error.message);
        }
    });
    <?php endif; ?>
    
    async function parseJsonResponse(response) {
        const contentType = response.headers.get('content-type') || '';

        if (!contentType.includes('application/json')) {
            const text = await response.text();
            throw new Error(text || 'Unexpected non-JSON response');
        }

        return response.json();
    }

    async function deleteExpense(expenseId) {
        const confirmed = await AppConfirm.ask('Are you sure you want to delete this expense record?', {title:'Delete expense record?', confirmText:'Delete'});
        if (!confirmed) return;

        try {
            const params = new URLSearchParams({ id: expenseId, csrf_token: '<?php echo csrf_token(); ?>' });
            const response = await fetch('../api/delete_expense.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: params.toString()
            });
            const data = await parseJsonResponse(response);

            if (data.success) {
                location.reload();
            } else {
                AppNotify.error(data.error || data.message || 'Unable to delete expense');
            }
        } catch (error) {
            AppNotify.error('Network error: ' + error.message);
        }
    }

    // Show messages
    </script>
</body>
</html>
<?php
if ($pdfRequested) {
    pdf_report_finish('ruminant-expenses-' . $yearMonth . '.pdf', 'landscape', 'Ruminant Expenses Record - ' . date('F Y', strtotime($yearMonth)));
}
?>
