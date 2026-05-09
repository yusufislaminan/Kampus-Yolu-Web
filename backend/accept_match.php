<?php
declare(strict_types=1);

require __DIR__ . '/http.php';
require __DIR__ . '/db.php';

set_cors();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(405, ['success' => false, 'error' => 'Method not allowed']);
}

$data = read_json_body();
$matchId = isset($data['matchId']) ? (int) $data['matchId'] : 0;
$userId  = isset($data['userId'])  ? (int) $data['userId']  : 0;

if ($matchId <= 0 || $userId <= 0) {
    json_response(400, ['success' => false, 'error' => 'Missing fields']);
}

try {
    // Sadece receiver (user2_id) kabul edebilir
    $stmt = $pdo->prepare(
        "SELECT id, user1_id, user2_id FROM matches 
         WHERE id = ? AND user2_id = ? AND status = 'pending' LIMIT 1"
    );
    $stmt->execute([$matchId, $userId]);
    $match = $stmt->fetch();

    if (!$match) {
        json_response(403, ['success' => false, 'error' => 'Bu isteği kabul etme yetkiniz yok veya istek bulunamadı']);
    }

    // Engelleme kontrolü
    $stmtBlock = $pdo->prepare(
        "SELECT id FROM blocked_users 
         WHERE (blocker_id = ? AND blocked_id = ?) OR (blocker_id = ? AND blocked_id = ?) LIMIT 1"
    );
    $stmtBlock->execute([$userId, (int)$match['user1_id'], (int)$match['user1_id'], $userId]);
    if ($stmtBlock->fetch()) {
        json_response(403, ['success' => false, 'error' => 'Bu kullanıcıyla etkileşim kurulamaz']);
    }

    $stmtUpdate = $pdo->prepare("UPDATE matches SET status = 'accepted' WHERE id = ?");
    $stmtUpdate->execute([$matchId]);

    json_response(200, ['success' => true, 'message' => 'Eşleşme kabul edildi']);
} catch (Throwable $e) {
    error_log('Accept match error: ' . $e->getMessage());
    json_response(500, ['success' => false, 'error' => 'Eşleşme kabul hatası: ' . $e->getMessage()]);
}
