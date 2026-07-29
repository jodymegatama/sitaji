<?php
// includes/broadcast_modal.php
// Shared broadcast notification modal for admin & pegawai.
// Queries unread broadcast periods for the CURRENT user only.
// Requires: $pdo, current_user() from auth.php.

$cu = current_user();
if (!$cu) return;
$modalUserId = (int)$cu['id'];

// Fetch all unread broadcast periods for THIS user.
// "Unread" = status broadcast/revisi_pending AND no record in dana_notifikasi_dibaca for this user.
// For pegawai: only show periods where they have a matching payroll deduction (nilai > 0)
// for at least one komponen mapped to the stakeholder. Admin sees all.
$filterExtra = '';
$filterParams = [];
if ($cu['role'] !== 'admin') {
    $pegawaiId = (int)$cu['pegawai_id'];
    $filterExtra = "
      AND EXISTS (
        SELECT 1 FROM pemangku_kepentingan_komponen pkk
        JOIN payroll_detail pd ON pd.komponen_id = pkk.komponen_id
        JOIN payroll p ON p.id = pd.payroll_id
        WHERE pkk.pemangku_kepentingan_id = dp.pemangku_kepentingan_id
          AND p.pegawai_id = ?
          AND MONTH(p.periode) = dp.bulan
          AND YEAR(p.periode) = dp.tahun
          AND pd.nilai > 0
      )";
    $filterParams[] = $pegawaiId;
}

