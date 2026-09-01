# Renee Farms V2.2 Deployment History

This is the single deployment-history document for V2.2. New release notes must be appended here instead of creating additional `DEPLOYMENT_V2.2.xx.md` files.

## Base Deployment Notes

# V2.2.3 Deployment — Integrated Daily Feed Consumption

1. Deploy the application files to the V2.2 test environment.
2. Ensure V2.2.1 migration `014_v22_financial_foundation.sql` has already been applied.
3. Apply `migrations/015_daily_feed_integration.sql` once.
4. Run:
   `php scripts/verify_v223_daily_feed_integration.php`
5. Test Layer, Broiler, and Ruminant Daily Record feed selection and saving.
6. Verify stock decreases by the entered quantity.
7. Edit the daily record with a different feed/quantity and verify the old stock effect is restored and the new effect applied.
8. Delete a test daily record and verify the linked stock quantity is restored.
9. Confirm the dedicated Layer/Broiler/Ruminant Feed Records pages continue to work independently.

Do not deploy over the frozen V2.1 stable environment.

## V2.2.11 Stock Ledger Integrity

After uploading the V2.2.11 package:

1. Run the normal migration runner:
   `php scripts/run_migrations.php`
2. Confirm the new migration was applied:
   `migrations/016_stock_ledger_integrity.sql`
3. Run the contract audit:
   `php scripts/verify_v2211_stock_ledger_integrity.php`
4. Run the read-only live reconciliation report:
   `php scripts/reconcile_stock_ledger.php`
   or for one farm:
   `php scripts/reconcile_stock_ledger.php --farm-id=3`
5. For CI/deployment gating, use:
   `php scripts/reconcile_stock_ledger.php --strict`

**Do not manually repair a mismatch by changing `stock_items.current_stock` first.**
A mismatch means the live balance and the append-only ledger disagree and must be
investigated from the transaction chain before any correction is made.


### V2.2.12 stock ledger hardening
Run migrations normally. Migration 017 creates a single read-only audit starting point for legacy stock items that have a positive current balance but no ledger rows. It does not alter current stock and does not repair ambiguous mismatches. After migration, run `php scripts/reconcile_stock_ledger.php --strict`.

---

## Archived from `DEPLOYMENT_V2.2.10.md`

# V2.2.10 deployment

1. Upload/extract the V2.2.10 ZIP over the existing `/v2` application.
2. Run the migration:
   `migrations/015_feed_reversal_audit.sql`
3. Verify:
   `php scripts/verify_v2210_feed_reversal_integrity.php`
4. Run the normal PHP syntax/contract checks.

No manual data repair is performed by this migration. Existing V2.2.9 test rows that already show an inflated restoration (for example Chikun `100 -> 115`) are historical data and should be corrected separately after confirming the affected test transaction/source record. The new append-only reversal model prevents new inflation from being created.

---

## Archived from `DEPLOYMENT_V2.2.11.md`

# V2.2.11 — Stock Ledger Integrity Deployment

## 1. Upload

Replace the application files with this package while preserving the existing database.

## 2. Run migrations

From the `/v2` directory:

```bash
php scripts/run_migrations.php
```

This applies:

```text
migrations/016_stock_ledger_integrity.sql
```

## 3. Run application contracts

```bash
php scripts/verify_v2211_stock_ledger_integrity.php
```

The full existing test suite should also be run:

```bash
php tests/contract_checks.php
php tests/daily_feed_category_contract.php
php tests/daily_feed_sync_signature_contract.php
php tests/frontend_asset_contract.php
php tests/safe_exception_helper_contract.php
php tests/v22_financial_checks.php
php tests/v2_contract_checks.php
php scripts/verify_v228_feed_ledger_consistency.php
php scripts/verify_v2210_feed_reversal_integrity.php
```

## 4. Reconcile live stock

Read-only report:

```bash
php scripts/reconcile_stock_ledger.php
```

One farm only:

