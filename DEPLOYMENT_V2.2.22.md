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
