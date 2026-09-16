<?php
$pageTitle = 'Detail Periode Dana';
$activeNav = 'periode';
require_once __DIR__ . '/../includes/header_stakeholder.php';

$pkId = $stakeholder['id'];
$id = (int)($_GET['id'] ?? 0);

// Ambil data periode (hanya milik stakeholder ini)
$stmt = $pdo->prepare("SELECT * FROM dana_periode WHERE id = ? AND pemangku_kepentingan_id = ?");
$stmt->execute([$id, $pkId]);
$periode = $stmt->fetch();
if (!$periode) {
    echo '<div class="alert alert-danger">Periode tidak ditemukan.</div>';
    require __DIR__ . '/../includes/footer.php';
    exit;
}

// Ambil rincian pemanfaatan
$rincian = $pdo->prepare("SELECT * FROM dana_pemanfaatan WHERE periode_id = ? ORDER BY id ASC");
$rincian->execute([$id]);
$rincian = $rincian->fetchAll();

$totalPemanfaatan = array_sum(array_column($rincian, 'nominal'));
$saldoAwal = (float)($periode['saldo_awal'] ?? 0);
$sisaSaldo = $saldoAwal + (float)$periode['total_pemasukan'] - $totalPemanfaatan;

// Cek apakah ada revisi pending
$revisiPending = $pdo->prepare("SELECT COUNT(*) FROM dana_revisi_request WHERE periode_id = ? AND status = 'pending'");
$revisiPending->execute([$id]);
$hasRevisiPending = (int)$revisiPending->fetchColumn() > 0;

// Cek apakah ada hapus pending
$hapusPending = $pdo->prepare("SELECT COUNT(*) FROM dana_hapus_request WHERE periode_id = ? AND status = 'pending'");
$hapusPending->execute([$id]);
$hasHapusPending = (int)$hapusPending->fetchColumn() > 0;

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

// Flash messages
$error = $_GET['error'] ?? '';
$success = $_GET['success'] ?? '';
$flashMsg = '';
$flashType = '';
if ($error === 'broadcast_failed') { $flashMsg = 'Gagal melakukan broadcast.'; $flashType = 'danger'; }
elseif ($error === 'revisi_failed') { $flashMsg = 'Gagal mengajukan revisi.'; $flashType = 'danger'; }
elseif ($error === 'revisi_empty') { $flashMsg = 'Data revisi tidak boleh kosong.'; $flashType = 'danger'; }
elseif ($error === 'pemanfaatan_over') { $flashMsg = 'Total pemanfaatan melebihi total pemasukan.'; $flashType = 'danger'; }
elseif ($error === 'nominal_over') { $flashMsg = 'Nominal terlalu besar. Maksimal 9.999.999.999.999 (13 digit).'; $flashType = 'danger'; }
elseif ($error === 'upload') { $flashMsg = 'Gagal memproses file lampiran PDF.'; $flashType = 'danger'; }
elseif ($error === 'not_pdf') { $flashMsg = 'Lampiran harus berupa file PDF yang valid.'; $flashType = 'danger'; }
elseif ($error === 'post_too_large') { $flashMsg = 'Ukuran file lampiran melebihi batas maksimum server. Perkecil file atau hubungi admin.'; $flashType = 'danger'; }
elseif ($error === 'hapus_empty') { $flashMsg = 'Alasan penghapusan tidak boleh kosong.'; $flashType = 'danger'; }
elseif ($error === 'hapus_failed') { $flashMsg = 'Gagal mengajukan penghapusan.'; $flashType = 'danger'; }
elseif ($success === 'broadcast') { $flashMsg = 'Periode berhasil di-broadcast ke pegawai dan admin.'; $flashType = 'success'; }
elseif ($success === 'revisi_submitted') { $flashMsg = 'Revisi berhasil diajukan. Menunggu persetujuan admin.'; $flashType = 'info'; }
elseif ($success === 'hapus_submitted') { $flashMsg = 'Penghapusan berhasil diajukan. Menunggu persetujuan admin.'; $flashType = 'info'; }
?>

