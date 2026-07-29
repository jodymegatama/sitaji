<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/helpers.php';
require_role('pegawai');

$u = current_user();
$id = (int)($_GET['id'] ?? 0);

$st = $pdo->prepare("
  SELECT p.*, pg.nama AS pegawai_nama, pg.nip AS pegawai_nip
  FROM payroll p
  JOIN pegawai pg ON pg.id = p.pegawai_id
  WHERE p.id=? AND p.pegawai_id=?
");
$st->execute([$id, $u['pegawai_id']]);
$r = $st->fetch();
if (!$r) { echo '<div class="alert alert-danger">Slip tidak ditemukan.</div>'; exit; }
$fields = payroll_fields();

try {
    $values = payroll_values_map($id);
    $r = array_merge($r, $values);
} catch (PDOException $e) {
    error_log("Pegawai Detail Slip Error: " . $e->getMessage());
    if ($e->getCode() === '42S02') {
        echo '<div class="alert alert-warning">Terjadi masalah konfigurasi sistem, hubungi administrator.</div>';
        exit;
    }
    throw $e;
}

$r['_detail_sum_pendapatan'] = array_sum(array_intersect_key($values, array_flip(array_keys(payroll_fields()['pendapatan']))));
$r['_detail_sum_potongan'] = array_sum(array_intersect_key($values, array_flip(array_merge(array_keys(payroll_fields()['potongan_umum']), array_keys(payroll_fields()['koperasi'])))));
?>
<style>
@import url('https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,400;9..144,600;9..144,700&family=IBM+Plex+Mono:wght@400;500;600&family=Inter:wght@400;500;600;700&display=swap');
#slipModal .modal-dialog { max-width: 580px; }
#slipModal .modal-content {
  background: transparent;
  border: none;
  padding: 0;
  box-shadow: none;
  overflow: visible;
}
#slipModal .modal-body {
  padding: 0 !important;
  background: transparent;
}
.modal-slip-redesign {
  --paper: #FFFFFF;
  --paper-dim: #FAFAFA;
  --ink: #1C2B44;
  --ink-soft: #5B6B84;
  --emerald: #145C4B;
  --emerald-bg: #E7F1EC;
  --clay: #9C4221;
  --clay-bg: #F7EAE2;
  --gold: #B8863B;
  --line: #E4E7EC;
  --shadow: 0 30px 60px -20px rgba(28,43,68,0.22);
  font-family: 'Fraunces', 'IBM Plex Mono', sans-serif;
  color: var(--ink);
  width: 100%;
  max-width: 580px;
  background: rgba(255,255,255,0.72);
  backdrop-filter: blur(18px) saturate(160%);
  -webkit-backdrop-filter: blur(18px) saturate(160%);
  border: 1px solid rgba(255,255,255,0.6);
  border-radius: 22px;
  box-shadow: var(--shadow);
  overflow: hidden;
  position: relative;
  animation: slip-in 0.5s cubic-bezier(0.22,1,0.36,1);
}
@keyframes slip-in {
  from { opacity: 0; transform: translateY(24px) scale(0.96); }
  to { opacity: 1; transform: translateY(0) scale(1); }
}
@media (prefers-reduced-motion: reduce) {
  .modal-slip-redesign { animation: none; }
  .modal-slip-redesign .btn-confirm { transition: none; }
}
.modal-slip-redesign .slip-inner { padding: 30px 30px; }
.modal-slip-redesign .close-btn {
  position: absolute;
  top: 24px; right: 24px;
  width: 34px; height: 34px;
  border-radius: 50%;
  border: 1px solid var(--line);
  background: var(--paper);
  color: var(--ink-soft);
  font-size: 16px;
  display: flex; align-items: center; justify-content: center;
  cursor: pointer;
  z-index: 2;
}
.modal-slip-redesign .head {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  border-bottom: 1px solid var(--line);
  padding-bottom: 20px;
  margin-bottom: 26px;
}
.modal-slip-redesign .head .eyebrow {
  font-family: 'IBM Plex Mono', monospace;
  font-size: 11px;
  letter-spacing: 0.14em;
  text-transform: uppercase;
  color: var(--ink-soft);
  margin: 0 0 6px;
}
.modal-slip-redesign .head h1 {
  font-family: 'Fraunces', serif;
  font-weight: 600;
  font-size: 23px;
  margin: 0;
  color: var(--ink);
  letter-spacing: -0.01em;
}
.modal-slip-redesign .head .period { text-align: right; }
.modal-slip-redesign .head .period .eyebrow { margin-bottom: 6px; }
.modal-slip-redesign .head .period .val {
  font-family: 'Fraunces', serif;
  font-size: 20px;
  font-weight: 600;
  color: var(--gold);
}
.modal-slip-redesign .employee {
  font-family: 'IBM Plex Mono', monospace;
  font-size: 12.5px;
  color: var(--ink-soft);
  margin: -14px 0 26px;
}
.modal-slip-redesign .employee b { color: var(--ink); font-weight: 600; }
.modal-slip-redesign .columns {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 18px;
}
.modal-slip-redesign .col-label {
  display: flex;
  align-items: center;
  gap: 8px;
  font-family: 'IBM Plex Mono', monospace;
  font-size: 11.5px;
  letter-spacing: 0.1em;
  text-transform: uppercase;
  font-weight: 600;
  margin-bottom: 14px;
}
.modal-slip-redesign .col-label.income { color: var(--emerald); }
.modal-slip-redesign .col-label.outcome { color: var(--clay); }
.modal-slip-redesign .col-label .dot {
  width: 7px; height: 7px; border-radius: 50%;
  flex-shrink: 0;
}
.modal-slip-redesign .col-label.income .dot { background: var(--emerald); }
.modal-slip-redesign .col-label.outcome .dot { background: var(--clay); }
.modal-slip-redesign .ledger-row {
  display: flex;
  align-items: baseline;
  gap: 8px;
  padding: 9px 0;
  font-size: 13px;
  color: var(--ink);
}
.modal-slip-redesign .ledger-row .name { white-space: nowrap; }
.modal-slip-redesign .ledger-row .leader {
  flex: 1;
  border-bottom: 1px dotted var(--line);
  transform: translateY(-4px);
}
.modal-slip-redesign .ledger-row .amt {
  font-family: 'IBM Plex Mono', monospace;
  font-weight: 500;
  white-space: nowrap;
  font-variant-numeric: tabular-nums;
}
.modal-slip-redesign .col-total {
  display: flex;
  justify-content: space-between;
  align-items: baseline;
  margin-top: 14px;
  padding-top: 14px;
  border-top: 1.5px solid var(--ink);
  font-family: 'IBM Plex Mono', monospace;
}
.modal-slip-redesign .col-total .lbl {
  font-size: 10px;
  letter-spacing: 0.06em;
  text-transform: uppercase;
  font-weight: 600;
  color: var(--ink-soft);
  white-space: nowrap;
}
.modal-slip-redesign .col-total .val {
  font-size: 15px;
  font-weight: 600;
  font-variant-numeric: tabular-nums;
}
.modal-slip-redesign .col-total.income .val { color: var(--emerald); }
.modal-slip-redesign .col-total.outcome .val { color: var(--clay); }
.modal-slip-redesign .thp {
  margin-top: 30px;
  border: 1.5px solid var(--ink);
  border-radius: 16px;
  padding: 22px 26px;
  display: flex;
  justify-content: space-between;
  align-items: center;
  position: relative;
  background:
    repeating-linear-gradient(135deg, rgba(28,43,68,0.025) 0 2px, transparent 2px 10px);
}
.modal-slip-redesign .thp .lbl {
  font-family: 'IBM Plex Mono', monospace;
  font-size: 11.5px;
  letter-spacing: 0.1em;
  text-transform: uppercase;
  color: var(--ink-soft);
  margin-bottom: 6px;
}
.modal-slip-redesign .thp .val {
  font-family: 'Fraunces', serif;
  font-weight: 700;
  font-size: 28px;
  color: var(--ink);
  letter-spacing: -0.01em;
}
.modal-slip-redesign .stamp {
  font-family: 'IBM Plex Mono', monospace;
  font-size: 11px;
  letter-spacing: 0.08em;
  color: var(--gold);
  border: 1.5px solid var(--gold);
  border-radius: 999px;
  padding: 6px 14px;
  text-transform: uppercase;
  transform: rotate(-6deg);
  white-space: nowrap;
  flex-shrink: 0;
}
.modal-slip-redesign .actions { margin-top: 24px; }
.modal-slip-redesign .btn-confirm {
  width: 100%;
  padding: 16px;
  border: none;
  border-radius: 14px;
  background: var(--ink);
  color: var(--paper);
  font-family: 'Inter', sans-serif;
  font-weight: 600;
  font-size: 14.5px;
  letter-spacing: 0.01em;
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 10px;
  transition: transform .15s ease, background .15s ease;
}
.modal-slip-redesign .btn-confirm:hover { background: #0e1626; transform: translateY(-1px); }
.modal-slip-redesign .btn-confirm:disabled { opacity: .55; cursor: not-allowed; transform: none; }
.modal-slip-redesign .btn-confirm svg { width: 16px; height: 16px; flex-shrink: 0; }
.modal-slip-redesign .footnote {
  text-align: center;
  margin-top: 14px;
  font-size: 11.5px;
  color: var(--ink-soft);
  font-family: 'IBM Plex Mono', monospace;
}
@media (max-width: 560px) {
  .modal-slip-redesign .slip-inner { padding: 8px 22px 28px; }
  .modal-slip-redesign .columns { grid-template-columns: 1fr; gap: 22px; }
  .modal-slip-redesign .thp { flex-direction: column; align-items: flex-start; gap: 12px; }
}
@media print {
  #slipModal .modal-content {
    box-shadow: none;
    border-radius: 0;
  }
  .modal-slip-redesign .close-btn,
  .modal-slip-redesign .actions,
  .modal-slip-redesign .footnote { display: none; }
  .modal-slip-redesign {
    -webkit-print-color-adjust: exact;
    print-color-adjust: exact;
    background: #ffffff !important;
    backdrop-filter: none !important;
    -webkit-backdrop-filter: none !important;
    border: 1px solid var(--line);
  }
  .modal-slip-redesign .thp {
    -webkit-print-color-adjust: exact;
    print-color-adjust: exact;
  }
}
</style>

