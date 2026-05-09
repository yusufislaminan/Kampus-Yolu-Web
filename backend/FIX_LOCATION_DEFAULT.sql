-- FIX: Veritabanı 'location' alanı sorununu çöz
-- Bu SQL'i Wampserver'ın SQL alanına yapıştır ve çalıştır

ALTER TABLE users 
  MODIFY COLUMN location POINT SRID 4326 DEFAULT NULL;

-- Kontrol
SELECT * FROM users LIMIT 1;
