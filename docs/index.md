# Dokumentasi Laravel Authentication

Pusat dokumentasi resmi **`mixudev/laravel-authentication`**
(`Vendor\LaravelAuthentication\`) — arsitektur autentikasi enterprise untuk
Laravel 10.x - 13.x: modular, portable, fail-closed, dan siap produksi.

---

## 📁 Struktur Dokumentasi

```
docs/
├── index.md                        # Portal ini
├── getting-started/                # Instalasi & persiapan
│   ├── installation.md             # Setup lengkap: composer → publish → migrate → Tailwind → verifikasi
│   ├── prerequisites.md            # API keys (CAPTCHA, OAuth, SMTP) & checklist produksi
│   └── modular-installation.md     # Mode single-folder (modules/Authentication)
├── features/                       # Fitur & konfigurasi
│   ├── overview.md                 # 15 fitur + saklar config
│   └── passkey.md                  # FIDO2 / WebAuthn detail kriptografi
├── development/                    # Ekstensi & kustomisasi untuk developer
│   ├── custom-controller-guide.md  # Controller/view custom (login, 2FA, API, roles)
│   ├── custom-strategies.md        # Strategi autentikasi kustom
│   ├── events-and-listeners.md     # 15 domain events + cara listen
│   └── views-customization.md      # Kustomisasi tampilan (ID/EN)
├── api/                            # REST API
│   └── api-reference.md            # Katalog endpoint + sitemap 49 rute
├── security/                       # Keamanan
│   ├── architecture.md             # Threat model & mitigasi
│   ├── hardening-notes.md          # Changelog remediasi SA-01..SA-13
│   └── vulnerability-reporting.md  # Kebijakan lapor kerentanan
└── operations/                     # Operasional
    └── publishing-guide.md         # Rilis, SemVer, Packagist
```

---

## 🚀 Panduan Memulai

1. **[Instalasi & Setup Lengkap](getting-started/installation.md)**
   `composer require` → publish config → migrate → Tailwind → verifikasi.

2. **[Prerequisites & API Keys](getting-started/prerequisites.md)**
   Daftar layanan pihak ketiga (CAPTCHA, OAuth, SMTP) & cara mendapatkannya.

3. **[Mode Modul Mandiri](getting-started/modular-installation.md)**
   Semua file package dalam satu folder `modules/Authentication/`.

---

## ⚙️ Fitur

4. **[Fitur & Konfigurasi](features/overview.md)**
   2FA TOTP, session manager, rate limiting granular, CAPTCHA adaptif,
   OTP passwordless, registrasi, reset password, social login, passkey,
   kebijakan password, optimasi skala 10M+.

5. **[Passkey WebAuthn FIDO2](features/passkey.md)**
   Verifikasi kriptografi ES256/RS256/EdDSA, anti-replay, cloned-authenticator
   detection.

---

## 🛠️ Pengembangan

6. **[Custom Controller Guide](development/custom-controller-guide.md)**
   Bangun controller/view sendiri di atas service package.

7. **[Custom Authentication Strategies](development/custom-strategies.md)**
   Tambah strategi baru (NIP, HP, RFID, SSO) tanpa ubah source.

8. **[Events & Listeners](development/events-and-listeners.md)**
   Katalog 15 event (login, 2FA, OTP, passkey, session, password),
   cara daftarkan listener, payload redaction.

9. **[Views Customization](development/views-customization.md)**
   Template split/card, dark mode, publish & edit Blade, BYO UI.

---

## 🔌 API Reference

10. **[REST API Reference](api/api-reference.md)**
    Endpoint `/api/v1/auth/*` lengkap + sitemap 49 rute Web & API.

---

## 🔐 Keamanan

11. **[Security Architecture](security/architecture.md)**
    Threat matrix & mitigasi: brute force, enumeration, session hijacking,
    fixation, timing attack, credential stuffing.

12. **[Hardening Notes](security/hardening-notes.md)**
    Log remediasi audit SA-01..SA-13.

13. **[Vulnerability Reporting](security/vulnerability-reporting.md)**
    Cara melaporkan kerentanan.

---

## 📦 Operasional

14. **[Publishing & Release](operations/publishing-guide.md)**
    Conventional commits, SemVer tagging, sinkronisasi Packagist.