<div class="d-flex align-items-center gap-3 mb-4">
  <a href="daftar_periode" class="btn-secondary-modern"><i class="bi bi-arrow-left"></i> Kembali</a>
  <div>
    <h2 class="fw-bold mb-0"><?= $bulanNama[(int)$periode['bulan']] ?> <?= $periode['tahun'] ?></h2>
    <span class="badge <?= $statusBadge[$periode['status']] ?> mt-1"><?= ucfirst(str_replace('_', ' ', $periode['status'])) ?></span>
    <?php if ($hasRevisiPending): ?>
      <span class="badge bg-info text-dark ms-1">Revisi Pending</span>
    <?php endif; ?>
    <?php if ($hasHapusPending): ?>
      <span class="badge bg-danger ms-1">Penghapusan Pending</span>
    <?php endif; ?>
  </div>
</div>

<?php if ($flashMsg): ?>
<div class="alert alert-<?= $flashType ?> alert-dismissible fade show" role="alert">
  <?= e($flashMsg) ?>
  <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Tutup"></button>
</div>
<?php endif; ?>

<div class="row g-4">
  <div class="col-md-4">
    <div class="app-card h-100">
      <h6 class="fw-bold text-muted mb-3">Ringkasan Keuangan</h6>
      <div class="mb-3">
        <div class="text-muted small">Saldo Awal</div>
        <div class="fs-5 fw-bold text-info"><?= rupiah($saldoAwal) ?></div>
      </div>
      <div class="mb-3">
        <div class="text-muted small">(+) Total Pemasukan</div>
        <div class="fs-4 fw-bold text-success"><?= rupiah($periode['total_pemasukan']) ?></div>
      </div>
      <div class="mb-3">
        <div class="text-muted small">(-) Total Pemanfaatan</div>
        <div class="fs-4 fw-bold text-danger"><?= rupiah($totalPemanfaatan) ?></div>
      </div>
      <div>
        <div class="text-muted small">Sisa Saldo</div>
        <div class="fs-4 fw-bold <?= $sisaSaldo >= 0 ? 'text-success' : 'text-danger' ?>"><?= rupiah($sisaSaldo) ?></div>
      </div>
      <?php if ($periode['broadcast_at']): ?>
      <div class="mt-3 pt-3 border-top">
        <div class="text-muted small">Di-broadcast pada</div>
        <div class="fw-semibold"><?= date('d M Y H:i', strtotime($periode['broadcast_at'])) ?></div>
      </div>
      <?php endif; ?>
    </div>
  </div>

  <div class="col-md-8">
    <div class="app-card h-100">
      <h6 class="fw-bold text-muted mb-3">Rincian Pemanfaatan Dana</h6>
      <?php if (empty($rincian)): ?>
        <p class="text-muted fst-italic">Belum ada rincian pemanfaatan.</p>
      <?php else: ?>
        <table class="table table-sm mb-0">
          <thead><tr><th>Keterangan</th><th class="text-end">Nominal</th><th class="text-end">Lampiran</th></tr></thead>
          <tbody>
            <?php foreach ($rincian as $r): ?>
              <tr>
                <td><?= e($r['keterangan']) ?></td>
                <td class="text-end"><?= rupiah($r['nominal']) ?></td>
                <td class="text-end">
                  <?php if (!empty($r['lampiran'])): ?>
                    <a href="../uploads/lampiran/<?= e($r['lampiran']) ?>" target="_blank" class="text-primary small text-decoration-none"><i class="bi bi-file-pdf"></i> PDF</a>
                  <?php else: ?>
                    <span class="text-muted small">-</span>
                  <?php endif; ?>
                </td>
              </tr>
            <?php endforeach; ?>
            <tr class="table-active fw-bold">
              <td>Total</td>
              <td class="text-end"><?= rupiah($totalPemanfaatan) ?></td>
              <td></td>
            </tr>
          </tbody>
        </table>
      <?php endif; ?>
    </div>
  </div>
