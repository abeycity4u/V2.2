-- V2.2.1 Financial Foundation
-- Preserve inventory cost at point of use and optionally associate stock
-- consumption with a production cycle. Add controlled allocation of shared
-- expenses to production cycles.
-- Historical transactions intentionally keep NULL cost snapshots.

ALTER TABLE stock_transactions
    ADD COLUMN cycle_id INT NULL AFTER farm_id,
    ADD COLUMN unit_cost DECIMAL(14,4) NULL AFTER quantity,
    ADD COLUMN total_cost DECIMAL(14,2) NULL AFTER unit_cost,
    ADD COLUMN source_type VARCHAR(50) NULL AFTER farm_type,
    ADD COLUMN source_id INT NULL AFTER source_type;

ALTER TABLE stock_transactions
    ADD INDEX idx_stock_tx_cycle_date (cycle_id, transaction_date),
    ADD INDEX idx_stock_tx_source (source_type, source_id);

ALTER TABLE stock_transactions
    ADD CONSTRAINT fk_stock_tx_cycle
        FOREIGN KEY (cycle_id) REFERENCES production_cycles(id) ON DELETE SET NULL;

CREATE TABLE IF NOT EXISTS financial_allocations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    farm_id INT NOT NULL,
    expense_id INT NOT NULL,
    cycle_id INT NOT NULL,
    allocation_percent DECIMAL(7,4) NOT NULL,
    allocated_amount DECIMAL(14,2) NOT NULL,
    notes VARCHAR(255) NULL,
    created_by INT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_financial_allocation (expense_id, cycle_id),
    INDEX idx_fin_alloc_farm_cycle (farm_id, cycle_id),
    INDEX idx_fin_alloc_expense (expense_id),
    CONSTRAINT fk_fin_alloc_farm FOREIGN KEY (farm_id) REFERENCES farms(id) ON DELETE RESTRICT,
    CONSTRAINT fk_fin_alloc_expense FOREIGN KEY (expense_id) REFERENCES farm_expenses(id) ON DELETE CASCADE,
    CONSTRAINT fk_fin_alloc_cycle FOREIGN KEY (cycle_id) REFERENCES production_cycles(id) ON DELETE CASCADE,
    CONSTRAINT fk_fin_alloc_user FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS financial_settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    farm_id INT NOT NULL,
    feed_costing_method ENUM('weighted_average','snapshot') NOT NULL DEFAULT 'weighted_average',
    default_currency CHAR(3) NOT NULL DEFAULT 'NGN',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_financial_settings_farm (farm_id),
    CONSTRAINT fk_fin_settings_farm FOREIGN KEY (farm_id) REFERENCES farms(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO financial_settings (farm_id)
SELECT id FROM farms
ON DUPLICATE KEY UPDATE farm_id = VALUES(farm_id);

INSERT INTO schema_migrations (filename)
VALUES ('014_v22_financial_foundation.sql')
ON DUPLICATE KEY UPDATE filename = VALUES(filename);
