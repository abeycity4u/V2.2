# V2.2.42 — Centralized Application PDF Report Engine

## Why
Browser-native printing adds browser-controlled URL/title/date/page headers and caused inconsistent pagination across reports. Official reports now begin migration to application-generated PDFs.

## Central architecture
- `includes/pdf/PdfReportService.php` is the single PDF service.
- `dompdf/dompdf` is managed through Composer.
- The service controls A4 orientation, margins, typography, table layout, page numbering and footer branding.
- Browser URL headers and browser timestamps are not part of generated PDFs.
- Existing report pages remain the source of report data; PDF mode captures the same server-rendered report, avoiding duplicate accounting/report SQL.

## Migrated in V2.2.42
- Sales Report — Monthly/Yearly PDF
- Expense Report — Monthly/Yearly PDF
- Poultry & Ruminant Report — PDF Report

The previously non-clickable Poultry & Ruminant Print Report action is replaced by a direct PDF link, so it no longer depends on the browser print JavaScript handler.

## Transitional scope
The V2.2.41 browser print manager remains available as fallback for pages not yet migrated. Debt History and Reports & Analytics are intentionally not switched in this first batch. Reports & Analytics requires chart-specific PDF handling rather than silently dropping or oversizing Chart.js canvases.

## Deployment
`vendor/` is committed by the release workflow so shared-hosting deployment does not require Composer on the production server.

## Verification
Run:

```bash
php scripts/verify_v2242_pdf_engine.php
php scripts/verify_v2240_receivable_payment_overpayment_protection.php
```
