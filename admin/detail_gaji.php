<?php
// Fragment HTML untuk modal "Audit Payroll Detail" — di-load via fetch().
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/helpers.php';
require_role('admin');

$id = (int)($_GET['id'] ?? 0);
$stmt = $pdo->prepare("SELECT p.*, pg.nama, pg.nip FROM payroll p JOIN pegawai pg ON pg.id=p.pegawai_id WHERE p.id=?");
$stmt->execute([$id]); $r = $stmt->fetch();
if (!$r) { echo '<div class="alert alert-danger">Data tidak ditemukan.</div>'; exit; }

$fields = payroll_fields();

// Ambil data detail payroll
try {
    $values = payroll_values_map($id);
    $r = array_merge($r, $values);
} catch (PDOException $e) {
    error_log("Detail Gaji Error: " . $e->getMessage());
    if ($e->getCode() === '42S02') {
        echo '<div class="alert alert-warning">Terjadi masalah konfigurasi sistem, hubungi administrator.</div>';
        exit;
    }
    throw $e;
}

// Set virtual sums for helper functions (total_bruto, etc.)
$r['_detail_sum_pendapatan'] = array_sum(array_intersect_key($values, array_flip(array_keys(payroll_fields()['pendapatan']))));
$r['_detail_sum_potongan'] = array_sum(array_intersect_key($values, array_flip(array_merge(array_keys(payroll_fields()['potongan_umum']), array_keys(payroll_fields()['koperasi'])))));
?>
<div class="d-flex justify-content-between align-items-center mb-3">
  <h5 class="fw-bold mb-0"><i class="bi bi-journal-text text-primary"></i> Audit Payroll Detail</h5>
  <button class="btn-close" data-bs-dismiss="modal"></button>
</div>

<div class="d-flex justify-content-between flex-wrap mb-3">
  <div>
    <div class="text-muted small text-uppercase">Nama Pegawai / NIP</div>
    <div class="fw-bold"><?= e($r['nama']) ?> <span class="text-muted fw-normal">(<?= e($r['nip']) ?>)</span></div>
  </div>
  <div class="text-end">
    <div class="text-muted small text-uppercase">Periode Gaji</div>
    <div class="text-primary fw-semibold"><?= periode_label($r['periode']) ?></div>
  </div>
</div>

<div class="row g-3">
  <div class="col-md-6">
    <div class="slip-section in">
      <div class="section-label green mb-2"><i class="bi bi-arrow-down-circle"></i> PENDAPATAN (BRUTO)</div>
      <?php foreach ($fields['pendapatan'] as $k=>$lab):
        $val = (float)($r[$k] ?? 0);
      ?>
        <?php if ($val > 0 || isset($r[$k])): ?>
        <div class="slip-row <?= $k==='gaji_13' ? 'highlight':'' ?>">
          <span><?= e($lab) ?></span><span><?= rupiah($val) ?></span>
        </div>
        <?php endif; ?>
      <?php endforeach; ?>
    </div>
  </div>
  <div class="col-md-6">
    <div class="slip-section out">
      <div class="section-label red mb-2"><i class="bi bi-arrow-up-circle"></i> RINCIAN POTONGAN & INFAQ</div>
      <?php
      $allPot = array_merge($fields['potongan_umum'], $fields['koperasi']);
      $hasPot = false;
      foreach ($allPot as $k=>$lab):
        $val = (float)($r[$k] ?? 0);
        if ($val <= 0 && !isset($r[$k])) continue;
        $hasPot = true;
      ?>
        <div class="slip-row"><span><?= e($lab) ?></span><span><?= rupiah($val) ?></span></div>
      <?php endforeach; ?>
      <?php if (!$hasPot): ?>
        <div style="background:#f8fafc;border:1px dashed #d1d5db;border-radius:8px;padding:12px 14px;margin-top:8px">
          <span style="color:#64748b;font-size:0.85rem;display:flex;align-items:center;gap:6px">
            <i class="bi bi-check-circle" style="color:#22c55e"></i>
            Tidak terdapat potongan dan infaq pada periode ini.
          </span>
        </div>
      <?php endif; ?>
      <?php if($r['keterangan']): ?>
        <div class="slip-row mt-2"><span>Keterangan:</span><span><?= e($r['keterangan']) ?></span></div>
      <?php endif; ?>
    </div>
  </div>
</div>

<div class="slip-total">
  <div><div class="label">TAKE HOME PAY (GAJI BERSIH)</div><div class="value"><?= rupiah(take_home($r)) ?></div></div>
  <div class="badge bg-secondary">STATUS: <?= $r['status_terima'] ? '1' : '0' ?></div>
</div>
