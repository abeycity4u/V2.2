<?php require_once(dirname(__DIR__) . '/init.php'); ?>
<?php
require_once(__DIR__ . '/../config.php');
require_once(__DIR__ . '/../includes/functions.php');
requireLogin();
requireBusinessReportAccess();
$tenantFarmId = requireCurrentFarmId();
if (!isPlatformOwner() && !hasRole('farm_admin') && !hasPermission(getUserType(), 'production_cycles')) { header('Location: ' . BASE_URL . '/no_access.php'); exit(); }

$cycleTableExists = false;
$stockBatchTableExists = false;
$migration002Recorded = false;
$errorMessage = null;
$flash = null;

$summary = [
    'active_cycles' => 0,
    'planned_cycles' => 0,
    'closed_cycles' => 0,
    'stock_batches' => 0,
    'total_current_stock' => 0,
];
$recentCycles = [];
$activeCycles = [];
$closedCycleDetails = [];
$recentStockBatches = [];

// Preserve the Create Cycle form after validation/duplicate errors so the user
// can correct only the problematic field instead of re-entering everything.
$createCycleForm = [
    'cycle_code' => '',
    'farm_type' => 'poultry',
    'production_type' => 'layer',
    'start_date' => '',
    'expected_end_date' => '',
    'opening_headcount' => '0',
    'start_age_days' => '1',
    'notes' => '',
];

/**
 * Estimate cycle current stock using latest daily record(s).
 */
