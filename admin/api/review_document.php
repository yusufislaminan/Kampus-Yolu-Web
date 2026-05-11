<?php
declare(strict_types=1);
require dirname(__DIR__) . '/inc/auth.php';
require dirname(__DIR__) . '/inc/log_helper.php';
require_admin();
require_csrf();

$data = json_decode(file_get_contents('php://input'), true) ?: [];
$docId = (int)($data['docId'] ?? 0);
$status = $data['status'] ?? '';
$note = trim($data['note'] ?? '');

if ($docId <= 0 || !in_array($status, ['approved','rejected'])) {
    admin_json(400, ['success' => false, 'error' => 'Geçersiz parametreler']);
}

$admin = current_admin();

try {
    $pdo->beginTransaction();

    $stmt = $pdo->prepare(
        "UPDATE user_documents SET status = ?, admin_note = ?, reviewed_by = ?, reviewed_at = NOW() WHERE id = ?"
    );
    $stmt->execute([$status, $note ?: null, $admin['id'], $docId]);

    // Onaylandıysa trust_level güncelle
    if ($status === 'approved') {
        $stDoc = $pdo->prepare("SELECT user_id, doc_type FROM user_documents WHERE id = ?");
        $stDoc->execute([$docId]);
        $doc = $stDoc->fetch();
        if ($doc) {
            // Her onaylanan belge trust_level'ı 1 artırır (max 3)
            $pdo->prepare("UPDATE users SET trust_level = LEAST(trust_level + 1, 3) WHERE id = ?")->execute([$doc['user_id']]);
        }
    }

    $pdo->commit();
    log_document_reviewed($pdo, $admin['id'], $docId, $status);
    admin_json(200, ['success' => true, 'message' => 'Belge ' . ($status === 'approved' ? 'onaylandı' : 'reddedildi')]);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    admin_json(500, ['success' => false, 'error' => $e->getMessage()]);
}
