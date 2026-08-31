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
