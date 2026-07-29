<?php
$pageTitle = 'Edit Pemangku Kepentingan';
$activeNav = 'pemangku';
require_once __DIR__ . '/../includes/header_admin.php';

$id = (int)($_GET['id'] ?? 0);
$st = $pdo->prepare("SELECT pk.*, u.username FROM pemangku_kepentingan pk JOIN users u ON u.id = pk.user_id WHERE pk.id = ?");
$st->execute([$id]);
$pk = $st->fetch();
if (!$pk) {
    echo '<div class="alert alert-danger">Pemangku kepentingan tidak ditemukan.</div>';
    require __DIR__ . '/../includes/footer.php';
    exit;
}

// Ambil komponen yang sudah di-mapping
$mappedIds = $pdo->prepare("SELECT komponen_id FROM pemangku_kepentingan_komponen WHERE pemangku_kepentingan_id = ?");
$mappedIds->execute([$id]);
$mappedIds = array_column($mappedIds->fetchAll(), 'komponen_id');

// Semua komponen potongan aktif
$komponenList = $pdo->query("SELECT id, nama, kategori FROM komponen_payroll WHERE tipe='potongan' AND aktif=1 ORDER BY urutan ASC, id ASC")->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $nama = trim($_POST['nama'] ?? '');
    $aktif = (int)($_POST['aktif'] ?? 1);
    $komponenIds = $_POST['komponen_ids'] ?? [];
    $resetPassword = trim($_POST['new_password'] ?? '');

    if ($nama === '') {
        $flashMsg = 'Nama wajib diisi.';
        $flashType = 'danger';
    } elseif (empty($komponenIds)) {
        $flashMsg = 'Pilih minimal 1 komponen potongan gaji.';
        $flashType = 'danger';
    } else {
        try {
            $pdo->beginTransaction();

            // Update nama & status
            $pdo->prepare("UPDATE pemangku_kepentingan SET nama = ?, aktif = ? WHERE id = ?")
                ->execute([$nama, $aktif, $id]);

            // Reset password jika diisi
            if ($resetPassword !== '') {
                if (strlen($resetPassword) < 6) {
                    $pdo->rollBack();
                    $flashMsg = 'Password baru minimal 6 karakter.';
                    $flashType = 'danger';
                    goto render;
                }
                $pdo->prepare("UPDATE users SET password_hash = ? WHERE id = ?")
                    ->execute([password_hash($resetPassword, PASSWORD_DEFAULT), $pk['user_id']]);
            }

            // Sync mapping komponen: hapus lama, insert baru
            $pdo->prepare("DELETE FROM pemangku_kepentingan_komponen WHERE pemangku_kepentingan_id = ?")
                ->execute([$id]);
            $stmt = $pdo->prepare("INSERT INTO pemangku_kepentingan_komponen (pemangku_kepentingan_id, komponen_id) VALUES (?, ?)");
            foreach ($komponenIds as $kid) {
                $stmt->execute([$id, (int)$kid]);
            }

            $pdo->commit();
            header('Location: pemangku_kepentingan?tab=daftar&success=updated');
            exit;
        } catch (Exception $e) {
            $pdo->rollBack();
            $flashMsg = 'Gagal menyimpan: ' . $e->getMessage();
            $flashType = 'danger';
        }
    }
}
?>
<?php render: ?>
<div class="d-flex align-items-center gap-3 mb-4">
  <a href="pemangku_kepentingan?tab=daftar" class="btn-secondary-modern"><i class="bi bi-arrow-left"></i> Kembali</a>
  <h2 class="fw-bold mb-0">Edit Pemangku Kepentingan</h2>
</div>

<?php if (!empty($flashMsg)): ?>
<div class="alert alert-<?= $flashType ?> alert-dismissible fade show" role="alert">
  <?= e($flashMsg) ?>
  <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Tutup"></button>
</div>
<?php endif; ?>

<div class="app-card" style="max-width:720px">
  <form method="post" data-validate novalidate>
    <input type="hidden" name="csrf" value="<?= csrf_token() ?>">

    <div class="mb-3">
      <label class="form-label fw-semibold small text-uppercase text-muted">Nama Pemangku Kepentingan</label>
      <input class="form-control form-control-lg" name="nama" value="<?= e($pk['nama']) ?>" required>
    </div>

    <div class="mb-3">
      <label class="form-label fw-semibold small text-uppercase text-muted">Username (Login)</label>
      <input class="form-control form-control-lg" value="<?= e($pk['username']) ?>" disabled>
      <div class="form-text">Username tidak dapat diubah.</div>
    </div>

    <div class="mb-3">
      <label class="form-label fw-semibold small text-uppercase text-muted">Reset Password (Kosongkan jika tidak diubah)</label>
      <input type="password" class="form-control form-control-lg" name="new_password" placeholder="Masukkan password baru (min 6 karakter)" minlength="6">
    </div>

    <div class="mb-3">
      <label class="form-label fw-semibold small text-uppercase text-muted">Status</label>
      <select class="form-select form-select-lg" name="aktif">
        <option value="1" <?= $pk['aktif'] ? 'selected' : '' ?>>Aktif</option>
        <option value="0" <?= !$pk['aktif'] ? 'selected' : '' ?>>Nonaktif</option>
      </select>
    </div>

    <div class="mb-3">
      <label class="form-label fw-semibold small text-uppercase text-muted">Komponen Potongan Gaji</label>
      <div class="border rounded p-3" style="max-height:200px;overflow-y:auto;background:#f8fafc">
        <?php foreach ($komponenList as $k): ?>
          <div class="form-check mb-1">
            <input class="form-check-input" type="checkbox" name="komponen_ids[]" value="<?= $k['id'] ?>" id="kmp_<?= $k['id'] ?>" <?= in_array($k['id'], $mappedIds) ? 'checked' : '' ?>>
            <label class="form-check-label small" for="kmp_<?= $k['id'] ?>">
              <?= e($k['nama']) ?> <span class="text-muted">(<?= e($k['kategori']) ?>)</span>
            </label>
          </div>
        <?php endforeach; ?>
      </div>
    </div>

    <div class="d-flex gap-2">
      <button class="btn-primary-modern"><i class="bi bi-check-circle"></i> Update</button>
      <a href="pemangku_kepentingan?tab=daftar" class="btn-secondary-modern">Batal</a>
    </div>
  </form>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
