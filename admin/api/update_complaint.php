<?php
declare(strict_types=1);
require dirname(__DIR__) . '/inc/auth.php';
require dirname(__DIR__) . '/inc/log_helper.php';
require_admin();
require_csrf();

$data = json_decode(file_get_contents('php://input'), true) ?: [];
$complaintId = (int)($data['complaintId'] ?? 0);
$status = $data['status'] ?? '';
$note = trim($data['note'] ?? '');

if ($complaintId <= 0 || !in_array($status, ['open','investigating','resolved','dismissed'])) {
    admin_json(400, ['success' => false, 'error' => 'Geçersiz parametreler']);
}

$admin = current_admin();

try {
    $resolved = in_array($status, ['resolved','dismissed']);

    // Şikayeti yapan kullanıcıyı bul
    $stFetch = $pdo->prepare("SELECT reporter_id, category FROM complaints WHERE id = ?");
    $stFetch->execute([$complaintId]);
    $complaintData = $stFetch->fetch();

    $stmt = $pdo->prepare(
        "UPDATE complaints SET status = ?, admin_note = ?, resolved_by = ?, resolved_at = ? WHERE id = ?"
    );
    $stmt->execute([
        $status,
        $note ?: null,
        $resolved ? $admin['id'] : null,
        $resolved ? date('Y-m-d H:i:s') : null,
        $complaintId
    ]);

    // Şikayet edene sistem uyarısı olarak bilgi gönder
    if ($complaintData && $status !== 'open') {
        $durumMesaji = '';
        if ($status === 'investigating') $durumMesaji = 'inceleniyor.';
        elseif ($status === 'resolved') $durumMesaji = 'çözüldü ve gerekli işlemler yapıldı.';
        elseif ($status === 'dismissed') $durumMesaji = 'incelendi ancak ihlal tespit edilemedi (reddedildi).';
        
        $bilgiMesaji = "Yaptığınız '{$complaintData['category']}' kategorisindeki şikayetin durumu güncellendi: " . $durumMesaji;
        if ($note) {
            $bilgiMesaji .= "\nAdmin Notu: " . $note;
        }

        $stWarn = $pdo->prepare("INSERT INTO admin_warnings (user_id, admin_id, severity, message) VALUES (?, ?, 'info', ?)");
        $stWarn->execute([$complaintData['reporter_id'], $admin['id'], $bilgiMesaji]);
    }

    log_complaint_updated($pdo, $admin['id'], $complaintId, $status);
    admin_json(200, ['success' => true, 'message' => 'Şikayet güncellendi ve kullanıcıya bildirildi']);
} catch (Throwable $e) {
    admin_json(500, ['success' => false, 'error' => $e->getMessage()]);
}
