<?php
/**
 * stakeholder/ajukan_hapus.php
 *
 * Endpoint POST untuk pengajuan penghapusan periode dana (broadcast)
 * oleh stakeholder ke admin. Mengikuti pola stakeholder/ajukan_revisi.php.
 *
 * Validasi:
 *   - require_role('stakeholder')
 *   - POST only, csrf_check()
 *   - Periode milik stakeholder ini (pemangku_kepentingan_id = pkId)
 *   - Status periode = 'broadcast'
 *   - Tidak ada hapus_request pending untuk periode ini
 *   - Tidak ada revisi_request pending untuk periode ini
 *   - Alasan tidak boleh kosong
 *
 * Saat pengajuan:
 *   - INSERT ke dana_hapus_request dengan snapshot data periode
 *   - UPDATE dana_periode.status = 'hapus_pending'
 */
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
$alasan = trim($_POST['alasan'] ?? '');

if ($periodeId <= 0 || $pkId <= 0) {
    header('Location: daftar_periode');
    exit;
}

if ($alasan === '') {
    header('Location: detail_periode?id=' . $periodeId . '&error=hapus_empty');
    exit;
}

$bulanNama = [
    1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
    5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
    9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember'
];

// Ambil periode (hanya milik stakeholder ini)
$stmt = $pdo->prepare("
    SELECT dp.*, pk.nama AS nama_pemangku,
           COALESCE((SELECT SUM(nominal) FROM dana_pemanfaatan WHERE periode_id = dp.id), 0) AS total_pemanfaatan
    FROM dana_periode dp
    JOIN pemangku_kepentingan pk ON pk.id = dp.pemangku_kepentingan_id
    WHERE dp.id = ? AND dp.pemangku_kepentingan_id = ?
");
$stmt->execute([$periodeId, $pkId]);
$periode = $stmt->fetch();

if (!$periode) {
    header('Location: daftar_periode');
    exit;
}

// Hanya periode broadcast yang bisa diajukan hapus
if ($periode['status'] !== 'broadcast') {
    header('Location: detail_periode?id=' . $periodeId);
    exit;
}

// Cek tidak ada hapus_request pending
$cekHapus = $pdo->prepare("SELECT COUNT(*) FROM dana_hapus_request WHERE periode_id = ? AND status = 'pending'");
$cekHapus->execute([$periodeId]);
if ((int)$cekHapus->fetchColumn() > 0) {
    header('Location: detail_periode?id=' . $periodeId);
    exit;
}

// Cek tidak ada revisi_request pending
$cekRevisi = $pdo->prepare("SELECT COUNT(*) FROM dana_revisi_request WHERE periode_id = ? AND status = 'pending'");
$cekRevisi->execute([$periodeId]);
if ((int)$cekRevisi->fetchColumn() > 0) {
    header('Location: detail_periode?id=' . $periodeId);
    exit;
}

// Siapkan snapshot
$periodeLabel = $bulanNama[(int)$periode['bulan']] . ' ' . $periode['tahun'];
$namaPemangku = $periode['nama_pemangku'];
$totalPemasukan = (float)$periode['total_pemasukan'];
$totalPemanfaatan = (float)$periode['total_pemanfaatan'];
$userId = $_SESSION['user']['id'];

try {
    $pdo->beginTransaction();

    $pdo->prepare("
        INSERT INTO dana_hapus_request
            (periode_id, diajukan_oleh, alasan, status, periode_label, nama_pemangku,
             total_pemasukan, total_pemanfaatan)
        VALUES (?, ?, ?, 'pending', ?, ?, ?, ?)
    ")->execute([
        $periodeId, $userId, $alasan, $periodeLabel, $namaPemangku,
        $totalPemasukan, $totalPemanfaatan
    ]);

    $pdo->prepare("UPDATE dana_periode SET status = 'hapus_pending', updated_at = NOW() WHERE id = ?")
        ->execute([$periodeId]);

    $pdo->commit();
    header('Location: detail_periode?id=' . $periodeId . '&success=hapus_submitted');
} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    header('Location: detail_periode?id=' . $periodeId . '&error=hapus_failed');
}
exit;
