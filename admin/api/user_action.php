<?php
declare(strict_types=1);
require dirname(__DIR__) . '/inc/auth.php';
require dirname(__DIR__) . '/inc/log_helper.php';
require_admin();
require_csrf();

$data = json_decode(file_get_contents('php://input'), true) ?: [];
$userId = (int)($data['userId'] ?? 0);
$action = $data['action'] ?? '';

if ($userId <= 0 || !in_array($action, ['suspend','unsuspend','delete','make_admin','remove_admin'])) {
    admin_json(400, ['success' => false, 'error' => 'Geçersiz parametreler']);
}

$admin = current_admin();

// Admin kendini silemesin veya yetkisini alamamasın
if ($userId === $admin['id'] && in_array($action, ['delete', 'remove_admin', 'suspend'])) {
    admin_json(403, ['success' => false, 'error' => 'Kendi hesabınız üzerinde bu işlemi yapamazsınız']);
}

try {
    if ($action === 'suspend') {
        $pdo->prepare("UPDATE users SET is_suspended = 1 WHERE id = ?")->execute([$userId]);
        log_user_suspended($pdo, $admin['id'], $userId);
        admin_json(200, ['success' => true, 'message' => 'Kullanıcı askıya alındı']);
    } elseif ($action === 'unsuspend') {
        $pdo->prepare("UPDATE users SET is_suspended = 0 WHERE id = ?")->execute([$userId]);
        log_action($pdo, $admin['id'], 'user_unsuspended', 'medium', ['target_user_id' => $userId]);
        admin_json(200, ['success' => true, 'message' => 'Askı kaldırıldı']);
    } elseif ($action === 'delete') {
        $st = $pdo->prepare("SELECT email FROM users WHERE id = ? LIMIT 1");
        $st->execute([$userId]);
        $email = $st->fetchColumn() ?: 'unknown';
        $pdo->prepare("DELETE FROM users WHERE id = ? AND role != 'admin'")->execute([$userId]);
        log_user_deleted($pdo, $admin['id'], $userId, $email);
        admin_json(200, ['success' => true, 'message' => 'Kullanıcı silindi']);
    } elseif ($action === 'make_admin') {
        $pdo->prepare("UPDATE users SET role = 'admin' WHERE id = ?")->execute([$userId]);
        log_action($pdo, $admin['id'], 'made_admin', 'high', ['target_user_id' => $userId]);
        admin_json(200, ['success' => true, 'message' => 'Kullanıcıya admin yetkisi verildi']);
    } elseif ($action === 'remove_admin') {
        $pdo->prepare("UPDATE users SET role = 'user' WHERE id = ?")->execute([$userId]);
        log_action($pdo, $admin['id'], 'removed_admin', 'high', ['target_user_id' => $userId]);
        admin_json(200, ['success' => true, 'message' => 'Kullanıcının admin yetkisi alındı']);
    }
} catch (Throwable $e) {
    admin_json(500, ['success' => false, 'error' => $e->getMessage()]);
}
