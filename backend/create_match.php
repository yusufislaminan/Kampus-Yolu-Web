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
$targetUserId = isset($data['targetUserId']) ? (int) $data['targetUserId'] : 0;

if ($userId <= 0 || $targetUserId <= 0 || $userId === $targetUserId) {
    json_response(400, ['success' => false, 'error' => 'Invalid user IDs']);
}

try {
    // Engelleme kontrolü
    $stmtBlock = $pdo->prepare(
        "SELECT id FROM blocked_users 
         WHERE (blocker_id = ? AND blocked_id = ?) OR (blocker_id = ? AND blocked_id = ?) LIMIT 1"
    );
    $stmtBlock->execute([$userId, $targetUserId, $targetUserId, $userId]);
    if ($stmtBlock->fetch()) {
        json_response(403, ['success' => false, 'error' => 'Bu kullanıcıyla etkileşim kurulamaz']);
    }

    // Daha önce eşleşme var mı kontrol et (pending veya accepted)
    $stmtCheck = $pdo->prepare(
        "SELECT id FROM matches 
         WHERE ((user1_id = ? AND user2_id = ?) OR (user1_id = ? AND user2_id = ?))
         AND status IN ('pending', 'accepted')
         LIMIT 1"
    );
    $stmtCheck->execute([$userId, $targetUserId, $targetUserId, $userId]);
    if ($stmtCheck->fetch()) {
        json_response(409, ['success' => false, 'error' => 'Match already exists']);
    }

    // Her iki kullanıcının konumunu al
    $stmtLoc = $pdo->prepare(
        "SELECT id, ST_X(location) AS lng, ST_Y(location) AS lat 
         FROM users WHERE id IN (?, ?) AND location IS NOT NULL"
    );
    $stmtLoc->execute([$userId, $targetUserId]);
    $locations = $stmtLoc->fetchAll();

    $loc1 = null;
    $loc2 = null;
    foreach ($locations as $l) {
        if ((int) $l['id'] === $userId)
            $loc1 = $l;
        if ((int) $l['id'] === $targetUserId)
            $loc2 = $l;
    }

    // Orta nokta hesaplama (PHP tarafında basit aritmetik ortalama)
    // Frontend Turf.js ile daha hassas hesap yapacak, burada DB kaydı için yeterli
    $midpointWkt = null;
    $midLat = null;
    $midLng = null;
    if ($loc1 && $loc2) {
        $midLng = ((float) $loc1['lng'] + (float) $loc2['lng']) / 2;
        $midLat = ((float) $loc1['lat'] + (float) $loc2['lat']) / 2;
        $midpointWkt = sprintf('POINT(%f %f)', $midLng, $midLat);
    }

    // Uyumluluk hesapla
    $stmtCompat = $pdo->prepare(
        "SELECT 
            (SELECT COUNT(*) FROM user_interests ui1 
             JOIN user_interests ui2 ON ui1.interest_id = ui2.interest_id 
             WHERE ui1.user_id = ? AND ui2.user_id = ?) AS common_count,
            (SELECT COUNT(*) FROM user_interests WHERE user_id = ?) AS total1,
            (SELECT COUNT(*) FROM user_interests WHERE user_id = ?) AS total2"
    );
    $stmtCompat->execute([$userId, $targetUserId, $userId, $targetUserId]);
    $compat = $stmtCompat->fetch();

    $common = (int) $compat['common_count'];
    $union = (int) $compat['total1'] + (int) $compat['total2'] - $common;
    $score = $union > 0 ? (int) round(($common / $union) * 100) : 0;

    // Eşleştirme oluştur
    if ($midpointWkt) {
        $stmtIns = $pdo->prepare(
            "INSERT INTO matches (user1_id, user2_id, midpoint, compatibility_score, status)
             VALUES (?, ?, ST_GeomFromText(?, 4326), ?, 'pending')"
        );
        $stmtIns->execute([$userId, $targetUserId, $midpointWkt, $score]);
    } else {
        $stmtIns = $pdo->prepare(
            "INSERT INTO matches (user1_id, user2_id, compatibility_score, status)
             VALUES (?, ?, ?, 'pending')"
        );
        $stmtIns->execute([$userId, $targetUserId, $score]);
    }

    $matchId = (int) $pdo->lastInsertId();

    json_response(200, [
        'success' => true,
        'matchId' => $matchId,
        'compatibility' => $score,
        'midpoint' => $midLat !== null ? ['lat' => $midLat, 'lng' => $midLng] : null,
    ]);
} catch (Throwable $e) {
    error_log('Match creation error: ' . $e->getMessage());
    json_response(500, ['success' => false, 'error' => 'Match creation failed: ' . $e->getMessage()]);
}
