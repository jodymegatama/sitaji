<?php
$pageTitle = 'Info Dana';
$activeNav = 'info_dana';
require_once __DIR__ . '/../includes/header_pegawai.php';

// Filters
$fPemangku = trim($_GET['pemangku'] ?? '');
$fBulan = (int)($_GET['bul'] ?? 0);
$fTahun = (int)($_GET['thn'] ?? 0);

$cu = current_user();
$isAdmin = $cu && $cu['role'] === 'admin';
// For pegawai: only show periods where they have a matching payroll deduction (nilai > 0)
// for at least one komponen mapped to the stakeholder. Admin sees all.
$filterExtra = '';
if (!$isAdmin) {
    $pegawaiId = (int)$cu['pegawai_id'];
    $filterExtra = "
      AND EXISTS (
        SELECT 1 FROM pemangku_kepentingan_komponen pkk
        JOIN payroll_detail pd ON pd.komponen_id = pkk.komponen_id
        JOIN payroll p ON p.id = pd.payroll_id
        WHERE pkk.pemangku_kepentingan_id = dp.pemangku_kepentingan_id
          AND p.pegawai_id = :filter_pegawai_id
          AND MONTH(p.periode) = dp.bulan
          AND YEAR(p.periode) = dp.tahun
          AND pd.nilai > 0
      )";
}

// Build query
$where = "dp.status IN ('broadcast', 'revisi_pending')";
$params = [];

if (!$isAdmin) {
    $params[':filter_pegawai_id'] = (int)$cu['pegawai_id'];
}

if ($fPemangku !== '') {
    $where .= " AND pk.nama LIKE :pemangku";
    $params[':pemangku'] = '%' . $fPemangku . '%';
}
if ($fBulan >= 1 && $fBulan <= 12) {
    $where .= " AND dp.bulan = :bulan";
    $params[':bulan'] = $fBulan;
}
if ($fTahun >= 2020) {
    $where .= " AND dp.tahun = :tahun";
    $params[':tahun'] = $fTahun;
}

