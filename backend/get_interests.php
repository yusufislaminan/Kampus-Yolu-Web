<?php
declare(strict_types=1);

require __DIR__ . '/http.php';
require __DIR__ . '/db.php';

set_cors();

// GET veya POST kabul et
if (!in_array($_SERVER['REQUEST_METHOD'], ['GET', 'POST'], true)) {
    json_response(405, ['success' => false, 'error' => 'Method not allowed']);
}

try {
    $stmt = $pdo->query("SELECT id, category, name, icon FROM interests ORDER BY category, name");
    $rows = $stmt->fetchAll();

    $categories = [];
    foreach ($rows as $row) {
        $cat = $row['category'];
        if (!isset($categories[$cat])) {
            $categories[$cat] = [];
        }
        $categories[$cat][] = [
            'id' => (int) $row['id'],
            'name' => $row['name'],
            'icon' => $row['icon'],
        ];
    }

    // Kategori başlıkları (Türkçe etiketler)
    $categoryLabels = [
        'muzik' => '🎵 Müzik Türleri',
        'spor' => '⚽ Sporlar',
        'akademik' => '📚 Akademik İlgiler',
        'hobi' => '🎮 Hobiler',
        'yasam' => '☕ Yaşam Tarzı',
    ];

    json_response(200, [
        'success' => true,
        'categories' => $categories,
        'categoryLabels' => $categoryLabels,
    ]);
} catch (Throwable $e) {
    error_log('Get interests error: ' . $e->getMessage());
    json_response(500, ['success' => false, 'error' => 'Failed to load interests: ' . $e->getMessage()]);
}
