<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/helpers.php';
require_role('stakeholder');
csrf_check();

$pkId = $_SESSION['stakeholder']['id'] ?? 0;
$id = (int)($_POST['id'] ?? 0);

if ($id <= 0 || $pkId <= 0) {
    header('Location: daftar_periode');
    exit;
}

$stmt = $pdo->prepare("SELECT id, status, bulan, tahun FROM dana_periode WHERE id = ? AND pemangku_kepentingan_id = ?");
$stmt->execute([$id, $pkId]);
$periode = $stmt->fetch();

if (!$periode) {
    $_SESSION['flash_message'] = 'Data periode tidak ditemukan.';
    $_SESSION['flash_type'] = 'danger';
    header('Location: daftar_periode');
    exit;
}

if ($periode['status'] !== 'draft') {
    $_SESSION['flash_message'] = 'Periode yang sudah di-broadcast tidak dapat dihapus.';
    $_SESSION['flash_type'] = 'danger';
    header('Location: daftar_periode');
    exit;
}

$namaPeriode = date('F', mktime(0, 0, 0, (int)$periode['bulan'], 1)) . ' ' . $periode['tahun'];

try {
    $pdo->beginTransaction();
    
    // Cleanup: hapus file lampiran sebelum hapus record
    $stmtLamp = $pdo->prepare("SELECT lampiran FROM dana_pemanfaatan WHERE periode_id = ? AND lampiran IS NOT NULL");
    $stmtLamp->execute([$id]);
    $files = $stmtLamp->fetchAll(PDO::FETCH_COLUMN);
    foreach ($files as $file) {
        $path = __DIR__ . '/../uploads/lampiran/' . $file;
        if (file_exists($path)) unlink($path);
    }

    $pdo->prepare("DELETE FROM dana_pemanfaatan WHERE periode_id = ?")->execute([$id]);
    $pdo->prepare("DELETE FROM dana_periode WHERE id = ?")->execute([$id]);
    $pdo->commit();

    $_SESSION['flash_message'] = "Periode {$namaPeriode} berhasil dihapus.";
    $_SESSION['flash_type'] = 'success';
} catch (Exception $e) {
    $pdo->rollBack();
    $_SESSION['flash_message'] = 'Terjadi kesalahan database. Silakan coba lagi.';
    $_SESSION['flash_type'] = 'danger';
}
header('Location: daftar_periode');
exit;
