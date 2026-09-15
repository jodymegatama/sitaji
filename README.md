# SITAJI — Sistem Informasi Tunjangan & Administrasi Pegawai

> **Repository ini untuk PRODUCTION.** Hanya berisi kode aplikasi yang siap deploy. Tidak ada data sensitif, credential, atau file environment-specific.

---

## 📋 Prasyarat

- PHP 8.1+ (tested on 8.2.27)
- MySQL 5.7+ atau 8.x
- Apache + mod_rewrite (untuk clean URL)
- aaPanel (untuk VPS) / Laragon (untuk local dev)

---

## 🚀 Setup Local (Laragon)

### 1. Clone repo
```bash
cd C:/laragon/www
git clone https://github.com/jodymegatama/sitaji.git
cd sitaji
```

### 2. Buat file `.env` (lihat `.env.example` sebagai template)
```bash
copy .env.example .env
```
Edit `.env`:
```
APP_ENV=local
DB_HOST=localhost
DB_NAME=sitaji
DB_USER=root
DB_PASS=
```

### 3. Import database
Import `sitaji.sql` ke MySQL local:
```bash
mysql -u root -e "CREATE DATABASE IF NOT EXISTS sitaji CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;"
mysql -u root sitaji < sitaji.sql
```
> ⚠️ File `sitaji.sql` **tidak ada di repo GitHub** (di-gitignore karena berisi data pegawai). Dapatkan file ini dari administrator sistem atau dari VPS.

### 4. Akses aplikasi
```
http://localhost/sitaji/login
```

### Default admin login (dari database VPS):
- Username: `admin`
- Password: `admin123456`

---

## 🚀 Deploy ke VPS via aaPanel

### 1. Di aaPanel: Buat site
- **Site → Add Site**
- Domain: `sitaji.kemenagkabpasuruan.id` (atau domain Anda)
- PHP Version: 8.1+
- Database: MySQL (create database `sitaji`)

### 2. Clone repo ke VPS
```bash
cd /www/wwwroot/sitaji.kemenagkabpasuruan.id
git clone https://github.com/jodymegatama/sitaji.git .
```

### 3. Buat file `.env` di root web
```bash
cat > /www/wwwroot/sitaji.kemenagkabpasuruan.id/.env << 'EOF'
APP_ENV=production
DB_HOST=localhost
DB_NAME=sitaji
DB_USER=sitaji
DB_PASS=<password-dari-aaPanel-database>
EOF
chmod 600 .env
```

### 4. Import database
```bash
mysql -u sitaji -p sitaji < /path/to/sitaji.sql
```

### 5. Setup `.user.ini` (VPS-specific, via aaPanel)
Di aaPanel → Site → sitaji → Config:
```ini
open_basedir=/www/wwwroot/sitaji.kemenagkabpasuruan.id/:/tmp/
```

### 6. Set permissions
```bash
chown -R www:www /www/wwwroot/sitaji.kemenagkabpasuruan.id
chmod -R 755 /www/wwwroot/sitaji.kemenagkabpasuruan.id
chmod 600 .env
```

---

## 🔄 Workflow: Local → GitHub → VPS

### A. Buat perubahan di Local
```bash
# Edit file di C:/laragon/www/sitaji
# Test di http://localhost/sitaji/
```

### B. Commit & Push ke GitHub
```bash
cd C:/laragon/www/sitaji
git add -A
git commit -m "deskripsi perubahan"
git push origin main
```

### C. Deploy ke VPS via aaPanel SSH Terminal
```bash
cd /www/wwwroot/sitaji.kemenagkabpasuruan.id
git pull origin main
```

> **Pastikan `.env` dan `.user.ini` tidak akan di-overwrite oleh git pull** — keduanya sudah di `.gitignore`.

---

## 📁 Struktur Direktori

```
sitaji/
├── .env.example          # Template konfigurasi (di-commit)
├── .env                  # Konfigurasi nyata (TIDAK di-commit, local-only)
├── .gitignore
├── .htaccess             # Clean URL routing
├── .user.ini             # VPS-only (TIDAK di-commit, dibuat via aaPanel)
├── index.php             # Entry point
├── login.php             # Halaman login
├── logout.php
├── README.md             # Dokumentasi ini
├── admin/                # Halaman admin (dashboard, pegawai, payroll, dll)
├── pegawai/              # Halaman pegawai (dashboard, slip gaji, dll)
├── stakeholder/          # Halaman stakeholder (laporan dana, revisi, dll)
├── includes/             # Shared components (db, auth, helpers, header/footer)
│   ├── db.php            # Koneksi database (baca dari .env)
│   ├── auth.php          # Autentikasi
│   └── helpers.php       # Helper functions
├── assets/               # CSS, JS, images
│   ├── css/style.css
│   ├── js/app.js
│   └── img/
└── migrations/           # Database migration scripts
    ├── README.md
    ├── 001_normalize_kategori.php
    └── ...
```

---

## ⚠️ Keamanan

- **`.env`** berisi password database — **TIDAK di-commit ke git**.
- **`.user.ini`** berisi VPS path config — **TIDAK di-commit ke git**.
- **`sitaji.sql`** berisi data asli pegawai (NIP, password hash) — **TIDAK di-commit ke git**.
- **`includes/db.php.vps.bak`** backup db.php lama (hardcoded password) — **TIDAK di-commit ke git**.

**Jika password VPS pernah ter-commit ke GitHub publik, segera:**
1. Ganti password database user `sitaji` di aaPanel
2. Update `.env` di VPS dengan password baru

---

## 🔧 Troubleshooting

| Masalah | Solusi |
|---------|--------|
| Blank page / 500 error | Cek `.env` ada dan readable. Cek `error_log` |
| Login gagal | Cek `users` table ada data, password_hash valid |
| Clean URL 404 | Cek `mod_rewrite` enabled, `.htaccess` AllowOverride All |
| Database connection failed | Cek `.env` credentials benar, MySQL running |

---

## 📝 Notes

- Repository ini hanya berisi **kode aplikasi** yang siap deploy.
- File konfigurasi environment (`.env`, `.user.ini`) dibuat manual di setiap server.
- Database (`sitaji.sql`) dikelola terpisah, tidak di-commit ke repo.
- Migrations (`migrations/`) di-commit dan bisa di-run untuk update schema.
