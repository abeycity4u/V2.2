<?php
$root = dirname(__DIR__);
$theme = file_get_contents($root . '/assets/css/theme.css');
$dashboard = file_get_contents($root . '/dashboard.php');
$checks = [
    'semantic elevated surface token exists' => strpos($theme, '--app-surface-elevated:') !== false,
    'dashboard stock rows have explicit dark foreground pairing' => strpos($theme, '.stock-control-table td strong') !== false,
    'dashboard quick actions have explicit child text pairing' => strpos($theme, '.smart-action-card strong') !== false && strpos($theme, '.smart-action-card small') !== false,
    'smart health ring no longer keeps a white center in dark mode' => strpos($theme, 'radial-gradient(circle at center,var(--app-surface-2)') !== false,
    'daily calendar surfaces are dark-theme aware' => strpos($theme, '.calendar-day:not(.has-record)') !== false,
    'striped table odd and even rows are both paired' => strpos($theme, 'nth-of-type(odd)') !== false && strpos($theme, 'nth-of-type(even)') !== false,
    'native select option palette is defined' => strpos($theme, 'select option') !== false,
    'modal close control is visible on dark surfaces' => strpos($theme, '.btn-close') !== false && strpos($theme, 'filter:invert(1)') !== false,
    'light utility button state is explicitly paired' => strpos($theme, '.btn-light') !== false,
    'dashboard still exposes stock command center for regression target' => strpos($dashboard, 'Smart Stock Control') !== false,
];
$passed = 0;
foreach ($checks as $label => $ok) {
    if ($ok) { echo "PASS: {$label}\n"; $passed++; }
    else { echo "FAIL: {$label}\n"; }
}
if ($passed !== count($checks)) {
    fwrite(STDERR, 'V2.2.26 verification failed: ' . $passed . '/' . count($checks) . " checks passed.\n");
    exit(1);
}
echo 'V2.2.26 verification passed: ' . $passed . " check(s).\n";
