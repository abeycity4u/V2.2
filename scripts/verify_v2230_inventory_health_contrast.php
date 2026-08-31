<?php
$root = dirname(__DIR__);
$theme = file_get_contents($root . '/assets/css/theme.css');
$checks = [
    'inventory score dark selector exists' => strpos($theme, 'html[data-theme="dark"] .inventory-score-ring') !== false,
    'bootstrap dark selector exists' => strpos($theme, 'html[data-bs-theme="dark"] .inventory-score-ring') !== false,
    'score ring uses dark surface token' => strpos($theme, 'radial-gradient(circle at center, var(--app-surface)') !== false,
    'score value uses strong text token' => strpos($theme, '.inventory-score-ring strong') !== false && strpos($theme, 'var(--app-text-strong)') !== false,
];
$pass = 0;
foreach ($checks as $label => $ok) {
    echo ($ok ? 'PASS: ' : 'FAIL: ') . $label . PHP_EOL;
    if ($ok) $pass++;
}
if ($pass !== count($checks)) exit(1);
echo 'V2.2.30 verification passed: ' . $pass . ' check(s).' . PHP_EOL;
