# V2.2.10 deployment

1. Upload/extract the V2.2.10 ZIP over the existing `/v2` application.
2. Run the migration:
   `migrations/015_feed_reversal_audit.sql`
3. Verify:
   `php scripts/verify_v2210_feed_reversal_integrity.php`
4. Run the normal PHP syntax/contract checks.

No manual data repair is performed by this migration. Existing V2.2.9 test rows that already show an inflated restoration (for example Chikun `100 -> 115`) are historical data and should be corrected separately after confirming the affected test transaction/source record. The new append-only reversal model prevents new inflation from being created.
