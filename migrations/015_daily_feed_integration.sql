-- V2.2.3/2.2.4: Integrate daily feed consumption with inventory feed items.
ALTER TABLE layer_daily_records
    ADD COLUMN feed_item_id INT NULL AFTER feed_consumption_bags,
    ADD INDEX idx_layer_daily_feed_item (feed_item_id),
    ADD CONSTRAINT fk_layer_daily_feed_item FOREIGN KEY (feed_item_id) REFERENCES stock_items(id) ON DELETE SET NULL;

ALTER TABLE broiler_daily_records
    ADD COLUMN feed_item_id INT NULL AFTER feed_consumption_bags,
    ADD INDEX idx_broiler_daily_feed_item (feed_item_id),
    ADD CONSTRAINT fk_broiler_daily_feed_item FOREIGN KEY (feed_item_id) REFERENCES stock_items(id) ON DELETE SET NULL;

ALTER TABLE ruminant_daily_records
    ADD COLUMN feed_item_id INT NULL AFTER feed_consumption_kg,
    ADD COLUMN feed_consumption_unit VARCHAR(50) NOT NULL DEFAULT 'kg' AFTER feed_item_id,
    ADD INDEX idx_ruminant_daily_feed_item (feed_item_id),
    ADD CONSTRAINT fk_ruminant_daily_feed_item FOREIGN KEY (feed_item_id) REFERENCES stock_items(id) ON DELETE SET NULL;

INSERT INTO schema_migrations (filename)
VALUES ('015_daily_feed_integration.sql')
ON DUPLICATE KEY UPDATE filename = VALUES(filename);
