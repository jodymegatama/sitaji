<?php
/**
 * 006_full_eav_payroll.php
 * EXECUTED on 2026-06-21
 *
 * Tujuan: Migrasi penuh ke arsitektur EAV.
 * 1. Pindahkan semua nilai dari 24 kolom fix di tabel `payroll` ke `payroll_detail`.
 * 2. Hapus 24 kolom fix tersebut dari tabel `payroll`.
 *
 * Catatan Transaksi:
 * Perlu diperhatikan bahwa ALTER TABLE di MySQL/MariaDB memicu implicit commit.
 * Artinya, transaksi yang membungkus INSERT + ALTER TABLE tidak sepenuhnya atomic.
 * Risiko ini dimitigasi oleh sifat idempotent dari ON DUPLICATE KEY UPDATE pada 
 * Step 1, sehingga re-run migrasi aman dilakukan tanpa duplikasi data.
 *
 * Perintah yang dijalankan:
 * - INSERT INTO payroll_detail ... SELECT FROM payroll ... ON DUPLICATE KEY UPDATE
 * - ALTER TABLE payroll DROP COLUMN ...
 */
require_once __DIR__ . '/../includes/auth.php';
require_role('admin');
require_once __DIR__ . '/../includes/db.php';

$fixedCols = [
    'gaji_pokok','gaji_13','tunj_kinerja','tunj_profesi','uang_makan',
    'pot_tukin_13','pot_tukin','pot_pokjawas','pot_arisan','pot_mpa',
    'pot_dharma_wanita','pot_korpri','pot_zakat','pot_pmi','pot_zakat_profesi',
    'infaq_baznas','infaq_umat','infaq_idharoh','pot_bank',
    'kop_wajib','kop_pensiun','kop_belanja','kop_thr','kop_pinjaman',
];

try {
    $pdo->beginTransaction();

    echo "Step 1: Migrating fixed columns to payroll_detail...\n";
    
    // Ambil mapping field_key -> id dari komponen_payroll
    $kMap = $pdo->query("SELECT field_key, id FROM komponen_payroll")->fetchAll(PDO::FETCH_KEY_PAIR);
    
    // Ambil semua data payroll
    $payrolls = $pdo->query("SELECT id FROM payroll")->fetchAll(PDO::FETCH_COLUMN);
    
    $stmtIns = $pdo->prepare("INSERT INTO payroll_detail (payroll_id, komponen_id, nilai) 
                              VALUES (?, ?, ?) 
                              ON DUPLICATE KEY UPDATE nilai=VALUES(nilai)");

    $count = 0;
    foreach ($payrolls as $pid) {
        foreach ($fixedCols as $col) {
            if (!isset($kMap[$col])) {
                echo "Warning: field_key '$col' not found in komponen_payroll. Skipping.\n";
                continue;
            }
            
            // Ambil nilai kolom fix untuk payroll_id ini
            $val = $pdo->query("SELECT `$col` FROM payroll WHERE id = $pid")->fetchColumn();
            
            // Migrasikan semua nilai (termasuk 0) untuk audit trail
            $stmtIns->execute([$pid, $kMap[$col], $val]);
            $count++;
        }
    }
    echo "Migrated $count entries to payroll_detail.\n";

    echo "Step 2: Dropping fixed columns from payroll table...\n";
    $dropCols = array_map(fn($c) => "DROP COLUMN `$c`", $fixedCols);
    $sqlDrop = "ALTER TABLE payroll " . implode(', ', $dropCols);
    $pdo->exec($sqlDrop);
    echo "Fixed columns dropped successfully.\n";

    $pdo->commit();
    echo "Migration 006 completed successfully.\n";

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo "FAIL: " . $e->getMessage() . "\n";
    exit(1);
}
