<?php
$root = dirname(__DIR__);
$checks = [];
$must = function(bool $ok, string $msg) use (&$checks) { $checks[] = [$ok, $msg]; };
$composer = file_get_contents($root . '/composer.json');
$service = file_get_contents($root . '/includes/pdf/PdfReportService.php');
$must(str_contains($composer, 'dompdf/dompdf'), 'Dompdf dependency declared');
$must(str_contains($service, 'Content-Type: application/pdf'), 'central service emits PDF content type');
$must(str_contains($service, 'Content-Disposition: inline'), 'central service opens generated PDF inline');
$must(str_contains($service, 'Page {PAGE_NUM} of {PAGE_COUNT}'), 'central page numbering enabled');
$must(!str_contains($service, 'reneefarms.com/'), 'central footer does not hardcode site URL');
$targets = [
    'management/sales_records.php',
    'management/expenses.php',
    'management/poultry_ruminant_report.php',
];
foreach ($targets as $target) {
    $src = file_get_contents($root . '/' . $target);
    $must(str_contains($src, 'pdf_report_current_url()'), "$target has application PDF URL");
    $must(str_contains($src, 'pdf_report_finish('), "$target streams via central PDF service");
}
$pr = file_get_contents($root . '/management/poultry_ruminant_report.php');
$must(str_contains($pr, 'PDF Report'), 'Poultry & Ruminant broken print action replaced with PDF action');
$must(!preg_match("/\\$\\('#printBtn'\\).*PrintManager\\.print/s", $pr), 'Poultry & Ruminant no longer depends on broken browser print handler');
if (is_file($root . '/vendor/autoload.php')) {
    require_once $root . '/includes/pdf/PdfReportService.php';
    $pdf = (new PdfReportService($root))->renderHtml('<!doctype html><html><head><title>Test</title></head><body><h1>Renee Farms</h1><p>₦90,000.00</p></body></html>', 'portrait', 'Verifier');
    $must(str_starts_with($pdf, '%PDF-'), 'Dompdf renders a valid PDF document');
} else {
    $must(false, 'vendor/autoload.php installed');
}
$fail = 0;
foreach ($checks as [$ok, $msg]) { echo ($ok ? 'PASS' : 'FAIL') . " - $msg\n"; if (!$ok) $fail++; }
echo 'Result: ' . (count($checks) - $fail) . '/' . count($checks) . " passed\n";
exit($fail ? 1 : 0);
