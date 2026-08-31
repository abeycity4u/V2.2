# V2.2.11 — Stock Ledger Integrity Deployment

## 1. Upload

Replace the application files with this package while preserving the existing database.

## 2. Run migrations

From the `/v2` directory:

```bash
php scripts/run_migrations.php
```

This applies:

```text
migrations/016_stock_ledger_integrity.sql
```

## 3. Run application contracts

```bash
php scripts/verify_v2211_stock_ledger_integrity.php
```

The full existing test suite should also be run:

```bash
php tests/contract_checks.php
php tests/daily_feed_category_contract.php
php tests/daily_feed_sync_signature_contract.php
php tests/frontend_asset_contract.php
php tests/safe_exception_helper_contract.php
php tests/v22_financial_checks.php
php tests/v2_contract_checks.php
php scripts/verify_v228_feed_ledger_consistency.php
php scripts/verify_v2210_feed_reversal_integrity.php
```

## 4. Reconcile live stock

Read-only report:

```bash
php scripts/reconcile_stock_ledger.php
```

One farm only:

```bash
php scripts/reconcile_stock_ledger.php --farm-id=3
```

CI/deployment gate:

```bash
php scripts/reconcile_stock_ledger.php --strict
```

A mismatch is intentionally **not auto-repaired**. Investigate the affected
item's ledger chain first.

## 5. What changed

- Daily Records, Feed Records, Inventory and stock API now share one stock mutation service.
- Posted stock movements are append-only.
- Edit/delete corrections create linked reversal movements.
- Reversed originals remain visible in audit tables but never count toward active stock.
- Reversal rows use the actual correction/posting date while preserving the original transaction's effective date.
- Dashboard, Stock History, charts and financial feed costing use the active ledger semantics.
- Inventory cleanup no longer deletes stock transaction history.
- Stock History reports whether `stock_items.current_stock` reconciles with the active transaction ledger.