```bash
php scripts/reconcile_stock_ledger.php --farm-id=3
```

CI/deployment gate:

```bash
php scripts/reconcile_stock_ledger.php --strict
```

A mismatch is intentionally **not auto-repaired**. Investigate the affected
item's ledger chain first.

## 5. What changed

- Daily Records, Feed Records, Inventory and stock API now share one stock mutation service.
- Posted stock movements are append-only.
- Edit/delete corrections create linked reversal movements.
- Reversed originals remain visible in audit tables but never count toward active stock.
- Reversal rows use the actual correction/posting date while preserving the original transaction's effective date.
- Dashboard, Stock History, charts and financial feed costing use the active ledger semantics.
- Inventory cleanup no longer deletes stock transaction history.
- Stock History reports whether `stock_items.current_stock` reconciles with the active transaction ledger.

---

## Archived from `DEPLOYMENT_V2.2.14.md`

# V2.2.14 — Inventory Bootstrap & Effective Feed Summary

No database migration is required.

## Fixes
- `inventory.php` now explicitly loads `lib/stock_service.php`, fixing `Call to undefined function stock_apply_movement()` when adding or updating inventory.
- Layer, Broiler and Ruminant feed summary cards now use effective business movements: reversed originals and their linked reversal/restoration rows remain visible in Monthly Transactions but do not inflate Received/Used totals.
- Net Change is calculated from the same effective movement set.

## Verify
```bash
php scripts/verify_v2214_inventory_reporting.php
php scripts/reconcile_stock_ledger.php --strict
```

---

## Archived from `DEPLOYMENT_V2.2.16.md`

# Farm Platform V2.2.16 — Historical Cost Integrity

No new migration is required.

After deployment run:

```bash
php scripts/verify_v2216_historical_cost_integrity.php
php scripts/verify_v2215_reporting_ui_consistency.php
php scripts/reconcile_stock_ledger.php --strict
```

Expected: all verifier checks pass and stock reconciliation reports zero mismatches.

---

## Archived from `DEPLOYMENT_V2.2.17.md`

# V2.2.17 — Attribution & Cost Centre Foundation

This release adds a durable attribution hierarchy: **Farm Type → Production Type → Production Cycle**.

Key rules:
- `General` remains a first-class sales/reporting scope for waste, by-products and other farm income.
- Poultry production types: Layer, Broiler, Shared/Unallocated.
- Ruminant production types: Cattle, Goat, Sheep, Other, Shared/Unallocated.
- A transaction may be assigned to one cycle or intentionally remain pooled at production-type level.
- Pooled activity is never guessed into a cycle. Future explicit allocations are supported by `sales_allocations`; expense allocations continue to use `financial_allocations`.
- Feed movements snapshot production ownership and feed ledgers show Production Type + Cycle.
- Profitability supports Daily/Monthly filtering by Farm Type, Production Type and Cycle.

Deploy code, then run:

```bash
php scripts/run_migrations.php
php scripts/verify_v2217_attribution_cost_centres.php
php scripts/verify_v2216_historical_cost_integrity.php
php scripts/verify_v2215_reporting_ui_consistency.php
php scripts/reconcile_stock_ledger.php --strict
```

---

## Archived from `DEPLOYMENT_V2.2.19.md`

# V2.2.19 — Pooled Revenue Allocation & Sale Edit Stability

## What changed
- Fixes Edit Sale attribution controls so Production Type and matching Cycle always repopulate.
- Pooled Poultry → Layer egg sales are automatically allocated across Layer cycles that recorded output on the sale date.
- Allocation uses `crates_count` when available, otherwise `egg_production` as the proportional basis.
- Pooled revenue is **not** split equally among merely-active cycles.
- Original sale remains one source record; `sales_allocations` contains reporting ownership only, preventing double counting.
- Sales ledger shows Direct / Allocated / Partial / Unallocated status.
- Cycle Profitability includes explicit allocations and warns when matching pooled revenue remains unallocated.
- Adds a safe, idempotent backfill command for existing pooled Layer egg sales.

