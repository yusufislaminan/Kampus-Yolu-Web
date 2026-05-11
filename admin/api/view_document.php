<?php
declare(strict_types=1);
require dirname(__DIR__) . '/inc/auth.php';
require_admin();

$docId = (int)($_GET['id'] ?? 0);
if ($docId <= 0) {
    die("Geçersiz belge ID.");
}

$stmt = $pdo->prepare("SELECT file_name, original_name, mime_type FROM user_documents WHERE id = ?");
$stmt->execute([$docId]);
$doc = $stmt->fetch();

if (!$doc) {
    die("Belge bulunamadı.");
}

$config = require dirname(__DIR__, 2) . '/backend/config.php';
$uploadDir = $config['documents_path'] ?? (dirname(__DIR__, 2) . '/backend/uploads/documents/');
$file = $uploadDir . $doc['file_name'];
if (!file_exists($file)) {
    die("Fiziksel dosya sunucuda bulunamadı.");
}

header('Content-Type: ' . $doc['mime_type']);
header('Content-Disposition: inline; filename="' . basename($doc['original_name']) . '"');
header('Content-Length: ' . filesize($file));
readfile($file);
exit;
