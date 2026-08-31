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
