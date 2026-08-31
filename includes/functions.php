<?php require_once(__DIR__ . '/../init.php'); ?>
<?php
// includes/functions.php (updated compatibility)

// If $pdo is not set but $conn (mysqli) is present, create a PDO wrapper.
if (!isset($pdo) && isset($conn) && $conn instanceof mysqli) {
    $mysqli = $conn;
    $dsn = 'mysql:host='.$mysqli->host_info.';dbname='.$mysqli->database.';charset=utf8mb4';
    // The above may not give correct host/db - prefer config.php to create $pdo. Skip automatic conversion.
}

if (!function_exists('ensureInventoryActiveColumn')) {
function ensureInventoryActiveColumn($pdo) {
    // Ensure the stock_items table has an is_active flag for soft deletes
    $checkStmt = $pdo->query("SHOW COLUMNS FROM stock_items LIKE 'is_active'");
    if ($checkStmt->rowCount() === 0) {
        $pdo->exec("ALTER TABLE stock_items ADD COLUMN is_active TINYINT(1) NOT NULL DEFAULT 1 AFTER farm_type");
    }
}
}

if (!function_exists('ensureInventoryUnitCostColumn')) {
function ensureInventoryUnitCostColumn($pdo) {
    // Ensure the stock_items table can store a unit cost for accurate stock valuation
    $checkStmt = $pdo->query("SHOW COLUMNS FROM stock_items LIKE 'unit_cost'");
    if ($checkStmt->rowCount() === 0) {
        $pdo->exec("ALTER TABLE stock_items ADD COLUMN unit_cost DECIMAL(14,2) NOT NULL DEFAULT 0 AFTER current_stock");
    }
}
}

if (!function_exists('ensureExpenseUnitColumn')) {
function ensureExpenseUnitColumn($pdo) {
    // Ensure the farm_expenses table can store a unit quantity for line-item totals
    $checkStmt = $pdo->query("SHOW COLUMNS FROM farm_expenses LIKE 'unit'");
    if ($checkStmt->rowCount() === 0) {
        $pdo->exec("ALTER TABLE farm_expenses ADD COLUMN unit DECIMAL(12,2) NOT NULL DEFAULT 1 AFTER amount");
    }
}
}

if (!function_exists('ensureUserLastLoginColumn')) {
function ensureUserLastLoginColumn($pdo) {
    // Track the last time each user logged in to avoid shared login timestamps
    $checkStmt = $pdo->query("SHOW COLUMNS FROM users LIKE 'last_login_at'");
    if ($checkStmt->rowCount() === 0) {
        $pdo->exec("ALTER TABLE users ADD COLUMN last_login_at DATETIME NULL DEFAULT NULL AFTER full_name");
    }
}
}

if (!function_exists('ensurePoultryCategoryColumn')) {
function ensurePoultryCategoryColumn($pdo) {
    // Ensure the farm_expenses table can distinguish poultry expenses (broiler vs layer)
    $checkStmt = $pdo->query("SHOW COLUMNS FROM farm_expenses LIKE 'poultry_category'");
    if ($checkStmt->rowCount() === 0) {
        $pdo->exec("ALTER TABLE farm_expenses ADD COLUMN poultry_category ENUM('broiler','layer') NULL DEFAULT NULL AFTER farm_type");
    }
}
}

if (!function_exists('ensurePermissionsTable')) {
function ensurePermissionsTable($pdo) {
    // Ensure permissions table exists before attempting permission reads/writes
    $checkStmt = $pdo->query("SHOW TABLES LIKE 'permissions'");
    if ($checkStmt->rowCount() === 0) {
        $pdo->exec(
            "CREATE TABLE permissions (" .
            "id INT AUTO_INCREMENT PRIMARY KEY," .
            "farm_id INT NOT NULL DEFAULT 0," .
            // Use VARCHAR instead of ENUM to avoid insert errors when roles change
            "role VARCHAR(100) NOT NULL," .
            "module VARCHAR(100) NOT NULL," .
            "allowed TINYINT(1) NOT NULL DEFAULT 0," .
            "UNIQUE KEY uniq_permission_farm_role_module (farm_id, role, module)" .
            ")"
        );
    } else {
        // If the table exists from an older schema (ENUM), relax the column to VARCHAR
        $columnStmt = $pdo->query("SHOW COLUMNS FROM permissions LIKE 'role'");
        $column = $columnStmt->fetch(PDO::FETCH_ASSOC);
        if ($column && stripos($column['Type'], 'enum(') === 0) {
            $pdo->exec("ALTER TABLE permissions MODIFY COLUMN role VARCHAR(100) NOT NULL");
        }
    }
}
}

