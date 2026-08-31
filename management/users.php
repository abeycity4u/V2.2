<?php require_once(dirname(__DIR__) . '/init.php'); ?>
<?php
require_once(__DIR__ . '/../config.php');
require_once(__DIR__ . '/../includes/functions.php');
requireLogin();

// User management is tenant-scoped. Farm Admin always has it; specialist roles
// may receive it explicitly. Platform Owner can select a tenant without
// impersonating that tenant's admin.
if (!isPlatformOwner() && !hasRole('farm_admin') && !hasPermission(getUserType(), 'users')) {
    header('Location: ' . BASE_URL . '/no_access.php');
    exit();
}

$farmId = requireCurrentFarmId();
$managedFarmName = farmBrandName();
$platformFarmOptions = [];
if (isPlatformOwner()) {
    $platformFarmOptions = $pdo->query("SELECT id, name FROM farms WHERE slug <> 'owner' ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
    $requestedFarmId = filter_var($_POST['target_farm_id'] ?? $_GET['farm_id'] ?? 0, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) ?: 0;
    if ($requestedFarmId < 1 && $platformFarmOptions) $requestedFarmId = (int)$platformFarmOptions[0]['id'];
    $farmStmt = $pdo->prepare("SELECT id, name FROM farms WHERE id = ? AND slug <> 'owner' LIMIT 1");
    $farmStmt->execute([$requestedFarmId]);
    $managedFarm = $farmStmt->fetch(PDO::FETCH_ASSOC);
    if (!$managedFarm) { http_response_code(404); exit('Tenant farm not found.'); }
    $farmId = (int)$managedFarm['id'];
    $managedFarmName = $managedFarm['name'];
}

$moduleStmt = $pdo->prepare('SELECT module_code FROM farm_modules WHERE farm_id = ? AND is_enabled = 1');
$moduleStmt->execute([$farmId]);
$managedFarmModules = $moduleStmt->fetchAll(PDO::FETCH_COLUMN) ?: [];
$managedFarmHasModule = static fn(string $module): bool => in_array($module, $managedFarmModules, true);
$userManagerUrl = BASE_URL . '/management/users.php' . (isPlatformOwner() ? '?farm_id=' . $farmId : '');
$availableRoles = ['poultry_manager' => 'Poultry Manager', 'ruminant_manager' => 'Ruminant Manager', 'sales_rep' => 'Sales Representative', 'viewer' => 'Viewer'];
function tenantRoleLimits(PDO $pdo, int $farmId): array {
    $limits=['poultry_manager'=>1,'ruminant_manager'=>1,'sales_rep'=>1,'viewer'=>1];
    try { $stmt=$pdo->prepare('SELECT role_code,max_users FROM farm_role_limits WHERE farm_id=?'); $stmt->execute([$farmId]); foreach($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) if(array_key_exists($r['role_code'],$limits)) $limits[$r['role_code']] = (int)$r['max_users']; } catch(Throwable $e) {}
    return $limits;
}
function tenantRoleCount(PDO $pdo, int $farmId, string $role, int $excludeUserId=0): int {
    $sql="SELECT COUNT(DISTINCT u.id) FROM users u WHERE u.farm_id=? AND u.user_type <> 'farm_admin' AND (EXISTS (SELECT 1 FROM user_roles ur INNER JOIN roles r ON r.id=ur.role_id WHERE ur.user_id=u.id AND r.code=?) OR (u.user_type=? AND NOT EXISTS (SELECT 1 FROM user_roles ur2 WHERE ur2.user_id=u.id)))";
    $params=[$farmId,$role,$role]; if($excludeUserId>0){$sql.=' AND u.id<>?';$params[]=$excludeUserId;} $stmt=$pdo->prepare($sql);$stmt->execute($params);return (int)$stmt->fetchColumn();
}
function tenantRoleLabel(string $role): string {
    return ['poultry_manager'=>'Poultry Manager','ruminant_manager'=>'Ruminant Manager','sales_rep'=>'Sales Representative','viewer'=>'Viewer'][$role] ?? ucwords(str_replace('_',' ',$role));
}
function enforceTenantRoleLimits(PDO $pdo,int $farmId,array $roles,int $excludeUserId=0): void {
    $limits=tenantRoleLimits($pdo,$farmId);
    foreach($roles as $role){
        if(!array_key_exists($role,$limits)) continue;
        $max=(int)$limits[$role];
        $used=tenantRoleCount($pdo,$farmId,$role,$excludeUserId);
        $label=tenantRoleLabel($role);
        if($max<=0) throw new RuntimeException($label.' accounts are disabled for this farm by the Platform Owner.');
        if($used >= $max) throw new RuntimeException($label.' limit is '.$max.' and '.$used.' account(s) already have this role. Increase the limit or remove the role from an existing user before creating another.');
    }
}

$tenantRoleLimits = tenantRoleLimits($pdo,$farmId);

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) { http_response_code(419); exit('Invalid request token.'); }
    if (isset($_POST['add_user'])) {
        $roles = array_values(array_intersect($_POST['roles'] ?? [], array_keys($availableRoles)));
        $roles = array_values(array_filter($roles, static function ($role) use ($managedFarmHasModule) {
            if ($role === 'poultry_manager') return $managedFarmHasModule('poultry');
            if ($role === 'ruminant_manager') return $managedFarmHasModule('ruminant');
            if ($role === 'sales_rep') return $managedFarmHasModule('sales');
            return $role === 'viewer';
        }));
        if (!$roles) { $_SESSION['error'] = 'Select at least one role enabled by the platform owner.'; header('Location: ' . $userManagerUrl); exit(); }
        try { enforceTenantRoleLimits($pdo,$farmId,$roles); } catch(RuntimeException $e) { $_SESSION['error']=$e->getMessage(); header('Location: ' . $userManagerUrl); exit(); }
        $hashedPassword = password_hash($_POST['password'], PASSWORD_DEFAULT);
        
        $stmt = $pdo->prepare("INSERT INTO users (farm_id, username, password, user_type, full_name)
                               VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([
            $farmId,
            $_POST['username'],
            $hashedPassword,
            $roles[0],
            $_POST['full_name']
        ]);
        $userId = (int) $pdo->lastInsertId();
        $roleStmt = $pdo->prepare('INSERT INTO user_roles (user_id, role_id) SELECT ?, id FROM roles WHERE code = ?');
        foreach ($roles as $role) $roleStmt->execute([$userId, $role]);
        
        $_SESSION['success'] = "User added successfully!";
        header('Location: ' . $userManagerUrl);
        exit();
    }
    
    if (isset($_POST['delete_user'])) {
        $targetId = filter_var($_POST['user_id'] ?? 0, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) ?: 0;
        $targetStmt = $pdo->prepare("SELECT id, user_type FROM users WHERE id = ? AND farm_id = ? LIMIT 1");
        $targetStmt->execute([$targetId, $farmId]);
        $targetUser = $targetStmt->fetch(PDO::FETCH_ASSOC);
        if (!$targetUser || $targetId === (int)($_SESSION['user_id'] ?? 0) || $targetUser['user_type'] === 'farm_admin') {
            $_SESSION['error'] = 'The Farm Admin account is protected and cannot be deleted from Team Users.';
        } else {
            $pdo->prepare('DELETE FROM user_roles WHERE user_id = ?')->execute([$targetId]);
            $stmt = $pdo->prepare("DELETE FROM users WHERE id = ? AND farm_id = ?");
            $stmt->execute([$targetId, $farmId]);
            $_SESSION['success'] = "User deleted successfully!";
        }
        header('Location: ' . $userManagerUrl);
        exit();
    }

    if (isset($_POST['edit_user'])) {
        $editTargetId = filter_var($_POST['user_id'] ?? 0, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) ?: 0;
        $protectStmt = $pdo->prepare("SELECT user_type FROM users WHERE id = ? AND farm_id = ? LIMIT 1");
        $protectStmt->execute([$editTargetId, $farmId]);
        if ($protectStmt->fetchColumn() === 'farm_admin') { $_SESSION['error'] = 'The Farm Admin account is managed from the farm profile and is protected here.'; header('Location: ' . $userManagerUrl); exit(); }
        $roles = array_values(array_intersect($_POST['roles'] ?? [], array_keys($availableRoles)));
        $roles = array_values(array_filter($roles, static function ($role) use ($managedFarmHasModule) {
            if ($role === 'poultry_manager') return $managedFarmHasModule('poultry');
            if ($role === 'ruminant_manager') return $managedFarmHasModule('ruminant');
            if ($role === 'sales_rep') return $managedFarmHasModule('sales');
            return $role === 'viewer';
        }));
        if (!$roles) { $_SESSION['error'] = 'Select at least one role enabled by the platform owner.'; header('Location: ' . $userManagerUrl); exit(); }
        try { enforceTenantRoleLimits($pdo,$farmId,$roles,$editTargetId); } catch(RuntimeException $e) { $_SESSION['error']=$e->getMessage(); header('Location: ' . $userManagerUrl); exit(); }
        $updateQuery = "UPDATE users SET username = ?, user_type = ?, full_name = ?";
        $params = [
            $_POST['username'],
            $roles[0],
            $_POST['full_name']
        ];

        if (!empty($_POST['password'])) {
            $updateQuery .= ", password = ?";
            $params[] = password_hash($_POST['password'], PASSWORD_DEFAULT);
        }

        $updateQuery .= " WHERE id = ? AND farm_id = ?";
        $params[] = $_POST['user_id'];
        $params[] = $farmId;

        $stmt = $pdo->prepare($updateQuery);
        $stmt->execute($params);
        $pdo->prepare('DELETE ur FROM user_roles ur INNER JOIN users u ON u.id = ur.user_id WHERE ur.user_id = ? AND u.farm_id = ?')->execute([$_POST['user_id'], $farmId]);
        $roleStmt = $pdo->prepare('INSERT INTO user_roles (user_id, role_id) SELECT ?, id FROM roles WHERE code = ?');
        foreach ($roles as $role) $roleStmt->execute([$_POST['user_id'], $role]);

        $_SESSION['success'] = "User updated successfully!";
        header('Location: ' . $userManagerUrl);
        exit();
    }
}

