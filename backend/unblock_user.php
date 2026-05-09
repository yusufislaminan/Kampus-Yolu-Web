<?php
declare(strict_types=1);

require __DIR__ . '/http.php';
require __DIR__ . '/db.php';

set_cors();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(405, ['success' => false, 'error' => 'Method not allowed']);
}

$data = read_json_body();
$blockerId = isset($data['userId'])   ? (int) $data['userId']   : 0;
$blockedId = isset($data['blockedId']) ? (int) $data['blockedId'] : 0;

if ($blockerId <= 0 || $blockedId <= 0) {
    json_response(400, ['success' => false, 'error' => 'Geçersiz kullanıcı bilgileri']);
}

try {
    $stmt = $pdo->prepare("DELETE FROM blocked_users WHERE blocker_id = ? AND blocked_id = ?");
    $stmt->execute([$blockerId, $blockedId]);

    json_response(200, ['success' => true, 'message' => 'Engel kaldırıldı']);
} catch (Throwable $e) {
    error_log('Unblock user error: ' . $e->getMessage());
    json_response(500, ['success' => false, 'error' => 'Engel kaldırma hatası: ' . $e->getMessage()]);
}
