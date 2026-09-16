<?php
/**
 * admin/approve_hapus.php
 *
 * Endpoint POST untuk approve/reject penghapusan periode dana.
 * Mengikuti pola admin/approve_revisi.php.
 *
 * Approve:
 *   - UPDATE dana_hapus_request SET status='disetujui' SEBELUM delete periode
 *   - DELETE FROM dana_periode (CASCADE hapus: pemanfaatan, notifikasi, revisi)
 *   - SET NULL: dana_hapus_request.periode_id = NULL (history tetap)
 *
 * Reject:
 *   - UPDATE dana_periode SET status='broadcast' (kembali)
 *   - UPDATE dana_hapus_request SET status='ditolak'
 *   - Catatan admin wajib diisi
 */
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/helpers.php';
require_role('admin');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: pemangku_kepentingan?tab=hapus');
    exit;
}
csrf_check();

$action = trim($_POST['action'] ?? '');
$hapusId = (int)($_POST['hapus_id'] ?? 0);
$catatanAdmin = trim($_POST['catatan_admin'] ?? '');
$adminId = $_SESSION['user']['id'];

if ($hapusId <= 0) {
    header('Location: pemangku_kepentingan?tab=hapus');
    exit;
}

$stmt = $pdo->prepare("SELECT * FROM dana_hapus_request WHERE id = ?");
$stmt->execute([$hapusId]);
$hapusRequest = $stmt->fetch();

if (!$hapusRequest) {
    header('Location: pemangku_kepentingan?tab=hapus&error=not_found');
    exit;
}

if ($hapusRequest['status'] !== 'pending') {
    header('Location: pemangku_kepentingan?tab=hapus&error=not_pending');
    exit;
}

$periodeId = (int)$hapusRequest['periode_id'];

if ($action === 'approve') {
    // Validasi: periode harus ada dan status = hapus_pending
    if ($periodeId <= 0) {
        // periode sudah tidak ada (sudah dihapus?) — tetap tandai disetujui
        try {
            $pdo->prepare("
                UPDATE dana_hapus_request
                SET status = 'disetujui', disetujui_oleh = ?, catatan_admin = ?, diproses_at = NOW()
                WHERE id = ?
            ")->execute([$adminId, $catatanAdmin ?: null, $hapusId]);
            header('Location: pemangku_kepentingan?tab=hapus&success=hapus_approved');
        } catch (Exception $e) {
            header('Location: pemangku_kepentingan?tab=hapus&error=db');
        }
        exit;
    }

    $periStmt = $pdo->prepare("SELECT * FROM dana_periode WHERE id = ?");
    $periStmt->execute([$periodeId]);
    $periode = $periStmt->fetch();

    if (!$periode || $periode['status'] !== 'hapus_pending') {
        header('Location: pemangku_kepentingan?tab=hapus&error=not_found');
        exit;
    }

    try {
        $pdo->beginTransaction();

        // Hapus file lampiran fisik sebelum DELETE periode (CASCADE)
        $lampStmt = $pdo->prepare("SELECT lampiran FROM dana_pemanfaatan WHERE periode_id = ? AND lampiran IS NOT NULL");
        $lampStmt->execute([$periodeId]);
        $lampiranFiles = $lampStmt->fetchAll(PDO::FETCH_COLUMN);
        foreach ($lampiranFiles as $f) {
            $p = __DIR__ . '/../uploads/lampiran/' . $f;
            if (file_exists($p)) unlink($p);
        }

        // UPDATE hapus_request SEBELUM DELETE periode
        // agar status tersimpan. Saat periode di-DELETE,
        // dana_hapus_request.periode_id akan di-SET NULL (FK ON DELETE SET NULL)
        $pdo->prepare("
            UPDATE dana_hapus_request
            SET status = 'disetujui', disetujui_oleh = ?, catatan_admin = ?, diproses_at = NOW()
            WHERE id = ?
        ")->execute([$adminId, $catatanAdmin ?: null, $hapusId]);

        // DELETE periode — CASCADE akan hapus:
        //   - dana_pemanfaatan (CASCADE)
        //   - dana_notifikasi_dibaca (CASCADE)
        //   - dana_revisi_request (CASCADE)
        // SET NULL akan set:
        //   - dana_hapus_request.periode_id = NULL
        $pdo->prepare("DELETE FROM dana_periode WHERE id = ?")->execute([$periodeId]);

        $pdo->commit();
        header('Location: pemangku_kepentingan?tab=hapus&success=hapus_approved');
    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        header('Location: pemangku_kepentingan?tab=hapus&error=db');
    }
    exit;

} elseif ($action === 'reject') {
    if ($catatanAdmin === '') {
        header('Location: pemangku_kepentingan?tab=hapus&error=reject_reason_required');
        exit;
    }

    try {
        $pdo->beginTransaction();

        // Kembalikan status periode ke broadcast (jika periode masih ada)
        if ($periodeId > 0) {
            $pdo->prepare("UPDATE dana_periode SET status = 'broadcast', updated_at = NOW() WHERE id = ?")
                ->execute([$periodeId]);
        }

        $pdo->prepare("
            UPDATE dana_hapus_request
            SET status = 'ditolak', disetujui_oleh = ?, catatan_admin = ?, diproses_at = NOW()
            WHERE id = ?
        ")->execute([$adminId, $catatanAdmin, $hapusId]);

        $pdo->commit();
        header('Location: pemangku_kepentingan?tab=hapus&success=hapus_rejected');
    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        header('Location: pemangku_kepentingan?tab=hapus&error=db');
    }
    exit;

} else {
    header('Location: pemangku_kepentingan?tab=hapus&error=invalid_action');
    exit;
}
