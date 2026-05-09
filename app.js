// ======= KAMPÃœS YOLU - ANA UYGULAMA =======

// --- GLOBAL STATE ---
let aktifKullanici = null; // { userId, email, display_name, gender, interests, role }
let kampusHaritasi = null;
let konumWatchId = null;
let pollingInterval = null;
let kullaniciMarkerlar = {};
let benimMarkerim = null;
let benimKonumum = null; // { lat, lng }
let haritaSecimModu = false;
let seciliHobiler = new Set();
let tumHobiler = {};
let aktifMesajMatch = null;
let mesajPollingInterval = null;

// --- 1. TEMA YÃ–NETÄ°MÄ° ---
const temaDegistirBtn = document.getElementById('temaDegistirBtn');
const htmlEtiketi = document.documentElement;

function setTema(tip) {
    if (tip === 'dark') {
        htmlEtiketi.setAttribute('data-tema', 'dark');
        localStorage.setItem('kampusYoluTemasi', 'dark');
        temaDegistirBtn.innerHTML = '<i class="fa-solid fa-sun"></i>';
    } else {
        htmlEtiketi.removeAttribute('data-tema');
        localStorage.setItem('kampusYoluTemasi', 'light');
        temaDegistirBtn.innerHTML = '<i class="fa-solid fa-moon"></i>';
    }
}
setTema(localStorage.getItem('kampusYoluTemasi') === 'dark' ? 'dark' : 'light');
temaDegistirBtn.addEventListener('click', () => {
    setTema(htmlEtiketi.getAttribute('data-tema') === 'dark' ? 'light' : 'dark');
});

const API_ROOT = getApiRoot();

function getApiRoot() {
    if (window.location.protocol === 'file:') {
        const path = window.location.pathname.replace(/\\/g, '/');
        const parts = path.split('/');
        const frontIndex = parts.lastIndexOf('FRONT');
        const folder = frontIndex !== -1 ? parts[frontIndex] : (parts.length > 1 ? parts[parts.length - 2] : 'FRONT');
        const root = `http://localhost/${folder}/backend/`;
        alert('Bu proje Wampserver üzerinde çalıştırılmalıdır. Dosyayı file:// ile açmayın.\nÖnce projeyi Wampserver www klasörüne taşıyıp http://localhost/' + folder + '/index.html ile açın.');
        console.warn('⚠️ File protocol detected. Using fallback API root:', root);
        return root;
    }
    const path = window.location.pathname.replace(/\\/g, '/');
    const directory = path.endsWith('/') ? path : path.substring(0, path.lastIndexOf('/') + 1);
    const root = window.location.origin + directory + 'backend/';
    console.log('✅ API Root initialized:', root);
    return root;
}


function apiFetch(endpoint, options = {}) {
    const url = API_ROOT + endpoint;
    console.log('📡 API Call:', url, options);
    return fetch(url, options)
        .then(async response => {
            const text = await response.text();
            let data = null;
            try {
                data = text ? JSON.parse(text) : null;
            } catch (err) {
                console.error('❌ JSON Parse Error:', text);
                throw new Error('Beklenmeyen backend yanÄ±tÄ±: ' + text);
            }
            console.log('✅ API Response:', endpoint, data);
            if (!response.ok) {
                const errMsg = data?.error || data?.message || `HTTP ${response.status}`;
                console.error('❌ API Error:', errMsg);
                throw new Error(errMsg);
            }
            return data;
        })
        .catch(e => {
            console.error('❌ API Fetch Error:', e.message);
            throw e;
        });
}

// --- 2. DOÄRULAMA ---
function simpleValidateEmail(e) {
    if (typeof e !== 'string' || !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(e)) return { ok: false, message: 'E-posta formatÄ± hatalÄ±.' };
    return { ok: true };
}
function simpleValidatePassword(p) {
    if (typeof p !== 'string') return { ok: false, message: 'Åifre geÃ§ersiz.' };
    if (p.length < 8) return { ok: false, message: 'Åifre en az 8 karakter olmalÄ±.' };
    if (!/[A-Z]/.test(p)) return { ok: false, message: 'Åifre en az 1 bÃ¼yÃ¼k harf iÃ§ermeli.' };
    if (!/[a-z]/.test(p)) return { ok: false, message: 'Åifre en az 1 kÃ¼Ã§Ã¼k harf iÃ§ermeli.' };
    if (!/[0-9]/.test(p)) return { ok: false, message: 'Åifre en az 1 rakam iÃ§ermeli.' };
    return { ok: true };
}

