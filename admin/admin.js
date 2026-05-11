// ===== KAMPÜS YOLU — ADMIN PANEL JS =====

// --- CSRF Token (sayfadan alınır) ---
function getCsrf() {
    const el = document.querySelector('meta[name="csrf_token"]');
    return el ? el.getAttribute('content') : '';
}

// --- API Helper ---
function adminApi(endpoint, options = {}) {
    const url = 'api/' + endpoint;
    return fetch(url, options)
        .then(async r => {
            const text = await r.text();
            try { return JSON.parse(text); }
            catch(e) { throw new Error('Geçersiz yanıt: ' + text.substring(0, 200)); }
        });
}

function adminPost(endpoint, data = {}) {
    data.csrf_token = getCsrf();
    return adminApi(endpoint, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(data)
    });
}

// --- MODAL ---
function openModal(id) {
    document.getElementById(id).classList.add('active');
}
function closeModal(id) {
    document.getElementById(id).classList.remove('active');
}

// --- KULLANICI İŞLEMLERİ ---
function userAction(userId, action, userName) {
    const messages = {
        'suspend': `"${userName}" kullanıcısını askıya almak istediğinize emin misiniz?`,
        'unsuspend': `"${userName}" kullanıcısının askısını kaldırmak istediğinize emin misiniz?`,
        'delete': `"${userName}" kullanıcısını kalıcı olarak silmek istediğinize emin misiniz? Bu işlem geri alınamaz!`,
        'make_admin': `"${userName}" kullanıcısına Yönetici (Admin) yetkisi vermek istediğinize emin misiniz?`,
        'remove_admin': `"${userName}" kullanıcısının Yönetici yetkisini almak istediğinize emin misiniz?`
    };
    if (!confirm(messages[action] || 'Devam etmek istiyor musunuz?')) return;

    adminPost('user_action.php', { userId, action })
        .then(data => {
            if (data.success) {
                alert('İşlem başarılı: ' + (data.message || ''));
                location.reload();
            } else {
                alert('Hata: ' + (data.error || 'Bilinmeyen hata'));
            }
        })
        .catch(e => alert('İstek hatası: ' + e.message));
}

// --- UYARI GÖNDER ---
function openWarningModal(userId, userName) {
    document.getElementById('warningUserId').value = userId;
    document.getElementById('warningUserName').textContent = userName;
    document.getElementById('warningMessage').value = '';
    openModal('warningModal');
}

function sendWarning() {
    const userId = parseInt(document.getElementById('warningUserId').value);
    const severity = document.getElementById('warningSeverity').value;
    const message = document.getElementById('warningMessage').value.trim();
    if (!message) return alert('Mesaj alanı boş olamaz.');

    adminPost('send_warning.php', { userId, severity, message })
        .then(data => {
            if (data.success) {
                alert('Uyarı başarıyla gönderildi.');
                closeModal('warningModal');
            } else {
                alert('Hata: ' + (data.error || 'Bilinmeyen hata'));
            }
        })
        .catch(e => alert('İstek hatası: ' + e.message));
}

// --- BELGE İNCELEME ---
function reviewDoc(docId, status) {
    const msg = status === 'approved' ? 'Bu belgeyi onaylamak istiyor musunuz?' : 'Bu belgeyi reddetmek istiyor musunuz?';
    if (!confirm(msg)) return;

    const note = status === 'rejected' ? (prompt('Red nedeni (opsiyonel):') || '') : '';

    adminPost('review_document.php', { docId, status, note })
        .then(data => {
            if (data.success) {
                alert('Belge ' + (status === 'approved' ? 'onaylandı' : 'reddedildi') + '.');
                location.reload();
            } else {
                alert('Hata: ' + (data.error || 'Bilinmeyen hata'));
            }
        })
        .catch(e => alert('İstek hatası: ' + e.message));
}

// --- ŞİKAYET GÜNCELLEME ---
function openComplaintModal(id, userName, desc, status, reportedId) {
    document.getElementById('complaintId').value = id;
    document.getElementById('complaintReportedId').value = reportedId;
    document.getElementById('complaintUserName').textContent = userName;
    document.getElementById('complaintDesc').textContent = desc;
    document.getElementById('complaintStatus').value = status;
    document.getElementById('complaintNote').value = '';
    openModal('complaintModal');
}

