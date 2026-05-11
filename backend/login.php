<?php
declare(strict_types=1);

// Güvenli session ayarları (admin yetkilendirme için)
ini_set('session.cookie_httponly', '1');
ini_set('session.cookie_samesite', 'Strict');
ini_set('session.use_strict_mode', '1');
ini_set('session.use_only_cookies', '1');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require __DIR__ . '/http.php';
require __DIR__ . '/db.php';
require __DIR__ . '/../admin/inc/security.php';
require __DIR__ . '/../admin/inc/log_helper.php';

set_cors();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(405, ['success' => false, 'error' => 'Method not allowed']);
}

$ip = get_client_ip();
$rateCheck = check_rate_limit($pdo, $ip);

if ($rateCheck['locked']) {
    json_response(429, ['success' => false, 'error' => "Çok fazla başarısız deneme. {$rateCheck['unlock_minutes']} dakika sonra tekrar deneyin."]);
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
    $stmt = $pdo->prepare('SELECT id, email, password_hash, role, display_name, gender, profile_pic, trust_level FROM users WHERE email = ? LIMIT 1');
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if (!$user || !password_verify($password, $user['password_hash'])) {
        error_log("Failed login attempt for email: '$email'. User found: " . ($user ? 'Yes' : 'No'));
        log_login_failed($pdo, $user ? (int)$user['id'] : null, $email);
        json_response(401, ['success' => false, 'error' => 'Invalid credentials']);
    }

    if ($user['role'] === 'admin') {
        // Admin session ayarla
        session_regenerate_id(true);
        $_SESSION['admin_id'] = (int) $user['id'];
        $_SESSION['admin_email'] = $user['email'];
        $_SESSION['admin_name'] = $user['display_name'] ?? '';
        $_SESSION['admin_role'] = 'admin';
        $_SESSION['last_regeneration'] = time();
        
        log_login_success($pdo, (int)$user['id'], $user['email']);
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
        'trust_level' => (int) ($user['trust_level'] ?? 0),
        'interests' => $interests,
    ]);
} catch (Throwable $e) {
    error_log('Login error: ' . $e->getMessage());
    json_response(500, ['success' => false, 'error' => 'Login failed: ' . $e->getMessage()]);
}
