-- MySQL schema: Kampüs Yolu
-- Çalıştırma: önce DB oluşturun, sonra bu scripti çalıştırın.

CREATE DATABASE IF NOT EXISTS kampus_yolu CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE kampus_yolu;

-- ============================================
-- KULLANICILAR TABLOSU (Spatial konum desteği)
-- ============================================
CREATE TABLE IF NOT EXISTS users (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  email VARCHAR(255) NOT NULL,
  password_hash VARCHAR(255) NOT NULL,
  display_name VARCHAR(100) DEFAULT NULL,
  gender ENUM('erkek','kadin','belirtmek_istemiyorum') NOT NULL DEFAULT 'belirtmek_istemiyorum',
  profile_pic VARCHAR(255) DEFAULT NULL,
  role ENUM('user','admin') NOT NULL DEFAULT 'user',
  -- MySQL Spatial: Kullanıcı konumu
  -- ST_GeomFromText('POINT(boylam enlem)', 4326) şeklinde eklenir
  location POINT SRID 4326 DEFAULT NULL,
  location_updated_at TIMESTAMP NULL DEFAULT NULL,
  -- Kullanıcı durumu
  status ENUM('offline','online','searching','matched') NOT NULL DEFAULT 'offline',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uniq_users_email (email)
) ENGINE=InnoDB;

-- ============================================
-- İLGİ ALANLARI / HOBİLER HAVUZU
-- ============================================
CREATE TABLE IF NOT EXISTS interests (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  category VARCHAR(50) NOT NULL,
  name VARCHAR(100) NOT NULL,
  icon VARCHAR(10) DEFAULT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uniq_interest (category, name)
) ENGINE=InnoDB;

-- ============================================
-- KULLANICI - İLGİ ALANI İLİŞKİSİ (Many-to-Many)
-- ============================================
CREATE TABLE IF NOT EXISTS user_interests (
  user_id BIGINT UNSIGNED NOT NULL,
  interest_id INT UNSIGNED NOT NULL,
  PRIMARY KEY (user_id, interest_id),
  CONSTRAINT fk_ui_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_ui_interest FOREIGN KEY (interest_id) REFERENCES interests(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ============================================
-- EŞLEŞTİRME TABLOSU
-- ============================================
CREATE TABLE IF NOT EXISTS matches (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  user1_id BIGINT UNSIGNED NOT NULL,
  user2_id BIGINT UNSIGNED NOT NULL,
  midpoint POINT SRID 4326 DEFAULT NULL,
  compatibility_score TINYINT UNSIGNED NOT NULL DEFAULT 0,
  status ENUM('pending','accepted','rejected','completed') NOT NULL DEFAULT 'pending',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_matches_user1 (user1_id),
  KEY idx_matches_user2 (user2_id),
  CONSTRAINT fk_matches_user1 FOREIGN KEY (user1_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_matches_user2 FOREIGN KEY (user2_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ============================================
-- MESAJLAŞMA TABLOSU
-- ============================================
CREATE TABLE IF NOT EXISTS messages (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  match_id BIGINT UNSIGNED NOT NULL,
  sender_id BIGINT UNSIGNED NOT NULL,
  content TEXT NOT NULL,
  is_read TINYINT(1) NOT NULL DEFAULT 0,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_messages_match (match_id),
  KEY idx_messages_sender (sender_id),
  CONSTRAINT fk_messages_match FOREIGN KEY (match_id) REFERENCES matches(id) ON DELETE CASCADE,
  CONSTRAINT fk_messages_sender FOREIGN KEY (sender_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ============================================
-- ENGELLEME TABLOSU
-- ============================================
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

-- ============================================
-- SEED DATA: Önceden tanımlı ilgi alanları
-- ============================================
INSERT IGNORE INTO interests (category, name, icon) VALUES
-- 🎵 Müzik Türleri
('muzik', 'Pop', '🎵'),
('muzik', 'Rock', '🎸'),
('muzik', 'Rap', '🎤'),
('muzik', 'Klasik', '🎻'),
('muzik', 'Jazz', '🎷'),
('muzik', 'Indie', '🎶'),
('muzik', 'Metal', '🤘'),
('muzik', 'Elektronik', '🎧'),
-- ⚽ Sporlar
('spor', 'Futbol', '⚽'),
('spor', 'Basketbol', '🏀'),
('spor', 'Voleybol', '🏐'),
('spor', 'Masa Tenisi', '🏓'),
('spor', 'Yüzme', '🏊'),
('spor', 'Koşu', '🏃'),
('spor', 'Bisiklet', '🚴'),
('spor', 'Yoga', '🧘'),
-- 📚 Akademik İlgiler
('akademik', 'Yazılım', '💻'),
('akademik', 'Matematik', '📐'),
('akademik', 'Fizik', '⚛️'),
('akademik', 'Edebiyat', '📖'),
('akademik', 'Tarih', '🏛️'),
('akademik', 'Felsefe', '🤔'),
('akademik', 'Psikoloji', '🧠'),
('akademik', 'Ekonomi', '📊'),
-- 🎮 Hobiler
('hobi', 'Oyun', '🎮'),
('hobi', 'Film/Dizi', '🎬'),
('hobi', 'Fotoğrafçılık', '📷'),
('hobi', 'Seyahat', '✈️'),
('hobi', 'Yemek Yapma', '🍳'),
('hobi', 'Kitap Okuma', '📚'),
('hobi', 'Resim', '🎨'),
('hobi', 'Müzik Aleti', '🪕'),
-- ☕ Yaşam Tarzı
('yasam', 'Kahve Tutkunu', '☕'),
('yasam', 'Doğa Sever', '🌿'),
('yasam', 'Gece Kuşu', '🦉'),
('yasam', 'Erken Kalkan', '🌅'),
-- ============================================
-- HESAP SİLME NEDENLERİ
-- ============================================
CREATE TABLE IF NOT EXISTS deletion_reasons (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_email VARCHAR(255) NOT NULL,
  reason_category VARCHAR(100) NOT NULL,
  reason_text TEXT DEFAULT NULL,
  deleted_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id)
) ENGINE=InnoDB;
