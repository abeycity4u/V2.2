from pathlib import Path
import re

root = Path('.')

composer = '''{
  "name": "renee-farms/platform-v2",
  "description": "Renee Farms V2.2 farm management platform",
  "type": "project",
  "require": {
    "dompdf/dompdf": "^3.0"
  },
  "config": {
    "optimize-autoloader": true,
    "sort-packages": true
  }
}
'''
Path('composer.json').write_text(composer)

service = r'''<?php
/**
 * Renee Farms centralized application-generated PDF service.
 *
 * V2.2.42 replaces browser-native printing for official reports so exported
 * PDFs do not inherit browser URLs, timestamps, or browser page headers.
 */

use Dompdf\Dompdf;
use Dompdf\Options;

function pdf_report_is_requested(): bool
{
    return isset($_GET['pdf']) && (string) $_GET['pdf'] === '1';
}

function pdf_report_begin(): void
{
    if (ob_get_level() === 0) {
        ob_start();
    } else {
        ob_start();
    }
}

function pdf_report_current_url(): string
{
    $params = $_GET;
    $params['pdf'] = '1';
    $path = basename((string)($_SERVER['PHP_SELF'] ?? 'report.php'));
    return $path . '?' . http_build_query($params);
}

final class PdfReportService
{
    private string $root;

    public function __construct(?string $root = null)
    {
        $this->root = $root ?: dirname(__DIR__, 2);
        $autoload = $this->root . '/vendor/autoload.php';
        if (!is_file($autoload)) {
            throw new RuntimeException('PDF engine is not installed. Deploy vendor/ or run composer install.');
        }
        require_once $autoload;
    }

    public function renderHtml(string $html, string $orientation = 'portrait', string $title = 'Renee Farms Report'): string
    {
        $orientation = strtolower($orientation) === 'landscape' ? 'landscape' : 'portrait';

        // Scripts are unnecessary for PDF output and can cause malformed output.
        $html = preg_replace('#<script\b[^>]*>.*?</script>#is', '', $html) ?? $html;

        $style = $this->documentCss($orientation);
        if (stripos($html, '</head>') !== false) {
            $html = preg_replace('#</head>#i', '<meta charset="UTF-8"><style>' . $style . '</style></head>', $html, 1) ?? $html;
        } else {
            $html = '<!doctype html><html><head><meta charset="UTF-8"><style>' . $style . '</style></head><body>' . $html . '</body></html>';
        }

        $options = new Options();
        $options->set('isRemoteEnabled', true);
        $options->set('isHtml5ParserEnabled', true);
        $options->set('isFontSubsettingEnabled', true);
        $options->set('defaultFont', 'DejaVu Sans');
        $options->set('chroot', $this->root);
        $options->set('defaultMediaType', 'print');
        $options->set('dpi', 96);

        $dompdf = new Dompdf($options);
        $dompdf->setPaper('A4', $orientation);
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->render();

        $canvas = $dompdf->getCanvas();
        $font = $dompdf->getFontMetrics()->getFont('DejaVu Sans', 'normal');
        $canvas->page_text(28, $canvas->get_height() - 22, 'Renee Farms • ' . $title, $font, 7, [0.25, 0.25, 0.25]);
        $canvas->page_text($canvas->get_width() - 110, $canvas->get_height() - 22, 'Page {PAGE_NUM} of {PAGE_COUNT}', $font, 7, [0.25, 0.25, 0.25]);

        return $dompdf->output();
    }

    public function streamHtml(string $html, string $filename, string $orientation = 'portrait', string $title = 'Renee Farms Report'): never
    {
        $pdf = $this->renderHtml($html, $orientation, $title);
        $safe = preg_replace('/[^A-Za-z0-9._-]+/', '-', $filename) ?: 'renee-farms-report.pdf';
        if (!str_ends_with(strtolower($safe), '.pdf')) {
            $safe .= '.pdf';
        }
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        header('Content-Type: application/pdf');
        header('Content-Disposition: inline; filename="' . $safe . '"');
        header('Content-Length: ' . strlen($pdf));
        header('Cache-Control: private, max-age=0, must-revalidate');
        echo $pdf;
        exit;
    }

    private function documentCss(string $orientation): string
    {
        return <<<CSS
@page { size: A4 {$orientation}; margin: 10mm 9mm 14mm; }
html, body { background: #fff !important; color: #1f2937 !important; font-family: 'DejaVu Sans', sans-serif !important; font-size: 9pt; }
body { margin: 0 !important; padding: 0 !important; }
nav, .navbar, #appNavbar, .no-print, .report-controls, button, .btn, .modal, .offcanvas,
.dataTables_length, .dataTables_filter, .dataTables_paginate, .dataTables_info, .dt-buttons, .pagination,
.form-select, .form-control, .input-group, .print-action-column { display: none !important; }
.container, .container-fluid { width: 100% !important; max-width: none !important; margin: 0 !important; padding: 0 !important; }
.card, .card-body, .card-header, .bg-light, .bg-dark, .bg-primary, .bg-success, .bg-danger, .bg-warning, .bg-info {
  background: #fff !important; color: #1f2937 !important; box-shadow: none !important; border-color: #d1d5db !important;
}
.card { border: 1px solid #d1d5db !important; margin-bottom: 8px !important; page-break-inside: avoid; }
.card-header { border-bottom: 1px solid #d1d5db !important; padding: 7px 9px !important; }
.card-body { padding: 8px 9px !important; }
h1, h2, h3, h4, h5, h6 { color: #111827 !important; margin-top: 0; }
.text-success, .text-info { color: #087f5b !important; }
.text-danger { color: #b42318 !important; }
.table-responsive { overflow: visible !important; width: 100% !important; }
table, table.table { width: 100% !important; max-width: 100% !important; border-collapse: collapse !important; table-layout: auto; font-size: 8pt !important; }
thead { display: table-header-group; }
tr { page-break-inside: avoid; }
th, td, .table th, .table td { padding: 4px 5px !important; white-space: normal !important; overflow-wrap: anywhere !important; border: 1px solid #d1d5db !important; vertical-align: top !important; }
th, .table th, .table-dark th { background: #137a42 !important; color: #fff !important; font-weight: 700 !important; }
.badge { background: #eef2f7 !important; color: #1f2937 !important; border: 1px solid #cbd5e1 !important; }
.progress { border: 1px solid #d1d5db !important; background: #f3f4f6 !important; }
.progress-bar { background: #137a42 !important; }
canvas { max-width: 100% !important; max-height: 240px !important; }
a { color: inherit !important; text-decoration: none !important; }
[data-print-keep='together'], .print-keep-together { page-break-inside: avoid !important; }
CSS;
    }
}

function pdf_report_finish(string $filename, string $orientation = 'portrait', string $title = 'Renee Farms Report'): never
{
    $html = ob_get_clean();
    if ($html === false) {
        $html = '';
    }
    try {
        $service = new PdfReportService();
        $service->streamHtml($html, $filename, $orientation, $title);
    } catch (Throwable $e) {
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        http_response_code(503);
        header('Content-Type: text/plain; charset=UTF-8');
        echo 'PDF generation unavailable: ' . $e->getMessage();
        exit;
    }
}
'''
Path('includes/pdf').mkdir(parents=True, exist_ok=True)
Path('includes/pdf/PdfReportService.php').write_text(service)


