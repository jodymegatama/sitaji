<?php
/**
 * migrations/011_add_lampiran_to_pemanfaatan.php
 *
 * Menambahkan kolom lampiran untuk PDF pada tabel dana_pemanfaatan
 */

require_once __DIR__ . '/../includes/db.php';

try {
    $pdo->exec("ALTER TABLE dana_pemanfaatan ADD COLUMN lampiran VARCHAR(255) NULL AFTER nominal");
    echo "Migration 011: Column 'lampiran' added successfully to 'dana_pemanfaatan'.\n";
} catch (PDOException $e) {
    if ($e->errorInfo[1] == 1060) {
        echo "Migration 011: Column 'lampiran' already exists.\n";
    } else {
        echo "Migration 011 Error: " . $e->getMessage() . "\n";
    }
}
