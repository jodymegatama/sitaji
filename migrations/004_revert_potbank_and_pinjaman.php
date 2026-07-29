<?php
/**
 * 004_revert_potbank_and_pinjaman.php
 * ALREADY EXECUTED — for record only, do not re-run
 *
 * Tujuan: Revert 2 perubahan data dari 003_fix_potbank_label.php dan perubahan
 *         aktif pinjaman_kenakalan yang dilakukan bersamaan (di luar skrip terpisah):
 *
 * 1. Kembalikan label pot_bank ke 'Pot Bank' (nilai original dari seed data)
 * 2. Kembalikan aktif pinjaman_kenakalan ke 1 (nilai original dari seed data)
 *
 * Perintah yang dijalankan:
 *
 * UPDATE komponen_payroll SET nama='Pot Bank' WHERE field_key='pot_bank';
 * -- Affected: 1 row
 *
 * UPDATE komponen_payroll SET aktif=1 WHERE field_key='pinjaman_kenakalan';
 * -- Affected: 1 row
 *
 * -- Verifikasi:
 * SELECT nama FROM komponen_payroll WHERE field_key='pot_bank';                -- => 'Pot Bank'
 * SELECT aktif FROM komponen_payroll WHERE field_key='pinjaman_kenakalan';      -- => 1
 *
 * Alasan revert: DB-driven refactor harus membuat kode mengikuti data di database,
 * bukan mengubah data supaya cocok dengan kode hardcode lama.
 * pinjaman_kenakalan aktif=1 adalah kondisi nyata database — kode harus
 * menyesuaikan, bukan data yang diubah.
 */

echo "ALREADY EXECUTED — do not re-run.\n";
