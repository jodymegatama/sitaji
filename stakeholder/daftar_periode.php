<?php
$pageTitle = 'Daftar Periode Dana';
$activeNav = 'periode';
require_once __DIR__ . '/../includes/header_stakeholder.php';

$pkId = $stakeholder['id'];

// ── Saldo awal carry-over dari periode broadcast terakhir ──
$saldoAwalCarryOver = 0;
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
    $saldoAwalCarryOver = (float)$lastRow['total_pemasukan'] - (float)$lastRow['total_pemanfaatan'];
}
if ($saldoAwalCarryOver < 0) $saldoAwalCarryOver = 0;

// ── Komponen mapped ──
$komponenMapped = $pdo->prepare("
    SELECT kp.id, kp.nama, kp.field_key
    FROM pemangku_kepentingan_komponen pkc
    JOIN komponen_payroll kp ON kp.id = pkc.komponen_id
    WHERE pkc.pemangku_kepentingan_id = ?
");
$komponenMapped->execute([$pkId]);
$komponenMapped = $komponenMapped->fetchAll();

// ── Filter dari/sampai ──
$dari = $_GET['dari'] ?? '';
$sampai = $_GET['sampai'] ?? '';
$filterAktif = $dari !== '' && $sampai !== '';
$rangeSql = '';
$rangeParams = [];
$filterTeks = '';
if ($filterAktif) {
    if (preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $dari) && preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $sampai)) {
        $dP = explode('-', $dari); $dariTahun = (int)$dP[0]; $dariBulan = (int)$dP[1];
        $sP = explode('-', $sampai); $sampaiTahun = (int)$sP[0]; $sampaiBulan = (int)$sP[1];
        if ($sampaiTahun > $dariTahun || ($sampaiTahun === $dariTahun && $sampaiBulan >= $dariBulan)) {
            $rangeSql = " AND (dp.tahun > ? OR (dp.tahun = ? AND dp.bulan >= ?))
                           AND (dp.tahun < ? OR (dp.tahun = ? AND dp.bulan <= ?))";
            $rangeParams = [$dariTahun, $dariTahun, $dariBulan, $sampaiTahun, $sampaiTahun, $sampaiBulan];
        } else {
            $filterAktif = false;
        }
    } else {
        $filterAktif = false;
    }
}

$params = [$pkId];
if ($filterAktif) {
    $params = array_merge([$pkId], $rangeParams);
}

// Hitung total untuk header (tanpa LIMIT)
$totalQ = $pdo->prepare("SELECT COUNT(*) FROM dana_periode dp WHERE dp.pemangku_kepentingan_id = ? $rangeSql");
$totalQ->execute($params);
$total = (int)$totalQ->fetchColumn();

