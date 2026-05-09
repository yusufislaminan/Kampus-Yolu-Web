# Kampüs Yolu - Backend (PHP/MySQL)

## 1) MySQL database oluştur
- `schema.sql` dosyasını çalıştırın (MySQL 8.0+ gerekli - Spatial Index desteği için).
- Script otomatik olarak `kampus_yolu` veritabanını, tabloları ve seed data'yı oluşturur.

## 2) config.php
- `backend/config.php` içindeki `user/pass` değerlerini MySQL bilgilerinize göre güncelleyin.

## 3) API Endpoint'leri

| Endpoint | Metod | Açıklama |
|----------|-------|----------|
| `login.php` | POST | Giriş (userId, interests, display_name döndürür) |
| `register.php` | POST | Kayıt (display_name, gender kabul eder) |
| `update_location.php` | POST | Konum güncelleme (ST_GeomFromText POINT) |
| `get_nearby_users.php` | POST | Yakın kullanıcılar (ST_Distance_Sphere) |
| `get_interests.php` | POST/GET | Hobi/ilgi alanı listesi |
| `update_profile.php` | POST | Profil güncelleme (isim, cinsiyet, hobiler) |
| `create_match.php` | POST | Eşleştirme oluşturma |
| `get_matches.php` | POST | Eşleşme listesi |
| `send_message.php` | POST | Mesaj gönderme |
| `get_messages.php` | POST | Mesajları getirme |

## 4) Kurulum (XAMPP / Apache)
- `htdocs/.../FRONT/backend` klasörünü web server root'a bağlayın.
- MySQL 8.0+ sürümü gereklidir (SPATIAL INDEX ve ST_Distance_Sphere desteği).
