<?php
declare(strict_types=1);
require __DIR__ . '/inc/auth.php';
require_admin();

$pageTitle = 'Sistem Logları';
$activePage = 'logs';

$filterAction = $_GET['action'] ?? '';
$filterRisk = $_GET['risk'] ?? '';
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 30;
$offset = ($page - 1) * $perPage;

$where = [];
$params = [];
if ($filterAction !== '') { $where[] = "sl.action = ?"; $params[] = $filterAction; }
if ($filterRisk !== '') { $where[] = "sl.risk_level = ?"; $params[] = $filterRisk; }
$whereSQL = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

$stCount = $pdo->prepare("SELECT COUNT(*) FROM system_logs sl $whereSQL");
$stCount->execute($params);
$total = (int) $stCount->fetchColumn();
$totalPages = max(1, (int) ceil($total / $perPage));

$stLogs = $pdo->prepare(
    "SELECT sl.*, u.display_name, u.email
     FROM system_logs sl
     LEFT JOIN users u ON sl.user_id = u.id
     $whereSQL
     ORDER BY sl.created_at DESC
     LIMIT $perPage OFFSET $offset"
);
$stLogs->execute($params);
$logs = $stLogs->fetchAll();

// Benzersiz action tipleri
$actionTypes = [];
try {
    $stActions = $pdo->query("SELECT DISTINCT action FROM system_logs ORDER BY action");
    $actionTypes = $stActions->fetchAll(PDO::FETCH_COLUMN);
} catch (Throwable $e) {}

require __DIR__ . '/inc/header.php';
?>

<div class="panel">
    <div class="filters-bar">
        <form method="GET" style="display:flex;flex-wrap:wrap;gap:10px;align-items:center;width:100%;">
            <select name="action">
                <option value="">Tüm Eylemler</option>
                <?php foreach ($actionTypes as $at): ?>
                <option value="<?= esc($at) ?>" <?= $filterAction===$at?'selected':'' ?>><?= esc($at) ?></option>
                <?php endforeach; ?>
            </select>
            <select name="risk">
                <option value="">Tüm Riskler</option>
                <option value="low" <?= $filterRisk==='low'?'selected':'' ?>>Low</option>
                <option value="medium" <?= $filterRisk==='medium'?'selected':'' ?>>Medium</option>
                <option value="high" <?= $filterRisk==='high'?'selected':'' ?>>High</option>
                <option value="critical" <?= $filterRisk==='critical'?'selected':'' ?>>Critical</option>
            </select>
            <button type="submit" class="btn btn-primary btn-sm"><i class="fa-solid fa-filter"></i> Filtrele</button>
            <a href="logs.php" class="btn btn-ghost btn-sm">Temizle</a>
            <span style="margin-left:auto;font-size:0.8rem;color:var(--text-muted);">Toplam: <?= $total ?> kayıt</span>
        </form>
    </div>

    <div class="panel-body no-pad">
        <?php if (empty($logs)): ?>
        <div class="empty-state"><i class="fa-solid fa-scroll"></i><p>Log kaydı bulunamadı.</p></div>
        <?php else: ?>
        <table class="data-table">
            <thead>
                <tr><th>Zaman</th><th>Eylem</th><th>Kullanıcı</th><th>IP</th><th>Risk</th><th>Detay</th></tr>
            </thead>
            <tbody>
            <?php foreach ($logs as $l): ?>
            <tr>
                <td style="font-size:0.78rem;color:var(--text-muted);white-space:nowrap;"><?= date('d.m.Y H:i:s', strtotime($l['created_at'])) ?></td>
                <td style="font-size:0.82rem;font-weight:500;"><?= esc($l['action']) ?></td>
                <td style="font-size:0.82rem;"><?= esc($l['display_name'] ?: ($l['email'] ?: '-')) ?></td>
                <td style="font-size:0.78rem;color:var(--text-muted);font-family:monospace;"><?= esc($l['ip_address']) ?></td>
                <td><span class="badge-status badge-risk-<?= esc($l['risk_level']) ?>"><?= esc(ucfirst($l['risk_level'])) ?></span></td>
                <td style="font-size:0.75rem;color:var(--text-muted);max-width:200px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">
                    <?php if ($l['details']): $det = json_decode($l['details'], true); echo esc(is_array($det) ? implode(', ', array_map(fn($k,$v)=>"$k:$v", array_keys($det), $det)) : $l['details']); endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>

    <?php if ($totalPages > 1): ?>
    <div class="pagination">
        <?php
        $queryBase = $_GET; unset($queryBase['page']);
        $qs = http_build_query($queryBase);
        $qs = $qs ? "&$qs" : '';
        ?>
        <a href="?page=<?= max(1,$page-1) . $qs ?>" class="<?= $page<=1?'disabled':'' ?>">‹ Önceki</a>
        <?php for ($i=max(1,$page-3); $i<=min($totalPages,$page+3); $i++): ?>
        <a href="?page=<?= $i . $qs ?>" class="<?= $i===$page?'active':'' ?>"><?= $i ?></a>
        <?php endfor; ?>
        <a href="?page=<?= min($totalPages,$page+1) . $qs ?>" class="<?= $page>=$totalPages?'disabled':'' ?>">Sonraki ›</a>
    </div>
    <?php endif; ?>
</div>

<?php require __DIR__ . '/inc/footer.php'; ?>
