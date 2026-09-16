// Validasi form generik: cegah submit jika ada input [required] yang kosong
document.addEventListener('submit', function (e) {
  const form = e.target;
  if (!form.matches('form[data-validate]')) return;
  let ok = true;
  form.querySelectorAll('[required]').forEach(function (el) {
    if (!String(el.value).trim()) {
      el.classList.add('is-invalid');
      ok = false;
    } else {
      el.classList.remove('is-invalid');
    }
  });
  if (!ok) {
    e.preventDefault();
    alert('Mohon lengkapi semua field yang wajib diisi.');
  }
});

// Toggle password visibility
document.addEventListener('click', function (e) {
  const btn = e.target.closest('[data-toggle-pwd]');
  if (!btn) return;
  const input = document.querySelector(btn.dataset.togglePwd);
  if (!input) return;
  input.type = input.type === 'password' ? 'text' : 'password';
  btn.querySelector('i')?.classList.toggle('bi-eye');
  btn.querySelector('i')?.classList.toggle('bi-eye-slash');
});

// DataTables default init
if (window.jQuery) {
  jQuery(function ($) {
    $('table.data-table').not('.no-datatable').each(function () {
      const $t = $(this);
      const emptyMsg = $t.data('empty-message') || 'Tidak ada data.';
      const isServerSide = $t.data('server-side') === true;
      const opts = {
        pageLength: 10,
        lengthChange: false,
        language: {
          search: 'Cari:',
          info: 'Menampilkan _START_ sampai _END_ dari _TOTAL_ entri',
          infoEmpty: 'Tidak ada data',
          zeroRecords: 'Data tidak ditemukan',
          emptyTable: emptyMsg,
          paginate: { previous: 'Sebelumnya', next: 'Selanjutnya' },
        },
      };
      if (isServerSide) {
        opts.serverSide = true;
        opts.ajax = $t.data('ajax');
        opts.deferRender = true;
        opts.processing = true;
        opts.columnDefs = [
          { orderable: false, targets: -1 },
        ];
      }
      // Urutan awal custom per tabel, mis. data-order='[[4,"desc"]]'
      const orderAttr = $t.attr('data-order');
      if (orderAttr) {
        try { opts.order = JSON.parse(orderAttr); } catch (e) { /* abaikan format tidak valid */ }
      }
      $t.DataTable(opts);
    });
  });
}

// Live ringkasan payroll
function recalcPayroll() {
  const form = document.getElementById('payrollForm');
  if (!form) return;

  let bruto = 0;
  let pot = 0;

  form.querySelectorAll('input[type="number"][data-group]').forEach(input => {
    const val = parseFloat(input.value) || 0;
    const group = input.dataset.group;
    if (group === 'pendapatan') {
      bruto += val;
    } else if (group === 'potongan_umum' || group === 'koperasi') {
      pot += val;
    }
  });

  const net = bruto - pot;
  const fmt = n => 'Rp ' + (n||0).toLocaleString('id-ID');
  document.getElementById('sumBruto').textContent = fmt(bruto);
  document.getElementById('sumPot').textContent = fmt(pot);
  document.getElementById('sumNet').textContent = fmt(net);
}
document.addEventListener('input', e => {
  if (e.target.closest('#payrollForm')) recalcPayroll();
});
document.addEventListener('DOMContentLoaded', recalcPayroll);