</div>

<?php if ($periode['status'] === 'draft'): ?>
<div class="mt-4 d-flex gap-2">
  <a href="edit_periode?id=<?= $id ?>" class="btn-primary-modern"><i class="bi bi-pencil"></i> Edit Periode</a>
  <form method="post" action="broadcast_periode" style="display:inline" onsubmit="return confirm('Broadcast periode ini ke semua pegawai dan admin? Setelah di-broadcast, data tidak dapat diedit langsung.')">
    <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
    <input type="hidden" name="id" value="<?= $id ?>">
    <button type="submit" class="btn-success-modern"><i class="bi bi-broadcast"></i> Broadcast ke Pegawai</button>
  </form>
</div>
<?php elseif ($periode['status'] === 'broadcast' && !$hasRevisiPending && !$hasHapusPending): ?>
<div class="mt-4 d-flex gap-2">
  <button class="btn-primary-modern" data-bs-toggle="modal" data-bs-target="#revisiModal"><i class="bi bi-pencil-square"></i> Ajukan Revisi</button>
  <button class="btn-danger-modern" data-bs-toggle="modal" data-bs-target="#hapusModal"
          data-id="<?= $id ?>"
          data-periode="<?= e($bulanNama[(int)$periode['bulan']] . ' ' . $periode['tahun']) ?>"
          data-pemasukan="<?= rupiah($periode['total_pemasukan']) ?>"
          data-pemanfaatan="<?= rupiah($totalPemanfaatan) ?>">
    <i class="bi bi-trash"></i> Ajukan Penghapusan
  </button>
</div>
<?php elseif ($periode['status'] === 'revisi_pending'): ?>
<div class="mt-3">
  <div class="alert alert-info mb-0"><i class="bi bi-hourglass-split"></i> Revisi sedang dalam proses persetujuan admin.</div>
</div>
<?php elseif ($periode['status'] === 'hapus_pending'): ?>
<div class="mt-3">
  <div class="alert alert-danger mb-0"><i class="bi bi-hourglass-split"></i> Penghapusan periode sedang dalam proses persetujuan admin.</div>
</div>
<?php endif; ?>

