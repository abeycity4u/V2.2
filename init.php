<?php
// init.php - common bootstrap for all pages when included
// Use __DIR__-based includes; this file should be required using an absolute path from each script when possible.
if (!defined('PROJECT_ROOT')) define('PROJECT_ROOT', __DIR__);
// V2.2.27: static asset versioning previously stayed at 2024.06.01, so browsers
// could keep an older CSS/JS file after a deployment. Prefer each local asset's
// mtime as the cache key and keep the release token only as a safe fallback.
if (!defined('ASSET_VERSION')) define('ASSET_VERSION', '2.2.27');

if (!function_exists('versioned_asset')) {
    function versioned_asset(string $path): string
    {
        $assetPath = parse_url($path, PHP_URL_PATH);
        $localPath = PROJECT_ROOT . '/' . ltrim((string) $assetPath, '/');
        $version = is_file($localPath) ? (string) filemtime($localPath) : ASSET_VERSION;
        $delimiter = strpos($path, '?') === false ? '?' : '&';
        return $path . $delimiter . 'v=' . rawurlencode($version);
    }
}
if (!isset($pdo)) {
    // try to load config.php which should create $pdo
    if (file_exists(__DIR__ . '/config.php')) require_once __DIR__ . '/config.php';
}
?>