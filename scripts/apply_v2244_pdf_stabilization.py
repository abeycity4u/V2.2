from pathlib import Path
import re

root = Path('.')

# Expense Report PDF mode fix.
p = root / 'management/expenses.php'
text = p.read_text()
if '$pdfRequested = pdf_report_is_requested();' not in text:
    text = text.replace('requireLogin();', "requireLogin();\n$pdfRequested = pdf_report_is_requested();\nif ($pdfRequested) { pdf_report_begin(); }", 1)
p.write_text(text)

# Reports & Analytics -> application-generated PDF.
p = root / 'management/reports.php'
text = p.read_text()
config = "require_once(__DIR__ . '/../config.php');"
service = "require_once(__DIR__ . '/../includes/pdf/PdfReportService.php');"
if service not in text:
    text = text.replace(config, config + "\n" + service, 1)
if '$pdfRequested = pdf_report_is_requested();' not in text:
    text = text.replace('requireBusinessReportAccess();', "requireBusinessReportAccess();\n$pdfRequested = pdf_report_is_requested();\nif ($pdfRequested) { pdf_report_begin(); }", 1)
if '$pdfReportUrl = pdf_report_current_url();' not in text:
    text = text.replace("?>\n<!DOCTYPE html>", "$pdfReportUrl = pdf_report_current_url();\n?>\n<!DOCTYPE html>", 1)
text = text.replace(
    '<button class="btn btn-primary" onclick="printReport()">\n                                <i class="bi bi-printer"></i> Print Report\n                            </button>',
    '<a class="btn btn-primary" href="<?php echo htmlspecialchars($pdfReportUrl); ?>" target="_blank">\n                                <i class="bi bi-file-earmark-pdf"></i> PDF Report\n                            </a>'
)
text = re.sub(r"\n\s*function printReport\(\) \{\s*PrintManager\.print\(\);\s*\}\s*", '\n', text)
text = text.replace(
    '<canvas id="profitChart" height="100"></canvas>',
    '''<?php if ($pdfRequested): ?>\n<table class="table table-bordered"><thead><tr><th>Month</th><th>Farm Type</th><th>Net Profit</th></tr></thead><tbody><?php foreach ($profitData as $row): ?><tr><td><?php echo date('M Y', strtotime($row['month'] . '-01')); ?></td><td><?php echo ucfirst($row['farm_type']); ?></td><td>₦<?php echo number_format($row['net_profit'], 2); ?></td></tr><?php endforeach; ?></tbody></table>\n<?php else: ?><canvas id="profitChart" height="100"></canvas><?php endif; ?>'''
)
text = text.replace(
    '<canvas id="productsChart"></canvas>',
    '''<?php if ($pdfRequested): ?>\n<table class="table table-bordered"><thead><tr><th>Product</th><th>Quantity</th><th>Revenue</th></tr></thead><tbody><?php foreach ($topProducts as $product): ?><tr><td><?php echo htmlspecialchars($product['product_type']); ?></td><td><?php echo number_format((float)$product['total_quantity'], 2); ?></td><td>₦<?php echo number_format((float)$product['total_revenue'], 2); ?></td></tr><?php endforeach; ?><?php if (empty($topProducts)): ?><tr><td colspan="3">No sales data for this period.</td></tr><?php endif; ?></tbody></table>\n<?php else: ?><canvas id="productsChart"></canvas><?php endif; ?>'''
)
text = text.replace(
    '<canvas id="expensesChart"></canvas>',
    '''<?php if ($pdfRequested): ?>\n<table class="table table-bordered"><thead><tr><th>Category</th><th>Total Amount</th></tr></thead><tbody><?php foreach ($expenses as $expense): ?><tr><td><?php echo ucfirst(htmlspecialchars($expense['category'])); ?></td><td>₦<?php echo number_format((float)$expense['total_amount'], 2); ?></td></tr><?php endforeach; ?><?php if (empty($expenses)): ?><tr><td colspan="2">No expense data for this period.</td></tr><?php endif; ?></tbody></table>\n<?php else: ?><canvas id="expensesChart"></canvas><?php endif; ?>'''
)
if 'pdf_report_finish(' not in text:
    text = text.rstrip() + "\n<?php\nif ($pdfRequested) {\n    pdf_report_finish('farm-reports-' . preg_replace('/[^0-9A-Za-z-]+/', '-', strtolower((string)$year)) . '.pdf', 'landscape', 'Farm Reports & Analytics - ' . $year);\n}\n?>\n"
p.write_text(text)

