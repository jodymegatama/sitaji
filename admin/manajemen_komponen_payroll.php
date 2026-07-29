<?php
$pageTitle = 'Manajemen Komponen Payroll';
$activeNav = 'payroll_komponen';
require_once __DIR__ . '/../includes/header_admin.php';

$reservedKeys = [
    'id', 'nip', 'periode', 'nama', 'gaji_pokok', 'tunj_kinerja',
    'tunj_profesi', 'uang_makan', 'gaji_13', 'thp', 'take_home_pay',
    'created_at', 'updated_at',
];

/** Komponen Payroll */
function auto_field_key(string $nama): string {
    $key = strtolower(trim($nama));
    $key = preg_replace('/[^a-z0-9]+/', '_', $key);
    return trim($key, '_');
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $act = $_POST['act'] ?? '';

    if ($act === 'add') {
        $nama      = trim($_POST['nama'] ?? '');
        $kategori  = trim($_POST['kategori'] ?? '');
        $tipe      = $_POST['tipe'] ?? 'potongan';
        $aktif     = (int)($_POST['aktif'] ?? 1);

        if (strlen($nama) < 3) {
            $errors[] = 'Nama variabel minimal 3 karakter.';
        } elseif (strlen($nama) > 100) {
            $errors[] = 'Nama variabel maksimal 100 karakter.';
        }

        $field_key = auto_field_key($nama);

        if (!preg_match('/^[a-z0-9_]+$/', $field_key)) {
            $errors[] = 'Field key hasil generate tidak valid.';
        }

        if (in_array($field_key, $reservedKeys, true)) {
            $errors[] = 'Nama variabel tersebut merupakan komponen bawaan sistem.';
        }

        if ($tipe === 'potongan' && !in_array($kategori, ['Potongan Umum', 'Koperasi atau Bank & Pinjaman'], true)) {
            $errors[] = 'Kategori tidak valid.';
        }

        if (!$errors) {
            $cek = $pdo->prepare("SELECT COUNT(*) FROM komponen_payroll WHERE field_key = ?");
            $cek->execute([$field_key]);
            if ((int)$cek->fetchColumn() > 0) {
                $errors[] = 'Variabel dengan nama tersebut sudah ada.';
            }
        }

        if (!$errors) {
            if ($tipe === 'pendapatan') {
                $kategori = 'Pendapatan';
            }
            $stUrutan = $pdo->prepare("SELECT COALESCE(MAX(urutan), 0) + 1 FROM komponen_payroll WHERE tipe = ?");
            $stUrutan->execute([$tipe]);
            $urutanBaru = (int)$stUrutan->fetchColumn();
            
            $pdo->prepare("INSERT INTO komponen_payroll (nama, field_key, kategori, tipe, aktif, urutan) VALUES (?,?,?,?,?,?)")
                ->execute([$nama, $field_key, $kategori, $tipe, $aktif, $urutanBaru]);
            $tab = $tipe === 'pendapatan' ? 'pendapatan' : ($kategori === 'Koperasi atau Bank & Pinjaman' ? 'koperasi' : 'potongan');
            header('Location: manajemen_komponen_payroll?tab=' . urlencode($tab)); exit;
        }
    } elseif ($act === 'del') {
        $id = (int)$_POST['id'];
        $kom = $pdo->prepare("SELECT * FROM komponen_payroll WHERE id=?");
        $kom->execute([$id]); $kom = $kom->fetch();
        if (!$kom) { $errors[] = 'Komponen tidak ditemukan.'; }
        else {
            $ck = $pdo->prepare("SELECT COUNT(*) FROM payroll_detail WHERE komponen_id=?");
            $ck->execute([$id]);
            $usedInDetail = (int)$ck->fetchColumn();

            if ($usedInDetail > 0) {
                $pdo->prepare("UPDATE komponen_payroll SET aktif=0 WHERE id=?")->execute([$id]);
                $msg = 'Komponen ini sudah pernah dipakai di data payroll, dinonaktifkan (bukan dihapus) untuk menjaga integritas data historis.';
                header('Location: manajemen_komponen_payroll?tab=' . urlencode($_POST['tab'] ?? 'pendapatan') . '&msg=' . urlencode($msg)); exit;
            } else {
                $pdo->prepare("DELETE FROM komponen_payroll WHERE id=?")->execute([$id]);
                header('Location: manajemen_komponen_payroll?tab=' . urlencode($_POST['tab'] ?? 'pendapatan')); exit;
            }
        }
    } elseif ($act === 'edit') {
        $id = (int)$_POST['id'];
        $nama = trim($_POST['nama'] ?? '');
        if (strlen($nama) < 3) {
            $errors[] = 'Nama komponen minimal 3 karakter.';
        } else {
            $kom = $pdo->prepare("SELECT * FROM komponen_payroll WHERE id=?");
            $kom->execute([$id]); $kom = $kom->fetch();
            if (!$kom) {
                $errors[] = 'Komponen tidak ditemukan.';
            } else {
                $pdo->prepare("UPDATE komponen_payroll SET nama=? WHERE id=?")->execute([$nama, $id]);
                header('Location: manajemen_komponen_payroll?tab=' . urlencode($_POST['tab'] ?? 'pendapatan')); exit;
            }
        }
    }
}

