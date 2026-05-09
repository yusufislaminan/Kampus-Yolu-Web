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
$displayName = isset($data['display_name']) ? trim((string) $data['display_name']) : '';
$gender = isset($data['gender']) ? (string) $data['gender'] : '';
$interestIds = isset($data['interest_ids']) && is_array($data['interest_ids']) ? $data['interest_ids'] : [];

if ($userId <= 0) {
    json_response(400, ['success' => false, 'error' => 'Missing userId']);
}

$allowedGenders = ['erkek', 'kadin', 'belirtmek_istemiyorum'];
if ($gender !== '' && !in_array($gender, $allowedGenders, true)) {
    $gender = 'belirtmek_istemiyorum';
}

try {
    $pdo->beginTransaction();

    // Kullanıcı bilgilerini güncelle
    $updates = [];
    $params = [];

    if ($displayName !== '') {
        $updates[] = 'display_name = ?';
        $params[] = $displayName;
    }
    if ($gender !== '') {
        $updates[] = 'gender = ?';
        $params[] = $gender;
    }

    if (!empty($updates)) {
        $params[] = $userId;
        $sql = 'UPDATE users SET ' . implode(', ', $updates) . ' WHERE id = ?';
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
    }

    // İlgi alanlarını güncelle (replace stratejisi: eskilerini sil, yenilerini ekle)
    $stmtDel = $pdo->prepare('DELETE FROM user_interests WHERE user_id = ?');
    $stmtDel->execute([$userId]);

    if (!empty($interestIds)) {
        $stmtIns = $pdo->prepare('INSERT INTO user_interests (user_id, interest_id) VALUES (?, ?)');
        foreach ($interestIds as $iid) {
            $iid = (int) $iid;
            if ($iid > 0) {
                $stmtIns->execute([$userId, $iid]);
            }
        }
    }

    $pdo->commit();

    // Güncellenmiş profili döndür
    $stmtUser = $pdo->prepare('SELECT id, email, display_name, gender, profile_pic FROM users WHERE id = ? LIMIT 1');
    $stmtUser->execute([$userId]);
    $user = $stmtUser->fetch();

    $stmtInt = $pdo->prepare(
        'SELECT i.id, i.name, i.icon, i.category FROM user_interests ui 
         JOIN interests i ON ui.interest_id = i.id WHERE ui.user_id = ?'
    );
    $stmtInt->execute([$userId]);
    $interests = $stmtInt->fetchAll();

    json_response(200, [
        'success' => true,
        'user' => $user,
        'interests' => $interests,
    ]);
} catch (Throwable $e) {
    if ($pdo->inTransaction())
        $pdo->rollBack();
    error_log('Profile update error: ' . $e->getMessage());
    json_response(500, ['success' => false, 'error' => 'Profile update failed: ' . $e->getMessage()]);
}
