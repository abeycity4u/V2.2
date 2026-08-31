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
