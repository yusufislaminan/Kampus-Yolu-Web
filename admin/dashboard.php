<?php
declare(strict_types=1);
require __DIR__ . '/inc/auth.php';
require_admin();

$pageTitle = 'Dashboard';
$activePage = 'dashboard';

// İstatistikler
$stats = [];
try {
    $stats['total_users'] = (int) $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
    $stats['active_users'] = (int) $pdo->query("SELECT COUNT(*) FROM users WHERE status IN ('online','searching')")->fetchColumn();
    $stats['pending_docs'] = (int) $pdo->query("SELECT COUNT(*) FROM user_documents WHERE status='pending'")->fetchColumn();
    $stats['open_complaints'] = (int) $pdo->query("SELECT COUNT(*) FROM complaints WHERE status='open'")->fetchColumn();
    $stats['suspended_users'] = (int) $pdo->query("SELECT COUNT(*) FROM users WHERE is_suspended=1")->fetchColumn();
    $stats['today_matches'] = (int) $pdo->query("SELECT COUNT(*) FROM matches WHERE DATE(created_at)=CURDATE()")->fetchColumn();
} catch (Throwable $e) {
    // Tablolar henüz oluşmamış olabilir
}

// Son 5 şikayet
$recentComplaints = [];
try {
    $stRC = $pdo->query(
        "SELECT c.*, 
                reporter.display_name as reporter_name, reporter.email as reporter_email,
                reported.display_name as reported_name, reported.email as reported_email
         FROM complaints c
         JOIN users reporter ON c.reporter_id = reporter.id
         JOIN users reported ON c.reported_id = reported.id
         ORDER BY c.created_at DESC LIMIT 5"
    );
    $recentComplaints = $stRC->fetchAll();
} catch (Throwable $e) {}

// Son 10 log
$recentLogs = [];
try {
    $stRL = $pdo->query(
        "SELECT sl.*, u.display_name, u.email 
         FROM system_logs sl 
         LEFT JOIN users u ON sl.user_id = u.id
         ORDER BY sl.created_at DESC LIMIT 10"
    );
    $recentLogs = $stRL->fetchAll();
} catch (Throwable $e) {}

require __DIR__ . '/inc/header.php';
?>

<!-- İSTATİSTİK KARTLARI -->
<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-icon green"><i class="fa-solid fa-users"></i></div>
        <div class="stat-value"><?= $stats['total_users'] ?? 0 ?></div>
        <div class="stat-label">Toplam Kullanıcı</div>
    </div>
    <div class="stat-card">
        <div class="stat-icon blue"><i class="fa-solid fa-signal"></i></div>
        <div class="stat-value"><?= $stats['active_users'] ?? 0 ?></div>
        <div class="stat-label">Aktif (Online)</div>
    </div>
    <div class="stat-card">
        <div class="stat-icon yellow"><i class="fa-solid fa-file-circle-check"></i></div>
        <div class="stat-value"><?= $stats['pending_docs'] ?? 0 ?></div>
        <div class="stat-label">Bekleyen Belge</div>
    </div>
    <div class="stat-card">
        <div class="stat-icon red"><i class="fa-solid fa-flag"></i></div>
        <div class="stat-value"><?= $stats['open_complaints'] ?? 0 ?></div>
        <div class="stat-label">Açık Şikayet</div>
    </div>
    <div class="stat-card">
        <div class="stat-icon purple"><i class="fa-solid fa-ban"></i></div>
        <div class="stat-value"><?= $stats['suspended_users'] ?? 0 ?></div>
        <div class="stat-label">Askıya Alınmış</div>
    </div>
    <div class="stat-card">
        <div class="stat-icon green"><i class="fa-solid fa-handshake"></i></div>
        <div class="stat-value"><?= $stats['today_matches'] ?? 0 ?></div>
        <div class="stat-label">Bugünkü Eşleşme</div>
    </div>
</div>

<div class="grid-2">
    <!-- SON ŞİKAYETLER -->
    <div class="panel">
        <div class="panel-header">
            <h3><i class="fa-solid fa-flag"></i> Son Şikayetler</h3>
            <a href="complaints.php" class="btn btn-ghost btn-sm">Tümünü Gör</a>
        </div>
        <div class="panel-body <?= empty($recentComplaints) ? '' : 'no-pad' ?>">
            <?php if (empty($recentComplaints)): ?>
            <div class="empty-state"><i class="fa-solid fa-check-circle"></i><p>Açık şikayet yok.</p></div>
            <?php else: ?>
            <table class="data-table">
                <thead><tr><th>Şikayet Eden</th><th>Hakkında</th><th>Kategori</th><th>Durum</th></tr></thead>
                <tbody>
                <?php foreach ($recentComplaints as $c): 
                    $catLabels = ['uygunsuz_davranis'=>'Uygunsuz','gelmeme'=>'Gelmeme','spam'=>'Spam','sahte_profil'=>'Sahte','diger'=>'Diğer'];
                ?>
                <tr>
                    <td><?= esc($c['reporter_name'] ?: $c['reporter_email']) ?></td>
                    <td><?= esc($c['reported_name'] ?: $c['reported_email']) ?></td>
                    <td><?= esc($catLabels[$c['category']] ?? $c['category']) ?></td>
                    <td><span class="badge-status badge-<?= esc($c['status']) ?>"><?= esc(ucfirst($c['status'])) ?></span></td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
        </div>
    </div>

    <!-- SON LOGLAR -->
    <div class="panel">
        <div class="panel-header">
            <h3><i class="fa-solid fa-scroll"></i> Son İşlemler</h3>
            <a href="logs.php" class="btn btn-ghost btn-sm">Tümünü Gör</a>
        </div>
        <div class="panel-body <?= empty($recentLogs) ? '' : 'no-pad' ?>">
            <?php if (empty($recentLogs)): ?>
            <div class="empty-state"><i class="fa-solid fa-clock-rotate-left"></i><p>Henüz log kaydı yok.</p></div>
            <?php else: ?>
            <table class="data-table">
                <thead><tr><th>Eylem</th><th>Kullanıcı</th><th>Risk</th><th>Zaman</th></tr></thead>
                <tbody>
                <?php foreach ($recentLogs as $l): ?>
                <tr>
                    <td style="font-size:0.8rem;"><?= esc($l['action']) ?></td>
                    <td><?= esc($l['display_name'] ?: ($l['email'] ?: '-')) ?></td>
                    <td><span class="badge-status badge-risk-<?= esc($l['risk_level']) ?>"><?= esc(ucfirst($l['risk_level'])) ?></span></td>
                    <td style="font-size:0.78rem;color:var(--text-muted);"><?= date('d.m H:i', strtotime($l['created_at'])) ?></td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require __DIR__ . '/inc/footer.php'; ?>
