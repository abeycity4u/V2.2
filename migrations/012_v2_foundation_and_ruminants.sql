-- Farm Platform V2 foundation + ruminant registry
-- Safe additive migration. Run through the existing migration runner.

CREATE TABLE IF NOT EXISTS v2_audit_log (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
 farm_id INT NULL, user_id INT NULL, action VARCHAR(80) NOT NULL,
 entity_type VARCHAR(80) NOT NULL, entity_id VARCHAR(100) NULL,
 details_json JSON NULL, ip_address VARCHAR(45) NULL, user_agent VARCHAR(500) NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 PRIMARY KEY(id), KEY idx_audit_farm_created(farm_id,created_at),
 KEY idx_audit_entity(entity_type,entity_id), KEY idx_audit_user_created(user_id,created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS ruminant_animals (
 id INT AUTO_INCREMENT PRIMARY KEY, farm_id INT NOT NULL, tag_no VARCHAR(100) NOT NULL,
 species ENUM('cattle','goat','sheep','other') NOT NULL DEFAULT 'other', breed VARCHAR(120) NULL,
 sex ENUM('male','female','unknown') NOT NULL DEFAULT 'unknown', birth_date DATE NULL,
 source VARCHAR(150) NULL, purchase_date DATE NULL, purchase_cost DECIMAL(14,2) NOT NULL DEFAULT 0,
 status ENUM('active','sold','dead','culled','transferred') NOT NULL DEFAULT 'active',
 notes TEXT NULL, created_by INT NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, updated_at DATETIME NULL,
 UNIQUE KEY uniq_ruminant_farm_species_tag(farm_id,species,tag_no), KEY idx_ruminant_farm_status(farm_id,status), KEY idx_ruminant_species(farm_id,species),
 FOREIGN KEY(created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS ruminant_animal_weights (
 id INT AUTO_INCREMENT PRIMARY KEY, farm_id INT NOT NULL, animal_id INT NOT NULL, weight_date DATE NOT NULL,
 weight_kg DECIMAL(10,2) NOT NULL, notes VARCHAR(255) NULL, recorded_by INT NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 KEY idx_weight_animal_date(animal_id,weight_date), KEY idx_weight_farm_date(farm_id,weight_date),
 FOREIGN KEY(animal_id) REFERENCES ruminant_animals(id) ON DELETE CASCADE, FOREIGN KEY(recorded_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS ruminant_health_events (
 id INT AUTO_INCREMENT PRIMARY KEY, farm_id INT NOT NULL, animal_id INT NOT NULL, event_date DATE NOT NULL,
 event_type ENUM('vaccination','treatment','diagnosis','vet_visit','deworming','other') NOT NULL DEFAULT 'other',
 description TEXT NULL, medicine VARCHAR(150) NULL, dosage VARCHAR(100) NULL, withdrawal_until DATE NULL, recorded_by INT NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 KEY idx_health_animal_date(animal_id,event_date), KEY idx_health_farm_date(farm_id,event_date),
 FOREIGN KEY(animal_id) REFERENCES ruminant_animals(id) ON DELETE CASCADE, FOREIGN KEY(recorded_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