// --- 3. GÄ°RÄ°Å ---
const girisFormu = document.getElementById('girisFormu');
const girisEkrani = document.getElementById('girisEkrani');
const anaUygulama = document.getElementById('anaUygulama');
const adminPaneli = document.getElementById('adminPaneli');
const kullaniciProfiliMini = document.getElementById('kullaniciProfiliMini');

girisFormu.addEventListener('submit', function(e) {
    e.preventDefault();
    const eposta = document.getElementById('epostaGirdisi').value;
    const sifre = document.getElementById('sifreGirdisi').value;
    if (!simpleValidateEmail(eposta).ok) return alert(simpleValidateEmail(eposta).message);
    if (!simpleValidatePassword(sifre).ok) return alert(simpleValidatePassword(sifre).message);

    girisEkrani.classList.add('gizli');
    kullaniciProfiliMini.classList.remove('gizli');

    apiFetch('login.php', {
        method: 'POST', headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ eposta, sifre })
    })
    .then(data => {
        if (!data?.success) {
            alert('E-posta veya ÅŸifre hatalÄ±.');
            girisEkrani.classList.remove('gizli');
            kullaniciProfiliMini.classList.add('gizli');
            return;
        }
        aktifKullanici = {
            userId: data.userId, email: data.email,
            display_name: data.display_name || '', gender: data.gender || 'belirtmek_istemiyorum',
            profile_pic: data.profile_pic || null,
            interests: data.interests || [], role: data.role
        };
        document.getElementById('aktifKullaniciAdi').innerHTML =
            (aktifKullanici.display_name || aktifKullanici.email) + ' <i class="fa-solid fa-circle-check text-yesil"></i>';

        if (data.role === 'admin') {
            adminPaneli.classList.remove('gizli');
        } else {
            anaUygulama.classList.remove('gizli');
            haritayiYukle();
            konumBaslat();
            hobileriYukle();
            profilDoldur();
            eslesmelerYukle();
        }
    })
    .catch(e => {
        console.error('Login error:', e);
        alert('GiriÅŸ baÅŸarÄ±sÄ±z. Backend baÄŸlantÄ±sÄ±nÄ± kontrol edin.\n' + e.message);
        girisEkrani.classList.remove('gizli');
        kullaniciProfiliMini.classList.add('gizli');
    });
});

function cikisYap() {
    if (konumWatchId) navigator.geolocation.clearWatch(konumWatchId);
    if (pollingInterval) clearInterval(pollingInterval);
    if (mesajPollingInterval) clearInterval(mesajPollingInterval);
    aktifKullanici = null;
    anaUygulama.classList.add('gizli');
    adminPaneli.classList.add('gizli');
    kullaniciProfiliMini.classList.add('gizli');
    girisEkrani.classList.remove('gizli');
}

// --- KAYIT ---
const kayitOlLink = document.getElementById('kayitOlLink');
const kayitKarti = document.getElementById('kayitKarti');
const kayitVazgecBtn = document.getElementById('kayitVazgecBtn');
if (kayitOlLink) kayitOlLink.addEventListener('click', e => { e.preventDefault(); kayitKarti.classList.remove('gizli'); });
if (kayitVazgecBtn) kayitVazgecBtn.addEventListener('click', () => kayitKarti.classList.add('gizli'));

const kayitFormu = document.getElementById('kayitFormu');
if (kayitFormu) {
    kayitFormu.addEventListener('submit', e => {
        e.preventDefault();
        const isim = document.getElementById('kayitIsim')?.value?.trim() ?? '';
        const eposta = document.getElementById('kayitEposta')?.value?.trim() ?? '';
        const cinsiyet = document.getElementById('kayitCinsiyet')?.value ?? 'belirtmek_istemiyorum';
        const sifre = document.getElementById('kayitSifre')?.value ?? '';
        const sifreTekrar = document.getElementById('kayitSifreTekrar')?.value ?? '';
        if (!eposta) return alert('E-posta gerekli.');
        if (!simpleValidateEmail(eposta).ok) return alert(simpleValidateEmail(eposta).message);
        if (!simpleValidatePassword(sifre).ok) return alert(simpleValidatePassword(sifre).message);
        if (sifre !== sifreTekrar) return alert('Åifreler eÅŸleÅŸmiyor.');

        apiFetch('register.php', {
            method: 'POST', headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ eposta, sifre, sifreTekrar, display_name: isim, gender: cinsiyet })
        })
        .then(data => {
            alert('KayÄ±t baÅŸarÄ±lÄ±! GiriÅŸ yapabilirsiniz.');
            kayitKarti.classList.add('gizli');
        })
        .catch(e => {
            console.error('Register error:', e);
            alert('KayÄ±t isteÄŸi baÅŸarÄ±sÄ±z.\n' + e.message);
        });
    });
}

