<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';
require_role('admin');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    die('Metode request tidak diizinkan.');
}
csrf_check();

$id = (int)($_POST['id'] ?? 0);
if ($id <= 0) {
    die('ID tidak valid.');
}

// Cek apakah pegawai memiliki record payroll
$stmt = $pdo->prepare("SELECT COUNT(*) FROM payroll WHERE pegawai_id = ?");
$stmt->execute([$id]);
$hasPayroll = (int)$stmt->fetchColumn() > 0;

if ($hasPayroll) {
    die('Pegawai tidak bisa dihapus karena memiliki riwayat payroll. Nonaktifkan akun jika diperlukan.');
}

// Hapus dalam satu transaksi
try {
    $pdo->beginTransaction();
    $pdo->prepare("DELETE FROM users WHERE pegawai_id = ?")->execute([$id]);
    $pdo->prepare("DELETE FROM pegawai WHERE id = ?")->execute([$id]);
    $pdo->commit();
} catch (Exception $e) {
    $pdo->rollBack();
    die('Gagal menghapus pegawai: ' . htmlspecialchars($e->getMessage()));
}

header('Location: pegawai');
