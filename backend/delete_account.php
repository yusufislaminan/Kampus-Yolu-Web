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
$reasonCategory = $data['reason_category'] ?? 'diger';
$reasonText = $data['reason_text'] ?? '';

if ($userId <= 0) {
    json_response(400, ['success' => false, 'error' => 'Geçersiz parametreler']);
}

try {
    // 1. Ensure deletion_reasons table exists
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS deletion_reasons (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            user_email VARCHAR(255) NOT NULL,
            reason_category VARCHAR(100) NOT NULL,
            reason_text TEXT DEFAULT NULL,
            deleted_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id)
        ) ENGINE=InnoDB;
    ");

    $pdo->beginTransaction();

    // 2. Get user email
    $stmt = $pdo->prepare("SELECT email FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $email = $stmt->fetchColumn();

    if (!$email) {
        $pdo->rollBack();
        json_response(404, ['success' => false, 'error' => 'Kullanıcı bulunamadı']);
    }

    // 3. Save reason
    $stmt2 = $pdo->prepare("INSERT INTO deletion_reasons (user_email, reason_category, reason_text) VALUES (?, ?, ?)");
    $stmt2->execute([$email, $reasonCategory, $reasonText ?: null]);

    // 4. Delete user (ON DELETE CASCADE will handle the rest)
    $stmt3 = $pdo->prepare("DELETE FROM users WHERE id = ?");
    $stmt3->execute([$userId]);

    $pdo->commit();
    json_response(200, ['success' => true]);

} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('Delete account error: ' . $e->getMessage());
    json_response(500, ['success' => false, 'error' => 'Hesap silinirken hata oluştu.']);
}
