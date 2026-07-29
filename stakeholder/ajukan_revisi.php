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
$periodeId = (int)($_POST['periode_id'] ?? 0);
$totalPemasukan = (float)($_POST['total_pemasukan'] ?? 0);
$rincianKeterangan = $_POST['rincian_keterangan'] ?? [];
$rincianNominal = $_POST['rincian_nominal'] ?? [];
$alasan = trim($_POST['alasan'] ?? '');

if ($periodeId <= 0 || $pkId <= 0) {
    header('Location: daftar_periode');
    exit;
}

// Validasi dasar
if ($totalPemasukan <= 0 || $alasan === '') {
    header('Location: detail_periode?id=' . $periodeId . '&error=revisi_empty');
    exit;
}
if ($totalPemasukan > 9999999999999) {
    header('Location: detail_periode?id=' . $periodeId . '&error=nominal_over');
    exit;
}

// Bangun array rincian yang valid
$rincian = [];
for ($i = 0; $i < count($rincianKeterangan); $i++) {
    $ket = trim($rincianKeterangan[$i] ?? '');
    $nom = (float)($rincianNominal[$i] ?? 0);
    if ($ket !== '' && $nom > 0) {
        if ($nom > 9999999999999) {
            header('Location: detail_periode?id=' . $periodeId . '&error=nominal_over');
            exit;
        }
        $rincian[] = ['keterangan' => $ket, 'nominal' => $nom];
    }
}

// Validasi: total pemanfaatan <= total pemasukan
$totalPemanfaatan = array_sum(array_column($rincian, 'nominal'));
if ($totalPemanfaatan > $totalPemasukan) {
    header('Location: detail_periode?id=' . $periodeId . '&error=pemanfaatan_over');
    exit;
}

// Ambil periode (hanya milik stakeholder ini)
$stmt = $pdo->prepare("SELECT status FROM dana_periode WHERE id = ? AND pemangku_kepentingan_id = ?");
$stmt->execute([$periodeId, $pkId]);
$periode = $stmt->fetch();

if (!$periode) {
    header('Location: daftar_periode');
    exit;
}

// Hanya periode broadcast yang bisa direvisi
if ($periode['status'] !== 'broadcast') {
    header('Location: detail_periode?id=' . $periodeId);
    exit;
}

// Cek sudah ada revisi pending?
$cek = $pdo->prepare("SELECT COUNT(*) FROM dana_revisi_request WHERE periode_id = ? AND status = 'pending'");
$cek->execute([$periodeId]);
if ((int)$cek->fetchColumn() > 0) {
    header('Location: detail_periode?id=' . $periodeId);
    exit;
}

// Siapkan data baru sebagai JSON
$dataBaru = json_encode([
    'total_pemasukan' => $totalPemasukan,
    'pemanfaatan' => $rincian
], JSON_UNESCAPED_UNICODE);

$userId = $_SESSION['user']['id'];

try {
    $pdo->beginTransaction();

    $pdo->prepare("INSERT INTO dana_revisi_request (periode_id, diajukan_oleh, data_baru, alasan) VALUES (?, ?, ?, ?)")
        ->execute([$periodeId, $userId, $dataBaru, $alasan]);

    $pdo->prepare("UPDATE dana_periode SET status = 'revisi_pending' WHERE id = ?")
        ->execute([$periodeId]);

    $pdo->commit();
    header('Location: detail_periode?id=' . $periodeId . '&success=revisi_submitted');
} catch (Exception $e) {
    $pdo->rollBack();
    header('Location: detail_periode?id=' . $periodeId . '&error=revisi_failed');
}
exit;
