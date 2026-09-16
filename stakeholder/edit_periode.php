<?php
$pageTitle = 'Edit Periode Dana';
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

// SERVER-SIDE: Tolak edit jika bukan draft
if ($periode['status'] !== 'draft') {
    header('Location: daftar_periode?error=not_draft');
    exit;
}

// Ambil rincian pemanfaatan
$rincian = $pdo->prepare("SELECT * FROM dana_pemanfaatan WHERE periode_id = ? ORDER BY id ASC");
$rincian->execute([$id]);
$rincian = $rincian->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();

    // DOUBLE-CHECK server-side: status masih draft?
    $recheck = $pdo->prepare("SELECT status FROM dana_periode WHERE id = ?");
    $recheck->execute([$id]);
    $currentStatus = $recheck->fetchColumn();
    if ($currentStatus !== 'draft') {
        header('Location: daftar_periode?error=not_draft');
        exit;
    }

    $totalPemasukan = (float)($_POST['total_pemasukan'] ?? 0);
    $rincianKeterangan = $_POST['rincian_keterangan'] ?? [];
    $rincianNominal = $_POST['rincian_nominal'] ?? [];

    if ($totalPemasukan <= 0) {
        header('Location: edit_periode?id=' . $id . '&error=nominal_invalid');
        exit;
    }
    if ($totalPemasukan > 9999999999999) {
        header('Location: edit_periode?id=' . $id . '&error=nominal_over');
        exit;
    }

    // Bangun array rincian yang valid
    $rincianNew = [];
    $uploadDir = __DIR__ . '/../uploads/lampiran/';
    if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);

    for ($i = 0; $i < count($rincianKeterangan); $i++) {
        $ket = trim($rincianKeterangan[$i] ?? '');
        $nom = (float)($rincianNominal[$i] ?? 0);
        if ($ket !== '' && $nom > 0) {
            if ($nom > 9999999999999) {
                header('Location: edit_periode?id=' . $id . '&error=nominal_over');
                exit;
            }
            $lampiran = null;
            if (isset($_FILES['rincian_lampiran']['name'][$i]) && $_FILES['rincian_lampiran']['error'][$i] !== UPLOAD_ERR_NO_FILE) {
                $fErr = $_FILES['rincian_lampiran']['error'][$i];
                if ($fErr !== UPLOAD_ERR_OK) {
                    header('Location: edit_periode?id=' . $id . '&error=upload');
                    exit;
                }
                $finfo = new finfo(FILEINFO_MIME_TYPE);
                $mime = $finfo->file($_FILES['rincian_lampiran']['tmp_name'][$i]);
                if ($mime !== 'application/pdf') {
                    header('Location: edit_periode?id=' . $id . '&error=not_pdf');
                    exit;
                }
                $safeName = date('Ymd') . '_' . uniqid() . '_' . bin2hex(random_bytes(4)) . '.pdf';
                if (!move_uploaded_file($_FILES['rincian_lampiran']['tmp_name'][$i], $uploadDir . $safeName)) {
                    header('Location: edit_periode?id=' . $id . '&error=upload');
                    exit;
                }
                $lampiran = $safeName;
            }
            $rincianNew[] = ['keterangan' => $ket, 'nominal' => $nom, 'lampiran' => $lampiran];
        }
    }

    if (empty($rincianNew)) {
        header('Location: edit_periode?id=' . $id . '&error=rincian_empty');
        exit;
    }

    // Validasi: total pemanfaatan <= (saldo_awal + total pemasukan)
    $saldoAwal = (float)($periode['saldo_awal'] ?? 0);
    $totalPemanfaatan = array_sum(array_column($rincianNew, 'nominal'));
    $maksPemanfaatan = $saldoAwal + $totalPemasukan;
    if ($totalPemanfaatan > $maksPemanfaatan) {
        header('Location: edit_periode?id=' . $id . '&error=pemanfaatan_over');
        exit;
    }

    try {
        $pdo->beginTransaction();

        // Update periode
        $pdo->prepare("UPDATE dana_periode SET total_pemasukan = ? WHERE id = ?")
            ->execute([$totalPemasukan, $id]);

        // Replace rincian: hapus lama (dan file lampiran lama), insert baru
        $oldRincian = $pdo->prepare("SELECT lampiran FROM dana_pemanfaatan WHERE periode_id = ? AND lampiran IS NOT NULL");
        $oldRincian->execute([$id]);
        $oldFiles = $oldRincian->fetchAll(PDO::FETCH_COLUMN);
        $pdo->prepare("DELETE FROM dana_pemanfaatan WHERE periode_id = ?")
            ->execute([$id]);
        $stmt = $pdo->prepare("INSERT INTO dana_pemanfaatan (periode_id, keterangan, nominal, lampiran) VALUES (?, ?, ?, ?)");
        foreach ($rincianNew as $r) {
            $stmt->execute([$id, $r['keterangan'], $r['nominal'], $r['lampiran']]);
            // Jika baris ini tidak upload file baru, pertahankan file lama? Tidak — rincian diganti total, hapus file lama yang tidak dipakai ulang
        }
        // Hapus file lama yang tidak direferensikan lagi (kecuali jika dipakai ulang — tidak ada mekanisme reuse, jadi hapus semua file lama yang berubah)
        $newFiles = array_filter(array_column($rincianNew, 'lampiran'));
        foreach ($oldFiles as $f) {
            if (!in_array($f, $newFiles, true)) {
                $p = __DIR__ . '/../uploads/lampiran/' . $f;
                if (file_exists($p)) unlink($p);
            }
        }

        $pdo->commit();
        header('Location: daftar_periode?success=updated');
        exit;
    } catch (Exception $e) {
        $pdo->rollBack();
        header('Location: edit_periode?id=' . $id . '&error=db');
        exit;
    }
}

