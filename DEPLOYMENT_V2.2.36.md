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
