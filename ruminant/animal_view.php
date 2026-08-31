<?php
require_once(dirname(__DIR__) . '/init.php');
require_once(__DIR__ . '/../config.php');
require_once(__DIR__ . '/../includes/functions.php');
require_once(__DIR__ . '/../includes/audit_helpers.php');
requireLogin();
ensureAllowed('ruminant_daily');
$farmId = requireCurrentFarmId();
$animalId = (int)($_GET['id'] ?? 0);
if ($animalId < 1) { http_response_code(400); exit('Invalid animal.'); }

$canManage = isPlatformOwner() || hasRole('farm_admin') || hasRole('ruminant_manager');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!$canManage) { http_response_code(403); exit('Access denied.'); }
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) { http_response_code(419); exit('Invalid request token.'); }

    $action = $_POST['action'] ?? '';
    $check = $pdo->prepare('SELECT id, tag_no, species, status FROM ruminant_animals WHERE id=? AND farm_id=? LIMIT 1');
    $check->execute([$animalId, $farmId]);
    $target = $check->fetch(PDO::FETCH_ASSOC);
    if (!$target) { http_response_code(404); exit('Animal not found.'); }

    if ($action === 'add_weight') {
        $date = $_POST['weight_date'] ?? '';
        $weight = (float)($_POST['weight_kg'] ?? 0);
        $notes = trim($_POST['weight_notes'] ?? '');
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) || $weight <= 0 || $weight > 10000) {
            http_response_code(422); exit('Enter a valid weighing date and weight.');
        }
        $stmt = $pdo->prepare('INSERT INTO ruminant_animal_weights (farm_id, animal_id, weight_date, weight_kg, notes, recorded_by) VALUES (?,?,?,?,?,?)');
        $stmt->execute([$farmId, $animalId, $date, $weight, $notes ?: null, $_SESSION['user_id'] ?? null]);
        audit_log_event('create', 'ruminant_animal_weight', $pdo->lastInsertId(), ['animal_id'=>$animalId,'tag_no'=>$target['tag_no'],'weight_kg'=>$weight,'weight_date'=>$date]);
        header('Location: animal_view.php?id='.$animalId.'#weight-history'); exit();
    }

    if ($action === 'add_health') {
        $date = $_POST['event_date'] ?? '';
        $type = $_POST['event_type'] ?? 'other';
        $description = trim($_POST['description'] ?? '');
        $medicine = trim($_POST['medicine'] ?? '');
        $dosage = trim($_POST['dosage'] ?? '');
        $withdrawal = $_POST['withdrawal_until'] ?? '';
        $types = ['vaccination','treatment','diagnosis','vet_visit','deworming','other'];
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) || !in_array($type, $types, true)) {
            http_response_code(422); exit('Enter a valid health-event date and type.');
        }
        if ($withdrawal !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $withdrawal)) {
            http_response_code(422); exit('Enter a valid withdrawal date.');
        }
        $stmt = $pdo->prepare('INSERT INTO ruminant_health_events (farm_id, animal_id, event_date, event_type, description, medicine, dosage, withdrawal_until, recorded_by) VALUES (?,?,?,?,?,?,?,?,?)');
        $stmt->execute([$farmId, $animalId, $date, $type, $description ?: null, $medicine ?: null, $dosage ?: null, $withdrawal ?: null, $_SESSION['user_id'] ?? null]);
        audit_log_event('create', 'ruminant_health_event', $pdo->lastInsertId(), ['animal_id'=>$animalId,'tag_no'=>$target['tag_no'],'event_type'=>$type,'event_date'=>$date]);
        header('Location: animal_view.php?id='.$animalId.'#health-history'); exit();
    }
}

$stmt = $pdo->prepare('SELECT a.*, u.full_name AS created_by_name FROM ruminant_animals a LEFT JOIN users u ON u.id=a.created_by WHERE a.id=? AND a.farm_id=? LIMIT 1');
$stmt->execute([$animalId, $farmId]);
$animal = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$animal) { http_response_code(404); exit('Animal not found.'); }

$weightsStmt = $pdo->prepare('SELECT w.*, u.full_name AS recorded_by_name FROM ruminant_animal_weights w LEFT JOIN users u ON u.id=w.recorded_by WHERE w.animal_id=? AND w.farm_id=? ORDER BY w.weight_date DESC, w.id DESC');
$weightsStmt->execute([$animalId, $farmId]);
$weights = $weightsStmt->fetchAll(PDO::FETCH_ASSOC);

$healthStmt = $pdo->prepare('SELECT h.*, u.full_name AS recorded_by_name FROM ruminant_health_events h LEFT JOIN users u ON u.id=h.recorded_by WHERE h.animal_id=? AND h.farm_id=? ORDER BY h.event_date DESC, h.id DESC');
$healthStmt->execute([$animalId, $farmId]);
$healthEvents = $healthStmt->fetchAll(PDO::FETCH_ASSOC);