## Database
No new migration. V2.2.19 uses the `sales_allocations` table introduced by migration 018.

## Deploy / verify
```bash
php scripts/run_migrations.php
php scripts/backfill_pooled_sales_allocations.php
php scripts/verify_v2219_pooled_revenue_allocation.php
php scripts/verify_v2218_dependent_attribution_filters.php
php scripts/verify_v2217_attribution_cost_centres.php
php scripts/reconcile_stock_ledger.php --strict
```

The backfill only auto-allocates pooled Layer egg sales where cycle-level Layer Daily Record production exists for the sale date. Other pooled sales stay explicitly unallocated rather than using a guessed split.

---

## Archived from `DEPLOYMENT_V2.2.21.md`

# Farm Platform V2.2.21 — Effective-Date & UI Consistency

This stabilization release closes several integrity and UX gaps found during live QA.

- Stock reversals/restorations now inherit the original transaction business date; `created_at` still records when the correction was posted.
- Layer, Broiler and Ruminant Edit Expense forms now mirror Add Expense attribution fields.
- Ruminant Edit Expense supports Production Type -> matching Production Cycle dependency.
- Inactive inventory items with stock history are protected as archived audit records instead of presenting a permanent-delete action that must fail.
- Inventory `Feed Type` label is renamed to `Usage Classification` to cover feed and non-feed stock without colliding with Production Type terminology.
- Success notifications auto-dismiss sooner (3.5 seconds); info/warning notifications remain slightly longer.

No database migration is required.

---

## Archived from `DEPLOYMENT_V2.2.22.md`

# Farm Platform V2.2.22 — Inventory & Expense Cleanup

## Scope

This stabilization release closes the live-QA gaps found after V2.2.21:

- setup-only inventory items can be removed without being trapped behind Protected history;
- real operational stock history remains append-only and protected;
- inventory category deletion no longer throws HTTP 500 when a category is still in use;
- Inventory Ledger deletion uses the platform confirmation UI;
- success notifications auto-dismiss in about 2.5 seconds;
- Layer/Broiler Add and Edit Expense forms show the same controlled Production Type field;
- Ruminant Edit Expense keeps its editable Production Type -> Production Cycle dependency;
- Usage Classification remains the inventory label for feed/non-feed usage.

No database migration is required.

## Deploy

Upload the V2.2.22 files over the current V2.2 installation, then run:

```bash
php scripts/verify_v2222_inventory_expense_cleanup.php
php scripts/verify_v2221_effective_date_ui_consistency.php
php scripts/verify_v2220_layer_egg_inventory.php
php scripts/verify_v2216_historical_cost_integrity.php
php scripts/reconcile_stock_ledger.php --strict
```

Expected V2.2.22 result:

```text
V2.2.22 verification passed: 11 check(s).
```

Strict reconciliation should report 0 mismatches.

## Inventory deletion semantics

- No stock history: item can be permanently deleted.
- Setup-only opening transaction (`Initial stock entry`): item and that setup transaction can be permanently deleted together.
- Any real operational stock movement: item is archived instead, preserving its audit history.

An inactive setup-only item created under an earlier release can also be permanently removed from the Inactive Items section.

## Category deletion semantics

A category assigned to one or more inventory items cannot be deleted. The Manage Categories UI disables deletion for categories in use, and the backend also returns a friendly warning rather than an unhandled RuntimeException/HTTP 500.

---

## Archived from `DEPLOYMENT_V2.2.25.md`

# V2.2.25 — Dark Mode Visual & Accessibility Hardening

No database migration is required.

This release hardens the shared theme layer so page-local light surfaces cannot combine with dark-theme light/muted text. It adds semantic surface/text tokens and explicit dark treatment for dashboard operational cards, Smart Stock Control, Quick Actions, production tiles, forms, DataTables, modals, dropdowns, pagination, subtle status badges and legacy inline-white surfaces.