<?php if ($periode['status'] === 'broadcast' && !$hasRevisiPending): ?>
<div class="modal fade" id="revisiModal" tabindex="-1" aria-label="Ajukan Revisi Periode"><div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable"><div class="modal-content border-0 shadow-lg" style="border-radius:1rem;overflow:hidden;">
  <form method="post" action="ajukan_revisi" data-validate novalidate enctype="multipart/form-data">
    <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
    <input type="hidden" name="periode_id" value="<?= $id ?>">
    <div class="modal-header px-4 py-3" style="background:linear-gradient(135deg,#667eea 0%,#764ba2 100%);">
      <div class="d-flex align-items-center gap-2">
        <span class="d-inline-flex align-items-center justify-content-center rounded-circle bg-white bg-opacity-25" style="width:38px;height:38px;color:#fff;">
          <i class="bi bi-pencil-square fs-5"></i>
        </span>
        <div>
          <h5 class="modal-title fw-bold text-white mb-0">Ajukan Revisi Periode</h5>
          <small class="text-white-50">Ubah data pemasukan, rincian, dan lampiran pendukung</small>
        </div>
      </div>
      <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Tutup"></button>
    </div>
    <div class="modal-body p-4 bg-white">
      <!-- Card 1: Pemasukan -->
      <div class="p-3 rounded-4 mb-4" style="background:#f8fafc;border:1px solid #e2e8f0;">
        <h6 class="fw-bold text-dark small text-uppercase tracking-wider mb-3"><i class="bi bi-cash-stack me-1 text-primary"></i> Total Pemasukan Baru</h6>
        <div class="row g-3">
          <div class="col-md-6">
            <label for="revTotalPemasukan" class="form-label fw-semibold small text-muted">Total Pemasukan (Rp)</label>
            <input type="number" class="form-control bg-white fw-semibold" name="total_pemasukan" id="revTotalPemasukan" placeholder="0" min="0" max="9999999999999" step="1000" value="<?= (int)$periode['total_pemasukan'] ?>" required>
          </div>
          <div class="col-md-6 d-flex align-items-end">
            <div class="p-2 px-3 rounded-3 w-100" style="background:#eff6ff;border:1px solid #bfdbfe;color:#1e40af;font-size:0.825rem;">
              <i class="bi bi-info-circle me-1"></i> Saldo awal: <strong><?= rupiah($saldoAwal) ?></strong> (tidak berubah)
            </div>
          </div>
        </div>
      </div>

      <!-- Card 2: Rincian Pemanfaatan -->
      <div class="p-3 rounded-4 mb-4" style="background:#f8fafc;border:1px solid #e2e8f0;">
        <div class="d-flex justify-content-between align-items-center mb-3">
          <h6 class="fw-bold text-dark small text-uppercase tracking-wider mb-0"><i class="bi bi-list-check me-1 text-primary"></i> Rincian Pemanfaatan Baru</h6>
          <button type="button" class="btn btn-outline-primary btn-sm rounded-pill px-3 fw-semibold shadow-none" id="revAddRow" style="font-size:0.8rem;">
            <i class="bi bi-plus-circle me-1"></i> Tambah Baris
          </button>
        </div>
        <div id="revRincianContainer" class="d-flex flex-column gap-2">
          <?php foreach ($rincian as $i => $r): ?>
          <div class="rev-rincian-row row g-2 align-items-center p-2 rounded-3 bg-white border">
            <div class="col-md-4">
              <input class="form-control form-control-sm border-0 bg-transparent shadow-none" name="rincian_keterangan[]" value="<?= e($r['keterangan']) ?>" placeholder="Keterangan..." required>
            </div>
            <div class="col-md-3">
              <input type="number" class="form-control form-control-sm rev-nominal border-0 bg-transparent shadow-none fw-medium" name="rincian_nominal[]" value="<?= (int)$r['nominal'] ?>" min="0" max="9999999999999" step="1000" required>
            </div>
            <div class="col-md-3">
              <label class="btn btn-sm btn-outline-primary w-100 mb-0 lampiran-label" style="border-radius:0.5rem;font-size:0.78rem;background:var(--primary-soft,#eef2ff);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">
                <i class="bi bi-file-earmark-pdf me-1"></i><span class="lampiran-text"><?= !empty($r['lampiran']) ? 'Ganti PDF' : 'PDF' ?></span>
                <input type="file" class="d-none" name="rincian_lampiran[]" accept="application/pdf" title="Lampiran PDF (opsional)">
              </label>
              <input type="hidden" name="rincian_lampiran_lama[]" value="<?= e($r['lampiran'] ?? '') ?>">
            </div>
            <div class="col-md-2 text-end">
              <button type="button" class="btn btn-link text-danger p-0 rev-remove" title="Hapus baris"><i class="bi bi-trash fs-6"></i></button>
            </div>
          </div>
          <?php endforeach; ?>
          <?php if (empty($rincian)): ?>
          <div class="rev-rincian-row row g-2 align-items-center p-2 rounded-3 bg-white border">
            <div class="col-md-4"><input class="form-control form-control-sm border-0 bg-transparent shadow-none" name="rincian_keterangan[]" placeholder="Keterangan..." required></div>
            <div class="col-md-3"><input type="number" class="form-control form-control-sm rev-nominal border-0 bg-transparent shadow-none fw-medium" name="rincian_nominal[]" placeholder="Nominal (Rp)..." min="0" max="9999999999999" step="1000" required></div>
            <div class="col-md-3"><label class="btn btn-sm btn-outline-primary w-100 mb-0 lampiran-label" style="border-radius:0.5rem;font-size:0.78rem;background:var(--primary-soft,#eef2ff);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;"><i class="bi bi-file-earmark-pdf me-1"></i><span class="lampiran-text">PDF</span><input type="file" class="d-none" name="rincian_lampiran[]" accept="application/pdf" title="Lampiran PDF (opsional)"></label><input type="hidden" name="rincian_lampiran_lama[]" value=""></div>
            <div class="col-md-2 text-end"><button type="button" class="btn btn-link text-danger p-0 rev-remove" title="Hapus baris"><i class="bi bi-trash fs-6"></i></button></div>
          </div>
          <?php endif; ?>
        </div>
      </div>

      <!-- Card 3: Ringkasan (dark) -->
      <div class="p-3 rounded-4 text-white mb-4" style="background:linear-gradient(135deg,#0f172a 0%,#1e293b 100%);box-shadow:0 10px 15px -3px rgba(15,23,42,0.2);">
        <h6 class="fw-bold small text-uppercase tracking-wider mb-3" style="color:#94a3b8;"><i class="bi bi-graph-up-arrow me-1"></i> Ringkasan Revisi</h6>
        <div class="row g-3">
          <div class="col-md-4 border-end border-secondary border-opacity-25">
            <div class="small mb-1" style="color:#94a3b8;">Total Pemanfaatan Baru</div>
            <div class="fs-5 fw-bold font-monospace" id="revTotalPemanfaatanDisplay">Rp 0</div>
          </div>
          <div class="col-md-4 border-end border-secondary border-opacity-25">
            <div class="small mb-1" style="color:#94a3b8;">(+) Pemasukan Baru</div>
            <div class="fs-5 fw-bold font-monospace" id="revPemasukanDisplay">Rp <?= number_format((int)$periode['total_pemasukan'], 0, ',', '.') ?></div>
          </div>
          <div class="col-md-4">
            <div class="small mb-1" style="color:#94a3b8;">Sisa Saldo Baru</div>
            <div class="fs-5 fw-bold font-monospace text-info" id="revSisaSaldoDisplay">Rp <?= number_format((int)$sisaSaldo, 0, ',', '.') ?></div>
          </div>
        </div>
        <div class="d-flex justify-content-between align-items-center small mt-3 pt-2 border-top border-secondary border-opacity-25" id="revPerbandinganRow">
          <span style="color:#94a3b8;">Perubahan dari sisa saldo lama</span>
          <span id="revPerbandinganText"><span class="text-muted"><?= number_format((int)$sisaSaldo, 0, ',', '.') ?></span> → <span class="fw-bold text-info">Rp <?= number_format((int)$sisaSaldo, 0, ',', '.') ?></span></span>
        </div>
        <div class="alert alert-warning border-0 small mt-3 mb-0 py-2 px-3 rounded-3 text-dark bg-warning bg-opacity-75" id="revSaldoAlert" style="display:none">
          <i class="bi bi-exclamation-triangle-fill me-1"></i> Total pemanfaatan melebihi dana yang tersedia!
        </div>
      </div>

      <!-- Card 4: Alasan -->
      <div class="p-3 rounded-4" style="background:#f8fafc;border:1px solid #e2e8f0;">
        <h6 class="fw-bold text-dark small text-uppercase tracking-wider mb-3"><i class="bi bi-chat-left-text me-1 text-primary"></i> Alasan Revisi <span class="text-danger">*</span></h6>
        <textarea id="alasanRevisi" class="form-control bg-white" name="alasan" rows="3" placeholder="Jelaskan alasan revisi..." required></textarea>
      </div>
    </div>
    <div class="modal-footer px-4 py-3 bg-light border-top d-flex gap-2">
      <button type="button" class="btn-secondary-modern px-4" data-bs-dismiss="modal">Batal</button>
      <button class="btn-primary-modern px-4" id="revSubmitBtn"><i class="bi bi-send me-1"></i> Kirim Revisi</button>
    </div>
  </form>
