<?php
$root = dirname(__DIR__);
$checks = [];
$must = function(bool $ok, string $msg) use (&$checks) { $checks[] = [$ok, $msg]; };
foreach (['management/sales_records.php','management/expenses.php'] as $file) {
    $src = file_get_contents($root . '/' . $file);
    $must(str_contains($src, 'PDF Monthly'), "$file exposes PDF Monthly");
    $must(str_contains($src, 'PDF Yearly'), "$file exposes PDF Yearly");
    $must(str_contains($src, 'href="<?php echo htmlspecialchars($pdfReportUrl); ?>"'), "$file PDF actions are links");
    $must(!str_contains($src, '> Print Monthly</button>'), "$file dead Print Monthly removed");
}
foreach (['poultry/layer_expenses.php'=>'Layer','poultry/broiler_expenses.php'=>'Broiler','ruminant/ruminant_expenses.php'=>'Ruminant'] as $file=>$label) {
    $src = file_get_contents($root . '/' . $file);
    $must(str_contains($src, 'PdfReportService.php'), "$label expenses loads PDF service");
    $must(str_contains($src, 'pdf_report_current_url()'), "$label expenses builds PDF URL");
    $must(str_contains($src, 'PDF Report'), "$label expenses exposes PDF Report");
    $must(str_contains($src, 'pdf_report_finish('), "$label expenses streams PDF");
}
$fail=0;
foreach($checks as [$ok,$msg]) { echo ($ok?'PASS':'FAIL')." - $msg\n"; if(!$ok)$fail++; }
echo 'Result: '.(count($checks)-$fail).'/'.count($checks)." passed\n";
exit($fail?1:0);