function getCycleCurrentStock(PDO $pdo, array $cycle): int
{
    $cycleId = (int)($cycle['id'] ?? 0);
    $farmType = strtolower((string)($cycle['farm_type'] ?? ''));
    $productionType = strtolower((string)($cycle['production_type'] ?? ''));

    if ($cycleId <= 0) {
        return 0;
    }

    if ($farmType === 'poultry' && $productionType === 'layer') {
        $stmt = $pdo->prepare(
            "SELECT opening_stock, mortality
             FROM layer_daily_records
             WHERE cycle_id = ?
             ORDER BY record_date DESC, id DESC
             LIMIT 1"
        );
        $stmt->execute([$cycleId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ? max(0, (int)$row['opening_stock'] - (int)$row['mortality']) : 0;
    }

    if ($farmType === 'poultry' && $productionType === 'broiler') {
        $stmt = $pdo->prepare(
            "SELECT opening_stock, mortality
             FROM broiler_daily_records
             WHERE cycle_id = ?
             ORDER BY record_date DESC, id DESC
             LIMIT 1"
        );
        $stmt->execute([$cycleId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ? max(0, (int)$row['opening_stock'] - (int)$row['mortality']) : 0;
    }

    if ($farmType === 'ruminant') {
        $latestDateStmt = $pdo->prepare(
            "SELECT MAX(record_date) FROM ruminant_daily_records WHERE cycle_id = ?"
        );
        $latestDateStmt->execute([$cycleId]);
        $latestDate = $latestDateStmt->fetchColumn();
        if (!$latestDate) {
            return 0;
        }

        $sumStmt = $pdo->prepare(
            "SELECT COALESCE(SUM(opening_stock - mortality), 0)
             FROM ruminant_daily_records
             WHERE cycle_id = ? AND record_date = ?"
        );
        $sumStmt->execute([$cycleId, $latestDate]);

        return max(0, (int)$sumStmt->fetchColumn());
    }

    return 0;
}

try {
    $cycleTableExists = ($pdo->query("SHOW TABLES LIKE 'production_cycles'")->rowCount() > 0);
    $stockBatchTableExists = ($pdo->query("SHOW TABLES LIKE 'stock_batches'")->rowCount() > 0);
    $migrationCheckStmt = $pdo->prepare("SELECT COUNT(*) FROM schema_migrations WHERE filename = ?");
    $migrationCheckStmt->execute(['002_production_cycles.sql']);
    $migration002Recorded = ((int)$migrationCheckStmt->fetchColumn() > 0);

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && $cycleTableExists) {
        if (!(isPlatformOwner() || hasRole('farm_admin'))) { http_response_code(403); exit('Production-cycle management access required.'); }
        if (!verify_csrf_token($_POST['csrf_token'] ?? '')) { http_response_code(419); exit('Invalid request token.'); }
        $action = $_POST['action'] ?? '';

        if ($action === 'create_cycle') {
            $cycleCode = trim((string)($_POST['cycle_code'] ?? ''));
            $farmType = strtolower(trim((string)($_POST['farm_type'] ?? '')));
            $productionType = strtolower(trim((string)($_POST['production_type'] ?? '')));
            $startDate = trim((string)($_POST['start_date'] ?? ''));
            $expectedEndDate = trim((string)($_POST['expected_end_date'] ?? ''));
            $openingHeadcountRaw = trim((string)($_POST['opening_headcount'] ?? '0'));
            $startAgeDaysRaw = trim((string)($_POST['start_age_days'] ?? '1'));
            $notes = trim((string)($_POST['notes'] ?? ''));

            $createCycleForm = [
                'cycle_code' => $cycleCode,
                'farm_type' => $farmType !== '' ? $farmType : 'poultry',
                'production_type' => $productionType !== '' ? $productionType : 'layer',
                'start_date' => $startDate,
                'expected_end_date' => $expectedEndDate,
                'opening_headcount' => $openingHeadcountRaw,
                'start_age_days' => $startAgeDaysRaw,
                'notes' => $notes,
            ];

            $openingHeadcount = filter_var($openingHeadcountRaw, FILTER_VALIDATE_INT);
            $startAgeDays = filter_var($startAgeDaysRaw, FILTER_VALIDATE_INT);
            $allowedProductionTypes = [
                'poultry' => ['layer', 'broiler'],
                'ruminant' => ['cattle', 'goat', 'sheep', 'other'],
            ];

            if ($cycleCode === '' || $productionType === '' || $startDate === '') {
                $flash = ['type' => 'danger', 'message' => 'Cycle code, production type, and start date are required.', 'title' => 'Required cycle information is missing.'];
            } elseif (mb_strlen($cycleCode) > 100) {
                $flash = ['type' => 'danger', 'message' => 'Cycle code must be 100 characters or fewer.', 'title' => 'Cycle code is too long.'];
            } elseif (!in_array($farmType, allowedFarmTypes(false), true)) {
                $flash = ['type' => 'danger', 'message' => 'Farm type must be poultry or ruminant.', 'title' => 'Invalid farm type.'];
            } elseif (!isset($allowedProductionTypes[$farmType]) || !in_array($productionType, $allowedProductionTypes[$farmType], true)) {
                $flash = ['type' => 'danger', 'message' => 'Select a valid production type for the selected farm type.', 'title' => 'Invalid production type.'];
            } elseif ($openingHeadcount === false || $openingHeadcount < 0) {
                $flash = ['type' => 'danger', 'message' => 'Opening headcount must be 0 or greater.', 'title' => 'Invalid opening headcount.'];
            } elseif ($startAgeDays === false || $startAgeDays < 1) {
                $flash = ['type' => 'danger', 'message' => 'Start age must be at least 1 day.', 'title' => 'Invalid start age.'];
            } elseif ($expectedEndDate !== '' && $expectedEndDate < $startDate) {
                $flash = ['type' => 'danger', 'message' => 'Expected end date cannot be earlier than the cycle start date.', 'title' => 'Invalid cycle dates.'];
            } else {
                // Friendly pre-check: the database constraint remains the final safeguard.
                $duplicateStmt = $pdo->prepare(
                    'SELECT id FROM production_cycles WHERE farm_id = ? AND cycle_code = ? LIMIT 1'
                );
                $duplicateStmt->execute([$tenantFarmId, $cycleCode]);
                if ($duplicateStmt->fetchColumn()) {
                    $flash = [
                        'type' => 'danger',
                        'title' => 'Cycle code already exists.',
                        'message' => 'The cycle code "' . $cycleCode . '" is already being used in this farm. Please choose a different code.',
                        'tip' => 'Your other entries have been preserved. Change only the cycle code and submit again.',
                    ];
                } else {
                    try {
                        $pdo->beginTransaction();
                        $stmt = $pdo->prepare(
                            'INSERT INTO production_cycles
                            (farm_id, cycle_code, farm_type, production_type, status, start_date, expected_end_date, opening_headcount, notes, created_by)
                            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
                        );
                        $stmt->execute([
                            $tenantFarmId,
                            $cycleCode,
                            $farmType,
                            $productionType,
                            'active',
                            $startDate,
                            $expectedEndDate !== '' ? $expectedEndDate : null,
                            $openingHeadcount,
                            ($notes !== '' ? $notes : null),
                            $_SESSION['user_id'] ?? null,
                        ]);
                        $newCycleId = (int)$pdo->lastInsertId();

                        // Seed opening record on cycle start date so daily pages can continue immediately.
                        if ($farmType === 'poultry' && $productionType === 'layer') {
                            $seedStmt = $pdo->prepare(
                                'INSERT INTO layer_daily_records
                                (farm_id, cycle_id, record_date, opening_stock, mortality, feed_consumption_bags, water_consumption_liters, medications, egg_production, crates_count, laying_rate, birds_age, remarks, user_id)
                                VALUES (?, ?, ?, ?, 0, 0, 0, NULL, 0, 0, 0, ?, ?, ?)'
                            );
                            $seedStmt->execute([$tenantFarmId, $newCycleId, $startDate, $openingHeadcount, max(1, $startAgeDays), 'Auto-created from Production Cycle opening stock.', $_SESSION['user_id'] ?? null]);
                        } elseif ($farmType === 'poultry' && $productionType === 'broiler') {
                            $seedStmt = $pdo->prepare(
                                'INSERT INTO broiler_daily_records
                                (farm_id, cycle_id, record_date, opening_stock, mortality, feed_consumption_bags, water_consumption_liters, medications, birds_age, remarks, user_id)
                                VALUES (?, ?, ?, ?, 0, 0, 0, NULL, ?, ?, ?)'
                            );
                            $seedStmt->execute([$tenantFarmId, $newCycleId, $startDate, $openingHeadcount, max(1, $startAgeDays), 'Auto-created from Production Cycle opening stock.', $_SESSION['user_id'] ?? null]);
                        } elseif ($farmType === 'ruminant') {
                            $seedStmt = $pdo->prepare(
                                'INSERT INTO ruminant_daily_records
                                (farm_id, cycle_id, record_date, animal_type, opening_stock, mortality, feed_consumption_kg, water_consumption_liters, other_details, tag_no, medications, reproduction_details, remarks, user_id)
                                VALUES (?, ?, ?, ?, ?, 0, 0, 0, NULL, NULL, NULL, NULL, ?, ?)'
                            );
                            $seedStmt->execute([$tenantFarmId, $newCycleId, $startDate, $productionType, $openingHeadcount, 'Auto-created from Production Cycle opening stock.', $_SESSION['user_id'] ?? null]);
                        }
                        $pdo->commit();
                        $createCycleForm = [
                            'cycle_code' => '', 'farm_type' => $farmType, 'production_type' => $productionType,
                            'start_date' => '', 'expected_end_date' => '', 'opening_headcount' => '0', 'start_age_days' => '1', 'notes' => ''
                        ];
                        $flash = ['type' => 'success', 'message' => 'Production cycle created successfully.'];
                    } catch (PDOException $e) {
                        if ($pdo->inTransaction()) { $pdo->rollBack(); }
                        if ((int)$e->errorInfo[1] === 1062) {
                            $flash = [
                                'type' => 'danger',
                                'title' => 'Cycle code already exists.',
                                'message' => 'The cycle code "' . $cycleCode . '" is already being used in this farm. Please choose a different code.',
                                'tip' => 'Your other entries have been preserved. Change only the cycle code and submit again.',
                            ];
                        } else {
                            error_log('Production cycle creation failed: ' . $e->getMessage());
                            $flash = [
                                'type' => 'danger',
                                'title' => 'Cycle could not be created.',
                                'message' => 'We could not create this production cycle right now. No changes were saved.',
                                'tip' => 'Please review the entries and try again. If the problem continues, contact your platform administrator.',
                            ];
                        }
                    }
                }
            }
        }

        if ($action === 'post_batch' && $stockBatchTableExists) {
            $cycleId = (int)($_POST['cycle_id'] ?? 0);
            $itemDescription = trim((string)($_POST['item_description'] ?? ''));
            $quantity = (int)($_POST['quantity'] ?? 0);
            $unitCost = (float)($_POST['unit_cost'] ?? 0);
            $receivedDate = $_POST['received_date'] ?? '';
            $supplierName = trim((string)($_POST['supplier_name'] ?? ''));
            $batchCode = trim((string)($_POST['batch_code'] ?? ''));
            $notes = trim((string)($_POST['batch_notes'] ?? ''));

            if ($cycleId <= 0 || $itemDescription === '' || $quantity <= 0 || $receivedDate === '') {
                $flash = ['type' => 'danger', 'message' => 'Cycle, item description, quantity, and received date are required for stock batch.'];
            } else {
                $cycleOwnerStmt = $pdo->prepare("SELECT id FROM production_cycles WHERE id = ? AND farm_id = ? AND status = 'active'");
                $cycleOwnerStmt->execute([$cycleId, $tenantFarmId]);
                if (!$cycleOwnerStmt->fetchColumn()) {
                    $flash = ['type' => 'danger', 'message' => 'The selected active cycle does not belong to this farm.'];
                } else {
                    $stmt = $pdo->prepare(
                        'INSERT INTO stock_batches
                        (farm_id, cycle_id, batch_code, item_description, quantity, unit_cost, supplier_name, received_date, notes, created_by)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
                    );
                    $stmt->execute([
                        $tenantFarmId,
                        $cycleId,
                        ($batchCode !== '' ? $batchCode : null),
                        $itemDescription,
                        $quantity,
                        $unitCost,
                        ($supplierName !== '' ? $supplierName : null),
                        $receivedDate,
                        ($notes !== '' ? $notes : null),
                        $_SESSION['user_id'] ?? null,
                    ]);
                    $flash = ['type' => 'success', 'message' => 'Stock batch posted and linked to the selected active cycle.'];
                }
            }
        }

        if ($action === 'close_cycle') {
            $cycleId = (int)($_POST['cycle_id'] ?? 0);
            $closeDate = $_POST['close_date'] ?? '';
            $postedClosingHeadcount = trim((string)($_POST['closing_headcount'] ?? ''));
            $closingHeadcount = ($postedClosingHeadcount === '') ? 0 : (int)$postedClosingHeadcount;

            if ($cycleId <= 0 || $closeDate === '') {
                $flash = ['type' => 'danger', 'message' => 'Cycle and close date are required to close a production cycle.'];
            } else {
                $cycleStmt = $pdo->prepare(
                    'SELECT id, cycle_code, farm_type, production_type
                     FROM production_cycles
                     WHERE id = ? AND farm_id = ? AND status = ?
                     LIMIT 1'
                );
                $cycleStmt->execute([$cycleId, $tenantFarmId, 'active']);
                $cycleToClose = $cycleStmt->fetch(PDO::FETCH_ASSOC);

                if (!$cycleToClose) {
                    $flash = ['type' => 'danger', 'message' => 'Selected active cycle was not found.'];
                } else {
                    if ($closingHeadcount <= 0) {
                        $closingHeadcount = getCycleCurrentStock($pdo, $cycleToClose);
                    }

                    $stmt = $pdo->prepare(
                        'UPDATE production_cycles
                         SET status = ?, close_date = ?, closing_headcount = ?
                         WHERE id = ? AND farm_id = ?'
                    );
                    $stmt->execute(['closed', $closeDate, $closingHeadcount, $cycleId, $tenantFarmId]);
                    $flash = ['type' => 'success', 'message' => 'Cycle closed successfully.'];
                }
            }
        }
    }

    if ($cycleTableExists) {
        $statusStmt = $pdo->prepare("SELECT status, COUNT(*) AS total FROM production_cycles WHERE farm_id = ? GROUP BY status");
        $statusStmt->execute([$tenantFarmId]);
        foreach ($statusStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $status = $row['status'] . '_cycles';
            if (array_key_exists($status, $summary)) {
                $summary[$status] = (int)$row['total'];
            }
        }

        // Planned cycles are tied to having an expected end date while not yet closed.
        $plannedStmt = $pdo->prepare(
            "SELECT COUNT(*)
             FROM production_cycles
             WHERE farm_id = ?
               AND expected_end_date IS NOT NULL
               AND status <> 'closed'"
        );
        $plannedStmt->execute([$tenantFarmId]);
        $summary['planned_cycles'] = (int)$plannedStmt->fetchColumn();

        if ($stockBatchTableExists) {
            $stockBatchCountStmt = $pdo->prepare("SELECT COUNT(*) FROM stock_batches WHERE farm_id = ?");
            $stockBatchCountStmt->execute([$tenantFarmId]);
            $summary['stock_batches'] = (int)$stockBatchCountStmt->fetchColumn();
        }

        $activeStmt = $pdo->prepare("SELECT id, cycle_code, farm_type, production_type FROM production_cycles WHERE farm_id = ? AND status = 'active' ORDER BY start_date DESC");
        $activeStmt->execute([$tenantFarmId]);
        $activeCycles = $activeStmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($activeCycles as &$cycle) {
            $cycle['current_stock'] = getCycleCurrentStock($pdo, $cycle);
            $summary['total_current_stock'] += (int)$cycle['current_stock'];
        }
        unset($cycle);

        $closedCycleStmt = $pdo->prepare(
            "SELECT cycle_code, farm_type, production_type, opening_headcount, close_date, closing_headcount
             FROM production_cycles
             WHERE farm_id = ? AND status = 'closed'
             ORDER BY close_date DESC, created_at DESC
             LIMIT 20"
        );
        $closedCycleStmt->execute([$tenantFarmId]);
        $closedCycleDetails = $closedCycleStmt->fetchAll(PDO::FETCH_ASSOC);

        $recentStmt = $pdo->prepare(
            "SELECT cycle_code, farm_type, production_type, status, start_date, opening_headcount, expected_end_date, close_date
             FROM production_cycles
             WHERE farm_id = ?
             ORDER BY created_at DESC
             LIMIT 12"
        );
        $recentStmt->execute([$tenantFarmId]);
        $recentCycles = $recentStmt->fetchAll(PDO::FETCH_ASSOC);

        if ($stockBatchTableExists) {
            $batchStmt = $pdo->prepare(
                "SELECT sb.batch_code, sb.item_description, sb.quantity, sb.unit_cost, sb.received_date, sb.supplier_name,
                        pc.cycle_code, pc.production_type
                 FROM stock_batches sb
                 INNER JOIN production_cycles pc ON pc.id = sb.cycle_id AND pc.farm_id = sb.farm_id
                 WHERE sb.farm_id = ?
                 ORDER BY sb.received_date DESC, sb.id DESC
                 LIMIT 20"
            );
            $batchStmt->execute([$tenantFarmId]);
            $recentStockBatches = $batchStmt->fetchAll(PDO::FETCH_ASSOC);
        }
    }
} catch (Throwable $exception) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('Production cycle page error: ' . $exception->getMessage());
    $errorMessage = 'We could not load the production cycle data right now. Please try again.';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <?php include(__DIR__ . '/../navbar_head.php'); ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Production Cycles - Farm Management System</title>
</head>
<body>
<?php include(__DIR__ . '/../navbar.php'); ?>

<div class="container-fluid mt-4">
    <div class="row mb-3">
        <div class="col-12">
            <div class="card">
                <div class="card-body">
                    <h4 class="mb-2"><i class="bi bi-arrow-repeat"></i> Production Cycles</h4>
                    <p class="text-muted mb-1">This page is where the new cycle model appears in the platform.</p>
                    <p class="mb-0">Use this for Create Cycle, Close Cycle, and cycle-level monitoring with current stock visibility.</p>
                </div>
            </div>
        </div>
    </div>

    <?php if ($flash !== null): ?>
        <?php renderNotification(
            $flash['type'] === 'danger' ? 'error' : $flash['type'],
            $flash['message'],
            $flash['title'] ?? null,
            $flash['tip'] ?? null
        ); ?>
    <?php endif; ?>

    <?php if (!$cycleTableExists || !$stockBatchTableExists): ?>
        <div class="alert alert-warning" role="alert">
            <strong>Cycle tables are not available yet.</strong>
            Run <code>php scripts/run_migrations.php</code> so the <code>production_cycles</code> and <code>stock_batches</code> tables are created.
            <?php if ($migration002Recorded): ?>
                <hr class="my-2">
                <div><strong>Detected mismatch:</strong> migration <code>002_production_cycles.sql</code> is recorded, but required tables are missing. Re-run migrations again using the same database credentials as the web app.</div>
            <?php endif; ?>
        </div>
    <?php elseif ($errorMessage !== null): ?>
        <?php renderNotification('error', $errorMessage, 'Could not load production cycle data.', 'Check the migration/database status and try again.'); ?>
    <?php else: ?>
        <div class="row g-3 mb-3">
            <div class="col-md-3"><div class="card"><div class="card-body"><div class="text-muted">Active Cycles</div><h4><?php echo $summary['active_cycles']; ?></h4></div></div></div>
            <div class="col-md-3"><div class="card border-success"><div class="card-body"><div class="text-muted">Current Stock (Active Cycles)</div><h4 class="text-success"><?php echo number_format((int)$summary['total_current_stock']); ?></h4></div></div></div>
            <div class="col-md-3"><div class="card"><div class="card-body"><div class="text-muted">Planned Cycles</div><h4><?php echo $summary['planned_cycles']; ?></h4></div></div></div>
            <div class="col-md-3"><div class="card"><div class="card-body"><div class="text-muted">Closed Cycles</div><h4><?php echo $summary['closed_cycles']; ?></h4></div></div></div>
        </div>

        <div class="row g-3 mb-3">
            <div class="col-lg-6">
                <div class="card h-100">
                    <div class="card-header"><strong>Create Cycle</strong></div>
                    <div class="card-body">
                        <form method="post">
                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrf_token(), ENT_QUOTES); ?>">
                            <input type="hidden" name="action" value="create_cycle">
                            <div class="mb-2"><label class="form-label">Cycle Code</label><input class="form-control" name="cycle_code" maxlength="100" value="<?php echo htmlspecialchars($createCycleForm['cycle_code'], ENT_QUOTES); ?>" required></div>
                            <div class="mb-2"><label class="form-label">Farm Type</label><select class="form-select" name="farm_type" required><?php foreach (allowedFarmTypes(false) as $type): ?><option value="<?php echo $type; ?>" <?php echo $createCycleForm['farm_type'] === $type ? 'selected' : ''; ?>><?php echo ucfirst($type); ?></option><?php endforeach; ?></select></div>
                            <div class="mb-2">
                                <label class="form-label">Production Type</label>
                                <select class="form-select" name="production_type" id="productionType" data-selected="<?php echo htmlspecialchars($createCycleForm['production_type'], ENT_QUOTES); ?>" required></select>
                            </div>
                            <div class="mb-2"><label class="form-label">Start Date</label><input class="form-control" type="date" name="start_date" value="<?php echo htmlspecialchars($createCycleForm['start_date'], ENT_QUOTES); ?>" required></div>
                            <div class="mb-2"><label class="form-label">Expected End Date</label><input class="form-control" type="date" name="expected_end_date" value="<?php echo htmlspecialchars($createCycleForm['expected_end_date'], ENT_QUOTES); ?>"></div>
                            <div class="mb-2"><label class="form-label">Opening Headcount</label><input class="form-control" type="number" min="0" name="opening_headcount" value="<?php echo htmlspecialchars($createCycleForm['opening_headcount'], ENT_QUOTES); ?>"></div>
                            <div class="mb-2"><label class="form-label">Start Age (days)</label><input class="form-control" type="number" min="1" name="start_age_days" value="<?php echo htmlspecialchars($createCycleForm['start_age_days'], ENT_QUOTES); ?>"></div>
                            <div class="mb-2"><label class="form-label">Notes</label><textarea class="form-control" name="notes" rows="2"><?php echo htmlspecialchars($createCycleForm['notes'], ENT_QUOTES); ?></textarea></div>
                            <button class="btn btn-success" type="submit">Create Cycle</button>
                        </form>
                    </div>
                </div>
            </div>

            <div class="col-lg-6">
                <div class="card h-100">
                    <div class="card-header"><strong>Close Cycle</strong></div>
                    <div class="card-body">
                        <form method="post">
                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrf_token(), ENT_QUOTES); ?>">
                            <input type="hidden" name="action" value="close_cycle">
                            <div class="mb-2"><label class="form-label">Active Cycle</label>
                                <select class="form-select" name="cycle_id" required>
                                    <option value="">Select active cycle</option>
                                    <?php foreach ($activeCycles as $cycle): ?>
                                        <option value="<?php echo (int)$cycle['id']; ?>"><?php echo htmlspecialchars($cycle['cycle_code'] . ' - ' . $cycle['production_type']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="mb-2"><label class="form-label">Close Date</label><input class="form-control" type="date" name="close_date" required></div>
                            <div class="mb-2"><label class="form-label">Closing Headcount</label><input class="form-control" type="number" min="0" name="closing_headcount" placeholder="Auto-fill from latest closing if left blank or 0"><div class="form-text">Leave blank to derive it from the selected cycle code's latest closing stock.</div></div>
                            <button class="btn btn-warning" type="submit">Close Cycle</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">Recent Cycles</h5>
                <span class="badge bg-success">New</span>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-striped mb-0">
                        <thead>
                        <tr>
                            <th>Cycle Code</th>
                            <th>Farm Type</th>
                            <th>Production Type</th>
                            <th>Status</th>
                            <th>Start Date</th>
                            <th class="text-end">Opening Headcount</th>
                            <th>Expected End</th>
                            <th>Closed Date</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php if (empty($recentCycles)): ?>
                            <tr><td colspan="8" class="text-center text-muted py-4">No cycles yet. Create your first cycle above.</td></tr>
                        <?php else: ?>
                            <?php foreach ($recentCycles as $cycle): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($cycle['cycle_code']); ?></td>
                                    <td class="text-capitalize"><?php echo htmlspecialchars($cycle['farm_type']); ?></td>
                                    <td><?php echo htmlspecialchars($cycle['production_type']); ?></td>
                                    <td><span class="badge bg-secondary text-uppercase"><?php echo htmlspecialchars($cycle['status']); ?></span></td>
                                    <td><?php echo htmlspecialchars($cycle['start_date']); ?></td>
                                    <td class="text-end"><?php echo number_format(max(0, (int)($cycle['opening_headcount'] ?? 0))); ?></td>
                                    <td><?php echo htmlspecialchars($cycle['expected_end_date'] ?? '-'); ?></td>
                                    <td><?php echo htmlspecialchars($cycle['close_date'] ?? '-'); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>


        <div class="card mt-3">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">Close Cycle Details</h5>
                <span class="badge bg-success">Live</span>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-striped mb-0">
                        <thead>
                        <tr>
                            <th>Cycle</th>
                            <th>Farm Type</th>
                            <th>Production Type</th>
                            <th class="text-end">Opening Headcount</th>
                            <th>Close Date</th>
                            <th class="text-end">Closing Headcount</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php if (empty($closedCycleDetails)): ?>
                            <tr><td colspan="6" class="text-center text-muted py-4">No closed cycles yet. Close a cycle to see details here.</td></tr>
                        <?php else: ?>
                            <?php foreach ($closedCycleDetails as $cycle): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($cycle['cycle_code']); ?></td>
                                    <td class="text-capitalize"><?php echo htmlspecialchars($cycle['farm_type']); ?></td>
                                    <td class="text-capitalize"><?php echo htmlspecialchars($cycle['production_type']); ?></td>
                                    <td class="text-end"><?php echo number_format(max(0, (int)($cycle['opening_headcount'] ?? 0))); ?></td>
                                    <td><?php echo htmlspecialchars($cycle['close_date'] ?? '-'); ?></td>
                                    <td class="text-end fw-bold"><?php echo number_format(max(0, (int)($cycle['closing_headcount'] ?? 0))); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

    <?php endif; ?>
</div>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const farmType = document.querySelector('select[name="farm_type"]');
    const productionType = document.getElementById('productionType');
    if (!farmType || !productionType) return;

    const options = {
        poultry: ['layer', 'broiler'],
        ruminant: ['cattle', 'goat', 'sheep', 'other']
    };

    const render = () => {
        const selectedFarm = farmType.value || 'poultry';
        const requestedProduction = productionType.dataset.selected || productionType.value;
        productionType.innerHTML = '';
        (options[selectedFarm] || []).forEach((value) => {
            const option = document.createElement('option');
            option.value = value;
            option.textContent = value.charAt(0).toUpperCase() + value.slice(1);
            if (value === requestedProduction) option.selected = true;
            productionType.appendChild(option);
        });
    };

    farmType.addEventListener('change', function () {
        productionType.dataset.selected = '';
        render();
    });
    render();
});
</script>
</body>
</html>
