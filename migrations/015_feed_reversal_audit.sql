-- V2.2.10 Feed reversal audit integrity
-- Keep original Daily Record feed transactions instead of deleting them during
-- edit/delete. A reversal row is linked to the original transaction, making
-- the ledger auditable and preventing apparent stock inflation.

ALTER TABLE stock_transactions
    ADD COLUMN is_reversed TINYINT(1) NOT NULL DEFAULT 0 AFTER source_id,
    ADD COLUMN reversal_of_id INT NULL AFTER is_reversed,
    ADD COLUMN reversed_at DATETIME NULL AFTER reversal_of_id,
    ADD INDEX idx_stock_tx_reversal (reversal_of_id),
    ADD INDEX idx_stock_tx_active_source (farm_id, source_type, source_id, transaction_type, is_reversed);

INSERT INTO schema_migrations (filename)
VALUES ('015_feed_reversal_audit.sql')
ON DUPLICATE KEY UPDATE filename = VALUES(filename);
