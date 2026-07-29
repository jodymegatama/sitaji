<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/helpers.php';
require_role('stakeholder');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: daftar_periode');
    exit;
}
csrf_check();

$pkId = $_SESSION['stakeholder']['id'] ?? 0;
$id = (int)($_POST['id'] ?? 0);

if ($id <= 0 || $pkId <= 0) {
    header('Location: daftar_periode');
    exit;
}

// Ambil periode (hanya milik stakeholder ini)
$stmt = $pdo->prepare("SELECT status FROM dana_periode WHERE id = ? AND pemangku_kepentingan_id = ?");
$stmt->execute([$id, $pkId]);
$periode = $stmt->fetch();

if (!$periode) {
    header('Location: daftar_periode?error=not_found');
    exit;
}

// Hanya periode draft yang bisa di-broadcast
if ($periode['status'] !== 'draft') {
    header('Location: detail_periode?id=' . $id);
    exit;
}

try {
    $pdo->prepare("UPDATE dana_periode SET status = 'broadcast', broadcast_at = NOW() WHERE id = ?")
        ->execute([$id]);
    header('Location: detail_periode?id=' . $id . '&success=broadcast');
} catch (Exception $e) {
    header('Location: detail_periode?id=' . $id . '&error=broadcast_failed');
}
exit;