def migrate_page(path, label, filename_expr, orientation='landscape'):
    p = Path(path)
    text = p.read_text()
    if 'pdf_report_is_requested()' not in text:
        anchor = "requireBusinessReportAccess();"
        insert = anchor + "\n$pdfRequested = pdf_report_is_requested();\nif ($pdfRequested) {\n    pdf_report_begin();\n}\n"
        # service must be loaded before helper calls
        require_line = "require_once(__DIR__ . '/../config.php');"
        text = text.replace(require_line, require_line + "\nrequire_once(__DIR__ . '/../includes/pdf/PdfReportService.php');", 1)
        text = text.replace(anchor, insert, 1)

    # Add reusable PDF URL after report variables have been established, before HTML starts.
    marker = "?>\n<!DOCTYPE html>"
    if '$pdfReportUrl = pdf_report_current_url();' not in text:
        text = text.replace(marker, "$pdfReportUrl = pdf_report_current_url();\n?>\n<!DOCTYPE html>", 1)

    # Change print labels/buttons to anchors. Preserve ids for existing show/hide logic.
    text = re.sub(r'<button class="btn btn-primary" id="printMonthlyBtn"([^>]*)><i class="bi bi-printer"></i> Print Monthly</button>',
                  r'<a class="btn btn-primary" id="printMonthlyBtn"\1 href="<?php echo htmlspecialchars($pdfReportUrl); ?>" target="_blank"><i class="bi bi-file-earmark-pdf"></i> PDF Monthly</a>', text)
    text = re.sub(r'<button class="btn btn-primary" id="printYearlyBtn"([^>]*)><i class="bi bi-printer"></i> Print Yearly</button>',
                  r'<a class="btn btn-primary" id="printYearlyBtn"\1 href="<?php echo htmlspecialchars($pdfReportUrl); ?>" target="_blank"><i class="bi bi-file-earmark-pdf"></i> PDF Yearly</a>', text)
    text = re.sub(r'<button class="btn btn-primary" id="printBtn"><i class="bi bi-printer"></i> Print Report</button>',
                  r'<a class="btn btn-primary" id="printBtn" href="<?php echo htmlspecialchars($pdfReportUrl); ?>" target="_blank"><i class="bi bi-file-earmark-pdf"></i> PDF Report</a>', text)

    # Remove migrated browser print click handlers, including the broken Poultry/Ruminant handler.
    text = re.sub(r"\s*\$\('#printMonthlyBtn'\)\.on\('click', function\(\) \{\s*PrintManager\.print\(\);\s*\}\);", '', text)
    text = re.sub(r"\s*\$\('#printYearlyBtn'\)\.on\('click', function\(\) \{\s*PrintManager\.print\(\);\s*\}\);", '', text)
    text = re.sub(r"\s*\$\('#printMonthlyBtn, #printYearlyBtn'\)\.on\('click', function\(\) \{\s*PrintManager\.print\(\);\s*\}\);", '', text)
    text = re.sub(r"\s*\$\('#printBtn'\)\.on\('click', \(\) => PrintManager\.print\(\)\);", '', text)

    # Debt History stays browser-print for now; only official full report is migrated.

    finish = f"\n<?php\nif ($pdfRequested) {{\n    pdf_report_finish({filename_expr}, '{orientation}', {label});\n}}\n?>\n"
    if 'pdf_report_finish(' not in text:
        text = text.rstrip() + finish
    p.write_text(text)


