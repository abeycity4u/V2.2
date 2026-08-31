-- V2.2.20 Layer Egg Production-to-Sales Ledger
-- Persist the quantity ownership behind pooled revenue allocations. Revenue
-- remains stored once on sales_records; sales_allocations only owns the cycle
-- share used by profitability.

ALTER TABLE sales_allocations
    ADD COLUMN allocated_quantity DECIMAL(14,4) NULL AFTER allocation_percent,
    ADD COLUMN allocation_unit VARCHAR(30) NULL AFTER allocated_quantity;

ALTER TABLE sales_allocations
    ADD INDEX idx_sales_alloc_sale_qty (farm_id, sale_id, allocated_quantity);

-- Preserve quantity ownership for allocations created by V2.2.19 before this
-- migration. Future rebuilds replace these estimates from the unsold egg pool.
UPDATE sales_allocations sa
JOIN sales_records s ON s.id=sa.sale_id AND s.farm_id=sa.farm_id
SET sa.allocated_quantity = ROUND(s.quantity * sa.allocation_percent / 100, 4),
    sa.allocation_unit = CASE
        WHEN s.farm_type='poultry' AND LOWER(COALESCE(s.production_type,''))='layer'
             AND LOWER(s.product_type) LIKE '%egg%' THEN 'crate'
        ELSE NULL
    END
WHERE sa.allocated_quantity IS NULL;

INSERT INTO schema_migrations (filename)
VALUES ('019_layer_egg_inventory_allocation.sql')
ON DUPLICATE KEY UPDATE filename=VALUES(filename);
