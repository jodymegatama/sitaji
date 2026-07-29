<?php
/**
 * 008_add_is_revisi_flag.php
 *
 * Tujuan: Tambah kolom is_revisi sebagai flag eksplisit di dana_periode.
 * Kolom ini menandai apakah suatu broadcast adalah hasil dari approval revisi
 * (bukan broadcast baru pertama kali).
 *
 * Default 0 — di-set ke 1 secara eksplisit oleh admin/approve_revisi.php
 * saat approve revisi dan re-broadcast periode.
 *
 * DDL, MySQL auto-commit. Idempotent.
 */
require_once __DIR__ . '/../includes/db.php';

try {
    echo "Adding is_revisi column to dana_periode...\n";

    // Check if column already exists
    $cols = $pdo->query("SHOW COLUMNS FROM dana_periode LIKE 'is_revisi'")->fetch();
    if (!$cols) {
        $pdo->exec("ALTER TABLE dana_periode ADD COLUMN is_revisi TINYINT(1) NOT NULL DEFAULT 0 AFTER broadcast_at");
        echo "  Column is_revisi added.\n";
    } else {
        echo "  Column is_revisi already exists, skipping.\n";
    }

    echo "Done.\n";
} catch (PDOException $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    exit(1);
}
