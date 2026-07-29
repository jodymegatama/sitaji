<?php
/**
 * 002_add_tipe_column_and_pendapatan_seed.php
 * ALREADY EXECUTED — for record only, do not re-run
 *
 * Tujuan: Tambah kolom tipe ENUM('pendapatan','potongan') untuk membedakan
 *         komponen pendapatan vs potongan, lalu seed 5 baris pendapatan dasar.
 *
 * Perintah yang dijalankan:
 *
 * BEGIN TRANSACTION;
 *
 * ALTER TABLE komponen_payroll
 *   ADD COLUMN tipe ENUM('pendapatan','potongan') NOT NULL DEFAULT 'potongan' AFTER nama;
 * -- Default 'potongan' membuat 20 baris existing otomatis ter-set tipe='potongan'
 *
 * INSERT INTO komponen_payroll (nama, field_key, kategori, tipe, urutan, aktif) VALUES
 * ('Gaji Pokok',    'gaji_pokok',     'Pendapatan', 'pendapatan', 1, 1),
 * ('Gaji 13',       'gaji_13',        'Pendapatan', 'pendapatan', 2, 1),
 * ('Tunj. Kinerja', 'tunj_kinerja',   'Pendapatan', 'pendapatan', 3, 1),
 * ('Tunj. Profesi', 'tunj_profesi',   'Pendapatan', 'pendapatan', 4, 1),
 * ('Uang Makan',    'uang_makan',     'Pendapatan', 'pendapatan', 5, 1);
 *
 * -- Verifikasi:
 * SELECT COUNT(*) FROM komponen_payroll WHERE tipe='pendapatan';  -- => 5
 * SELECT COUNT(*) FROM komponen_payroll WHERE tipe='potongan';    -- => 20
 * SELECT COUNT(*) FROM komponen_payroll;                          -- => 25
 * -- Duplicate check: SELECT field_key, COUNT(*) ... HAVING COUNT(*) > 1 => 0
 *
 * COMMIT;
 *
 * Catatan: ALTER TABLE menyebabkan implicit COMMIT di MySQL, sehingga transaksi
 * eksplisit tidak efektif untuk ALTER. INSERT berjalan di auto-commit setelahnya.
 * Verifikasi tetap dilakukan dan data terkonfirmasi benar.
 */

echo "ALREADY EXECUTED — do not re-run.\n";
