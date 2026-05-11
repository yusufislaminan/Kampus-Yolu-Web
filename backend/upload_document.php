<?php
declare(strict_types=1);
require __DIR__ . '/http.php';
require __DIR__ . '/db.php';

set_cors();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(405, ['success' => false, 'error' => 'Method not allowed']);
}

$userId = isset($_POST['userId']) ? (int) $_POST['userId'] : 0;
$docType = isset($_POST['doc_type']) ? (string) $_POST['doc_type'] : '';

if ($userId <= 0) {
    json_response(400, ['success' => false, 'error' => 'Missing userId']);
}

$allowedTypes = ['ogrenci_belgesi', 'sabika_kaydi', 'kimlik', 'diger'];
if (!in_array($docType, $allowedTypes, true)) {
    json_response(400, ['success' => false, 'error' => 'Geçersiz belge türü']);
}

if (!isset($_FILES['document']) || $_FILES['document']['error'] !== UPLOAD_ERR_OK) {
    json_response(400, ['success' => false, 'error' => 'Dosya yükleme hatası']);
}

$tmpPath = $_FILES['document']['tmp_name'];
$fileSize = $_FILES['document']['size'];
$originalName = $_FILES['document']['name'];

// Boyut kontrolü (5MB)
if ($fileSize > 5 * 1024 * 1024) {
    json_response(400, ['success' => false, 'error' => 'Dosya boyutu 5MB\'ı aşamaz']);
}

// Magic byte kontrolü
$finfo = finfo_open(FILEINFO_MIME_TYPE);
$realMime = finfo_file($finfo, $tmpPath);
finfo_close($finfo);

$allowedMimes = [
    'image/jpeg' => 'jpg',
    'image/png' => 'png',
    'application/pdf' => 'pdf',
];

if (!isset($allowedMimes[$realMime])) {
    json_response(400, ['success' => false, 'error' => 'Sadece JPEG, PNG ve PDF dosyaları kabul edilir. Algılanan: ' . $realMime]);
}

// Ek magic byte kontrolü
$handle = fopen($tmpPath, 'rb');
$header = fread($handle, 8);
fclose($handle);

$isValidJpeg = (substr($header, 0, 3) === "\xFF\xD8\xFF");
$isValidPng  = (substr($header, 0, 4) === "\x89PNG");
$isValidPdf  = (substr($header, 0, 5) === "%PDF-");

if ($realMime === 'image/jpeg' && !$isValidJpeg) json_response(400, ['success' => false, 'error' => 'Geçersiz JPEG']);
if ($realMime === 'image/png' && !$isValidPng) json_response(400, ['success' => false, 'error' => 'Geçersiz PNG']);
if ($realMime === 'application/pdf' && !$isValidPdf) json_response(400, ['success' => false, 'error' => 'Geçersiz PDF']);

// Dosya kaydet
$config = require __DIR__ . '/config.php';
$uploadDir = $config['documents_path'] ?? (__DIR__ . '/uploads/documents/');
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

$ext = $allowedMimes[$realMime];
$newFileName = bin2hex(random_bytes(16)) . '.' . $ext;
$destination = $uploadDir . $newFileName;

if (!move_uploaded_file($tmpPath, $destination)) {
    json_response(500, ['success' => false, 'error' => 'Dosya kaydedilemedi']);
}

try {
    $stmt = $pdo->prepare(
        "INSERT INTO user_documents (user_id, doc_type, file_name, original_name, mime_type, file_size) 
         VALUES (?, ?, ?, ?, ?, ?)"
    );
    $stmt->execute([$userId, $docType, $newFileName, basename($originalName), $realMime, $fileSize]);
    // Dosya yolu veritabanına kaydedildi
    // Onay bekliyor durumunda ('pending') eklendi. Admin onaylarsa trust_level artacak.
    json_response(200, [
        'success' => true,
        'message' => 'Belge başarıyla yüklendi. Admin onayı bekleniyor.',
        'documentId' => (int) $pdo->lastInsertId(),
    ]);
} catch (Throwable $e) {
    if (file_exists($destination)) unlink($destination);
    error_log('Document upload error: ' . $e->getMessage());
    json_response(500, ['success' => false, 'error' => 'Belge yükleme hatası']);
}
