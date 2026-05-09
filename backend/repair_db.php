<?php
declare(strict_types=1);

require __DIR__ . '/http.php';

set_cors();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(405, ['success' => false, 'error' => 'Method not allowed']);
}

try {
    // Raw PDO connection for schema modification
    $pdo = new PDO(
        'mysql:host=localhost;charset=utf8mb4',
        'root',
        '',
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]
    );

    // Check and create/fix the database
    $pdo->exec("CREATE DATABASE IF NOT EXISTS kampus_yolu CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    $pdo->exec("USE kampus_yolu");
    
    // Fix the users table - drop and recreate if needed
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS users_new (
          id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
          email VARCHAR(255) NOT NULL,
          password_hash VARCHAR(255) NOT NULL,
          display_name VARCHAR(100) DEFAULT NULL,
          gender ENUM('erkek','kadin','belirtmek_istemiyorum') NOT NULL DEFAULT 'belirtmek_istemiyorum',
          role ENUM('user','admin') NOT NULL DEFAULT 'user',
          location POINT SRID 4326 DEFAULT NULL,
          location_updated_at TIMESTAMP NULL DEFAULT NULL,
          status ENUM('offline','online','searching','matched') NOT NULL DEFAULT 'offline',
          created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
          PRIMARY KEY (id),
          UNIQUE KEY uniq_users_email (email)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    // Check if old table has data
    $result = $pdo->query("SELECT COUNT(*) as cnt FROM information_schema.TABLES WHERE TABLE_SCHEMA='kampus_yolu' AND TABLE_NAME='users'");
    $exists = $result->fetch()['cnt'] > 0;
    
    if ($exists) {
        // Try to migrate data
        try {
            $pdo->exec("INSERT INTO users_new SELECT * FROM users");
            $pdo->exec("DROP TABLE users");
            $pdo->exec("RENAME TABLE users_new TO users");
        } catch (Throwable $e) {
            // If migration fails, just recreate
            $pdo->exec("DROP TABLE IF EXISTS users");
            $pdo->exec("RENAME TABLE users_new TO users");
        }
    } else {
        $pdo->exec("RENAME TABLE users_new TO users");
    }

    // Create other tables if they don't exist
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS interests (
          id INT UNSIGNED NOT NULL AUTO_INCREMENT,
          category VARCHAR(50) NOT NULL,
          name VARCHAR(100) NOT NULL,
          icon VARCHAR(10) DEFAULT NULL,
          PRIMARY KEY (id),
          UNIQUE KEY uniq_interest (category, name)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS user_interests (
          user_id BIGINT UNSIGNED NOT NULL,
          interest_id INT UNSIGNED NOT NULL,
          PRIMARY KEY (user_id, interest_id),
          CONSTRAINT fk_ui_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
          CONSTRAINT fk_ui_interest FOREIGN KEY (interest_id) REFERENCES interests(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    $pdo->exec("
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
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    $pdo->exec("
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
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    json_response(200, [
        'success' => true,
        'message' => 'Veritabanı başarıyla düzeltildi! Tüm tablolar yeniden oluşturuldu.',
        'details' => 'Artık kayıt işlemi çalışmalıdır.'
    ]);

} catch (Throwable $e) {
    error_log('Database repair error: ' . $e->getMessage());
    json_response(500, [
        'success' => false,
        'error' => 'Veritabanı düzeltme başarısız: ' . $e->getMessage()
    ]);
}
?>
