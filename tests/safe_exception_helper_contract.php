<?php
$root = dirname(__DIR__);
$contents = file_get_contents($root . '/config.php');
if (strpos($contents, "function safeUserExceptionMessage") === false) {
    fwrite(STDERR, "safeUserExceptionMessage helper missing from common bootstrap.\n");
    exit(1);
}
if (strpos($contents, "if (!function_exists('safeUserExceptionMessage'))") === false) {
    fwrite(STDERR, "safeUserExceptionMessage is not guarded against redeclaration.\n");
    exit(1);
}
foreach (['Insufficient stock.', 'Opening stock must match', 'Cycle code already exists'] as $prefix) {
    if (strpos($contents, $prefix) === false) {
        fwrite(STDERR, "Expected safe validation prefix missing: $prefix\n");
        exit(1);
    }
}
if (strpos($contents, "SQLSTATE") !== false && strpos($contents, "Never expose SQLSTATE") === false) {
    fwrite(STDERR, "SQLSTATE handling appears to be missing from the safe exception helper.\n");
    exit(1);
}
echo "Safe exception helper contract passed.\n";
