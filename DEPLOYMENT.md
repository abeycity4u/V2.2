# V2.2.3 Deployment — Integrated Daily Feed Consumption

1. Deploy the application files to the V2.2 test environment.
2. Ensure V2.2.1 migration `014_v22_financial_foundation.sql` has already been applied.
3. Apply `migrations/015_daily_feed_integration.sql` once.
4. Run:
   `php scripts/verify_v223_daily_feed_integration.php`
5. Test Layer, Broiler, and Ruminant Daily Record feed selection and saving.
6. Verify stock decreases by the entered quantity.
7. Edit the daily record with a different feed/quantity and verify the old stock effect is restored and the new effect applied.
8. Delete a test daily record and verify the linked stock quantity is restored.
9. Confirm the dedicated Layer/Broiler/Ruminant Feed Records pages continue to work independently.

Do not deploy over the frozen V2.1 stable environment.

## V2.2.11 Stock Ledger Integrity

After uploading the V2.2.11 package:

1. Run the normal migration runner:
   `php scripts/run_migrations.php`
2. Confirm the new migration was applied:
   `migrations/016_stock_ledger_integrity.sql`
3. Run the contract audit:
   `php scripts/verify_v2211_stock_ledger_integrity.php`
4. Run the read-only live reconciliation report:
   `php scripts/reconcile_stock_ledger.php`
   or for one farm:
   `php scripts/reconcile_stock_ledger.php --farm-id=3`
5. For CI/deployment gating, use:
   `php scripts/reconcile_stock_ledger.php --strict`

**Do not manually repair a mismatch by changing `stock_items.current_stock` first.**
A mismatch means the live balance and the append-only ledger disagree and must be
investigated from the transaction chain before any correction is made.


### V2.2.12 stock ledger hardening
Run migrations normally. Migration 017 creates a single read-only audit starting point for legacy stock items that have a positive current balance but no ledger rows. It does not alter current stock and does not repair ambiguous mismatches. After migration, run `php scripts/reconcile_stock_ledger.php --strict`.
