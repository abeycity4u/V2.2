# V2.2.41 Platform Print Architecture Audit

This audit was generated before centralization by scanning application PHP/JS/CSS for native printing, print buttons, print CSS and `no-print` usage.

## Pre-centralization print touchpoints

### `assets/css/dashboard.css`
- L242: `/* Print Styles */`
- L243: `@media print {`
- L244: `.no-print {`

### `assets/css/style.css`
- L413: `/* Print Styles */`
- L414: `@media print {`
- L415: `.no-print {`
- L744: `@media print {`

### `assets/js/main.js`
- L621: `* Print report`
- L628: `window.print();`

### `management/expenses.php`
- L136: `<button class="btn btn-primary" id="printMonthlyBtn" <?php echo $reportMode === 'yearly' ? 'style="display:none;"' : ''; ?>><i class="bi bi-printer"></i> Print Monthly</button>`
- L137: `<button class="btn btn-primary" id="printYearlyBtn" <?php echo $reportMode === 'monthly' ? 'style="display:none;"' : ''; ?>><i class="bi bi-printer"></i> Print Yearly</button>`
- L443: `window.print();`

### `management/poultry_ruminant_report.php`
- L176: `<button class="btn btn-primary" id="printBtn"><i class="bi bi-printer"></i> Print Report</button>`
- L230: `$('#printBtn').on('click', () => window.print());`

### `management/reports.php`
- L297: `<i class="bi bi-printer"></i> Print Report`
- L471: `window.print();`

### `management/sales_records.php`
- L580: `<button class="btn btn-primary" id="printMonthlyBtn" <?php echo $reportMode === 'yearly' ? 'style="display:none;"' : ''; ?>><i class="bi bi-printer"></i> Print Monthly</button>`
- L581: `<button class="btn btn-primary" id="printYearlyBtn" <?php echo $reportMode === 'monthly' ? 'style="display:none;"' : ''; ?>><i class="bi bi-printer"></i> Print Yearly</button>`
- L665: `<div class="d-flex gap-2 no-print">`
- L681: `<i class="bi bi-printer me-1"></i>Print Debt History`
- L724: `<th class="no-print">Actions</th>`
- L759: `<td class="no-print">`
- L1339: `window.print();`
- L1343: `window.print();`
- L1353: `window.print();`

### `navbar.php`
- L33: `<?php if ($subscriptionNotice): ?><div class="alert alert-warning rounded-0 mb-0 text-center no-print"><?php echo htmlspecialchars($subscriptionNotice); ?></div><?php endif; ?>`
- L34: `<nav id="appNavbar" class="navbar navbar-expand-lg navbar-dark bg-success shadow-sm no-print">`

## Centralized architecture

- `assets/css/print.css` owns print layout, table wrapping, page breaks, print colors and hidden interactive UI.
- `assets/js/print-manager.js` owns print invocation, automatic portrait/landscape selection and automatic Actions-column suppression.
- `navbar_head.php` loads both once for all application pages.
- Direct `window.print()` calls are migrated to `PrintManager.print()`.
- Pages can override orientation with `data-print-orientation="portrait|landscape"`; otherwise the manager chooses landscape for wide tables.
- `data-print-keep="header-with-first-row"` and `.print-keep-together` provide reusable page-break semantics.

## Files migrated from native print calls
- `assets/js/main.js`
- `management/expenses.php`
- `management/poultry_ruminant_report.php`
- `management/reports.php`
- `management/sales_records.php`

## Post-centralization consolidation

- Legacy print blocks were removed from both `assets/css/style.css` and `assets/css/dashboard.css`.
- `assets/css/print.css` is now the only application-owned `@media print` source.
- Runtime page orientation is owned by `assets/js/print-manager.js`.
