-- V2.2.17 Attribution & Cost Centre Foundation
-- Adds durable operation ownership to sales, expenses and stock movements.
-- cycle_id remains optional: NULL + production_type means pooled/shared activity
-- at that production-type level (e.g. eggs from multiple layer cycles).

ALTER TABLE sales_records
    ADD COLUMN production_type VARCHAR(100) NULL AFTER farm_type,
    ADD COLUMN attribution_scope ENUM('cycle','production_type','farm') NOT NULL DEFAULT 'farm' AFTER production_type;
ALTER TABLE sales_records
    ADD INDEX idx_sales_attribution (farm_id, farm_type, production_type, cycle_id, sale_date);

ALTER TABLE farm_expenses
    MODIFY COLUMN farm_type ENUM('poultry','ruminant','both','general') NOT NULL DEFAULT 'both';

ALTER TABLE farm_expenses
    ADD COLUMN production_type VARCHAR(100) NULL AFTER farm_type,
    ADD COLUMN attribution_scope ENUM('cycle','production_type','farm') NOT NULL DEFAULT 'farm' AFTER production_type;
ALTER TABLE farm_expenses
    ADD INDEX idx_expense_attribution (farm_id, farm_type, production_type, cycle_id, expense_date);

ALTER TABLE stock_transactions
    ADD COLUMN production_type VARCHAR(100) NULL AFTER farm_type,
    ADD COLUMN attribution_scope ENUM('cycle','production_type','farm') NOT NULL DEFAULT 'farm' AFTER production_type;
ALTER TABLE stock_transactions
    ADD INDEX idx_stock_tx_attribution (farm_id, farm_type, production_type, cycle_id, transaction_date);

-- Backfill sales from cycle first, then product semantics for legacy records.
UPDATE sales_records s
LEFT JOIN production_cycles pc ON pc.id=s.cycle_id AND pc.farm_id=s.farm_id
SET s.production_type = COALESCE(
        NULLIF(LOWER(pc.production_type), ''),
        CASE
            WHEN s.farm_type='general' THEN 'general'
            WHEN s.farm_type='poultry' AND LOWER(s.product_type) REGEXP 'egg|layer|pullet' THEN 'layer'
            WHEN s.farm_type='poultry' AND LOWER(s.product_type) REGEXP 'broiler|chicken|bird' THEN 'broiler'
            WHEN s.farm_type='ruminant' AND LOWER(s.product_type) REGEXP 'cow|cattle|bull|heifer' THEN 'cattle'
            WHEN s.farm_type='ruminant' AND LOWER(s.product_type) REGEXP 'goat' THEN 'goat'
            WHEN s.farm_type='ruminant' AND LOWER(s.product_type) REGEXP 'sheep|ram|ewe' THEN 'sheep'
            WHEN s.farm_type='ruminant' THEN 'other'
            ELSE 'shared'
        END
    )
WHERE s.production_type IS NULL OR s.production_type='';
UPDATE sales_records
SET attribution_scope = CASE WHEN cycle_id IS NOT NULL AND cycle_id>0 THEN 'cycle'
                             WHEN farm_type='general' OR production_type='shared' THEN 'farm'
                             ELSE 'production_type' END;

-- Existing module-specific expense pages already identify Layer/Broiler via poultry_category.
UPDATE farm_expenses e
LEFT JOIN production_cycles pc ON pc.id=e.cycle_id AND pc.farm_id=e.farm_id
SET e.production_type = COALESCE(
        NULLIF(LOWER(pc.production_type), ''),
        CASE
            WHEN e.farm_type='poultry' AND LOWER(COALESCE(e.poultry_category,'')) IN ('layer','broiler') THEN LOWER(e.poultry_category)
            WHEN e.farm_type='ruminant' THEN 'shared'
            WHEN e.farm_type='general' THEN 'general'
            ELSE 'shared'
        END
    )
WHERE e.production_type IS NULL OR e.production_type='';
UPDATE farm_expenses
SET attribution_scope = CASE WHEN cycle_id IS NOT NULL AND cycle_id>0 THEN 'cycle'
                             WHEN farm_type='general' OR production_type='shared' THEN 'farm'
                             ELSE 'production_type' END;

-- Stock usage follows its cycle where possible. Poultry feed category is a safe
-- operation hint; generic ruminant feed remains shared unless a cycle identifies species.
UPDATE stock_transactions t
JOIN stock_items s ON s.id=t.stock_item_id AND s.farm_id=t.farm_id
LEFT JOIN production_cycles pc ON pc.id=t.cycle_id AND pc.farm_id=t.farm_id
SET t.production_type = COALESCE(
        NULLIF(LOWER(pc.production_type), ''),
        CASE
            WHEN LOWER(s.feed_category) IN ('layer','broiler') THEN LOWER(s.feed_category)
            WHEN t.farm_type='general' THEN 'general'
            ELSE 'shared'
        END
    )
WHERE t.production_type IS NULL OR t.production_type='';
UPDATE stock_transactions
SET attribution_scope = CASE WHEN cycle_id IS NOT NULL AND cycle_id>0 THEN 'cycle'
                             WHEN farm_type='general' OR production_type='shared' THEN 'farm'
                             ELSE 'production_type' END;

CREATE TABLE IF NOT EXISTS sales_allocations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    farm_id INT NOT NULL,
    sale_id INT NOT NULL,
    cycle_id INT NOT NULL,
    allocation_percent DECIMAL(7,4) NOT NULL,
    allocated_amount DECIMAL(14,2) NOT NULL,
    allocation_basis VARCHAR(50) NOT NULL DEFAULT 'manual',
    notes VARCHAR(255) NULL,
    created_by INT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_sales_allocation (sale_id, cycle_id),
    INDEX idx_sales_alloc_farm_cycle (farm_id, cycle_id),
    CONSTRAINT fk_sales_alloc_farm FOREIGN KEY (farm_id) REFERENCES farms(id) ON DELETE RESTRICT,
    CONSTRAINT fk_sales_alloc_sale FOREIGN KEY (sale_id) REFERENCES sales_records(id) ON DELETE CASCADE,
    CONSTRAINT fk_sales_alloc_cycle FOREIGN KEY (cycle_id) REFERENCES production_cycles(id) ON DELETE CASCADE,
    CONSTRAINT fk_sales_alloc_user FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO schema_migrations (filename)
VALUES ('018_attribution_cost_centres.sql')
ON DUPLICATE KEY UPDATE filename=VALUES(filename);