// Flash messages
$error = $_GET['error'] ?? '';
$flashMsg = '';
$flashType = '';
if ($error === 'nominal_invalid') { $flashMsg = 'Total nominal harus lebih dari 0.'; $flashType = 'danger'; }
elseif ($error === 'pemanfaatan_over') { $flashMsg = 'Total pemanfaatan melebihi total saldo yang tersedia (saldo awal + pemasukan).'; $flashType = 'danger'; }
elseif ($error === 'rincian_empty') { $flashMsg = 'Tambahkan minimal 1 rincian pemanfaatan dana.'; $flashType = 'danger'; }
elseif ($error === 'nominal_over') { $flashMsg = 'Nominal terlalu besar. Maksimal 9.999.999.999.999 (13 digit).'; $flashType = 'danger'; }
elseif ($error === 'upload') { $flashMsg = 'Gagal memproses file lampiran PDF.'; $flashType = 'danger'; }
elseif ($error === 'not_pdf') { $flashMsg = 'Lampiran harus berupa file PDF yang valid.'; $flashType = 'danger'; }
?>

<div class="d-flex align-items-center gap-3 mb-4">
  <a href="daftar_periode" class="btn-secondary-modern"><i class="bi bi-arrow-left"></i> Kembali</a>
  <h2 class="fw-bold mb-0">Edit Periode Dana</h2>
</div>

<?php if ($flashMsg): ?>
<div class="alert alert-<?= $flashType ?> alert-dismissible fade show" role="alert">
  <?= e($flashMsg) ?>
  <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Tutup"></button>
</div>
<?php endif; ?>

