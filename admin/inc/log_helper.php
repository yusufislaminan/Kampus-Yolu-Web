<?php
/**
 * Kampüs Yolu — Log Helper
 * 
 * Tüm admin ve sistem eylemlerini system_logs tablosuna yazar.
 */
declare(strict_types=1);

/**
 * Sistem logu yaz
 *
 * @param PDO    $pdo     Veritabanı bağlantısı
 * @param int|null $userId  İlgili kullanıcı ID (opsiyonel)
 * @param string $action  Eylem adı (login_success, login_failed, user_suspended, vb.)
 * @param string $risk    Risk seviyesi: low, medium, high, critical
 * @param array  $details Ek detaylar (JSON olarak saklanır)
 */
function log_action(PDO $pdo, ?int $userId, string $action, string $risk = 'low', array $details = []): void {
    try {
        $ip = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        if (strpos($ip, ',') !== false) {
            $ip = trim(explode(',', $ip)[0]);
        }
        
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? null;
        
        $stmt = $pdo->prepare(
            "INSERT INTO system_logs (user_id, action, ip_address, user_agent, details, risk_level)
             VALUES (?, ?, ?, ?, ?, ?)"
        );
        $stmt->execute([
            $userId,
            $action,
            $ip,
            $userAgent ? mb_substr($userAgent, 0, 500) : null,
            !empty($details) ? json_encode($details, JSON_UNESCAPED_UNICODE) : null,
            $risk,
        ]);
    } catch (Throwable $e) {
        // Log yazma hatası uygulamayı durdurmasın
        error_log('Log write error: ' . $e->getMessage());
    }
}

/**
 * Sık kullanılan log aksiyonları
 */
function log_login_success(PDO $pdo, int $userId, string $email): void {
    log_action($pdo, $userId, 'login_success', 'low', ['email' => $email]);
}

function log_login_failed(PDO $pdo, ?int $userId, string $email): void {
    log_action($pdo, $userId, 'login_failed', 'medium', ['email' => $email]);
}

function log_user_suspended(PDO $pdo, int $adminId, int $targetId, string $reason = ''): void {
    log_action($pdo, $adminId, 'user_suspended', 'high', [
        'target_user_id' => $targetId,
        'reason' => $reason,
    ]);
}

function log_user_deleted(PDO $pdo, int $adminId, int $targetId, string $email): void {
    log_action($pdo, $adminId, 'user_deleted', 'critical', [
        'target_user_id' => $targetId,
        'target_email' => $email,
    ]);
}

function log_document_reviewed(PDO $pdo, int $adminId, int $docId, string $status): void {
    log_action($pdo, $adminId, 'document_reviewed', 'low', [
        'document_id' => $docId,
        'status' => $status,
    ]);
}

function log_complaint_updated(PDO $pdo, int $adminId, int $complaintId, string $status): void {
    log_action($pdo, $adminId, 'complaint_updated', 'low', [
        'complaint_id' => $complaintId,
        'status' => $status,
    ]);
}

function log_warning_sent(PDO $pdo, int $adminId, int $targetId, string $severity): void {
    log_action($pdo, $adminId, 'warning_sent', 'medium', [
        'target_user_id' => $targetId,
        'severity' => $severity,
    ]);
}
