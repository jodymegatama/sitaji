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

// Cek apakah pemangku kepentingan memiliki riwayat data periode
$stmt = $pdo->prepare("SELECT COUNT(*) FROM dana_periode WHERE pemangku_kepentingan_id = ?");
$stmt->execute([$id]);
$hasPeriode = (int)$stmt->fetchColumn() > 0;

if ($hasPeriode) {
    header('Location: pemangku_kepentingan?tab=daftar&error=has_periode');
    exit;
}

// Ambil user_id untuk hapus user juga
$stmt = $pdo->prepare("SELECT user_id FROM pemangku_kepentingan WHERE id = ?");
$stmt->execute([$id]);
$userId = $stmt->fetchColumn();

if (!$userId) {
    header('Location: pemangku_kepentingan?tab=daftar&error=delete_failed');
    exit;
}

try {
    $pdo->beginTransaction();

    // Mapping komponen otomatis terhapus via ON DELETE CASCADE di pemangku_kepentingan
    $pdo->prepare("DELETE FROM pemangku_kepentingan WHERE id = ?")->execute([$id]);
    $pdo->prepare("DELETE FROM users WHERE id = ?")->execute([$userId]);

    $pdo->commit();
    header('Location: pemangku_kepentingan?tab=daftar&success=deleted');
} catch (Exception $e) {
    $pdo->rollBack();
    header('Location: pemangku_kepentingan?tab=daftar&error=delete_failed');
}
exit;
