<?php
declare(strict_types=1);

require __DIR__ . '/http.php';
require __DIR__ . '/db.php';

set_cors();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(405, ['success' => false, 'error' => 'Method not allowed']);
}

$data = read_json_body();
$userId = isset($data['userId']) ? (int) $data['userId'] : 0;
$lat = isset($data['latitude']) ? (float) $data['latitude'] : null;
$lng = isset($data['longitude']) ? (float) $data['longitude'] : null;

if ($userId <= 0) {
    json_response(400, ['success' => false, 'error' => 'Missing userId']);
}

if ($lat === null || $lng === null || $lat < -90 || $lat > 90 || $lng < -180 || $lng > 180) {
    json_response(400, ['success' => false, 'error' => 'Invalid coordinates']);
}

try {
    // MySQL Spatial: POINT(boylam enlem) formatında kaydet
    // Not: MySQL POINT formatı POINT(X Y) = POINT(lng lat) şeklindedir
    $stmt = $pdo->prepare(
        "UPDATE users 
         SET location = ST_GeomFromText(:point, 4326), 
             location_updated_at = NOW(), 
             status = 'searching' 
         WHERE id = :uid"
    );
    $pointWkt = sprintf('POINT(%f %f)', $lng, $lat);
    $stmt->execute([
        ':point' => $pointWkt,
        ':uid' => $userId,
    ]);

    json_response(200, ['success' => true]);
} catch (Throwable $e) {
    error_log('Location update error: ' . $e->getMessage());
    json_response(500, ['success' => false, 'error' => 'Location update failed: ' . $e->getMessage()]);
}