// --- 4. HARÄ°TA ---
function haritayiYukle() {
    if (kampusHaritasi) return;
    const varsayilan = [39.905, 41.240];
    kampusHaritasi = L.map('haritaAlani').setView(varsayilan, 15);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: 'Â© OpenStreetMap | KampÃ¼s Yolu'
    }).addTo(kampusHaritasi);

    // Harita tÄ±klama - konum seÃ§me modu
    kampusHaritasi.on('click', function(e) {
        if (!haritaSecimModu) return;
        benimKonumum = { lat: e.latlng.lat, lng: e.latlng.lng };
        haritaSecimModu = false;
        document.getElementById('haritaSecimUyari').classList.add('gizli');
        benimMarkerGuncelle();
        konumGuncelle(benimKonumum.lat, benimKonumum.lng);
        konumDurumGuncelle('basarili', 'Konum haritadan seÃ§ildi');
    });
}

// --- 5. KONUM YÃ–NETÄ°MÄ° ---
function konumBaslat() {
    if (!navigator.geolocation) {
        konumDurumGuncelle('hata', 'TarayÄ±cÄ± konum desteklemiyor');
        haritadanSecModuAc();
        return;
    }
    konumDurumGuncelle('yukleniyor', 'Konum alÄ±nÄ±yor...');
    konumWatchId = navigator.geolocation.watchPosition(
        pos => {
            benimKonumum = { lat: pos.coords.latitude, lng: pos.coords.longitude };
            benimMarkerGuncelle();
            konumGuncelle(benimKonumum.lat, benimKonumum.lng);
            konumDurumGuncelle('basarili', 'Konum aktif');
        },
        err => {
            console.warn('Konum hatasÄ±:', err.message);
            konumDurumGuncelle('hata', 'Konum alÄ±namadÄ±');
            haritadanSecModuAc();
        },
        { enableHighAccuracy: true, maximumAge: 10000, timeout: 15000 }
    );
}

function haritadanSecModuAc() {
    haritaSecimModu = true;
    document.getElementById('haritaSecimUyari').classList.remove('gizli');
    konumDurumGuncelle('hata', 'Haritaya tÄ±klayarak konum seÃ§in');
}
function haritaSecimIptal() {
    haritaSecimModu = false;
    document.getElementById('haritaSecimUyari').classList.add('gizli');
}

function konumDurumGuncelle(tip, mesaj) {
    const el = document.getElementById('konumDurum');
    el.className = 'konum-durum ' + tip;
    if (tip === 'basarili') el.innerHTML = '<span class="pulse-dot"></span> ' + mesaj;
    else if (tip === 'hata') el.innerHTML = '<i class="fa-solid fa-triangle-exclamation"></i> ' + mesaj;
    else el.innerHTML = '<span class="pulse-dot"></span> ' + mesaj;
}

function kullaniciAvatarHtml(profilePic, displayName, sinif) {
    if (profilePic) {
        return `<img src="${API_ROOT}uploads/avatars/${profilePic}" alt="${displayName || ''}" class="${sinif}">`;
    }
    return `<span class="${sinif}-text">${(displayName || 'A')[0].toUpperCase()}</span>`;
}

function benimMarkerGuncelle() {
    if (!kampusHaritasi || !benimKonumum) return;
    const isim = aktifKullanici?.display_name || 'Ben';
    const pic = aktifKullanici?.profile_pic;
    const avatarInner = pic
        ? `<img src="${API_ROOT}uploads/avatars/${pic}" alt="${isim}" class="marker-avatar-img">`
        : `<span class="marker-avatar-text">${isim[0].toUpperCase()}</span>`;
    const markerHtml = `<div class="custom-marker benim-marker">
        <div class="marker-avatar" style="border-color:#3b82f6">${avatarInner}</div>
        <div class="marker-isim">${isim}</div>
        <div class="marker-ok" style="border-top-color:#3b82f6"></div>
    </div>`;
    const icon = L.divIcon({ html: markerHtml, className: 'marker-div-icon', iconSize: [60, 75], iconAnchor: [30, 75], popupAnchor: [0, -70] });

    if (benimMarkerim) {
        benimMarkerim.setLatLng([benimKonumum.lat, benimKonumum.lng]).setIcon(icon);
    } else {
        benimMarkerim = L.marker([benimKonumum.lat, benimKonumum.lng], { icon })
            .addTo(kampusHaritasi).bindPopup('<b>Sen buradasÄ±n</b>');
        kampusHaritasi.setView([benimKonumum.lat, benimKonumum.lng], 15);
    }
}

