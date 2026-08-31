-- V2.2.11 Stock Ledger Integrity
-- One append-only inventory ledger for Daily Records, Feed Records, Inventory
-- and API stock updates. transaction_date is the effective/business date;
-- created_at is the actual posting/event time.

ALTER TABLE stock_transactions
    ADD INDEX idx_stock_tx_item_event (farm_id, stock_item_id, created_at, id),
    ADD INDEX idx_stock_tx_item_effective (farm_id, stock_item_id, transaction_date, created_at, id);

INSERT INTO schema_migrations (filename)
VALUES ('016_stock_ledger_integrity.sql')
ON DUPLICATE KEY UPDATE filename = VALUES(filename);
