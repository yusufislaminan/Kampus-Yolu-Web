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

if ($userId <= 0) {
    json_response(400, ['success' => false, 'error' => 'Missing userId']);
}

try {
    // Kullanıcının tüm eşleşmelerini çek (kendisi user1 veya user2 olan)
    $stmt = $pdo->prepare("
        SELECT 
            m.id AS match_id,
            m.user1_id,
            m.user2_id,
            m.compatibility_score,
            m.status,
            m.created_at,
            CASE WHEN m.midpoint IS NOT NULL THEN ST_X(m.midpoint) ELSE NULL END AS mid_lng,
            CASE WHEN m.midpoint IS NOT NULL THEN ST_Y(m.midpoint) ELSE NULL END AS mid_lat,
            -- Karşı tarafın bilgileri
            CASE WHEN m.user1_id = ? THEN u2.id ELSE u1.id END AS other_user_id,
            CASE WHEN m.user1_id = ? THEN u2.display_name ELSE u1.display_name END AS other_display_name,
            CASE WHEN m.user1_id = ? THEN u2.gender ELSE u1.gender END AS other_gender,
            CASE WHEN m.user1_id = ? THEN u2.profile_pic ELSE u1.profile_pic END AS other_profile_pic,
            CASE WHEN m.user1_id = ? THEN ST_Y(u2.location) ELSE ST_Y(u1.location) END AS other_lat,
            CASE WHEN m.user1_id = ? THEN ST_X(u2.location) ELSE ST_X(u1.location) END AS other_lng,
            -- Okunmamış mesaj sayısı
            (SELECT COUNT(*) FROM messages msg 
             WHERE msg.match_id = m.id AND msg.sender_id != ? AND msg.is_read = 0) AS unread_count
        FROM matches m
        JOIN users u1 ON m.user1_id = u1.id
        JOIN users u2 ON m.user2_id = u2.id
        WHERE (m.user1_id = ? OR m.user2_id = ?)
          AND m.status IN ('pending', 'accepted')
        ORDER BY m.created_at DESC
    ");

    $stmt->execute([$userId, $userId, $userId, $userId, $userId, $userId, $userId, $userId, $userId]);
    $matches = $stmt->fetchAll();

    $result = [];
    foreach ($matches as $row) {
        $result[] = [
            'matchId' => (int) $row['match_id'],
            'otherUserId' => (int) $row['other_user_id'],
            'otherDisplayName' => $row['other_display_name'] ?? 'Anonim',
            'otherGender' => $row['other_gender'],
            'otherProfilePic' => $row['other_profile_pic'] ?? null,
            'otherLat' => $row['other_lat'] !== null ? (float) $row['other_lat'] : null,
            'otherLng' => $row['other_lng'] !== null ? (float) $row['other_lng'] : null,
            'compatibility' => (int) $row['compatibility_score'],
            'status' => $row['status'],
            'midpoint' => $row['mid_lat'] !== null ? ['lat' => (float) $row['mid_lat'], 'lng' => (float) $row['mid_lng']] : null,
            'unreadCount' => (int) $row['unread_count'],
            'createdAt' => $row['created_at'],
            'isRequester' => (int) $row['user1_id'] === $userId,
        ];
    }

    json_response(200, ['success' => true, 'matches' => $result]);
} catch (Throwable $e) {
    error_log('Get matches error: ' . $e->getMessage());
    json_response(500, ['success' => false, 'error' => 'Failed to load matches: ' . $e->getMessage()]);
}