function konumGuncelle(lat, lng) {
    if (!aktifKullanici) return;
    apiFetch('update_location.php', {
        method: 'POST', headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ userId: aktifKullanici.userId, latitude: lat, longitude: lng })
    }).catch(e => console.error('Konum gÃ¼ncelleme hatasÄ±:', e));
}

// --- 6. YAKINLARI BULMA (POLLING) ---
const yaricapSlider = document.getElementById('yaricapSlider');
const yaricapDegeri = document.getElementById('yaricapDegeri');
if (yaricapSlider) {
    yaricapSlider.addEventListener('input', () => {
        const km = (parseInt(yaricapSlider.value) / 1000).toFixed(1);
        yaricapDegeri.textContent = km + ' km';
    });
}

document.getElementById('yakindakileriBulBtn')?.addEventListener('click', () => {
    if (!benimKonumum) return alert('Ã–nce konumunuzun alÄ±nmasÄ±nÄ± bekleyin veya haritadan seÃ§in.');
    yakinlariGetir();
    // 3 saniyelik polling baÅŸlat
    if (pollingInterval) clearInterval(pollingInterval);
    pollingInterval = setInterval(yakinlariGetir, 3000);
});

// Sayfa gÃ¶rÃ¼nÃ¼rlÃ¼ÄŸÃ¼: gizliyken polling durdur
document.addEventListener('visibilitychange', () => {
    if (document.hidden && pollingInterval) { clearInterval(pollingInterval); pollingInterval = null; }
});

function yakinlariGetir() {
    if (!aktifKullanici || !benimKonumum) return;
    const radius = parseInt(yaricapSlider?.value || 2000);
    const gender = document.getElementById('cinsiyetTercihi')?.value || 'farketmez';

    apiFetch('get_nearby_users.php', {
        method: 'POST', headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ userId: aktifKullanici.userId, radius, genderPref: gender })
    })
    .then(data => {
        haritaMarkerlariGuncelle(data.users || []);
    })
    .catch(e => console.error('YakÄ±n arama hatasÄ±:', e));
}

function uyumRengi(score) {
    if (score >= 70) return '#10b981';
    if (score >= 40) return '#f59e0b';
    return '#ef4444';
}

function haritaMarkerlariGuncelle(users) {
    if (!kampusHaritasi) return;
    const mevcutIdler = new Set(users.map(u => u.id));

    Object.keys(kullaniciMarkerlar).forEach(id => {
        if (!mevcutIdler.has(parseInt(id))) {
            kampusHaritasi.removeLayer(kullaniciMarkerlar[id]);
            delete kullaniciMarkerlar[id];
        }
    });

    users.forEach(u => {
        const renk = uyumRengi(u.compatibility);
        const avatarInner = u.profile_pic
            ? `<img src="${API_ROOT}uploads/avatars/${u.profile_pic}" alt="${u.display_name || ''}" class="marker-avatar-img">`
            : `<span class="marker-avatar-text">${(u.display_name || 'A')[0].toUpperCase()}</span>`;

        const markerHtml = `<div class="custom-marker">
            <div class="marker-avatar" style="border-color:${renk}">${avatarInner}</div>
            <div class="marker-isim">${u.display_name || 'Anonim'}</div>
            <div class="marker-ok" style="border-top-color:${renk}"></div>
        </div>`;

        const icon = L.divIcon({ html: markerHtml, className: 'marker-div-icon', iconSize: [60, 75], iconAnchor: [30, 75], popupAnchor: [0, -70] });

        const hobilerHtml = (u.interests || []).slice(0, 5).map(i =>
            `<span class="popup-etiket">${i.icon || ''} ${i.name}</span>`
        ).join('');

        const popupAvatarHtml = u.profile_pic
            ? `<img src="${API_ROOT}uploads/avatars/${u.profile_pic}" class="popup-avatar-img">`
            : '';

        const popup = `<div class="kullanici-popup">
            ${popupAvatarHtml}
            <h4>${u.display_name || 'Anonim'}</h4>
            <div class="uyum-bilgi">Uyum: %${u.compatibility} Â· ${Math.round(u.distance)}m uzakta</div>
            <div class="uyum-bar-bg"><div class="uyum-bar" style="width:${u.compatibility}%;background:${renk};"></div></div>
            <div class="popup-etiketler">${hobilerHtml}</div>
            <div class="popup-butonlar">
                <button class="popup-buton" onclick="eslesmeGonder(${u.id})"><i class="fa-solid fa-handshake"></i> EÅŸleÅŸ</button>
            </div>
        </div>`;

        if (kullaniciMarkerlar[u.id]) {
            kullaniciMarkerlar[u.id].setLatLng([u.lat, u.lng]).setIcon(icon);
            kullaniciMarkerlar[u.id].getPopup()?.setContent(popup);
        } else {
            kullaniciMarkerlar[u.id] = L.marker([u.lat, u.lng], { icon })
                .addTo(kampusHaritasi).bindPopup(popup);
        }
    });
}

