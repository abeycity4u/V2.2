-- V2.2.32 Permission Governance & Role Limits
CREATE TABLE IF NOT EXISTS farm_role_limits (
    id INT AUTO_INCREMENT PRIMARY KEY,
    farm_id INT NOT NULL,
    role_code VARCHAR(50) NOT NULL,
    max_users INT NOT NULL DEFAULT 1,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_farm_role_limit (farm_id, role_code),
    INDEX idx_farm_role_limits_farm (farm_id),
    CONSTRAINT fk_farm_role_limits_farm FOREIGN KEY (farm_id) REFERENCES farms(id) ON DELETE CASCADE
);

INSERT INTO schema_migrations (filename)
VALUES ('021_role_limits_permission_cleanup.sql')
ON DUPLICATE KEY UPDATE filename = VALUES(filename);

-- Sensible global defaults. Tenant-specific rows remain untouched.
INSERT INTO permissions (farm_id, role, module, allowed) VALUES
(0,'poultry_manager','poultry_overview',1),(0,'poultry_manager','poultry_daily_layer',1),(0,'poultry_manager','poultry_daily_broiler',1),(0,'poultry_manager','poultry_feeds',1),(0,'poultry_manager','poultry_health',1),(0,'poultry_manager','poultry_expenses',1),(0,'poultry_manager','inventory',1),(0,'poultry_manager','reports',1),(0,'poultry_manager','production_cycles',1),
(0,'ruminant_manager','ruminant_overview',1),(0,'ruminant_manager','ruminant_daily',1),(0,'ruminant_manager','ruminant_feeds',1),(0,'ruminant_manager','ruminant_expenses',1),(0,'ruminant_manager','inventory',1),(0,'ruminant_manager','reports',1),(0,'ruminant_manager','production_cycles',1),
(0,'sales_rep','sales',1),(0,'sales_rep','inventory',1),(0,'sales_rep','expenses',1),(0,'sales_rep','reports',1)
ON DUPLICATE KEY UPDATE allowed=VALUES(allowed);
