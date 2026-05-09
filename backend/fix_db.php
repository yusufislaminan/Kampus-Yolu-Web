<?php
declare(strict_types=1);

require __DIR__ . '/http.php';
require __DIR__ . '/db.php';

set_cors();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(405, ['success' => false, 'error' => 'Method not allowed']);
}

try {
    // Veritabanını düzelt
    $pdo->exec("ALTER TABLE users MODIFY COLUMN location POINT SRID 4326 DEFAULT NULL");
    
    json_response(200, [
        'success' => true, 
        'message' => 'Veritabanı başarıyla düzeltildi! Artık kayıt yapabilirsiniz.'
    ]);
} catch (Throwable $e) {
    error_log('Database fix error: ' . $e->getMessage());
    json_response(500, [
        'success' => false, 
        'error' => 'Veritabanı düzeltme başarısız: ' . $e->getMessage()
    ]);
}
?>
