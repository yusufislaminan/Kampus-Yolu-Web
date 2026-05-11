<?php
declare(strict_types=1);
require dirname(__DIR__) . '/inc/auth.php';
require dirname(__DIR__) . '/inc/log_helper.php';
require_admin();
require_csrf();

$data = json_decode(file_get_contents('php://input'), true) ?: [];
$userId = (int)($data['userId'] ?? 0);
$severity = $data['severity'] ?? 'warning';
$message = trim($data['message'] ?? '');

if ($userId <= 0 || $message === '') {
    admin_json(400, ['success' => false, 'error' => 'Kullanıcı ID ve mesaj gereklidir']);
}
if (!in_array($severity, ['info','warning','critical'])) $severity = 'warning';

$admin = current_admin();

try {
    $stmt = $pdo->prepare("INSERT INTO admin_warnings (user_id, admin_id, severity, message) VALUES (?, ?, ?, ?)");
    $stmt->execute([$userId, $admin['id'], $severity, $message]);
    log_warning_sent($pdo, $admin['id'], $userId, $severity);
    admin_json(200, ['success' => true, 'message' => 'Uyarı gönderildi']);
} catch (Throwable $e) {
    admin_json(500, ['success' => false, 'error' => $e->getMessage()]);
}
