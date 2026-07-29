<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';
require_role('admin');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: pemangku_kepentingan?tab=daftar');
    exit;
}
csrf_check();

$nama = trim($_POST['nama'] ?? '');
$username = trim($_POST['username'] ?? '');
$password = $_POST['password'] ?? '';
$komponenIds = $_POST['komponen_ids'] ?? [];

if ($nama === '' || $username === '' || $password === '') {
    header('Location: pemangku_kepentingan?tab=daftar&error=incomplete');
    exit;
}
if (strlen($password) < 6) {
    header('Location: pemangku_kepentingan?tab=daftar&error=short_password');
    exit;
}
if (empty($komponenIds)) {
    header('Location: pemangku_kepentingan?tab=daftar&error=no_komponen');
    exit;
}

// Cek username duplikat
$cek = $pdo->prepare("SELECT COUNT(*) FROM users WHERE username = ?");
$cek->execute([$username]);
if ((int)$cek->fetchColumn() > 0) {
    header('Location: pemangku_kepentingan?tab=daftar&error=duplicate_username');
    exit;
}

try {
    $pdo->beginTransaction();

    // 1. Buat user dengan role stakeholder
    $pdo->prepare("INSERT INTO users (username, password_hash, role) VALUES (?, ?, 'stakeholder')")
        ->execute([$username, password_hash($password, PASSWORD_DEFAULT)]);
    $userId = (int)$pdo->lastInsertId();

    // 2. Buat pemangku_kepentingan
    $pdo->prepare("INSERT INTO pemangku_kepentingan (nama, user_id) VALUES (?, ?)")
        ->execute([$nama, $userId]);
    $pkId = (int)$pdo->lastInsertId();

    // 3. Insert mapping komponen
    $stmt = $pdo->prepare("INSERT INTO pemangku_kepentingan_komponen (pemangku_kepentingan_id, komponen_id) VALUES (?, ?)");
    foreach ($komponenIds as $kid) {
        $stmt->execute([$pkId, (int)$kid]);
    }

    $pdo->commit();
    header('Location: pemangku_kepentingan?tab=daftar&success=created');
} catch (Exception $e) {
    $pdo->rollBack();
    header('Location: pemangku_kepentingan?tab=daftar&error=db');
}
exit;
