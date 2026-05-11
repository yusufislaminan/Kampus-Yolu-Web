<?php
declare(strict_types=1);
require __DIR__ . '/inc/auth.php';
require __DIR__ . '/inc/log_helper.php';
require_admin();

$pageTitle = 'Kullanıcı Yönetimi';
$activePage = 'users';

// Sayfalama
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 20;
$offset = ($page - 1) * $perPage;

// Filtreler
$search = trim($_GET['search'] ?? '');
$filterRole = $_GET['role'] ?? '';
$filterStatus = $_GET['status'] ?? '';
$filterSuspended = $_GET['suspended'] ?? '';

$where = [];
$params = [];

if ($search !== '') {
    $where[] = "(u.display_name LIKE ? OR u.email LIKE ? OR u.id = ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = (int)$search;
}
if ($filterRole !== '') { $where[] = "u.role = ?"; $params[] = $filterRole; }
if ($filterStatus !== '') { $where[] = "u.status = ?"; $params[] = $filterStatus; }
if ($filterSuspended === '1') { $where[] = "u.is_suspended = 1"; }
if ($filterSuspended === '0') { $where[] = "u.is_suspended = 0"; }

$whereSQL = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

// Toplam sayı
$stCount = $pdo->prepare("SELECT COUNT(*) FROM users u $whereSQL");
$stCount->execute($params);
$totalUsers = (int) $stCount->fetchColumn();
$totalPages = max(1, (int) ceil($totalUsers / $perPage));

// Kullanıcıları çek
$stUsers = $pdo->prepare(
    "SELECT u.id, u.email, u.display_name, u.gender, u.role, u.status, 
            u.is_suspended, u.trust_level, u.profile_pic, u.created_at,
            (SELECT COUNT(*) FROM complaints c WHERE c.reported_id = u.id AND c.status IN ('open','investigating')) as open_complaints
     FROM users u
     $whereSQL
     ORDER BY u.created_at DESC
     LIMIT $perPage OFFSET $offset"
);
$stUsers->execute($params);
$users = $stUsers->fetchAll();

require __DIR__ . '/inc/header.php';
?>

