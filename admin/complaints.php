<?php
declare(strict_types=1);
require __DIR__ . '/inc/auth.php';
require_admin();

$pageTitle = 'Şikayet Yönetimi';
$activePage = 'complaints';

$filterStatus = $_GET['status'] ?? '';
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 20;
$offset = ($page - 1) * $perPage;

$where = $filterStatus !== '' ? "WHERE c.status = ?" : "";
$params = $filterStatus !== '' ? [$filterStatus] : [];

$stCount = $pdo->prepare("SELECT COUNT(*) FROM complaints c $where");
$stCount->execute($params);
$total = (int) $stCount->fetchColumn();
$totalPages = max(1, (int) ceil($total / $perPage));

$stComplaints = $pdo->prepare(
    "SELECT c.*, 
            reporter.display_name as reporter_name, reporter.email as reporter_email,
            reported.display_name as reported_name, reported.email as reported_email,
            reported.is_suspended as reported_suspended,
            resolver.display_name as resolver_name
     FROM complaints c
     JOIN users reporter ON c.reporter_id = reporter.id
     JOIN users reported ON c.reported_id = reported.id
     LEFT JOIN users resolver ON c.resolved_by = resolver.id
     $where
     ORDER BY c.created_at DESC
     LIMIT $perPage OFFSET $offset"
);
$stComplaints->execute($params);
$complaints = $stComplaints->fetchAll();

$catLabels = ['uygunsuz_davranis'=>'Uygunsuz Davranış','gelmeme'=>'Yürüyüşe Gelmeme','spam'=>'Spam','sahte_profil'=>'Sahte Profil','diger'=>'Diğer'];
$statusLabels = ['open'=>'Açık','investigating'=>'İnceleniyor','resolved'=>'Çözüldü','dismissed'=>'Reddedildi'];

require __DIR__ . '/inc/header.php';
?>

<div class="panel">
    <div class="filters-bar">
        <a href="?status=" class="btn <?= $filterStatus===''?'btn-info':'btn-ghost' ?> btn-sm">Tümü</a>
        <a href="?status=open" class="btn <?= $filterStatus==='open'?'btn-warning':'btn-ghost' ?> btn-sm">Açık</a>
        <a href="?status=investigating" class="btn <?= $filterStatus==='investigating'?'btn-info':'btn-ghost' ?> btn-sm">İnceleniyor</a>
        <a href="?status=resolved" class="btn <?= $filterStatus==='resolved'?'btn-primary':'btn-ghost' ?> btn-sm">Çözüldü</a>
        <a href="?status=dismissed" class="btn <?= $filterStatus==='dismissed'?'btn-ghost':'btn-ghost' ?> btn-sm">Reddedildi</a>
        <span style="margin-left:auto;font-size:0.8rem;color:var(--text-muted);">Toplam: <?= $total ?></span>
    </div>

    <div class="panel-body no-pad">
        <?php if (empty($complaints)): ?>
        <div class="empty-state"><i class="fa-solid fa-check-circle"></i><p>Şikayet bulunamadı.</p></div>
        <?php else: ?>
        <table class="data-table">
            <thead>
                <tr><th>Şikayet Eden</th><th>Hakkında</th><th>Kategori</th><th>Açıklama</th><th>Durum</th><th>Tarih</th><th>İşlem</th></tr>
            </thead>
            <tbody>
            <?php foreach ($complaints as $c): ?>
            <tr>
                <td style="font-size:0.85rem;"><?= esc($c['reporter_name'] ?: $c['reporter_email']) ?></td>
                <td>
                    <div style="font-weight:600;font-size:0.85rem;"><?= esc($c['reported_name'] ?: $c['reported_email']) ?></div>
                    <?php if ($c['reported_suspended']): ?>
                    <span class="badge-status badge-suspended" style="font-size:0.65rem;">Askıda</span>
                    <?php endif; ?>
                </td>
                <td><span class="badge-status badge-open"><?= esc($catLabels[$c['category']] ?? $c['category']) ?></span></td>
                <td style="max-width:200px;font-size:0.8rem;color:var(--text-dim);overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"><?= esc($c['description']) ?></td>
                <td><span class="badge-status badge-<?= esc($c['status']) ?>"><?= esc($statusLabels[$c['status']] ?? $c['status']) ?></span></td>
                <td style="font-size:0.78rem;color:var(--text-muted);"><?= date('d.m.Y H:i', strtotime($c['created_at'])) ?></td>
                <td>
                    <div class="flex gap-4" style="gap:4px;">
                        <?php if ($c['status'] === 'open' || $c['status'] === 'investigating'): ?>
                        <button class="btn-icon" title="Detay / Güncelle" onclick="openComplaintModal(<?= $c['id'] ?>,'<?= esc($c['reported_name'] ?: $c['reported_email']) ?>','<?= esc($c['description']) ?>','<?= esc($c['status']) ?>',<?= $c['reported_id'] ?>)"><i class="fa-solid fa-pen"></i></button>
                        <button class="btn-icon warning" title="Kullanıcıyı Askıya Al" onclick="userAction(<?= $c['reported_id'] ?>,'suspend','<?= esc($c['reported_name'] ?: $c['reported_email']) ?>')"><i class="fa-solid fa-pause"></i></button>
                        <?php endif; ?>
                    </div>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>

    <?php if ($totalPages > 1): ?>
    <div class="pagination">
        <?php $qs = $filterStatus !== '' ? "&status=$filterStatus" : ''; ?>
        <a href="?page=<?= max(1,$page-1) . $qs ?>" class="<?= $page<=1?'disabled':'' ?>">‹ Önceki</a>
        <?php for ($i=1; $i<=$totalPages; $i++): ?>
        <a href="?page=<?= $i . $qs ?>" class="<?= $i===$page?'active':'' ?>"><?= $i ?></a>
        <?php endfor; ?>
        <a href="?page=<?= min($totalPages,$page+1) . $qs ?>" class="<?= $page>=$totalPages?'disabled':'' ?>">Sonraki ›</a>
    </div>
    <?php endif; ?>
