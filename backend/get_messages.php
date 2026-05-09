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
$userId = isset($data['userId']) ? (int) $data['userId'] : 0;
$afterId = isset($data['afterId']) ? (int) $data['afterId'] : 0;

if ($matchId <= 0 || $userId <= 0) {
    json_response(400, ['success' => false, 'error' => 'Missing fields']);
}

try {
    // Yetki kontrolü
    $stmtCheck = $pdo->prepare(
        "SELECT id FROM matches WHERE id = ? AND (user1_id = ? OR user2_id = ?) LIMIT 1"
    );
    $stmtCheck->execute([$matchId, $userId, $userId]);
    if (!$stmtCheck->fetch()) {
        json_response(403, ['success' => false, 'error' => 'Not authorized']);
    }

    // Mesajları çek
    $sql = "SELECT id, sender_id, content, is_read, created_at FROM messages WHERE match_id = ?";
    $params = [$matchId];

    if ($afterId > 0) {
        $sql .= " AND id > ?";
        $params[] = $afterId;
    }

    $sql .= " ORDER BY created_at ASC LIMIT 100";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $messages = $stmt->fetchAll();

    // Karşı tarafın mesajlarını okundu olarak işaretle
    $stmtRead = $pdo->prepare(
        "UPDATE messages SET is_read = 1 WHERE match_id = ? AND sender_id != ? AND is_read = 0"
    );
    $stmtRead->execute([$matchId, $userId]);

    $result = [];
    foreach ($messages as $msg) {
        $result[] = [
            'id' => (int) $msg['id'],
            'senderId' => (int) $msg['sender_id'],
            'content' => $msg['content'],
            'isRead' => (bool) $msg['is_read'],
            'createdAt' => $msg['created_at'],
            'isMine' => (int) $msg['sender_id'] === $userId,
        ];
    }

    json_response(200, ['success' => true, 'messages' => $result]);
} catch (Throwable $e) {
    error_log('Get messages error: ' . $e->getMessage());
    json_response(500, ['success' => false, 'error' => 'Failed to load messages: ' . $e->getMessage()]);
}
