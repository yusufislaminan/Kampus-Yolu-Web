<?php
declare(strict_types=1);

require __DIR__ . '/http.php';
require __DIR__ . '/db.php';

set_cors();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(405, ['success' => false, 'error' => 'Method not allowed']);
}

// --- 1. userId kontrolü ---
$userId = isset($_POST['userId']) ? (int) $_POST['userId'] : 0;
if ($userId <= 0) {
    json_response(400, ['success' => false, 'error' => 'Missing userId']);
}

// --- 2. Dosya varlık kontrolü ---
if (!isset($_FILES['profile_pic']) || $_FILES['profile_pic']['error'] !== UPLOAD_ERR_OK) {
    $errCode = $_FILES['profile_pic']['error'] ?? -1;
    $errMessages = [
        UPLOAD_ERR_INI_SIZE   => 'Dosya sunucu limitini aşıyor',
        UPLOAD_ERR_FORM_SIZE  => 'Dosya form limitini aşıyor',
        UPLOAD_ERR_PARTIAL    => 'Dosya kısmen yüklendi',
        UPLOAD_ERR_NO_FILE    => 'Dosya seçilmedi',
        UPLOAD_ERR_NO_TMP_DIR => 'Geçici klasör bulunamadı',
        UPLOAD_ERR_CANT_WRITE => 'Dosya yazılamadı',
    ];
    $msg = $errMessages[$errCode] ?? 'Dosya yükleme hatası';
    json_response(400, ['success' => false, 'error' => $msg]);
}

$tmpPath = $_FILES['profile_pic']['tmp_name'];
$fileSize = $_FILES['profile_pic']['size'];

// --- 3. Dosya boyutu kontrolü (maks 2MB) ---
$maxSize = 2 * 1024 * 1024; // 2MB
if ($fileSize > $maxSize) {
    json_response(400, ['success' => false, 'error' => 'Dosya boyutu 2MB\'ı aşamaz']);
}

// --- 4. Magic Bytes kontrolü (finfo ile gerçek MIME türü) ---
$finfo = finfo_open(FILEINFO_MIME_TYPE);
if (!$finfo) {
    json_response(500, ['success' => false, 'error' => 'Dosya doğrulama servisi başlatılamadı']);
}
$realMime = finfo_file($finfo, $tmpPath);
finfo_close($finfo);

$allowedMimes = [
    'image/jpeg' => 'jpg',
    'image/png'  => 'png',
];

if (!isset($allowedMimes[$realMime])) {
    json_response(400, ['success' => false, 'error' => 'Sadece JPEG ve PNG dosyaları kabul edilir. Algılanan tür: ' . $realMime]);
}

// --- 5. Ek güvenlik: dosyanın ilk byte'larını kontrol et (Magic Numbers) ---
$handle = fopen($tmpPath, 'rb');
$header = fread($handle, 8);
fclose($handle);

$isValidJpeg = (substr($header, 0, 3) === "\xFF\xD8\xFF");
$isValidPng  = (substr($header, 0, 4) === "\x89PNG");

if ($realMime === 'image/jpeg' && !$isValidJpeg) {
    json_response(400, ['success' => false, 'error' => 'Geçersiz JPEG dosyası']);
}
if ($realMime === 'image/png' && !$isValidPng) {
    json_response(400, ['success' => false, 'error' => 'Geçersiz PNG dosyası']);
}

// --- 6. UUID ile benzersiz dosya adı oluştur ---
$extension = $allowedMimes[$realMime];
$newFileName = bin2hex(random_bytes(16)) . '.' . $extension;

// --- 7. Depolama dizini oluştur ---
$uploadDir = $config['upload_path'] ?? (__DIR__ . '/uploads/avatars/');
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

$destination = $uploadDir . $newFileName;

// --- 8. Dosyayı taşı ---
if (!move_uploaded_file($tmpPath, $destination)) {
    json_response(500, ['success' => false, 'error' => 'Dosya kaydedilemedi']);
}

try {
    // --- 9. Eski profil resmini al ---
    $stmtOld = $pdo->prepare('SELECT profile_pic FROM users WHERE id = ? LIMIT 1');
    $stmtOld->execute([$userId]);
    $oldPic = $stmtOld->fetchColumn();

    // --- 10. Veritabanını güncelle ---
    $stmtUpdate = $pdo->prepare('UPDATE users SET profile_pic = ? WHERE id = ?');
    $stmtUpdate->execute([$newFileName, $userId]);

    // --- 11. Eski resmi sil ---
    if ($oldPic && file_exists($uploadDir . $oldPic)) {
        unlink($uploadDir . $oldPic);
    }

    json_response(200, [
        'success' => true,
        'profile_pic' => $newFileName,
        'message' => 'Profil resmi başarıyla yüklendi'
    ]);
} catch (Throwable $e) {
    // Yüklenen dosyayı temizle
    if (file_exists($destination)) {
        unlink($destination);
    }
    error_log('Profile pic upload error: ' . $e->getMessage());
    json_response(500, ['success' => false, 'error' => 'Profil resmi güncellenemedi: ' . $e->getMessage()]);
}