function updateComplaint() {
    const complaintId = parseInt(document.getElementById('complaintId').value);
    const status = document.getElementById('complaintStatus').value;
    const note = document.getElementById('complaintNote').value.trim();

    adminPost('update_complaint.php', { complaintId, status, note })
        .then(data => {
            if (data.success) {
                alert('Şikayet güncellendi.');
                closeModal('complaintModal');
                location.reload();
            } else {
                alert('Hata: ' + (data.error || 'Bilinmeyen hata'));
            }
        })
        .catch(e => alert('İstek hatası: ' + e.message));
}

// --- ISI HARİTASI ---
let heatmapInstance = null;
let heatLayer = null;

function initHeatmap() {
    if (!document.getElementById('heatmapContainer')) return;
    heatmapInstance = L.map('heatmapContainer').setView([39.905, 41.240], 15);
    L.tileLayer('https://{s}.basemaps.cartocdn.com/dark_all/{z}/{x}/{y}{r}.png', {
        attribution: '© CartoDB | Kampüs Yolu Admin'
    }).addTo(heatmapInstance);
    loadHeatmapData();
}

function loadHeatmapData() {
    if (!heatmapInstance) return;
    const period = document.getElementById('heatmapPeriod')?.value || '168';
    const hours = document.getElementById('heatmapHours')?.value || '';

    fetch(`api/get_heatmap_data.php?hours=${period}&timeRange=${hours}`)
        .then(r => r.json())
        .then(data => {
            if (heatLayer) heatmapInstance.removeLayer(heatLayer);
            const points = (data.points || []).map(p => [p.lat, p.lng, p.intensity || 0.5]);
            heatLayer = L.heatLayer(points, {
                radius: 25, blur: 15, maxZoom: 18,
                gradient: { 0.2: '#2563eb', 0.4: '#10b981', 0.6: '#f59e0b', 0.8: '#f97316', 1.0: '#ef4444' }
            }).addTo(heatmapInstance);
            const countEl = document.getElementById('heatmapCount');
            if (countEl) countEl.textContent = `${points.length} konum noktası`;
        })
        .catch(e => console.error('Heatmap hata:', e));
}

// --- ESC ile modal kapat ---
document.addEventListener('keydown', e => {
    if (e.key === 'Escape') {
        document.querySelectorAll('.modal-overlay.active').forEach(m => m.classList.remove('active'));
    }
});

// Overlay tıklama ile kapat
document.querySelectorAll('.modal-overlay').forEach(overlay => {
    overlay.addEventListener('click', e => {
        if (e.target === overlay) overlay.classList.remove('active');
    });
});

// --- TEMA YÖNETİMİ ---
const adminTemaBtn = document.getElementById('adminTemaBtn');
const htmlEtiketi = document.documentElement;

function setAdminTema(tip) {
    if (tip === 'light') {
        htmlEtiketi.setAttribute('data-tema', 'light');
        localStorage.setItem('kampusYoluTemasi', 'light');
        if(adminTemaBtn) adminTemaBtn.innerHTML = '<i class="fa-solid fa-moon"></i>';
    } else {
        htmlEtiketi.removeAttribute('data-tema');
        localStorage.setItem('kampusYoluTemasi', 'dark');
        if(adminTemaBtn) adminTemaBtn.innerHTML = '<i class="fa-solid fa-sun"></i>';
    }
}

setAdminTema(localStorage.getItem('kampusYoluTemasi') === 'light' ? 'light' : 'dark');

if (adminTemaBtn) {
    adminTemaBtn.addEventListener('click', () => {
        setAdminTema(htmlEtiketi.getAttribute('data-tema') === 'light' ? 'dark' : 'light');
    });
}

// --- INIT ---
document.addEventListener('DOMContentLoaded', () => {
    if (typeof L !== 'undefined' && document.getElementById('heatmapContainer')) {
        initHeatmap();
    }
});
