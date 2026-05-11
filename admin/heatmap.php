<?php
declare(strict_types=1);
require __DIR__ . '/inc/auth.php';
require_admin();

$pageTitle = 'Rota Isı Haritası';
$activePage = 'heatmap';

require __DIR__ . '/inc/header.php';
?>

<div class="panel mb-16">
    <div class="filters-bar">
        <label style="font-size:0.82rem;font-weight:600;color:var(--text-dim);">Zaman Aralığı:</label>
        <select id="heatmapPeriod" class="form-control" style="width:auto;">
            <option value="24">Son 24 Saat</option>
            <option value="168" selected>Son 7 Gün</option>
            <option value="720">Son 30 Gün</option>
        </select>
        <label style="font-size:0.82rem;font-weight:600;color:var(--text-dim);margin-left:12px;">Saat Dilimi:</label>
        <select id="heatmapHours" class="form-control" style="width:auto;">
            <option value="">Tümü</option>
            <option value="6-12">Sabah (06-12)</option>
            <option value="12-18">Öğle (12-18)</option>
            <option value="18-24">Akşam (18-24)</option>
            <option value="0-6">Gece (00-06)</option>
        </select>
        <button class="btn btn-primary btn-sm" onclick="loadHeatmapData()"><i class="fa-solid fa-sync"></i> Yenile</button>
        <span id="heatmapCount" style="margin-left:auto;font-size:0.8rem;color:var(--text-muted);"></span>
    </div>
</div>

<div style="height:65vh;border-radius:var(--radius);overflow:hidden;border:1px solid var(--border);">
    <div id="heatmapContainer" style="width:100%;height:100%;"></div>
</div>

<script>
// Heatmap init fonksiyonu admin.js'te çağrılacak
window.HEATMAP_API = 'api/get_heatmap_data.php';
</script>

<?php require __DIR__ . '/inc/footer.php'; ?>
