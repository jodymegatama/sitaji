<?php
/**
 * 009_add_saldo_awal.php
 *
 * Tujuan: Tambah kolom saldo_awal di dana_periode untuk mendukung
 * perhitungan saldo berjalan (running balance) antar periode.
 *
 * saldo_awal akan diisi secara otomatis dari sisa saldo periode
 * broadcast terakhir milik stakeholder yang sama saat membuat
 * periode baru (di stakeholder/tambah_periode.php).
 *
 * Data lama: backfill saldo_awal = 0.
 *
 * DDL, MySQL auto-commit. Idempotent.
 */
require_once __DIR__ . '/../includes/db.php';

try {
    echo "Adding saldo_awal column to dana_periode...\n";

    // Check if column already exists
    $cols = $pdo->query("SHOW COLUMNS FROM dana_periode LIKE 'saldo_awal'")->fetch();
    if (!$cols) {
        $pdo->exec("ALTER TABLE dana_periode ADD COLUMN saldo_awal DECIMAL(15,2) NOT NULL DEFAULT 0 AFTER total_pemasukan");
        echo "  Column saldo_awal added.\n";
    } else {
        echo "  Column saldo_awal already exists, skipping.\n";
    }

    // Backfill existing rows with 0
    $stmt = $pdo->exec("UPDATE dana_periode SET saldo_awal = 0 WHERE saldo_awal IS NULL OR saldo_awal = 0");
    echo "  Backfill completed.\n";

    echo "Done.\n";
} catch (PDOException $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    exit(1);
}
