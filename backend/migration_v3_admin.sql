-- ============================================================
-- Migration v3: Admin Paneli Tabloları + Event Scheduler
-- Kampüs Yolu — Admin Panel Infrastructure
-- ============================================================

USE kampus_yolu;

-- ============================================
-- 1. users tablosuna yeni sütunlar
-- ============================================
SET @col1 = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_SCHEMA = 'kampus_yolu' AND TABLE_NAME = 'users' AND COLUMN_NAME = 'is_suspended');
SET @sql1 = IF(@col1 = 0, 
    'ALTER TABLE users ADD COLUMN is_suspended TINYINT(1) NOT NULL DEFAULT 0 AFTER status', 
    'SELECT 1');
PREPARE stmt1 FROM @sql1;
EXECUTE stmt1;
DEALLOCATE PREPARE stmt1;

SET @col2 = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_SCHEMA = 'kampus_yolu' AND TABLE_NAME = 'users' AND COLUMN_NAME = 'trust_level');
SET @sql2 = IF(@col2 = 0, 
    'ALTER TABLE users ADD COLUMN trust_level TINYINT UNSIGNED NOT NULL DEFAULT 0 AFTER is_suspended', 
    'SELECT 1');
PREPARE stmt2 FROM @sql2;
EXECUTE stmt2;
DEALLOCATE PREPARE stmt2;

-- ============================================
-- 2. Kullanıcı Belgeleri
-- ============================================
CREATE TABLE IF NOT EXISTS user_documents (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id BIGINT UNSIGNED NOT NULL,
  doc_type ENUM('ogrenci_belgesi','sabika_kaydi','kimlik','diger') NOT NULL,
  file_name VARCHAR(255) NOT NULL,
  original_name VARCHAR(255) NOT NULL,
  mime_type VARCHAR(100) NOT NULL,
  file_size INT UNSIGNED NOT NULL DEFAULT 0,
  status ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  admin_note TEXT DEFAULT NULL,
  reviewed_by BIGINT UNSIGNED DEFAULT NULL,
  reviewed_at TIMESTAMP NULL DEFAULT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_docs_user (user_id),
  KEY idx_docs_status (status),
  CONSTRAINT fk_docs_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_docs_reviewer FOREIGN KEY (reviewed_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ============================================
-- 3. Şikayetler
-- ============================================
CREATE TABLE IF NOT EXISTS complaints (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  reporter_id BIGINT UNSIGNED NOT NULL,
  reported_id BIGINT UNSIGNED NOT NULL,
  category ENUM('uygunsuz_davranis','gelmeme','spam','sahte_profil','diger') NOT NULL,
  description TEXT NOT NULL,
  status ENUM('open','investigating','resolved','dismissed') NOT NULL DEFAULT 'open',
  admin_note TEXT DEFAULT NULL,
  resolved_by BIGINT UNSIGNED DEFAULT NULL,
  resolved_at TIMESTAMP NULL DEFAULT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_complaints_reporter (reporter_id),
  KEY idx_complaints_reported (reported_id),
  KEY idx_complaints_status (status),
  CONSTRAINT fk_complaint_reporter FOREIGN KEY (reporter_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_complaint_reported FOREIGN KEY (reported_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_complaint_resolver FOREIGN KEY (resolved_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ============================================
-- 4. Admin Uyarıları
-- ============================================
CREATE TABLE IF NOT EXISTS admin_warnings (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id BIGINT UNSIGNED NOT NULL,
  admin_id BIGINT UNSIGNED NOT NULL,
  severity ENUM('info','warning','critical') NOT NULL DEFAULT 'warning',
  message TEXT NOT NULL,
  is_read TINYINT(1) NOT NULL DEFAULT 0,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_warnings_user (user_id),
  KEY idx_warnings_read (is_read),
  CONSTRAINT fk_warning_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_warning_admin FOREIGN KEY (admin_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ============================================
-- 5. Sistem Logları
-- ============================================
CREATE TABLE IF NOT EXISTS system_logs (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id BIGINT UNSIGNED DEFAULT NULL,
  action VARCHAR(100) NOT NULL,
  ip_address VARCHAR(45) NOT NULL DEFAULT '0.0.0.0',
  user_agent TEXT DEFAULT NULL,
  details JSON DEFAULT NULL,
  risk_level ENUM('low','medium','high','critical') NOT NULL DEFAULT 'low',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_logs_user (user_id),
  KEY idx_logs_action (action),
  KEY idx_logs_created (created_at),
  KEY idx_logs_risk (risk_level)
) ENGINE=InnoDB;

-- ============================================
-- 6. Konum Geçmişi (Isı Haritası)
-- ============================================
CREATE TABLE IF NOT EXISTS location_history (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id BIGINT UNSIGNED NOT NULL,
  location POINT NOT NULL SRID 4326,
  recorded_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  SPATIAL INDEX idx_loc_hist_location (location),
  KEY idx_loc_hist_time (recorded_at),
  KEY idx_loc_hist_user (user_id),
  CONSTRAINT fk_loc_hist_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ============================================
-- 7. MySQL Event Scheduler: 30 Günlük Otomatik Temizlik
-- ============================================
-- Event Scheduler'ı etkinleştir
SET GLOBAL event_scheduler = ON;

-- Mevcut event varsa sil ve yeniden oluştur
DROP EVENT IF EXISTS evt_cleanup_location_history;

DELIMITER $$
CREATE EVENT evt_cleanup_location_history
  ON SCHEDULE EVERY 1 DAY
  STARTS (TIMESTAMP(CURDATE(), '03:00:00') + INTERVAL (CASE WHEN CURTIME() >= '03:00:00' THEN 1 ELSE 0 END) DAY)
  ON COMPLETION PRESERVE
  COMMENT 'Her gece 03:00 - 30 günden eski konum geçmişini temizle'
DO
BEGIN
  DELETE FROM location_history WHERE recorded_at < NOW() - INTERVAL 30 DAY;
  -- Temizlenen kayıt sayısını logla
  INSERT INTO system_logs (user_id, action, ip_address, details, risk_level)
  VALUES (NULL, 'auto_cleanup_location_history', '127.0.0.1', 
          JSON_OBJECT('deleted_rows', ROW_COUNT(), 'cleanup_date', NOW()), 'low');
END$$
DELIMITER ;

-- ============================================
-- 8. Eski logları temizle (90 gün)
-- ============================================
DROP EVENT IF EXISTS evt_cleanup_old_logs;

DELIMITER $$
CREATE EVENT evt_cleanup_old_logs
  ON SCHEDULE EVERY 1 DAY
  STARTS (TIMESTAMP(CURDATE(), '03:30:00') + INTERVAL (CASE WHEN CURTIME() >= '03:30:00' THEN 1 ELSE 0 END) DAY)
  ON COMPLETION PRESERVE
  COMMENT 'Her gece 03:30 - 90 günden eski system_logs temizle'
DO
BEGIN
  DELETE FROM system_logs WHERE created_at < NOW() - INTERVAL 90 DAY;
END$$
DELIMITER ;