<div class="modal-slip-redesign">
  <button class="close-btn" data-bs-dismiss="modal" aria-label="Tutup">&times;</button>
  <div class="slip-inner">

    <div class="head">
      <div>
        <p class="eyebrow">Audit Payroll Detail</p>
        <h1>Rincian Slip Gaji</h1>
      </div>
      <div class="period">
        <p class="eyebrow">Periode Gaji</p>
        <div class="val"><?= periode_label($r['periode']) ?></div>
      </div>
    </div>

    <div class="employee"><b><?= e($r['pegawai_nama']) ?></b> — NIP <?= e($r['pegawai_nip']) ?></div>

    <div class="columns">
      <div>
        <div class="col-label income"><span class="dot"></span> Pendapatan (Bruto)</div>
        <?php foreach ($fields['pendapatan'] as $k => $lab):
          $val = (float)($r[$k] ?? 0);
        ?>
          <?php if ($val > 0 || isset($r[$k])): ?>
          <div class="ledger-row">
            <span class="name"><?= e($lab) ?></span>
            <span class="leader"></span>
            <span class="amt"><?= rupiah($val) ?></span>
          </div>
          <?php endif; ?>
        <?php endforeach; ?>
        <div class="col-total income">
          <span class="lbl">Total Pendapatan</span>
          <span class="val"><?= rupiah(total_bruto($r)) ?></span>
        </div>
      </div>

      <div>
        <div class="col-label outcome"><span class="dot"></span> Pengeluaran &amp; Infaq</div>
        <?php
        $allPot = array_merge($fields['potongan_umum'], $fields['koperasi']);
        $hasPot = false;
        foreach ($allPot as $k => $lab):
          $val = (float)($r[$k] ?? 0);
          if ($val <= 0 && !isset($r[$k])) continue;
          $hasPot = true;
        ?>
          <div class="ledger-row">
            <span class="name"><?= e($lab) ?></span>
            <span class="leader"></span>
            <span class="amt"><?= rupiah($val) ?></span>
          </div>
        <?php endforeach; ?>
        <div class="col-total outcome">
          <span class="lbl">Total Pengeluaran</span>
          <span class="val"><?= rupiah(total_potongan($r)) ?></span>
        </div>
      </div>
    </div>

    <div class="thp">
      <div>
        <div class="lbl">Take Home Pay (Gaji Bersih)</div>
        <div class="val"><?= rupiah(take_home($r)) ?></div>
      </div>
      <div class="stamp">STATUS: <?= $r['status_terima'] ? 'SUDAH DIKONFIRMASI' : 'BELUM DIKONFIRMASI' ?></div>
    </div>

    <form method="post" action="tanda_terima">
      <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
      <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
      <div class="actions">
        <button class="btn-confirm" <?= $r['status_terima'] ? 'disabled' : '' ?>>
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6L9 17l-5-5"/></svg>
          <?= $r['status_terima'] ? 'SLIP SUDAH DIKONFIRMASI' : 'SETUJUI &amp; KONFIRMASI DATA SLIP' ?>
        </button>
      </div>
    </form>

    <div class="footnote">Dokumen ini dibuat otomatis oleh sistem SITAJI</div>

  </div>
</div>
