<?php
/**
 * Admin Panel — Header (Sidebar + Topbar)
 * Her sayfa bu dosyayı include eder.
 * 
 * Gerekli değişkenler:
 *   $pageTitle  — Sayfa başlığı
 *   $activePage — Aktif menü öğesi (dashboard, users, documents, complaints, heatmap, logs)
 */
$admin = current_admin();
$adminInitial = mb_strtoupper(mb_substr($admin['name'] ?: $admin['email'], 0, 1));

// Bekleyen belge ve şikayet sayıları (sidebar badge için)
$badgeDocs = 0;
$badgeComplaints = 0;
try {
    $stBadge = $pdo->query("SELECT COUNT(*) FROM user_documents WHERE status='pending'");
    $badgeDocs = (int) $stBadge->fetchColumn();
    $stBadge2 = $pdo->query("SELECT COUNT(*) FROM complaints WHERE status='open'");
    $badgeComplaints = (int) $stBadge2->fetchColumn();
} catch (Throwable $e) { /* tablo henüz yoksa sessizce devam */ }
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf_token" content="<?= csrf_token() ?>">
    <title><?= esc($pageTitle ?? 'Admin') ?> — Kampüs Yolu Yönetim</title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <?php if (($activePage ?? '') === 'heatmap'): ?>
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
    <?php endif; ?>
</head>
<body>

<!-- SIDEBAR -->
<aside class="sidebar" id="sidebar">
    <div class="sidebar-logo">
        <i class="fa-solid fa-shield-halved"></i>
        <div>
            <span>Kampüs Yolu</span>
            <small>Yönetim Paneli</small>
        </div>
    </div>
    <nav class="sidebar-nav">
        <a href="dashboard.php" class="nav-item <?= ($activePage ?? '') === 'dashboard' ? 'active' : '' ?>">
            <i class="fa-solid fa-gauge-high"></i> Dashboard
        </a>
        <a href="users.php" class="nav-item <?= ($activePage ?? '') === 'users' ? 'active' : '' ?>">
            <i class="fa-solid fa-users"></i> Kullanıcılar
        </a>
        <a href="documents.php" class="nav-item <?= ($activePage ?? '') === 'documents' ? 'active' : '' ?>">
            <i class="fa-solid fa-file-circle-check"></i> Belge Doğrulama
            <?php if ($badgeDocs > 0): ?><span class="badge"><?= $badgeDocs ?></span><?php endif; ?>
        </a>
        <a href="complaints.php" class="nav-item <?= ($activePage ?? '') === 'complaints' ? 'active' : '' ?>">
            <i class="fa-solid fa-flag"></i> Şikayetler
            <?php if ($badgeComplaints > 0): ?><span class="badge"><?= $badgeComplaints ?></span><?php endif; ?>
        </a>
        <a href="heatmap.php" class="nav-item <?= ($activePage ?? '') === 'heatmap' ? 'active' : '' ?>">
            <i class="fa-solid fa-fire"></i> Isı Haritası
        </a>
        <a href="logs.php" class="nav-item <?= ($activePage ?? '') === 'logs' ? 'active' : '' ?>">
            <i class="fa-solid fa-scroll"></i> Sistem Logları
        </a>
    </nav>
    <div class="sidebar-footer">
        <div class="admin-avatar"><?= esc($adminInitial) ?></div>
        <div class="admin-info">
            <div class="name"><?= esc($admin['name'] ?: $admin['email']) ?></div>
            <div class="role">Sistem Yöneticisi</div>
        </div>
    </div>
</aside>

<!-- TOPBAR -->
<header class="topbar">
    <div class="topbar-title"><?= esc($pageTitle ?? 'Dashboard') ?></div>
    <div class="topbar-actions">
        <button id="adminTemaBtn" class="btn-icon" title="Tema Değiştir" style="margin-right:10px; border:none; background:transparent;"><i class="fa-solid fa-moon"></i></button>
        <a href="logout.php" class="btn-logout"><i class="fa-solid fa-right-from-bracket"></i> Çıkış</a>
    </div>
</header>

<!-- MAIN CONTENT -->
<main class="main-content">