$msg = trim($_GET['msg'] ?? '');
$activeTab = in_array($_GET['tab'] ?? '', ['pendapatan','potongan','koperasi']) ? $_GET['tab'] : 'pendapatan';
$list = $pdo->query("SELECT * FROM komponen_payroll WHERE tipe='potongan' ORDER BY urutan ASC, id ASC")->fetchAll();

$pendapatanList = $pdo->query("SELECT * FROM komponen_payroll WHERE tipe='pendapatan' ORDER BY urutan ASC, id ASC")->fetchAll();
?>
<div class="d-flex justify-content-between align-items-end flex-wrap gap-3 mb-4">
  <div>
    <h2 class="fw-bold mb-1">Manajemen Komponen Payroll</h2>
    <p class="text-muted mb-0">Konfigurasi seluruh komponen pendapatan dan potongan payroll sistem secara real-time.</p>
  </div>
  <div class="d-flex gap-2">
    <button class="btn-primary-modern" data-bs-toggle="modal" data-bs-target="#addModal"><i class="bi bi-plus-circle"></i>Tambah Variabel</button>
    <a href="dashboard" class="btn-secondary-modern"><i class="bi bi-arrow-left"></i>Kembali</a>
  </div>
</div>

<?php if ($errors): ?>
  <div class="alert alert-danger"><?= e(implode('<br>', $errors)) ?></div>
<?php endif; ?>
<?php if ($msg): ?>
  <div class="alert alert-warning"><?= e($msg) ?></div>
<?php endif; ?>

<?php
$potonganUmum = array_values(array_filter($list, fn($k) => $k['kategori'] === 'Potongan Umum'));
$koperasi     = array_values(array_filter($list, fn($k) => $k['kategori'] === 'Koperasi atau Bank & Pinjaman'));
?>

<ul class="nav nav-tabs mb-4" id="komponenTab" role="tablist">
  <li class="nav-item" role="presentation">
    <button class="nav-link <?= $activeTab === 'pendapatan' ? 'active' : '' ?>" id="pendapatan-tab" data-bs-toggle="tab" data-bs-target="#pendapatan" type="button" role="tab">
      <i class="bi bi-cash-coin text-warning me-1"></i> Pendapatan (Earnings)
    </button>
  </li>
  <li class="nav-item" role="presentation">
    <button class="nav-link <?= $activeTab === 'potongan' ? 'active' : '' ?>" id="potongan-tab" data-bs-toggle="tab" data-bs-target="#potongan" type="button" role="tab">
      <i class="bi bi-dash-circle text-danger me-1"></i> Potongan Umum
    </button>
  </li>
  <li class="nav-item" role="presentation">
    <button class="nav-link <?= $activeTab === 'koperasi' ? 'active' : '' ?>" id="koperasi-tab" data-bs-toggle="tab" data-bs-target="#koperasi" type="button" role="tab">
      <i class="bi bi-bank text-success me-1"></i> Koperasi atau Bank & Pinjaman
    </button>
  </li>
</ul>

