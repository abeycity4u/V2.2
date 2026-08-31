-- V2.2.34 Sales expense delegation and role-limit clarity
INSERT INTO permissions (farm_id, role, module, allowed) VALUES
(0,'sales_rep','poultry_expenses',1),
(0,'sales_rep','ruminant_expenses',1)
ON DUPLICATE KEY UPDATE allowed=VALUES(allowed);

INSERT INTO schema_migrations (filename) VALUES ('022_sales_expense_delegation.sql')
ON DUPLICATE KEY UPDATE filename=VALUES(filename);
