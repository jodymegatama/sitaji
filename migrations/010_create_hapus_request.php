<?php
/**
 * 010_create_hapus_request.php
 *
 * Tujuan: Buat tabel dana_hapus_request untuk fitur pengajuan penghapusan
 * periode dana oleh stakeholder, yang dikonfirmasi admin.
 *
 * Juga menambahkan enum value 'hapus_pending' pada kolom status di dana_periode.
 *
 * FK strategy:
 *   - periode_id → dana_periode.id ON DELETE SET NULL
 *     (agar record history tetap ada setelah periode dihapus)
 *   - diajukan_oleh → users.id ON DELETE CASCADE
 *   - disetujui_oleh → users.id ON DELETE SET NULL
 *
 * Snapshot columns (periode_label, nama_pemangku, total_pemasukan,
 * total_pemanfaatan) disalin saat pengajuan, agar history tetap terbaca
 * setelah periode dihapus dan periode_id menjadi NULL.
 *
 * DDL, MySQL auto-commit. Idempotent.
 */
require_once __DIR__ . '/../includes/db.php';

try {
    // 1. Buat tabel dana_hapus_request jika belum ada
    $exists = $pdo->query("SHOW TABLES LIKE 'dana_hapus_request'")->fetch();
    if (!$exists) {
        echo "Creating table dana_hapus_request...\n";
        $pdo->exec("
            CREATE TABLE `dana_hapus_request` (
              `id` int NOT NULL AUTO_INCREMENT,
              `periode_id` int DEFAULT NULL,
              `diajukan_oleh` int NOT NULL,
              `alasan` text NOT NULL,
              `status` enum('pending','disetujui','ditolak') NOT NULL DEFAULT 'pending',
              `disetujui_oleh` int DEFAULT NULL,
              `catatan_admin` text,
              `diproses_at` datetime DEFAULT NULL,
              `periode_label` varchar(30) NOT NULL,
              `nama_pemangku` varchar(150) NOT NULL,
              `total_pemasukan` decimal(15,2) NOT NULL DEFAULT 0.00,
              `total_pemanfaatan` decimal(15,2) NOT NULL DEFAULT 0.00,
              `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
              PRIMARY KEY (`id`),
              KEY `idx_hapus_periode` (`periode_id`),
              KEY `idx_hapus_status` (`status`),
              KEY `idx_hapus_diajukan` (`diajukan_oleh`),
              KEY `idx_hapus_disetujui` (`disetujui_oleh`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci
        ");
        echo "  Table dana_hapus_request created.\n";
    } else {
        echo "  Table dana_hapus_request already exists, skipping CREATE.\n";
    }

    // 2. Tambah foreign keys jika belum ada
    $fkExists = $pdo->query("
        SELECT 1 FROM information_schema.TABLE_CONSTRAINTS
        WHERE TABLE_SCHEMA = DATABASE()
        AND TABLE_NAME = 'dana_hapus_request'
        AND CONSTRAINT_NAME = 'dana_hapus_request_fk_periode'
    ")->fetch();

    if (!$fkExists) {
        echo "Adding foreign keys...\n";
        $pdo->exec("
            ALTER TABLE `dana_hapus_request`
              ADD CONSTRAINT `dana_hapus_request_fk_periode`
                FOREIGN KEY (`periode_id`) REFERENCES `dana_periode` (`id`) ON DELETE SET NULL,
              ADD CONSTRAINT `dana_hapus_request_fk_diajukan`
                FOREIGN KEY (`diajukan_oleh`) REFERENCES `users` (`id`) ON DELETE CASCADE,
              ADD CONSTRAINT `dana_hapus_request_fk_disetujui`
                FOREIGN KEY (`disetujui_oleh`) REFERENCES `users` (`id`) ON DELETE SET NULL
        ");
        echo "  Foreign keys added.\n";
    } else {
        echo "  Foreign keys already exist, skipping.\n";
    }

    // 3. Tambah enum value 'hapus_pending' ke dana_periode.status
    //    Cek apakah 'hapus_pending' sudah ada di enum
    $colInfo = $pdo->query("SHOW COLUMNS FROM dana_periode LIKE 'status'")->fetch();
    $hasHapusPending = $colInfo && strpos($colInfo['Type'], 'hapus_pending') !== false;

    if (!$hasHapusPending) {
        echo "Adding 'hapus_pending' to dana_periode.status enum...\n";
        $pdo->exec("
            ALTER TABLE `dana_periode`
            MODIFY COLUMN `status` enum('draft','broadcast','revisi_pending','hapus_pending')
            NOT NULL DEFAULT 'draft'
        ");
        echo "  Enum status updated.\n";
    } else {
        echo "  Enum 'hapus_pending' already exists, skipping.\n";
    }

    echo "Done.\n";
} catch (PDOException $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    exit(1);
}
