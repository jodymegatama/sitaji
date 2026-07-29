<?php
/**
 * 005_create_payroll_detail.php
 * EXECUTED on 2026-06-21
 *
 * Tujuan: Membuat tabel payroll_detail untuk menyimpan nilai komponen
 *         pendapatan/potongan yang field_key-nya TIDAK ADA sebagai kolom fix
 *         di tabel payroll (misal: komponen baru, pinjaman_kenakalan).
 *
 * Tabel payroll tetap mempertahankan 989 baris data riwayat di kolom fix.
 * payroll_detail hanya untuk komponen baru/tambahan.
 *
 * Perintah yang dijalankan:
 *
 * CREATE TABLE payroll_detail (
 *   id INT AUTO_INCREMENT PRIMARY KEY,
 *   payroll_id INT NOT NULL,
 *   komponen_id INT NOT NULL,
 *   nilai DECIMAL(15,2) DEFAULT 0.00,
 *   UNIQUE KEY uniq_payroll_komponen (payroll_id, komponen_id),
 *   CONSTRAINT fk_detail_payroll FOREIGN KEY (payroll_id) REFERENCES payroll(id) ON DELETE CASCADE,
 *   CONSTRAINT fk_detail_komponen FOREIGN KEY (komponen_id) REFERENCES komponen_payroll(id) ON DELETE RESTRICT
 * ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
 */
require_once __DIR__ . '/../includes/auth.php';
require_role('admin');
require_once __DIR__ . '/../includes/db.php';

try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS payroll_detail (
        id INT AUTO_INCREMENT PRIMARY KEY,
        payroll_id INT NOT NULL,
        komponen_id INT NOT NULL,
        nilai DECIMAL(15,2) DEFAULT 0.00,
        UNIQUE KEY uniq_payroll_komponen (payroll_id, komponen_id),
        CONSTRAINT fk_detail_payroll FOREIGN KEY (payroll_id) REFERENCES payroll(id) ON DELETE CASCADE,
        CONSTRAINT fk_detail_komponen FOREIGN KEY (komponen_id) REFERENCES komponen_payroll(id) ON DELETE RESTRICT
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    echo "OK: payroll_detail created or already exists.\n";

    // Verifikasi
    $r = $pdo->query("SHOW CREATE TABLE payroll_detail")->fetch();
    echo $r['Create Table'] . "\n";
} catch (Exception $e) {
    echo "FAIL: " . $e->getMessage() . "\n";
    exit(1);
}