</div></div></div>

<script>
(function(){
  var SALDO_AWAL = <?= json_encode((float)$saldoAwal) ?>;
  var SISA_LAMA = <?= json_encode((float)$sisaSaldo) ?>;

  var container = document.getElementById('revRincianContainer');
  var addBtn = document.getElementById('revAddRow');
  var pemasukanInput = document.getElementById('revTotalPemasukan');
  var submitBtn = document.getElementById('revSubmitBtn');

  var totalPemDisplay = document.getElementById('revTotalPemanfaatanDisplay');
  var sisaDisplay = document.getElementById('revSisaSaldoDisplay');
  var alertEl = document.getElementById('revSaldoAlert');
  var perbandinganText = document.getElementById('revPerbandinganText');
  var pemasukanDisplay = document.getElementById('revPemasukanDisplay');

  function recalc() {
    var pemasukan = parseFloat(pemasukanInput.value) || 0;
    var totalPem = 0;
    container.querySelectorAll('.rev-nominal').forEach(function(el) {
      totalPem += parseFloat(el.value) || 0;
    });

    totalPemDisplay.textContent = 'Rp ' + totalPem.toLocaleString('id-ID');
    pemasukanDisplay.textContent = 'Rp ' + pemasukan.toLocaleString('id-ID');

    var sisaBaru = SALDO_AWAL + pemasukan - totalPem;
    sisaDisplay.textContent = 'Rp ' + sisaBaru.toLocaleString('id-ID');
    sisaDisplay.className = 'fw-bold fs-5 ' + (sisaBaru >= 0 ? 'text-success' : 'text-danger');

    var diff = sisaBaru - SISA_LAMA;
    var diffAbs = Math.abs(diff);
    var arrow = diff > 0 ? '\u25B2' : (diff < 0 ? '\u25BC' : '\u2015');
    var colorClass = diff > 0 ? 'text-success' : (diff < 0 ? 'text-danger' : 'text-muted');
    perbandinganText.innerHTML = '<span class="text-muted">' + SISA_LAMA.toLocaleString('id-ID') + '</span> \u2192 <span class="fw-bold ' + colorClass + '">Rp ' + sisaBaru.toLocaleString('id-ID') + '</span> <span class="' + colorClass + '">' + arrow + ' Rp ' + diffAbs.toLocaleString('id-ID') + '</span>';

    var exceeded = totalPem > (SALDO_AWAL + pemasukan);
    alertEl.style.display = exceeded ? '' : 'none';
    submitBtn.disabled = exceeded;
  }

  addBtn.addEventListener('click', function() {
    var row = document.createElement('div');
    row.className = 'rev-rincian-row row g-2 align-items-center p-2 rounded-3 bg-white border';
    row.innerHTML = '<div class="col-md-4"><input class="form-control form-control-sm border-0 bg-transparent shadow-none" name="rincian_keterangan[]" placeholder="Keterangan..." required></div><div class="col-md-3"><input type="number" class="form-control form-control-sm rev-nominal border-0 bg-transparent shadow-none fw-medium" name="rincian_nominal[]" placeholder="0" min="0" max="9999999999999" step="1000" required></div><div class="col-md-3"><label class="btn btn-sm btn-outline-primary w-100 mb-0 lampiran-label" style="border-radius:0.5rem;font-size:0.78rem;background:var(--primary-soft,#eef2ff);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;"><i class="bi bi-file-earmark-pdf me-1"></i><span class="lampiran-text">PDF</span><input type="file" class="d-none" name="rincian_lampiran[]" accept="application/pdf" title="Lampiran PDF (opsional)"></label><input type="hidden" name="rincian_lampiran_lama[]" value=""></div><div class="col-md-2 text-end"><button type="button" class="btn btn-link text-danger p-0 rev-remove" title="Hapus baris"><i class="bi bi-trash fs-6"></i></button></div>';
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
    var btn = e.target.closest('.rev-remove');
    if (btn) {
      btn.closest('.rev-rincian-row').remove();
      recalc();
    }
  });

  container.addEventListener('input', function(e) {
    if (e.target.matches('.rev-nominal')) {
      recalc();
    }
  });

  pemasukanInput.addEventListener('input', recalc);

  // Lampiran label: nama file tampil saat dipilih (baris awal)
  container.querySelectorAll('input[type=file]').forEach(function(inp) {
    inp.addEventListener('change', function() {
      var span = inp.closest('.lampiran-label').querySelector('.lampiran-text');
      span.textContent = inp.files.length ? inp.files[0].name : 'PDF';
    });
  });

  document.addEventListener('DOMContentLoaded', recalc);

  var modalEl = document.getElementById('revisiModal');
  modalEl.addEventListener('shown.bs.modal', recalc);
  modalEl.addEventListener('hidden.bs.modal', function() { location.reload(); });
})();
</script>
<?php endif; ?>