<div class="tab-content" id="komponenTabContent">

  <div class="tab-pane fade <?= $activeTab === 'pendapatan' ? 'show active' : '' ?>" id="pendapatan" role="tabpanel">
    <div class="app-card">
      <table class="table data-table align-middle mb-0">
        <thead><tr><th>#</th><th>Nama Parameter</th><th>Field Key</th><th>Status</th><th>Aksi</th></tr></thead>
        <tbody>
          <?php if (count($pendapatanList) === 0): ?>
            <tr><td></td><td></td><td></td><td class="text-center text-muted py-4">Belum ada komponen pendapatan.</td><td></td></tr>
          <?php else: ?>
            <?php foreach($pendapatanList as $i=>$k): ?>
            <tr>
              <td><?= $i+1 ?></td>
              <td>
                <div class="fw-semibold"><?= e($k['nama']) ?></div>
                <code class="small text-muted"><?= e($k['field_key']) ?></code>
              </td>
              <td><code class="small"><?= e($k['field_key']) ?></code></td>
              <td>
                <?php if ((int)$k['aktif'] === 1): ?>
                  <span class="text-success small fw-semibold">● Aktif</span>
                <?php else: ?>
                  <span class="text-muted small fw-semibold">○ Nonaktif</span>
                <?php endif; ?>
              </td>
              <td>
                <form method="post" class="d-inline" onsubmit="return confirm('Hapus komponen ini?')">
                  <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
                  <input type="hidden" name="act" value="del">
                  <input type="hidden" name="tab" value="pendapatan">
                  <input type="hidden" name="id" value="<?= $k['id'] ?>">
                  <button class="btn-action-modern text-danger" title="Hapus"><i class="bi bi-trash"></i></button>
                </form>
              </td>
            </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

  <div class="tab-pane fade <?= $activeTab === 'potongan' ? 'show active' : '' ?>" id="potongan" role="tabpanel">
    <div class="app-card">
      <table class="table data-table align-middle mb-0">
        <thead><tr><th>#</th><th>Nama Parameter Potongan</th><th>Kategori</th><th>Status</th><th>Aksi</th></tr></thead>
        <tbody>
          <?php if (count($potonganUmum) === 0): ?>
            <tr><td></td><td></td><td class="text-center text-muted py-4">Belum ada komponen pada kategori ini.</td><td></td><td></td></tr>
          <?php else: ?>
            <?php foreach($potonganUmum as $i=>$k): ?>
            <tr>
              <td><?= $i+1 ?></td>
              <td>
                <div class="fw-semibold"><?= e($k['nama']) ?></div>
                <code class="small text-muted"><?= e($k['field_key']) ?></code>
              </td>
              <td>
                <?php if ($k['kategori'] === 'Potongan Umum'): ?>
                  <span class="badge" style="background:#fef2f2;color:#dc2626;padding:.3rem .65rem;border-radius:999px;font-weight:600;font-size:.78rem">
                    <i class="bi bi-dash-circle"></i> <?= e($k['kategori']) ?>
                  </span>
                <?php else: ?>
                  <span class="badge" style="background:#f0fdf4;color:#16a34a;padding:.3rem .65rem;border-radius:999px;font-weight:600;font-size:.78rem">
                    <i class="bi bi-heart"></i> <?= e($k['kategori']) ?>
                  </span>
                <?php endif; ?>
              </td>
              <td>
                <?php if ((int)$k['aktif'] === 1): ?>
                  <span class="text-success small fw-semibold">● Aktif</span>
                <?php else: ?>
                  <span class="text-muted small fw-semibold">○ Nonaktif</span>
                <?php endif; ?>
              </td>
              <td>
                <button class="btn-action-modern" title="Edit" data-bs-toggle="modal" data-bs-target="#editModal_<?= $k['id'] ?>"><i class="bi bi-pencil"></i></button>
                <form method="post" class="d-inline" onsubmit="return confirm('Hapus komponen ini?')">
                  <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
                  <input type="hidden" name="act" value="del">
                  <input type="hidden" name="tab" value="potongan">
                  <input type="hidden" name="id" value="<?= $k['id'] ?>">
                  <button class="btn-action-modern text-danger" title="Hapus"><i class="bi bi-trash"></i></button>
                </form>
              </td>
            </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

  <div class="tab-pane fade <?= $activeTab === 'koperasi' ? 'show active' : '' ?>" id="koperasi" role="tabpanel">
    <div class="app-card">
      <table class="table data-table align-middle mb-0">
        <thead><tr><th>#</th><th>Nama Parameter Potongan</th><th>Kategori</th><th>Status</th><th>Aksi</th></tr></thead>
        <tbody>
          <?php if (count($koperasi) === 0): ?>
            <tr><td></td><td></td><td class="text-center text-muted py-4">Belum ada komponen pada kategori ini.</td><td></td><td></td></tr>
          <?php else: ?>
            <?php foreach($koperasi as $i=>$k): ?>
            <tr>
              <td><?= $i+1 ?></td>
              <td>
                <div class="fw-semibold"><?= e($k['nama']) ?></div>
                <code class="small text-muted"><?= e($k['field_key']) ?></code>
              </td>
              <td>
                <?php if ($k['kategori'] === 'Potongan Umum'): ?>
                  <span class="badge" style="background:#fef2f2;color:#dc2626;padding:.3rem .65rem;border-radius:999px;font-weight:600;font-size:.78rem">
                    <i class="bi bi-dash-circle"></i> <?= e($k['kategori']) ?>
                  </span>
                <?php else: ?>
                  <span class="badge" style="background:#f0fdf4;color:#16a34a;padding:.3rem .65rem;border-radius:999px;font-weight:600;font-size:.78rem">
                    <i class="bi bi-heart"></i> <?= e($k['kategori']) ?>
                  </span>
                <?php endif; ?>
              </td>
              <td>
                <?php if ((int)$k['aktif'] === 1): ?>
                  <span class="text-success small fw-semibold">● Aktif</span>
                <?php else: ?>
                  <span class="text-muted small fw-semibold">○ Nonaktif</span>
                <?php endif; ?>
              </td>
              <td>
                <button class="btn-action-modern" title="Edit" data-bs-toggle="modal" data-bs-target="#editModal_<?= $k['id'] ?>"><i class="bi bi-pencil"></i></button>
                <form method="post" class="d-inline" onsubmit="return confirm('Hapus komponen ini?')">
                  <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
                  <input type="hidden" name="act" value="del">
                  <input type="hidden" name="tab" value="koperasi">
                  <input type="hidden" name="id" value="<?= $k['id'] ?>">
                  <button class="btn-action-modern text-danger" title="Hapus"><i class="bi bi-trash"></i></button>
                </form>
              </td>
            </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