// --- 7. EÅžLEÅžME ---
function eslesmeGonder(targetId) {
    if (!aktifKullanici) return;
    apiFetch('create_match.php', {
        method: 'POST', headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ userId: aktifKullanici.userId, targetUserId: targetId })
    })
    .then(data => {
        alert('EÅŸleÅŸme isteÄŸi gÃ¶nderildi!');
        if (data.midpoint && benimKonumum) {
            ortaNoktaGoster(benimKonumum, data.midpoint);
        }
        eslesmelerYukle();
    })
    .catch(() => alert('EÅŸleÅŸme hatasÄ±.'));
}

function ortaNoktaGoster(konum1, konum2) {
    if (!kampusHaritasi) return;
    let mid;
    if (typeof turf !== 'undefined') {
        const p1 = turf.point([konum1.lng, konum1.lat]);
        const p2 = turf.point([konum2.lng, konum2.lat]);
        const mp = turf.midpoint(p1, p2);
        mid = { lat: mp.geometry.coordinates[1], lng: mp.geometry.coordinates[0] };
    } else {
        mid = { lat: (konum1.lat + konum2.lat) / 2, lng: (konum1.lng + konum2.lng) / 2 };
    }

    const icon = L.divIcon({
        html: '<div style="width:20px;height:20px;background:linear-gradient(135deg,#f59e0b,#ef4444);border-radius:50%;border:3px solid #fff;box-shadow:0 0 12px rgba(245,158,11,0.5);display:flex;align-items:center;justify-content:center;"><i class="fa-solid fa-flag" style="color:#fff;font-size:8px;"></i></div>',
        className: '', iconSize: [20, 20], iconAnchor: [10, 10]
    });
    L.marker([mid.lat, mid.lng], { icon }).addTo(kampusHaritasi)
        .bindPopup('<b>BuluÅŸma NoktasÄ±</b><br>Ä°kinizin orta noktasÄ±').openPopup();

    L.polyline([[konum1.lat, konum1.lng], [mid.lat, mid.lng]], {
        color: '#f59e0b', weight: 3, dashArray: '8,8', opacity: 0.8
    }).addTo(kampusHaritasi);
}

// --- 8. EŞLEŞMELER LİSTESİ ---
function eslesmelerYukle() {
    if (!aktifKullanici) return;
    apiFetch('get_matches.php', {
        method: 'POST', headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ userId: aktifKullanici.userId })
    })
    .then(data => {
        const alan = document.getElementById('istekListesiAlani');
        const matches = data.matches || [];
        let toplamOkunmamis = 0;

        if (matches.length === 0) {
            alan.innerHTML = '<p style="text-align:center;color:var(--yazi-ikincil);padding:40px 0;"><i class="fa-solid fa-inbox" style="font-size:2rem;display:block;margin-bottom:10px;"></i>Henüz eşleşme yok.</p>';
        } else {
            alan.innerHTML = matches.map(m => {
                toplamOkunmamis += m.unreadCount || 0;
                const renk = uyumRengi(m.compatibility);
                const avatarHtml = m.otherProfilePic
                    ? `<img src="${API_ROOT}uploads/avatars/${m.otherProfilePic}" class="eslesme-avatar-img">`
                    : `<div class="eslesme-avatar-harf">${(m.otherDisplayName||'A')[0].toUpperCase()}</div>`;

                let durumHtml = '';
                let kartOnclick = '';
                if (m.status === 'accepted') {
                    durumHtml = '<span class="esleme-durum-badge aktif"><i class="fa-solid fa-check-circle"></i> Aktif</span>';
                    kartOnclick = `onclick="mesajAc(${m.matchId},'${(m.otherDisplayName||'').replace(/'/g,"\\\'")}',${m.compatibility},'accepted')"`;
                } else if (m.status === 'pending' && !m.isRequester) {
                    durumHtml = `<div class="kart-butonlari" style="margin-top:8px;">
                        <button class="buton-onay" onclick="event.stopPropagation();eslesmeKabulEt(${m.matchId})"><i class="fa-solid fa-check"></i> Kabul Et</button>
                        <button class="buton-red" onclick="event.stopPropagation();eslesmeReddet(${m.matchId})"><i class="fa-solid fa-xmark"></i> Reddet</button>
                    </div>`;
                } else if (m.status === 'pending' && m.isRequester) {
                    durumHtml = '<span class="esleme-durum-badge beklemede"><i class="fa-solid fa-clock"></i> Yanıt bekleniyor...</span>';
                }

                return `<div class="kart" style="cursor:${m.status === 'accepted' ? 'pointer' : 'default'};" ${kartOnclick}>
                    <div class="kart-baslik">
                        ${avatarHtml}
                        <div style="flex:1;">
                            <h4>${m.otherDisplayName || 'Anonim'}</h4>
                            <small style="color:var(--yazi-ikincil);">Uyum: %${m.compatibility}</small>
                        </div>
                        ${m.unreadCount > 0 ? `<span class="bildirim" style="position:static;">${m.unreadCount}</span>` : ''}
                        <button class="engelle-btn-kucuk" onclick="event.stopPropagation();kullaniciEngelle(${m.otherUserId},'${(m.otherDisplayName||'').replace(/'/g,"\\\'")}')" title="Engelle"><i class="fa-solid fa-ban"></i></button>
                    </div>
                    <div class="uyum-bar-bg"><div class="uyum-bar" style="width:${m.compatibility}%;background:${renk};"></div></div>
                    ${durumHtml}
                </div>`;
            }).join('');
        }
        const badge = document.getElementById('mesajBildirim');
        if (toplamOkunmamis > 0) { badge.textContent = toplamOkunmamis; badge.classList.remove('gizli'); }
        else { badge.classList.add('gizli'); }
        document.getElementById('toplamEslesme').textContent = matches.length;
        if (matches.length > 0) {
            const ort = Math.round(matches.reduce((t, m) => t + m.compatibility, 0) / matches.length);
            document.getElementById('ortalamaUyum').textContent = '%' + ort;
        }
    })
    .catch(e => console.error('Eşleşme yükleme hatası:', e));
}

