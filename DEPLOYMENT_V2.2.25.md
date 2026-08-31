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