Run after deployment:

```bash
php scripts/verify_v2225_dark_mode_accessibility.php
php scripts/verify_v2224_reporting_theme_hardening.php
php scripts/verify_v2223_ui_archive_audit_theme.php
php scripts/reconcile_stock_ledger.php --strict
```

---

## Archived from `DEPLOYMENT_V2.2.26.md`

# Farm Platform V2.2.26 — Dark Theme Consistency Audit

V2.2.26 is a visual/accessibility stabilization release built on V2.2.25.

## What changed
- Platform-wide dark-mode surface/foreground pairing hardened after a 35-screen visual QA audit.
- Dashboard Inventory Command Center stock rows, quick actions, intelligence cards, score ring, production cards and activity cards receive explicit readable dark-theme colors.
- Daily record calendars, DataTables/striped tables, forms, selects/options, modals, helper text, disabled/read-only states, alerts, utility buttons and report controls receive consistent dark-theme treatment.
- No accounting, stock-ledger, profitability, attribution, sales allocation or database logic changed.

## Deployment
No migration is required.

Run:
```bash
php scripts/verify_v2226_dark_theme_consistency.php
php scripts/verify_v2225_dark_mode_accessibility.php
php scripts/verify_v2224_reporting_theme_hardening.php
php scripts/reconcile_stock_ledger.php --strict
```

---

## Archived from `DEPLOYMENT_V2.2.27.md`

# Farm Platform V2.2.27 — Asset Cache & Theme Reload Hardening

## Purpose
V2.2.26 CSS was correct in the package, but `versioned_asset()` still emitted the historical fixed `?v=2024.06.01`. Browsers therefore could continue serving an older `theme.css`, producing white dashboard rows with dark-mode light text.

## Changes
- Local CSS/JS assets now use their file modification time as the cache-busting version.
- Release fallback version is 2.2.27.
- Critical Dashboard Inventory Command Center and Smart Quick Action dark selectors support both `data-theme=dark` and Bootstrap `data-bs-theme=dark`.
- No database or business-logic change.

## Deployment
No migration is required. Upload/replace the application files, then refresh once. The browser should request a new theme.css URL automatically.

## Verify
```bash
php scripts/verify_v2227_asset_cache_theme_reload.php
php scripts/verify_v2226_dark_theme_consistency.php
php scripts/verify_v2225_dark_mode_accessibility.php
php scripts/reconcile_stock_ledger.php --strict
```

---

## Archived from `DEPLOYMENT_V2.2.28.md`

# V2.2.28 — Production Theme Contrast Completion

UI-only dark-mode hardening for Poultry and Ruminant modules.

- Overrides light `!important` production-panel gradients in dark mode.
- Repairs faded Layer/Broiler/Ruminant Daily, Feed and Expense module surfaces.
- Repairs Ruminant Animal Profile light-body/dark-text conflict.
- Repairs All / Legacy calendar date and record-count contrast.
- No database migration or business-logic changes.

---

## Archived from `DEPLOYMENT_V2.2.29.md`

# V2.2.29 — Final Theme Contrast Cleanup

This release completes the remaining dark-mode contrast issues reported after V2.2.28.

- Production intelligence strips now use a dark surface with explicit readable heading/helper/icon colours across Layer, Broiler and Ruminant Daily, Feed and Expense pages.
- Inventory Ledger's sticky Actions column no longer retains the light-mode white background in dark mode.
- Both `data-theme="dark"` and Bootstrap `data-bs-theme="dark"` are supported.
- No database or business-logic changes.

---

## Archived from `DEPLOYMENT_V2.2.30.md`

# V2.2.30 — Inventory Health Contrast Completion

UI-only corrective release.

- Fixes the inventory health score percentage becoming unreadable in dark mode.
- Replaces the score ring's hard-coded white center with the active dark surface token.
- Forces the score value to use the strong dark-theme foreground color.
- Supports both `data-theme="dark"` and Bootstrap `data-bs-theme="dark"`.
- No database migration required.

