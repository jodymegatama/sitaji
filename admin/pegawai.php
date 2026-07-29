<?php
$pageTitle = 'Manajemen Pegawai';
$activeNav = 'pegawai';
require_once __DIR__ . '/../includes/header_admin.php';

$total = (int)$pdo->query("SELECT COUNT(*) FROM pegawai")->fetchColumn();

$errorMsg = '';
$errorType = '';
$error = $_GET['error'] ?? '';
if ($error === 'incomplete') {
    $errorMsg = 'Data tidak lengkap. NIP dan Nama wajib diisi.';
    $errorType = 'danger';
} elseif ($error === 'db') {
    $errorMsg = 'Gagal menyimpan data. Silakan coba lagi.';
    $errorType = 'danger';
}
?>
<?php if ($errorMsg): ?>
<div class="alert alert-<?= $errorType ?> alert-dismissible fade show" role="alert">
  <?= e($errorMsg) ?>
  <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Tutup"></button>
</div>
<?php endif; ?>
<div class="d-flex justify-content-between align-items-end flex-wrap gap-3 mb-4">
  <div>
    <h2 class="fw-bold mb-1">Database Pegawai</h2>
    <p class="text-muted mb-0">Kelola informasi fundamental, kepangkatan, dan akses akun pegawai.</p>
  </div>
  <div class="btn-group-payroll">
    <button class="btn-payroll btn-import" data-bs-toggle="modal" data-bs-target="#importPegawaiModal">
      <i class="bi bi-file-earmark-excel"></i>
      Import Excel
    </button>
    <a href="export_pegawai" class="btn-payroll btn-export">
      <i class="bi bi-download"></i>
      Export Excel
    </a>
    <button class="btn-payroll btn-tambah" data-bs-toggle="modal" data-bs-target="#addModal">
      <i class="bi bi-plus-circle"></i>
      Tambah Pegawai
    </button>
  </div>
</div>

<div class="app-card">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <h5 class="fw-bold mb-0">List Pegawai Terdaftar</h5>
    <span class="text-primary small fw-semibold">Total: <?= $total ?> Orang</span>
  </div>
  <table class="table data-table align-middle" data-server-side="true" data-ajax="pegawai_data">
    <thead><tr><th>NIP & Profil</th><th>Nama Lengkap</th><th>Golongan</th><th>Jabatan</th><th class="text-end">Aksi</th></tr></thead>
    <tbody></tbody>
  </table>
</div>

<?php require __DIR__ . '/_pegawai_modal.php'; ?>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
