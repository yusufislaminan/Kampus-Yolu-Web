<?php
declare(strict_types=1);
require __DIR__ . '/http.php';
require __DIR__ . '/db.php';

set_cors();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(405, ['success' => false, 'error' => 'Method not allowed']);
}

$data = read_json_body();
$userId = (int)($data['userId'] ?? 0);

if ($userId <= 0) {
    json_response(400, ['success' => false, 'error' => 'Missing userId']);
}

try {
    $stmt = $pdo->prepare(
        "SELECT id, severity, message, is_read, created_at 
         FROM admin_warnings WHERE user_id = ? ORDER BY created_at DESC LIMIT 50"
    );
    $stmt->execute([$userId]);
    $warnings = $stmt->fetchAll();

    // Okunmamış sayısı
    $stUnread = $pdo->prepare("SELECT COUNT(*) FROM admin_warnings WHERE user_id = ? AND is_read = 0");
    $stUnread->execute([$userId]);
    $unreadCount = (int) $stUnread->fetchColumn();

    // Uyarıları okundu olarak işaretle
    $pdo->prepare("UPDATE admin_warnings SET is_read = 1 WHERE user_id = ? AND is_read = 0")->execute([$userId]);

    json_response(200, [
        'success' => true,
        'warnings' => $warnings,
        'unreadCount' => $unreadCount,
    ]);
} catch (Throwable $e) {
    json_response(200, ['success' => true, 'warnings' => [], 'unreadCount' => 0]);
}
