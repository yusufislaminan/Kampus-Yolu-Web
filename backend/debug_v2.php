<?php
declare(strict_types=1);
require __DIR__ . '/db.php';
header('Content-Type: application/json; charset=utf-8');

$result = [];

// 1. profile_pic sütunu var mı?
try {
    $stmt = $pdo->query("SHOW COLUMNS FROM users LIKE 'profile_pic'");
    $col = $stmt->fetch();
    $result['profile_pic_column_exists'] = $col ? true : false;
    $result['profile_pic_column_info'] = $col ?: 'NOT FOUND';
} catch (Throwable $e) {
    $result['profile_pic_column_error'] = $e->getMessage();
}

// 2. blocked_users tablosu var mı?
try {
    $stmt = $pdo->query("SHOW TABLES LIKE 'blocked_users'");
    $result['blocked_users_table_exists'] = $stmt->fetch() ? true : false;
} catch (Throwable $e) {
    $result['blocked_users_table_error'] = $e->getMessage();
}

// 3. uploads dizini yazılabilir mi?
$config = require __DIR__ . '/config.php';
$uploadDir = $config['upload_path'] ?? (__DIR__ . '/uploads/avatars/');
$result['upload_dir_exists'] = is_dir($uploadDir);
$result['upload_dir_writable'] = is_writable($uploadDir);
$result['upload_dir_path'] = $uploadDir;

// 4. PHP finfo eklentisi var mı?
$result['finfo_available'] = function_exists('finfo_open');

// 5. Mevcut kullanıcıları ve profile_pic değerlerini kontrol et
try {
    $stmt = $pdo->query("SELECT id, display_name, profile_pic FROM users LIMIT 5");
    $result['users_sample'] = $stmt->fetchAll();
} catch (Throwable $e) {
    $result['users_error'] = $e->getMessage();
}

echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