---

## Archived from `DEPLOYMENT_V2.2.31.md`

# V2.2.31 — Permission & Administration Hardening

This release starts from the V2.2.30 stable theme checkpoint and addresses the Farm A Admin audit findings.

## Before browser testing

Run the migration first:

```bash
php scripts/run_migrations.php
```

Then run:

```bash
php scripts/verify_v2231_permission_admin_hardening.php
php tests/frontend_asset_contract.php
```

## What changed

- Fixed Broiler Feed **Operational View / Full Audit** switching by applying the same row classification used by Layer Feed.
- Tenant-scoped the role/module permission matrix with `permissions.farm_id`.
- Permission and role changes are re-read from the database on the user's next request, so refresh/navigation is enough; logout/login is not required.
- Farm Admin access label now describes enabled farm modules instead of becoming `Sales` merely because sales access exists.
- Sales users can be granted Inventory access through the permission matrix. Inventory management remains separately controlled by Add Item / Update Stock permissions.
- Expense Report now respects the configurable `expenses` permission for specialist users; Farm Admin and Platform Owner retain access.
- `users.php` is available to Platform Owner with an explicit tenant selector and to specialist roles only when the `users` permission is granted.
- User deletion now uses the standard custom confirmation workflow; Farm Admin accounts are protected from Team Users deletion/editing.
- Permanent farm deletion now clears V2.2 allocation, ruminant, audit, permission, and financial tenant data in dependency-safe order before removing the farm.
- Improved dark-mode contrast for the Permissions page guidance tip.

## Focused browser retest

1. Farm A Admin: verify Permissions page, including dark mode.
2. Give Sales `inventory` and `expenses`; refresh the Sales user's page and verify both become available without logout.
3. Remove those permissions; refresh and verify access is removed.
4. Give Poultry Manager `users`; refresh and verify Users opens instead of returning 404.
5. Verify Users delete opens the custom confirmation and never permits deletion of the Farm Admin account.
6. Platform Owner: open Users, select Farm A/Farm B, and confirm each list is tenant-scoped.
7. Broiler Feed: switch Operational View ↔ Full Audit and confirm the table redraws correctly.
8. Platform Owner: create a disposable tenant with test data, then permanently delete it and confirm no deletion error is returned.

Do not use Farm A/B/C for the destructive deletion test unless the farm is intentionally disposable.

---

## Archived from `DEPLOYMENT_V2.2.32.md`

# V2.2.32 — Permission Governance & Role Limits

This release follows the Farm A administration audit.

## Changes
- Tenant permission UI always loads global defaults first and overlays the selected farm's saved overrides.
- Permission roles are limited to the farm's subscribed modules; impossible role/module combinations are disabled.
- Added explicit Production Cycles permission so Sales users do not inherit cycle access through reporting.
- Added direct Sales navigation for authorized Sales Representatives.
- Expense permission now grants the authorized expense page/actions instead of only exposing a menu concept.
- Permission changes remain request-time/database driven, so refresh/navigation applies changes without sign-out.
- Added per-farm user limits for Poultry, Ruminant, Sales and Viewer roles, editable by Platform Owner on Platform Farms.
- User creation/editing enforces the platform-set role limits.
- Farm deletion now requires two custom high-risk confirmations.
- Team-user deletion now uses an explicit custom confirmation flow.
- Broiler Feed Operational / Full Audit uses a server-backed view switch for reliability.
- Permission page dark-mode group labels, descriptions and footer tip receive explicit high-contrast styling.
- Farm Admin dashboard access label is `Farm Administration` rather than being mistaken for a specialist role.

## Deploy
1. Extract over the existing V2.2 installation.
2. Run `php scripts/run_migrations.php`.
3. Run `php scripts/verify_v2232_permission_governance.php`.
4. Run `php tests/frontend_asset_contract.php`.
5. Run `php scripts/reconcile_stock_ledger.php --strict`.

