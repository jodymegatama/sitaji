<?php
$pageTitle = 'Dashboard';
$activeNav = 'dashboard';
require_once __DIR__ . '/../includes/header_stakeholder.php';

$pkId = $stakeholder['id'];
$tahunIni = (int)date('Y');

// Saldo Berjalan Terkini: ambil sisa saldo dari periode broadcast terakhir
$saldoBerjalan = 0;
$lastPeriod = $pdo->prepare("
    SELECT dp.total_pemasukan, dp.saldo_awal, COALESCE(SUM(dm.nominal), 0) AS total_pemanfaatan
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
    $saldoAwal = (float)($lastRow['saldo_awal'] ?? 0);
    $saldoBerjalan = $saldoAwal + (float)$lastRow['total_pemasukan'] - (float)$lastRow['total_pemanfaatan'];
}
if ($saldoBerjalan < 0) $saldoBerjalan = 0;

// Total Pemasukan Tahun Berjalan
$stmtPemasukan = $pdo->prepare("
    SELECT COALESCE(SUM(dp.total_pemasukan), 0) AS total
    FROM dana_periode dp
    WHERE dp.pemangku_kepentingan_id = ? AND dp.tahun = ?
");
$stmtPemasukan->execute([$pkId, $tahunIni]);
$totalPemasukanTahun = (float)$stmtPemasukan->fetchColumn();

// Total Pemanfaatan Tahun Berjalan
$stmtPemanfaatan = $pdo->prepare("
    SELECT COALESCE(SUM(dm.nominal), 0) AS total
    FROM dana_periode dp
    JOIN dana_pemanfaatan dm ON dm.periode_id = dp.id
    WHERE dp.pemangku_kepentingan_id = ? AND dp.tahun = ?
");
$stmtPemanfaatan->execute([$pkId, $tahunIni]);
$totalPemanfaatanTahun = (float)$stmtPemanfaatan->fetchColumn();

// Jumlah Periode Dilaporkan
$stmtJumlah = $pdo->prepare("SELECT COUNT(*) FROM dana_periode WHERE pemangku_kepentingan_id = ?");
$stmtJumlah->execute([$pkId]);
$jumlahPeriode = (int)$stmtJumlah->fetchColumn();

// 5 Periode Terbaru
$listLatest = $pdo->prepare("
    SELECT dp.*,
           COALESCE(SUM(dm.nominal), 0) AS total_pemanfaatan
    FROM dana_periode dp
    LEFT JOIN dana_pemanfaatan dm ON dm.periode_id = dp.id
    WHERE dp.pemangku_kepentingan_id = ?
    GROUP BY dp.id
    ORDER BY dp.tahun DESC, dp.bulan DESC
    LIMIT 5
");
$listLatest->execute([$pkId]);
$listLatest = $listLatest->fetchAll();

// Rincian Pemanfaatan Terbesar Tahun Berjalan
$topPemanfaatan = $pdo->prepare("
    SELECT dm.keterangan, dm.nominal, dp.bulan, dp.tahun
    FROM dana_pemanfaatan dm
    JOIN dana_periode dp ON dp.id = dm.periode_id
    WHERE dp.pemangku_kepentingan_id = ? AND dp.tahun = ? AND dp.status = 'broadcast'
    ORDER BY dm.nominal DESC
    LIMIT 5
");
$topPemanfaatan->execute([$pkId, $tahunIni]);
$topPemanfaatanRows = $topPemanfaatan->fetchAll();

$bulanNama = [
    1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
    5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
    9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember'
];
$statusBadge = [
    'draft' => 'bg-secondary',
    'broadcast' => 'bg-success',
    'revisi_pending' => 'bg-warning text-dark',
];
?>
<div class="mb-4">
  <h2 class="fw-bold mb-1">Dashboard</h2>
  <p class="text-muted mb-0">Ringkasan laporan dana pemangku kepentingan.</p>
</div>

<div class="row g-3 mb-4">
  <div class="col-md-3 col-sm-6">
    <div class="stat-card">
      <div><div class="label">Saldo Berjalan Terkini</div><div class="value"><?= rupiah($saldoBerjalan) ?></div></div>
      <div class="icon"><i class="bi bi-wallet2"></i></div>
    </div>
  </div>
  <div class="col-md-3 col-sm-6">
    <div class="stat-card green">
      <div><div class="label">Pemasukan Tahun <?= $tahunIni ?></div><div class="value"><?= rupiah($totalPemasukanTahun) ?></div></div>
      <div class="icon"><i class="bi bi-graph-up-arrow"></i></div>
    </div>
  </div>
  <div class="col-md-3 col-sm-6">
    <div class="stat-card red">
      <div><div class="label">Pemanfaatan Tahun <?= $tahunIni ?></div><div class="value"><?= rupiah($totalPemanfaatanTahun) ?></div></div>
      <div class="icon"><i class="bi bi-receipt"></i></div>
    </div>
  </div>
  <div class="col-md-3 col-sm-6">
    <div class="stat-card yellow">
      <div><div class="label">Jumlah Periode</div><div class="value"><?= number_format($jumlahPeriode, 0, ',', '.') ?></div></div>
      <div class="icon"><i class="bi bi-calendar-check"></i></div>
    </div>
  </div>
</div>

<div class="row g-4 mb-4">
  <div class="col-md-8">
    <div class="app-card">
      <h5 class="fw-bold mb-3">Rincian Pemanfaatan Terbesar (<?= $tahunIni ?>)</h5>
      <?php if (empty($topPemanfaatanRows)): ?>
        <p class="text-muted fst-italic small mb-0">Belum ada rincian pemanfaatan tahun ini.</p>
      <?php else: ?>
        <div class="list-group list-group-flush">
          <?php foreach ($topPemanfaatanRows as $i => $row): ?>
          <div class="list-group-item px-0 py-3 border-start-0 border-end-0 d-flex justify-content-between align-items-center">
            <div class="d-flex align-items-center gap-3">
              <span class="d-inline-flex align-items-center justify-content-center rounded-2 flex-shrink-0 fw-bold small" style="width:32px;height:32px;background:var(--primary-soft);color:var(--primary);"><?= $i + 1 ?></span>
              <div>
                <div class="fw-semibold"><?= e($row['keterangan']) ?></div>
                <div class="text-muted small"><?= $bulanNama[(int)$row['bulan']] ?> <?= $row['tahun'] ?></div>
              </div>
            </div>
            <span class="num fw-bold" style="color:var(--danger);"><?= rupiah($row['nominal']) ?></span>
          </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>
  </div>
  <div class="col-md-4">
    <div class="app-card">
      <h5 class="fw-bold mb-3">Periode Terbaru</h5>
      <?php if (empty($listLatest)): ?>
        <p class="text-muted fst-italic small mb-0">Belum ada periode.</p>
      <?php else: ?>
        <div class="list-group list-group-flush">
          <?php foreach ($listLatest as $row):
            $saldoAwal = (float)($row['saldo_awal'] ?? 0);
            $pemasukan = (float)$row['total_pemasukan'];
            $pemanfaatan = (float)$row['total_pemanfaatan'];
            $sisa = $saldoAwal + $pemasukan - $pemanfaatan;
          ?>
          <a href="detail_periode?id=<?= $row['id'] ?>" class="list-group-item list-group-item-action px-0 py-3 border-start-0 border-end-0">
            <div class="d-flex justify-content-between align-items-center">
              <div>
                <div class="fw-semibold"><?= $bulanNama[(int)$row['bulan']] ?> <?= $row['tahun'] ?></div>
                <div class="text-muted small">Sisa: <?= rupiah($sisa) ?></div>
              </div>
              <span class="badge <?= $statusBadge[$row['status']] ?? 'bg-secondary' ?>"><?= ucfirst(str_replace('_', ' ', $row['status'])) ?></span>
            </div>
          </a>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>
  </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
