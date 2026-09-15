# 📋 PLAN: Fitur Pengajuan Penghapusan Periode Dana (Broadcast)

> **Referensi:**
> - PHP PDO: [php.net/manual/en](https://www.php.net/manual/en/) — `beginTransaction()`, `commit()`, `rollBack()`, prepared statements
> - MySQL 8.0: [dev.mysql.com/doc/refman/8.0](https://dev.mysql.com/doc/refman/8.0/) — `FOREIGN KEY ... ON DELETE SET NULL`, `ALTER TABLE ... MODIFY COLUMN ... ENUM`, `ALGORITHM=INSTANT`
> - Bootstrap 5.3: [getbootstrap.com/docs/5.3](https://getbootstrap.com/docs/5.3/) — Modal `show.bs.modal` event, Nav Pills `data-bs-toggle="pill"`, Badge `rounded-pill`

---

## 1. 🎯 Tujuan & Ruang Lingkup

Stakeholder dapat **mengajukan penghapusan** periode dana berstatus `broadcast` ke admin disertai **alasan**. Admin dapat **menyetujui** (data periode beserta seluruh relasinya dihapus permanen) atau **menolak** (data kembali ke `broadcast`).

### Batasan
- Hanya periode berstatus `broadcast` yang dapat diajukan hapus.
- Tidak dapat mengajukan hapus jika periode sedang `revisi_pending` atau `hapus_pending`.
- Tidak dapat mengajukan hapus jika sudah ada `dana_hapus_request` dengan `status='pending'` untuk periode tersebut.
- Penghapusan periode `draft` tetap menggunakan fitur lama (`stakeholder/hapus_periode.php`) — langsung hapus tanpa approval.

---

## 2. 🗄️ Database Schema

### 2a. Tabel Baru: `dana_hapus_request`

Mengikuti struktur `dana_revisi_request` yang sudah ada, dengan dua penyesuaian:

1. **`periode_id` nullable** (`int DEFAULT NULL`) — karena FK menggunakan `ON DELETE SET NULL`
2. **Snapshot columns** (`periode_label`, `nama_pemangku`, `total_pemasukan`, `total_pemanfaatan`) — disalin saat pengajuan, agar history tetap terbaca setelah periode dihapus

```sql
CREATE TABLE `dana_hapus_request` (
  `id` int NOT NULL AUTO_INCREMENT,
  `periode_id` int DEFAULT NULL,
  `diajukan_oleh` int NOT NULL,
  `alasan` text NOT NULL,
  `status` enum('pending','disetujui','ditolak') NOT NULL DEFAULT 'pending',
  `disetujui_oleh` int DEFAULT NULL,
  `catatan_admin` text,
  `diproses_at` datetime DEFAULT NULL,
  -- Snapshot (disalin saat pengajuan, agar history tetap terbaca setelah periode dihapus)
  `periode_label` varchar(30) NOT NULL,
  `nama_pemangku` varchar(150) NOT NULL,
  `total_pemasukan` decimal(15,2) NOT NULL DEFAULT 0.00,
  `total_pemanfaatan` decimal(15,2) NOT NULL DEFAULT 0.00,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_hapus_periode` (`periode_id`),
  KEY `idx_hapus_status` (`status`),
  KEY `idx_hapus_diajukan` (`diajukan_oleh`),
  KEY `idx_hapus_disetujui` (`disetujui_oleh`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
```

### 2b. Foreign Keys — `ON DELETE SET NULL`

> **MySQL 8.0 Reference (Context7):** `SET NULL` updates the child column to NULL, requiring that the column is **not defined as NOT NULL**. RESTRICT/NO ACTION reject the operation. CASCADE propagates the delete.

```sql
ALTER TABLE `dana_hapus_request`
  ADD CONSTRAINT `dana_hapus_request_fk_periode`
    FOREIGN KEY (`periode_id`) REFERENCES `dana_periode` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `dana_hapus_request_fk_diajukan`
    FOREIGN KEY (`diajukan_oleh`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `dana_hapus_request_fk_disetujui`
    FOREIGN KEY (`disetujui_oleh`) REFERENCES `users` (`id`) ON DELETE SET NULL;
```

**Alasan `ON DELETE SET NULL` untuk `periode_id`:** Saat admin approve, `dana_periode` di-DELETE. Dengan SET NULL, `dana_hapus_request.periode_id` menjadi NULL tetapi record history tetap ada. Berbeda dengan `dana_revisi_request` yang menggunakan CASCADE (revisi tidak relevan jika periode dihapus), hapus_request justru adalah catatan penghapusan itu sendiri — harus tetap ada.

### 2c. Alter `dana_periode.status` — Tambah Enum `hapus_pending`

> **MySQL 8.0 Reference (Context7):** `ALTER TABLE t1 MODIFY COLUMN c1 ENUM('a','b','c','d'), ALGORITHM=INSTANT;` — adding a new enum value is an instant operation.

```sql
ALTER TABLE `dana_periode`
  MODIFY COLUMN `status` enum('draft','broadcast','revisi_pending','hapus_pending') NOT NULL DEFAULT 'draft',
  ALGORITHM=INSTANT;
```

### 2d. Cascade Behavior Saat Periode Dihapus

Tabel-tabel berikut sudah memiliki `ON DELETE CASCADE` ke `dana_periode.id`, sehingga **otomatis terhapus** saat admin approve:

| Tabel | FK Constraint | Behavior |
|-------|---------------|----------|
| `dana_pemanfaatan` | `dana_pemanfaatan_ibfk_1` | CASCADE — rincian pemanfaatan terhapus |
| `dana_notifikasi_dibaca` | `dana_notifikasi_dibaca_ibfk_1` | CASCADE — status baca pegawai terhapus |
| `dana_revisi_request` | `dana_revisi_request_ibfk_1` | CASCADE — riwayat revisi terhapus |
| `dana_hapus_request` | `dana_hapus_request_fk_periode` | **SET NULL** — record tetap, `periode_id` jadi NULL |

---

## 3. 🔄 State Machine (Updated)

```
                    ┌────────┐  broadcast  ┌──────────┐
                    │ DRAFT  │ ──────────► │ BROADCAST │
                    └────────┘             └─────┬────┘
                     ▲                           │
                     │ hapus langsung            │ ajukan_revisi
                     │ (fitur lama)             ▼
                     │                   ┌─────────────────┐
                     │                   │ REVISI_PENDING   │
                     │                   └────┬────────────┘
                     │              admin     │  admin
                     │              approve  │  reject
                     │              ◄────────┤────────►
                     │                        │ (kembali ke broadcast)
                     │                        │
                     │  ajukan_hapus (BARU)   │
                     │                        ▼
                     │              ┌───────────────┐
                     │              │ HAPUS_PENDING  │
                     │              └──────┬────────┘
                     │           admin     │  admin
                     │           approve   │  reject
                     │           ◄────────┤────────►
                     │                    │ (kembali ke broadcast)
                     │                    │
                     └────────────────────┘
                       (data terhapus permanen)
```

**Aturan transisi:**
- `draft → broadcast` — via `broadcast_periode.php` (sudah ada)
- `broadcast → revisi_pending` — via `ajukan_revisi.php` (sudah ada)
- `revisi_pending → broadcast` — via `approve_revisi.php` approve/reject (sudah ada)
- `broadcast → hapus_pending` — via `ajukan_hapus.php` **(BARU)**
- `hapus_pending → (deleted)` — via `approve_hapus.php` approve **(BARU)**
- `hapus_pending → broadcast` — via `approve_hapus.php` reject **(BARU)**

---

## 4. 📁 File Baru & Modifikasi

### A. File BARU (3 file)

| # | File | Fungsi |
|---|------|--------|
| 1 | `migrations/010_create_hapus_request.php` | Buat tabel `dana_hapus_request` + alter enum `dana_periode.status`. Idempotent. Mengikuti pola `migrations/009_add_saldo_awal.php`. |
| 2 | `stakeholder/ajukan_hapus.php` | Endpoint POST pengajuan hapus periode broadcast. Mengikuti pola `stakeholder/ajukan_revisi.php`. |
| 3 | `admin/approve_hapus.php` | Endpoint POST approve/reject penghapusan. Mengikuti pola `admin/approve_revisi.php`. |

### B. File MODIFIKASI (3 file)

| # | File | Perubahan |
|---|------|-----------|
| 4 | `stakeholder/detail_periode.php` | Tambah query `$hapusPending`, badge, tombol "Ajukan Penghapusan", modal form alasan. Tambah flash messages. |
| 5 | `stakeholder/daftar_periode.php` | Tambah badge `hapus_pending` di `$statusBadge` array. Tambah flash messages. |
| 6 | `admin/pemangku_kepentingan.php` | Tambah tab "Approval Penghapusan" di nav-tabs. Tambah query `$hapusPendingList` + `$hapusHistoryList`. Tambah tab-pane content. |

---

## 5. 🔧 Detail Implementasi Per File

### 5a. `migrations/010_create_hapus_request.php`

Mengikuti pola `migrations/009_add_saldo_awal.php` — PHP script yang `require_once db.php`, cek idempotency, jalankan DDL.

```php
<?php
require_once __DIR__ . '/../includes/db.php';

try {
    // 1. Cek apakah tabel sudah ada
    $exists = $pdo->query("SHOW TABLES LIKE 'dana_hapus_request'")->fetch();
    if (!$exists) {
        $pdo->exec("CREATE TABLE dana_hapus_request ( ... ) ENGINE=InnoDB ...");
        echo "Table dana_hapus_request created.\n";
    } else {
        echo "Table dana_hapus_request already exists.\n";
    }

    // 2. Cek apakah FK sudah ada
    $fk = $pdo->query("SELECT 1 FROM information_schema.TABLE_CONSTRAINTS
                       WHERE TABLE_SCHEMA = DATABASE()
                       AND TABLE_NAME = 'dana_hapus_request'
                       AND CONSTRAINT_NAME = 'dana_hapus_request_fk_periode'")->fetch();
    if (!$fk) {
        $pdo->exec("ALTER TABLE dana_hapus_request ADD CONSTRAINT ... ON DELETE SET NULL");
        echo "Foreign keys added.\n";
    }

    // 3. Alter enum (idempotent — MODIFY COLUMN selalu valid)
    $pdo->exec("ALTER TABLE dana_periode
                MODIFY COLUMN status enum('draft','broadcast','revisi_pending','hapus_pending')
                NOT NULL DEFAULT 'draft', ALGORITHM=INSTANT");
    echo "Enum status updated.\n";

    echo "Done.\n";
} catch (PDOException $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    exit(1);
}
```

> **PHP PDO Reference (Context7):** DDL statements like `CREATE TABLE` trigger an implicit commit — `$pdo->rollBack()` cannot undo DDL. Karena itu migration tidak menggunakan transaction (DDL auto-commit), sama seperti migration 008 dan 009.

### 5b. `stakeholder/ajukan_hapus.php`

Mengikuti pola `stakeholder/ajukan_revisi.php` (103 lines). Perbedaan: tidak ada input nominal/rincian, hanya `alasan`.

```
1.  require_once auth.php, db.php, helpers.php
2.  require_role('stakeholder')
3.  Cek REQUEST_METHOD === 'POST', jika tidak → redirect daftar_periode
4.  csrf_check()
5.  $pkId = $_SESSION['stakeholder']['id']
6.  $periodeId = (int)($_POST['periode_id'] ?? 0)
7.  $alasan = trim($_POST['alasan'] ?? '')
8.  Validasi: $periodeId > 0 && $pkId > 0, jika tidak → redirect
9.  Validasi: $alasan !== '', jika tidak → redirect ?error=hapus_empty
10. Query: SELECT id, status, bulan, tahun, total_pemasukan, saldo_awal
           FROM dana_periode WHERE id = ? AND pemangku_kepentingan_id = ?
11. Jika tidak ditemukan → redirect daftar_periode
12. Cek status === 'broadcast', jika tidak → redirect detail_periode
13. Cek tidak ada hapus_request pending:
    SELECT COUNT(*) FROM dana_hapus_request WHERE periode_id = ? AND status = 'pending'
14. Cek tidak ada revisi_request pending:
    SELECT COUNT(*) FROM dana_revisi_request WHERE periode_id = ? AND status = 'pending'
15. Ambil snapshot data:
    - $periodeLabel = $bulanNama[bulan] . ' ' . tahun
    - $namaPemangku = $stakeholder['nama']
    - $totalPemasukan = periode.total_pemasukan
    - $totalPemanfaatan = SELECT SUM(nominal) FROM dana_pemanfaatan WHERE periode_id = ?
16. $userId = $_SESSION['user']['id']
17. try:
    $pdo->beginTransaction()
    INSERT INTO dana_hapus_request
      (periode_id, diajukan_oleh, alasan, status, periode_label, nama_pemangku,
       total_pemasukan, total_pemanfaatan)
      VALUES (?, ?, ?, 'pending', ?, ?, ?, ?)
    UPDATE dana_periode SET status = 'hapus_pending', updated_at = NOW() WHERE id = ?
    $pdo->commit()
    redirect detail_periode?id=X&success=hapus_submitted
18. catch:
    $pdo->rollBack()
    redirect detail_periode?id=X&error=hapus_failed
```

### 5c. `admin/approve_hapus.php`

Mengikuti pola `admin/approve_revisi.php` (136 lines).

```
1.  require_once auth.php, db.php, helpers.php
2.  require_role('admin')
3.  Cek REQUEST_METHOD === 'POST', jika tidak → redirect pemangku_kepentingan?tab=hapus
4.  csrf_check()
5.  $action = trim($_POST['action'] ?? '')
6.  $hapusId = (int)($_POST['hapus_id'] ?? 0)
7.  $catatanAdmin = trim($_POST['catatan_admin'] ?? '')
8.  $adminId = $_SESSION['user']['id']
9.  Validasi: $hapusId > 0, jika tidak → redirect
10. Query: SELECT * FROM dana_hapus_request WHERE id = ?
11. Jika tidak ditemukan → redirect ?error=not_found
12. Cek status === 'pending', jika tidak → redirect ?error=not_pending
13. $periodeId = (int)$hapusRequest['periode_id']

IF action === 'approve':
    14a. Query: SELECT * FROM dana_periode WHERE id = ?
    14b. Cek periode ada && status === 'hapus_pending', jika tidak → ?error=not_found
    14c. try:
        $pdo->beginTransaction()

        // UPDATE hapus_request SEBELUM DELETE periode
        // ( agar status tersimpan, lalu SET NULL saat periode di-delete )
        UPDATE dana_hapus_request
          SET status = 'disetujui', disetujui_oleh = ?, catatan_admin = ?, diproses_at = NOW()
          WHERE id = ?

        // DELETE periode — CASCADE akan hapus:
        //   - dana_pemanfaatan (CASCADE)
        //   - dana_notifikasi_dibaca (CASCADE)
        //   - dana_revisi_request (CASCADE)
        // SET NULL akan set:
        //   - dana_hapus_request.periode_id = NULL
        DELETE FROM dana_periode WHERE id = ?

        $pdo->commit()
        redirect ?tab=hapus&success=hapus_approved

    14d. catch:
        if ($pdo->inTransaction()) $pdo->rollBack()
        redirect ?tab=hapus&error=db

IF action === 'reject':
    15a. Validasi: $catatanAdmin !== '', jika tidak → ?error=reject_reason_required
    15b. try:
        $pdo->beginTransaction()

        UPDATE dana_periode SET status = 'broadcast', updated_at = NOW() WHERE id = ?
        UPDATE dana_hapus_request
          SET status = 'ditolak', disetujui_oleh = ?, catatan_admin = ?, diproses_at = NOW()
          WHERE id = ?

        $pdo->commit()
        redirect ?tab=hapus&success=hapus_rejected

    15c. catch:
        if ($pdo->inTransaction()) $pdo->rollBack()
        redirect ?tab=hapus&error=db

ELSE:
    redirect ?tab=hapus&error=invalid_action
```

> **PHP PDO Reference (Context7):** `beginTransaction()` → multiple `prepare()` + `execute()` → `commit()`. Jika ada exception, `rollBack()`. Gunakan `$pdo->inTransaction()` sebelum rollBack untuk safety. Prepared statements dengan `?` placeholders mencegah SQL injection.

### 5d. `stakeholder/detail_periode.php` — Modifikasi

#### Tambah query (setelah `$hasRevisiPending` check, ~line 31):

```php
// Cek apakah ada hapus pending
$hapusPending = $pdo->prepare("SELECT COUNT(*) FROM dana_hapus_request WHERE periode_id = ? AND status = 'pending'");
$hapusPending->execute([$id]);
$hasHapusPending = (int)$hapusPending->fetchColumn() > 0;
```

#### Tambah badge `$statusBadge` (line ~38):

```php
$statusBadge = [
    'draft' => 'bg-secondary',
    'broadcast' => 'bg-success',
    'revisi_pending' => 'bg-warning text-dark',
    'hapus_pending' => 'bg-danger',   // ← BARU
];
```

#### Tambah badge "Hapus Pending" (line ~63, setelah Revisi Pending badge):

```php
<?php if ($hasHapusPending): ?>
  <span class="badge bg-danger ms-1">Penghapusan Pending</span>
<?php endif; ?>
```

#### Tambah flash messages (line ~49):

```php
elseif ($error === 'hapus_empty') { $flashMsg = 'Alasan penghapusan tidak boleh kosong.'; $flashType = 'danger'; }
elseif ($error === 'hapus_failed') { $flashMsg = 'Gagal mengajukan penghapusan.'; $flashType = 'danger'; }
elseif ($success === 'hapus_submitted') { $flashMsg = 'Penghapusan berhasil diajukan. Menunggu persetujuan admin.'; $flashType = 'info'; }
```

#### Tambah tombol "Ajukan Penghapusan" (line ~140, di block `broadcast && !hasRevisiPending`):

> **Bootstrap 5.3 Reference (Context7):** Modal `show.bs.modal` event dengan `event.relatedTarget` + `data-bs-*` attributes untuk passing data ke modal.

```php
<?php elseif ($periode['status'] === 'broadcast' && !$hasRevisiPending && !$hasHapusPending): ?>
<div class="mt-4 d-flex gap-2">
  <button class="btn-primary-modern" data-bs-toggle="modal" data-bs-target="#revisiModal">
    <i class="bi bi-pencil-square"></i> Ajukan Revisi
  </button>
  <button class="btn-danger-modern" data-bs-toggle="modal" data-bs-target="#hapusModal"
          data-id="<?= $id ?>"
          data-periode="<?= e($bulanNama[(int)$periode['bulan']] . ' ' . $periode['tahun']) ?>"
          data-pemasukan="<?= rupiah($periode['total_pemasukan']) ?>"
          data-pemanfaatan="<?= rupiah($totalPemanfaatan) ?>">
    <i class="bi bi-trash"></i> Ajukan Penghapusan
  </button>
</div>
```

#### Tambah block `hapus_pending` (setelah `revisi_pending` block):

```php
<?php elseif ($periode['status'] === 'hapus_pending'): ?>
<div class="mt-3">
  <div class="alert alert-danger mb-0">
    <i class="bi bi-hourglass-split"></i> Penghapusan periode sedang dalam proses persetujuan admin.
  </div>
</div>
<?php endif; ?>
```

#### Tambah modal "Ajukan Penghapusan" (setelah `revisiModal`, sebelum `</script>`):

> **Bootstrap 5.3 Reference (Context7):** Modal structure: `.modal > .modal-dialog > .modal-content > .modal-header/.modal-body/.modal-footer`. Event `show.bs.modal` dengan `button.getAttribute('data-bs-whatever')`.

```php
<?php if ($periode['status'] === 'broadcast' && !$hasRevisiPending && !$hasHapusPending): ?>
<div class="modal fade" id="hapusModal" tabindex="-1" aria-label="Ajukan Penghapusan Periode">
<div class="modal-dialog modal-dialog-centered"><div class="modal-content modal-modern">
  <form method="post" action="ajukan_hapus">
    <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
    <input type="hidden" name="periode_id" value="<?= $id ?>">
    <div class="modal-header">
      <h5 class="modal-title fw-bold">Ajukan Penghapusan Periode</h5>
      <button class="btn-close" data-bs-dismiss="modal"></button>
    </div>
    <div class="modal-body">
      <div class="alert alert-warning">
        <i class="bi bi-exclamation-triangle"></i>
        <strong>Peringatan:</strong> Penghapusan akan menghapus <strong>seluruh data</strong>
        periode ini secara permanen setelah disetujui admin, termasuk rincian pemanfaatan
        dan status baca notifikasi pegawai. Data tidak dapat dikembalikan.
      </div>
      <div class="mb-3">
        <div class="small text-muted mb-1">Periode</div>
        <div class="fw-bold" id="hapusPeriodeLabel"></div>
      </div>
      <div class="row mb-3">
        <div class="col-6">
          <div class="small text-muted">Total Pemasukan</div>
          <div class="fw-bold text-success" id="hapusPemasukanLabel"></div>
        </div>
        <div class="col-6">
          <div class="small text-muted">Total Pemanfaatan</div>
          <div class="fw-bold text-danger" id="hapusPemanfaatanLabel"></div>
        </div>
      </div>
      <div class="mb-3">
        <label for="alasanHapus" class="form-label fw-semibold small text-uppercase text-muted">
          Alasan Penghapusan <span class="text-danger">*</span>
        </label>
        <textarea id="alasanHapus" class="form-control" name="alasan" rows="3"
                  placeholder="Jelaskan alasan penghapusan periode ini..." required></textarea>
      </div>
    </div>
    <div class="modal-footer border-0 d-flex gap-2">
      <button type="button" class="btn-secondary-modern" data-bs-dismiss="modal">Batal</button>
      <button class="btn-danger-modern" type="submit">
        <i class="bi bi-trash"></i> Kirim Pengajuan
      </button>
    </div>
  </form>
</div></div></div>

<script>
document.getElementById('hapusModal').addEventListener('show.bs.modal', function(event) {
  var btn = event.relatedTarget;
  this.querySelector('[name="periode_id"]').value = btn.getAttribute('data-id');
  document.getElementById('hapusPeriodeLabel').textContent = btn.getAttribute('data-periode');
  document.getElementById('hapusPemasukanLabel').textContent = btn.getAttribute('data-pemasukan');
  document.getElementById('hapusPemanfaatanLabel').textContent = btn.getAttribute('data-pemanfaatan');
});
</script>
<?php endif; ?>
```

### 5e. `stakeholder/daftar_periode.php` — Modifikasi

#### Tambah badge (line ~114):

```php
$statusBadge = [
    'draft' => 'bg-secondary',
    'broadcast' => 'bg-success',
    'revisi_pending' => 'bg-warning text-dark',
    'hapus_pending' => 'bg-danger',   // ← BARU
];
```

#### Tambah flash messages (line ~106):

```php
elseif ($success === 'hapus_submitted') { $flashMsg = 'Penghapusan berhasil diajukan. Menunggu persetujuan admin.'; $flashType = 'info'; }
```

### 5f. `admin/pemangku_kepentingan.php` — Modifikasi

#### Tambah query (setelah `$historyList`, ~line 54):

```php
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
```

#### Tambah flash messages (line ~78):

```php
elseif ($success === 'hapus_approved') { $flashMsg = 'Penghapusan periode disetujui. Data telah dihapus permanen.'; $flashType = 'success'; }
elseif ($success === 'hapus_rejected') { $flashMsg = 'Penghapusan periode ditolak. Data periode dikembalikan.'; $flashType = 'info'; }
```

#### Tambah nav tab (line ~115, setelah Approval Revisi):

> **Bootstrap 5.3 Reference (Context7):** Nav pills/tabs dengan `data-bs-toggle="pill"` dan `data-bs-target`. Badge count di dalam nav-link.

```html
<li class="nav-item">
  <a class="nav-link <?= $activeTab === 'hapus' ? 'active' : '' ?>" href="?tab=hapus">
    <i class="bi bi-trash3 me-1"></i> Approval Penghapusan
    <?php if ($totalHapusPending > 0): ?>
    <span class="badge bg-danger ms-1"><?= $totalHapusPending ?></span>
    <?php endif; ?>
  </a>
</li>
```

#### Tambah tab-pane content (setelah tab approval, sebelum `</div>` closing tab-content):

```html
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
      $collapseId = "hapusDetail_" . $hapusId;
  ?>
  <div class="app-card mb-4" style="border-left:4px solid var(--danger)">
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
    <table class="table data-table align-middle" data-empty-message="Belum ada riwayat penghapusan.">
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
          <td class="small"><?= date('d M Y H:i', strtotime($h['created_at'])) ?></td>
          <td class="small"><?= $h['diproses_at'] ? date('d M Y H:i', strtotime($h['diproses_at'])) : '-' ?></td>
          <td class="small"><?= e($h['diproses_username'] ?? '-') ?></td>
          <td class="small" style="max-width:200px"><?= e($h['catatan_admin'] ?? '-') ?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>
</div>
```

---

## 6. 🔒 Validasi & Keamanan

| # | Kontrol | Implementasi |
|---|---------|-------------|
| 1 | CSRF protection | `csrf_check()` di `ajukan_hapus.php` dan `approve_hapus.php` |
| 2 | Role check | `require_role('stakeholder')` di ajukan, `require_role('admin')` di approve |
| 3 | Ownership check | `WHERE id = ? AND pemangku_kepentingan_id = ?` — stakeholder hanya bisa hapus periode miliknya |
| 4 | Status check | Server-side: `status === 'broadcast'` — tidak bisa hapus `draft`/`revisi_pending`/`hapus_pending` |
| 5 | Double request check | Cek tidak ada `dana_hapus_request` pending + tidak ada `dana_revisi_request` pending |
| 6 | Alasan required | `$alasan !== ''` — tidak boleh kosong |
| 7 | Catatan admin required for reject | `$catatanAdmin === ''` → redirect `?error=reject_reason_required` |
| 8 | Transaction safety | `beginTransaction()` + `commit()` / `rollBack()` dengan `$pdo->inTransaction()` guard |
| 9 | SQL injection | Prepared statements dengan `?` placeholders di semua query |
| 10 | XSS protection | `e()` (htmlspecialchars) di semua output user input |

> **PHP PDO Reference (Context7):** Prepared statements dengan `?` placeholders + `execute([params])` mencegah SQL injection. `ATTR_EMULATE_PREPARES => false` (real prepared statements) sudah dikonfigurasi di `includes/db.php`.

---

## 7. ⚠️ Edge Cases

| Edge Case | Penanganan |
|-----------|------------|
| Periode bukan milik stakeholder | Ownership check di server-side → redirect tanpa error |
| Status bukan `broadcast` | Tolak, redirect ke detail |
| Sudah ada hapus_pending | Cek `dana_hapus_request WHERE status='pending'` → redirect |
| Sedang revisi_pending | Cek `dana_revisi_request WHERE status='pending'` → redirect |
| Admin approve → periode sudah tidak ada | Cek `SELECT * FROM dana_periode WHERE id = ?` → redirect `?error=not_found` |
| Admin approve → status bukan `hapus_pending` | Cek `periode.status === 'hapus_pending'` → redirect |
| Hapus_request sudah diproses sebelumnya | Cek `status === 'pending'` → redirect `?error=not_pending` |
| FK CASCADE saat approve | `dana_pemanfaatan`, `dana_notifikasi_dibaca`, `dana_revisi_request` auto-DELETE. `dana_hapus_request.periode_id` = NULL (SET NULL). |
| History tetap terbaca setelah hapus | Snapshot columns (`periode_label`, `nama_pemangku`, `total_pemasukan`, `total_pemanfaatan`) disimpan di `dana_hapus_request` |
| DDL implicit commit | Migration tidak menggunakan transaction — DDL auto-commit di MySQL (sama seperti migration 008, 009) |

---

## 8. 📊 Data Saat Ini (Diverifikasi)

| Tabel | Jumlah | Status |
|-------|--------|--------|
| `dana_periode` | 2 baris | id=1 (pk=2, Sep 2026, broadcast, is_revisi=1), id=2 (pk=3, Sep 2026, broadcast, is_revisi=0) |
| `dana_revisi_request` | 1 baris | id=1, periode_id=1, status=disetujui, diajukan_oleh=2983 (dharma) |
| `dana_pemanfaatan` | 5 baris | FK CASCADE ke dana_periode |
| `dana_notifikasi_dibaca` | 120 baris | FK CASCADE ke dana_periode |
| `pemangku_kepentingan` | 3 baris | id=1 (zawa), id=2 (dharma), id=3 (korpri) |

Kedua periode berstatus `broadcast` → **bisa langsung di-test** pengajuan hapus.

---

## 9. 📝 Urutan Implementasi

| Step | Task | Estimasi |
|------|------|----------|
| 1 | Buat `migrations/010_create_hapus_request.php` + jalankan di local | 15 min |
| 2 | Buat `stakeholder/ajukan_hapus.php` | 15 min |
| 3 | Modifikasi `stakeholder/detail_periode.php` — tambah query, badge, tombol, modal | 25 min |
| 4 | Modifikasi `stakeholder/daftar_periode.php` — tambah badge + flash | 5 min |
| 5 | Buat `admin/approve_hapus.php` | 20 min |
| 6 | Modifikasi `admin/pemangku_kepentingan.php` — tambah tab + content | 35 min |
| 7 | Test local: stakeholder ajukan hapus → admin approve → verify data terhapus | 15 min |
| 8 | Test local: stakeholder ajukan hapus → admin reject → verify data kembali | 10 min |
| 9 | Commit & push ke GitHub | 5 min |
| 10 | Deploy ke VPS: `git pull` + jalankan migration di VPS | 10 min |
| **Total** | | **~2.5 jam** |

---

## 10. 🚀 Deployment ke VPS

1. **Local test selesai** → `git add -A && git commit -m "feat: pengajuan penghapusan periode dana" && git push origin main`
2. **VPS:** `cd /www/wwwroot/sitaji.kemenagkabpasuruan.id && git pull origin main`
3. **VPS:** Jalankan migration: `php migrations/010_create_hapus_request.php` (via aaPanel Terminal)
4. **VPS:** Database tidak diubah selain tambah tabel + alter enum (sesuai constraint: "database di vps sudah ada jangan di rubah")
5. **Verify:** Buka https://sitaji.kemenagkabpasuruan.id/ → login stakeholder → test ajukan hapus

> ⚠️ Migration hanya menambah 1 tabel baru (`dana_hapus_request`) dan mengubah enum `dana_periode.status` (tambah value). **Tidak mengubah/menghapus data existing.** Data VPS tetap aman.