$latestWeight = $weights[0]['weight_kg'] ?? null;
$previousWeight = $weights[1]['weight_kg'] ?? null;
$weightChange = ($latestWeight !== null && $previousWeight !== null) ? (float)$latestWeight - (float)$previousWeight : null;
$today = date('Y-m-d');
?>
<!doctype html>
<html lang="en">
<head>
<?php include(__DIR__ . '/../navbar_head.php'); ?>
<title><?php echo htmlspecialchars($animal['tag_no']); ?> — Animal Profile</title>
</head>
<body class="ruminant-page">
<?php include(__DIR__ . '/../navbar.php'); ?>
<main class="container-fluid mt-4 poultry-shell">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <div>
      <div class="text-muted small">Ruminant Animal Registry</div>
      <h3 class="mb-0"><i class="bi bi-person-badge"></i> <?php echo htmlspecialchars($animal['tag_no']); ?></h3>
    </div>
    <div class="d-flex gap-2">
      <a class="btn btn-outline-secondary" href="animal_registry.php"><i class="bi bi-arrow-left"></i> Back</a>
      <?php if ($canManage): ?><a class="btn btn-primary" href="animal_registry.php?edit=<?php echo (int)$animal['id']; ?>"><i class="bi bi-pencil"></i> Edit</a><?php endif; ?>
    </div>
  </div>

  <div class="row g-3">
    <div class="col-lg-5">
      <div class="card poultry-panel h-100">
        <div class="card-header poultry-hero d-flex justify-content-between align-items-center"><strong>Animal Profile</strong><span class="badge text-bg-<?php echo $animal['status']==='active'?'success':'secondary'; ?>"><?php echo ucfirst($animal['status']); ?></span></div>
        <div class="card-body">
          <div class="row g-3">
            <div class="col-6"><small class="text-muted">Tag No.</small><div class="fw-semibold"><?php echo htmlspecialchars($animal['tag_no']); ?></div></div>
            <div class="col-6"><small class="text-muted">Species</small><div class="fw-semibold"><?php echo ucfirst($animal['species']); ?></div></div>
            <div class="col-6"><small class="text-muted">Breed</small><div><?php echo htmlspecialchars($animal['breed'] ?: '—'); ?></div></div>
            <div class="col-6"><small class="text-muted">Sex</small><div><?php echo ucfirst($animal['sex']); ?></div></div>
            <div class="col-6"><small class="text-muted">Birth Date</small><div><?php echo $animal['birth_date'] ? date('d/m/Y', strtotime($animal['birth_date'])) : '—'; ?></div></div>
            <div class="col-6"><small class="text-muted">Source</small><div><?php echo htmlspecialchars($animal['source'] ?: '—'); ?></div></div>
            <div class="col-6"><small class="text-muted">Purchase Date</small><div><?php echo $animal['purchase_date'] ? date('d/m/Y', strtotime($animal['purchase_date'])) : '—'; ?></div></div>
            <div class="col-6"><small class="text-muted">Purchase Cost</small><div>₦<?php echo number_format((float)$animal['purchase_cost'], 2); ?></div></div>
            <div class="col-12"><small class="text-muted">Notes</small><div><?php echo nl2br(htmlspecialchars($animal['notes'] ?: 'No notes.')); ?></div></div>
          </div>
        </div>
      </div>
    </div>

    <div class="col-lg-7">
      <div class="row g-3 mb-3">
        <div class="col-md-4"><div class="card h-100"><div class="card-body"><small class="text-muted">Current Weight</small><h4 class="mb-0"><?php echo $latestWeight !== null ? number_format((float)$latestWeight, 2).' kg' : '—'; ?></h4><?php if ($canManage): ?><button type="button" class="btn btn-sm btn-outline-primary mt-2" data-bs-toggle="modal" data-bs-target="#weightModal">+ Record Weight</button><?php endif; ?></div></div></div>
        <div class="col-md-4"><div class="card h-100"><div class="card-body"><small class="text-muted">Weight Records</small><h4 class="mb-0"><?php echo count($weights); ?></h4></div></div></div>
        <div class="col-md-4"><div class="card h-100"><div class="card-body"><small class="text-muted">Health Events</small><h4 class="mb-0"><?php echo count($healthEvents); ?></h4><?php if ($canManage): ?><button type="button" class="btn btn-sm btn-outline-primary mt-2" data-bs-toggle="modal" data-bs-target="#healthModal">+ Add Health</button><?php endif; ?></div></div></div>
      </div>

      <div class="card poultry-panel mb-3" id="weight-history">
        <div class="card-header d-flex justify-content-between align-items-center"><strong><i class="bi bi-speedometer2"></i> Weight History</strong><?php if ($canManage): ?><button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#weightModal">+ Add Weight</button><?php endif; ?><?php if ($weightChange !== null): ?><span class="ms-auto me-2 <?php echo $weightChange >= 0 ? 'text-success' : 'text-danger'; ?>"><?php echo ($weightChange >= 0 ? '+' : '').number_format($weightChange,2); ?> kg since previous record</span><?php endif; ?></div>
        <div class="table-responsive"><table class="table table-sm mb-0"><thead><tr><th>Date</th><th>Weight</th><th>Change</th><th>Notes</th></tr></thead><tbody>
        <?php foreach ($weights as $i => $w): $olderWeight = $weights[$i + 1]['weight_kg'] ?? null; $change = $olderWeight !== null ? (float)$w['weight_kg'] - (float)$olderWeight : null; ?>
          <tr><td><?php echo date('d/m/Y', strtotime($w['weight_date'])); ?></td><td><?php echo number_format((float)$w['weight_kg'],2); ?> kg</td><td><?php echo $change === null ? '—' : (($change >= 0 ? '+' : '').number_format($change,2).' kg'); ?></td><td><?php echo htmlspecialchars($w['notes'] ?: '—'); ?></td></tr>
        <?php endforeach; if (!$weights): ?><tr><td colspan="4" class="text-center text-muted py-3">No weight records yet.</td></tr><?php endif; ?></tbody></table></div>
      </div>

      <div class="card poultry-panel" id="health-history">
        <div class="card-header d-flex justify-content-between align-items-center"><strong><i class="bi bi-heart-pulse"></i> Health & Treatment History</strong><?php if ($canManage): ?><button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#healthModal">+ Add Health Record</button><?php endif; ?></div>
        <div class="table-responsive"><table class="table table-sm mb-0"><thead><tr><th>Date</th><th>Type</th><th>Description</th><th>Medicine</th><th>Withdrawal Until</th></tr></thead><tbody>
        <?php foreach ($healthEvents as $h): ?><tr><td><?php echo date('d/m/Y', strtotime($h['event_date'])); ?></td><td><?php echo ucfirst(str_replace('_',' ',$h['event_type'])); ?></td><td><?php echo nl2br(htmlspecialchars($h['description'] ?: '—')); ?></td><td><?php echo htmlspecialchars($h['medicine'] ?: '—'); ?><?php echo $h['dosage'] ? ' ('.htmlspecialchars($h['dosage']).')' : ''; ?></td><td><?php echo $h['withdrawal_until'] ? date('d/m/Y', strtotime($h['withdrawal_until'])) : '—'; ?></td></tr><?php endforeach; if (!$healthEvents): ?><tr><td colspan="5" class="text-center text-muted py-3">No health records yet.</td></tr><?php endif; ?></tbody></table></div>
      </div>
    </div>
  </div>
