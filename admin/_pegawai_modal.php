<div class="modal fade" id="addModal" tabindex="-1" aria-label="Tambah Pegawai Baru"><div class="modal-dialog modal-dialog-centered"><div class="modal-content modal-modern">
  <form method="post" action="tambah_pegawai" data-validate novalidate>
    <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
    <div class="modal-header"><h5 class="modal-title fw-bold">Tambah Pegawai Baru</h5><button class="btn-close" data-bs-dismiss="modal"></button></div>
    <div class="modal-body">
      <div class="mb-3"><label for="modalNip" class="form-label small text-uppercase fw-semibold text-muted">Nomor Induk Pegawai (NIP)</label>
        <input id="modalNip" class="form-control form-control-lg" name="nip" placeholder="Masukkan NIP 18 digit..." required></div>
      <div class="mb-3"><label for="modalNama" class="form-label small text-uppercase fw-semibold text-muted">Nama Lengkap & Gelar</label>
        <input id="modalNama" class="form-control form-control-lg" name="nama" placeholder="Nama lengkap sesuai KTP..." required></div>
      <div class="row g-3">
        <div class="col-md-6"><label for="modalGolongan" class="form-label small text-muted">Golongan</label>
          <input id="modalGolongan" class="form-control" name="golongan" placeholder="Contoh: IV/a"></div>
        <div class="col-md-6"><label for="modalJabatan" class="form-label small text-muted">Jabatan</label>
          <input id="modalJabatan" class="form-control" name="jabatan" placeholder="Contoh: Guru Madya"></div>
      </div>
      <div class="alert alert-info small mt-3 mb-0">
        <i class="bi bi-info-circle"></i> Akun akan otomatis dibuatkan dengan password default: <strong>kemenag123</strong>
      </div>
    </div>
    <div class="modal-footer border-0 d-flex gap-2">
      <button type="button" class="btn-secondary-modern" data-bs-dismiss="modal">Batal</button>
      <button class="btn-primary-modern"><i class="bi bi-check-circle"></i> Simpan Data Pegawai</button>
    </div>
  </form>
</div></div></div>

<style>
/* Title style */
.import-title {
  font-size: 1.15rem;
  font-weight: 700;
  color: #16a34a;
  margin: 0;
  display: flex;
  align-items: center;
  gap: 10px;
}
.import-title .icon-box {
  width: 36px;
  height: 36px;
  border-radius: 10px;
  background: #16a34a;
  color: #fff;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 1rem;
}

/* File input area */
.file-drop-zone {
  border: 2px dashed #cbd5e1;
  border-radius: 12px;
  padding: 28px 20px;
  text-align: center;
  cursor: pointer;
  transition: all 0.2s ease;
  background: #f8fafc;
  position: relative;
}
.file-drop-zone:hover {
  border-color: #16a34a;
  background: #f0fdf4;
}
.file-drop-zone.dragover {
  border-color: #16a34a;
  background: #dcfce7;
}
.file-drop-zone .drop-icon {
  width: 48px;
  height: 48px;
  border-radius: 12px;
  background: #e2e8f0;
  color: #64748b;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 1.3rem;
  margin: 0 auto 12px;
  transition: all 0.2s ease;
}
.file-drop-zone:hover .drop-icon {
  background: #dcfce7;
  color: #16a34a;
}
.file-drop-zone .drop-text {
  font-size: 0.9rem;
  color: #475569;
  margin: 0;
}
.file-drop-zone .drop-hint {
  font-size: 0.8rem;
  color: #94a3b8;
  margin: 4px 0 0;
}
.file-drop-zone input[type="file"] {
  position: absolute;
  inset: 0;
  opacity: 0;
  cursor: pointer;
}

