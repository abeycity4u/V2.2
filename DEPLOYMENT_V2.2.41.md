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