</main>
<?php if ($canManage): ?>
<div class="modal fade" id="weightModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog"><div class="modal-content"><form method="post">
    <div class="modal-header"><h5 class="modal-title">Record Weight</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
    <div class="modal-body"><input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrf_token(), ENT_QUOTES); ?>"><input type="hidden" name="action" value="add_weight">
      <div class="mb-3"><label class="form-label">Date *</label><input type="date" class="form-control" name="weight_date" value="<?php echo $today; ?>" max="<?php echo $today; ?>" required></div>
      <div class="mb-3"><label class="form-label">Weight (kg) *</label><input type="number" class="form-control" name="weight_kg" min="0.01" max="10000" step="0.01" required></div>
      <div><label class="form-label">Notes</label><textarea class="form-control" name="weight_notes" rows="3" maxlength="255" placeholder="e.g. Routine weighing"></textarea></div>
    </div>
    <div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button><button class="btn btn-primary">Save Weight</button></div>
  </form></div></div>
</div>
<div class="modal fade" id="healthModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg"><div class="modal-content"><form method="post">
    <div class="modal-header"><h5 class="modal-title">Add Health / Treatment Record</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
    <div class="modal-body"><input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrf_token(), ENT_QUOTES); ?>"><input type="hidden" name="action" value="add_health">
      <div class="row g-3">
        <div class="col-md-6"><label class="form-label">Date *</label><input type="date" class="form-control" name="event_date" value="<?php echo $today; ?>" max="<?php echo $today; ?>" required></div>
        <div class="col-md-6"><label class="form-label">Type *</label><select class="form-select" name="event_type" required><?php foreach(['vaccination','treatment','diagnosis','vet_visit','deworming','other'] as $type): ?><option value="<?php echo $type; ?>"><?php echo ucfirst(str_replace('_',' ',$type)); ?></option><?php endforeach; ?></select></div>
        <div class="col-12"><label class="form-label">Description</label><textarea class="form-control" name="description" rows="3" placeholder="What happened or what was treated?"></textarea></div>
        <div class="col-md-6"><label class="form-label">Medicine</label><input class="form-control" name="medicine" maxlength="150" placeholder="Medicine/vaccine name"></div>
        <div class="col-md-6"><label class="form-label">Dosage</label><input class="form-control" name="dosage" maxlength="100" placeholder="e.g. 10 ml"></div>
        <div class="col-md-6"><label class="form-label">Withdrawal Until</label><input type="date" class="form-control" name="withdrawal_until" min="<?php echo $today; ?>"></div>
      </div>
    </div>
    <div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button><button class="btn btn-primary">Save Health Record</button></div>
  </form></div></div>
</div>
<?php endif; ?>
<script src="<?php echo BASE_URL; ?><?php echo versioned_asset('/assets/vendor/bootstrap5/js/bootstrap.bundle.min.js'); ?>"></script>
</body>
</html>