</div>

<!-- ŞİKAYET DETAY MODAL -->
<div class="modal-overlay" id="complaintModal">
    <div class="modal">
        <div class="modal-header">
            <h3><i class="fa-solid fa-flag"></i> Şikayet Detayı</h3>
            <button class="modal-close" onclick="closeModal('complaintModal')">&times;</button>
        </div>
        <div class="modal-body">
            <input type="hidden" id="complaintId">
            <input type="hidden" id="complaintReportedId">
            <p style="margin-bottom:8px;font-size:0.85rem;color:var(--text-dim);">Hakkında: <strong id="complaintUserName"></strong></p>
            <p style="margin-bottom:16px;font-size:0.85rem;color:var(--text-dim);" id="complaintDesc"></p>
            <div class="form-group">
                <label>Durumu Güncelle</label>
                <select id="complaintStatus" class="form-control">
                    <option value="open">Açık</option>
                    <option value="investigating">İnceleniyor</option>
                    <option value="resolved">Çözüldü</option>
                    <option value="dismissed">Reddedildi</option>
                </select>
            </div>
            <div class="form-group">
                <label>Admin Notu</label>
                <textarea id="complaintNote" class="form-control" rows="2" placeholder="Not ekleyin..."></textarea>
            </div>
        </div>
        <div class="modal-footer">
            <button class="btn btn-ghost" onclick="closeModal('complaintModal')">İptal</button>
            <button class="btn btn-warning" onclick="openWarningModal(document.getElementById('complaintReportedId').value, document.getElementById('complaintUserName').textContent)"><i class="fa-solid fa-triangle-exclamation"></i> Uyarı Gönder</button>
            <button class="btn btn-primary" onclick="updateComplaint()"><i class="fa-solid fa-check"></i> Kaydet</button>
        </div>
    </div>
</div>

<!-- UYARI MODAL (complaints'ten de kullanılır) -->
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
