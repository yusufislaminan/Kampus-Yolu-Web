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
    // Sadece receiver (user2_id) reddedebilir
    $stmt = $pdo->prepare(
        "SELECT id FROM matches 
         WHERE id = ? AND user2_id = ? AND status = 'pending' LIMIT 1"
    );
    $stmt->execute([$matchId, $userId]);

    if (!$stmt->fetch()) {
        json_response(403, ['success' => false, 'error' => 'Bu isteği reddetme yetkiniz yok veya istek bulunamadı']);
    }

    $stmtUpdate = $pdo->prepare("UPDATE matches SET status = 'rejected' WHERE id = ?");
    $stmtUpdate->execute([$matchId]);

    json_response(200, ['success' => true, 'message' => 'Eşleşme reddedildi']);
} catch (Throwable $e) {
    error_log('Reject match error: ' . $e->getMessage());
    json_response(500, ['success' => false, 'error' => 'Eşleşme red hatası: ' . $e->getMessage()]);
}
