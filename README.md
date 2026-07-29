# SITAJI (Sistem Transparansi Gaji) — Sistem Manajemen Payroll (PHP + MySQL + Bootstrap 5)

Replikasi struktur halaman `kemenag-sitaji` (Admin &amp; Pegawai).

## Cara Pakai (XAMPP / Laragon)

1. Ekstrak folder `sitaji/` ke dalam `htdocs/` (XAMPP) atau `www/` (Laragon).
2. Buat database baru bernama **`sitaji`** di phpMyAdmin.
3. Import file **`sitaji.sql`** ke database tersebut.
4. Edit kredensial DB di **`includes/db.php`** jika perlu (default: host `localhost`, user `root`, password kosong).
5. Buka di browser: `http://localhost/sitaji/login.php`

## Akun Default

| Role    | Username              | Password     |
|---------|-----------------------|--------------|
| Admin   | `admin`               | `admin123456`   |
| Pegawai | `199401312025051004`  | `kemenag123` |

Pegawai baru yang ditambahkan admin otomatis mendapat password default **`kemenag123`**.

## Struktur File

```
sitaji/
├── sitaji.sql                   # Skema MySQL + seed data
├── login.php                     # Halaman login (Admin & Pegawai)
├── logout.php
├── index.php                     # Redirect ke login / dashboard
├── migrations/                   # Riwayat migrasi skema & data (001-009)
│   ├── README.md                 # Konvensi & daftar migrasi
│   ├── 001_normalize_kategori.php
│   ├── 002_add_tipe_column_and_pendapatan_seed.php
│   ├── 003_fix_potbank_label.php
│   ├── 004_revert_potbank_and_pinjaman.php
│   ├── 005_create_payroll_detail.php
│   ├── 006_full_eav_payroll.php
│   ├── 007_stakeholder_module.php
│   ├── 008_add_is_revisi_flag.php
│   └── 009_add_saldo_awal.php
├── includes/
│   ├── db.php                    # Koneksi PDO
│   ├── auth.php                  # Session & guard role
│   ├── helpers.php               # Format rupiah, periode, payroll_fields(), total_bruto/potongan/thp
│   ├── header_admin.php
│   ├── header_pegawai.php
│   └── footer.php
├── admin/
│   ├── dashboard.php             # Data Payroll + statistik (termasuk LEFT JOIN payroll_detail)
│   ├── tambah_gaji.php           # Input payroll (fixed + komponen dinamis via payroll_detail)
│   ├── edit_gaji.php             # Edit payroll (pre-fill fixed + detail)
│   ├── detail_gaji.php           # AJAX: audit payroll detail (fixed + dinamis)
│   ├── hapus_gaji.php
│   ├── export_gaji.php           # Export Excel (termasuk kolom dinamis)
│   ├── import_gaji.php           # Import Excel (fixed kolom → payroll, kolom lain → payroll_detail)
│   ├── manajemen_komponen_payroll.php    # CRUD komponen pendapatan/potongan + DELETE kondisional
│   ├── pegawai.php               # Manajemen pegawai (CRUD)
│   ├── tambah_pegawai.php
│   ├── edit_pegawai.php
│   └── hapus_pegawai.php
├── pegawai/
│   ├── dashboard.php             # Riwayat slip + statistik (dengan LEFT JOIN payroll_detail)
│   ├── detail_slip.php           # AJAX modal slip gaji (fixed + dinamis)
│   ├── tanda_terima.php          # Konfirmasi terima slip
│   └── ubah_password.php
├── stakeholder/
│   ├── dashboard.php             # Dashboard (kartu ringkasan + grafik)
│   ├── index.php                 # Redirect ke dashboard
│   ├── tambah_periode.php        # Input periode baru (dengan saldo_awal)
│   ├── daftar_periode.php        # Daftar semua periode
│   ├── detail_periode.php        # Detail + tombol broadcast/revisi
│   ├── edit_periode.php          # Edit periode (hanya draft)
│   ├── broadcast_periode.php     # POST: ubah status jadi broadcast
│   ├── ajukan_revisi.php         # POST: ajukan revisi ke admin
│   └── laporan.php               # Endpoint export Excel (dipanggil via modal di daftar_periode)
└── assets/
    ├── css/style.css
    └── js/app.js
```

## Arsitektur Komponen Payroll Dinamis

SITAJI menggunakan pendekatan hybrid untuk menyimpan komponen pendapatan/potongan:

