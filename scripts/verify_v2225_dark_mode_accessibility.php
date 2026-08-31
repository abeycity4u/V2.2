<?php
$root = dirname(__DIR__);
$checks = [];
function check25($ok, $label) {
    global $checks;
    $checks[] = $ok;
    echo ($ok ? 'PASS: ' : 'FAIL: ') . $label . PHP_EOL;
}
$theme = file_get_contents($root . '/assets/css/theme.css');
check25(strpos($theme, 'V2.2.25 — visual/accessibility hardening') !== false, 'v2.2.25 theme hardening layer exists');
check25(strpos($theme, '--app-text-secondary') !== false && strpos($theme, '--app-text-disabled') !== false, 'semantic readable and disabled text tokens exist');
check25(strpos($theme, '.stock-control-table tbody td') !== false && strpos($theme, 'background:var(--app-surface-2)!important') !== false, 'dashboard stock rows use paired dark surface and foreground');
check25(strpos($theme, '.smart-action-card') !== false && strpos($theme, '.production-tile') !== false, 'quick actions and production tiles are dark-mode scoped');
check25(strpos($theme, 'table.dataTable tbody td') !== false, 'DataTables rows receive explicit dark foreground/background');
check25(strpos($theme, '.form-control:focus') !== false && strpos($theme, 'border-color:#62a4ff') !== false, 'form focus contrast is explicit');
check25(strpos($theme, '.bg-success-subtle') !== false && strpos($theme, '.bg-warning-subtle') !== false, 'subtle semantic badges use paired dark colors');
check25(strpos($theme, '[style*="background: #fff"]') !== false, 'legacy inline white surfaces receive dark fallback');
if (in_array(false, $checks, true)) {
    fwrite(STDERR, "V2.2.25 verification failed.\n"); exit(1);
}
echo 'V2.2.25 verification passed: ' . count($checks) . " check(s).\n";
