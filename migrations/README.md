# Database Migration Scripts — SITAJI

## Convention

Setiap skrip migrasi (skema atau data) disimpan di folder ini
dengan format penamaan:

    NNN_deskripsi_singkat.php

Skrip **TIDAK BOLEH DIHAPUS** setelah dijalankan — seluruh riwayat
harus dipertahankan sebagai audit trail permanent.

Skrip yang sudah dijalankan ditandai dengan komentar:

    // ALREADY EXECUTED — for record only, do not re-run

    Sesuai standar keamanan, migrasi berikutnya WAJIB menggunakan prepared statement PDO 
    untuk semua query yang melibatkan variabel (bahkan jika variabel berasal dari 
    query internal), guna mencegah risiko SQL Injection dan menjaga konsistensi kode.

Jika suatu skrip perlu di-revert, buat skrip baru dengan nomor
berikutnya yang melakukan operasi kebalikannya, lalu tandai skrip
lama di baris komentar pertamanya dengan:

    // REVERTED by NNN_nama_skrip_revert.php

## Daftar Migrasi

| #   | File                                    | Keterangan                                               |
|-----|-----------------------------------------|----------------------------------------------------------|
| 001 | 001_normalize_kategori.php              | Normalisasi nilai kategori legacy ke 2 grup baku         |
| 002 | 002_add_tipe_column_and_pendapatan_seed | Tambah kolom tipe ENUM, seed 5 baris pendapatan          |
| 003 | 003_fix_potbank_label.php               | Label pot_bank: 'Pot Bank' → 'Potongan Bank' [REVERTED]  |
| 004 | 004_revert_potbank_and_pinjaman.php     | Revert pot_bank + aktifkan pinjaman_kenakalan kembali     |
| 005 | 005_create_payroll_detail.php            | Buat tabel payroll_detail untuk komponen dinamis             |
| 006 | 006_full_eav_payroll.php                 | Migrasi Full EAV: hapus 24 kolom fix dari tabel payroll       |
| 007 | 007_stakeholder_module.php               | Buat modul stakeholder: role ENUM, tabel dana_periode, dana_pemanfaatan, dll. |
| 008 | 008_add_is_revisi_flag.php               | Tambah kolom is_revisi flag ke dana_periode                      |
| 009 | 009_add_saldo_awal.php                   | Tambah kolom saldo_awal ke dana_periode untuk running balance    |
