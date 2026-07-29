<?php
$pageTitle = 'Edit Payroll';
$activeNav = 'payroll';
require_once __DIR__ . '/../includes/header_admin.php';

$id = (int)($_GET['id'] ?? 0);
$row = $pdo->prepare("SELECT * FROM payroll WHERE id = ?");
$row->execute([$id]); $row = $row->fetch();
if (!$row) { echo '<div class="alert alert-danger">Data tidak ditemukan.</div>'; require __DIR__.'/../includes/footer.php'; exit; }

$fields = payroll_fields();
$pegawai = $pdo->query("SELECT id, nip, nama FROM pegawai ORDER BY nama")->fetchAll();
$errors = [];

// Ambil payroll_detail untuk pre-fill form
try {
    $row = array_merge($row, payroll_values_map($id));
} catch (PDOException $e) {
    error_log("Edit Gaji Read Detail Error: " . $e->getMessage());
    if ($e->getCode() === '42S02') {
        die('<div class="alert alert-warning m-5">Terjadi masalah konfigurasi sistem, hubungi administrator.</div>');
    }
    throw $e;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $pid = (int)$_POST['pegawai_id'];
    $periode = $_POST['periode'] ?? '';
    $keterangan = $_POST['keterangan'] ?? null;

    if (!$periode) {
        $errors[] = 'Periode wajib diisi.';
    } else {
        if (preg_match('/^\d{4}-\d{2}$/', $periode)) $periode .= '-01';
        
        $detailVals = [];
        foreach ($fields as $group => $arr) {
            foreach (array_keys($arr) as $f) {
                $detailVals[$f] = (float)($_POST[$f] ?? 0);
            }
        }

        try {
            $pdo->beginTransaction();
            
            // Update metadata
            $pdo->prepare("UPDATE payroll SET pegawai_id = ?, periode = ?, keterangan = ? WHERE id = ?")
                ->execute([$pid, $periode, $keterangan, $id]);
            
            // Upsert all components to payroll_detail
            if ($detailVals) {
                $kMap = $pdo->query("SELECT field_key, id FROM komponen_payroll")->fetchAll(PDO::FETCH_KEY_PAIR);
                $multi = [];
                $multiParams = [];
                foreach ($detailVals as $fk => $v) {
                    if (isset($kMap[$fk])) {
                        $multi[] = "(?,?,?)";
                        $multiParams[] = $id;
                        $multiParams[] = (int)$kMap[$fk];
                        $multiParams[] = $v;
                    }
                }
                if ($multi) {
                    $placeholders = implode(',', $multi);
                    $pdo->prepare("INSERT INTO payroll_detail (payroll_id, komponen_id, nilai) VALUES $placeholders ON DUPLICATE KEY UPDATE nilai=VALUES(nilai)")->execute($multiParams);
                }
                $pdo->prepare("DELETE FROM payroll_detail WHERE payroll_id=? AND nilai=0")->execute([$id]);
            }
            
            $pdo->commit();
            header('Location: dashboard'); exit;
        } catch (PDOException $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $errors[] = 'Gagal simpan: ' . $e->getMessage();
        }
    }
}
?>
<div class="d-flex align-items-center gap-3 mb-4">
  <a href="dashboard" class="btn-secondary-modern"><i class="bi bi-arrow-left"></i> Kembali</a>
  <h2 class="fw-bold mb-0">Edit Payroll</h2>
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
            <label for="editPegawai" class="form-label">Pegawai</label>
            <select id="editPegawai" name="pegawai_id" class="form-select" required>
              <?php foreach($pegawai as $p): ?>
                <option value="<?= $p['id'] ?>" <?= $p['id']==$row['pegawai_id']?'selected':'' ?>><?= e($p['nama']) ?> — <?= e($p['nip']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-md-6">
            <label for="editPeriode" class="form-label">Periode</label>
            <input type="month" id="editPeriode" name="periode" class="form-control" value="<?= e(substr($row['periode'],0,7)) ?>" required>
          </div>
        </div>
        <?php foreach($fields as $group => $arr):
          $cls = ['pendapatan'=>'green','potongan_umum'=>'red','koperasi'=>'yellow'][$group] ?? '';
          $lab = ['pendapatan'=>'Pendapatan','potongan_umum'=>'Potongan Umum','koperasi'=>'Koperasi & Bank'][$group];
        ?>
        <div class="section-label <?= $cls ?> mt-4"><?= $lab ?></div>
        <div class="row g-3">
          <?php foreach($arr as $k=>$label): ?>
            <div class="col-md-4 col-sm-6">
              <label for="ef_<?= $k ?>" class="form-label small"><?= e($label) ?></label>
               <input type="number" step="1" min="0" id="ef_<?= $k ?>" name="<?= $k ?>" value="<?= (int)($row[$k] ?? 0) ?>" class="form-control" data-group="<?= $group ?>">
            </div>
          <?php endforeach; ?>
        </div>
        <?php endforeach; ?>
        <div class="section-label mt-4">Keterangan</div>
        <label for="editKeterangan" class="form-label small">Keterangan Tambahan</label>
        <input type="text" id="editKeterangan" name="keterangan" class="form-control" value="<?= e($row['keterangan'] ?? '') ?>">
      </div>
    </div>
    <div class="col-lg-4">
      <div class="summary-card">
        <h5 class="fw-bold mb-3">Ringkasan Otomatis</h5>
        <div class="d-flex justify-content-between mb-2"><span>Total Pendapatan</span><strong id="sumBruto" class="text-success">Rp 0</strong></div>
        <div class="d-flex justify-content-between mb-3"><span>Total Potongan</span><strong id="sumPot" class="text-danger">Rp 0</strong></div>
        <div class="net mb-3"><div class="label">PENGHASILAN BERSIH</div><div class="value" id="sumNet">Rp 0</div></div>
        <button class="btn-primary-modern w-100"><i class="bi bi-save"></i> Update Data Gaji</button>
      </div>
    </div>
  </div>
</form>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
