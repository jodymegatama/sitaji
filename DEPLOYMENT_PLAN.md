# 📋 PLAN: Workflow Local → GitHub → VPS (aaPanel)
## SITAJI — Sistem Informasi Tunjangan & Administrasi Pegawai

---

## 🎯 Tujuan

Repositori GitHub `https://github.com/jodymegatama/sitaji` berisi **kode produksi** yang siap deploy ke VPS. Setiap perubahan dibuat di local, commit ke GitHub, lalu di-pull ke VPS via aaPanel.

---

## ✅ Yang Sudah Dilakukan (Status Saat Ini)

| Item | Status | Keterangan |
|------|--------|-----------|
| Hapus password VPS dari `db.php` | ✅ Selesai | `db.php` sekarang baca dari `.env` file |
| Hapus `sitaji.sql` dari repo | ✅ Selesai | Data pegawai sensitif, tidak boleh di public repo |
| Hapus `graphify-out/` dari repo | ✅ Selesai | Artifact lokal, bukan production code |
| Buat `.env.example` | ✅ Selesai | Template konfigurasi untuk local & VPS |
| Update `.gitignore` | ✅ Selesai | Ignore `.env`, `*.sql`, `.user.ini`, backup files |
| Update `README.md` | ✅ Selesai | Dokumentasi setup local + deploy VPS |
| Force-push ke GitHub | ✅ Selesai | History bersih dari password VPS |
| Test `db.php` + `.env` local | ✅ Selesai | PDO OK, 988 users, login admin OK |

---

## 📁 Yang Di-Commit ke GitHub (Production Code)

```
sitaji/
├── .env.example              ← Template .env (TANPA password nyata)
├── .gitignore                ← Daftar file yang di-ignore
├── .htaccess                 ← Clean URL routing (support localhost & vhost)
├── README.md                 ← Dokumentasi setup & deploy
├── index.php                 ← Entry point
├── login.php
├── logout.php
├── admin/                    ← 21 file PHP (dashboard, pegawai, payroll, dll)
├── pegawai/                  ← 6 file PHP (dashboard, slip gaji, dll)
├── stakeholder/              ← 9 file PHP (laporan dana, revisi, dll)
├── includes/                 ← 11 file PHP (db, auth, helpers, header/footer)
│   └── db.php                ← Baca kredensial dari .env (TANPA hardcoded password)
├── assets/                   ← CSS, JS, images
└── migrations/               ← 9 migration scripts + README
```

**Total: 74 file kode murni** — tidak ada data, tidak ada kredensial.

---

## 🚫 Yang TIDAK Di-Commit (Di-ignore)

| File | Alasan |
|------|--------|
| `.env` | Berisi password database — setiap environment berbeda |
| `*.sql` (termasuk `sitaji.sql`) | Berisi data asli pegawai (NIP, password hash) |
| `.user.ini` | VPS-specific config (open_basedir path) |
| `.user.ini.vps.bak` | Backup file VPS |
| `includes/db.php.vps.bak` | Backup db.php lama (hardcoded password) |
| `graphify-out/` | Artifact lokal dari graphify, bukan production |
| `*.log` | Log file lokal |
| `.vscode/`, `.idea/` | IDE config |

---

## 🔄 Workflow: Local → GitHub → VPS

### Step 1: Buat Perubahan di Local

```bash
cd C:/laragon/www/sitaji
# Edit file PHP/JS/CSS menggunakan editor favorit Anda
# Test di browser: http://localhost/sitaji/login
```

### Step 2: Commit & Push ke GitHub

```bash
cd C:/laragon/www/sitaji
git add -A
git commit -m "deskripsi perubahan (contoh: tambah fitur export slip gaji PDF)"
git push origin main
```

### Step 3: Deploy ke VPS via aaPanel

1. Login ke aaPanel: `https://<vps-ip>:8888`
2. Buka **Terminal** (atau SSH ke VPS)
3. Jalankan:
```bash
cd /www/wwwroot/sitaji.kemenagkabpasuruan.id
git pull origin main
```

4. **`.env` dan `.user.ini` tidak akan ter-overwrite** oleh `git pull` karena sudah di `.gitignore`.

---

## 🔧 Setup VPS Pertama Kali (aaPanel)

### 1. Buat Site di aaPanel
- **Website → Add Site**
- Domain: `sitaji.kemenagkabpasuruan.id`
- PHP Version: 8.1+
- Database: MySQL — create database `sitaji` + user `sitaji` + password

### 2. Clone Repo
```bash
cd /www/wwwroot/sitaji.kemenagkabpasuruan.id
git clone https://github.com/jodymegatama/sitaji.git .
```

### 3. Buat `.env` di VPS
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

### 4. Import Database
```bash
# Upload sitaji.sql ke VPS (via aaPanel File Manager atau SCP)
mysql -u sitaji -p sitaji < /path/to/sitaji.sql
```

### 5. Setup `.user.ini` via aaPanel
Di aaPanel → Website → sitaji → Config → `.user.ini`:
```ini
open_basedir=/www/wwwroot/sitaji.kemenagkabpasuruan.id/:/tmp/
```

### 6. Set Permissions
```bash
chown -R www:www /www/wwwroot/sitaji.kemenagkabpasuruan.id
chmod -R 755 /www/wwwroot/sitaji.kemenagkabpasuruan.id
chmod 600 .env
```

---

## 🔒 Keamanan

### ⚠️ Password VPS Pernah Terekspos di GitHub

Password database VPS (`c364DCY8aW3HTrRe`) sempat ter-commit di `db.php` versi lama.
**Sudah di-force-push (history di-rewrite), namun:**

**WAJIB GANTI PASSWORD DATABASE VPS:**
1. Login ke aaPanel → Database
2. Ganti password user `sitaji`
3. Update `.env` di VPS dengan password baru

---

## 📋 Checklist Sebelum Deploy

Setiap kali deploy ke VPS, pastikan:

- [ ] Perubahan sudah di-test di local (`http://localhost/sitaji/`)
- [ ] Login admin berfungsi (`admin`/`admin123456`)
- [ ] Login pegawai berfungsi (NIP + password)
- [ ] Tidak ada error PHP di log
- [ ] `git status` clean — semua perubahan sudah di-commit
- [ ] `git push origin main` sukses
- [ ] SSH ke VPS → `git pull origin main` sukses
- [ ] Test akses VPS: `https://sitaji.kemenagkabpasuruan.id/login`
- [ ] `.env` di VPS masih ada dan valid (tidak ter-overwrite)

---

## 🆘 Troubleshooting

| Masalah | Solusi |
|---------|--------|
| Local: blank page | Cek `.env` ada dan readable. `cat .env` |
| Local: PDO connection failed | Cek MySQL running di Laragon. Cek `DB_USER` dan `DB_PASS` di `.env` |
| VPS: 500 error | Cek `error_log` di aaPanel → Website → sitaji → Log |
| VPS: database connection failed | Cek `.env` di VPS punya credentials yang benar |
| VPS: clean URL 404 | Cek Apache `mod_rewrite` enabled, `.htaccess` AllowOverride All |
| `git pull` conflict | `.env` atau `.user.ini` di-overwrite? Cek `.gitignore` |
| Login gagal setelah deploy | Cek `users` table ada data, `password_hash` valid |
