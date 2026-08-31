<?php
$root = dirname(__DIR__);
$checks = [];
$must = function(bool $ok, string $msg) use (&$checks) { $checks[] = [$ok, $msg]; };
$expenses = file_get_contents($root . '/management/expenses.php');
$must(str_contains($expenses, '$pdfRequested = pdf_report_is_requested();'), 'Expense Report initializes PDF mode');
$must(str_contains($expenses, 'pdf_report_begin()'), 'Expense Report starts PDF capture');
$must(str_contains($expenses, 'pdf_report_finish('), 'Expense Report finishes through central PDF engine');
$reports = file_get_contents($root . '/management/reports.php');
$must(str_contains($reports, 'PdfReportService.php'), 'Reports & Analytics loads central PDF service');
$must(str_contains($reports, 'PDF Report'), 'Reports & Analytics exposes PDF Report');
$must(str_contains($reports, 'pdf_report_finish('), 'Reports & Analytics streams application PDF');
$must(!str_contains($reports, 'PrintManager.print();'), 'Reports & Analytics no longer uses browser print');
$service = file_get_contents($root . '/includes/pdf/PdfReportService.php');
$must(str_contains($service, 'stripActionsColumns'), 'Central PDF service strips Actions columns');
$must(str_contains($service, "preg_replace('#<i"), 'Central PDF service strips icon-font tags');
$must(count(glob($root . '/DEPLOYMENT_V2.2.*.md')) === 0, 'Version-specific deployment docs consolidated');
$must(str_contains(file_get_contents($root . '/DEPLOYMENT.md'), 'V2.2.44'), 'DEPLOYMENT.md includes V2.2.44');
$fail = 0;
foreach ($checks as [$ok,$msg]) { echo ($ok ? 'PASS' : 'FAIL') . " - $msg\n"; if (!$ok) $fail++; }
echo 'Result: ' . (count($checks)-$fail) . '/' . count($checks) . " passed\n";
exit($fail ? 1 : 0);
