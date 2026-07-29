<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';
require_role('pegawai');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header('Location: dashboard'); exit; }
csrf_check();
$u = current_user();
$old = $_POST['old'] ?? ''; $new = $_POST['new'] ?? ''; $cf = $_POST['confirm'] ?? '';

if (strlen($new) < 8 || !preg_match('/[A-Za-z]/', $new) || !preg_match('/[0-9]/', $new)) {
    header('Location: dashboard?pwd=fail_format');
    exit;
}
if ($new !== $cf) {
    header('Location: dashboard?pwd=fail_confirm');
    exit;
}

$st = $pdo->prepare("SELECT password_hash FROM users WHERE id=?");
$st->execute([$u['id']]); $hash = $st->fetchColumn();
if (!password_verify($old, $hash)) {
    header('Location: dashboard?pwd=fail_old');
    exit;
}

$pdo->prepare("UPDATE users SET password_hash=? WHERE id=?")
    ->execute([password_hash($new, PASSWORD_DEFAULT), $u['id']]);
header('Location: dashboard?pwd=ok');
