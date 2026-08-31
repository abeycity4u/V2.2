<?php
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

        // Scripts and icon-font tags are unnecessary for PDF output and can cause malformed glyphs.
        $html = preg_replace('#<script\b[^>]*>.*?</script>#is', '', $html) ?? $html;
        $html = preg_replace('#<i\b[^>]*>.*?</i>#is', '', $html) ?? $html;
        $html = $this->stripActionsColumns($html);

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

    private function stripActionsColumns(string $html): string
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
.card { border: 1px solid #d1d5db !important; margin-bottom: 8px !important; }
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
