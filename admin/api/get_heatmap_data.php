<?php
declare(strict_types=1);
require dirname(__DIR__) . '/inc/auth.php';
require_admin();

$hours = max(1, (int)($_GET['hours'] ?? 168));
$timeRange = $_GET['timeRange'] ?? '';

$whereExtra = '';
if ($timeRange !== '') {
    $parts = explode('-', $timeRange);
    if (count($parts) === 2) {
        $from = (int)$parts[0];
        $to = (int)$parts[1];
        $whereExtra = " AND HOUR(lh.recorded_at) >= $from AND HOUR(lh.recorded_at) < $to";
    }
}

try {
    $stmt = $pdo->prepare(
        "SELECT ST_Y(lh.location) AS lat, ST_X(lh.location) AS lng, COUNT(*) as intensity
         FROM location_history lh
         WHERE lh.recorded_at > NOW() - INTERVAL ? HOUR
         $whereExtra
         GROUP BY ROUND(ST_Y(lh.location), 4), ROUND(ST_X(lh.location), 4)
         ORDER BY intensity DESC
         LIMIT 5000"
    );
    $stmt->execute([$hours]);
    $points = $stmt->fetchAll();

    $result = array_map(fn($p) => [
        'lat' => (float)$p['lat'],
        'lng' => (float)$p['lng'],
        'intensity' => min(1.0, (int)$p['intensity'] / 10)
    ], $points);

    admin_json(200, ['success' => true, 'points' => $result]);
} catch (Throwable $e) {
    admin_json(200, ['success' => true, 'points' => []]);
}
