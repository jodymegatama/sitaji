<?php
/**
 * 003_fix_potbank_label.php
 * REVERTED by 004_revert_potbank_and_pinjaman.php
 *
 * Tujuan: Mengubah label komponen pot_bank dari 'Pot Bank' menjadi 'Potongan Bank'
 *         agar sesuai dengan nilai hardcode di helpers.php::payroll_fields().
 *
 * KEPUTUSAN KELIRU: Task refactor payroll_fields() dari hardcode ke DB-driven
 * seharusnya membuat kode mengikuti data, bukan mengubah data mengikuti kode.
 * Skrip ini di-revert oleh 004.
 *
 * Perintah yang sempat dijalankan:
 *
 * UPDATE komponen_payroll SET nama='Potongan Bank' WHERE field_key='pot_bank' AND nama='Pot Bank';
 * -- Affected: 1 row
 *
 * -- Verifikasi: SELECT nama FROM komponen_payroll WHERE field_key='pot_bank'; => 'Potongan Bank'
 */

echo "REVERTED — do not re-run.\n";
