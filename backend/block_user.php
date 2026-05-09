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

if ($blockerId <= 0 || $blockedId <= 0 || $blockerId === $blockedId) {
    json_response(400, ['success' => false, 'error' => 'Geçersiz kullanıcı bilgileri']);
}

try {
    $pdo->beginTransaction();

    // 1. Engelleme kaydı oluştur
    $stmtBlock = $pdo->prepare(
        "INSERT IGNORE INTO blocked_users (blocker_id, blocked_id) VALUES (?, ?)"
    );
    $stmtBlock->execute([$blockerId, $blockedId]);

    // 2. Aralarındaki aktif eşleşmeleri iptal et (pending veya accepted)
    $stmtMatches = $pdo->prepare(
        "UPDATE matches SET status = 'rejected' 
         WHERE ((user1_id = ? AND user2_id = ?) OR (user1_id = ? AND user2_id = ?))
         AND status IN ('pending', 'accepted')"
    );
    $stmtMatches->execute([$blockerId, $blockedId, $blockedId, $blockerId]);

    $pdo->commit();

    json_response(200, ['success' => true, 'message' => 'Kullanıcı engellendi']);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log('Block user error: ' . $e->getMessage());
    json_response(500, ['success' => false, 'error' => 'Engelleme hatası: ' . $e->getMessage()]);
}
