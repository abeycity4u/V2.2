# V2.2.17 — Attribution & Cost Centre Foundation

This release adds a durable attribution hierarchy: **Farm Type → Production Type → Production Cycle**.

Key rules:
- `General` remains a first-class sales/reporting scope for waste, by-products and other farm income.
- Poultry production types: Layer, Broiler, Shared/Unallocated.
- Ruminant production types: Cattle, Goat, Sheep, Other, Shared/Unallocated.
- A transaction may be assigned to one cycle or intentionally remain pooled at production-type level.
- Pooled activity is never guessed into a cycle. Future explicit allocations are supported by `sales_allocations`; expense allocations continue to use `financial_allocations`.
- Feed movements snapshot production ownership and feed ledgers show Production Type + Cycle.
- Profitability supports Daily/Monthly filtering by Farm Type, Production Type and Cycle.

Deploy code, then run:

```bash
php scripts/run_migrations.php
php scripts/verify_v2217_attribution_cost_centres.php
php scripts/verify_v2216_historical_cost_integrity.php
php scripts/verify_v2215_reporting_ui_consistency.php
php scripts/reconcile_stock_ledger.php --strict
```
