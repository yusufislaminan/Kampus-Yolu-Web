// Basit istemci tarafı kontroller (Sunucu tarafı doğrulama MUTLAKA gerekli)
export function validatePassword(password) {
  if (typeof password !== 'string') return { ok: false, message: 'Şifre geçersiz.' };
  if (password.length < 8) return { ok: false, message: 'Şifre en az 8 karakter olmalı.' };
  if (!/[A-Z]/.test(password)) return { ok: false, message: 'Şifre en az 1 büyük harf içermeli.' };
  if (!/[a-z]/.test(password)) return { ok: false, message: 'Şifre en az 1 küçük harf içermeli.' };
  if (!/[0-9]/.test(password)) return { ok: false, message: 'Şifre en az 1 rakam içermeli.' };
  // Basit içerik kontrol: sadece boşluk/çok benzer karakterleri azalt
  if (/^([a-zA-Z0-9])\1+$/.test(password)) {
    return { ok: false, message: 'Şifre çok basit görünüyor. Lütfen daha güçlü bir şifre kullanın.' };
  }
  return { ok: true };
}

export function validateEmail(email) {
  if (typeof email !== 'string') return { ok: false, message: 'E-posta geçersiz.' };
  // atauni.edu.tr için kısıt (isterseniz kaldırın)
  const re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
  if (!re.test(email)) return { ok: false, message: 'E-posta formatı hatalı.' };
  return { ok: true };
}

