<?php
if (!defined('INSTANSI')) define('INSTANSI', 'KEMENTERIAN AGAMA REPUBLIK INDONESIA');
if (!defined('INSTANSI_UNIT')) define('INSTANSI_UNIT', 'KANTOR KEMENTERIAN AGAMA KABUPATEN PASURUAN');
if (!defined('INSTANSI_ALAMAT')) define('INSTANSI_ALAMAT', 'Jalan Dr. Wahidin Sudirohusodo No. 5 Kota Pasuruan Kode Pos 67126');
if (!defined('INSTANSI_TELEPON')) define('INSTANSI_TELEPON', 'Telepon (0343) 421947, Faksimili (0343) 421947');
if (!defined('INSTANSI_WEBSITE')) define('INSTANSI_WEBSITE', 'website : http://kabpasuruan.kemenag.go.id');

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/helpers.php';
require_role('pegawai');

$u = current_user();
$id = (int)($_GET['id'] ?? 0);

$st = $pdo->prepare("SELECT p.*, pe.nip, pe.nama, pe.golongan, pe.jabatan
    FROM payroll p
    JOIN pegawai pe ON pe.id = p.pegawai_id
    WHERE p.id=? AND p.pegawai_id=?");
$st->execute([$id, $u['pegawai_id']]);
$r = $st->fetch();

if (!$r) {
    echo '<!doctype html><html><head><title>Error</title></head><body style="font-family:sans-serif;text-align:center;padding:4rem"><h2>Slip tidak ditemukan.</h2><p><a href="dashboard">Kembali ke Dashboard</a></p></body></html>';
    exit;
}

$fields = payroll_fields();

try {
    $values = payroll_values_map($id);
    $r = array_merge($r, $values);
} catch (PDOException $e) {
    error_log("Cetak Slip Error: " . $e->getMessage());
    echo '<!doctype html><html><head><title>Error</title></head><body style="font-family:sans-serif;text-align:center;padding:4rem"><h2>Terjadi kesalahan sistem.</h2><p><a href="dashboard">Kembali ke Dashboard</a></p></body></html>';
    exit;
}

$r['_detail_sum_pendapatan'] = array_sum(array_intersect_key($values, array_flip(array_keys(payroll_fields()['pendapatan']))));
$r['_detail_sum_potongan'] = array_sum(array_intersect_key($values, array_flip(array_merge(array_keys(payroll_fields()['potongan_umum']), array_keys(payroll_fields()['koperasi'])))));

// Format tanggal Indonesia
$bulanIndo = ['', 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
$tglCetak = date('j') . ' ' . $bulanIndo[(int)date('n')] . ' ' . date('Y');

$allPot = array_merge($fields['pendapatan'], $fields['potongan_umum'], $fields['koperasi']);
$pendapatanFields = $fields['pendapatan'];
$potonganFields = array_merge($fields['potongan_umum'], $fields['koperasi']);

// Hitung nomor urut
$noPend = 0; $noPot = 0;
?>
<!doctype html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Cetak Slip Gaji - <?= e($r['nama']) ?></title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=JetBrains+Mono:wght@400;500;600&display=swap" rel="stylesheet">
<style>
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
    body {
        font-family: 'Plus Jakarta Sans', Arial, Helvetica, sans-serif;
        font-size: 12px;
        line-height: 1.5;
        color: #000;
        background: #f0f0f0;
        padding: 20px;
    }
    .page {
        max-width: 210mm;
        margin: 0 auto;
        background: #fff;
        padding: 2cm;
        box-shadow: 0 2px 12px rgba(0,0,0,0.1);
        min-height: 297mm;
    }
    .kop {
        margin-bottom: 6px;
    }
    .kop .kop-body {
        display:flex;
        align-items:center;
        justify-content:space-between;
        margin-bottom:6px;
    }
    .kop .kop-text {
        flex:1;
        text-align:center;
    }
    .kop .kop-text h1 {
        font-size: 16px;
        font-weight: 800;
        letter-spacing: 1px;
        text-transform: uppercase;
        margin-bottom: 1px;
    }
    .kop .kop-text .instansi-unit {
        font-size: 13px;
        font-weight: 700;
        letter-spacing: 0.5px;
        text-transform: uppercase;
        margin-bottom: 3px;
    }
    .kop .kop-text .info-line {
        font-size: 10px;
        color: #333;
        line-height: 1.4;
    }
    .kop hr {
        border: none;
        border-top: 3px solid #000;
        margin: 6px 0 14px 0;
    }
    .judul {
        text-align: center;
        margin-bottom: 16px;
    }
    .judul h2 {
        font-size: 15px;
        font-weight: bold;
        text-transform: uppercase;
        margin-bottom: 2px;
    }
    .judul p {
        font-size: 13px;
        font-weight: 600;
    }
    table.info {
        width: 100%;
        border-collapse: collapse;
        margin-bottom: 16px;
        font-size: 12px;
    }
    table.info td {
        padding: 2px 4px;
        vertical-align: top;
    }
    table.info td:first-child {
        width: 100px;
        font-weight: 600;
    }
    table.info td.sep {
        width: 12px;
        text-align: center;
    }
    .table-slip {
        width: 100%;
        border-collapse: collapse;
        margin-bottom: 16px;
        font-size: 12px;
    }
    .table-slip thead th {
        background: #e8e8e8;
        border: 1px solid #000;
        padding: 5px 6px;
        text-align: left;
        font-size: 11px;
        font-weight: bold;
        text-transform: uppercase;
    }
    .table-slip tbody td {
        border: 1px solid #000;
        padding: 4px 6px;
        vertical-align: top;
    }
    .table-slip .no { text-align: center; width: 30px; }
    .table-slip .uraian { }
    .table-slip .jumlah { text-align: right; width: 130px; white-space: nowrap;}
    .table-slip .mono { font-family: 'JetBrains Mono', 'Consolas', monospace; }
    .table-slip .subtotal td {
        font-weight: bold;
        border-top: 2px solid #000;
    }
    .thp-row td {
        font-weight: bold;
        font-size: 13px;
        border: 1px solid #000;
        padding: 6px;
    }
    .thp-row .label-thp { text-align: left; }
    .thp-row .value-thp { text-align: right; }
    .ttd {
        width: 100%;
        border-collapse: collapse;
        margin-top: 30px;
        font-size: 11px;
    }
    .ttd td {
        vertical-align: top;
        padding: 4px 8px;
        width: 50%;
    }
    .ttd .kiri { text-align: left; }
    .ttd .kanan { text-align: right; }
    .ttd .spacer { height: 50px; }
    .ttd .garis { border-bottom: 1px solid #000; display: inline-block; min-width: 180px; }
    .footer {
        text-align: center;
        margin-top: 20px;
        font-size: 10px;
        color: #666;
        border-top: 1px solid #ccc;
        padding-top: 8px;
    }
    .no-print {
        text-align: center;
        margin: 16px auto;
        max-width: 210mm;
    }
    .no-print .btn {
        display: inline-block;
        padding: 10px 24px;
        margin: 0 6px;
        font-size: 13px;
        font-weight: 600;
        border: none;
        border-radius: 50px;
        cursor: pointer;
        text-decoration: none;
        color: #fff;
    }
    .no-print .btn-cetak { background: linear-gradient(135deg, #4f46e5, #6366f1); }
    .no-print .btn-cetak:hover { opacity: .9; }
    .no-print .btn-tutup { background: #64748b; }
    .no-print .btn-tutup:hover { background: #475569; }

    @media print {
        body { background: #fff; padding: 0; }
        .page {
            box-shadow: none;
            padding: 0;
            max-width: 100%;
            min-height: auto;
        }
        .no-print { display: none !important; }
        @page { size: A4; margin: 2cm; }
    }
</style>
</head>
<body>

<div class="no-print">
    <button class="btn btn-cetak btn-pill" onclick="window.print()"><i class="bi bi-printer"></i> Cetak / Download PDF</button>
    <a href="#" onclick="window.close(); return false;" class="btn btn-tutup btn-pill"><i class="bi bi-x-lg"></i> Tutup</a>
</div>

<div class="page">
    <!-- KOP SURAT -->
    <div class="kop">
        <div class="kop-body">
            <img src="../assets/img/logo-kemenag.png" alt="Kementerian Agama" height="65" style="display:block">
            <div class="kop-text">
                <h1><?= e(INSTANSI) ?></h1>
                <div class="instansi-unit"><?= e(INSTANSI_UNIT) ?></div>
                <div class="info-line"><?= e(INSTANSI_ALAMAT) ?></div>
                <div class="info-line"><?= e(INSTANSI_TELEPON) ?></div>
                <div class="info-line"><?= e(INSTANSI_WEBSITE) ?></div>
            </div>
        </div>
        <hr>
    </div>

    <!-- JUDUL -->
    <div class="judul">
        <h2>SLIP GAJI PEGAWAI</h2>
        <p>Periode <?= periode_label($r['periode']) ?></p>
    </div>

    <!-- INFO PEGAWAI -->
    <table class="info">
        <tr><td>Nama</td><td class="sep">:</td><td><?= e(strtoupper($r['nama'])) ?></td></tr>
        <tr><td>NIP</td><td class="sep">:</td><td><?= e($r['nip']) ?></td></tr>
        <tr><td>Golongan</td><td class="sep">:</td><td><?= e($r['golongan'] ?: '-') ?></td></tr>
        <tr><td>Jabatan</td><td class="sep">:</td><td><?= e($r['jabatan'] ?: '-') ?></td></tr>
    </table>

    <!-- TABEL PENDAPATAN -->
    <table class="table-slip">
        <thead>
            <tr><th class="no">No</th><th class="uraian">URAIAN PENDAPATAN</th><th class="jumlah">JUMLAH</th></tr>
        </thead>
        <tbody>
            <?php $noPend = 0; $sumPend = 0; ?>
            <?php foreach ($pendapatanFields as $k => $lab):
                $val = (float)($r[$k] ?? 0);
                if ($val <= 0 && !isset($r[$k])) continue;
                $noPend++; $sumPend += $val;
            ?>
            <tr>
                <td class="no"><?= $noPend ?></td>
                <td class="uraian"><?= e($lab) ?></td>
                <td class="jumlah mono"><?= rupiah($val) ?></td>
            </tr>
            <?php endforeach; ?>
            <?php if ($noPend === 0): ?>
            <tr><td class="no">-</td><td class="uraian">Tidak ada pendapatan</td><td class="jumlah mono"><?= rupiah(0) ?></td></tr>
            <?php endif; ?>
            <tr class="subtotal">
                <td colspan="2" style="text-align:right">TOTAL PENDAPATAN (BRUTO)</td>
                <td class="jumlah mono"><?= rupiah($sumPend) ?></td>
            </tr>
        </tbody>
    </table>

    <!-- TABEL POTONGAN -->
    <table class="table-slip">
        <thead>
            <tr><th class="no">No</th><th class="uraian">URAIAN PENGELUARAN</th><th class="jumlah">JUMLAH</th></tr>
        </thead>
        <tbody>
            <?php $noPot = 0; $sumPot = 0; ?>
            <?php foreach ($potonganFields as $k => $lab):
                $val = (float)($r[$k] ?? 0);
                if ($val <= 0 && !isset($r[$k])) continue;
                $noPot++; $sumPot += $val;
            ?>
            <tr>
                <td class="no"><?= $noPot ?></td>
                <td class="uraian"><?= e($lab) ?></td>
                <td class="jumlah mono"><?= rupiah($val) ?></td>
            </tr>
            <?php endforeach; ?>
            <?php if ($noPot === 0): ?>
            <tr><td class="no">-</td><td class="uraian">Tidak ada potongan</td><td class="jumlah mono"><?= rupiah(0) ?></td></tr>
            <?php endif; ?>
            <tr class="subtotal">
                <td colspan="2" style="text-align:right">TOTAL PENGELUARAN</td>
                <td class="jumlah mono"><?= rupiah($sumPot) ?></td>
            </tr>
        </tbody>
    </table>

    <!-- TAKE HOME PAY -->
    <table class="table-slip">
        <tbody>
            <tr class="thp-row">
                <td style="width:30px">&nbsp;</td>
                <td class="label-thp">TAKE HOME PAY / GAJI BERSIH</td>
                <td class="value-thp mono"><?= rupiah(take_home($r)) ?></td>
            </tr>
        </tbody>
    </table>

    <!-- TANDA TANGAN -->
    <table class="ttd">
        <tr>
            <td class="kiri">
                <div>&nbsp;</div>
                <div style="margin-top:4px">Mengetahui,</div>
                <div class="spacer">&nbsp;</div>
                <div class="garis">&nbsp;</div>
                <div style="margin-top:2px">Dr. Bakhrul Ulum, S.Ag., M.Si.</div>
                <div>NIP. 197608182000031002</div>
            </td>
            <td class="kanan">
                <div>Pasuruan, <?= $tglCetak ?></div>
                <div style="margin-top:4px">Pegawai yang bersangkutan,</div>
                <div class="spacer">&nbsp;</div>
                <div class="garis">&nbsp;</div>
                <div style="margin-top:2px"><?= e(strtoupper($r['nama'])) ?></div>
                <div>NIP. <?= e($r['nip']) ?></div>
            </td>
        </tr>
    </table>

    <!-- FOOTER -->
    <div class="footer">
        Dokumen ini dicetak otomatis oleh sistem SITAJI &mdash; <?= $tglCetak ?>
    </div>
</div>

</body>
</html>
