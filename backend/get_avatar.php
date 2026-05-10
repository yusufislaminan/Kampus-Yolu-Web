<?php
declare(strict_types=1);

$config = require __DIR__ . '/config.php';

if (!isset($_GET['file'])) {
    http_response_code(400);
    exit('Missing file parameter');
}

$filename = basename($_GET['file']); // Güvenlik için basename kullanıyoruz
$uploadDir = $config['upload_path'] ?? (__DIR__ . '/uploads/avatars/');
$filePath = $uploadDir . $filename;

if (!file_exists($filePath) || !is_file($filePath)) {
    http_response_code(404);
    exit('File not found');
}

$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mimeType = finfo_file($finfo, $filePath);
finfo_close($finfo);

// Sadece görsellere izin ver
if (!in_array($mimeType, ['image/jpeg', 'image/png'])) {
    http_response_code(403);
    exit('Access denied');
}

header('Content-Type: ' . $mimeType);
header('Content-Length: ' . filesize($filePath));
readfile($filePath);
exit;
