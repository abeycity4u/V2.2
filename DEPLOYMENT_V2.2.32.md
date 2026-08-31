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