<div class="app-card" style="max-width:800px">
  <form method="post" data-validate novalidate id="formEditPeriode" enctype="multipart/form-data">
    <input type="hidden" name="csrf" value="<?= csrf_token() ?>">

    <div class="row g-3 mb-4">
      <div class="col-md-4">
        <label class="form-label fw-semibold small text-uppercase text-muted">Bulan</label>
        <input class="form-control" value="<?= date('F', mktime(0,0,0,(int)$periode['bulan'],1)) ?>" disabled>
      </div>
      <div class="col-md-4">
        <label class="form-label fw-semibold small text-uppercase text-muted">Tahun</label>
        <input class="form-control" value="<?= $periode['tahun'] ?>" disabled>
      </div>
      <div class="col-md-4">
        <label for="totalPemasukan" class="form-label fw-semibold small text-uppercase text-muted">Total Pemasukan (Rp)</label>
        <input type="number" class="form-control" name="total_pemasukan" id="totalPemasukan" value="<?= (int)$periode['total_pemasukan'] ?>" min="0" max="9999999999999" step="1000" required>
      </div>
    </div>

    <div class="row g-3 mb-4">
      <div class="col-md-6">
        <label class="form-label fw-semibold small text-uppercase text-muted">Saldo Awal (Carry-over)</label>
        <input type="text" class="form-control" id="saldoAwalDisplay" value="<?= rupiah((float)($periode['saldo_awal'] ?? 0)) ?>" readonly>
        <input type="hidden" id="saldoAwal" value="<?= (float)($periode['saldo_awal'] ?? 0) ?>">
        <div class="form-text text-muted">Sisa saldo dari periode broadcast sebelumnya (otomatis).</div>
      </div>
    </div>

    <h6 class="fw-bold mb-3">Rincian Pemanfaatan Dana</h6>

    <div id="rincianContainer">
      <?php foreach ($rincian as $i => $r): ?>
      <div class="rincian-row row g-2 mb-2 align-items-end">
        <div class="col-md-5"><input class="form-control" name="rincian_keterangan[]" value="<?= e($r['keterangan']) ?>" placeholder="Keterangan" required></div>
        <div class="col-md-3"><input type="number" class="form-control rincian-nominal" name="rincian_nominal[]" value="<?= (int)$r['nominal'] ?>" min="0" max="9999999999999" step="1000" required></div>
        <div class="col-md-2">
          <?php if (!empty($r['lampiran'])): ?>
            <a href="../uploads/lampiran/<?= e($r['lampiran']) ?>" target="_blank" class="small text-decoration-none text-primary"><i class="bi bi-file-pdf"></i> <?= e($r['lampiran']) ?></a>
          <?php else: ?>
            <span class="text-muted small">Tidak ada</span>
          <?php endif; ?>
          <input type="file" class="form-control form-control-sm mt-1" name="rincian_lampiran[]" accept="application/pdf" title="Ganti lampiran PDF">
        </div>
        <div class="col-md-2 d-grid"><button type="button" class="btn btn-outline-danger btn-sm remove-row"><i class="bi bi-trash"></i></button></div>
      </div>
      <?php endforeach; ?>
    </div>

    <button type="button" class="btn btn-outline-success btn-sm mb-3" id="addRow"><i class="bi bi-plus-circle"></i> Tambah Baris</button>

    <div class="border-top pt-3 mt-2">
      <div class="d-flex justify-content-between align-items-center mb-2">
        <span class="fw-semibold">Total Pemanfaatan:</span>
        <span class="fw-bold fs-5" id="totalPemanfaatanDisplay">Rp 0</span>
      </div>
      <div class="d-flex justify-content-between align-items-center mb-3">
        <span class="fw-semibold">Sisa Saldo:</span>
        <span class="fw-bold fs-5" id="sisaSaldoDisplay">Rp 0</span>
      </div>
      <div class="alert alert-warning small mb-3" id="saldoAlert" style="display:none">
        <i class="bi bi-exclamation-triangle"></i> Total pemanfaatan melebihi total saldo yang tersedia!
      </div>
    </div>

    <div class="d-flex gap-2">
      <button class="btn-primary-modern" type="submit"><i class="bi bi-check-circle"></i> Update Periode</button>
      <a href="daftar_periode" class="btn-secondary-modern">Batal</a>
    </div>
  </form>
</div>

<script>
(function(){
  const container = document.getElementById('rincianContainer');
  const addBtn = document.getElementById('addRow');
  const pemasukanInput = document.getElementById('totalPemasukan');
  const totalPemDisplay = document.getElementById('totalPemanfaatanDisplay');
  const sisaDisplay = document.getElementById('sisaSaldoDisplay');
  const alertEl = document.getElementById('saldoAlert');

  function recalc() {
    const pemasukan = parseFloat(pemasukanInput.value) || 0;
    const saldoAwal = parseFloat(document.getElementById('saldoAwal').value) || 0;
    let totalPem = 0;
    document.querySelectorAll('.rincian-nominal').forEach(el => {
      totalPem += parseFloat(el.value) || 0;
    });
    totalPemDisplay.textContent = 'Rp ' + totalPem.toLocaleString('id-ID');
    const sisa = saldoAwal + pemasukan - totalPem;
    sisaDisplay.textContent = 'Rp ' + sisa.toLocaleString('id-ID');
    sisaDisplay.className = 'fw-bold fs-5 ' + (sisa >= 0 ? 'text-success' : 'text-danger');
    alertEl.style.display = sisa < 0 ? '' : 'none';
  }

  addBtn.addEventListener('click', function() {
    const row = document.createElement('div');
    row.className = 'rincian-row row g-2 mb-2 align-items-end';
    row.innerHTML = '<div class="col-md-5"><input class="form-control" name="rincian_keterangan[]" placeholder="Keterangan" required></div><div class="col-md-3"><input type="number" class="form-control rincian-nominal" name="rincian_nominal[]" placeholder="0" min="0" max="9999999999999" step="1000" required></div><div class="col-md-2"><input type="file" class="form-control form-control-sm" name="rincian_lampiran[]" accept="application/pdf" title="Lampiran PDF (opsional)"></div><div class="col-md-2 d-grid"><button type="button" class="btn btn-outline-danger btn-sm remove-row"><i class="bi bi-trash"></i></button></div>';
    container.appendChild(row);
    row.querySelector('.remove-row').addEventListener('click', function(){ row.remove(); recalc(); });
    row.querySelectorAll('input[type=number]').forEach(el => el.addEventListener('input', recalc));
  });

  container.querySelectorAll('.remove-row').forEach(btn => {
    btn.addEventListener('click', function(){ this.closest('.rincian-row').remove(); recalc(); });
  });
  container.querySelectorAll('input[type=number]').forEach(el => el.addEventListener('input', recalc));
  pemasukanInput.addEventListener('input', recalc);
  recalc();
})();
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