/* File info display */
.file-info {
  display: none;
  background: #f0fdf4;
  border: 1px solid #bbf7d0;
  border-radius: 10px;
  padding: 12px 16px;
  margin-top: 12px;
}
.file-info.show {
  display: flex;
  align-items: center;
  gap: 12px;
}
.file-info .file-icon {
  width: 40px;
  height: 40px;
  border-radius: 10px;
  background: #16a34a;
  color: #fff;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 1rem;
  flex-shrink: 0;
}
.file-info .file-details {
  flex: 1;
  min-width: 0;
}
.file-info .file-name {
  font-weight: 600;
  font-size: 0.875rem;
  color: #1e293b;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}
.file-info .file-size {
  font-size: 0.75rem;
  color: #64748b;
}
.file-info .file-remove {
  background: none;
  border: none;
  color: #94a3b8;
  cursor: pointer;
  padding: 4px;
  border-radius: 6px;
  transition: all 0.15s;
  font-size: 1.1rem;
}
.file-info .file-remove:hover {
  background: #fee2e2;
  color: #dc2626;
}

/* Instruction text */
.import-instruction {
  font-size: 0.82rem;
  color: #64748b;
  line-height: 1.5;
  margin: 0;
  padding: 10px 14px;
  background: #f8fafc;
  border-radius: 8px;
  border-left: 3px solid #16a34a;
}

/* Download template link */
.download-template {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  font-size: 0.875rem;
  font-weight: 600;
  color: var(--primary);
  text-decoration: none;
  transition: color 0.15s;
}
.download-template:hover {
  color: var(--primary);
  text-decoration: underline;
}
.download-template i {
  font-size: 1rem;
}

/* Status alert */
.import-status {
  display: none;
  border-radius: 10px;
  padding: 12px 16px;
  font-size: 0.85rem;
  margin: 0;
  border: none;
}
.import-status.show {
  display: block;
}
.import-status.success {
  background: #f0fdf4;
  color: #166534;
  border: 1px solid #bbf7d0;
}
.import-status.error {
  background: #fef2f2;
  color: #991b1b;
  border: 1px solid #fecaca;
}
.import-status.warning {
  background: #fffbeb;
  color: #92400e;
  border: 1px solid #fde68a;
}
.import-status.info {
  background: #eff6ff;
  color: #1e40af;
  border: 1px solid #bfdbfe;
}
</style>

<div class="modal fade" id="importPegawaiModal" tabindex="-1" aria-labelledby="importPegawaiModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered" style="max-width: 480px;">
    <div class="modal-content modal-modern">

      <!-- Header -->
      <div class="modal-header border-0 pb-0">
        <h5 class="modal-title import-title" id="importPegawaiModalLabel">
          <span class="icon-box"><i class="bi bi-file-earmark-excel"></i></span>
          Import Massal
        </h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup" id="importModalClose"></button>
      </div>

      <!-- Body -->
      <div class="modal-body">

        <!-- File Drop Zone -->
        <div class="file-drop-zone" id="dropZone">
          <div class="drop-icon"><i class="bi bi-cloud-arrow-up"></i></div>
          <p class="drop-text">Pilih file .xlsx...</p>
          <p class="drop-hint">atau seret file ke sini</p>
          <input type="file" accept=".xlsx" id="importFileInput">
        </div>

        <!-- File Info (hidden by default) -->
        <div class="file-info" id="fileInfo">
          <div class="file-icon"><i class="bi bi-file-earmark-excel"></i></div>
          <div class="file-details">
            <div class="file-name" id="fileName">-</div>
            <div class="file-size" id="fileSize">-</div>
          </div>
          <button type="button" class="file-remove" id="fileRemove" title="Hapus file"><i class="bi bi-x-lg"></i></button>
        </div>

        <!-- Instruction -->
        <p class="import-instruction mt-3">
          Pastikan format kolom <strong>NIP</strong>, <strong>NAMA_PEGAWAI</strong>, <strong>JABATAN</strong> sesuai dengan template yang tersedia.
        </p>

        <!-- Status Alert -->
        <div class="import-status" id="importStatus"></div>

      </div>

      <!-- Footer -->
      <div class="modal-footer border-0 d-flex justify-content-between align-items-center pt-0">
        <a href="download_template_pegawai" target="_blank" class="download-template">
          <i class="bi bi-download"></i>
          Download Template
        </a>
        <div class="d-flex gap-2">
          <button type="button" class="btn-secondary-modern" data-bs-dismiss="modal">Batal</button>
          <button type="button" class="btn-success-modern" id="btnImportPegawai" disabled>
            <i class="bi bi-upload"></i>
            Mulai Import
          </button>
        </div>
      </div>

    </div>
  </div>
</div>

<script>
(function() {
  const dropZone    = document.getElementById('dropZone');
  const fileInput   = document.getElementById('importFileInput');
  const fileInfo    = document.getElementById('fileInfo');
  const fileName    = document.getElementById('fileName');
  const fileSize    = document.getElementById('fileSize');
  const fileRemove  = document.getElementById('fileRemove');
  const btnImport   = document.getElementById('btnImportPegawai');
  const statusDiv   = document.getElementById('importStatus');
  const modal       = document.getElementById('importPegawaiModal');

  function formatSize(bytes) {
    if (bytes < 1024) return bytes + ' B';
    if (bytes < 1048576) return (bytes / 1024).toFixed(1) + ' KB';
    return (bytes / 1048576).toFixed(2) + ' MB';
  }

  function showFile(file) {
    if (!file || !file.name.toLowerCase().endsWith('.xlsx')) {
      setStatus('warning', 'Hanya file .xlsx yang diperbolehkan.');
      return;
    }
    if (file.size > 5 * 1024 * 1024) {
      setStatus('warning', 'Ukuran file maksimal 5 MB.');
      return;
    }
    fileName.textContent = file.name;
    fileSize.textContent = formatSize(file.size);
    dropZone.style.display = 'none';
    fileInfo.classList.add('show');
    btnImport.disabled = false;
    statusDiv.classList.remove('show');
  }

  function clearFile() {
    fileInput.value = '';
    dropZone.style.display = '';
    fileInfo.classList.remove('show');
    btnImport.disabled = true;
    statusDiv.classList.remove('show');
  }

  function setStatus(type, msg) {
    statusDiv.className = 'import-status show ' + type;
    statusDiv.textContent = msg;
  }

  function setStatusWithList(type, msg, items) {
    statusDiv.className = 'import-status show ' + type;
    statusDiv.textContent = msg;
    if (items && items.length > 0) {
      var ul = document.createElement('ul');
      ul.style.margin = '8px 0 0';
      ul.style.paddingLeft = '18px';
      var limit = Math.min(items.length, 10);
      for (var i = 0; i < limit; i++) {
        var li = document.createElement('li');
        li.textContent = items[i];
        ul.appendChild(li);
      }
      if (items.length > 10) {
        var liMore = document.createElement('li');
        liMore.textContent = '...dan ' + (items.length - 10) + ' lainnya.';
        liMore.style.fontStyle = 'italic';
        liMore.style.color = '#64748b';
        ul.appendChild(liMore);
      }
      statusDiv.appendChild(ul);
    }
  }

  // Drag & drop
  ['dragenter','dragover'].forEach(function(evt) {
    dropZone.addEventListener(evt, function(e) { e.preventDefault(); dropZone.classList.add('dragover'); });
  });
  ['dragleave','drop'].forEach(function(evt) {
    dropZone.addEventListener(evt, function(e) { e.preventDefault(); dropZone.classList.remove('dragover'); });
  });
  dropZone.addEventListener('drop', function(e) {
    var file = e.dataTransfer.files[0];
    if (file) showFile(file);
  });

  // File input change
  fileInput.addEventListener('change', function() {
    if (fileInput.files[0]) showFile(fileInput.files[0]);
  });

  // Remove file
  fileRemove.addEventListener('click', clearFile);

  // Reset state saat modal dibuka ulang
  modal.addEventListener('show.bs.modal', function() {
    clearFile();
    statusDiv.classList.remove('show');
  });

  // Import handler
  btnImport.addEventListener('click', function() {
    var file = fileInput.files[0];
    if (!file) {
      setStatus('warning', 'Silakan pilih file Excel terlebih dahulu.');
      return;
    }

    var formData = new FormData();
    formData.append('file', file);
    formData.append('csrf', '<?= csrf_token() ?>');

    btnImport.disabled = true;
    btnImport.innerHTML = '<i class="bi bi-hourglass-split"></i> Mengimpor...';

    fetch('import_pegawai', { method: 'POST', body: formData })
      .then(function(res) { return res.json(); })
      .then(function(data) {
        if (data.success) {
          setStatus('success', data.message);
          setTimeout(function() {
            bootstrap.Modal.getInstance(modal).hide();
            location.reload();
          }, 1500);
        } else {
          if (data.count === 0 && data.errors && data.errors.length > 0) {
            setStatusWithList('danger', data.message, data.errors);
          } else {
            setStatus('danger', data.message);
          }
        }
      })
      .catch(function(err) {
        setStatus('danger', 'Terjadi kesalahan jaringan: ' + err.message);
      })
      .finally(function() {
        btnImport.disabled = false;
        btnImport.innerHTML = '<i class="bi bi-upload"></i> Mulai Import';
      });
  });
})();
</script>
