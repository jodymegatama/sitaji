<?php
/**
 * 001_normalize_kategori.php
 * ALREADY EXECUTED — for record only, do not re-run
 *
 * Tujuan: Normalisasi nilai kategori legacy ('Organisasi / Wajib', 'Infaq', 'Koperasi')
 *         menjadi 2 grup baku ('Potongan Umum', 'Koperasi atau Bank & Pinjaman').
 *
 * Perintah yang dijalankan:
 *
 * BEGIN TRANSACTION;
 *
 * UPDATE komponen_payroll SET kategori='Koperasi atau Bank & Pinjaman'
 * WHERE field_key IN ('pot_bank','kop_wajib','kop_pensiun','kop_belanja','kop_thr','kop_pinjaman');
 * -- Affected: 6 rows
 *
 * UPDATE komponen_payroll SET kategori='Potongan Umum'
 * WHERE field_key NOT IN ('pot_bank','kop_wajib','kop_pensiun','kop_belanja','kop_thr','kop_pinjaman');
 * -- Affected: 14 rows
 *
 * -- Verifikasi:
 * SELECT COUNT(*) FROM komponen_payroll;                                    -- => 20
 * SELECT COUNT(*) FROM komponen_payroll WHERE kategori='Potongan Umum';     -- => 14
 * SELECT COUNT(*) FROM komponen_payroll WHERE kategori='Koperasi atau Bank & Pinjaman'; -- => 6
 *
 * COMMIT;
 *
 * Catatan: pinjaman_kenakalan (baris ke-20) ikut masuk Potongan Umum karena
 * field_key-nya tidak ada di IN clause koperasi. Ini adalah test data.
 * Setelah migrasi, kolom `urutan` dihapus dari kode (tapi kolom tetap ada di DB).
 */

echo "ALREADY EXECUTED — do not re-run.\n";