---

## Archived from `DEPLOYMENT_V2.2.33.md`

# V2.2.33 — Feed Audit Consistency & User Limit Clarity

- Layer and Ruminant Operational View / Full Audit are now server-backed, matching Broiler.
- User-limit errors now name the exact role and report current usage versus the configured limit.
- Add User role choices display used/max counts so Farm Admin can see capacity before submission.

---

## Archived from `DEPLOYMENT_V2.2.34.md`

# V2.2.34 — Role Limits & Sales Expense Access

- Corrects tenant role usage counting so limits mean exactly 0=disabled, 1=one account, 2=two accounts.
- Removes duplicate top-level Sales navigation; Sales Report remains under Management.
- Allows Sales Representative to be delegated Layer, Broiler and Ruminant expense-entry permissions without granting production daily/feed access.
- Adds expense-entry links under Management when authorized.

Run migrations and `php scripts/verify_v2234_role_limits_sales_expenses.php`.

---

## Archived from `DEPLOYMENT_V2.2.35.md`

# V2.2.35 — Exact Role Quotas & Child-Aware Navigation

- Farm Admin is explicitly excluded from specialist-role quota counting, even if legacy user_roles rows exist.
- Role limits are exact: 0 blocks, 1 permits one specialist account, 2 permits two, etc.
- Poultry and Ruminant parent menus appear when any permitted child page is available.
- Expense-only users can reach Layer/Broiler/Ruminant expense pages without receiving overview permission.
- Duplicate expense-entry links were removed from Management.
- Sales Report remains under Management.

Run `php scripts/verify_v2235_role_quota_child_navigation.php` after deployment.

---

## Archived from `DEPLOYMENT_V2.2.36.md`

# V2.2.36 — Sales Expense Delegation Fix

This release fixes the authorization short-circuit that prevented Sales Representatives from receiving delegated Poultry/Ruminant expense permissions.

## What changed
- `hasPermission()` now allows `sales_rep` to proceed to the tenant permission matrix for `poultry_expenses` and `ruminant_expenses`.
- Other Poultry/Ruminant child permissions remain restricted to their specialist manager roles.
- Child-aware navigation from V2.2.35 is preserved: a Sales Representative with only expense permissions sees only the applicable expense entries under Poultry/Ruminants.
- No duplicate expense links were reintroduced under Management.

## Verification
```bash
php scripts/verify_v2236_sales_expense_delegation.php
php scripts/verify_v2235_role_quota_child_navigation.php
php scripts/verify_v2234_role_limits_sales_expenses.php
php tests/frontend_asset_contract.php
php scripts/reconcile_stock_ledger.php --strict
```

No database migration is required for V2.2.36.

---

## Archived from `DEPLOYMENT_V2.2.37.md`

# V2.2.37 — Feed Full Audit Display Integrity

- Removed stale client-side DataTables filtering from Layer and Ruminant feed ledgers.
- Operational / Full Audit remains server-backed across Layer, Broiler, and Ruminant.
- Full Audit now renders reversed originals and restoration rows returned by the server.
- Effective Used displays `0.00` instead of `-0.00` when there is no effective usage.

## Verification
Run:

```bash
php scripts/verify_v2237_feed_full_audit_display.php
php scripts/verify_v2236_sales_expense_delegation.php
php tests/frontend_asset_contract.php
php scripts/reconcile_stock_ledger.php --strict
```

---

## Archived from `DEPLOYMENT_V2.2.40.md`

# V2.2.40 — Receivable Payment Overpayment Protection

## Purpose
Prevent debt-payment entry from creating negative receivables or implicit customer advances.