</div>

<!-- Modal Tambah -->
<div class="modal fade" id="addModal" tabindex="-1" aria-label="Tambah Komponen Payroll"><div class="modal-dialog modal-dialog-centered"><div class="modal-content modal-modern">
  <form method="post" novalidate>
    <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
    <input type="hidden" name="act" value="add">
    <div class="modal-header">
      <h5 class="modal-title fw-bold"><i class="bi bi-plus-circle text-primary"></i> Tambah Komponen Payroll</h5>
      <button class="btn-close" data-bs-dismiss="modal"></button>
    </div>
    <div class="modal-body">
      <div class="mb-3">
        <label for="fieldTipe" class="form-label fw-semibold small text-uppercase text-muted">Tipe</label>
        <select class="form-select form-select-lg" name="tipe" id="fieldTipe" required>
          <option value="pendapatan">Pendapatan</option>
          <option value="potongan" selected>Potongan</option>
        </select>
      </div>
      <div class="mb-3">
        <label for="fieldNama" class="form-label fw-semibold small text-uppercase text-muted">Nama Variabel</label>
        <input class="form-control form-control-lg" name="nama" id="fieldNama" minlength="3" maxlength="100" placeholder="Contoh: Pinjaman Koperasi" required autocomplete="off">
      </div>
      <div class="mb-3">
        <label for="fieldKeyPreview" class="form-label fw-semibold small text-uppercase text-muted">Field Key <span class="text-muted fw-normal">(otomatis)</span></label>
        <input class="form-control form-control-lg font-monospace" id="fieldKeyPreview" value="" placeholder="akan dibuat otomatis..." readonly style="background:#f1f5f9;color:#475569">
      </div>
      <div class="mb-3" id="kategoriWrapper">
        <label for="fieldKategori" class="form-label fw-semibold small text-uppercase text-muted">Kategori</label>
        <select class="form-select form-select-lg" name="kategori" id="fieldKategori" required>
          <option value="Potongan Umum">Potongan Umum</option>
          <option value="Koperasi atau Bank & Pinjaman">Koperasi atau Bank & Pinjaman</option>
        </select>
      </div>
      <div class="row g-3">
        <div class="col-12">
          <label class="form-label fw-semibold small text-uppercase text-muted">Status</label>
          <select class="form-select form-select-lg" name="aktif">
            <option value="1">Aktif</option>
            <option value="0">Nonaktif</option>
          </select>
        </div>
      </div>
    </div>
    <div class="modal-footer border-0 d-flex gap-2">
      <button type="button" class="btn-secondary-modern" data-bs-dismiss="modal">Batal</button>
      <button class="btn-primary-modern"><i class="bi bi-check-circle"></i> Simpan</button>
    </div>
  </form>
