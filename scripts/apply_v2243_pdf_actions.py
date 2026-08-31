from pathlib import Path
import re

root = Path(__file__).resolve().parents[1]

old_month = '''<button class="btn btn-primary" id="printMonthlyBtn" <?php echo $reportMode === 'yearly' ? 'style="display:none;"' : ''; ?>><i class="bi bi-printer"></i> Print Monthly</button>'''
new_month = '''<a class="btn btn-primary" id="printMonthlyBtn" <?php echo $reportMode === 'yearly' ? 'style="display:none;"' : ''; ?> href="<?php echo htmlspecialchars($pdfReportUrl); ?>" target="_blank"><i class="bi bi-file-earmark-pdf"></i> PDF Monthly</a>'''
old_year = '''<button class="btn btn-primary" id="printYearlyBtn" <?php echo $reportMode === 'monthly' ? 'style="display:none;"' : ''; ?>><i class="bi bi-printer"></i> Print Yearly</button>'''
new_year = '''<a class="btn btn-primary" id="printYearlyBtn" <?php echo $reportMode === 'monthly' ? 'style="display:none;"' : ''; ?> href="<?php echo htmlspecialchars($pdfReportUrl); ?>" target="_blank"><i class="bi bi-file-earmark-pdf"></i> PDF Yearly</a>'''

for rel in ['management/sales_records.php', 'management/expenses.php']:
    path = root / rel
    text = path.read_text()
    text = text.replace(old_month, new_month, 1)
    text = text.replace(old_year, new_year, 1)
    text = re.sub(r"\s*\$\('#printMonthlyBtn'\)\.on\('click', function\(\) \{\s*PrintManager\.print\(\);\s*\}\);", '', text)
    text = re.sub(r"\s*\$\('#printYearlyBtn'\)\.on\('click', function\(\) \{\s*PrintManager\.print\(\);\s*\}\);", '', text)
    text = re.sub(r"\s*\$\('#printMonthlyBtn, #printYearlyBtn'\)\.on\('click', function\(\) \{\s*PrintManager\.print\(\);\s*\}\);", '', text)
    path.write_text(text)


def migrate_expense(rel, label, prefix):
    path = root / rel
    text = path.read_text()

    config = "require_once(__DIR__ . '/../config.php');"
    service = "require_once(__DIR__ . '/../includes/pdf/PdfReportService.php');"
    if service not in text:
        text = text.replace(config, config + "\n" + service, 1)

    if 'pdf_report_is_requested()' not in text:
        text = text.replace('requireLogin();', 'requireLogin();\n$pdfRequested = pdf_report_is_requested();\nif ($pdfRequested) { pdf_report_begin(); }', 1)

    if '$pdfReportUrl = pdf_report_current_url();' not in text:
        text = text.replace("?>\n<!DOCTYPE html>", "$pdfReportUrl = pdf_report_current_url();\n?>\n<!DOCTYPE html>", 1)

    if '> PDF Report</a>' not in text:
        anchor = '<button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addExpenseModal">'
        link = '<a class="btn btn-outline-primary" href="<?php echo htmlspecialchars($pdfReportUrl); ?>" target="_blank"><i class="bi bi-file-earmark-pdf"></i> PDF Report</a>\n                            '
        text = text.replace(anchor, link + anchor, 1)

    if 'pdf_report_finish(' not in text:
        title = f"'{label} Expenses Record - ' . date('F Y', strtotime($yearMonth))"
        text = text.rstrip() + f"\n<?php\nif ($pdfRequested) {{\n    pdf_report_finish('{prefix}-' . $yearMonth . '.pdf', 'landscape', {title});\n}}\n?>\n"

    path.write_text(text)


migrate_expense('poultry/layer_expenses.php', 'Layer', 'layer-expenses')
migrate_expense('poultry/broiler_expenses.php', 'Broiler', 'broiler-expenses')
migrate_expense('ruminant/ruminant_expenses.php', 'Ruminant', 'ruminant-expenses')

(root / 'scripts/verify_v2243_pdf_actions.php').write_text(r'''<?php
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
''')

(root / 'DEPLOYMENT_V2.2.43.md').write_text('''# V2.2.43 — PDF Action Completion\n\n- Sales Report: PDF Monthly / PDF Yearly links fixed.\n- Expense Report: PDF Monthly / PDF Yearly links fixed.\n- Layer Expenses: PDF Report added.\n- Broiler Expenses: PDF Report added.\n- Ruminant Expenses: PDF Report added.\n- All use the centralized application PDF engine; browser URL/timestamp headers are not part of these PDFs.\n- Receivables logic unchanged.\n''')

print('V2.2.43 PDF actions prepared.')
