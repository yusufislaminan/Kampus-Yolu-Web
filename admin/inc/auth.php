<?php
/**
 * Kampüs Yolu — Admin Yetki & Oturum Kontrolü
 * 
 * Her admin sayfası bu dosyayı require eder.
 * - Session kontrolü
 * - Admin rolü doğrulaması
 * - CSRF token üretimi & doğrulaması
 * - XSS koruması helper'ı
 * - Güvenli header'lar
 */
declare(strict_types=1);

// Güvenli session ayarları
ini_set('session.cookie_httponly', '1');
ini_set('session.cookie_samesite', 'Strict');
ini_set('session.use_strict_mode', '1');
ini_set('session.use_only_cookies', '1');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Güvenlik header'ları
header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');
header('X-XSS-Protection: 1; mode=block');
header('Referrer-Policy: strict-origin-when-cross-origin');

// DB bağlantısı — admin/inc/ -> proje kökü (2 üst dizin)
$config = require dirname(__DIR__, 2) . '/backend/config.php';
$dsn = sprintf('mysql:host=%s;dbname=%s;charset=%s', $config['host'], $config['db'], $config['charset']);

try {
    $pdo = new PDO($dsn, $config['user'], $config['pass'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
    $pdo->exec("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");
} catch (Throwable $e) {
    error_log('Admin DB error: ' . $e->getMessage());
    die('Veritabanı bağlantı hatası.');
}

/**
 * Giriş sayfası hariç tüm sayfalarda çağrılır.
 * Giriş yapılmamışsa veya admin değilse yönlendirir.
 */
function require_admin(): void {
    if (
        empty($_SESSION['admin_id']) ||
        empty($_SESSION['admin_role']) ||
        $_SESSION['admin_role'] !== 'admin'
    ) {
        // Session'ı temizle
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $params['path'], $params['domain'],
                $params['secure'], $params['httponly']
            );
        }
        session_destroy();
        header('Location: ../index.html');
        exit;
    }
    
    // Session fixation koruması: 30 dakikada bir yenile
    if (!isset($_SESSION['last_regeneration'])) {
        $_SESSION['last_regeneration'] = time();
    } elseif (time() - $_SESSION['last_regeneration'] > 1800) {
        session_regenerate_id(true);
        $_SESSION['last_regeneration'] = time();
    }
}

/**
 * CSRF token üret (yoksa oluştur)
 */
function csrf_token(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Hidden input olarak CSRF token
 */
function csrf_field(): string {
    return '<input type="hidden" name="csrf_token" value="' . csrf_token() . '">';
}

/**
 * CSRF token doğrula
 */
function verify_csrf(?string $token = null): bool {
    if ($token === null) {
        $token = $_POST['csrf_token'] ?? $_GET['csrf_token'] ?? '';
        // Fallback to JSON body if $_POST is empty
        if (empty($token)) {
            $input = json_decode(file_get_contents('php://input'), true);
            if (is_array($input) && isset($input['csrf_token'])) {
                $token = $input['csrf_token'];
            }
        }
    }
    
    if (empty($token) || empty($_SESSION['csrf_token'])) {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * CSRF doğrulama — başarısızsa die
 */
function require_csrf(): void {
    if (!verify_csrf()) {
        http_response_code(403);
        die(json_encode(['success' => false, 'error' => 'Geçersiz güvenlik tokeni (CSRF). Sayfayı yenileyip tekrar deneyin.']));
    }
}

/**
 * XSS koruması: htmlspecialchars wrapper
 */
function esc(?string $str): string {
    if ($str === null) return '';
    return htmlspecialchars($str, ENT_QUOTES | ENT_HTML5, 'UTF-8');
}

/**
 * JSON API yanıtı
 */
function admin_json(int $status, array $data): void {
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

/**
 * Mevcut admin bilgisi
 */
function current_admin(): array {
    return [
        'id' => $_SESSION['admin_id'] ?? 0,
        'email' => $_SESSION['admin_email'] ?? '',
        'name' => $_SESSION['admin_name'] ?? '',
        'role' => $_SESSION['admin_role'] ?? '',
    ];
}
