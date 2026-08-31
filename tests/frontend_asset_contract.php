<?php
/** V2.2 frontend asset contract. Run: php tests/frontend_asset_contract.php */
$root = dirname(__DIR__);
$missing = [];
$checked = [];
$files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));
foreach ($files as $file) {
    if (!$file->isFile() || strtolower($file->getExtension()) !== 'php') continue;
    $path = $file->getPathname();
    if (str_contains($path, DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR)) continue;
    $text = file_get_contents($path);
    if ($text === false) continue;
    if (preg_match_all("~versioned_asset\\(\\s*['\"](/assets/[^'\"?]+)~", $text, $m)) {
        foreach ($m[1] as $asset) {
            $checked[$asset] = true;
            if (!is_file($root . $asset)) $missing[$asset][] = str_replace($root . DIRECTORY_SEPARATOR, '', $path);
        }
    }
}
$required = [
    '/assets/vendor/bootstrap5/js/bootstrap.bundle.min.js',
    '/assets/vendor/bootstrap5/css/bootstrap.min.css',
    '/assets/vendor/jquery/jquery.min.js',
    '/assets/vendor/datatables/js/jquery.dataTables.min.js',
    '/assets/vendor/datatables/js/dataTables.bootstrap5.min.js',
    '/assets/vendor/datatables-responsive/js/dataTables.responsive.min.js',
];
foreach ($required as $asset) {
    $checked[$asset] = true;
    if (!is_file($root . $asset)) $missing[$asset][] = 'required runtime dependency';
}
if ($missing) {
    fwrite(STDERR, "FRONTEND ASSET CONTRACT FAILED\n");
    foreach ($missing as $asset => $sources) fwrite(STDERR, "- $asset (" . implode(', ', array_unique($sources)) . ")\n");
    exit(1);
}
echo "Frontend asset contract passed. Checked " . count($checked) . " local asset references.\n";
