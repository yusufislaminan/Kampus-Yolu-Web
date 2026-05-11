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

    // Isı haritası için konum geçmişi kaydet (her 5 dakikada bir)
    try {
        $stCheck = $pdo->prepare(
            "SELECT id FROM location_history 
             WHERE user_id = ? AND recorded_at > NOW() - INTERVAL 5 MINUTE 
             LIMIT 1"
        );
        $stCheck->execute([$userId]);
        if (!$stCheck->fetch()) {
            $stHist = $pdo->prepare(
                "INSERT INTO location_history (user_id, location) 
                 VALUES (?, ST_GeomFromText(?, 4326))"
            );
            $stHist->execute([$userId, $pointWkt]);
        }
    } catch (Throwable $ignored) {
        // location_history tablosu yoksa sessizce devam et
    }

    json_response(200, ['success' => true]);
} catch (Throwable $e) {
    error_log('Location update error: ' . $e->getMessage());
    json_response(500, ['success' => false, 'error' => 'Location update failed: ' . $e->getMessage()]);
}
