<?php
declare(strict_types=1);

require __DIR__ . '/http.php';
require __DIR__ . '/db.php';

set_cors();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(405, ['success' => false, 'error' => 'Method not allowed']);
}

$data = read_json_body();
$email = isset($data['eposta']) ? trim((string) $data['eposta']) : (isset($data['email']) ? trim((string) $data['email']) : '');
$password = isset($data['sifre']) ? (string) $data['sifre'] : (isset($data['password']) ? (string) $data['password'] : '');

if ($email === '' || $password === '') {
    json_response(400, ['success' => false, 'error' => 'Missing fields']);
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    json_response(400, ['success' => false, 'error' => 'Invalid email']);
}

try {
    $stmt = $pdo->prepare('SELECT id, email, password_hash, role, display_name, gender, profile_pic FROM users WHERE email = ? LIMIT 1');
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if (!$user || !password_verify($password, $user['password_hash'])) {
        json_response(401, ['success' => false, 'error' => 'Invalid credentials']);
    }

    // Kullanıcıyı online yap
    $pdo->prepare("UPDATE users SET status = 'online' WHERE id = ?")->execute([(int) $user['id']]);

    // Kullanıcının ilgi alanlarını çek
    $stmtInt = $pdo->prepare(
        'SELECT i.id, i.name, i.icon, i.category FROM user_interests ui 
         JOIN interests i ON ui.interest_id = i.id WHERE ui.user_id = ?'
    );
    $stmtInt->execute([(int) $user['id']]);
    $interests = $stmtInt->fetchAll();

    json_response(200, [
        'success' => true,
        'role' => $user['role'] === 'admin' ? 'admin' : 'user',
        'userId' => (int) $user['id'],
        'email' => $user['email'],
        'display_name' => $user['display_name'] ?? '',
        'gender' => $user['gender'] ?? 'belirtmek_istemiyorum',
        'profile_pic' => $user['profile_pic'] ?? null,
        'interests' => $interests,
    ]);
} catch (Throwable $e) {
    error_log('Login error: ' . $e->getMessage());
    json_response(500, ['success' => false, 'error' => 'Login failed: ' . $e->getMessage()]);
}