// --- 9. MESAJLAŞMA ---
function mesajAc(matchId, isim, uyum, status) {
    if (status !== 'accepted') {
        alert('Mesajlaşma için eşleşmenin kabul edilmesi gerekir.');
        return;
    }
    aktifMesajMatch = matchId;
    document.getElementById('isteklerListeGorunumu').classList.add('gizli');
    document.getElementById('mesajGorunumu').classList.remove('gizli');
    document.getElementById('mesajKarsiIsim').textContent = isim;
    document.getElementById('mesajUyum').textContent = '%' + uyum + ' Uyum';
    document.getElementById('mesajListesi').innerHTML = '';
    // Mesaj inputunu aktif et
    document.getElementById('mesajInput').disabled = false;
    document.getElementById('mesajInput').placeholder = 'Mesajınızı yazın...';
    mesajlariYukle();
    if (mesajPollingInterval) clearInterval(mesajPollingInterval);
    mesajPollingInterval = setInterval(mesajlariYukle, 3000);
}

function mesajKapat() {
    aktifMesajMatch = null;
    if (mesajPollingInterval) clearInterval(mesajPollingInterval);
    document.getElementById('mesajGorunumu').classList.add('gizli');
    document.getElementById('isteklerListeGorunumu').classList.remove('gizli');
    eslesmelerYukle();
}

function mesajlariYukle() {
    if (!aktifKullanici || !aktifMesajMatch) return;
    const liste = document.getElementById('mesajListesi');
    const sonMesaj = liste.lastElementChild;
    const afterId = sonMesaj ? parseInt(sonMesaj.dataset.id || 0) : 0;

    apiFetch('get_messages.php', {
        method: 'POST', headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ matchId: aktifMesajMatch, userId: aktifKullanici.userId, afterId })
    })
    .then(data => {
        (data.messages || []).forEach(m => {
            const div = document.createElement('div');
            div.className = 'mesaj-balonu ' + (m.isMine ? 'benim' : 'karsi');
            div.dataset.id = m.id;
            const zaman = new Date(m.createdAt).toLocaleTimeString('tr-TR', { hour: '2-digit', minute: '2-digit' });
            div.innerHTML = m.content + '<div class="mesaj-zaman">' + zaman + '</div>';
            liste.appendChild(div);
        });
        if (data.messages?.length > 0) liste.scrollTop = liste.scrollHeight;
    })
    .catch(e => console.error('Mesaj yÃ¼kleme hatasÄ±:', e));
}