<?php if ($periode['status'] === 'broadcast' && !$hasRevisiPending && !$hasHapusPending): ?>
<!-- ── MODAL AJUKAN PENGHAPUSAN ── -->
<div class="modal fade" id="hapusModal" tabindex="-1" aria-label="Ajukan Penghapusan Periode">
<div class="modal-dialog modal-dialog-centered"><div class="modal-content modal-modern">
  <form method="post" action="ajukan_hapus">
    <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
    <input type="hidden" name="periode_id" value="<?= $id ?>">
    <div class="modal-header">
      <h5 class="modal-title fw-bold">Ajukan Penghapusan Periode</h5>
      <button class="btn-close" data-bs-dismiss="modal"></button>
    </div>
    <div class="modal-body">
      <div class="alert alert-warning">
        <i class="bi bi-exclamation-triangle"></i>
        <strong>Peringatan:</strong> Penghapusan akan menghapus <strong>seluruh data</strong>
        periode ini secara permanen setelah disetujui admin, termasuk rincian pemanfaatan
        dan status baca notifikasi pegawai. Data tidak dapat dikembalikan.
      </div>
      <div class="mb-3">
        <div class="small text-muted mb-1">Periode</div>
        <div class="fw-bold" id="hapusPeriodeLabel"></div>
      </div>
      <div class="row mb-3">
        <div class="col-6">
          <div class="small text-muted">Total Pemasukan</div>
          <div class="fw-bold text-success" id="hapusPemasukanLabel"></div>
        </div>
        <div class="col-6">
          <div class="small text-muted">Total Pemanfaatan</div>
          <div class="fw-bold text-danger" id="hapusPemanfaatanLabel"></div>
        </div>
      </div>
      <div class="mb-3">
        <label for="alasanHapus" class="form-label fw-semibold small text-uppercase text-muted">
          Alasan Penghapusan <span class="text-danger">*</span>
        </label>
        <textarea id="alasanHapus" class="form-control" name="alasan" rows="3"
                  placeholder="Jelaskan alasan penghapusan periode ini..." required></textarea>
      </div>
    </div>
    <div class="modal-footer border-0 d-flex gap-2">
      <button type="button" class="btn-secondary-modern" data-bs-dismiss="modal">Batal</button>
      <button class="btn-danger-modern" type="submit">
        <i class="bi bi-trash"></i> Kirim Pengajuan
      </button>
    </div>
  </form>
</div></div></div>

<script>
document.getElementById('hapusModal').addEventListener('show.bs.modal', function(event) {
  var btn = event.relatedTarget;
  this.querySelector('[name="periode_id"]').value = btn.getAttribute('data-id');
  document.getElementById('hapusPeriodeLabel').textContent = btn.getAttribute('data-periode');
  document.getElementById('hapusPemasukanLabel').textContent = btn.getAttribute('data-pemasukan');
  document.getElementById('hapusPemanfaatanLabel').textContent = btn.getAttribute('data-pemanfaatan');
});
</script>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
