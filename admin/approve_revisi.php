<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/helpers.php';
require_role('admin');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: pemangku_kepentingan?tab=approval');
    exit;
}
csrf_check();

$action = trim($_POST['action'] ?? '');
$revisiId = (int)($_POST['revisi_id'] ?? 0);
$catatanAdmin = trim($_POST['catatan_admin'] ?? '');
$adminId = $_SESSION['user']['id'];

if ($revisiId <= 0) {
    header('Location: pemangku_kepentingan?tab=approval');
    exit;
}

$stmt = $pdo->prepare("SELECT * FROM dana_revisi_request WHERE id = ?");
$stmt->execute([$revisiId]);
$revisi = $stmt->fetch();

if (!$revisi) {
    header('Location: pemangku_kepentingan?tab=approval&error=not_found');
    exit;
}

if ($revisi['status'] !== 'pending') {
    header('Location: pemangku_kepentingan?tab=approval&error=not_pending');
    exit;
}

$periodeId = (int)$revisi['periode_id'];

if ($action === 'approve') {
    $periStmt = $pdo->prepare("SELECT * FROM dana_periode WHERE id = ?");
    $periStmt->execute([$periodeId]);
    $periode = $periStmt->fetch();

    if (!$periode || $periode['status'] !== 'revisi_pending') {
        header('Location: pemangku_kepentingan?tab=approval&error=not_found');
        exit;
    }

    $dataBaru = json_decode($revisi['data_baru'], true);
    if (!$dataBaru || !isset($dataBaru['total_pemasukan'])) {
        header('Location: pemangku_kepentingan?tab=approval&error=db');
        exit;
    }

    $newPemasukan = (float)$dataBaru['total_pemasukan'];
    $newRincian = $dataBaru['pemanfaatan'] ?? [];

    if ($newPemasukan > 9999999999999) {
        header('Location: pemangku_kepentingan?tab=approval&error=nominal_over');
        exit;
    }
    foreach ($newRincian as $r) {
        if ((float)($r['nominal'] ?? 0) > 9999999999999) {
            header('Location: pemangku_kepentingan?tab=approval&error=nominal_over');
            exit;
        }
    }

    try {
        $pdo->beginTransaction();

        $pdo->prepare(
            "UPDATE dana_periode 
            SET total_pemasukan = ?, status = 'broadcast', broadcast_at = NOW(), 
                is_revisi = 1, updated_at = NOW()
            WHERE id = ?
        ")->execute([$newPemasukan, $periodeId]);

        // Collect lampiran lama SEBELUM delete (file fisik cleanup)
        $oldLamp = $pdo->prepare("SELECT lampiran FROM dana_pemanfaatan WHERE periode_id = ? AND lampiran IS NOT NULL");
        $oldLamp->execute([$periodeId]);
        $oldLampFiles = $oldLamp->fetchAll(PDO::FETCH_COLUMN);

        $pdo->prepare("DELETE FROM dana_pemanfaatan WHERE periode_id = ?")->execute([$periodeId]);

        if (!empty($newRincian)) {
            $insStmt = $pdo->prepare("INSERT INTO dana_pemanfaatan (periode_id, keterangan, nominal, lampiran) VALUES (?, ?, ?, ?)");
            foreach ($newRincian as $r) {
                $insStmt->execute([$periodeId, $r['keterangan'], (float)$r['nominal'], $r['lampiran'] ?? null]);
            }
        }

        $pdo->prepare("DELETE FROM dana_notifikasi_dibaca WHERE periode_id = ?")->execute([$periodeId]);

        // Hapus file lampiran lama HANYA yang tidak dipakai lagi oleh rincian baru
        $newLampSet = [];
        foreach ($newRincian as $r) {
            if (!empty($r['lampiran'])) $newLampSet[$r['lampiran']] = true;
        }
        foreach ($oldLampFiles as $f) {
            if (isset($newLampSet[$f])) continue; // lampiran dipertahankan → jangan hapus
            $p = __DIR__ . '/../uploads/lampiran/' . basename($f);
            if (file_exists($p)) unlink($p);
        }

        $pdo->prepare("
            UPDATE dana_revisi_request 
            SET status = 'disetujui', disetujui_oleh = ?, catatan_admin = ?, diproses_at = NOW() 
            WHERE id = ?
        ")->execute([$adminId, $catatanAdmin ?: null, $revisiId]);

        $pdo->commit();
        header('Location: pemangku_kepentingan?tab=approval&success=approved');
    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        header('Location: pemangku_kepentingan?tab=approval&error=db');
    }
    exit;

} elseif ($action === 'reject') {
    if ($catatanAdmin === '') {
        header('Location: pemangku_kepentingan?tab=approval&error=reject_reason_required');
        exit;
    }

    try {
        $pdo->beginTransaction();

        $pdo->prepare("
            UPDATE dana_periode 
            SET status = 'broadcast', updated_at = NOW()
            WHERE id = ?
        ")->execute([$periodeId]);

        $pdo->prepare("
            UPDATE dana_revisi_request 
            SET status = 'ditolak', disetujui_oleh = ?, catatan_admin = ?, diproses_at = NOW() 
            WHERE id = ?
        ")->execute([$adminId, $catatanAdmin, $revisiId]);

        $pdo->commit();

        // Cleanup: hapus PDF yang diupload untuk revisi ini (jangan hapus yang masih dipakai periode lain)
        $dataBaru = json_decode($revisi['data_baru'] ?? '', true) ?: [];
        $newLampFiles = [];
        foreach (($dataBaru['pemanfaatan'] ?? []) as $r) {
            if (!empty($r['lampiran'])) $newLampFiles[] = $r['lampiran'];
        }
        if ($newLampFiles) {
            $ph = implode(',', array_fill(0, count($newLampFiles), '?'));
            $usedStmt = $pdo->prepare("SELECT lampiran FROM dana_pemanfaatan WHERE lampiran IN ($ph)");
            $usedStmt->execute($newLampFiles);
            $used = $usedStmt->fetchAll(PDO::FETCH_COLUMN);
            foreach ($newLampFiles as $f) {
                if (in_array($f, $used, true)) continue; // masih dipakai → pertahankan
                $p = __DIR__ . '/../uploads/lampiran/' . basename($f);
                if (file_exists($p)) unlink($p);
            }
        }

        header('Location: pemangku_kepentingan?tab=approval&success=rejected');
    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        header('Location: pemangku_kepentingan?tab=approval&error=db');
    }
    exit;

} else {
    header('Location: pemangku_kepentingan?tab=approval&error=invalid_action');
    exit;
}