function mesajGonder() {
    const input = document.getElementById('mesajInput');
    const content = input.value.trim();
    if (!content || !aktifMesajMatch || !aktifKullanici) return;
    input.value = '';

    apiFetch('send_message.php', {
        method: 'POST', headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ matchId: aktifMesajMatch, senderId: aktifKullanici.userId, content })
    })
    .then(data => {
        mesajlariYukle();
    })
    .catch(e => alert('Mesaj gÃ¶nderme hatasÄ±.\n' + e.message));
}

// Enter ile mesaj gÃ¶nder
document.getElementById('mesajInput')?.addEventListener('keypress', e => {
    if (e.key === 'Enter') mesajGonder();
});

// --- 10. PROFİL & HOBİLER ---
function profilDoldur() {
    if (!aktifKullanici) return;
    document.getElementById('profilIsim').textContent = aktifKullanici.display_name || aktifKullanici.email;
    document.getElementById('profilEmail').textContent = aktifKullanici.email;
    document.getElementById('profilIsimInput').value = aktifKullanici.display_name || '';
    document.getElementById('profilCinsiyet').value = aktifKullanici.gender || 'belirtmek_istemiyorum';
    const avatar = document.getElementById('profilAvatar');
    const avatarImg = document.getElementById('profilAvatarImg');
    if (aktifKullanici.profile_pic) {
        avatarImg.src = API_ROOT + 'uploads/avatars/' + aktifKullanici.profile_pic;
        avatarImg.classList.remove('gizli');
        avatar.classList.add('gizli');
    } else {
        avatarImg.classList.add('gizli');
        avatar.classList.remove('gizli');
        if (aktifKullanici.display_name) avatar.textContent = aktifKullanici.display_name[0].toUpperCase();
    }
    seciliHobiler.clear();
    (aktifKullanici.interests || []).forEach(i => seciliHobiler.add(i.id));
    engellenenlerYukle();
}

function hobileriYukle() {
    apiFetch('get_interests.php', { method: 'POST', headers: { 'Content-Type': 'application/json' } })
    .then(data => {
        tumHobiler = data.categories || {};
        const labels = data.categoryLabels || {};
        const alan = document.getElementById('hobiSecimAlani');
        let html = '';

        Object.entries(tumHobiler).forEach(([cat, items]) => {
            html += `<div class="kategori-baslik">${labels[cat] || cat}</div><div class="etiketler">`;
            items.forEach(item => {
                const secili = seciliHobiler.has(item.id) ? ' secili' : '';
                html += `<span class="hobi-chip${secili}" data-id="${item.id}" onclick="hobiToggle(this,${item.id})">${item.icon || ''} ${item.name}</span>`;
            });
            html += '</div>';
        });
        alan.innerHTML = html;
    })
    .catch(() => {
        document.getElementById('hobiSecimAlani').innerHTML = '<p style="color:var(--kirmizi);">Hobiler yÃ¼klenemedi.</p>';
    });
}

function hobiToggle(el, id) {
    if (seciliHobiler.has(id)) { seciliHobiler.delete(id); el.classList.remove('secili'); }
    else { seciliHobiler.add(id); el.classList.add('secili'); }
}

document.getElementById('profilKaydetBtn')?.addEventListener('click', () => {
    if (!aktifKullanici) return;
    const displayName = document.getElementById('profilIsimInput').value.trim();
    const gender = document.getElementById('profilCinsiyet').value;

    apiFetch('update_profile.php', {
        method: 'POST', headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            userId: aktifKullanici.userId,
            display_name: displayName,
            gender,
            interest_ids: Array.from(seciliHobiler)
        })
    })
    .then(data => {
        aktifKullanici.display_name = data.user?.display_name || displayName;
        aktifKullanici.gender = data.user?.gender || gender;
        aktifKullanici.interests = data.interests || [];
        profilDoldur();
        document.getElementById('aktifKullaniciAdi').innerHTML =
            (aktifKullanici.display_name || aktifKullanici.email) + ' <i class="fa-solid fa-circle-check text-yesil"></i>';
        alert('Profil kaydedildi!');
    })
    .catch(e => {
        console.error('Profile save error:', e);
        alert('Profil kaydetme hatasÄ±.\n' + e.message);
    });
});

// --- 11. SEKMELER ---
function sekmeDegistir(hedefSayfaId, btn) {
    document.querySelectorAll('.uygulama-sayfasi').forEach(s => s.classList.add('gizli'));
    document.getElementById(hedefSayfaId).classList.remove('gizli');
    document.querySelectorAll('.nav-buton').forEach(b => b.classList.remove('aktif'));
    btn.classList.add('aktif');
    if (hedefSayfaId === 'sayfa-kesfet') setTimeout(() => { if (kampusHaritasi) kampusHaritasi.invalidateSize(); }, 100);
    if (hedefSayfaId === 'sayfa-istekler') eslesmelerYukle();
    if (hedefSayfaId === 'sayfa-profil') { profilDoldur(); hobileriYukle(); }
}

