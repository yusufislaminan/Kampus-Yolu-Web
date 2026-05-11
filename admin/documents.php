<?php
declare(strict_types=1);
require __DIR__ . '/inc/auth.php';
require_admin();

$pageTitle = 'Belge Doğrulama';
$activePage = 'documents';

$filterStatus = $_GET['status'] ?? 'pending';
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 20;
$offset = ($page - 1) * $perPage;

$where = $filterStatus !== '' ? "WHERE d.status = ?" : "";
$params = $filterStatus !== '' ? [$filterStatus] : [];

$stCount = $pdo->prepare("SELECT COUNT(*) FROM user_documents d $where");
$stCount->execute($params);
$total = (int) $stCount->fetchColumn();
$totalPages = max(1, (int) ceil($total / $perPage));

$stDocs = $pdo->prepare(
    "SELECT d.*, u.display_name, u.email, u.trust_level,
            reviewer.display_name as reviewer_name
     FROM user_documents d
     JOIN users u ON d.user_id = u.id
     LEFT JOIN users reviewer ON d.reviewed_by = reviewer.id
     $where
     ORDER BY d.created_at DESC
     LIMIT $perPage OFFSET $offset"
);
$stDocs->execute($params);
$docs = $stDocs->fetchAll();

$docTypeLabels = ['ogrenci_belgesi'=>'Öğrenci Belgesi','sabika_kaydi'=>'Sabıka Kaydı','kimlik'=>'Kimlik','diger'=>'Diğer'];

require __DIR__ . '/inc/header.php';
?>

<div class="panel">
    <div class="filters-bar">
        <a href="?status=pending" class="btn <?= $filterStatus==='pending'?'btn-warning':'btn-ghost' ?> btn-sm">Bekleyen</a>
        <a href="?status=approved" class="btn <?= $filterStatus==='approved'?'btn-primary':'btn-ghost' ?> btn-sm">Onaylanan</a>
        <a href="?status=rejected" class="btn <?= $filterStatus==='rejected'?'btn-danger':'btn-ghost' ?> btn-sm">Reddedilen</a>
        <a href="?status=" class="btn <?= $filterStatus===''?'btn-info':'btn-ghost' ?> btn-sm">Tümü</a>
        <span style="margin-left:auto;font-size:0.8rem;color:var(--text-muted);">Toplam: <?= $total ?></span>
    </div>

    <div class="panel-body no-pad">
        <?php if (empty($docs)): ?>
        <div class="empty-state"><i class="fa-solid fa-file-circle-check"></i><p>Belge bulunamadı.</p></div>
        <?php else: ?>
        <table class="data-table">
            <thead>
                <tr><th>Kullanıcı</th><th>Belge Türü</th><th>Dosya</th><th>Boyut</th><th>Durum</th><th>Tarih</th><th>İşlem</th></tr>
            </thead>
            <tbody>
            <?php foreach ($docs as $d): ?>
            <tr>
                <td>
                    <div style="font-weight:600;font-size:0.85rem;"><?= esc($d['display_name'] ?: $d['email']) ?></div>
                    <div style="font-size:0.75rem;color:var(--text-muted);">Güven: Lv.<?= $d['trust_level'] ?></div>
                </td>
                <td><?= esc($docTypeLabels[$d['doc_type']] ?? $d['doc_type']) ?></td>
                <td style="font-size:0.8rem;"><?= esc($d['original_name']) ?></td>
                <td style="font-size:0.8rem;color:var(--text-muted);"><?= round($d['file_size']/1024) ?> KB</td>
                <td><span class="badge-status badge-<?= esc($d['status']) ?>"><?= esc(ucfirst($d['status'])) ?></span></td>
                <td style="font-size:0.78rem;color:var(--text-muted);"><?= date('d.m.Y H:i', strtotime($d['created_at'])) ?></td>
                <td>
                    <div class="flex gap-4" style="gap:4px;">
                        <a href="api/view_document.php?id=<?= $d['id'] ?>" target="_blank" class="btn-icon info" title="Görüntüle"><i class="fa-solid fa-eye"></i></a>
                        <?php if ($d['status'] === 'pending'): ?>
                        <button class="btn-icon success" title="Onayla" onclick="reviewDoc(<?= $d['id'] ?>,'approved')"><i class="fa-solid fa-check"></i></button>
                        <button class="btn-icon danger" title="Reddet" onclick="reviewDoc(<?= $d['id'] ?>,'rejected')"><i class="fa-solid fa-xmark"></i></button>
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

<?php require __DIR__ . '/inc/footer.php'; ?>