<div class="panel">
    <!-- FILTERS -->
    <div class="filters-bar">
        <form method="GET" style="display:flex;flex-wrap:wrap;gap:10px;align-items:center;width:100%;">
            <div class="search-box">
                <i class="fa-solid fa-magnifying-glass"></i>
                <input type="text" name="search" placeholder="İsim, email veya ID ara..." value="<?= esc($search) ?>">
            </div>
            <select name="role">
                <option value="">Tüm Roller</option>
                <option value="user" <?= $filterRole==='user'?'selected':'' ?>>Kullanıcı</option>
                <option value="admin" <?= $filterRole==='admin'?'selected':'' ?>>Admin</option>
            </select>
            <select name="status">
                <option value="">Tüm Durumlar</option>
                <option value="online" <?= $filterStatus==='online'?'selected':'' ?>>Online</option>
                <option value="searching" <?= $filterStatus==='searching'?'selected':'' ?>>Arıyor</option>
                <option value="offline" <?= $filterStatus==='offline'?'selected':'' ?>>Offline</option>
            </select>
            <select name="suspended">
                <option value="">Askı Durumu</option>
                <option value="1" <?= $filterSuspended==='1'?'selected':'' ?>>Askıda</option>
                <option value="0" <?= $filterSuspended==='0'?'selected':'' ?>>Aktif</option>
            </select>
            <button type="submit" class="btn btn-primary btn-sm"><i class="fa-solid fa-filter"></i> Filtrele</button>
            <a href="users.php" class="btn btn-ghost btn-sm">Temizle</a>
            <span style="margin-left:auto;font-size:0.8rem;color:var(--text-muted);">Toplam: <?= $totalUsers ?> kullanıcı</span>
        </form>
    </div>

    <!-- TABLE -->
    <div class="panel-body no-pad">
        <?php if (empty($users)): ?>
        <div class="empty-state"><i class="fa-solid fa-users-slash"></i><p>Kullanıcı bulunamadı.</p></div>
        <?php else: ?>
        <table class="data-table">
            <thead>
                <tr>
                    <th>ID</th><th>Kullanıcı</th><th>Email</th><th>Rol</th>
                    <th>Durum</th><th>Güven</th><th>Şikayet</th><th>Kayıt</th><th>İşlem</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($users as $u): ?>
            <tr>
                <td style="color:var(--text-muted);font-size:0.8rem;">#<?= $u['id'] ?></td>
                <td>
                    <div class="flex-center gap-8">
                        <div class="mini-avatar"><?= esc(mb_strtoupper(mb_substr($u['display_name'] ?: $u['email'], 0, 1))) ?></div>
                        <div>
                            <div style="font-weight:600;font-size:0.85rem;"><?= esc($u['display_name'] ?: '-') ?></div>
                            <?php if ($u['is_suspended']): ?>
                            <span class="badge-status badge-suspended" style="font-size:0.65rem;">Askıda</span>
                            <?php endif; ?>
                        </div>
                    </div>
                </td>
                <td style="font-size:0.82rem;"><?= esc($u['email']) ?></td>
                <td><span class="badge-status <?= $u['role']==='admin'?'badge-approved':'badge-offline' ?>"><?= esc(ucfirst($u['role'])) ?></span></td>
                <td><span class="badge-status badge-<?= esc($u['status']==='online'||$u['status']==='searching'?'online':'offline') ?>"><?= esc(ucfirst($u['status'])) ?></span></td>
                <td><span class="badge-status badge-trust-<?= $u['trust_level'] ?>">Lv.<?= $u['trust_level'] ?></span></td>
                <td>
                    <?php if ($u['open_complaints'] > 0): ?>
                    <span class="badge-status badge-rejected"><?= $u['open_complaints'] ?></span>
                    <?php else: ?>
                    <span style="color:var(--text-muted);">0</span>
                    <?php endif; ?>
                </td>
                <td style="font-size:0.78rem;color:var(--text-muted);"><?= date('d.m.Y', strtotime($u['created_at'])) ?></td>
                <td>
                    <div class="flex gap-4" style="gap:4px;">
                        <?php if (!$u['is_suspended']): ?>
                        <button class="btn-icon warning" title="Askıya Al" onclick="userAction(<?= $u['id'] ?>,'suspend','<?= esc($u['display_name'] ?: $u['email']) ?>')"><i class="fa-solid fa-pause"></i></button>
                        <?php else: ?>
                        <button class="btn-icon success" title="Askıyı Kaldır" onclick="userAction(<?= $u['id'] ?>,'unsuspend','<?= esc($u['display_name'] ?: $u['email']) ?>')"><i class="fa-solid fa-play"></i></button>
                        <?php endif; ?>
                        
                        <?php if ($u['role'] === 'user'): ?>
                        <button class="btn-icon info" title="Admin Yap" onclick="userAction(<?= $u['id'] ?>,'make_admin','<?= esc($u['display_name'] ?: $u['email']) ?>')"><i class="fa-solid fa-user-shield"></i></button>
                        <?php else: ?>
                        <button class="btn-icon danger" title="Yetkiyi Al" onclick="userAction(<?= $u['id'] ?>,'remove_admin','<?= esc($u['display_name'] ?: $u['email']) ?>')"><i class="fa-solid fa-user-slash"></i></button>
                        <?php endif; ?>

                        <button class="btn-icon" title="Uyarı Gönder" onclick="openWarningModal(<?= $u['id'] ?>,'<?= esc($u['display_name'] ?: $u['email']) ?>')"><i class="fa-solid fa-triangle-exclamation"></i></button>
                        <button class="btn-icon danger" title="Sil" onclick="userAction(<?= $u['id'] ?>,'delete','<?= esc($u['display_name'] ?: $u['email']) ?>')"><i class="fa-solid fa-trash"></i></button>
                    </div>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>

    <!-- PAGINATION -->
    <?php if ($totalPages > 1): ?>
    <div class="pagination">
        <?php
        $queryBase = $_GET; unset($queryBase['page']);
        $qs = http_build_query($queryBase);
        $qs = $qs ? "&$qs" : '';
        ?>
        <a href="?page=<?= max(1,$page-1) . $qs ?>" class="<?= $page<=1?'disabled':'' ?>">‹ Önceki</a>
        <?php for ($i=1; $i<=$totalPages; $i++): ?>
        <a href="?page=<?= $i . $qs ?>" class="<?= $i===$page?'active':'' ?>"><?= $i ?></a>
        <?php endfor; ?>
        <a href="?page=<?= min($totalPages,$page+1) . $qs ?>" class="<?= $page>=$totalPages?'disabled':'' ?>">Sonraki ›</a>
    </div>
    <?php endif; ?>
</div>

<!-- UYARI MODAL -->
<div class="modal-overlay" id="warningModal">
    <div class="modal">
        <div class="modal-header">
            <h3><i class="fa-solid fa-triangle-exclamation"></i> Uyarı Gönder</h3>
            <button class="modal-close" onclick="closeModal('warningModal')">&times;</button>
        </div>
        <div class="modal-body">
            <input type="hidden" id="warningUserId">
            <p style="margin-bottom:12px;font-size:0.85rem;color:var(--text-dim);">Kullanıcı: <strong id="warningUserName"></strong></p>
            <div class="form-group">
                <label>Uyarı Seviyesi</label>
                <select id="warningSeverity" class="form-control">
                    <option value="info">Bilgilendirme</option>
                    <option value="warning" selected>Uyarı</option>
                    <option value="critical">Kritik</option>
                </select>
            </div>
            <div class="form-group">
                <label>Mesaj</label>
                <textarea id="warningMessage" class="form-control" rows="3" placeholder="Uyarı mesajınızı yazın..."></textarea>
            </div>
        </div>
        <div class="modal-footer">
            <button class="btn btn-ghost" onclick="closeModal('warningModal')">İptal</button>
            <button class="btn btn-warning" onclick="sendWarning()"><i class="fa-solid fa-paper-plane"></i> Gönder</button>
        </div>
    </div>
</div>

<?php require __DIR__ . '/inc/footer.php'; ?>
