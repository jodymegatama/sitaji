<?php
$pageTitle = 'Dashboard Payroll';
$activeNav = 'payroll';
require_once __DIR__ . '/../includes/header_admin.php';

// Filter Periode
$periodeFilter = $_GET['periode'] ?? date('Y-m'); // Default bulan berjalan
$isAll = ($periodeFilter === 'all');

$where = "";
$whereSub = "";
$params = [];

if (!$isAll) {
    $where = "WHERE p.periode = :periode";
    $whereSub = "WHERE pd.payroll_id IN (SELECT p_sub.id FROM payroll p_sub WHERE p_sub.periode = :periode_sub)";
    $params['periode'] = $periodeFilter . '-01';
    $params['periode_sub'] = $periodeFilter . '-01';
}

// Statistik (tetap global, tapi bisa diadjust kalau perlu)
$totalPegawai = (int)$pdo->query("SELECT COUNT(*) FROM pegawai")->fetchColumn();

// Query utama dengan filter
try {
    $sql = "
      SELECT p.*, pg.nama, pg.nip,
             COALESCE(ds.pendapatan_sum, 0) AS _detail_sum_pendapatan,
             COALESCE(ds.potongan_sum, 0) AS _detail_sum_potongan,
             COALESCE(ds.infaq_sum, 0) AS _detail_sum_infaq
      FROM payroll p
      JOIN pegawai pg ON pg.id = p.pegawai_id
      LEFT JOIN (
        SELECT pd.payroll_id,
               SUM(CASE WHEN k.tipe='pendapatan' THEN pd.nilai ELSE 0 END) AS pendapatan_sum,
               SUM(CASE WHEN k.tipe='potongan' THEN pd.nilai ELSE 0 END) AS potongan_sum,
               SUM(CASE WHEN k.field_key IN ('pot_zakat','pot_zakat_profesi','infaq_sodaqoh') THEN pd.nilai ELSE 0 END) AS infaq_sum
        FROM payroll_detail pd
        JOIN komponen_payroll k ON k.id = pd.komponen_id
        $whereSub
        GROUP BY pd.payroll_id
      ) ds ON ds.payroll_id = p.id
      $where
      ORDER BY p.periode DESC, pg.nama ASC
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log("Dashboard Payroll Error: " . $e->getMessage());
    if ($e->getCode() === '42S02') {
        die('<div class="alert alert-warning m-5">Terjadi masalah konfigurasi sistem, hubungi administrator.</div>');
    }
    throw $e;
}

$totBruto = $totPot = $totInfaq = 0;
foreach ($rows as $r) {
    $totBruto += total_bruto($r);
    $totPot   += total_potongan($r);
    $totInfaq += (float)($r['_detail_sum_infaq'] ?? 0);
}
?>
<div class="d-flex justify-content-between align-items-end flex-wrap gap-3 mb-4">
  <div>
    <h2 class="fw-bold mb-1">Dashboard Payroll</h2>
    <p class="text-muted mb-0">Pantau transaksi gaji, tunjangan dan potongan secara real-time.</p>
  </div>
  <div class="d-flex gap-2 align-items-center flex-wrap">
    <form method="get" class="d-flex gap-2">
      <label for="filterPeriode" class="visually-hidden">Filter Periode</label>
      <select name="periode" id="filterPeriode" class="form-select" onchange="this.form.submit()" style="border-radius:999px">
        <option value="all" <?= $isAll ? 'selected' : '' ?>>Semua Periode</option>
        <?php
        $periods = $pdo->query("SELECT DISTINCT DATE_FORMAT(periode, '%Y-%m') as p FROM payroll ORDER BY p DESC")->fetchAll();
        foreach ($periods as $p) {
            echo '<option value="'.e($p['p']).'" '.($periodeFilter==$p['p'] ? 'selected' : '').'>'.e(periode_label($p['p'].'-01')).'</option>';
        }
        ?>
      </select>
    </form>
    <div class="btn-group-payroll">
      <button type="button" class="btn-payroll btn-export" data-bs-toggle="modal" data-bs-target="#exportModal">
        <i class="bi bi-file-earmark-spreadsheet"></i> Export
      </button>
      <button type="button" class="btn-payroll btn-import" data-bs-toggle="modal" data-bs-target="#importModal">
        <i class="bi bi-upload"></i> Impor
      </button>
      <a href="tambah_gaji" class="btn-payroll btn-tambah">
        <i class="bi bi-plus-circle"></i> Tambah Data
      </a>
    </div>
  </div>
