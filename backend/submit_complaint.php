<?php
declare(strict_types=1);
require __DIR__ . '/http.php';
require __DIR__ . '/db.php';

set_cors();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(405, ['success' => false, 'error' => 'Method not allowed']);
}

$data = read_json_body();
$reporterId = (int)($data['userId'] ?? 0);
$reportedId = (int)($data['reportedId'] ?? 0);
$category = $data['category'] ?? '';
$description = trim($data['description'] ?? '');

if ($reporterId <= 0 || $reportedId <= 0 || $reporterId === $reportedId) {
    json_response(400, ['success' => false, 'error' => 'Geçersiz kullanıcı bilgileri']);
}

$allowedCats = ['uygunsuz_davranis','gelmeme','spam','sahte_profil','diger'];
if (!in_array($category, $allowedCats, true)) {
    json_response(400, ['success' => false, 'error' => 'Geçersiz şikayet kategorisi']);
}

if (mb_strlen($description) < 10) {
    json_response(400, ['success' => false, 'error' => 'Açıklama en az 10 karakter olmalıdır']);
}

if (mb_strlen($description) > 2000) {
    json_response(400, ['success' => false, 'error' => 'Açıklama 2000 karakteri aşamaz']);
}

try {
    // Aynı şikayetin tekrar gönderilmesini engelle (son 24 saat)
    $stCheck = $pdo->prepare(
        "SELECT id FROM complaints WHERE reporter_id = ? AND reported_id = ? AND created_at > NOW() - INTERVAL 24 HOUR LIMIT 1"
    );
    $stCheck->execute([$reporterId, $reportedId]);
    if ($stCheck->fetch()) {
        json_response(429, ['success' => false, 'error' => 'Bu kullanıcı hakkında son 24 saat içinde zaten şikayet gönderdiniz']);
    }

    $stmt = $pdo->prepare(
        "INSERT INTO complaints (reporter_id, reported_id, category, description) VALUES (?, ?, ?, ?)"
    );
    $stmt->execute([$reporterId, $reportedId, $category, $description]);

    json_response(200, ['success' => true, 'message' => 'Şikayetiniz alındı. Admin ekibimiz inceleyecektir.']);
} catch (Throwable $e) {
    error_log('Complaint submit error: ' . $e->getMessage());
    json_response(500, ['success' => false, 'error' => 'Şikayet gönderilemedi']);
}
