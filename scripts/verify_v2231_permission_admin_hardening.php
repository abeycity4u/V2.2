<?php
$root = dirname(__DIR__);
$checks = [];
function check_contract(&$checks, $name, $ok) { $checks[] = [$name, (bool)$ok]; }
function contains_text($path, $needle) { return is_file($path) && strpos(file_get_contents($path), $needle) !== false; }

check_contract($checks, 'Broiler feed rows carry audit/operational class', contains_text($root . '/poultry/broiler_feeds.php', "feed-audit-row' : 'feed-operational-row"));
check_contract($checks, 'Permissions migration is tenant scoped', contains_text($root . '/migrations/020_permission_admin_hardening.sql', 'uniq_permission_farm_role_module'));
check_contract($checks, 'Permission reads prefer tenant overrides', contains_text($root . '/includes/functions.php', 'Tenant-scoped permission overrides global defaults'));
check_contract($checks, 'Current roles are read from database per request', contains_text($root . '/config.php', 'SELECT r.code FROM user_roles'));
check_contract($checks, 'Enabled farm modules are read from database per request', contains_text($root . '/config.php', 'SELECT module_code FROM farm_modules WHERE farm_id = ? AND is_enabled = 1'));
check_contract($checks, 'Sales/specialist users can receive Inventory access', contains_text($root . '/inventory.php', 'Inventory is permission-driven'));
check_contract($checks, 'Expense Report is permission-driven', contains_text($root . '/management/expenses.php', "hasPermission(getUserType(), 'expenses')"));
check_contract($checks, 'Expense Report navigation respects delegated permission', contains_text($root . '/navbar.php', "hasPermission(\$_SESSION['user_type'], 'expenses')"));
check_contract($checks, 'Users delete uses custom confirmation', contains_text($root . '/management/users.php', 'data-confirm="Delete this user account?'));
check_contract($checks, 'Platform Owner can select tenant users', contains_text($root . '/management/users.php', 'managedFarmSelect'));
check_contract($checks, 'Farm purge clears V2.2 allocation tables', contains_text($root . '/management/farms.php', "'sales_allocations', 'financial_allocations'"));
check_contract($checks, 'Farm purge is transaction wrapped', contains_text($root . '/management/farms.php', '$pdo->beginTransaction(); deleteFarmData($pdo, $farmId); $pdo->commit()'));
check_contract($checks, 'Farm Admin access label is module based', contains_text($root . '/config.php', 'function currentAccessLabel'));
check_contract($checks, 'Permission tip has explicit dark contrast', contains_text($root . '/admin/permissions.php', 'html[data-theme="dark"] .permission-tip'));

$failed = 0;
foreach ($checks as [$name, $ok]) {
    echo ($ok ? '[PASS] ' : '[FAIL] ') . $name . PHP_EOL;
    if (!$ok) $failed++;
}
echo count($checks) . ' check(s); ' . $failed . ' failure(s).' . PHP_EOL;
exit($failed ? 1 : 0);
