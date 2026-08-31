# Farm Platform V2.2.16 — Historical Cost Integrity

No new migration is required.

After deployment run:

```bash
php scripts/verify_v2216_historical_cost_integrity.php
php scripts/verify_v2215_reporting_ui_consistency.php
php scripts/reconcile_stock_ledger.php --strict
```

Expected: all verifier checks pass and stock reconciliation reports zero mismatches.