# Central PDF cleanup.
p = root / 'includes/pdf/PdfReportService.php'
text = p.read_text()
needle = "        // Scripts are unnecessary for PDF output and can cause malformed output.\n        $html = preg_replace('#<script\\b[^>]*>.*?</script>#is', '', $html) ?? $html;"
if 'stripActionsColumns($html)' not in text:
    replacement = """        // Scripts and icon-font tags are unnecessary for PDF output and can cause malformed glyphs.\n        $html = preg_replace('#<script\\b[^>]*>.*?</script>#is', '', $html) ?? $html;\n        $html = preg_replace('#<i\\b[^>]*>.*?</i>#is', '', $html) ?? $html;\n        $html = $this->stripActionsColumns($html);"""
    text = text.replace(needle, replacement, 1)
text = text.replace(
    ".card { border: 1px solid #d1d5db !important; margin-bottom: 8px !important; page-break-inside: avoid; }",
    ".card { border: 1px solid #d1d5db !important; margin-bottom: 8px !important; }"
)
if 'private function stripActionsColumns' not in text:
    method = r'''    private function stripActionsColumns(string $html): string
    {
        if (!class_exists('DOMDocument')) return $html;
        $dom = new DOMDocument('1.0', 'UTF-8');
        $previous = libxml_use_internal_errors(true);
        $loaded = $dom->loadHTML('<?xml encoding="UTF-8">' . $html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
        if (!$loaded) return $html;

        foreach (iterator_to_array($dom->getElementsByTagName('table')) as $table) {
            $rows = $table->getElementsByTagName('tr');
            $indexes = [];
            if ($rows->length > 0) {
                $headers = $rows->item(0)->getElementsByTagName('th');
                foreach ($headers as $i => $th) {
                    if (strcasecmp(trim($th->textContent), 'Actions') === 0) $indexes[] = $i;
                }
            }
            rsort($indexes);
            foreach ($indexes as $index) {
                foreach (iterator_to_array($rows) as $row) {
                    $cells = [];
                    foreach ($row->childNodes as $child) {
                        if ($child instanceof DOMElement && in_array(strtolower($child->tagName), ['th', 'td'], true)) $cells[] = $child;
                    }
                    if (isset($cells[$index])) $row->removeChild($cells[$index]);
                }
            }
        }
        return $dom->saveHTML() ?: $html;
    }

'''
    text = text.replace("    private function documentCss(string $orientation): string\n    {", method + "    private function documentCss(string $orientation): string\n    {", 1)
p.write_text(text)

# Consolidate deployment docs.
def version_key(path: Path):
    nums = re.findall(r'\d+', path.stem)
    return tuple(int(n) for n in nums)

deployment_files = sorted(root.glob('DEPLOYMENT_V2.2.*.md'), key=version_key)
base_path = root / 'DEPLOYMENT.md'
base = base_path.read_text() if base_path.exists() else ''
parts = [
    '# Renee Farms V2.2 Deployment History', '',
    'This is the single deployment-history document for V2.2. New release notes must be appended here instead of creating additional `DEPLOYMENT_V2.2.xx.md` files.', '',
    '## Base Deployment Notes', '', base.strip()
]
for file in deployment_files:
    parts += ['', '---', '', f'## Archived from `{file.name}`', '', file.read_text().strip()]
parts += ['', '---', '', '## V2.2.44 — PDF Export Stabilization & Documentation Cleanup', '',
          '- Fixed Expense Report PDF mode so `?pdf=1` returns a PDF instead of the HTML report page.',
          '- Migrated Farm Reports & Analytics from browser Print Report to application-generated PDF Report.',
          '- Analytics PDF uses server-rendered tables instead of Chart.js canvases.',
          '- Central PDF service strips icon-font tags and empty Actions columns.',
          '- Relaxed generic card page-break rules to reduce blank/orphan pages.',
          '- Consolidated version-specific deployment Markdown files into this single `DEPLOYMENT.md`.',
          '- Receivables/accounting logic unchanged.', '']
base_path.write_text('\n'.join(parts))
for file in deployment_files:
    file.unlink()

# Remove one-time migration workflows that should not keep triggering.
for wf in [
    '.github/workflows/apply-v2243-pdf-actions.yml',
    '.github/workflows/apply-v2243-pdf-actions-final.yml',
    '.github/workflows/run-v2243-pdf-actions.yml',
    '.github/workflows/apply-v2244-pdf-stabilization.yml',
]:
    q = root / wf
    if q.exists(): q.unlink()

(root / 'scripts/verify_v2244_pdf_stabilization.php').write_text(r'''<?php
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
''')

print('V2.2.44 migration prepared.')