$stmt = $pdo->prepare("
    SELECT dp.id, dp.bulan, dp.tahun, dp.total_pemasukan, dp.broadcast_at, dp.status,
           dp.is_revisi, pk.nama AS nama_pemangku
    FROM dana_periode dp
    JOIN pemangku_kepentingan pk ON pk.id = dp.pemangku_kepentingan_id
    WHERE $where
    $filterExtra
    ORDER BY dp.broadcast_at DESC
");
$stmt->execute($params);
$periodes = $stmt->fetchAll();

// Fetch rincian for each periode
$bulanNama = [1=>'Januari',2=>'Februari',3=>'Maret',4=>'April',5=>'Mei',6=>'Juni',
              7=>'Juli',8=>'Agustus',9=>'September',10=>'Oktober',11=>'November',12=>'Desember'];

$periodeData = [];
foreach ($periodes as $p) {
    $pid = (int)$p['id'];
    $rStmt = $pdo->prepare("SELECT keterangan, nominal FROM dana_pemanfaatan WHERE periode_id = ? ORDER BY id ASC");
    $rStmt->execute([$pid]);
    $rincian = $rStmt->fetchAll();
    $totalPem = array_sum(array_column($rincian, 'nominal'));

    // Flag eksplisit dari kolom is_revisi (di-set oleh approve_revisi.php)
    $isRevisi = (int)$p['is_revisi'] === 1;

    $periodeData[$pid] = [
        'rincian' => $rincian,
        'total_pem' => $totalPem,
        'sisa' => (float)$p['total_pemasukan'] - $totalPem,
        'is_revisi' => $isRevisi,
    ];
}

// Distinct list of pemangku names for filter dropdown — filtered by payroll match for pegawai
if ($isAdmin) {
    $pemangkuList = $pdo->query("SELECT DISTINCT pk.nama FROM dana_periode dp JOIN pemangku_kepentingan pk ON pk.id = dp.pemangku_kepentingan_id WHERE dp.status IN ('broadcast','revisi_pending') ORDER BY pk.nama ASC")->fetchAll(PDO::FETCH_COLUMN);
    $yearList = $pdo->query("SELECT DISTINCT tahun FROM dana_periode WHERE status IN ('broadcast','revisi_pending') ORDER BY tahun DESC")->fetchAll(PDO::FETCH_COLUMN);
} else {
    $pegawaiId = (int)$cu['pegawai_id'];
    $pemStmt = $pdo->prepare("SELECT DISTINCT pk.nama FROM dana_periode dp JOIN pemangku_kepentingan pk ON pk.id = dp.pemangku_kepentingan_id WHERE dp.status IN ('broadcast','revisi_pending') AND EXISTS (SELECT 1 FROM pemangku_kepentingan_komponen pkk JOIN payroll_detail pd ON pd.komponen_id = pkk.komponen_id JOIN payroll p ON p.id = pd.payroll_id WHERE pkk.pemangku_kepentingan_id = dp.pemangku_kepentingan_id AND p.pegawai_id = ? AND MONTH(p.periode) = dp.bulan AND YEAR(p.periode) = dp.tahun AND pd.nilai > 0) ORDER BY pk.nama ASC");
    $pemStmt->execute([$pegawaiId]);
    $pemangkuList = $pemStmt->fetchAll(PDO::FETCH_COLUMN);
    $yrStmt = $pdo->prepare("SELECT DISTINCT tahun FROM dana_periode dp WHERE dp.status IN ('broadcast','revisi_pending') AND EXISTS (SELECT 1 FROM pemangku_kepentingan_komponen pkk JOIN payroll_detail pd ON pd.komponen_id = pkk.komponen_id JOIN payroll p ON p.id = pd.payroll_id WHERE pkk.pemangku_kepentingan_id = dp.pemangku_kepentingan_id AND p.pegawai_id = ? AND MONTH(p.periode) = dp.bulan AND YEAR(p.periode) = dp.tahun AND pd.nilai > 0) ORDER BY dp.tahun DESC");
    $yrStmt->execute([$pegawaiId]);
    $yearList = $yrStmt->fetchAll(PDO::FETCH_COLUMN);
}
?>

<div class="d-flex justify-content-between align-items-end flex-wrap gap-3 mb-4">
  <div>
    <h2 class="fw-bold mb-1">Info Dana Pemangku Kepentingan</h2>
    <p class="text-muted mb-0">Lihat laporan dana yang telah di-broadcast oleh pemangku kepentingan.</p>
  </div>
</div>

<div class="app-card mb-4">
  <form method="get" class="row g-3 align-items-end">
    <div class="col-md-3">
      <label for="fPemangku" class="form-label fw-semibold small text-uppercase text-muted">Pemangku Kepentingan</label>
      <input type="text" id="fPemangku" class="form-control" name="pemangku" value="<?= e($fPemangku) ?>" placeholder="Cari nama...">
    </div>
    <div class="col-md-2">
      <label for="fBulan" class="form-label fw-semibold small text-uppercase text-muted">Bulan</label>
      <select id="fBulan" class="form-select" name="bul">
        <option value="0">Semua Bulan</option>
        <?php for ($m = 1; $m <= 12; $m++): ?>
          <option value="<?= $m ?>" <?= $fBulan === $m ? 'selected' : '' ?>><?= $bulanNama[$m] ?></option>
        <?php endfor; ?>
      </select>
    </div>
    <div class="col-md-2">
      <label for="fTahun" class="form-label fw-semibold small text-uppercase text-muted">Tahun</label>
      <select id="fTahun" class="form-select" name="thn">
        <option value="0">Semua Tahun</option>
        <?php foreach ($yearList as $y): ?>
          <option value="<?= $y ?>" <?= $fTahun == $y ? 'selected' : '' ?>><?= $y ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="col-md-2 d-flex gap-2">
      <button type="submit" class="btn-primary-modern"><i class="bi bi-search"></i> Filter</button>
      <a href="info_dana" class="btn-secondary-modern">Reset</a>
    </div>
  </form>
</div>

<?php if (empty($periodes)): ?>
<div class="app-card">
  <div class="text-center py-5">
    <i class="bi bi-inbox text-muted" style="font-size:3rem"></i>
    <p class="text-muted mt-3 mb-0">Belum ada data dana yang di-broadcast.</p>
  </div>
</div>
<?php else: ?>
<div class="app-card" style="overflow:hidden">
  <div style="background:var(--primary-soft);padding:16px 20px;border-bottom:1px solid var(--border)">
    <h5 class="fw-bold mb-0"><i class="bi bi-megaphone text-primary"></i> Riwayat Broadcast Dana</h5>
  </div>
  <div style="padding:16px 20px">
    <div class="accordion" id="infoDanaAccordion">
      <?php foreach ($periodes as $idx => $p):
          $pid = (int)$p['id'];
          $d = $periodeData[$pid];
          $collapseId = "infoDana_$pid";
      ?>
      <div class="accordion-item border-0 mb-2" style="border-radius:12px!important;overflow:hidden;border:1px solid var(--border)!important">
        <h2 class="accordion-header">
          <button class="accordion-button <?= $idx > 0 ? 'collapsed' : '' ?>" type="button"
                  data-bs-toggle="collapse" data-bs-target="#<?= $collapseId ?>"
                  aria-expanded="<?= $idx === 0 ? 'true' : 'false' ?>">
            <div class="d-flex align-items-center gap-2 flex-grow-1 flex-wrap">
              <i class="bi bi-building text-primary"></i>
              <span class="fw-semibold"><?= e($p['nama_pemangku']) ?></span>
              <span class="text-muted">—</span>
              <span class="fw-semibold"><?= $bulanNama[(int)$p['bulan']] ?> <?= $p['tahun'] ?></span>
              <?php if ($d['is_revisi']): ?>
                <span class="badge bg-warning text-dark"><i class="bi bi-pencil-square"></i> Revisi</span>
              <?php endif; ?>
              <?php if ($p['status'] === 'revisi_pending'): ?>
                <span class="badge bg-info text-dark">Menunggu Persetujuan</span>
              <?php endif; ?>
              <span class="ms-auto text-muted small"><?= date('d M Y H:i', strtotime($p['broadcast_at'])) ?></span>
            </div>
          </button>
        </h2>
        <div id="<?= $collapseId ?>" class="accordion-collapse collapse <?= $idx === 0 ? 'show' : '' ?>" data-bs-parent="#infoDanaAccordion">
          <div class="accordion-body p-3">
            <div class="row g-3 mb-3">
              <div class="col-sm-4">
                <div class="text-muted small">Total Pemasukan</div>
                <div class="fw-bold text-success fs-5"><?= rupiah($p['total_pemasukan']) ?></div>
              </div>
              <div class="col-sm-4">
                <div class="text-muted small">Total Pemanfaatan</div>
                <div class="fw-bold text-danger fs-5"><?= rupiah($d['total_pem']) ?></div>
              </div>
              <div class="col-sm-4">
                <div class="text-muted small">Sisa Saldo</div>
                <div class="fw-bold fs-5 <?= $d['sisa'] >= 0 ? 'text-success' : 'text-danger' ?>"><?= rupiah($d['sisa']) ?></div>
              </div>
            </div>
            <?php if (!empty($d['rincian'])): ?>
            <h6 class="fw-bold text-muted small text-uppercase mb-2">Rincian Pemanfaatan</h6>
            <table class="table table-sm mb-0" style="font-size:.85rem">
              <thead><tr><th>Keterangan</th><th class="text-end">Nominal</th></tr></thead>
              <tbody>
                <?php foreach ($d['rincian'] as $r): ?>
                <tr>
                  <td><?= e($r['keterangan']) ?></td>
                  <td class="text-end"><?= rupiah($r['nominal']) ?></td>
                </tr>
                <?php endforeach; ?>
                <tr class="table-active fw-bold">
                  <td>Total Pemanfaatan</td>
                  <td class="text-end"><?= rupiah($d['total_pem']) ?></td>
                </tr>
              </tbody>
            </table>
            <?php else: ?>
            <p class="text-muted fst-italic small mb-0">Belum ada rincian pemanfaatan.</p>
            <?php endif; ?>
          </div>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</div>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
