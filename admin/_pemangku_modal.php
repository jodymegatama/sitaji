<?php
// admin/_pemangku_modal.php
// Modal tambah pemangku kepentingan
$komponenList = $pdo->query("SELECT id, nama, kategori FROM komponen_payroll WHERE tipe='potongan' AND aktif=1 ORDER BY urutan ASC, id ASC")->fetchAll();
?>
<div class="modal fade" id="addModal" tabindex="-1" aria-label="Tambah Pemangku Kepentingan"><div class="modal-dialog modal-dialog-centered modal-lg"><div class="modal-content modal-modern">
  <form method="post" action="tambah_pemangku" data-validate novalidate>
    <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
    <div class="modal-header"><h5 class="modal-title fw-bold">Tambah Pemangku Kepentingan</h5><button class="btn-close" data-bs-dismiss="modal"></button></div>
    <div class="modal-body">
      <div class="mb-3">
        <label for="pmNama" class="form-label small text-uppercase fw-semibold text-muted">Nama Pemangku Kepentingan</label>
        <input id="pmNama" class="form-control form-control-lg" name="nama" placeholder="Contoh: Zawa - Penyelenggara Zakat dan Wakaf" required>
      </div>

      <div class="mb-3">
        <label for="pmUsername" class="form-label small text-uppercase fw-semibold text-muted">Username (Login)</label>
        <input id="pmUsername" class="form-control form-control-lg" name="username" placeholder="Username untuk login" required>
      </div>

      <div class="mb-3">
        <label for="pmPassword" class="form-label small text-uppercase fw-semibold text-muted">Password</label>
        <input type="password" id="pmPassword" class="form-control form-control-lg" name="password" placeholder="Minimal 6 karakter" required minlength="6">
      </div>

      <div class="mb-3">
        <label class="form-label small text-uppercase fw-semibold text-muted">Komponen Potongan Gaji</label>
        <p class="text-muted small mb-2">Pilih komponen potongan yang terkait dengan pemangku kepentingan ini:</p>
        <div class="border rounded p-3" style="max-height:200px;overflow-y:auto;background:#f8fafc">
          <?php foreach ($komponenList as $k): ?>
            <div class="form-check mb-1">
              <input class="form-check-input" type="checkbox" name="komponen_ids[]" value="<?= $k['id'] ?>" id="kmp_<?= $k['id'] ?>">
              <label class="form-check-label small" for="kmp_<?= $k['id'] ?>">
                <?= e($k['nama']) ?> <span class="text-muted">(<?= e($k['kategori']) ?>)</span>
              </label>
            </div>
          <?php endforeach; ?>
          <?php if (empty($komponenList)): ?>
            <p class="text-muted small mb-0">Belum ada komponen potongan gaji aktif.</p>
          <?php endif; ?>
        </div>
      </div>

      <div class="alert alert-info small mt-3 mb-0">
        <i class="bi bi-info-circle"></i> Akun login akan otomatis dibuat dengan role <strong>stakeholder</strong>.
      </div>
    </div>
    <div class="modal-footer border-0 d-flex gap-2">
      <button type="button" class="btn-secondary-modern" data-bs-dismiss="modal">Batal</button>
      <button class="btn-primary-modern"><i class="bi bi-check-circle"></i> Simpan Pemangku Kepentingan</button>
    </div>
  </form>
</div></div></div>
