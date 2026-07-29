<?php
// Modals dari dashboard.php lama
?>
<!-- ===================== MODAL EXPORT ===================== -->
<div class="modal fade" id="exportModal" tabindex="-1" aria-labelledby="exportModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered" style="max-width:440px">
    <div class="modal-content modal-modern">
      <div class="modal-header border-0 pb-0 px-4 pt-4">
        <div class="d-flex align-items-center gap-2">
          <div style="width:38px;height:38px;border-radius:10px;background:#dcfce7;display:flex;align-items:center;justify-content:center">
            <i class="bi bi-file-earmark-spreadsheet" style="color:#16a34a;font-size:1.1rem"></i>
          </div>
          <h5 class="modal-title fw-bold mb-0" id="exportModalLabel">Export Data Payroll</h5>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
      </div>

      <div class="modal-body px-4 py-3">
        <p class="text-muted small mb-3">Pilih parameter untuk mengunduh laporan dalam format Excel (.xlsx)</p>

        <form id="exportForm" action="export_gaji" method="get" target="_blank">
          <div class="mb-3">
            <label class="form-label fw-semibold" style="font-size:.8rem;letter-spacing:.05em;text-transform:uppercase;color:#64748b">
              Bulan &amp; Tahun
            </label>
            <input type="month" name="periode" class="form-control form-control-lg"
                   value="<?= date('Y-m') ?>" required
                   style="border-radius:10px;border:1.5px solid #e2e8f0;font-size:.95rem">
          </div>
        </form>
      </div>

      <div class="modal-footer border-0 px-4 pb-4 pt-1 d-flex gap-2">
        <button type="button" class="btn-secondary-modern" data-bs-dismiss="modal">Batal</button>
        <button type="submit" form="exportForm" class="btn-success-modern">
          <i class="bi bi-download"></i> Download Excel
        </button>
      </div>
    </div>
  </div>
</div>

<!-- ===================== MODAL DETAIL ===================== -->
<div class="modal fade" id="detailModal" tabindex="-1" aria-label="Detail Payroll"><div class="modal-dialog modal-lg modal-dialog-centered">
  <div class="modal-content"><div class="modal-body p-4" id="detailBody">Memuat…</div></div>
</div></div>

<script>
document.getElementById('detailModal').addEventListener('show.bs.modal', async (ev) => {
  const url = ev.relatedTarget.getAttribute('data-detail-url');
  const body = document.getElementById('detailBody');
  body.innerHTML = 'Memuat…';
  const res = await fetch(url); body.innerHTML = await res.text();
});
</script>

<!-- ===================== MODAL IMPORT ===================== -->
<div class="modal fade" id="importModal" tabindex="-1" aria-labelledby="importModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered" style="max-width:460px">
    <div class="modal-content modal-modern">
      <div class="modal-header border-0 pb-0 px-4 pt-4">
        <div class="d-flex align-items-center gap-2">
          <div style="width:38px;height:38px;border-radius:10px;background:#dbeafe;display:flex;align-items:center;justify-content:center">
            <i class="bi bi-upload" style="color:var(--primary);font-size:1.1rem"></i>
          </div>
          <h5 class="modal-title fw-bold mb-0" id="importModalLabel">Impor Data Payroll</h5>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup" id="importModalClose"></button>
      </div>
      <div class="modal-body px-4 py-3">
        <div style="background:#e2edf7;border-left:4px solid var(--primary);border-radius:8px;padding:10px 14px;margin-bottom:16px">
          <div class="d-flex gap-2">
            <i class="bi bi-info-circle-fill text-primary mt-1" style="flex-shrink:0"></i>
            <p class="mb-0 small text-secondary">
              Gunakan <strong>Download Template Database</strong> untuk mendapatkan file Excel terbaru yang sudah berisi daftar pegawai aktif dan komponen potongan sesuai konfigurasi sistem saat ini. Data yang sama akan otomatis <strong>ditimpa (overwrite)</strong>.
            </p>
          </div>
        </div>
        <div id="importAlert" class="d-none mb-3 small rounded px-3 py-2"></div>
        <div id="importDetailBox" class="d-none mb-3"></div>
        <form id="importForm" novalidate>
          <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
          <div class="mb-3">
            <label for="importPeriode" class="form-label fw-semibold" style="font-size:.8rem;letter-spacing:.05em;text-transform:uppercase;color:#64748b">
              Periode Gaji (Bulan/Tahun)
            </label>
            <input type="month" name="periode" id="importPeriode"
                   class="form-control form-control-lg"
                   value="<?= date('Y-m') ?>" required
                   style="border-radius:10px;border:1.5px solid #e2e8f0;font-size:.95rem">
          </div>
          <div class="mb-1">
            <label for="importFile" class="form-label fw-semibold" style="font-size:.8rem;letter-spacing:.05em;text-transform:uppercase;color:#64748b">
              Pilih File Excel
            </label>
            <input type="file" name="file" id="importFile" accept=".xlsx"
                   class="form-control form-control-lg" required
                   style="border-radius:10px;border:1.5px solid #e2e8f0;font-size:.95rem">
            <div class="invalid-feedback">Pilih file Excel (.xlsx) terlebih dahulu.</div>
          </div>
        </form>
      </div>
      <div class="modal-footer border-0 px-4 pb-4 pt-1 d-flex justify-content-between align-items-center">
        <a href="template_payroll" target="_blank" class="btn-secondary-modern" style="text-decoration:none">
          <i class="bi bi-database-down"></i> Download Template Database
        </a>
        <div class="d-flex gap-2">
          <button type="button" class="btn-secondary-modern" data-bs-dismiss="modal">Batal</button>
          <button type="button" id="importSubmitBtn" class="btn-primary-modern">
            <i class="bi bi-upload" id="importBtnIcon"></i>
            <span id="importBtnText">Mulai Impor</span>
          </button>
        </div>
      </div>
    </div>
  </div>
