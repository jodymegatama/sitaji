<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';
require_role('admin');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: pegawai');
    exit;
}
csrf_check();

$nip = trim($_POST['nip']??''); $nama = trim($_POST['nama']??'');
$gol = trim($_POST['golongan']??''); $jab = trim($_POST['jabatan']??'');
if ($nip === '' || $nama === '') {
    header('Location: pegawai?error=incomplete');
    exit;
}

try {
    $pdo->beginTransaction();
    $pdo->prepare("INSERT INTO pegawai (nip, nama, golongan, jabatan) VALUES (?,?,?,?)")
        ->execute([$nip, $nama, $gol, $jab]);
    $pid = (int)$pdo->lastInsertId();
    $pdo->prepare("INSERT INTO users (username, password_hash, role, pegawai_id) VALUES (?,?,?,?)")
        ->execute([$nip, password_hash('kemenag123', PASSWORD_DEFAULT), 'pegawai', $pid]);
    $pdo->commit();
} catch (Exception $e) { $pdo->rollBack(); header('Location: pegawai?error=db'); exit; }

header('Location: pegawai');
