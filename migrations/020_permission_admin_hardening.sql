-- V2.2.31 Permission & Administration Hardening
-- Tenant-scope the permission matrix. Existing role/module rows remain as
-- farm_id=0 global defaults; tenant rows override them after a farm saves its matrix.

ALTER TABLE permissions
    ADD COLUMN farm_id INT NOT NULL DEFAULT 0 AFTER id;

ALTER TABLE permissions
    DROP INDEX unique_role_module;

ALTER TABLE permissions
    ADD UNIQUE KEY uniq_permission_farm_role_module (farm_id, role, module),
    ADD INDEX idx_permissions_farm (farm_id);

INSERT INTO schema_migrations (filename)
VALUES ('020_permission_admin_hardening.sql')
ON DUPLICATE KEY UPDATE filename = VALUES(filename);