</div>
<script>
(function () {
  const form       = document.getElementById('importForm');
  const btn        = document.getElementById('importSubmitBtn');
  const btnIcon    = document.getElementById('importBtnIcon');
  const btnText    = document.getElementById('importBtnText');
  const alertBox   = document.getElementById('importAlert');
  const closeBtn   = document.getElementById('importModalClose');
  const detailBox  = document.getElementById('importDetailBox');
  let needsReloadOnClose = false;

  function showAlert(success, msg) {
    alertBox.className = 'd-block mb-3 small rounded px-3 py-2 ' +
      (success ? 'alert alert-success' : 'alert alert-danger');
    alertBox.textContent = msg;
  }

  function resetBtn() {
    btn.disabled = false;
    btnIcon.className = 'bi bi-upload';
    btnText.textContent = 'Mulai Impor';
  }

  function renderImportDetails(data) {
    detailBox.innerHTML = '';
    const errs = data.errors || [];
    const warns = data.warnings || [];
    if (errs.length === 0 && warns.length === 0) {
      detailBox.className = 'd-none';
      return;
    }
    detailBox.className = 'd-block mb-3';

    const parts = [];
    if (errs.length) parts.push(errs.length + ' error');
    if (warns.length) parts.push(warns.length + ' warning');

    const link = document.createElement('a');
    link.href = '#';
    link.className = 'small fw-semibold text-decoration-none';
    link.setAttribute('data-bs-toggle', 'collapse');
    link.setAttribute('data-bs-target', '#importDetailCollapse');
    link.textContent = 'Lihat detail: ' + parts.join(', ');
    detailBox.appendChild(link);

    const wrap = document.createElement('div');
    wrap.className = 'collapse mt-2';
    wrap.id = 'importDetailCollapse';

    const inner = document.createElement('div');
    inner.className = 'rounded border';
    inner.style.maxHeight = '360px';
    inner.style.overflowY = 'auto';

    if (errs.length) {
      const hdr = document.createElement('div');
      hdr.className = 'fw-semibold small px-3 py-1';
      hdr.style.backgroundColor = '#f8d7da';
      hdr.style.color = '#721c24';
      hdr.textContent = 'Error (' + errs.length + ')';
      inner.appendChild(hdr);

      const ul = document.createElement('ul');
      ul.className = 'list-unstyled mb-0';
      errs.forEach(function (m) {
        const li = document.createElement('li');
        li.className = 'px-3 py-1 small';
        li.style.borderBottom = '1px solid #f5c6cb';
        li.style.color = '#721c24';
        li.textContent = m;
        ul.appendChild(li);
      });
      inner.appendChild(ul);
    }

    if (warns.length) {
      const hdr = document.createElement('div');
      hdr.className = 'fw-semibold small px-3 py-1';
      hdr.style.backgroundColor = '#fff3cd';
      hdr.style.color = '#856404';
      hdr.textContent = 'Warning (' + warns.length + ')';
      inner.appendChild(hdr);

      const ul = document.createElement('ul');
      ul.className = 'list-unstyled mb-0';
      warns.forEach(function (m) {
        const li = document.createElement('li');
        li.className = 'px-3 py-1 small';
        li.style.borderBottom = '1px solid #ffeeba';
        li.style.color = '#856404';
        li.textContent = m;
        ul.appendChild(li);
      });
      inner.appendChild(ul);
    }

    wrap.appendChild(inner);
    detailBox.appendChild(wrap);
  }

  const modalEl = document.getElementById('importModal');

  modalEl.addEventListener('show.bs.modal', function () {
    needsReloadOnClose = false;
    alertBox.className = 'd-none';
    alertBox.textContent = '';
    detailBox.innerHTML = '';
    detailBox.className = 'd-none';
    form.reset();
    document.getElementById('importPeriode').value = '<?= date('Y-m') ?>';
    resetBtn();
  });

  modalEl.addEventListener('hidden.bs.modal', function () {
    if (needsReloadOnClose) {
      needsReloadOnClose = false;
      location.reload();
    }
  });

  btn.addEventListener('click', async function () {
    const periodeVal = document.getElementById('importPeriode').value;
    const fileInput  = document.getElementById('importFile');

    if (!periodeVal) { showAlert(false, 'Pilih periode terlebih dahulu.'); return; }
    if (!fileInput.files.length) { showAlert(false, 'Pilih file Excel (.xlsx) terlebih dahulu.'); return; }
    if (!fileInput.files[0].name.endsWith('.xlsx')) {
      showAlert(false, 'Format file harus .xlsx. Gunakan template yang disediakan.');
      return;
    }

    btn.disabled = true;
    btnIcon.className = 'spinner-border spinner-border-sm';
    btnText.textContent = 'Mengimpor…';
    alertBox.className = 'd-none';

    const fd = new FormData(form);
    fd.set('periode', periodeVal);

    try {
      const res  = await fetch('import_gaji', { method: 'POST', body: fd });
      const data = await res.json();
      showAlert(data.success, data.message);
      renderImportDetails(data);
      if (data.success) {
        const errs = (data.errors || []).length;
        const warns = (data.warnings || []).length;
        if (errs === 0 && warns === 0) {
          setTimeout(function () { location.reload(); }, 1500);
        } else {
          needsReloadOnClose = true;
        }
      }
    } catch (e) {
      showAlert(false, 'Terjadi kesalahan jaringan. Coba lagi.');
    } finally {
      resetBtn();
    }
  });
}());
</script>
