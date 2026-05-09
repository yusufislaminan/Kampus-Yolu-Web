-- Migration: Profil resmi ve engelleme sistemi
-- Kampüs Yolu - v2.0

-- 1. Profil resmi sütunu ekle (sütun yoksa ekle)
SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_SCHEMA = 'kampus_yolu' AND TABLE_NAME = 'users' AND COLUMN_NAME = 'profile_pic');
SET @sql = IF(@col_exists = 0, 
    'ALTER TABLE users ADD COLUMN profile_pic VARCHAR(255) DEFAULT NULL AFTER gender', 
    'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 2. Engelleme tablosu oluştur
CREATE TABLE IF NOT EXISTS blocked_users (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  blocker_id BIGINT UNSIGNED NOT NULL,
  blocked_id BIGINT UNSIGNED NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uniq_block (blocker_id, blocked_id),
  KEY idx_blocked_blocker (blocker_id),
  KEY idx_blocked_blocked (blocked_id),
  CONSTRAINT fk_block_blocker FOREIGN KEY (blocker_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_block_blocked FOREIGN KEY (blocked_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;
