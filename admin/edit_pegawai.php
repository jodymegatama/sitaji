<?php
$pageTitle = 'Edit Pegawai';
$activeNav = 'pegawai';
require_once __DIR__ . '/../includes/header_admin.php';

$id = (int)($_GET['id'] ?? 0);
$st = $pdo->prepare("SELECT * FROM pegawai WHERE id=?"); $st->execute([$id]); $p = $st->fetch();
if (!$p) { echo '<div class="alert alert-danger">Pegawai tidak ditemukan.</div>'; require __DIR__.'/../includes/footer.php'; exit; }

if ($_SERVER['REQUEST_METHOD']==='POST') {
    csrf_check();
    $pdo->prepare("UPDATE pegawai SET nip=?, nama=?, golongan=?, jabatan=?, status=? WHERE id=?")
        ->execute([trim($_POST['nip']), trim($_POST['nama']), trim($_POST['golongan']), trim($_POST['jabatan']), $_POST['status'] ?? 'aktif', $id]);
    // sinkron username user
    $pdo->prepare("UPDATE users SET username=? WHERE pegawai_id=?")->execute([trim($_POST['nip']), $id]);
    header('Location: pegawai'); exit;
}
$flashMsg = $_SESSION['flash_message'] ?? '';
$flashType = $_SESSION['flash_type'] ?? '';
unset($_SESSION['flash_message'], $_SESSION['flash_type']);
?>
<div class="d-flex align-items-center gap-3 mb-4">
  <a href="pegawai" class="btn-secondary-modern"><i class="bi bi-arrow-left"></i> Kembali</a>
  <h2 class="fw-bold mb-0">Edit Pegawai</h2>
</div>

<?php if ($flashMsg): ?>
<div class="alert alert-<?= $flashType ?> alert-dismissible fade show" role="alert">
  <?= e($flashMsg) ?>
  <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Tutup"></button>
</div>
<?php endif; ?>

<div class="app-card" style="max-width:640px">
  <form method="post" data-validate novalidate>
    <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
    <div class="mb-3"><label for="editNip" class="form-label fw-semibold small text-uppercase text-muted">NIP</label><input id="editNip" class="form-control form-control-lg" name="nip" value="<?= e($p['nip']) ?>" required></div>
    <div class="mb-3"><label for="editNama" class="form-label fw-semibold small text-uppercase text-muted">Nama</label><input id="editNama" class="form-control form-control-lg" name="nama" value="<?= e($p['nama']) ?>" required></div>
    <div class="row g-3 mb-3">
      <div class="col-md-6"><label for="editGolongan" class="form-label fw-semibold small text-uppercase text-muted">Golongan</label><input id="editGolongan" class="form-control form-control-lg" name="golongan" value="<?= e($p['golongan']) ?>"></div>
      <div class="col-md-6"><label for="editJabatan" class="form-label fw-semibold small text-uppercase text-muted">Jabatan</label><input id="editJabatan" class="form-control form-control-lg" name="jabatan" value="<?= e($p['jabatan']) ?>"></div>
    </div>
    <div class="mb-3"><label for="editStatus" class="form-label fw-semibold small text-uppercase text-muted">Status</label>
      <select id="editStatus" class="form-select form-select-lg" name="status">
        <option value="aktif" <?= $p['status']==='aktif'?'selected':'' ?>>Aktif</option>
        <option value="nonaktif" <?= $p['status']==='nonaktif'?'selected':'' ?>>Nonaktif</option>
      </select>
    </div>
    <div class="d-flex gap-2">
      <button class="btn-primary-modern"><i class="bi bi-check-circle"></i> Update</button>
      <a href="pegawai" class="btn-secondary-modern">Batal</a>
    </div>
  </form>
</div>

<hr class="my-5">

<div class="app-card" style="max-width:640px">
  <div class="mb-4">
    <h5 class="fw-bold mb-1">Reset Password</h5>
    <p class="text-muted small mb-0">Minimal 8 karakter, mengandung huruf dan angka. Target terpisah dari form data pegawai di atas.</p>
  </div>
  <form method="post" action="reset_password_pegawai.php?id=<?= $id ?>" id="resetPwdForm">
    <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
    <div class="mb-3">
      <label class="form-label fw-semibold small text-uppercase text-muted" for="passwordBaru">Password Baru</label>
      <input type="password" class="form-control form-control-lg" name="password_baru" id="passwordBaru" minlength="8" placeholder="Min. 8 karakter, huruf & angka" required>
    </div>
    <div class="mb-3">
      <label class="form-label fw-semibold small text-uppercase text-muted" for="konfirmasiPassword">Konfirmasi Password Baru</label>
      <input type="password" class="form-control form-control-lg" name="konfirmasi_password" id="konfirmasiPassword" minlength="8" placeholder="Ketik ulang password baru" required>
    </div>
    <button class="btn-primary-modern"><i class="bi bi-key"></i> Update Password</button>
  </form>
</div>

<script>
document.getElementById('resetPwdForm')?.addEventListener('submit', function(e) {
    var pwd = document.getElementById('passwordBaru').value;
    var cf = document.getElementById('konfirmasiPassword').value;
    if (pwd.length < 8) {
        alert('Password minimal 8 karakter.');
        e.preventDefault();
        return false;
    }
    if (pwd !== cf) {
        alert('Password baru dan konfirmasi tidak cocok.');
        e.preventDefault();
        return false;
    }
});
</script>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
