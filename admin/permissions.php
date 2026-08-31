<?php require_once(__DIR__ . '/../init.php'); ?>
<?php
// permissions.php - Owner UI to manage module permissions
// init.php loads config.php and starts session

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/functions.php';

ensurePermissionsTable($pdo);

// ensure owner
if (!isPlatformOwner() && !hasRole('farm_admin')) {
    header('Location: ' . BASE_URL . '/no_access.php');
    exit();
}

// Permissions are tenant-scoped. Farm Admin edits only the current farm.
// Platform Owner may select a tenant explicitly without changing workspace/session.
$permissionFarmId = requireCurrentFarmId();
$permissionFarmName = farmBrandName();
$permissionFarms = [];
if (isPlatformOwner()) {
    $permissionFarms = $pdo->query("SELECT id, name FROM farms WHERE slug <> 'owner' ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
    $requestedFarmId = filter_var($_GET['farm_id'] ?? 0, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) ?: 0;
    if ($requestedFarmId > 0) {
        $farmStmt = $pdo->prepare("SELECT id, name FROM farms WHERE id = ? AND slug <> 'owner' LIMIT 1");
        $farmStmt->execute([$requestedFarmId]);
        if ($targetFarm = $farmStmt->fetch(PDO::FETCH_ASSOC)) {
            $permissionFarmId = (int)$targetFarm['id'];
            $permissionFarmName = $targetFarm['name'];
        }
    } elseif ($permissionFarms) {
        $permissionFarmId = (int)$permissionFarms[0]['id'];
        $permissionFarmName = $permissionFarms[0]['name'];
    }
}

$moduleGroups = [
    'Poultry' => [
        'poultry_overview' => 'Poultry dashboard and overall summary',
        'poultry_daily_layer' => 'Daily records for layer birds',
        'poultry_daily_broiler' => 'Daily records for broiler birds',
        'poultry_feeds' => 'Feed tracking and feed usage',
        'poultry_health' => 'Health monitoring and treatment records',
        'poultry_expenses' => 'Poultry-specific expenses'
    ],
    'Ruminant' => [
        'ruminant_overview' => 'Ruminant dashboard and overall summary',
        'ruminant_daily' => 'Daily records for ruminants',
        'ruminant_feeds' => 'Ruminant feed records and usage',
        'ruminant_expenses' => 'Ruminant-specific expenses'
    ],
    'Inventory & Operations' => [
        'inventory' => 'Inventory listing and stock movement',
        'inventory_add_new_item' => 'Add new item to inventory',
        'update_stock' => 'Update stock quantities and adjustments',
        'sales' => 'Sales entry and sales records',
        'expenses' => 'View and manage general expense records',
        'production_cycles' => 'View production cycles (creation/editing remains Farm Admin only)'
    ],
    'Administration' => [
        'reports' => 'View and generate reports',
        'users' => 'User account management'
    ]
];

$modules = [];
foreach ($moduleGroups as $groupModules) {
    $modules = array_merge($modules, array_keys($groupModules));
}

$enabledRoleStmt=$pdo->prepare('SELECT module_code FROM farm_modules WHERE farm_id=? AND is_enabled=1'); $enabledRoleStmt->execute([$permissionFarmId]); $enabledModules=$enabledRoleStmt->fetchAll(PDO::FETCH_COLUMN) ?: [];
$roles=[]; if(in_array('poultry',$enabledModules,true)) $roles[]='poultry_manager'; if(in_array('ruminant',$enabledModules,true)) $roles[]='ruminant_manager'; if(in_array('sales',$enabledModules,true)) $roles[]='sales_rep';
function permissionModuleApplicable(string $role,string $module): bool {
    // Expense-entry permissions may be delegated to Sales Representatives without
    // granting Daily Record / Feed / Health access to the production modules.
    if ($module === 'poultry_expenses') return in_array($role, ['poultry_manager','sales_rep'], true);
    if ($module === 'ruminant_expenses') return in_array($role, ['ruminant_manager','sales_rep'], true);
    if (str_starts_with($module,'poultry_')) return $role==='poultry_manager';
    if (str_starts_with($module,'ruminant_')) return $role==='ruminant_manager';
    if ($module==='sales') return $role==='sales_rep';
    return true;
}
$roleLabels = [
    'poultry_manager' => 'Poultry Manager',
    'ruminant_manager' => 'Ruminant Manager',
    'sales_rep' => 'Sales Representative'
];

$rolePlaceholders = rtrim(str_repeat('?,', count($roles)), ',');

// Load global defaults first (farm_id=0), then tenant overrides.
$permissions = [];
if ($roles) {
    $stmt = $pdo->prepare("SELECT farm_id,role,module,allowed FROM permissions WHERE farm_id IN (0, ?) AND role IN ($rolePlaceholders) ORDER BY farm_id ASC");
    $stmt->execute(array_merge([$permissionFarmId], $roles));
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $permissions[$row['role']][$row['module']] = $row['allowed'];
    }
}