</div>

<div class="row g-3 mb-4">
  <div class="col-md-3 col-sm-6">
    <div class="stat-card">
      <div><div class="label">Total Pegawai</div><div class="value"><?= number_format($totalPegawai,0,',','.') ?></div></div>
      <div class="icon"><i class="bi bi-people-fill"></i></div>
    </div>
  </div>
  <div class="col-md-3 col-sm-6">
    <div class="stat-card green">
      <div><div class="label">Gaji Bersih</div><div class="value"><?= rupiah($totBruto - $totPot) ?></div></div>
      <div class="icon"><i class="bi bi-wallet2"></i></div>
    </div>
  </div>
  <div class="col-md-3 col-sm-6">
    <div class="stat-card red">
      <div><div class="label">Total Potongan</div><div class="value"><?= rupiah($totPot) ?></div></div>
      <div class="icon"><i class="bi bi-receipt"></i></div>
    </div>
  </div>
  <div class="col-md-3 col-sm-6">
    <div class="stat-card yellow">
      <div>      <div class="label">Total Zakat, Infaq dan Shodaqoh</div><div class="value"><?= rupiah($totInfaq) ?></div></div>
      <div class="icon"><i class="bi bi-heart-fill"></i></div>
    </div>
  </div>
</div>

<div class="app-card">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <h5 class="fw-bold mb-0">Rincian Payroll Pegawai (<?= e($isAll ? 'Semua Periode' : periode_label($periodeFilter.'-01')) ?>)</h5>
    <span class="text-primary small fw-semibold">Total: <?= count($rows) ?> data</span>
  </div>

      <table class="table data-table align-middle" data-empty-message="Belum ada data payroll untuk periode ini.<br>Silakan pilih periode yang berbeda atau tambahkan data baru.">
    <thead>
      <tr>
        <th>Nama Pegawai</th><th>Periode</th><th>Bruto</th><th>Potongan</th>
        <th>Take Home Pay</th><th class="text-end">Aksi</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($rows as $r): $b=total_bruto($r); $p=total_potongan($r); ?>
      <tr>
        <td data-label="Nama Pegawai">
          <div class="fw-semibold"><?= e($r['nama']) ?></div>
          <div class="text-muted small"><?= e($r['nip']) ?></div>
        </td>
        <td data-label="Periode"><?= periode_label($r['periode']) ?></td>
        <td class="text-bruto" data-label="Bruto"><?= rupiah($b) ?></td>
        <td class="text-pot" data-label="Potongan"><?= rupiah($p) ?></td>
<td data-label="Take Home Pay"><span class="badge-thp"><?= rupiah($b-$p) ?></span></td>
        <td class="text-end">
          <button class="btn-action-modern" data-bs-toggle="modal" data-bs-target="#detailModal"
                  data-detail-url="detail_gaji?id=<?= $r['id'] ?>" title="Detail">
            <i class="bi bi-eye"></i>
          </button>
          <a class="btn-action-modern" href="edit_gaji?id=<?= $r['id'] ?>" title="Edit"><i class="bi bi-pencil"></i></a>
          <form method="post" action="hapus_gaji" style="display:inline" onsubmit="return confirm('Hapus data payroll ini?')">
            <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
            <input type="hidden" name="id" value="<?= $r['id'] ?>">
            <button type="submit" class="btn-action-modern text-danger" title="Hapus">
              <i class="bi bi-trash"></i>
            </button>
          </form>
        </td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>

<!-- Broadcast Notification Modal -->
<?php require __DIR__ . '/../includes/broadcast_modal.php'; ?>

<!-- MODALS (Sama) -->
<?php require __DIR__ . '/_dashboard_modals.php'; ?>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>

<script>
document.addEventListener('DOMContentLoaded', function() {
  var bcModal = document.getElementById('broadcastModal');
  if (bcModal) {
    var bsModal = bootstrap.Modal.getOrCreateInstance(bcModal);
    setTimeout(function() { bsModal.show(); }, 500);
  }
});
</script>
