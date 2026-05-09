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
$radius = isset($data['radius']) ? (int) $data['radius'] : 2000; // varsayılan 2km (metre)
$genderPref = isset($data['genderPref']) ? (string) $data['genderPref'] : 'farketmez';

if ($userId <= 0) {
    json_response(400, ['success' => false, 'error' => 'Missing userId']);
}

// Radius sınırları: 500m - 5000m
if ($radius < 500)
    $radius = 500;
if ($radius > 5000)
    $radius = 5000;

try {
    // Önce mevcut kullanıcının konumunu al
    $stmtSelf = $pdo->prepare(
        "SELECT ST_X(location) AS lng, ST_Y(location) AS lat 
         FROM users WHERE id = ? AND location IS NOT NULL LIMIT 1"
    );
    $stmtSelf->execute([$userId]);
    $self = $stmtSelf->fetch();

    if (!$self) {
        json_response(200, ['success' => true, 'users' => []]);
    }

    $selfLng = (float) $self['lng'];
    $selfLat = (float) $self['lat'];
    $selfPointWkt = sprintf('POINT(%f %f)', $selfLng, $selfLat);

    // Cinsiyet filtresi SQL parçası
    $genderFilter = '';

    if ($genderPref === 'erkek') {
        $genderFilter = "AND u.gender = 'erkek'";
    } elseif ($genderPref === 'kadin') {
        $genderFilter = "AND u.gender = 'kadin'";
    }

    // Yakındaki aktif kullanıcıları bul (son 10 dakika içinde konum güncelleyenler)
    // Engelleme filtresi: karşılıklı engelleme kontrolü
    $sql = "
        SELECT 
            u.id,
            u.display_name,
            u.gender,
            u.status,
            u.profile_pic,
            ST_X(u.location) AS lng,
            ST_Y(u.location) AS lat,
            ST_Distance_Sphere(u.location, ST_GeomFromText(?, 4326)) AS distance_meters,
            (SELECT COUNT(*) FROM user_interests ui1 
             JOIN user_interests ui2 ON ui1.interest_id = ui2.interest_id 
             WHERE ui1.user_id = ? AND ui2.user_id = u.id) AS common_interests,
            (SELECT COUNT(*) FROM user_interests WHERE user_id = u.id) AS total_interests_other,
            (SELECT COUNT(*) FROM user_interests WHERE user_id = ?) AS total_interests_self
        FROM users u
        WHERE u.id != ?
          AND u.status IN ('searching', 'online')
          AND u.location IS NOT NULL
          AND u.location_updated_at > NOW() - INTERVAL 10 MINUTE
          AND ST_Distance_Sphere(u.location, ST_GeomFromText(?, 4326)) <= ?
          AND u.id NOT IN (SELECT blocked_id FROM blocked_users WHERE blocker_id = ?)
          AND u.id NOT IN (SELECT blocker_id FROM blocked_users WHERE blocked_id = ?)
          {$genderFilter}
        ORDER BY distance_meters ASC
        LIMIT 20
    ";

    $stmtNearby = $pdo->prepare($sql);
    $stmtNearby->execute([$selfPointWkt, $userId, $userId, $userId, $selfPointWkt, $radius, $userId, $userId]);
    $nearbyUsers = $stmtNearby->fetchAll();

    $result = [];
    foreach ($nearbyUsers as $row) {
        // Uyumluluk hesapla: ortak ilgi alanları / birleşim × 100
        $common = (int) $row['common_interests'];
        $totalOther = (int) $row['total_interests_other'];
        $totalSelf = (int) $row['total_interests_self'];
        $union = $totalSelf + $totalOther - $common;
        $compatibility = $union > 0 ? round(($common / $union) * 100) : 0;

        // Kullanıcının ilgi alanlarını da çek
        $stmtInterests = $pdo->prepare(
            "SELECT i.name, i.icon, i.category FROM user_interests ui 
             JOIN interests i ON ui.interest_id = i.id 
             WHERE ui.user_id = ?"
        );
        $stmtInterests->execute([(int) $row['id']]);
        $interests = $stmtInterests->fetchAll();

        $result[] = [
            'id' => (int) $row['id'],
            'display_name' => $row['display_name'] ?? 'Anonim',
            'gender' => $row['gender'],
            'status' => $row['status'],
            'profile_pic' => $row['profile_pic'] ?? null,
            'lat' => (float) $row['lat'],
            'lng' => (float) $row['lng'],
            'distance' => round((float) $row['distance_meters']),
            'compatibility' => (int) $compatibility,
            'common_interests' => $common,
            'interests' => $interests,
        ];
    }

    json_response(200, ['success' => true, 'users' => $result]);
} catch (Throwable $e) {
    json_response(500, ['success' => false, 'error' => 'Nearby search failed: ' . $e->getMessage()]);
}

