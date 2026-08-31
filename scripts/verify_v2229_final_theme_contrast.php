<?php
$root = dirname(__DIR__);
$theme = file_get_contents($root . '/assets/css/theme.css');
$checks = [
    'intelligence strip gets dark surface' => str_contains($theme, 'html[data-theme="dark"] .smart-poultry-note') && str_contains($theme, 'background: linear-gradient(135deg, #16243a, #1b2b43) !important;'),
    'intelligence heading is explicit white' => str_contains($theme, '.smart-poultry-note .fw-bold') && str_contains($theme, 'color: #ffffff !important;'),
    'intelligence helper copy is readable' => str_contains($theme, '.smart-poultry-note .small') && str_contains($theme, 'color: #c8d5e5 !important;'),
    'intelligence icon has dark-mode accent' => str_contains($theme, '.smart-poultry-note .bi') && str_contains($theme, 'color: #66a9ff !important;'),
    'inventory sticky action body is dark' => str_contains($theme, '.inventory-table > tbody > tr:not(.child) > td:last-child') && str_contains($theme, 'background: var(--app-surface) !important;'),
    'inventory sticky action header is dark' => str_contains($theme, '.inventory-table > thead > tr > th:last-child') && str_contains($theme, 'background: #162237 !important;'),
    'bootstrap dark attribute covered' => str_contains($theme, 'html[data-bs-theme="dark"] .smart-poultry-note') && str_contains($theme, 'html[data-bs-theme="dark"] .inventory-table > thead > tr > th:last-child'),
];
$passed = 0;
foreach ($checks as $label => $ok) {
    echo ($ok ? 'PASS: ' : 'FAIL: ') . $label . PHP_EOL;
    if ($ok) $passed++;
}
if ($passed !== count($checks)) {
    fwrite(STDERR, "V2.2.29 verification failed: {$passed}/" . count($checks) . " checks passed.\n");
    exit(1);
}
echo "V2.2.29 verification passed: {$passed} check(s).\n";
