<?php
$pageTitle = 'Dashboard Pegawai';
$activeNav = 'dashboard';
require_once __DIR__ . '/../includes/header_pegawai.php';

$u = current_user();
$pid = (int)$u['pegawai_id'];
$peg = $pdo->prepare("SELECT * FROM pegawai WHERE id=?"); $peg->execute([$pid]); $peg = $peg->fetch();

try {
    $slips = $pdo->prepare("
      SELECT p.*,
             COALESCE(ds.pendapatan_sum, 0) AS _detail_sum_pendapatan,
             COALESCE(ds.potongan_sum, 0) AS _detail_sum_potongan,
             COALESCE(ds.infaq_sum, 0) AS _detail_sum_infaq
      FROM payroll p
      LEFT JOIN (
        SELECT pd.payroll_id,
               SUM(CASE WHEN k.tipe='pendapatan' THEN pd.nilai ELSE 0 END) AS pendapatan_sum,
               SUM(CASE WHEN k.tipe='potongan' THEN pd.nilai ELSE 0 END) AS potongan_sum,
               SUM(CASE WHEN k.field_key IN ('pot_zakat','pot_zakat_profesi','infaq_sodaqoh') THEN pd.nilai ELSE 0 END) AS infaq_sum
        FROM payroll_detail pd
        JOIN komponen_payroll k ON k.id = pd.komponen_id
        GROUP BY pd.payroll_id
      ) ds ON ds.payroll_id = p.id
      WHERE p.pegawai_id=?
      ORDER BY p.periode DESC
    ");
    $slips->execute([$pid]); $slips = $slips->fetchAll();
} catch (PDOException $e) {
    error_log("Pegawai Dashboard Error: " . $e->getMessage());
    if ($e->getCode() === '42S02') {
        die('<div class="alert alert-warning m-5">Terjadi masalah konfigurasi sistem, hubungi administrator.</div>');
    }
    throw $e;
}

$totBruto = $totPot = $totInfaq = 0;
foreach ($slips as $s) {
    $totBruto += total_bruto($s);
    $totPot   += total_potongan($s);
    $totInfaq += (float)($s['_detail_sum_infaq'] ?? 0);
}
$totPengeluaranNonZakat = $totPot - $totInfaq;

// Data Tren Take Home Pay
$trendSlips = array_slice($slips, 0, 6);
$trendValues = array_reverse(array_map(fn($s) => (float)take_home($s), $trendSlips));
$sCount = count($trendValues);
$trendPct = null;
if ($sCount >= 2) {
    $last = $trendValues[$sCount - 1];
    $prev = $trendValues[$sCount - 2];
    $trendPct = ($prev != 0) ? (($last - $prev) / $prev) * 100 : 0;
}

// Flash messages
$flashMsg = '';
$flashType = '';
$confirm = $_GET['confirm'] ?? '';
$pwd = $_GET['pwd'] ?? '';

if ($confirm === 'ok') {
    $flashMsg = 'Slip berhasil dikonfirmasi.';
    $flashType = 'success';
} elseif ($confirm === 'fail') {
    $flashMsg = 'Gagal mengkonfirmasi slip. Silakan coba lagi.';
    $flashType = 'danger';
} elseif ($pwd === 'ok') {
    $flashMsg = 'Password berhasil diubah.';
    $flashType = 'success';
} elseif ($pwd === 'fail_format') {
    $flashMsg = 'Password baru harus minimal 8 karakter dan mengandung huruf serta angka.';
    $flashType = 'danger';
} elseif ($pwd === 'fail_confirm') {
    $flashMsg = 'Konfirmasi password baru tidak cocok.';
    $flashType = 'danger';
} elseif ($pwd === 'fail_old') {
    $flashMsg = 'Password lama salah.';
    $flashType = 'danger';
}
?>
<?php if ($flashMsg): ?>
<div class="alert alert-<?= $flashType ?> alert-dismissible fade show" role="alert">
  <?= e($flashMsg) ?>
  <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Tutup"></button>
</div>
<?php endif; ?>
<div class="d-flex justify-content-between align-items-end flex-wrap gap-3 mb-4">
  <div>
    <h2 class="fw-bold mb-1">Dashboard Payroll Saya</h2>
    <p class="text-muted mb-0">Kelola dan lihat histori gaji setiap periode.</p>
  </div>
   <div class="d-flex gap-2 align-items-center">
     <button class="btn-secondary-modern" data-bs-toggle="modal" data-bs-target="#pwdModal"><i class="bi bi-key"></i> Ubah Password</button>
   </div>
</div>

<div class="mb-3">
  <p class="text-muted mb-0 small">Selamat Datang, <strong><?= e(strtoupper($peg['nama'])) ?></strong> — NIP: <?= e($peg['nip']) ?> | Golongan: <?= e($peg['golongan']) ?></p>
</div>

<div class="row g-3 mb-4">
  <div class="col-md-3">
    <div class="stat-card green"><div><div class="label">Total Pendapatan</div><div class="value text-success"><?= rupiah($totBruto) ?></div></div><div class="icon"><i class="bi bi-cash-stack"></i></div></div>
  </div>
  <div class="col-md-3">
    <div class="stat-card red"><div><div class="label">Total Pengeluaran</div><div class="value text-danger"><?= rupiah($totPengeluaranNonZakat) ?></div></div><div class="icon"><i class="bi bi-arrow-down-circle"></i></div></div>
  </div>
  <div class="col-md-3">
    <div class="stat-card yellow"><div><div class="label">Total Zakat, Infaq dan Shodaqoh</div><div class="value" style="color:var(--warning)"><?= rupiah($totInfaq) ?></div></div><div class="icon"><i class="bi bi-heart"></i></div></div>
  </div>
  <div class="col-md-3">
    <div class="tren-card" style="height:100%">
      <div class="label">Tren Take Home Pay</div>
      <div class="trend-content d-flex flex-column align-items-center justify-content-center gap-1">
        <?php if ($sCount < 2): ?>
          <div class="text-white-50 small">Data belum cukup untuk tren</div>
        <?php else: 
          $min = min($trendValues);
          $max = max($trendValues);
          $range = $max - $min;
          $pts = [];
          foreach ($trendValues as $i => $v) {
              $x = $i * (100 / ($sCount - 1));
              $y = 25 - (($v - $min) / ($range ?: 1) * 20);
              $pts[] = (float)$x . ',' . (float)$y;
          }
          $path = implode(' ', $pts);
          $lastPt = end($pts);
          $lastX = explode(',', $lastPt)[0];
          $lastY = explode(',', $lastPt)[1];
        ?>
          <svg viewBox="0 0 100 30" style="width:100%; height:40px; overflow:visible" class="trend-svg">
            <polyline fill="none" stroke="rgba(255,255,255,.6)" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" points="<?= $path ?>" />
            <polygon points="<?= (float)$lastX-3.5 ?>,<?= (float)$lastY+3 ?> <?= (float)$lastX+3.5 ?>,<?= (float)$lastY+3 ?> <?= (float)$lastX ?>,<?= (float)$lastY-4.5 ?>" fill="rgba(255,255,255,.6)" />
          </svg>
          <div class="trend-pct small fw-medium" style="color: <?= $trendPct >= 0 ? '#86efac' : '#fde047' ?>">
            <?= ($trendPct >= 0 ? '+' : '') . number_format($trendPct, 1) ?>% dari bulan lalu
          </div>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>

<div class="app-card" style="overflow:hidden">
  <div style="background:var(--primary-soft);padding:16px 20px;border-bottom:1px solid var(--border)">
    <h5 class="fw-bold mb-0"><i class="bi bi-clock-history text-primary"></i> Riwayat Slip Payroll</h5>
  </div>
  <div style="padding:16px 20px">
  <table class="table data-table align-middle" style="margin:0" data-empty-message="Belum ada slip gaji." data-order="[]">
    <thead><tr><th>Bulan/Tahun</th><th>Total Pendapatan</th><th>Total Pengeluaran</th><th>Gaji Bersih (THP)</th><th>Status</th><th>Opsi</th></tr></thead>
    <tbody>
      <?php foreach($slips as $s): ?>
      <tr style="transition:background 0.15s" onmouseover="this.style.background='#eef2ff'" onmouseout="this.style.background=''">
        <td data-label="Bulan/Tahun" data-order="<?= $s['periode'] ?>"><?= periode_label($s['periode']) ?></td>
        <td class="text-success fw-semibold" data-label="Total Pendapatan"><?= rupiah(total_bruto($s)) ?></td>
        <td class="text-danger" data-label="Total Pengeluaran"><?= rupiah(total_potongan($s)) ?></td>
        <td class="text-primary fw-bold" data-label="Gaji Bersih (THP)"><?= rupiah(take_home($s)) ?></td>
        <td data-label="Status"><?= $s['status_terima'] ? '<span class="badge bg-success">✔ Diterima</span>' : '<span class="badge bg-warning text-dark">● Belum</span>' ?></td>
        <td>
            <button class="btn-action-sm" data-bs-toggle="modal" data-bs-target="#slipModal" data-url="detail_slip?id=<?= $s['id'] ?>" title="Detail Slip">
              <i class="bi bi-eye"></i>
            </button>
            <a href="cetak_slip?id=<?= $s['id'] ?>" target="_blank" class="btn-action-sm" title="Cetak PDF">
              <i class="bi bi-printer"></i>
            </a>
        </td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
  </div>
</div>

<!-- Broadcast Notification Modal -->
<?php require __DIR__ . '/../includes/broadcast_modal.php'; ?>

<!-- Modal Slip -->
<div class="modal fade" id="slipModal" tabindex="-1" aria-label="Detail Slip Gaji"><div class="modal-dialog modal-lg modal-dialog-centered"><div class="modal-content">
  <div class="modal-body p-4" id="slipBody">Memuat…</div>
</div></div></div>

<!-- Modal Ubah Password -->
<div class="modal fade" id="pwdModal" tabindex="-1" aria-label="Ubah Password"><div class="modal-dialog modal-dialog-centered"><div class="modal-content modal-modern">
  <form method="post" action="ubah_password" data-validate novalidate>
    <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
    <div class="modal-header"><h5 class="modal-title fw-bold"><i class="bi bi-shield-lock text-warning"></i> Keamanan Akun</h5><button class="btn-close" data-bs-dismiss="modal"></button></div>
    <div class="modal-body">
      <div class="mb-3"><label for="pwdOld" class="form-label small text-uppercase fw-semibold">Password Lama</label><input type="password" id="pwdOld" name="old" class="form-control" required></div>
      <div class="mb-3"><label for="pwdNew" class="form-label small text-uppercase fw-semibold">Password Baru</label><input type="password" id="pwdNew" name="new" class="form-control" minlength="8" required></div>
      <div class="mb-3"><label for="pwdConfirm" class="form-label small text-uppercase fw-semibold">Konfirmasi Password Baru</label><input type="password" id="pwdConfirm" name="confirm" class="form-control" minlength="8" required></div>
    </div>
    <div class="modal-footer border-0 d-flex gap-2">
      <button type="button" class="btn-secondary-modern" data-bs-dismiss="modal">Batal</button>
      <button class="btn-primary-modern"><i class="bi bi-check-circle"></i> SIMPAN PERUBAHAN</button>
    </div>
  </form>
</div></div></div>

<script>
document.getElementById('slipModal').addEventListener('show.bs.modal', async ev => {
  const url = ev.relatedTarget.dataset.url;
  const body = document.getElementById('slipBody');
  body.innerHTML = 'Memuat…';
  body.innerHTML = await (await fetch(url)).text();
});

document.addEventListener('DOMContentLoaded', function() {
  var bcModal = document.getElementById('broadcastModal');
  if (bcModal) {
    var bsModal = bootstrap.Modal.getOrCreateInstance(bcModal);
    setTimeout(function() { bsModal.show(); }, 500);
  }
});
</script>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