// Ambil periode dengan LIMIT untuk mencegah pengambilan semua data
$list = $pdo->prepare("
    SELECT dp.*,
           COALESCE(SUM(dm.nominal), 0) AS total_pemanfaatan
    FROM dana_periode dp
    LEFT JOIN dana_pemanfaatan dm ON dm.periode_id = dp.id
    WHERE dp.pemangku_kepentingan_id = ?
    $rangeSql
    GROUP BY dp.id
    ORDER BY dp.tahun DESC, dp.bulan DESC
    LIMIT 60
");
$list->execute($params);
$list = $list->fetchAll();

// Flash messages — session-based
$flashMsg = '';
$flashType = '';
if (isset($_SESSION['flash_message'])) {
    $flashMsg = $_SESSION['flash_message'];
    $flashType = $_SESSION['flash_type'] ?? 'success';
    unset($_SESSION['flash_message'], $_SESSION['flash_type']);
}
$error = $_GET['error'] ?? '';
$success = $_GET['success'] ?? '';
if (!$flashMsg) {
    if ($error === 'incomplete') { $flashMsg = 'Data tidak lengkap. Bulan, tahun, dan total pemasukan wajib diisi.'; $flashType = 'danger'; }
    elseif ($error === 'nominal_invalid') { $flashMsg = 'Total nominal harus lebih dari 0.'; $flashType = 'danger'; }
    elseif ($error === 'pemanfaatan_over') { $flashMsg = 'Total pemanfaatan melebihi total pemasukan.'; $flashType = 'danger'; }
    elseif ($error === 'duplicate_periode') { $flashMsg = 'Periode bulan/tahun yang sama sudah ada. Pilih bulan atau tahun yang berbeda.'; $flashType = 'danger'; }
    elseif ($error === 'not_found') { $flashMsg = 'Data periode tidak ditemukan.'; $flashType = 'danger'; }
    elseif ($error === 'not_draft') { $flashMsg = 'Periode yang sudah di-broadcast tidak dapat diedit langsung. Gunakan fitur Revisi.'; $flashType = 'warning'; }
    elseif ($error === 'db') { $flashMsg = 'Terjadi kesalahan database. Silakan coba lagi.'; $flashType = 'danger'; }
    elseif ($error === 'rincian_empty') { $flashMsg = 'Tambahkan minimal 1 rincian pemanfaatan dana.'; $flashType = 'danger'; }
    elseif ($success === 'created') { $flashMsg = 'Periode berhasil ditambahkan.'; $flashType = 'success'; }
    elseif ($success === 'updated') { $flashMsg = 'Periode berhasil diperbarui.'; $flashType = 'success'; }
    elseif ($success === 'broadcast') { $flashMsg = 'Periode berhasil di-broadcast ke pegawai dan admin.'; $flashType = 'success'; }
    elseif ($success === 'revisi_submitted') { $flashMsg = 'Revisi berhasil diajukan. Menunggu persetujuan admin.'; $flashType = 'info'; }
    elseif ($success === 'hapus_submitted') { $flashMsg = 'Penghapusan berhasil diajukan. Menunggu persetujuan admin.'; $flashType = 'info'; }
}

$bulanNama = [
    1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
    5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
    9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember'
];
$statusBadge = [
    'draft' => 'bg-secondary',
    'broadcast' => 'bg-success',
    'revisi_pending' => 'bg-warning text-dark',
    'hapus_pending' => 'bg-danger',
];
$bulanSingkat = [
    1 => 'Jan', 2 => 'Feb', 3 => 'Mar', 4 => 'Apr', 5 => 'Mei', 6 => 'Jun',
    7 => 'Jul', 8 => 'Agu', 9 => 'Sep', 10 => 'Okt', 11 => 'Nov', 12 => 'Des'
];
if ($filterAktif) {
    $filterTeks = ($dariBulan === $sampaiBulan && $dariTahun === $sampaiTahun)
        ? $bulanSingkat[$dariBulan] . ' ' . $dariTahun
        : $bulanSingkat[$dariBulan] . ' ' . $dariTahun . '–' . $bulanSingkat[$sampaiBulan] . ' ' . $sampaiTahun;
}
?>
<?php if ($flashMsg): ?>
<div class="alert alert-<?= $flashType ?> alert-dismissible fade show" role="alert">
  <?= e($flashMsg) ?>
  <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Tutup"></button>
</div>
<?php endif; ?>

<div class="mb-4">
  <h2 class="fw-bold mb-1">Daftar Periode Dana</h2>
  <p class="text-muted mb-0">Kelola laporan pemasukan dan pemanfaatan dana per periode bulanan.</p>
</div>

<div class="app-card mb-3">
  <div class="row g-3 align-items-center">
    <div class="col-md-6">
      <label class="form-label fw-semibold small text-uppercase text-muted">Rentang Bulan</label>
      <input type="text" class="form-control" id="rangePicker" placeholder="Klik untuk pilih rentang bulan..." value="">
      <div class="d-flex align-items-center gap-2 flex-wrap mt-1">
        <small class="text-muted">Pilih 2 bulan untuk menentukan rentang</small>
        <?php if ($filterAktif): ?>
        <a href="daftar_periode" class="badge rounded-pill text-decoration-none px-3 py-1 small" style="background:var(--primary-soft);color:var(--primary);"><i class="bi bi-x-lg me-1"></i>Tampilkan Semua</a>
        <?php endif; ?>
      </div>
    </div>
    <div class="col-md-6">
      <div class="d-flex gap-2 justify-content-md-end flex-wrap">
        <form method="post" action="laporan" id="exportForm" class="d-inline">
          <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
          <input type="hidden" name="dari" value="<?= e($dari) ?>" id="exportDari">
          <input type="hidden" name="sampai" value="<?= e($sampai) ?>" id="exportSampai">
          <button class="btn-success-modern" id="exportBtn">
            <i class="bi bi-file-earmark-spreadsheet"></i>
            <?= $filterAktif ? 'Export Periode ' . $filterTeks : 'Export Semua Periode' ?>
          </button>
        </form>
        <button type="button" class="btn-primary-modern" data-bs-toggle="modal" data-bs-target="#tambahPeriodeModal">
          <i class="bi bi-plus-circle"></i> Tambah Periode
        </button>
      </div>
    </div>
  </div>
</div>

<div class="app-card p-0">
  <div class="d-flex justify-content-between align-items-center px-4 pt-4 pb-3">
    <h5 class="fw-bold mb-0">Riwayat Periode</h5>
    <span class="text-primary small fw-semibold">Total: <?= $total ?> periode</span>
  </div>
  <div class="table-responsive">
    <table class="table table-periode data-table align-middle mb-0" data-empty-message="Belum ada data periode.">
      <thead>
        <tr>
          <th class="ps-4">Periode</th>
          <th>Total Pemasukan</th>
          <th>Total Pemanfaatan</th>
          <th>Sisa Saldo</th>
          <th>Status</th>
          <th class="text-end pe-4">Aksi</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($list as $row): ?>
          <?php
          $saldoAwal = (float)($row['saldo_awal'] ?? 0);
          $pemasukan = (float)$row['total_pemasukan'];
          $pemanfaatan = (float)$row['total_pemanfaatan'];
          $sisa = $saldoAwal + $pemasukan - $pemanfaatan;
          ?>
          <tr>
            <td class="ps-4" data-label="Periode">
              <div class="d-flex align-items-center gap-2">
                <span class="d-inline-flex align-items-center justify-content-center rounded-2 flex-shrink-0" style="width:36px;height:36px;background:var(--primary-soft);color:var(--primary);">
                  <i class="bi bi-calendar3"></i>
                </span>
                <span class="fw-semibold"><?= $bulanNama[(int)$row['bulan']] ?> <?= $row['tahun'] ?></span>
              </div>
            </td>
            <td class="num fw-medium" data-label="Pemasukan"><?= rupiah($pemasukan) ?></td>
            <td class="num fw-medium" data-label="Pemanfaatan"><?= rupiah($pemanfaatan) ?></td>
            <td class="num fw-bold <?= $sisa >= 0 ? 'text-success' : 'text-danger' ?>" data-label="Sisa Saldo"><?= rupiah($sisa) ?></td>
            <td data-label="Status">
              <span class="badge rounded-pill <?= $statusBadge[$row['status']] ?? 'bg-secondary' ?> d-inline-flex align-items-center gap-1 py-1 px-3">
                <span class="rounded-circle d-inline-block" style="width:6px;height:6px;background:currentColor;"></span>
                <?= ucfirst(str_replace('_', ' ', $row['status'])) ?>
              </span>
            </td>
            <td class="text-end pe-4" data-label="Aksi">
              <a class="btn-action-modern" href="<?= base_url('stakeholder/detail_periode') ?>?id=<?= $row['id'] ?>" title="Detail"><i class="bi bi-eye"></i></a>
              <?php if ($row['status'] === 'draft'): ?>
              <a class="btn-action-modern" href="edit_periode?id=<?= $row['id'] ?>" title="Edit"><i class="bi bi-pencil"></i></a>
              <button type="button" class="btn-action-modern text-danger" title="Hapus"
                data-bs-toggle="modal" data-bs-target="#hapusPeriodeModal"
                data-id="<?= $row['id'] ?>"
                data-periode="<?= $bulanNama[(int)$row['bulan']] ?> <?= $row['tahun'] ?>">
                <i class="bi bi-trash"></i>
              </button>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- ── MODAL HAPUS PERIODE ── -->
<div class="modal fade" id="hapusPeriodeModal" tabindex="-1" aria-label="Hapus Periode"><div class="modal-dialog modal-dialog-centered"><div class="modal-content modal-modern">
  <form method="post" action="hapus_periode">
    <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
    <input type="hidden" name="id" id="hapusId" value="">
    <div class="modal-header">
      <h5 class="modal-title fw-bold">Hapus Periode</h5>
      <button class="btn-close" data-bs-dismiss="modal"></button>
    </div>
    <div class="modal-body">
      <p>Yakin ingin menghapus periode <strong id="hapusPeriodeNama"></strong>? Data yang sudah dihapus tidak bisa dikembalikan.</p>
    </div>
    <div class="modal-footer border-0 d-flex gap-2">
      <button type="button" class="btn-secondary-modern" data-bs-dismiss="modal">Batal</button>
      <button class="btn-danger-modern" type="submit"><i class="bi bi-trash"></i> Hapus</button>
    </div>
  </form>
</div></div></div>

<!-- ── MODAL TAMBAH PERIODE ── -->
<div class="modal fade" id="tambahPeriodeModal" tabindex="-1" aria-label="Tambah Periode Dana"><div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable"><div class="modal-content border-0 shadow-lg" style="border-radius:1rem;overflow:hidden;">
  <form method="post" action="tambah_periode" data-validate novalidate id="formTambahPeriode" enctype="multipart/form-data">
    <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
    <div class="modal-header px-4 py-3 bg-light border-bottom">
      <div class="d-flex align-items-center gap-2">
        <span class="d-inline-flex align-items-center justify-content-center rounded-circle" style="width:38px;height:38px;background:var(--primary-soft);color:var(--primary);">
          <i class="bi bi-calendar-plus fs-5"></i>
        </span>
        <div>
          <h5 class="modal-title fw-bold mb-0">Tambah Periode Dana</h5>
          <small class="text-muted">Masukkan data pemasukan dan rincian pemanfaatan bulanan</small>
        </div>
      </div>
      <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
    </div>
    <div class="modal-body p-4 bg-white">
      
      <!-- Card Section 1: Info Dasar -->
      <div class="p-3 rounded-4 mb-4" style="background:#f8fafc;border:1px solid #e2e8f0;">
        <h6 class="fw-bold text-dark small text-uppercase tracking-wider mb-3"><i class="bi bi-info-circle me-1 text-primary"></i> Periode & Saldo</h6>
        <div class="row g-3">
          <div class="col-md-4">
            <label for="tpBulan" class="form-label fw-semibold small text-muted">Bulan</label>
            <select id="tpBulan" class="form-select bg-white" name="bulan" required>
              <option value="">-- Pilih Bulan --</option>
              <?php for ($m = 1; $m <= 12; $m++): ?>
                <option value="<?= $m ?>"><?= date('F', mktime(0,0,0,$m,1)) ?></option>
              <?php endfor; ?>
            </select>
          </div>
          <div class="col-md-4">
            <label for="tpTahun" class="form-label fw-semibold small text-muted">Tahun</label>
            <select id="tpTahun" class="form-select bg-white" name="tahun" required>
              <option value="">-- Pilih Tahun --</option>
              <?php for ($y = date('Y'); $y >= 2020; $y--): ?>
                <option value="<?= $y ?>"><?= $y ?></option>
              <?php endfor; ?>
            </select>
          </div>
          <div class="col-md-4">
            <label for="modalTotalPemasukan" class="form-label fw-semibold small text-muted">Total Pemasukan (Rp)</label>
            <input type="number" class="form-control bg-white fw-semibold" name="total_pemasukan" id="modalTotalPemasukan" placeholder="0" min="0" max="9999999999999" step="1000" required>
          </div>
        </div>
        <div class="row g-3 mt-1">
          <div class="col-md-6">
            <label for="modalSaldoAwalDisplay" class="form-label fw-semibold small text-muted">Saldo Awal (Carry-over)</label>
            <input type="text" class="form-control bg-light text-muted fw-semibold" id="modalSaldoAwalDisplay" value="<?= rupiah($saldoAwalCarryOver) ?>" readonly>
            <input type="hidden" name="saldo_awal" id="modalSaldoAwal" value="<?= $saldoAwalCarryOver ?>">
            <div class="form-text text-muted small mt-1">Sisa saldo dari periode broadcast sebelumnya secara otomatis.</div>
          </div>
          <?php if (!empty($komponenMapped)): ?>
          <div class="col-md-6 d-flex align-items-center">
            <div class="p-2 px-3 rounded-3 w-100" style="background:#eff6ff;border:1px.solid #bfdbfe;color:#1e40af;font-size:0.825rem;">
              <i class="bi bi-shield-check me-1"></i> <strong>Komponen terkait:</strong>
              <?= implode(', ', array_map(fn($k) => e($k['nama']), $komponenMapped)) ?>
            </div>
          </div>
          <?php endif; ?>
        </div>
      </div>

      <!-- Card Section 2: Rincian Pemanfaatan -->
      <div class="p-3 rounded-4 mb-4" style="background:#f8fafc;border:1px solid #e2e8f0;">
        <div class="d-flex justify-content-between align-items-center mb-3">
          <h6 class="fw-bold text-dark small text-uppercase tracking-wider mb-0"><i class="bi bi-list-check me-1 text-primary"></i> Rincian Pemanfaatan Dana</h6>
          <button type="button" class="btn btn-outline-primary btn-sm rounded-pill px-3 fw-semibold shadow-none" id="modalAddRow" style="font-size:0.8rem;">
            <i class="bi bi-plus-circle me-1"></i> Tambah Baris
          </button>
        </div>
        
        <div id="modalRincianContainer" class="d-flex flex-column gap-2">
          <div class="rincian-row row g-2 align-items-center p-2 rounded-3 bg-white border">
            <div class="col-md-5">
              <input class="form-control form-control-sm border-0 bg-transparent shadow-none" name="rincian_keterangan[]" placeholder="Keterangan..." required>
            </div>
            <div class="col-md-3">
              <input type="number" class="form-control form-control-sm modal-rincian-nominal border-0 bg-transparent shadow-none fw-medium" name="rincian_nominal[]" placeholder="Nominal (Rp)..." min="0" max="9999999999999" step="1000" required>
            </div>
            <div class="col-md-3">
              <label class="btn btn-sm btn-outline-primary w-100 mb-0 lampiran-label" style="border-radius:0.5rem;font-size:0.78rem;background:var(--primary-soft,#eef2ff);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">
                <i class="bi bi-file-earmark-pdf me-1"></i><span class="lampiran-text">PDF</span>
                <input type="file" class="d-none" name="rincian_lampiran[]" accept="application/pdf" title="Lampiran PDF (opsional)">
              </label>
            </div>
            <div class="col-md-1 text-end">
              <button type="button" class="btn btn-link text-danger p-0 modal-remove-row" title="Hapus baris"><i class="bi bi-trash fs-6"></i></button>
            </div>
          </div>
        </div>
      </div>

      <!-- Card Section 3: Summary / Kalkulasi -->
      <div class="p-3 rounded-4 text-white" style="background:linear-gradient(135deg, #0f172a 0%, #1e293b 100%);box-shadow:0 10px 15px -3px rgba(15, 23, 42, 0.2);">
        <div class="row align-items-center">
          <div class="col-md-6 border-end border-secondary border-opacity-25">
            <div class="text-white-55 small uppercase tracking-wider mb-1">Total Pemanfaatan</div>
            <div class="fs-4 fw-bold font-monospace" id="modalTotalPemanfaatanDisplay">Rp 0</div>
          </div>
          <div class="col-md-6 ps-md-4">
            <div class="text-white-55 small uppercase tracking-wider mb-1">Sisa Saldo Akhir</div>
            <div class="fs-4 fw-bold font-monospace text-info" id="modalSisaSaldoDisplay">Rp 0</div>
          </div>
        </div>
        <div class="alert alert-warning border-0 small mt-3 mb-0 py-2 px-3 rounded-3 text-dark bg-warning bg-opacity-75" id="modalSaldoAlert" style="display:none">
          <i class="bi bi-exclamation-triangle-fill me-1"></i> Total pemanfaatan melebihi total saldo yang tersedia!
        </div>
      </div>

    </div>
    <div class="modal-footer px-4 py-3 bg-light border-top d-flex gap-2">
      <button type="button" class="btn-secondary-modern px-4" data-bs-dismiss="modal">Batal</button>
      <button class="btn-primary-modern px-4" type="submit"><i class="bi bi-check-circle me-1"></i> Simpan Periode</button>
    </div>
  </form>
</div></div></div>

<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/plugins/monthSelect/style.css">
<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
<script src="https://cdn.jsdelivr.net/npm/flatpickr/dist/plugins/monthSelect/index.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    flatpickr('#rangePicker', {
        plugins: [new monthSelectPlugin({
            shorthand: true,
            dateFormat: 'Y-m',
            altFormat: 'F Y'
        })],
        mode: 'range',
        <?php if ($filterAktif): ?>
        defaultDate: [<?= json_encode($dari) ?>, <?= json_encode($sampai) ?>],
        <?php endif; ?>
        onChange: function(selectedDates, dateStr, instance) {
            if (selectedDates.length === 2) {
                var fmt = function(d) {
                    return d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0');
                };
                window.location.href = 'daftar_periode?dari=' + fmt(selectedDates[0]) + '&sampai=' + fmt(selectedDates[1]);
            }
        }
    });

    // ── Modal Tambah Periode live recalc ──
    (function() {
        var container = document.getElementById('modalRincianContainer');
        var addBtn = document.getElementById('modalAddRow');
        var pemasukanInput = document.getElementById('modalTotalPemasukan');
        var totalPemDisplay = document.getElementById('modalTotalPemanfaatanDisplay');
        var sisaDisplay = document.getElementById('modalSisaSaldoDisplay');
        var alertEl = document.getElementById('modalSaldoAlert');

        function recalc() {
            var pemasukan = parseFloat(pemasukanInput.value) || 0;
            var saldoAwal = parseFloat(document.getElementById('modalSaldoAwal').value) || 0;
            var totalPem = 0;
            container.querySelectorAll('.modal-rincian-nominal').forEach(function(el) {
                totalPem += parseFloat(el.value) || 0;
            });
            totalPemDisplay.textContent = 'Rp ' + totalPem.toLocaleString('id-ID');
            var sisa = saldoAwal + pemasukan - totalPem;
            sisaDisplay.textContent = 'Rp ' + sisa.toLocaleString('id-ID');
            sisaDisplay.className = 'fs-4 fw-bold font-monospace ' + (sisa >= 0 ? 'text-info' : 'text-danger');
            alertEl.style.display = sisa < 0 ? '' : 'none';
        }

        addBtn.addEventListener('click', function() {
            var row = document.createElement('div');
            row.className = 'rincian-row row g-2 align-items-center p-2 rounded-3 bg-white border';
            row.innerHTML = '<div class="col-md-5"><input class="form-control form-control-sm border-0 bg-transparent shadow-none" name="rincian_keterangan[]" placeholder="Keterangan..." required></div><div class="col-md-3"><input type="number" class="form-control form-control-sm modal-rincian-nominal border-0 bg-transparent shadow-none fw-medium" name="rincian_nominal[]" placeholder="0" min="0" max="9999999999999" step="1000" required></div><div class="col-md-3"><label class="btn btn-sm btn-outline-primary w-100 mb-0 lampiran-label" style="border-radius:0.5rem;font-size:0.78rem;background:var(--primary-soft,#eef2ff);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;"><i class="bi bi-file-earmark-pdf me-1"></i><span class="lampiran-text">PDF</span><input type="file" class="d-none" name="rincian_lampiran[]" accept="application/pdf" title="Lampiran PDF (opsional)"></label></div><div class="col-md-1 text-end"><button type="button" class="btn btn-link text-danger p-0 modal-remove-row" title="Hapus baris"><i class="bi bi-trash fs-6"></i></button></div>';
            container.appendChild(row);
            row.querySelectorAll('input[type=file]').forEach(function(inp) {
                inp.addEventListener('change', function() {
                    var span = inp.closest('.lampiran-label').querySelector('.lampiran-text');
                    span.textContent = inp.files.length ? inp.files[0].name : 'PDF';
                });
            });
            recalc();
        });

        container.addEventListener('click', function(e) {
            var btn = e.target.closest('.modal-remove-row');
            if (btn) {
                btn.closest('.rincian-row').remove();
                recalc();
            }
        });

        container.addEventListener('input', function(e) {
            if (e.target.matches('.modal-rincian-nominal')) {
                recalc();
            }
        });

        pemasukanInput.addEventListener('input', recalc);

        // Lampiran label: tampilkan nama file saat dipilih (baris statis pertama)
        container.querySelectorAll('input[type=file]').forEach(function(inp) {
            inp.addEventListener('change', function() {
                var span = inp.closest('.lampiran-label').querySelector('.lampiran-text');
                span.textContent = inp.files.length ? inp.files[0].name : 'PDF';
            });
        });

        document.getElementById('tambahPeriodeModal').addEventListener('shown.bs.modal', recalc);
        recalc();

        // Auto-open modal jika ada ?modal=tambah
        if (window.location.search.indexOf('modal=tambah') !== -1) {
            var myModal = new bootstrap.Modal(document.getElementById('tambahPeriodeModal'));
            myModal.show();
            if (window.history.replaceState) {
                var url = window.location.pathname + window.location.search.replace(/[?&]modal=tambah[^&]*/g, '').replace(/[?&]$/, '');
                window.history.replaceState({}, '', url);
            }
        }
    })();

    // ── Modal Hapus Periode ──
    document.getElementById('hapusPeriodeModal').addEventListener('show.bs.modal', function (event) {
        var btn = event.relatedTarget;
        document.getElementById('hapusId').value = btn.getAttribute('data-id');
        document.getElementById('hapusPeriodeNama').textContent = btn.getAttribute('data-periode');
    });
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
