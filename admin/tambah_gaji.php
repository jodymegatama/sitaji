<?php
$pageTitle = 'Input Payroll';
$activeNav = 'payroll';
require_once __DIR__ . '/../includes/header_admin.php';

$fields = payroll_fields();
$pegawai = $pdo->query("SELECT id, nip, nama FROM pegawai ORDER BY nama")->fetchAll();
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $pid = (int)($_POST['pegawai_id'] ?? 0);
    $periode = $_POST['periode'] ?? '';
    if (!$pid) $errors[] = 'Pegawai wajib dipilih.';
    if (!$periode) $errors[] = 'Periode wajib diisi.';

    if (!$errors) {
        if (preg_match('/^\d{4}-\d{2}$/', $periode)) $periode .= '-01';

        $cols = ['pegawai_id','periode','keterangan'];
        $vals = [$pid, $periode, $_POST['keterangan'] ?? null];
        $detailVals = [];
        foreach ($fields as $group => $arr) {
            foreach (array_keys($arr) as $f) {
                $v = (float)($_POST[$f] ?? 0);
                $detailVals[$f] = $v;
            }
        }
        $place = implode(',', array_fill(0, count($cols), '?'));
        $sql = "INSERT INTO payroll (".implode(',',$cols).") VALUES ($place)";
        try {
            $pdo->beginTransaction();
            $pdo->prepare($sql)->execute($vals);
            $payrollId = (int)$pdo->lastInsertId();

            if ($detailVals) {
                try {
                    $kMap = $pdo->query("SELECT field_key, id FROM komponen_payroll")->fetchAll(PDO::FETCH_KEY_PAIR);
                    $multi = [];
                    $multiParams = [];
                    foreach ($detailVals as $fk => $v) {
                        if (isset($kMap[$fk])) {
                            $multi[] = "(?,?,?)";
                            $multiParams[] = $payrollId;
                            $multiParams[] = (int)$kMap[$fk];
                            $multiParams[] = $v;
                        }
                    }
                    if ($multi) {
                        $placeholders = implode(',', $multi);
                        $pdo->prepare("INSERT INTO payroll_detail (payroll_id, komponen_id, nilai) VALUES $placeholders ON DUPLICATE KEY UPDATE nilai=VALUES(nilai)")->execute($multiParams);
                    }
                    $pdo->prepare("DELETE FROM payroll_detail WHERE payroll_id=? AND nilai=0")->execute([$payrollId]);
                } catch (PDOException $e) {
                    if ($e->getCode() === '42S02') {
                        throw new Exception('Terjadi masalah konfigurasi sistem (payroll_detail missing), hubungi administrator.');
                    }
                    throw $e;
                }
            }

            $pdo->commit();
            header('Location: dashboard'); exit;
        } catch (PDOException $e) {
            $pdo->rollBack();
            if ($e->getCode() === '23000' && str_contains($e->getMessage(), 'uniq_peg_periode')) {
                $errors[] = 'Data payroll untuk pegawai dan periode ini sudah ada.';
            } else {
                $errors[] = 'Gagal simpan: ' . $e->getMessage();
            }
        }
    }
}
?>
<div class="d-flex align-items-center gap-3 mb-4">
  <a href="dashboard" class="btn-secondary-modern"><i class="bi bi-arrow-left"></i> Kembali</a>
  <h2 class="fw-bold mb-0">Input Payroll Spesifik</h2>
</div>

<?php if ($errors): ?><div class="alert alert-danger"><?= e(implode(' ', $errors)) ?></div><?php endif; ?>

<form id="payrollForm" method="post" data-validate novalidate>
  <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
  <div class="row g-4">
    <div class="col-lg-8">
      <div class="app-card">
        <div class="section-label">Identitas Pegawai</div>
        <div class="row g-3">
          <div class="col-md-6">
            <label for="pegawai_id" class="form-label">Pilih Pegawai *</label>
            <select name="pegawai_id" id="pegawai_id" class="form-select" required>
              <option value="">-- Cari Nama atau NIP --</option>
              <?php foreach($pegawai as $p): ?>
                <option value="<?= $p['id'] ?>"><?= e($p['nama']) ?> — <?= e($p['nip']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-md-6">
            <label for="periode_gaji" class="form-label">Periode Gaji (Bulan/Tahun) *</label>
            <input type="month" id="periode_gaji" name="periode" class="form-control" required>
          </div>
        </div>

        <div class="section-label green mt-4">Pendapatan (Earnings)</div>
        <div class="row g-3">
          <?php foreach($fields['pendapatan'] as $k=>$label): ?>
            <div class="col-md-6">
              <label for="f_<?= $k ?>" class="form-label"><?= e($label) ?></label>
              <div class="input-group"><span class="input-group-text">Rp</span>
                 <input type="number" step="1" min="0" id="f_<?= $k ?>" name="<?= $k ?>" value="0" class="form-control" data-group="pendapatan">
              </div>
            </div>
          <?php endforeach; ?>
        </div>

        <div class="section-label red mt-4">Potongan Umum</div>
        <div class="row g-3">
           <?php foreach($fields['potongan_umum'] as $k=>$label): ?>
             <div class="col-md-3 col-sm-6">
               <label for="f_<?= $k ?>" class="form-label small"><?= e($label) ?></label>
               <input type="number" step="1" min="0" id="f_<?= $k ?>" name="<?= $k ?>" value="0" class="form-control" data-group="potongan_umum">
             </div>
           <?php endforeach; ?>
        </div>

        <div class="section-label yellow mt-4">Koperasi, Bank & Pinjaman</div>
        <div class="row g-3">
           <?php foreach($fields['koperasi'] as $k=>$label): ?>
             <div class="col-md-4 col-sm-6">
               <label for="f_<?= $k ?>" class="form-label small"><?= e($label) ?></label>
               <input type="number" step="1" min="0" id="f_<?= $k ?>" name="<?= $k ?>" value="0" class="form-control" data-group="koperasi">
             </div>
           <?php endforeach; ?>
        </div>

        <div class="section-label mt-4">Keterangan Tambahan</div>
        <label for="keterangan" class="form-label small">Penggunaan Infaq (Keterangan)</label>
        <input type="text" id="keterangan" name="keterangan" class="form-control" placeholder="Contoh: Infaq Anak Yatim / Pembangunan Gedung">
      </div>
    </div>

    <div class="col-lg-4">
      <div class="summary-card">
        <h5 class="fw-bold mb-3">Ringkasan Otomatis</h5>
        <div class="d-flex justify-content-between mb-2"><span>Total Pendapatan</span><strong id="sumBruto" class="text-success">Rp 0</strong></div>
        <div class="d-flex justify-content-between mb-3"><span>Total Potongan</span><strong id="sumPot" class="text-danger">Rp 0</strong></div>
        <div class="net mb-3">
          <div class="label">PENGHASILAN BERSIH</div>
          <div class="value" id="sumNet">Rp 0</div>
        </div>
        <button class="btn-primary-modern w-100"><i class="bi bi-check-circle"></i> Simpan & Posting Gaji</button>
        <p class="text-muted small text-center mt-2 mb-0">Pastikan semua angka sudah sesuai dengan rincian audit sebelum menyimpan.</p>
      </div>
    </div>
  </div>
</form>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
