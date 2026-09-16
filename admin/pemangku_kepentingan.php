<?php
$pageTitle = 'Pemangku Kepentingan';
$activeNav = 'pemangku';
require_once __DIR__ . '/../includes/header_admin.php';

$activeTab = $_GET['tab'] ?? 'daftar';

// === Tab 1: Daftar Pemangku ===
$list = $pdo->query("
    SELECT pk.*, u.username, u.created_at AS user_created,
           GROUP_CONCAT(kp.nama SEPARATOR ', ') AS komponen_nama,
           COUNT(kp.id) AS komponen_count
    FROM pemangku_kepentingan pk
    JOIN users u ON u.id = pk.user_id
    LEFT JOIN pemangku_kepentingan_komponen pkc ON pkc.pemangku_kepentingan_id = pk.id
    LEFT JOIN komponen_payroll kp ON kp.id = pkc.komponen_id
    GROUP BY pk.id
    ORDER BY pk.nama ASC
")->fetchAll();
$total = count($list);

// === Tab 2: Approval Revisi ===
$pendingList = $pdo->query("
    SELECT dr.id AS revisi_id, dr.periode_id, dr.data_baru, dr.alasan, dr.status,
           dr.created_at, dr.disetujui_oleh, dr.catatan_admin, dr.diproses_at,
           dp.bulan, dp.tahun, dp.total_pemasukan, dp.status AS periode_status,
           dp.broadcast_at, dp.is_revisi,
           pk.nama AS nama_pemangku,
           u.username AS diajukan_username
    FROM dana_revisi_request dr
    JOIN dana_periode dp ON dp.id = dr.periode_id
    JOIN pemangku_kepentingan pk ON pk.id = dp.pemangku_kepentingan_id
    JOIN users u ON u.id = dr.diajukan_oleh
    WHERE dr.status = 'pending'
    ORDER BY dr.created_at ASC
")->fetchAll();
$totalPending = count($pendingList);

$historyList = $pdo->query("
    SELECT dr.id AS revisi_id, dr.periode_id, dr.status, dr.created_at, dr.diproses_at,
           dr.catatan_admin,
           dp.bulan, dp.tahun,
           pk.nama AS nama_pemangku,
           u.username AS diajukan_username,
           admin.username AS diproses_username
    FROM dana_revisi_request dr
    JOIN dana_periode dp ON dp.id = dr.periode_id
    JOIN pemangku_kepentingan pk ON pk.id = dp.pemangku_kepentingan_id
    JOIN users u ON u.id = dr.diajukan_oleh
    LEFT JOIN users admin ON admin.id = dr.disetujui_oleh
    WHERE dr.status != 'pending'
    ORDER BY dr.diproses_at DESC
    LIMIT 50
")->fetchAll();

// === Tab 3: Approval Penghapusan ===
$hapusPendingList = $pdo->query("
    SELECT hr.id AS hapus_id, hr.periode_id, hr.alasan, hr.status,
           hr.created_at, hr.diproses_at, hr.catatan_admin,
           hr.periode_label, hr.nama_pemangku, hr.total_pemasukan, hr.total_pemanfaatan,
           dp.status AS periode_status,
           u.username AS diajukan_username
    FROM dana_hapus_request hr
    LEFT JOIN dana_periode dp ON dp.id = hr.periode_id
    JOIN users u ON u.id = hr.diajukan_oleh
    WHERE hr.status = 'pending'
    ORDER BY hr.created_at ASC
")->fetchAll();
$totalHapusPending = count($hapusPendingList);

$hapusHistoryList = $pdo->query("
    SELECT hr.id AS hapus_id, hr.status, hr.created_at, hr.diproses_at, hr.catatan_admin,
           hr.periode_label, hr.nama_pemangku, hr.total_pemasukan, hr.total_pemanfaatan,
           u.username AS diajukan_username,
           admin.username AS diproses_username
    FROM dana_hapus_request hr
    JOIN users u ON u.id = hr.diajukan_oleh
    LEFT JOIN users admin ON admin.id = hr.disetujui_oleh
    WHERE hr.status != 'pending'
    ORDER BY hr.diproses_at DESC
    LIMIT 50
")->fetchAll();

// === Flash messages (gabungan dari kedua sistem) ===
$error = $_GET['error'] ?? '';
$success = $_GET['success'] ?? '';
$flashMsg = '';
$flashType = '';
if ($error === 'incomplete') { $flashMsg = 'Data tidak lengkap. Nama, username, dan password wajib diisi.'; $flashType = 'danger'; }
elseif ($error === 'short_password') { $flashMsg = 'Password minimal 6 karakter.'; $flashType = 'danger'; }
elseif ($error === 'no_komponen') { $flashMsg = 'Pilih minimal 1 komponen potongan gaji.'; $flashType = 'danger'; }
elseif ($error === 'duplicate_username') { $flashMsg = 'Username sudah digunakan. Pilih username lain.'; $flashType = 'danger'; }
elseif ($error === 'db') { $flashMsg = 'Terjadi kesalahan database. Silakan coba lagi.'; $flashType = 'danger'; }
elseif ($error === 'has_periode') { $flashMsg = 'Pemangku kepentingan memiliki riwayat data periode. Nonaktifkan saja jika tidak diperlukan.'; $flashType = 'warning'; }
elseif ($error === 'delete_failed') { $flashMsg = 'Gagal menghapus pemangku kepentingan.'; $flashType = 'danger'; }
elseif ($error === 'not_found') { $flashMsg = 'Revisi tidak ditemukan.'; $flashType = 'danger'; }
elseif ($error === 'not_pending') { $flashMsg = 'Revisi ini sudah diproses sebelumnya.'; $flashType = 'warning'; }
elseif ($error === 'invalid_action') { $flashMsg = 'Aksi tidak valid.'; $flashType = 'danger'; }
elseif ($error === 'reject_reason_required') { $flashMsg = 'Alasan penolakan wajib diisi.'; $flashType = 'danger'; }
elseif ($error === 'nominal_over') { $flashMsg = 'Nominal terlalu besar. Maksimal 9.999.999.999.999 (13 digit).'; $flashType = 'danger'; }
elseif ($success === 'created') { $flashMsg = 'Pemangku kepentingan berhasil ditambahkan.'; $flashType = 'success'; }
elseif ($success === 'updated') { $flashMsg = 'Data pemangku kepentingan berhasil diperbarui.'; $flashType = 'success'; }
elseif ($success === 'deleted') { $flashMsg = 'Pemangku kepentingan berhasil dihapus.'; $flashType = 'success'; }
elseif ($success === 'toggled') { $flashMsg = 'Status akun pemangku kepentingan berhasil diubah.'; $flashType = 'success'; }
elseif ($success === 'approved') { $flashMsg = 'Revisi berhasil disetujui dan diterapkan.'; $flashType = 'success'; }
elseif ($success === 'rejected') { $flashMsg = 'Revisi berhasil ditolak.'; $flashType = 'info'; }
elseif ($success === 'hapus_approved') { $flashMsg = 'Penghapusan periode disetujui. Data telah dihapus permanen.'; $flashType = 'success'; }
elseif ($success === 'hapus_rejected') { $flashMsg = 'Penghapusan periode ditolak. Data periode dikembalikan.'; $flashType = 'info'; }

$bulanNama = [1=>'Januari',2=>'Februari',3=>'Maret',4=>'April',5=>'Mei',6=>'Juni',
              7=>'Juli',8=>'Agustus',9=>'September',10=>'Oktober',11=>'November',12=>'Desember'];
?>
<?php if ($flashMsg): ?>
<div class="alert alert-<?= $flashType ?> alert-dismissible fade show" role="alert">
  <?= e($flashMsg) ?>
  <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Tutup"></button>
</div>
<?php endif; ?>

<div class="d-flex justify-content-between align-items-end flex-wrap gap-3 mb-4">
  <div>
    <h2 class="fw-bold mb-1">Pemangku Kepentingan</h2>
    <p class="text-muted mb-0">Kelola daftar pemangku kepentingan dan approval revisi dana.</p>
  </div>
  <?php if ($activeTab === 'daftar'): ?>
  <button class="btn-primary-modern" data-bs-toggle="modal" data-bs-target="#addModal"><i class="bi bi-plus-circle"></i> Tambah Pemangku</button>
  <?php endif; ?>
</div>

<!-- Nav Tabs -->
<ul class="nav nav-tabs mb-4">
  <li class="nav-item">
    <a class="nav-link <?= $activeTab === 'daftar' ? 'active' : '' ?>" href="?tab=daftar">
      <i class="bi bi-building me-1"></i> Daftar Pemangku
    </a>
  </li>
  <li class="nav-item">
    <a class="nav-link <?= $activeTab === 'approval' ? 'active' : '' ?>" href="?tab=approval">
      <i class="bi bi-check2-square me-1"></i> Approval Revisi
      <?php if ($totalPending > 0): ?>
      <span class="badge bg-warning text-dark ms-1"><?= $totalPending ?></span>
      <?php endif; ?>
    </a>
  </li>
  <li class="nav-item">
    <a class="nav-link <?= $activeTab === 'hapus' ? 'active' : '' ?>" href="?tab=hapus">
      <i class="bi bi-trash3 me-1"></i> Approval Penghapusan
      <?php if ($totalHapusPending > 0): ?>
      <span class="badge bg-danger ms-1"><?= $totalHapusPending ?></span>
      <?php endif; ?>
    </a>
  </li>
</ul>

<div class="tab-content">
  <!-- Tab: Daftar Pemangku -->
  <div class="tab-pane fade <?= $activeTab === 'daftar' ? 'show active' : '' ?>" id="daftar">
    <div class="app-card">
      <div class="d-flex justify-content-between align-items-center mb-3">
        <h5 class="fw-bold mb-0">Daftar Pemangku Kepentingan</h5>
        <span class="text-primary small fw-semibold">Total: <?= $total ?> data</span>
      </div>
      <table class="table data-table align-middle">
        <thead>
          <tr>
            <th>Nama</th>
            <th>Username</th>
            <th>Komponen Potongan</th>
            <th>Status Akun</th>
            <th class="text-end">Aksi</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($list as $pk): ?>
            <tr>
              <td><i class="bi bi-building me-2 text-primary"></i><div class="fw-semibold"><?= e($pk['nama']) ?></div></td>
              <td><code><?= e($pk['username']) ?></code></td>
              <td>
                <?php if ($pk['komponen_count'] > 0): ?>
                  <span class="badge bg-primary bg-opacity-10 text-primary"><?= (int)$pk['komponen_count'] ?> komponen</span>
                  <div class="text-muted small mt-1" style="max-width:300px"><?= e($pk['komponen_nama']) ?></div>
                <?php else: ?>
                  <span class="text-muted small fst-italic">Belum ada mapping</span>
                <?php endif; ?>
              </td>
              <td>
                <?php if ($pk['aktif']): ?>
                  <span class="badge bg-success">Aktif</span>
                <?php else: ?>
                  <span class="badge bg-secondary">Nonaktif</span>
                <?php endif; ?>
              </td>
              <td class="text-end">
                <a class="btn-action-modern" href="edit_pemangku?id=<?= $pk['id'] ?>" title="Edit"><i class="bi bi-pencil"></i></a>
                <form method="post" action="hapus_pemangku" style="display:inline" onsubmit="return confirm('Hapus pemangku kepentingan ini beserta semua mapping komponennya?')">
                  <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
                  <input type="hidden" name="id" value="<?= $pk['id'] ?>">
                  <button type="submit" class="btn-action-modern text-danger" title="Hapus"><i class="bi bi-trash"></i></button>
                </form>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>

  <!-- Tab: Approval Revisi -->
  <div class="tab-pane fade <?= $activeTab === 'approval' ? 'show active' : '' ?>" id="approval">
    <?php if ($totalPending === 0): ?>
    <div class="app-card">
      <div class="text-center py-5">
        <i class="bi bi-check-circle text-success" style="font-size:3rem"></i>
        <p class="text-muted mt-3 mb-0">Tidak ada revisi yang menunggu persetujuan.</p>
      </div>
    </div>
    <?php else: ?>
    <?php foreach ($pendingList as $idx => $rev):
        $periodeId = (int)$rev['periode_id'];
        $revisiId = (int)$rev['revisi_id'];
        $dataBaru = json_decode($rev['data_baru'], true);
        $newPemasukan = (float)($dataBaru['total_pemasukan'] ?? 0);
        $newRincian = $dataBaru['pemanfaatan'] ?? [];

        $curStmt = $pdo->prepare("SELECT * FROM dana_periode WHERE id = ?");
        $curStmt->execute([$periodeId]);
        $curPeriode = $curStmt->fetch();

        $curRincianStmt = $pdo->prepare("SELECT keterangan, nominal, lampiran FROM dana_pemanfaatan WHERE periode_id = ? ORDER BY id ASC");
        $curRincianStmt->execute([$periodeId]);
        $curRincian = $curRincianStmt->fetchAll();

        $curPemasukan = (float)$curPeriode['total_pemasukan'];
        $curTotalPem = array_sum(array_column($curRincian, 'nominal'));
        $newTotalPem = array_sum(array_column($newRincian, 'nominal'));
        $collapseId = "revDetail_" . $revisiId;

        // === Diff engine: match baris lama <-> baru berdasarkan keterangan (normalized) ===
        $curPool = [];
        foreach ($curRincian as $r) {
            $k = mb_strtolower(trim((string)$r['keterangan']));
            $curPool[$k][] = $r;
        }
        $diffRows = [];
        $dNom = 0; $dLampGanti = 0; $dLampBaru = 0; $dLampHapus = 0; $dBarisBaru = 0;
        foreach ($newRincian as $r) {
            $k = mb_strtolower(trim((string)($r['keterangan'] ?? '')));
            $old = null;
            if (!empty($curPool[$k])) { $old = array_shift($curPool[$k]); }
            $st = ['old' => $old, 'new' => $r, 'nominal_berubah' => false, 'lamp_status' => 'sama'];
            if ($old === null) {
                $st['status'] = 'baris_baru';
                $dBarisBaru++;
                if (!empty($r['lampiran'])) { $st['lamp_status'] = 'baru'; $dLampBaru++; }
            } else {
                $st['status'] = 'ada';
                $st['nominal_berubah'] = ((float)$old['nominal'] !== (float)($r['nominal'] ?? 0));
                if ($st['nominal_berubah']) $dNom++;
                $oldL = $old['lampiran'] ?? null;
                $newL = $r['lampiran'] ?? null;
                if ($newL && $oldL && $newL !== $oldL) { $st['lamp_status'] = 'diganti'; $dLampGanti++; }
                elseif ($newL && !$oldL) { $st['lamp_status'] = 'baru'; $dLampBaru++; }
                elseif (!$newL && $oldL) { $st['lamp_status'] = 'dihapus'; $dLampHapus++; }
            }
            $diffRows[] = $st;
        }
        $rowsDihapus = [];
        foreach ($curPool as $arr) {
            foreach ($arr as $r) { $rowsDihapus[] = $r; }
        }
        $dBarisHapus = count($rowsDihapus);
        $dLampTotal = $dLampGanti + $dLampBaru + $dLampHapus;
        $hanyaLampiran = ($dNom === 0 && $dBarisBaru === 0 && $dBarisHapus === 0 && $dLampTotal > 0);
    ?>
    <div class="app-card mb-4" style="border-left:4px solid var(--warning)">
      <div class="d-flex justify-content-between align-items-start flex-wrap gap-3">
        <div>
          <h5 class="fw-bold mb-1">
            <i class="bi bi-building text-primary me-1"></i> <?= e($rev['nama_pemangku']) ?>
            <span class="text-muted fw-normal">—</span>
            <?= $bulanNama[(int)$rev['bulan']] ?> <?= $rev['tahun'] ?>
          </h5>
          <div class="text-muted small">
            Diajukan oleh <strong><?= e($rev['diajukan_username']) ?></strong>
            pada <?= date('d M Y H:i', strtotime($rev['created_at'])) ?>
          </div>
          <div class="mt-2">
            <span class="badge bg-warning text-dark"><i class="bi bi-clock"></i> Menunggu Persetujuan</span>
          </div>
        </div>
        <div class="d-flex gap-2">
          <button class="btn btn-success btn-sm" style="border-radius:999px;padding:6px 16px"
                  data-bs-toggle="collapse" data-bs-target="#<?= $collapseId ?>">
            <i class="bi bi-eye"></i> Tinjau
          </button>
        </div>
      </div>

      <div class="mt-3 p-3 rounded" style="background:var(--primary-soft)">
        <div class="small fw-semibold text-muted text-uppercase mb-1">Alasan Revisi</div>
        <div class="small"><?= nl2br(e($rev['alasan'] ?? '-')) ?></div>
      </div>

      <?php if ($dNom + $dLampTotal + $dBarisBaru + $dBarisHapus > 0): ?>
      <div class="mt-2 d-flex flex-wrap gap-2 align-items-center">
        <span class="small fw-semibold text-muted text-uppercase">Ringkasan Perubahan:</span>
        <?php if ($dLampGanti): ?><span class="badge text-bg-info"><i class="bi bi-file-earmark-pdf"></i> Lampiran diganti: <?= $dLampGanti ?></span><?php endif; ?>
        <?php if ($dLampBaru): ?><span class="badge text-bg-success"><i class="bi bi-file-earmark-plus"></i> Lampiran baru: <?= $dLampBaru ?></span><?php endif; ?>
        <?php if ($dLampHapus): ?><span class="badge text-bg-danger"><i class="bi bi-file-earmark-x"></i> Lampiran dihapus: <?= $dLampHapus ?></span><?php endif; ?>
        <?php if ($dNom): ?><span class="badge text-bg-warning"><i class="bi bi-cash-coin"></i> Nominal berubah: <?= $dNom ?></span><?php endif; ?>
        <?php if ($dBarisBaru): ?><span class="badge text-bg-success"><i class="bi bi-plus-circle"></i> Baris baru: <?= $dBarisBaru ?></span><?php endif; ?>
        <?php if ($dBarisHapus): ?><span class="badge text-bg-danger"><i class="bi bi-dash-circle"></i> Baris dihapus: <?= $dBarisHapus ?></span><?php endif; ?>
      </div>
      <?php else: ?>
      <div class="mt-2">
        <span class="badge text-bg-secondary"><i class="bi bi-dash-circle"></i> Tidak ada perubahan data</span>
      </div>
      <?php endif; ?>

      <?php if ($hanyaLampiran): ?>
      <div class="alert alert-info d-flex align-items-center gap-2 mt-2 mb-0 py-2 small" role="alert">
        <i class="bi bi-info-circle-fill"></i>
        <span>Revisi ini <strong>hanya mengubah lampiran PDF</strong> — tidak ada perubahan angka. Bandingkan kedua file di kolom Lampiran sebelum menyetujui.</span>
      </div>
      <?php endif; ?>

      <div class="collapse <?= $idx === 0 ? 'show' : '' ?>" id="<?= $collapseId ?>">
        <div class="mt-3">
          <div class="row g-3 mb-3">
            <div class="col-md-6">
              <div class="p-3 rounded" style="background:#fef2f2;border:1px solid #fecaca">
                <h6 class="fw-bold text-danger small text-uppercase mb-2">
                  <i class="bi bi-x-circle"></i> Data Saat Ini (Lama)
                </h6>
                <div class="mb-2">
                  <span class="text-muted small">Total Pemasukan:</span>
                  <span class="fw-bold text-danger"><?= rupiah($curPemasukan) ?></span>
                </div>
                <?php if (!empty($curRincian)): ?>
                <table class="table table-sm mb-0 align-middle" style="font-size:.8rem">
                  <thead><tr><th>Keterangan</th><th class="text-end">Nominal</th><th class="text-center">Lampiran</th></tr></thead>
                  <tbody>
                    <?php foreach ($diffRows as $st): if ($st['old'] === null) continue; $o = $st['old'];
                        $rowCls = $st['nominal_berubah'] ? 'table-warning' : (in_array($st['lamp_status'], ['diganti','dihapus'], true) ? 'table-info' : '');
                    ?>
                    <tr class="<?= $rowCls ?>">
                      <td><?= e($o['keterangan']) ?><?php if ($st['nominal_berubah']): ?> <span class="badge text-bg-warning">nominal berubah</span><?php endif; ?></td>
                      <td class="text-end"><?= rupiah($o['nominal']) ?></td>
                      <td class="text-center">
                        <?php if (!empty($o['lampiran'])): ?>
                          <a href="../uploads/lampiran/<?= e($o['lampiran']) ?>" target="_blank" class="text-muted text-decoration-none" title="Lampiran lama: <?= e($o['lampiran']) ?>"><i class="bi bi-file-pdf"></i> Lama</a>
                        <?php else: ?><span class="text-muted">—</span><?php endif; ?>
                      </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php foreach ($rowsDihapus as $o): ?>
                    <tr class="table-danger">
                      <td><?= e($o['keterangan']) ?> <span class="badge text-bg-danger">baris dihapus</span></td>
                      <td class="text-end"><?= rupiah($o['nominal']) ?></td>
                      <td class="text-center">
                        <?php if (!empty($o['lampiran'])): ?>
                          <a href="../uploads/lampiran/<?= e($o['lampiran']) ?>" target="_blank" class="text-muted text-decoration-none" title="Lampiran lama: <?= e($o['lampiran']) ?>"><i class="bi bi-file-pdf"></i> Lama</a>
                        <?php else: ?><span class="text-muted">—</span><?php endif; ?>
                      </td>
                    </tr>
                    <?php endforeach; ?>
                    <tr class="table-active fw-bold"><td>Total</td><td class="text-end"><?= rupiah($curTotalPem) ?></td><td></td></tr>
                  </tbody>
                </table>
                <?php else: ?>
                <p class="text-muted small fst-italic mb-0">Belum ada rincian</p>
                <?php endif; ?>
              </div>
            </div>
            <div class="col-md-6">
              <div class="p-3 rounded" style="background:#f0fdf4;border:1px solid #bbf7d0">
                <h6 class="fw-bold text-success small text-uppercase mb-2">
                  <i class="bi bi-check-circle"></i> Data Usulan (Baru)
                </h6>
                <div class="mb-2">
                  <span class="text-muted small">Total Pemasukan:</span>
                  <span class="fw-bold text-success"><?= rupiah($newPemasukan) ?></span>
                </div>
                <?php if (!empty($newRincian)): ?>
                <table class="table table-sm mb-0 align-middle" style="font-size:.8rem">
                  <thead><tr><th>Keterangan</th><th class="text-end">Nominal</th><th class="text-center">Lampiran</th></tr></thead>
                  <tbody>
                    <?php foreach ($diffRows as $st): $n = $st['new'];
                        $rowCls = $st['old'] === null ? 'table-success' : ($st['nominal_berubah'] ? 'table-warning' : ($st['lamp_status'] !== 'sama' ? 'table-info' : ''));
                    ?>
                    <tr class="<?= $rowCls ?>">
                      <td><?= e($n['keterangan']) ?>
                        <?php if ($st['old'] === null): ?> <span class="badge text-bg-success">baris baru</span><?php endif; ?>
                        <?php if ($st['nominal_berubah']): ?> <span class="badge text-bg-warning">nominal diubah</span><?php endif; ?>
                      </td>
                      <td class="text-end"><?= rupiah($n['nominal']) ?></td>
                      <td class="text-center">
                        <?php if (!empty($n['lampiran'])): ?>
                          <a href="../uploads/lampiran/<?= e($n['lampiran']) ?>" target="_blank" class="text-primary text-decoration-none" title="Lampiran baru: <?= e($n['lampiran']) ?>"><i class="bi bi-file-pdf"></i> PDF</a>
                          <?php if ($st['lamp_status'] === 'diganti'): ?> <span class="badge text-bg-info">diganti</span>
                          <?php elseif ($st['lamp_status'] === 'baru'): ?> <span class="badge text-bg-success">baru</span><?php endif; ?>
                        <?php elseif ($st['lamp_status'] === 'dihapus'): ?>
                          <span class="badge text-bg-danger">dihapus</span>
                        <?php else: ?><span class="text-muted">—</span><?php endif; ?>
                      </td>
                    </tr>
                    <?php endforeach; ?>
                    <tr class="table-active fw-bold"><td>Total</td><td class="text-end"><?= rupiah($newTotalPem) ?></td><td></td></tr>
                  </tbody>
                </table>
                <?php else: ?>
                <p class="text-muted small fst-italic mb-0">Tidak ada rincian</p>
                <?php endif; ?>
              </div>
            </div>
          </div>

          <form method="post" action="approve_revisi" data-validate novalidate>
            <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
            <input type="hidden" name="revisi_id" value="<?= $revisiId ?>">
            <input type="hidden" name="action" value="approve">
            <div class="mb-3">
              <label for="catatanAdmin" class="form-label fw-semibold small text-uppercase text-muted">Catatan Admin (opsional untuk setujui, wajib untuk tolak)</label>
              <textarea id="catatanAdmin" class="form-control" name="catatan_admin" rows="2" placeholder="Catatan untuk pemangku kepentingan..."></textarea>
            </div>
            <div class="d-flex gap-2">
              <button type="submit" class="btn btn-success" style="border-radius:999px;padding:8px 20px">
                <i class="bi bi-check-circle"></i> Setujui Revisi
              </button>
              <button type="button" class="btn btn-danger" style="border-radius:999px;padding:8px 20px"
                      onclick="if(confirm('Tolak revisi ini? Data tidak akan berubah.')){this.closest('form').querySelector('input[name=action]').value='reject';this.closest('form').submit();}">
                <i class="bi bi-x-circle"></i> Tolak Revisi
              </button>
            </div>
          </form>
        </div>
      </div>
    </div>
    <?php endforeach; ?>
    <?php endif; ?>

    <?php if (!empty($historyList)): ?>
    <div class="app-card mt-4">
      <div class="d-flex justify-content-between align-items-center mb-3">
        <h5 class="fw-bold mb-0"><i class="bi bi-clock-history text-muted"></i> Riwayat Revisi</h5>
      </div>
      <table class="table data-table align-middle" data-empty-message="Belum ada riwayat revisi." data-order='[[4,"desc"]]'>
        <thead>
          <tr><th>Pemangku</th><th>Periode</th><th>Status</th><th>Diajukan</th><th>Diproses</th><th>Oleh</th><th>Catatan</th></tr>
        </thead>
        <tbody>
          <?php foreach ($historyList as $h): ?>
          <tr>
            <td class="fw-semibold"><?= e($h['nama_pemangku']) ?></td>
            <td><?= $bulanNama[(int)$h['bulan']] ?> <?= $h['tahun'] ?></td>
            <td>
              <?php if ($h['status'] === 'disetujui'): ?>
                <span class="badge bg-success">Disetujui</span>
              <?php else: ?>
                <span class="badge bg-danger">Ditolak</span>
              <?php endif; ?>
            </td>
            <td class="small" data-order="<?= $h['created_at'] ? date('Y-m-d H:i:s', strtotime($h['created_at'])) : '' ?>"><?= date('d M Y H:i', strtotime($h['created_at'])) ?></td>
            <td class="small" data-order="<?= $h['diproses_at'] ? date('Y-m-d H:i:s', strtotime($h['diproses_at'])) : '' ?>"><?= $h['diproses_at'] ? date('d M Y H:i', strtotime($h['diproses_at'])) : '-' ?></td>
            <td class="small"><?= e($h['diproses_username'] ?? '-') ?></td>
            <td class="small" style="max-width:200px"><?= e($h['catatan_admin'] ?? '-') ?></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php endif; ?>
  </div>

  <!-- Tab: Approval Penghapusan -->
  <div class="tab-pane fade <?= $activeTab === 'hapus' ? 'show active' : '' ?>" id="hapus">
    <?php if ($totalHapusPending === 0): ?>
    <div class="app-card">
      <div class="text-center py-5">
        <i class="bi bi-check-circle text-success" style="font-size:3rem"></i>
        <p class="text-muted mt-3 mb-0">Tidak ada penghapusan yang menunggu persetujuan.</p>
      </div>
    </div>
    <?php else: ?>
    <?php foreach ($hapusPendingList as $hapus):
        $hapusId = (int)$hapus['hapus_id'];
    ?>
    <div class="app-card mb-4" style="border-left:4px solid var(--danger,#dc3545)">
      <div class="d-flex justify-content-between align-items-start flex-wrap gap-3">
        <div>
          <h5 class="fw-bold mb-1">
            <i class="bi bi-building text-primary me-1"></i> <?= e($hapus['nama_pemangku']) ?>
            <span class="text-muted fw-normal">—</span>
            <?= e($hapus['periode_label']) ?>
          </h5>
          <div class="text-muted small">
            Diajukan oleh <strong><?= e($hapus['diajukan_username']) ?></strong>
            pada <?= date('d M Y H:i', strtotime($hapus['created_at'])) ?>
          </div>
          <div class="mt-2">
            <span class="badge bg-danger"><i class="bi bi-clock"></i> Menunggu Persetujuan Penghapusan</span>
          </div>
        </div>
      </div>

      <div class="mt-3 p-3 rounded" style="background:#fef2f2;border:1px solid #fecaca">
        <div class="small fw-semibold text-muted text-uppercase mb-1">Alasan Penghapusan</div>
        <div class="small"><?= nl2br(e($hapus['alasan'] ?? '-')) ?></div>
      </div>

      <div class="mt-3">
        <div class="row g-3 mb-3">
          <div class="col-md-6">
            <div class="small text-muted">Total Pemasukan</div>
            <div class="fw-bold text-success fs-5"><?= rupiah($hapus['total_pemasukan']) ?></div>
          </div>
          <div class="col-md-6">
            <div class="small text-muted">Total Pemanfaatan</div>
            <div class="fw-bold text-danger fs-5"><?= rupiah($hapus['total_pemanfaatan']) ?></div>
          </div>
        </div>

        <div class="alert alert-warning small mb-3">
          <i class="bi bi-exclamation-triangle"></i>
          Menyetujui akan menghapus <strong>seluruh data periode</strong> secara permanen
          (rincian pemanfaatan, notifikasi dibaca, riwayat revisi). Tidak dapat dibatalkan.
        </div>

        <form method="post" action="approve_hapus" data-validate novalidate>
          <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
          <input type="hidden" name="hapus_id" value="<?= $hapusId ?>">
          <input type="hidden" name="action" value="approve">
          <div class="mb-3">
            <label class="form-label fw-semibold small text-uppercase text-muted">
              Catatan Admin (opsional untuk setujui, wajib untuk tolak)
            </label>
            <textarea class="form-control" name="catatan_admin" rows="2"
                      placeholder="Catatan untuk pemangku kepentingan..."></textarea>
          </div>
          <div class="d-flex gap-2">
            <button type="submit" class="btn btn-danger" style="border-radius:999px;padding:8px 20px">
              <i class="bi bi-check-circle"></i> Setujui Penghapusan
            </button>
            <button type="button" class="btn btn-secondary" style="border-radius:999px;padding:8px 20px"
                    onclick="if(confirm('Tolak penghapusan ini? Data periode akan dikembalikan ke broadcast.')){this.closest('form').querySelector('input[name=action]').value='reject';this.closest('form').submit();}">
              <i class="bi bi-x-circle"></i> Tolak
            </button>
          </div>
        </form>
      </div>
    </div>
    <?php endforeach; ?>
    <?php endif; ?>

    <?php if (!empty($hapusHistoryList)): ?>
    <div class="app-card mt-4">
      <div class="d-flex justify-content-between align-items-center mb-3">
        <h5 class="fw-bold mb-0"><i class="bi bi-clock-history text-muted"></i> Riwayat Penghapusan</h5>
      </div>
      <table class="table data-table align-middle" data-empty-message="Belum ada riwayat penghapusan." data-order='[[4,"desc"]]'>
        <thead>
          <tr><th>Pemangku</th><th>Periode</th><th>Status</th><th>Diajukan</th><th>Diproses</th><th>Oleh</th><th>Catatan</th></tr>
        </thead>
        <tbody>
          <?php foreach ($hapusHistoryList as $h): ?>
          <tr>
            <td class="fw-semibold"><?= e($h['nama_pemangku']) ?></td>
            <td><?= e($h['periode_label']) ?></td>
            <td>
              <?php if ($h['status'] === 'disetujui'): ?>
                <span class="badge bg-success">Disetujui</span>
              <?php else: ?>
                <span class="badge bg-danger">Ditolak</span>
              <?php endif; ?>
            </td>
            <td class="small" data-order="<?= $h['created_at'] ? date('Y-m-d H:i:s', strtotime($h['created_at'])) : '' ?>"><?= date('d M Y H:i', strtotime($h['created_at'])) ?></td>
            <td class="small" data-order="<?= $h['diproses_at'] ? date('Y-m-d H:i:s', strtotime($h['diproses_at'])) : '' ?>"><?= $h['diproses_at'] ? date('d M Y H:i', strtotime($h['diproses_at'])) : '-' ?></td>
            <td class="small"><?= e($h['diproses_username'] ?? '-') ?></td>
            <td class="small" style="max-width:200px"><?= e($h['catatan_admin'] ?? '-') ?></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php endif; ?>
  </div>
</div>

<?php require __DIR__ . '/_pemangku_modal.php'; ?>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
