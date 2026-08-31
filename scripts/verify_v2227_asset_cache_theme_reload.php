<?php
$root = dirname(__DIR__);
$checks = 0;
function ck($ok, $label) { global $checks; if (!$ok) { fwrite(STDERR, "FAIL: $label\n"); exit(1); } $checks++; echo "PASS: $label\n"; }
$init = file_get_contents($root . '/init.php');
$theme = file_get_contents($root . '/assets/css/theme.css');
$head = file_get_contents($root . '/navbar_head.php');
ck(strpos($init, "ASSET_VERSION', '2.2.27'") !== false, 'release fallback asset version is current');
ck(strpos($init, 'filemtime($localPath)') !== false, 'local assets use file modification time for cache busting');
ck(strpos($init, 'rawurlencode($version)') !== false, 'asset cache key is safely encoded');
ck(substr_count($head, 'versioned_asset(') >= 3, 'shared stylesheets use versioned asset URLs');
ck(strpos($theme, 'V2.2.27 — cache-bust validation') !== false, 'fresh theme release marker exists');
ck(strpos($theme, 'html[data-bs-theme="dark"] .stock-control-table tbody td') !== false, 'Bootstrap dark attribute also hardens stock rows');
ck(strpos($theme, 'html[data-bs-theme="dark"] .smart-action-card') !== false, 'Bootstrap dark attribute also hardens quick actions');
echo "V2.2.27 verification passed: {$checks} check(s).\n";
