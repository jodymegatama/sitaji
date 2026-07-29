<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';
require_role('pegawai');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header('Location: dashboard'); exit; }
csrf_check();
$u = current_user(); $id = (int)($_POST['id'] ?? 0);
$st = $pdo->prepare("UPDATE payroll SET status_terima=1 WHERE id=? AND pegawai_id=?");
$st->execute([$id, $u['pegawai_id']]);
header('Location: dashboard' . ($st->rowCount() > 0 ? '?confirm=ok' : '?confirm=fail'));