// --- 12. PROFİL RESMİ YÜKLEME ---
document.getElementById('profilResimInput')?.addEventListener('change', function() {
    const file = this.files[0];
    if (!file) return;
    if (file.size > 2 * 1024 * 1024) {
        alert('Dosya boyutu 2MB\'ı aşamaz.');
        this.value = '';
        return;
    }
    if (!['image/jpeg', 'image/png'].includes(file.type)) {
        alert('Sadece JPEG ve PNG dosyaları kabul edilir.');
        this.value = '';
        return;
    }
    const formData = new FormData();
    formData.append('userId', aktifKullanici.userId);
    formData.append('profile_pic', file);

    fetch(API_ROOT + 'upload_profile_pic.php', { method: 'POST', body: formData })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            aktifKullanici.profile_pic = data.profile_pic;
            profilDoldur();
            benimMarkerGuncelle();
            alert('Profil resmi güncellendi!');
        } else {
            alert('Hata: ' + (data.error || 'Bilinmeyen hata'));
        }
    })
    .catch(e => alert('Profil resmi yükleme hatası.\n' + e.message));
    this.value = '';
});

// --- 13. EŞLEŞME KABUL / RED ---
function eslesmeKabulEt(matchId) {
    apiFetch('accept_match.php', {
        method: 'POST', headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ matchId, userId: aktifKullanici.userId })
    })
    .then(() => { alert('Eşleşme kabul edildi! Artık mesajlaşabilirsiniz.'); eslesmelerYukle(); })
    .catch(e => alert('Kabul hatası: ' + e.message));
}

function eslesmeReddet(matchId) {
    if (!confirm('Bu eşleşme isteğini reddetmek istediğinize emin misiniz?')) return;
    apiFetch('reject_match.php', {
        method: 'POST', headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ matchId, userId: aktifKullanici.userId })
    })
    .then(() => { alert('Eşleşme reddedildi.'); eslesmelerYukle(); })
    .catch(e => alert('Red hatası: ' + e.message));
}

// --- 14. ENGELLEME SİSTEMİ ---
function kullaniciEngelle(blockedId, isim) {
    if (!confirm(`${isim || 'Bu kullanıcıyı'} engellemek istediğinize emin misiniz? Engellenen kişi sizi göremez ve size istek atamaz.`)) return;
    apiFetch('block_user.php', {
        method: 'POST', headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ userId: aktifKullanici.userId, blockedId })
    })
    .then(() => {
        alert('Kullanıcı engellendi.');
        eslesmelerYukle();
        yakinlariGetir();
    })
    .catch(e => alert('Engelleme hatası: ' + e.message));
}

function engelKaldir(blockedId) {
    if (!confirm('Engeli kaldırmak istediğinize emin misiniz?')) return;
    apiFetch('unblock_user.php', {
        method: 'POST', headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ userId: aktifKullanici.userId, blockedId })
    })
    .then(() => { alert('Engel kaldırıldı.'); engellenenlerYukle(); })
    .catch(e => alert('Engel kaldırma hatası: ' + e.message));
}

function engellenenlerYukle() {
    if (!aktifKullanici) return;
    const alan = document.getElementById('engellenenlerAlani');
    if (!alan) return;
    apiFetch('get_blocked_users.php', {
        method: 'POST', headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ userId: aktifKullanici.userId })
    })
    .then(data => {
        const list = data.blocked || [];
        if (list.length === 0) {
            alan.innerHTML = '<p style="color:var(--yazi-ikincil);text-align:center;padding:10px;">Engellenen kullanıcı yok.</p>';
        } else {
            alan.innerHTML = list.map(b => {
                const avatarHtml = b.profile_pic
                    ? `<img src="${API_ROOT}uploads/avatars/${b.profile_pic}" class="engel-avatar-img">`
                    : `<div class="engel-avatar-harf">${(b.display_name||'A')[0].toUpperCase()}</div>`;
                return `<div class="engel-satir">
                    ${avatarHtml}
                    <span class="engel-isim">${b.display_name || 'Anonim'}</span>
                    <button class="engel-kaldir-btn" onclick="engelKaldir(${b.blocked_id})"><i class="fa-solid fa-unlock"></i> Kaldır</button>
                </div>`;
            }).join('');
        }
    })
    .catch(() => { alan.innerHTML = '<p style="color:var(--kirmizi);">Yüklenemedi.</p>'; });
}
