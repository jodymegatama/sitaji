<?php
/**
 * 007_stakeholder_module.php
 *
 * Tujuan: Modul Pemangku Kepentingan (Stakeholder)
 * 1. Tambah value 'stakeholder' ke enum role di tabel users
 * 2. Buat 6 tabel baru untuk modul stakeholder
 *
 * Catatan: Semua statement bersifat DDL (ALTER TABLE, CREATE TABLE).
 * MySQL/InnoDB auto-commit DDL — tidak perlu transaksi manual.
 * Script idempotent (CREATE TABLE IF NOT EXISTS), aman dijalankan ulang.
 */
require_once __DIR__ . '/../includes/db.php';

try {
    echo "Step 1: Alter users.role enum to add 'stakeholder'...\n";
    $pdo->exec("ALTER TABLE users MODIFY COLUMN role ENUM('admin','pegawai','stakeholder') NOT NULL");
    echo "  OK\n";

    echo "Step 2: Create pemangku_kepentingan table...\n";
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS pemangku_kepentingan (
            id INT AUTO_INCREMENT PRIMARY KEY,
            nama VARCHAR(150) NOT NULL,
            user_id INT NOT NULL,
            aktif TINYINT(1) NOT NULL DEFAULT 1,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uniq_user (user_id),
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    echo "  OK\n";

    echo "Step 3: Create pemangku_kepentingan_komponen table...\n";
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS pemangku_kepentingan_komponen (
            id INT AUTO_INCREMENT PRIMARY KEY,
            pemangku_kepentingan_id INT NOT NULL,
            komponen_id INT NOT NULL,
            FOREIGN KEY (pemangku_kepentingan_id) REFERENCES pemangku_kepentingan(id) ON DELETE CASCADE,
            FOREIGN KEY (komponen_id) REFERENCES komponen_payroll(id) ON DELETE CASCADE,
            UNIQUE KEY uniq_pk_komponen (pemangku_kepentingan_id, komponen_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    echo "  OK\n";

    echo "Step 4: Create dana_periode table...\n";
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS dana_periode (
            id INT AUTO_INCREMENT PRIMARY KEY,
            pemangku_kepentingan_id INT NOT NULL,
            bulan TINYINT NOT NULL,
            tahun SMALLINT NOT NULL,
            total_pemasukan DECIMAL(15,2) NOT NULL DEFAULT 0,
            status ENUM('draft','broadcast','revisi_pending') NOT NULL DEFAULT 'draft',
            broadcast_at DATETIME NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (pemangku_kepentingan_id) REFERENCES pemangku_kepentingan(id),
            UNIQUE KEY uniq_periode (pemangku_kepentingan_id, bulan, tahun)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    echo "  OK\n";

    echo "Step 5: Create dana_pemanfaatan table...\n";
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS dana_pemanfaatan (
            id INT AUTO_INCREMENT PRIMARY KEY,
            periode_id INT NOT NULL,
            keterangan VARCHAR(255) NOT NULL,
            nominal DECIMAL(15,2) NOT NULL,
            FOREIGN KEY (periode_id) REFERENCES dana_periode(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    echo "  OK\n";

    echo "Step 6: Create dana_notifikasi_dibaca table...\n";
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS dana_notifikasi_dibaca (
            id INT AUTO_INCREMENT PRIMARY KEY,
            periode_id INT NOT NULL,
            user_id INT NOT NULL,
            dibaca_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (periode_id) REFERENCES dana_periode(id) ON DELETE CASCADE,
            UNIQUE KEY uniq_read (periode_id, user_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    echo "  OK\n";

    echo "Step 7: Create dana_revisi_request table...\n";
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS dana_revisi_request (
            id INT AUTO_INCREMENT PRIMARY KEY,
            periode_id INT NOT NULL,
            diajukan_oleh INT NOT NULL,
            data_baru JSON NOT NULL,
            alasan TEXT NULL,
            status ENUM('pending','disetujui','ditolak') NOT NULL DEFAULT 'pending',
            disetujui_oleh INT NULL,
            catatan_admin TEXT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            diproses_at DATETIME NULL,
            FOREIGN KEY (periode_id) REFERENCES dana_periode(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    echo "  OK\n";

    echo "\nMigration 007 completed successfully.\n";

} catch (Exception $e) {
    echo "FAIL: " . $e->getMessage() . "\n";
    exit(1);
}
