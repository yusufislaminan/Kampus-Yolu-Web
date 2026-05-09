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
$senderId = isset($data['senderId']) ? (int) $data['senderId'] : 0;
$content = isset($data['content']) ? trim((string) $data['content']) : '';

if ($matchId <= 0 || $senderId <= 0) {
    json_response(400, ['success' => false, 'error' => 'Missing fields']);
}
if ($content === '') {
    json_response(400, ['success' => false, 'error' => 'Message cannot be empty']);
}
if (mb_strlen($content) > 1000) {
    json_response(400, ['success' => false, 'error' => 'Message too long']);
}

try {
    $stmtCheck = $pdo->prepare(
        "SELECT id FROM matches WHERE id = ? AND (user1_id = ? OR user2_id = ?) AND status IN ('pending','accepted') LIMIT 1"
    );
    $stmtCheck->execute([$matchId, $senderId, $senderId]);
    if (!$stmtCheck->fetch()) {
        json_response(403, ['success' => false, 'error' => 'Not authorized']);
    }

    $stmtIns = $pdo->prepare("INSERT INTO messages (match_id, sender_id, content) VALUES (?, ?, ?)");
    $stmtIns->execute([$matchId, $senderId, $content]);
    $msgId = (int) $pdo->lastInsertId();

    $pdo->prepare("UPDATE matches SET status = 'accepted' WHERE id = ? AND status = 'pending'")->execute([$matchId]);

    json_response(200, ['success' => true, 'messageId' => $msgId]);
} catch (Throwable $e) {
    error_log('Send message error: ' . $e->getMessage());
    json_response(500, ['success' => false, 'error' => 'Send message failed: ' . $e->getMessage()]);
}