migrate_page('management/sales_records.php', "'Sales Report - ' . $periodLabel", "'sales-report-' . preg_replace('/[^0-9A-Za-z-]+/', '-', strtolower($periodLabel)) . '.pdf'", 'landscape')
migrate_page('management/expenses.php', "'Expense Report - ' . $periodLabel", "'expense-report-' . preg_replace('/[^0-9A-Za-z-]+/', '-', strtolower($periodLabel)) . '.pdf'", 'landscape')
migrate_page('management/poultry_ruminant_report.php', "'Poultry & Ruminant Report - ' . $periodLabel", "'poultry-ruminant-report-' . preg_replace('/[^0-9A-Za-z-]+/', '-', strtolower($periodLabel)) . '.pdf'", 'landscape')

verify = r'''<?php
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
'''
Path('scripts/verify_v2242_pdf_engine.php').write_text(verify)

Path('DEPLOYMENT_V2.2.42.md').write_text('''# V2.2.42 — Centralized Application PDF Report Engine\n\n## Why\nBrowser-native printing adds browser-controlled URL/title/date/page headers and caused inconsistent pagination across reports. Official reports now begin migration to application-generated PDFs.\n\n## Central architecture\n- `includes/pdf/PdfReportService.php` is the single PDF service.\n- `dompdf/dompdf` is managed through Composer.\n- The service controls A4 orientation, margins, typography, table layout, page numbering and footer branding.\n- Browser URL headers and browser timestamps are not part of generated PDFs.\n- Existing report pages remain the source of report data; PDF mode captures the same server-rendered report, avoiding duplicate accounting/report SQL.\n\n## Migrated in V2.2.42\n- Sales Report — Monthly/Yearly PDF\n- Expense Report — Monthly/Yearly PDF\n- Poultry & Ruminant Report — PDF Report\n\nThe previously non-clickable Poultry & Ruminant Print Report action is replaced by a direct PDF link, so it no longer depends on the browser print JavaScript handler.\n\n## Transitional scope\nThe V2.2.41 browser print manager remains available as fallback for pages not yet migrated. Debt History and Reports & Analytics are intentionally not switched in this first batch. Reports & Analytics requires chart-specific PDF handling rather than silently dropping or oversizing Chart.js canvases.\n\n## Deployment\n`vendor/` is committed by the release workflow so shared-hosting deployment does not require Composer on the production server.\n\n## Verification\nRun:\n\n```bash\nphp scripts/verify_v2242_pdf_engine.php\nphp scripts/verify_v2240_receivable_payment_overpayment_protection.php\n```\n''')

changelog = Path('V2_CHANGELOG.md')
if changelog.exists():
    txt = changelog.read_text()
    entry = '\n## V2.2.42 — Centralized Application PDF Report Engine\n- Added centralized Dompdf-based PDF service.\n- Migrated Sales, Expense, and Poultry & Ruminant reports from browser print to application-generated PDFs.\n- Removed browser URL/header/footer dependency from migrated official reports.\n- Replaced the broken Poultry & Ruminant Print Report action with a direct PDF report action.\n- Preserved V2.2.40 receivable protections and existing report query logic.\n'
    if '## V2.2.42' not in txt:
        changelog.write_text(txt.rstrip() + '\n' + entry)

print('V2.2.42 PDF engine migration prepared.')
