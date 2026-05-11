<?php
/**
 * Kampüs Yolu — Güvenlik Modülü
 * 
 * - Brute Force koruması (IP bazlı rate limiting)
 * - Anomali tespiti (çok şikayet alan / çok istek atan kullanıcılar)
 */
declare(strict_types=1);

/**
 * IP adresini güvenli şekilde al
 */
function get_client_ip(): string {
    // Proxy arkasında ise
    if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $ips = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
        return trim($ips[0]);
    }
    return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
}

/**
 * Brute Force kontrolü
 * Son 15 dakikada aynı IP'den 5+ başarısız giriş → kilit
 * 
 * @return array ['locked' => bool, 'remaining_attempts' => int, 'unlock_minutes' => int]
 */
function check_rate_limit(PDO $pdo, string $ip, int $maxAttempts = 5, int $windowMinutes = 15): array {
    $stmt = $pdo->prepare(
        "SELECT COUNT(*) as cnt FROM system_logs 
         WHERE ip_address = ? 
         AND action = 'login_failed' 
         AND created_at > NOW() - INTERVAL ? MINUTE"
    );
    $stmt->execute([$ip, $windowMinutes]);
    $row = $stmt->fetch();
    $failCount = (int) ($row['cnt'] ?? 0);
    
    $locked = $failCount >= $maxAttempts;
    $remaining = max(0, $maxAttempts - $failCount);
    
    // Kilidi ne zaman açılır hesapla
    $unlockMinutes = 0;
    if ($locked) {
        $stmt2 = $pdo->prepare(
            "SELECT created_at FROM system_logs 
             WHERE ip_address = ? AND action = 'login_failed' 
             ORDER BY created_at DESC LIMIT 1"
        );
        $stmt2->execute([$ip]);
        $lastFail = $stmt2->fetch();
        if ($lastFail) {
            $lastTime = strtotime($lastFail['created_at']);
            $unlockTime = $lastTime + ($windowMinutes * 60);
            $unlockMinutes = max(0, (int) ceil(($unlockTime - time()) / 60));
        }
    }
    
    return [
        'locked' => $locked,
        'remaining_attempts' => $remaining,
        'unlock_minutes' => $unlockMinutes,
    ];
}

/**
 * Riskli kullanıcıları tespit et
 * - Son 24 saatte 3+ şikayet alanlar
 * - Son 1 saatte 5+ başarısız giriş deneyen IP'ler
 */
function get_risky_users(PDO $pdo): array {
    // Çok şikayet alan kullanıcılar
    $stmt1 = $pdo->prepare(
        "SELECT u.id, u.display_name, u.email, COUNT(c.id) as complaint_count,
                u.is_suspended, u.trust_level
         FROM complaints c
         JOIN users u ON c.reported_id = u.id
         WHERE c.created_at > NOW() - INTERVAL 24 HOUR
           AND c.status IN ('open', 'investigating')
         GROUP BY u.id
         HAVING complaint_count >= 3
         ORDER BY complaint_count DESC"
    );
    $stmt1->execute();
    $complainedUsers = $stmt1->fetchAll();
    
    // Çok başarısız giriş deneyen IP'ler
    $stmt2 = $pdo->prepare(
        "SELECT ip_address, COUNT(*) as fail_count,
                MAX(created_at) as last_attempt,
                JSON_ARRAYAGG(JSON_OBJECT('user_id', user_id, 'time', created_at)) as attempts
         FROM system_logs
         WHERE action = 'login_failed'
           AND created_at > NOW() - INTERVAL 1 HOUR
         GROUP BY ip_address
         HAVING fail_count >= 5
         ORDER BY fail_count DESC"
    );
    $stmt2->execute();
    $suspiciousIPs = $stmt2->fetchAll();
    
    return [
        'complained_users' => $complainedUsers,
        'suspicious_ips' => $suspiciousIPs,
    ];
}

/**
 * Anomali skoru hesapla (belirli bir kullanıcı için)
 * 0-100 arası, yüksek = daha riskli
 */
function calculate_risk_score(PDO $pdo, int $userId): int {
    $score = 0;
    
    // Şikayet sayısı (son 30 gün)
    $stmt = $pdo->prepare(
        "SELECT COUNT(*) FROM complaints 
         WHERE reported_id = ? AND created_at > NOW() - INTERVAL 30 DAY"
    );
    $stmt->execute([$userId]);
    $complaints = (int) $stmt->fetchColumn();
    $score += min($complaints * 15, 45); // Maks 45 puan
    
    // Askıya alınma durumu
    $stmt2 = $pdo->prepare("SELECT is_suspended FROM users WHERE id = ?");
    $stmt2->execute([$userId]);
    $suspended = (int) $stmt2->fetchColumn();
    if ($suspended) $score += 20;
    
    // Güven seviyesi düşüklüğü
    $stmt3 = $pdo->prepare("SELECT trust_level FROM users WHERE id = ?");
    $stmt3->execute([$userId]);
    $trustLevel = (int) $stmt3->fetchColumn();
    $score += max(0, (3 - $trustLevel) * 5); // Maks 15 puan
    
    // Engelleme sayısı
    $stmt4 = $pdo->prepare(
        "SELECT COUNT(*) FROM blocked_users WHERE blocked_id = ?"
    );
    $stmt4->execute([$userId]);
    $blocks = (int) $stmt4->fetchColumn();
    $score += min($blocks * 5, 20); // Maks 20 puan
    
    return min($score, 100);
}