$unreadStmt = $pdo->prepare("
    SELECT dp.id, dp.bulan, dp.tahun, dp.total_pemasukan, dp.broadcast_at, dp.status,
           dp.is_revisi, pk.nama AS nama_pemangku
    FROM dana_periode dp
    JOIN pemangku_kepentingan pk ON pk.id = dp.pemangku_kepentingan_id
    WHERE dp.status IN ('broadcast', 'revisi_pending')
      AND NOT EXISTS (
          SELECT 1 FROM dana_notifikasi_dibaca
          WHERE periode_id = dp.id AND user_id = ?
      )
      $filterExtra
    ORDER BY dp.broadcast_at DESC
");
$unreadStmt->execute(array_merge([$modalUserId], $filterParams));
$unreadPeriods = $unreadStmt->fetchAll();

if (empty($unreadPeriods)) return;

// CSRF token for AJAX call
$modalCsrf = csrf_token();

// For each period: fetch rincian + check if it's from a revision approval
$periodIds = [];
$periodDetails = [];
$bulanNama = [1=>'Januari',2=>'Februari',3=>'Maret',4=>'April',5=>'Mei',6=>'Juni',
              7=>'Juli',8=>'Agustus',9=>'September',10=>'Oktober',11=>'November',12=>'Desember'];

foreach ($unreadPeriods as $up) {
    $pid = (int)$up['id'];
    $periodIds[] = $pid;

    // Rincian pemanfaatan
    $rStmt = $pdo->prepare("SELECT keterangan, nominal FROM dana_pemanfaatan WHERE periode_id = ? ORDER BY id ASC");
    $rStmt->execute([$pid]);
    $rincian = $rStmt->fetchAll();
    $totalPem = array_sum(array_column($rincian, 'nominal'));

    // Flag eksplisit dari kolom is_revisi (di-set oleh approve_revisi.php)
    $isRevisi = (int)$up['is_revisi'] === 1;

    $periodDetails[$pid] = [
        'rincian' => $rincian,
        'total_pem' => $totalPem,
        'sisa' => (float)$up['total_pemasukan'] - $totalPem,
        'is_revisi' => $isRevisi,
        'label_bulan' => $bulanNama[(int)$up['bulan']] . ' ' . $up['tahun'],
    ];
}

// Build JSON for JS (period IDs displayed in this modal)
$displayedIdsJson = json_encode($periodIds);
?>

<!-- Broadcast Notification Modal -->
<div class="modal fade" id="broadcastModal" tabindex="-1" aria-label="Broadcast Notifikasi" data-bs-backdrop="static" data-bs-keyboard="false">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content modal-modern">
      <div class="modal-header border-0 pb-0 px-4 pt-4">
        <div class="d-flex align-items-center gap-3">
          <div class="icon-box bg-primary text-white" style="width:48px;height:48px;border-radius:14px;display:flex;align-items:center;justify-content:center;font-size:1.4rem">
            <i class="bi bi-megaphone-fill"></i>
          </div>
          <div>
            <h5 class="fw-bold mb-0">Notifikasi Broadcast Dana</h5>
            <small class="text-muted"><?= count($periodIds) ?> periode belum dibaca</small>
          </div>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
      </div>
      <div class="modal-body px-4 py-3">
        <div class="accordion" id="broadcastAccordion">
          <?php foreach ($unreadPeriods as $idx => $up):
              $pid = (int)$up['id'];
              $d = $periodDetails[$pid];
              $collapseId = "bcCollapse_$pid";
          ?>
          <div class="accordion-item border-0 mb-2" style="border-radius:12px!important;overflow:hidden;border:1px solid var(--border)!important">
            <h2 class="accordion-header">
              <button class="accordion-button <?= $idx > 0 ? 'collapsed' : '' ?>" type="button"
                      data-bs-toggle="collapse" data-bs-target="#<?= $collapseId ?>"
                      aria-expanded="<?= $idx === 0 ? 'true' : 'false' ?>">
                <div class="d-flex align-items-center gap-2 flex-grow-1">
                  <i class="bi bi-calendar3 text-primary"></i>
                  <span class="fw-semibold"><?= e($d['label_bulan']) ?></span>
                  <span class="text-muted small">— <?= e($up['nama_pemangku']) ?></span>
                  <?php if ($d['is_revisi']): ?>
                    <span class="badge bg-warning text-dark ms-1"><i class="bi bi-pencil-square"></i> Revisi</span>
                  <?php endif; ?>
                </div>
              </button>
            </h2>
            <div id="<?= $collapseId ?>" class="accordion-collapse collapse <?= $idx === 0 ? 'show' : '' ?>" data-bs-parent="#broadcastAccordion">
              <div class="accordion-body p-3">
                <div class="row g-3 mb-3">
                  <div class="col-sm-4">
                    <div class="text-muted small">Pemasukan</div>
                    <div class="fw-bold text-success"><?= rupiah($up['total_pemasukan']) ?></div>
                  </div>
                  <div class="col-sm-4">
                    <div class="text-muted small">Pemanfaatan</div>
                    <div class="fw-bold text-danger"><?= rupiah($d['total_pem']) ?></div>
                  </div>
                  <div class="col-sm-4">
                    <div class="text-muted small">Sisa Saldo</div>
                    <div class="fw-bold <?= $d['sisa'] >= 0 ? 'text-success' : 'text-danger' ?>"><?= rupiah($d['sisa']) ?></div>
                  </div>
                </div>
                <?php if (!empty($d['rincian'])): ?>
                <table class="table table-sm mb-0" style="font-size:.85rem">
                  <thead><tr><th>Keterangan</th><th class="text-end">Nominal</th></tr></thead>
                  <tbody>
                    <?php foreach ($d['rincian'] as $r): ?>
                    <tr>
                      <td><?= e($r['keterangan']) ?></td>
                      <td class="text-end"><?= rupiah($r['nominal']) ?></td>
                    </tr>
                    <?php endforeach; ?>
                  </tbody>
                </table>
                <?php else: ?>
                <p class="text-muted fst-italic small mb-0">Belum ada rincian pemanfaatan.</p>
                <?php endif; ?>
                <div class="text-muted small mt-2">
                  <i class="bi bi-clock"></i> Di-broadcast: <?= date('d M Y H:i', strtotime($up['broadcast_at'])) ?>
                  <?php if ($up['status'] === 'revisi_pending'): ?>
                    <span class="badge bg-info text-dark ms-1">Menunggu Persetujuan Revisi</span>
                  <?php endif; ?>
                </div>
              </div>
            </div>
          </div>
          <?php endforeach; ?>
        </div>
      </div>
      <div class="modal-footer border-0 px-4 pb-4 pt-1 d-flex justify-content-between align-items-center">
        <small class="text-muted">Pesan ini akan ditutup setelah Anda menutup popup.</small>
        <button type="button" class="btn-primary-modern" data-bs-dismiss="modal" id="btnCloseBroadcast">
          <i class="bi bi-check2-circle"></i> Saya Sudah Membaca
        </button>
      </div>
    </div>
  </div>
</div>

<script>
(function(){
  const displayedIds = <?= $displayedIdsJson ?>;
  let marked = false;

  function markAsRead() {
    if (marked || !displayedIds.length) return;
    marked = true;

    const csrfToken = '<?= $modalCsrf ?>';

    fetch('<?= base_url("includes/mark_broadcast_read.php") ?>', {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: 'periode_ids[]=' + displayedIds.join('&periode_ids[]=') + '&csrf=' + encodeURIComponent(csrfToken)
    }).then(r => r.json()).then(data => {
      if (data.ok) {
        const badge = document.getElementById('unreadBadge');
        if (badge) badge.style.display = 'none';
      }
    }).catch(err => {
      console.error('markBroadcastRead gagal — popup akan muncul lagi di login berikutnya:', err);
      const toast = document.createElement('div');
      toast.className = 'position-fixed bottom-0 end-0 p-3';
      toast.style.zIndex = '99999';
      toast.innerHTML = '<div class="toast show align-items-center text-bg-warning border-0" role="alert"><div class="d-flex"><div class="toast-body"><i class="bi bi-exclamation-triangle"></i> Gagal menandai sebagai sudah dibaca.</div><button type="button" class="btn-close me-2 m-auto" data-bs-dismiss="toast"></button></div></div>';
      document.body.appendChild(toast);
    });
  }

  document.getElementById('broadcastModal').addEventListener('hidden.bs.modal', markAsRead);
})();
</script>