| Lapisan | Tabel / Fungsi | Peran |
|---------|---------------|-------|
| Metadata | `komponen_payroll` | Menyimpan definisi komponen: `field_key`, `nama`, `tipe` (`pendapatan`/`potongan`), `kategori`, `aktif`. |
| Fixed columns | `payroll` (kolom: `gaji_pokok`, `pot_tukin`, dll.) | Menyimpan nilai komponen built-in lama — **dipertahankan untuk backward compatibility** dengan 989 baris data riwayat. |
| EAV dinamis | `payroll_detail` (`payroll_id`, `komponen_id`, `nilai`) | Menyimpan nilai komponen baru/tambahan yang ditambahkan admin lewat UI — **tidak ada ALTER TABLE otomatis**. |
| Query | `helpers.php::payroll_fields()` | Membaca `komponen_payroll WHERE aktif=1` → menghasilkan struktur `['pendapatan'=>[...], 'potongan_umum'=>[...], 'koperasi'=>[...]]` untuk dipakai di seluruh form, slip, dan dashboard. |
| Total | `helpers.php::total_bruto()`, `total_potongan()`, `take_home()` | Menjumlahkan kolom fix + `_detail_sum_pendapatan` / `_detail_sum_potongan` (dari LEFT JOIN payroll_detail) tanpa double-count. |

Komponen pendapatan/potongan dapat ditambah atau dihapus dari `admin/manajemen_komponen_payroll.php`.
Proses hapus bersifat **kondisional**:
- **Fisik (DELETE)** — jika komponen **belum pernah dipakai** di kolom fix payroll maupun di `payroll_detail`.
- **Soft-delete (`aktif=0`)** — jika komponen **sudah terpakai** di data riwayat (kolom fix bernilai != 0 atau ada baris di `payroll_detail`), untuk menjaga integritas data riwayat tanpa kehilangan referensi.

Setiap kali admin menambah/mengedit payroll via `tambah_gaji.php` / `edit_gaji.php`:
- Kolom fixed (`gaji_pokok`..`kop_pinjaman`) disimpan langsung ke tabel `payroll`.
- Kolom non‑fixed (termasuk `pinjaman_kenakalan`, Tunjangan baru, dll.) disimpan ke `payroll_detail` dengan `ON DUPLICATE KEY UPDATE`.
- Baris `payroll_detail` dengan nilai 0 otomatis dihapus setelah submit untuk menjaga kebersihan data.

## Riwayat Migrasi

Seluruh perubahan skema dan data tercatat di folder `migrations/` sebagai audit trail permanen.
File migrasi **tidak boleh dihapus**; skrip yang sudah dijalankan ditandai `ALREADY EXECUTED — for record only, do not re-run`.
Revert dilakukan dengan membuat skrip baru (bukan mengedit skrip lama), ditandai `REVERTED by NNN_nama_skrip.php`.

| # | File | Deskripsi |
|---|------|-----------|
| 001 | `001_normalize_kategori.php` | Normalisasi nilai kategori legacy ke 2 grup baku (Potongan Umum, Koperasi atau Bank & Pinjaman) |
| 002 | `002_add_tipe_column_and_pendapatan_seed.php` | Tambah kolom `tipe` ENUM(`pendapatan`,`potongan`), seed 5 pendapatan dasar |
| 003 | `003_fix_potbank_label.php` | Uji coba rename label pot_bank — **direvert** oleh 004 |
| 004 | `004_revert_potbank_and_pinjaman.php` | Revert pot_bank + aktifkan pinjaman_kenakalan kembali |
| 005 | `005_create_payroll_detail.php` | Buat tabel `payroll_detail` untuk komponen dinamis (FK ke payroll CASCADE, FK ke komponen RESTRICT, UNIQUE KEY payroll+komponen) |
| 006 | `006_full_eav_payroll.php` | Migrasi Full EAV: hapus 24 kolom fix dari tabel payroll, pindahkan ke payroll_detail |
| 007 | `007_stakeholder_module.php` | Buat modul stakeholder: tambah role ENUM, tabel pemangku_kepentingan, dana_periode, dana_pemanfaatan, dll. |
| 008 | `008_add_is_revisi_flag.php` | Tambah kolom `is_revisi` ke dana_periode sebagai flag broadcast hasil revisi |
| 009 | `009_add_saldo_awal.php` | Tambah kolom `saldo_awal` ke dana_periode untuk running balance antar periode |

## Catatan Teknis

- **PHP**: minimal 7.4 (PDO + mysqli). Disarankan 8.0+.
- **Frontend**: Bootstrap 5.3 + Bootstrap Icons + DataTables (CDN, tidak perlu install).
- **Keamanan**: password di-hash (`password_hash`), prepared statement (PDO), CSRF check sederhana lewat session, role guard di setiap halaman.
- **Validasi form**: JavaScript (`assets/js/app.js`) + validasi server-side.
