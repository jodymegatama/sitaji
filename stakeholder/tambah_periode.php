<?php
$pageTitle = 'Tambah Periode Dana';
$activeNav = 'periode';
require_once __DIR__ . '/../includes/header_stakeholder.php';

// Hanya POST — GET redirect ke daftar_periode
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: daftar_periode');
    exit;
}

$pkId = $stakeholder['id'];

// Ambil saldo_awal dari periode BROADCAST terakhir milik stakeholder yang sama
$saldoAwal = 0;
$lastPeriod = $pdo->prepare("
    SELECT dp.total_pemasukan, COALESCE(SUM(dm.nominal), 0) AS total_pemanfaatan
    FROM dana_periode dp
    LEFT JOIN dana_pemanfaatan dm ON dm.periode_id = dp.id
    WHERE dp.pemangku_kepentingan_id = ? AND dp.status = 'broadcast'
    GROUP BY dp.id
    ORDER BY dp.tahun DESC, dp.bulan DESC
    LIMIT 1
");
$lastPeriod->execute([$pkId]);
$lastRow = $lastPeriod->fetch();
if ($lastRow) {
    $saldoAwal = (float)$lastRow['total_pemasukan'] - (float)$lastRow['total_pemanfaatan'];
}
if ($saldoAwal < 0) $saldoAwal = 0;

// Ambil komponen potongan yang di-mapping ke stakeholder ini
$komponenMapped = $pdo->prepare("
    SELECT kp.id, kp.nama, kp.field_key
    FROM pemangku_kepentingan_komponen pkc
    JOIN komponen_payroll kp ON kp.id = pkc.komponen_id
    WHERE pkc.pemangku_kepentingan_id = ?
");
$komponenMapped->execute([$pkId]);
$komponenMapped = $komponenMapped->fetchAll();

csrf_check();

$bulan = (int)($_POST['bulan'] ?? 0);
$tahun = (int)($_POST['tahun'] ?? 0);
$totalPemasukan = (float)($_POST['total_pemasukan'] ?? 0);
$rincianKeterangan = $_POST['rincian_keterangan'] ?? [];
$rincianNominal = $_POST['rincian_nominal'] ?? [];

// Validasi dasar
if ($bulan < 1 || $bulan > 12 || $tahun < 2020) {
    $_SESSION['flash_message'] = 'Data tidak lengkap. Bulan, tahun, dan total pemasukan wajib diisi.';
    $_SESSION['flash_type'] = 'danger';
    header('Location: daftar_periode?modal=tambah');
    exit;
}
if ($totalPemasukan <= 0) {
    $_SESSION['flash_message'] = 'Total nominal harus lebih dari 0.';
    $_SESSION['flash_type'] = 'danger';
    header('Location: daftar_periode?modal=tambah');
    exit;
}
if ($totalPemasukan > 9999999999999) {
    $_SESSION['flash_message'] = 'Nominal terlalu besar. Maksimal 9.999.999.999.999 (13 digit).';
    $_SESSION['flash_type'] = 'danger';
    header('Location: daftar_periode?modal=tambah');
    exit;
}

// Bangun array rincian yang valid
$rincian = [];
for ($i = 0; $i < count($rincianKeterangan); $i++) {
    $ket = trim($rincianKeterangan[$i] ?? '');
    $nom = (float)($rincianNominal[$i] ?? 0);
    if ($ket !== '' && $nom > 0) {
        if ($nom > 9999999999999) {
            $_SESSION['flash_message'] = 'Nominal terlalu besar. Maksimal 9.999.999.999.999 (13 digit).';
            $_SESSION['flash_type'] = 'danger';
            header('Location: daftar_periode?modal=tambah');
            exit;
        }
        $rincian[] = ['keterangan' => $ket, 'nominal' => $nom];
    }
}

if (empty($rincian)) {
    $_SESSION['flash_message'] = 'Tambahkan minimal 1 rincian pemanfaatan dana.';
    $_SESSION['flash_type'] = 'danger';
    header('Location: daftar_periode?modal=tambah');
    exit;
}

// Validasi: total pemanfaatan <= (saldo_awal + total pemasukan)
$totalPemanfaatan = array_sum(array_column($rincian, 'nominal'));
$maksPemanfaatan = $saldoAwal + $totalPemasukan;
if ($totalPemanfaatan > $maksPemanfaatan) {
    $_SESSION['flash_message'] = 'Total pemanfaatan melebihi total saldo yang tersedia (saldo awal + pemasukan).';
    $_SESSION['flash_type'] = 'danger';
    header('Location: daftar_periode?modal=tambah');
    exit;
}

try {
    $pdo->beginTransaction();

    $pdo->prepare("INSERT INTO dana_periode (pemangku_kepentingan_id, bulan, tahun, total_pemasukan, saldo_awal, status) VALUES (?, ?, ?, ?, ?, 'draft')")
        ->execute([$pkId, $bulan, $tahun, $totalPemasukan, $saldoAwal]);
    $periodeId = (int)$pdo->lastInsertId();

    $stmt = $pdo->prepare("INSERT INTO dana_pemanfaatan (periode_id, keterangan, nominal) VALUES (?, ?, ?)");
    foreach ($rincian as $r) {
        $stmt->execute([$periodeId, $r['keterangan'], $r['nominal']]);
    }

    $pdo->commit();
    $_SESSION['flash_message'] = 'Periode berhasil ditambahkan.';
    $_SESSION['flash_type'] = 'success';
    header('Location: daftar_periode');
    exit;
} catch (PDOException $e) {
    $pdo->rollBack();
    $isDup = ($e->errorInfo[1] ?? 0) === 1062
             && str_contains($e->errorInfo[2] ?? '', 'uniq_periode');
    $_SESSION['flash_message'] = $isDup
        ? 'Periode bulan/tahun yang sama sudah ada. Pilih bulan atau tahun yang berbeda.'
        : 'Terjadi kesalahan database. Silakan coba lagi.';
    $_SESSION['flash_type'] = 'danger';
    header('Location: daftar_periode?modal=tambah');
    exit;
}
