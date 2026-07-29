<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/helpers.php';
require_role('admin');

$id = (int)($_GET['id'] ?? 0);

if ($id <= 0) {
    $_SESSION['flash_message'] = 'ID pegawai tidak valid.';
    $_SESSION['flash_type'] = 'danger';
    header('Location: pegawai');
    exit;
}

$st = $pdo->prepare("SELECT p.id, p.nama, u.id AS user_id FROM pegawai p LEFT JOIN users u ON u.pegawai_id = p.id WHERE p.id = ?");
$st->execute([$id]);
$data = $st->fetch();

if (!$data) {
    $_SESSION['flash_message'] = 'Pegawai tidak ditemukan.';
    $_SESSION['flash_type'] = 'danger';
    header('Location: edit_pegawai?id=' . $id);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();

    $newPass = $_POST['password_baru'] ?? '';
    $confirm = $_POST['konfirmasi_password'] ?? '';

    if ($newPass !== $confirm) {
        $_SESSION['flash_message'] = 'Password baru dan konfirmasi tidak cocok.';
        $_SESSION['flash_type'] = 'danger';
        header('Location: edit_pegawai?id=' . $id);
        exit;
    }

    if (strlen($newPass) < 8) {
        $_SESSION['flash_message'] = 'Password minimal 8 karakter.';
        $_SESSION['flash_type'] = 'danger';
        header('Location: edit_pegawai?id=' . $id);
        exit;
    }

    if (!preg_match('/[A-Za-z]/', $newPass) || !preg_match('/[0-9]/', $newPass)) {
        $_SESSION['flash_message'] = 'Password harus mengandung minimal 1 huruf dan 1 angka.';
        $_SESSION['flash_type'] = 'danger';
        header('Location: edit_pegawai?id=' . $id);
        exit;
    }

    if (!$data['user_id']) {
        $_SESSION['flash_message'] = 'Pegawai ini belum memiliki akun login.';
        $_SESSION['flash_type'] = 'danger';
        header('Location: edit_pegawai?id=' . $id);
        exit;
    }

    $pdo->prepare("UPDATE users SET password_hash = ? WHERE id = ?")
        ->execute([password_hash($newPass, PASSWORD_DEFAULT), $data['user_id']]);

    $_SESSION['flash_message'] = 'Password berhasil direset.';
    $_SESSION['flash_type'] = 'success';
    header('Location: edit_pegawai?id=' . $id);
    exit;
}

header('Location: edit_pegawai?id=' . $id);
exit;
