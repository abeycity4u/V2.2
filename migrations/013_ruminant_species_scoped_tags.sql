-- V2.1: allow the same physical tag number to exist for different ruminant species.
-- Example: Cattle #1 and Goat #1 are valid; two Cattle #1 records are not.
SET @has_old := (SELECT COUNT(*) FROM information_schema.statistics WHERE table_schema=DATABASE() AND table_name='ruminant_animals' AND index_name='uniq_ruminant_farm_tag');
SET @sql := IF(@has_old > 0, 'ALTER TABLE ruminant_animals DROP INDEX uniq_ruminant_farm_tag', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @has_new := (SELECT COUNT(*) FROM information_schema.statistics WHERE table_schema=DATABASE() AND table_name='ruminant_animals' AND index_name='uniq_ruminant_farm_species_tag');
SET @sql := IF(@has_new = 0, 'ALTER TABLE ruminant_animals ADD UNIQUE KEY uniq_ruminant_farm_species_tag (farm_id,species,tag_no)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
