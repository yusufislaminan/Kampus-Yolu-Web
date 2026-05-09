<?php
declare(strict_types=1);

require __DIR__ . '/http.php';
require __DIR__ . '/db.php';

set_cors();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(405, ['success' => false, 'error' => 'Method not allowed']);
}

$data = read_json_body();
$userId = isset($data['userId']) ? (int) $data['userId'] : 0;

if ($userId <= 0) {
    json_response(400, ['success' => false, 'error' => 'Missing userId']);
}

try {
    $stmt = $pdo->prepare(
        "SELECT b.id AS block_id, b.blocked_id, u.display_name, u.profile_pic, b.created_at
         FROM blocked_users b
         JOIN users u ON b.blocked_id = u.id
         WHERE b.blocker_id = ?
         ORDER BY b.created_at DESC"
    );
    $stmt->execute([$userId]);
    $blocked = $stmt->fetchAll();

    json_response(200, ['success' => true, 'blocked' => $blocked]);
} catch (Throwable $e) {
    error_log('Get blocked users error: ' . $e->getMessage());
    json_response(500, ['success' => false, 'error' => 'Engelli liste hatası: ' . $e->getMessage()]);
}
