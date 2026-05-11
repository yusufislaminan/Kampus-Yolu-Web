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
$password2 = isset($data['sifreTekrar']) ? (string) $data['sifreTekrar'] : (isset($data['password2']) ? (string) $data['password2'] : '');
$displayName = isset($data['display_name']) ? trim((string) $data['display_name']) : '';
$gender = isset($data['gender']) ? (string) $data['gender'] : 'belirtmek_istemiyorum';

if ($email === '' || $password === '' || $password2 === '') {
    json_response(400, ['success' => false, 'error' => 'Missing fields']);
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    json_response(400, ['success' => false, 'error' => 'Invalid email']);
}
if ($password !== $password2) {
    json_response(400, ['success' => false, 'error' => 'Passwords do not match']);
}

$len = strlen($password);
if ($len < 8)
    json_response(400, ['success' => false, 'error' => 'Password too short']);
if (!preg_match('/[A-Z]/', $password))
    json_response(400, ['success' => false, 'error' => 'Need uppercase']);
if (!preg_match('/[a-z]/', $password))
    json_response(400, ['success' => false, 'error' => 'Need lowercase']);
if (!preg_match('/[0-9]/', $password))
    json_response(400, ['success' => false, 'error' => 'Need number']);

$allowedGenders = ['erkek', 'kadin', 'belirtmek_istemiyorum'];
if (!in_array($gender, $allowedGenders, true))
    $gender = 'belirtmek_istemiyorum';

try {
    $stmt = $pdo->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
    $stmt->execute([$email]);
    if ($stmt->fetch()) {
        json_response(409, ['success' => false, 'error' => 'Email already exists']);
    }

    $hash = password_hash($password, PASSWORD_DEFAULT);
    $stmt = $pdo->prepare('INSERT INTO users (email, password_hash, display_name, gender, role) VALUES (?, ?, ?, ?, ?)');
    $stmt->execute([$email, $hash, $displayName ?: null, $gender, 'user']);

    $userId = (int) $pdo->lastInsertId();
    json_response(200, ['success' => true, 'userId' => $userId]);
} catch (Throwable $e) {
    error_log('Register error: ' . $e->getMessage());
    json_response(500, ['success' => false, 'error' => 'Registration failed: ' . $e->getMessage()]);
}
