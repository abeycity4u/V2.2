# V2.2.19 — Pooled Revenue Allocation & Sale Edit Stability

## What changed
- Fixes Edit Sale attribution controls so Production Type and matching Cycle always repopulate.
- Pooled Poultry → Layer egg sales are automatically allocated across Layer cycles that recorded output on the sale date.
- Allocation uses `crates_count` when available, otherwise `egg_production` as the proportional basis.
- Pooled revenue is **not** split equally among merely-active cycles.
- Original sale remains one source record; `sales_allocations` contains reporting ownership only, preventing double counting.
- Sales ledger shows Direct / Allocated / Partial / Unallocated status.
- Cycle Profitability includes explicit allocations and warns when matching pooled revenue remains unallocated.
- Adds a safe, idempotent backfill command for existing pooled Layer egg sales.

## Database
No new migration. V2.2.19 uses the `sales_allocations` table introduced by migration 018.

## Deploy / verify
```bash
php scripts/run_migrations.php
php scripts/backfill_pooled_sales_allocations.php
php scripts/verify_v2219_pooled_revenue_allocation.php
php scripts/verify_v2218_dependent_attribution_filters.php
php scripts/verify_v2217_attribution_cost_centres.php
php scripts/reconcile_stock_ledger.php --strict
```

The backfill only auto-allocates pooled Layer egg sales where cycle-level Layer Daily Record production exists for the sale date. Other pooled sales stay explicitly unallocated rather than using a guessed split.