$errorDetail = $_SESSION['permission_error_detail'] ?? null;
unset($_SESSION['permission_error_detail']);
?>
<!doctype html>
<html>
<head>
  <?php include __DIR__ . '/../navbar_head.php'; ?>
  <title>Permissions - Renee Farms</title>
  <style>
    .permissions-shell {
      max-width: 1200px;
      margin: 0 auto;
    }

    .permissions-card {
      border: 1px solid #e9ecef;
      border-radius: 14px;
      box-shadow: 0 8px 24px rgba(15, 23, 42, 0.06);
      overflow: hidden;
    }

    .permissions-card .card-header {
      background: linear-gradient(120deg, #198754 0%, #157347 100%);
      color: #fff;
      border: 0;
      padding: 1rem 1.25rem;
    }

    .permissions-intro {
      color: #5c6770;
      margin-bottom: 0;
      font-size: 0.95rem;
    }

    .permission-group-title {
      background: #f8faf9;
      color: #1f5136;
      font-weight: 600;
      font-size: 0.9rem;
      letter-spacing: .02em;
      text-transform: uppercase;
    }

    .permission-module {
      min-width: 260px;
    }

    .permission-module strong {
      display: block;
      color: #1f2937;
      margin-bottom: 0.15rem;
      font-size: 0.95rem;
    }

    .permission-module small {
      color: #6b7280;
      line-height: 1.35;
      display: inline-block;
    }

    .permission-check {
      transform: scale(1.2);
      cursor: pointer;
    }

    .sticky-actions {
      position: sticky;
      bottom: 0;
      background: #fff;
      border-top: 1px solid #e9ecef;
      padding: 1rem 1.25rem;
      display: flex;
      justify-content: space-between;
      align-items: center;
      gap: .75rem;
    }

    .permission-tip { color: var(--app-text-muted, #5c6770); font-weight: 600; }
    html[data-theme="dark"] .permissions-shell .permissions-intro,
    html[data-bs-theme="dark"] .permissions-shell .permissions-intro,
    html[data-theme="dark"] .permissions-shell .permission-module small,
    html[data-bs-theme="dark"] .permissions-shell .permission-module small,
    html[data-theme="dark"] .permission-tip,
    html[data-theme="dark"] .permissions-shell .permission-tip,
    html[data-bs-theme="dark"] .permissions-shell .permission-tip { color: #dbe5f3 !important; opacity: 1 !important; }
    html[data-theme="dark"] .permissions-shell .permission-group-title,
    html[data-bs-theme="dark"] .permissions-shell .permission-group-title { background: #172235 !important; color: #e8f2ff !important; }
    html[data-theme="dark"] .permissions-shell .permission-module strong,
    html[data-bs-theme="dark"] .permissions-shell .permission-module strong { color: #f8fafc !important; }
    html[data-theme="dark"] .permissions-shell .sticky-actions,
    html[data-bs-theme="dark"] .permissions-shell .sticky-actions { background: #111c2f !important; border-color: #334155 !important; }

    @media (max-width: 768px) {
      .permission-module {
        min-width: 220px;
      }

      .sticky-actions {
        flex-direction: column;
        align-items: stretch;
      }

      .sticky-actions button {
        width: 100%;
      }
    }
  </style>
</head>
<body>
  <?php include __DIR__ . '/../navbar.php'; ?>
  <div class="container py-4 permissions-shell">
    <div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-3">
      <div>
        <h3 class="mb-1">Module Permissions</h3>
        <p class="permissions-intro">Control which role can access each module across Poultry, Ruminant, Inventory, and Administration.</p>
      </div>
      <span class="badge text-bg-light border"><?php echo isPlatformOwner() ? 'Platform Owner • ' . htmlspecialchars($permissionFarmName) : 'Farm Admin Scoped Access'; ?></span>
    </div>

    <?php if (isset($_GET['updated'])): ?>
      <?php renderNotification('success', 'Permissions updated successfully!', 'Permissions updated successfully!'); ?>
    <?php elseif (isset($_GET['error'])): ?>
      <?php renderNotification('error', 'Unable to save permissions. Please try again or check the error log.', 'Unable to save permissions.'); ?>
      <?php if ($errorDetail): ?>
        <?php renderNotification('warning', $errorDetail, 'Additional details'); ?>
      <?php endif; ?>
    <?php endif; ?>

    <?php if (isPlatformOwner()): ?>
    <form method="get" class="card card-body mb-3 border-0 shadow-sm">
      <label class="form-label fw-semibold" for="permissionFarmSelect">Manage permissions for farm</label>
      <div class="d-flex gap-2 flex-wrap">
        <select class="form-select" style="max-width:420px" id="permissionFarmSelect" name="farm_id" onchange="this.form.submit()">
          <?php foreach ($permissionFarms as $farmOption): ?>
            <option value="<?= (int)$farmOption['id'] ?>" <?= (int)$farmOption['id'] === $permissionFarmId ? 'selected' : '' ?>><?= htmlspecialchars($farmOption['name']) ?></option>
          <?php endforeach; ?>
        </select>
        <span class="badge text-bg-light border align-self-center">Tenant-scoped settings</span>
      </div>
    </form>
    <?php endif; ?>

    <form method="post" action="permissions_save.php">
      <input type="hidden" name="farm_id" value="<?= (int)$permissionFarmId ?>">
      <div class="card permissions-card">
        <div class="card-header">
          <h5 class="mb-1">Role Access Matrix</h5>
          <small>Check the boxes to grant access. Unchecked means blocked. Greyed-out combinations do not apply to that role.</small>
        </div>

        <div class="table-responsive">
          <table class="table table-hover table-bordered align-middle mb-0">
            <thead class="table-light">
              <tr>
                <th class="permission-module">Module</th>
                <?php foreach ($roles as $role): ?>
                  <th class="text-center"><?= htmlspecialchars($roleLabels[$role] ?? $role) ?></th>
                <?php endforeach; ?>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($moduleGroups as $groupName => $groupModules): ?>
                <tr class="permission-group-title">
                  <td colspan="<?= count($roles) + 1 ?>"><?= htmlspecialchars($groupName) ?></td>
                </tr>
                <?php foreach ($groupModules as $module => $description): ?>
                  <tr>
                    <td class="permission-module">
                      <strong><?= htmlspecialchars($module) ?></strong>
                      <small><?= htmlspecialchars($description) ?></small>
                    </td>
                    <?php foreach ($roles as $role):
                      $applicable = permissionModuleApplicable($role,$module);
                      $checked = ($applicable && !empty($permissions[$role][$module]) && (int)$permissions[$role][$module] === 1) ? 'checked' : '';
                      $disabled = $applicable ? '' : 'disabled';
                    ?>
                      <td class="text-center">
                        <input
                          class="form-check-input permission-check"
                          type="checkbox"
                          name="perm[<?= htmlspecialchars($role) ?>][<?= htmlspecialchars($module) ?>]"
                          value="1"
                          <?= $checked ?> <?= $disabled ?>
                          aria-label="<?= htmlspecialchars($role) ?> access to <?= htmlspecialchars($module) ?>"
                        />
                      </td>
                    <?php endforeach; ?>
                  </tr>
                <?php endforeach; ?>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>

        <div class="sticky-actions">
          <small class="permission-tip">Tip: Review Administration modules before saving to avoid locking out managers from essential tools.</small>
          <button class="btn btn-success" type="submit">
            <i class="bi bi-check2-circle me-1"></i> Save Permissions
          </button>
        </div>
      </div>
    </form>
  </div>
</body>
</html>
