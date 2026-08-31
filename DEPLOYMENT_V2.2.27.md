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