if (!function_exists('runSchemaMigrations')) {
function runSchemaMigrations($pdo, array $targets = []) {
    static $completed = [];

    $map = [
        'inventory_active' => 'ensureInventoryActiveColumn',
        'inventory_unit_cost' => 'ensureInventoryUnitCostColumn',
        'expense_unit' => 'ensureExpenseUnitColumn',
        'user_last_login' => 'ensureUserLastLoginColumn',
        'poultry_category' => 'ensurePoultryCategoryColumn',
        'permissions_table' => 'ensurePermissionsTable'
    ];

    $selectedTargets = empty($targets) ? array_keys($map) : $targets;
    sort($selectedTargets);
    $signature = implode('|', $selectedTargets);

    if (isset($completed[$signature])) {
        return;
    }

    foreach ($selectedTargets as $target) {
        if (!isset($map[$target])) {
            continue;
        }

        $functionName = $map[$target];
        if (function_exists($functionName)) {
            $functionName($pdo);
        }
    }

    $completed[$signature] = true;
}
}

if (!function_exists('hasPermission')) {
function hasPermission($role, $module) {
    global $pdo, $conn;
    if (!$role && !function_exists('currentUserRoles')) return false;
    if (function_exists('isPlatformOwner') && isPlatformOwner()) return true;

    // Farm subscription/module entitlement is always the outer boundary.
    if (function_exists('farmHasModule')) {
        if (str_starts_with($module, 'poultry') && !farmHasModule('poultry')) return false;
        if (str_starts_with($module, 'ruminant') && !farmHasModule('ruminant')) return false;
        if ($module === 'sales' && !farmHasModule('sales')) return false;
    }

    if (function_exists('hasRole')) {
        if (hasRole('farm_admin')) return true;
        if (hasRole('viewer')) return in_array($module, ['management', 'reports'], true);

        // Production-module permissions normally belong to their specialist role,
        // but expense entry is intentionally delegable to Sales Representatives.
        // Do not reject that delegated permission before the tenant permission
        // matrix has a chance to evaluate it.
        $delegatedPoultryExpense = $module === 'poultry_expenses' && hasRole('sales_rep');
        $delegatedRuminantExpense = $module === 'ruminant_expenses' && hasRole('sales_rep');
        if (str_starts_with($module, 'poultry') && !hasRole('poultry_manager') && !$delegatedPoultryExpense) return false;
        if (str_starts_with($module, 'ruminant') && !hasRole('ruminant_manager') && !$delegatedRuminantExpense) return false;
        if ($module === 'sales' && !hasRole('sales_rep')) return false;
    } elseif ($role === 'farm_admin') {
        return true;
    }

    // Read current roles from the database on every request. This makes role and
    // permission changes effective as soon as the affected user refreshes/navigates.
    $roles = function_exists('currentUserRoles') ? currentUserRoles() : [$role];
    $roles = array_values(array_filter(array_unique($roles ?: [$role])));
    if (!$roles) return false;
    $farmId = function_exists('getCurrentFarmId') ? getCurrentFarmId() : 0;

    if (isset($pdo)) {
        $placeholders = implode(',', array_fill(0, count($roles), '?'));
        // Tenant-scoped permission overrides global defaults (farm_id=0).
        $sql = "SELECT farm_id, allowed FROM permissions WHERE farm_id IN (0, ?) AND role IN ($placeholders) AND module = ? ORDER BY farm_id DESC";
        $stmt = $pdo->prepare($sql);
        if ($stmt->execute(array_merge([$farmId], $roles, [$module]))) {
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            if ($rows) {
                $tenantRows = array_values(array_filter($rows, static fn($r) => (int)$r['farm_id'] === $farmId));
                $effectiveRows = $tenantRows ?: array_values(array_filter($rows, static fn($r) => (int)$r['farm_id'] === 0));
                foreach ($effectiveRows as $row) if ((int)$row['allowed'] === 1) return true;
                return false;
            }
        }
        return false;
    } elseif (isset($conn)) {
        // Legacy mysqli fallback retains the primary role check.
        $sql = "SELECT allowed FROM permissions WHERE role = ? AND module = ? ORDER BY farm_id DESC LIMIT 1";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('ss',$role,$module);
        $stmt->execute();
        $stmt->bind_result($allowed);
        if ($stmt->fetch()) { $stmt->close(); return ($allowed==1); }
        $stmt->close();
        return false;
    }
    return false;
}
}

if (!function_exists('ensureAllowed')) {
function ensureAllowed($module) {
    if (!isset($_SESSION['user_type'])) {
        header('Location: ' . BASE_URL . '/login.php');
        exit();
    }
    if (!hasPermission($_SESSION['user_type'], $module)) {
        header('Location: ' . BASE_URL . '/no_access.php');
        exit();
    }
}
}
?>
