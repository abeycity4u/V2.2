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