</div></div></div>

<!-- Modal Edit untuk setiap komponen -->
<?php
$allKomponen = $pdo->query("SELECT * FROM komponen_payroll ORDER BY tipe, urutan, id")->fetchAll();
foreach ($allKomponen as $k):
    $editTab = $k['tipe'] === 'pendapatan' ? 'pendapatan' : ($k['kategori'] === 'Koperasi atau Bank & Pinjaman' ? 'koperasi' : 'potongan');
?>
<div class="modal fade" id="editModal_<?= $k['id'] ?>" tabindex="-1" aria-label="Edit Komponen Payroll">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content modal-modern">
      <form method="post" novalidate>
        <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
        <input type="hidden" name="act" value="edit">
        <input type="hidden" name="id" value="<?= $k['id'] ?>">
        <input type="hidden" name="tab" value="<?= $editTab ?>">
        <div class="modal-header">
          <h5 class="modal-title fw-bold"><i class="bi bi-pencil-square text-primary"></i> Edit Komponen</h5>
          <button class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <div class="mb-3">
            <label class="form-label fw-semibold small text-uppercase text-muted">Nama Komponen</label>
            <input class="form-control form-control-lg" name="nama" value="<?= e($k['nama']) ?>" minlength="3" maxlength="80" required autocomplete="off">
          </div>
          <div class="mb-3">
            <label class="form-label fw-semibold small text-uppercase text-muted">Field Key <span class="text-muted fw-normal">(tidak dapat diubah)</span></label>
            <input class="form-control form-control-lg font-monospace" value="<?= e($k['field_key']) ?>" readonly style="background:#f1f5f9;color:#475569">
          </div>
        </div>
        <div class="modal-footer border-0 d-flex gap-2">
          <button type="button" class="btn-secondary-modern" data-bs-dismiss="modal">Batal</button>
          <button class="btn-primary-modern"><i class="bi bi-check-circle"></i> Simpan</button>
        </div>
      </form>
    </div>
  </div>
</div>
<?php endforeach; ?>

<script>
(function() {
  const namaInput = document.getElementById('fieldNama');
  const keyInput  = document.getElementById('fieldKeyPreview');
  const tipeSelect = document.getElementById('fieldTipe');
  const kategoriWrapper = document.getElementById('kategoriWrapper');
  const kategoriSelect = document.getElementById('fieldKategori');

  if (!namaInput || !keyInput) return;

  function generateKey(val) {
    return val.toLowerCase()
      .replace(/[^a-z0-9]+/g, '_')
      .replace(/^_+|_+$/g, '');
  }

  namaInput.addEventListener('input', function() {
    keyInput.value = generateKey(this.value);
  });

  if (tipeSelect) {
    function toggleKategori() {
      if (tipeSelect.value === 'pendapatan') {
        kategoriWrapper.style.display = 'none';
        kategoriSelect.removeAttribute('required');
      } else {
        kategoriWrapper.style.display = '';
        kategoriSelect.setAttribute('required', 'required');
      }
    }
    tipeSelect.addEventListener('change', toggleKategori);
    toggleKategori();
  }
})();
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