// Get all users
$usersStmt = $pdo->prepare("SELECT u.*, GROUP_CONCAT(r.code ORDER BY r.code SEPARATOR ',') AS role_codes FROM users u LEFT JOIN user_roles ur ON ur.user_id = u.id LEFT JOIN roles r ON r.id = ur.role_id WHERE u.farm_id = ? GROUP BY u.id ORDER BY u.username");
$usersStmt->execute([$farmId]);
$users = $usersStmt->fetchAll();

$roleCounts = [
    'farm_admin' => 0,
    'poultry_manager' => 0,
    'ruminant_manager' => 0,
    'sales_rep' => 0
];

foreach ($users as $existingUser) {
    $role = $existingUser['user_type'] ?? '';
    if (isset($roleCounts[$role])) {
        $roleCounts[$role]++;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <?php include(__DIR__ . '/../navbar_head.php'); ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Management - Renee Farms</title>
    <style>
        .user-management-shell {
            background: radial-gradient(circle at top right, rgba(25, 135, 84, 0.09), transparent 45%),
                        radial-gradient(circle at top left, rgba(13, 110, 253, 0.1), transparent 40%);
            border-radius: 1.5rem;
            padding: 1.5rem;
            border: 1px solid rgba(13, 110, 253, 0.08);
        }

        .hero-card {
            border: 0;
            border-radius: 1rem;
            background: linear-gradient(120deg, #0d6efd, #198754);
            color: #fff;
            box-shadow: 0 1rem 2rem rgba(13, 110, 253, 0.25);
        }

        .metric-card {
            border: 0;
            border-radius: .9rem;
            box-shadow: 0 .5rem 1rem rgba(0, 0, 0, .06);
            transition: transform .2s ease, box-shadow .2s ease;
        }

        .metric-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 .8rem 1.3rem rgba(0, 0, 0, .1);
        }

        .users-table-wrap {
            border-radius: 1rem;
            overflow: hidden;
            border: 1px solid rgba(0, 0, 0, 0.06);
        }

        .table thead th {
            border-bottom: 0;
            text-transform: uppercase;
            font-size: .75rem;
            letter-spacing: .04em;
        }

        .table tbody td {
            vertical-align: middle;
        }

        .user-avatar {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            color: #0d6efd;
            background-color: rgba(13, 110, 253, .12);
        }
    </style>
</head>
<body>
    <?php include(__DIR__ . '/../navbar.php'); ?>

    <div class="container-fluid mt-4 mb-4">
        <?php if (isPlatformOwner()): ?>
        <form method="get" class="card card-body border-0 shadow-sm mb-3">
            <label class="form-label fw-semibold" for="managedFarmSelect">Manage users for farm</label>
            <select class="form-select" style="max-width:420px" id="managedFarmSelect" name="farm_id" onchange="this.form.submit()">
                <?php foreach ($platformFarmOptions as $farmOption): ?>
                <option value="<?php echo (int)$farmOption['id']; ?>" <?php echo (int)$farmOption['id'] === $farmId ? 'selected' : ''; ?>><?php echo htmlspecialchars($farmOption['name']); ?></option>
                <?php endforeach; ?>
            </select>
            <div class="form-text">Platform Owner view is tenant-scoped; actions below affect only the selected farm.</div>
        </form>
        <?php endif; ?>

        <div class="user-management-shell">
            <div class="row g-3 mb-3">
                <div class="col-lg-8">
                    <div class="card hero-card h-100">
                        <div class="card-body p-4">
                            <div class="d-flex justify-content-between align-items-start flex-wrap gap-3">
                                <div>
                                    <p class="text-uppercase small fw-semibold mb-2">Control Center</p>
                                    <h2 class="h3 mb-2"><i class="bi bi-people-fill me-2"></i>User Management</h2>
                                    <p class="mb-0 opacity-75">Manage staff access within the modules enabled by the platform owner for your subscription.</p>
                                </div>
                                <button class="btn btn-light text-primary fw-semibold" data-bs-toggle="modal" data-bs-target="#addUserModal">
                                    <i class="bi bi-person-plus-fill me-1"></i> Add User
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-lg-4">
                    <div class="card metric-card h-100">
                        <div class="card-body">
                            <p class="text-muted mb-1">Total Team Members</p>
                            <h2 class="fw-bold mb-3"><?php echo count($users); ?></h2>
                            <div class="d-flex flex-wrap gap-2">
                                <span class="badge bg-success-subtle text-success">Poultry: <?php echo $roleCounts['poultry_manager']; ?></span>
                                <span class="badge bg-warning-subtle text-dark">Ruminant: <?php echo $roleCounts['ruminant_manager']; ?></span>
                                <span class="badge bg-info-subtle text-info-emphasis">Sales: <?php echo $roleCounts['sales_rep']; ?></span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <div class="users-table-wrap table-responsive">
                        <table class="table table-hover mb-0">
                            <thead class="table-dark">
                                <tr>
                                    <th>User</th>
                                    <th>Role</th>
                                    <th>Created</th>
                                    <th class="text-end">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($users as $user): ?>
                                <tr>
                                    <td>
                                        <div class="d-flex align-items-center gap-2">
                                            <span class="user-avatar">
                                                <?php echo strtoupper(substr($user['full_name'] ?: $user['username'], 0, 1)); ?>
                                            </span>
                                            <div>
                                                <strong><?php echo htmlspecialchars($user['username']); ?></strong>
                                                <div class="text-muted small"><?php echo htmlspecialchars($user['full_name']); ?></div>
                                            </div>
                                            <?php if ($user['id'] == $_SESSION['user_id']): ?>
                                            <span class="badge bg-primary-subtle text-primary">You</span>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td>
                                        <?php
                                            $badgeClasses = [
                                                'farm_admin' => 'danger',
                                                'poultry_manager' => 'success',
                                                'ruminant_manager' => 'warning',
                                                'sales_rep' => 'info'
                                            ];
                                            $badgeClass = $badgeClasses[$user['user_type']] ?? 'secondary';
                                        ?>
                                        <span class="badge bg-<?php echo $badgeClass; ?>">
                                            <?php echo htmlspecialchars(str_replace('_', ' ', ucfirst($user['user_type']))); ?>
                                        </span>
                                    </td>
                                    <td><?php echo htmlspecialchars(date('d M Y', strtotime($user['created_at']))); ?></td>
                                    <td class="text-end">
                                        <div class="d-inline-flex gap-1">
                                            <?php if ($user['user_type'] !== 'farm_admin'): ?>
                                            <button type="button"
                                                    class="btn btn-sm btn-outline-primary edit-user-btn"
                                                    data-user-id="<?php echo $user['id']; ?>"
                                                    data-username="<?php echo htmlspecialchars($user['username'], ENT_QUOTES); ?>"
                                                    data-full-name="<?php echo htmlspecialchars($user['full_name'], ENT_QUOTES); ?>"
                                                    data-user-type="<?php echo $user['user_type']; ?>"
                                                    data-roles="<?php echo htmlspecialchars($user['role_codes'] ?? $user['user_type'], ENT_QUOTES); ?>">
                                                <i class="bi bi-pencil-square me-1"></i>Edit
                                            </button>

                                            <?php if ($user['id'] != $_SESSION['user_id']): ?>
                                            <form method="POST" style="display: inline;" data-confirm="Delete this user account? Their login access will be removed immediately.">
                                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrf_token()); ?>">
                    <?php if (isPlatformOwner()): ?><input type="hidden" name="target_farm_id" value="<?php echo (int)$farmId; ?>"><?php endif; ?>
                                                <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                                <button type="button"
                                                        class="btn btn-sm btn-outline-danger" onclick="confirmUserDeletion(this.form, <?php echo htmlspecialchars(json_encode($user['username']), ENT_QUOTES, 'UTF-8'); ?>); return false;"
>
                                                    <i class="bi bi-trash me-1"></i>Delete
                                                </button>
                                            </form>
                                            <?php endif; ?>
                                            <?php else: ?>
                                            <span class="badge bg-light text-dark border" title="Farm Admin is managed from the farm profile">Protected Farm Admin</span>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Add User Modal -->
    <div class="modal fade" id="addUserModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrf_token()); ?>">
                    <?php if (isPlatformOwner()): ?><input type="hidden" name="target_farm_id" value="<?php echo (int)$farmId; ?>"><?php endif; ?>
                    <div class="modal-header">
                        <h5 class="modal-title">Add New User</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label>Username</label>
                            <input type="text" name="username" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label>Password</label>
                            <input type="password" name="password" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label>Full Name</label>
                            <input type="text" name="full_name" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="d-block">Roles</label>
                            <?php foreach ($availableRoles as $code => $label): $disabled = ($code === 'poultry_manager' && !$managedFarmHasModule('poultry')) || ($code === 'ruminant_manager' && !$managedFarmHasModule('ruminant')) || ($code === 'sales_rep' && !$managedFarmHasModule('sales')); ?>
                            <div class="form-check"><input class="form-check-input" type="checkbox" name="roles[]" value="<?php echo $code; ?>" id="add-<?php echo $code; ?>" <?php echo $disabled ? 'disabled' : ''; ?>><label class="form-check-label" for="add-<?php echo $code; ?>"><?php echo $label; ?><?php if (isset($tenantRoleLimits[$code])): ?> <span class="small text-muted">(<?php echo tenantRoleCount($pdo,$farmId,$code); ?>/<?php echo (int)$tenantRoleLimits[$code]; ?> used)</span><?php endif; ?></label></div><?php endforeach; ?>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="add_user" class="btn btn-primary">Add User</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit User Modal -->
    <div class="modal fade" id="editUserModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrf_token()); ?>">
                    <?php if (isPlatformOwner()): ?><input type="hidden" name="target_farm_id" value="<?php echo (int)$farmId; ?>"><?php endif; ?>
                    <input type="hidden" name="user_id">
                    <div class="modal-header">
                        <h5 class="modal-title">Edit User</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label>Username</label>
                            <input type="text" name="username" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label>Full Name</label>
                            <input type="text" name="full_name" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="d-block">Roles</label>
                            <?php foreach ($availableRoles as $code => $label): $disabled = ($code === 'poultry_manager' && !$managedFarmHasModule('poultry')) || ($code === 'ruminant_manager' && !$managedFarmHasModule('ruminant')) || ($code === 'sales_rep' && !$managedFarmHasModule('sales')); ?>
                            <div class="form-check"><input class="form-check-input" type="checkbox" name="roles[]" value="<?php echo $code; ?>" id="edit-<?php echo $code; ?>" <?php echo $disabled ? 'disabled' : ''; ?>><label class="form-check-label" for="edit-<?php echo $code; ?>"><?php echo $label; ?></label></div><?php endforeach; ?>
                        </div>
                        <div class="mb-3">
                            <label>New Password (leave blank to keep current)</label>
                            <input type="password" name="password" class="form-control" autocomplete="new-password">
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="edit_user" class="btn btn-primary">Save Changes</button>
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


    <script>
    attachEditModal({
        buttonSelector: '.edit-user-btn',
        modalSelector: '#editUserModal',
        fieldMap: {
            userId: 'input[name="user_id"]',
            username: 'input[name="username"]',
            fullName: 'input[name="full_name"]'
        },
        onShow: ({ modalElement, data }) => {
            const passwordField = modalElement.querySelector('input[name="password"]');
            if (passwordField) passwordField.value = '';
            const roles = (data.roles || '').split(',');
            modalElement.querySelectorAll('input[name="roles[]"]').forEach((field) => { field.checked = roles.includes(field.value); });
        }
    });
    </script>

<script>
function confirmUserDeletion(form, username) {
    AppConfirm.ask('This removes ' + username + "'s login access immediately.", {title:'Delete user?',confirmText:'Delete user',danger:true}).then(function(confirmed){
        if(!confirmed) return; const action=document.createElement('input'); action.type='hidden'; action.name='delete_user'; action.value='1'; form.appendChild(action); form.submit();
    });
}
</script>
</body>
</html>
