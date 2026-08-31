-- V2.2.12 Stock Ledger Hardening
-- Backfill a durable opening-balance ledger event for legacy stock items that
-- already have physical stock but no stock transaction history. This is safe:
-- it does not alter current_stock and it does not touch items that already have
-- any ledger movement. Future inventory creation already posts its own initial
-- movement through stock_service.php.

INSERT INTO stock_transactions
    (farm_id, cycle_id, stock_item_id, transaction_type, quantity, unit_cost, total_cost,
     previous_stock, new_stock, transaction_date, remarks, user_id, farm_type, source_type, source_id)
SELECT
    s.farm_id,
    NULL,
    s.id,
    'received',
    s.current_stock,
    s.unit_cost,
    ROUND(s.current_stock * s.unit_cost, 2),
    0,
    s.current_stock,
    DATE(s.created_at),
    'Legacy opening balance (pre-ledger stock)',
    NULL,
    s.farm_type,
    'legacy_opening_balance',
    s.id
FROM stock_items s
WHERE s.current_stock > 0
  AND NOT EXISTS (
      SELECT 1 FROM stock_transactions t
      WHERE t.farm_id = s.farm_id
        AND t.stock_item_id = s.id
  );

INSERT INTO schema_migrations (filename)
VALUES ('017_legacy_stock_opening_balances.sql')
ON DUPLICATE KEY UPDATE filename = VALUES(filename);