## Changes
- General customer payments now calculate the customer's total allocatable open receivable before allocation.
- Payments are rejected when the customer has no outstanding balance.
- Payments greater than the customer's outstanding balance are rejected.
- Specific-sale payment protection remains enforced against that sale's outstanding balance.
- Any unexpected FIFO remainder fails closed; it is no longer inserted as an unallocated/advance payment.
- Payment modal shows the selected customer's available outstanding and disables payment submission when the selected balance is zero or negative.

## Verification
Run:

```bash
php scripts/verify_v2240_receivable_payment_overpayment_protection.php
php scripts/verify_v2239_receivables_cash_reconciliation.php
```

## Live QA recovery checkpoint
The V2.2.39 reproduction intentionally created a ₦1,000 unallocated payment for `Credit Test Customer`, leaving a -₦1,000 balance. Keep that row until V2.2.40 is deployed. After deployment, remove only that failed-test payment entry, confirm the customer returns to ₦0.00, then retry a ₦1,000 payment and confirm it is rejected with no new ledger row.

---

## Archived from `DEPLOYMENT_V2.2.41.md`

# V2.2.41 — Centralized Platform Print Architecture

## Scope
Platform-wide print cleanup and stabilization.

## Architecture
- `assets/css/print.css` is the single source of truth for browser-print layout.
- `assets/js/print-manager.js` is the single print entry point and automatically chooses portrait/landscape based on report width unless a page explicitly overrides it.
- Print assets are loaded globally by `navbar_head.php`.
- Actions columns, buttons, filters, navigation and other interactive UI are removed centrally.
- Responsive tables are made printable, cells wrap, headers repeat, and rows avoid splitting across pages.
- Shared page-break utilities replace page-specific print CSS.

## Audit
See `docs/PRINT_ARCHITECTURE_AUDIT.md` for all pre-centralization print touchpoints discovered by the repository scan.

## Regression
Receivable/payment business logic is unchanged. V2.2.40 verification must continue to pass.

---

## Archived from `DEPLOYMENT_V2.2.42.md`

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

---

## Archived from `DEPLOYMENT_V2.2.43.md`

# V2.2.43 — PDF Action Completion

- Sales Report: PDF Monthly / PDF Yearly links fixed.
- Expense Report: PDF Monthly / PDF Yearly links fixed.
- Layer Expenses: PDF Report added.
- Broiler Expenses: PDF Report added.
- Ruminant Expenses: PDF Report added.
- All use the centralized application PDF engine; browser URL/timestamp headers are not part of these PDFs.
- Receivables logic unchanged.

---

## V2.2.44 — PDF Export Stabilization & Documentation Cleanup

- Fixed Expense Report PDF mode so `?pdf=1` returns a PDF instead of the HTML report page.
- Migrated Farm Reports & Analytics from browser Print Report to application-generated PDF Report.
- Analytics PDF uses server-rendered tables instead of Chart.js canvases.
- Central PDF service strips icon-font tags and empty Actions columns.
- Relaxed generic card page-break rules to reduce blank/orphan pages.
- Consolidated version-specific deployment Markdown files into this single `DEPLOYMENT.md`.
- Receivables/accounting logic unchanged.

---

## V2.2.45 — PDF Content Recovery & Debt History Migration

- Fixed application-generated PDFs rendering only the Renee Farms footer after V2.2.44.
- Removed the whole-document `DOMDocument` rewrite from the central PDF service; it was the common regression across all migrated PDFs.
- Actions columns on migrated expense pages now use explicit `no-print` markup.
- Sales customer debt history now uses a dedicated application-generated PDF instead of browser printing.
- Receivables accounting logic is unchanged.


---

## V2.2.46 — Dedicated Sales & Expense PDF Templates

- Sales PDF button now targets `management/sales_report_pdf.php`.
- Expense PDF button now targets `management/expense_report_pdf.php`.
- Interactive Bootstrap pages are no longer the official PDF render source for these reports.
- Sales PDF omits debt details unless a customer is selected.
- Expense PDF has no Actions column.
- Central `PdfReportService` remains the shared renderer.
- Receivables logic is unchanged.
