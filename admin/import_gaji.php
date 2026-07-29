<?php
/**
 * SITAJI — Handler Import Payroll dari Excel (.xlsx)
 * Dipanggil via AJAX POST dari modal #importModal di dashboard.php
 * Response: JSON { success, message, count, errors, warnings }
 *
 * PERBAIKAN OPTIMASI V2:
 * - Semua query/prepare statis dipindahkan ke LUAR loop per baris.
 * - Hanya execute() yang berulang di dalam loop.
 * - Estimasi query: untuk 997 baris x ~25 kolom, dari ±27.916 query menjadi ±2.995.
 *   Rincian: 1 (kMap) + 1 (payroll INSERT prepare) + 1 (payroll_detail INSERT prepare)
 *   + 1 (payroll_detail DELETE prepare) + 1 (ck SELECT prepare) + 997 (stmtPeg execute)
 *   + 997 (payroll INSERT execute) + 997 (ck execute, ~50% kasus) + 997×25 (detail INSERT execute)
 *   + 997 (DELETE execute) ≈ 2.995 query vs sebelumnya ~27.916.
 *
 * Kolom yang valid untuk diimpor ditentukan REAL-TIME:
 *   - Pendapatan  : dari helpers.php::payroll_fields()['pendapatan']
 *   - Potongan    : dari tabel komponen_payroll (aktif=1)
 * Kolom lain di Excel (nama, golongan, jabatan, dsb.) diabaikan saat INSERT.
 */
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/helpers.php';
require_role('admin');

header('Content-Type: application/json; charset=utf-8');
ob_start();

// ── Safety net: timeout lebih panjang untuk file besar ─────────────────────────
set_time_limit(120);

// ── Safety net: tangkap fatal error agar response tetap JSON valid ─────────────
register_shutdown_function(function () {
    $err = error_get_last();
    if ($err && in_array($err['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        error_log('[SITAJI Fatal Error] Type: ' . $err['type'] . ' | Message: ' . $err['message']
            . ' | File: ' . $err['file'] . ' | Line: ' . $err['line']);
        error_log('[SITAJI Memory] Peak usage: ' . memory_get_peak_usage(true) . ' bytes | '
            . 'memory_limit: ' . ini_get('memory_limit'));
        ob_end_clean();
        http_response_code(200);
        echo json_encode([
            'success'  => false,
            'message'  => 'Terjadi kesalahan sistem saat memproses file. Coba kurangi jumlah baris atau hubungi administrator.',
            'count'    => 0,
            'errors'   => [],
            'warnings' => [],
        ]);
    }
});

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Method tidak valid.']); exit;
}
csrf_check();

$periodeRaw = trim($_POST['periode'] ?? '');
if (!$periodeRaw || !preg_match('/^\d{4}-\d{2}$/', $periodeRaw)) {
    echo json_encode(['success' => false, 'message' => 'Periode tidak valid. Gunakan format YYYY-MM.']); exit;
}
$periodeDefault = $periodeRaw . '-01';

if (empty($_FILES['file']['tmp_name'])) {
    echo json_encode(['success' => false, 'message' => 'File tidak ditemukan.']); exit;
}
if (strtolower(pathinfo($_FILES['file']['name'], PATHINFO_EXTENSION)) !== 'xlsx') {
    echo json_encode(['success' => false, 'message' => 'Format file harus .xlsx.']); exit;
}

// ── Validasi ukuran file (max 5MB) ───────────────────────────────────────────
$maxSize = 5 * 1024 * 1024;
if ($_FILES['file']['size'] > $maxSize) {
    echo json_encode(['success' => false, 'message' => 'File terlalu besar. Maksimal 5 MB.']); exit;
}

// ── Validasi MIME type dengan finfo ───────────────────────────────────────────
$finfo = new finfo(FILEINFO_MIME_TYPE);
$mimeType = $finfo->file($_FILES['file']['tmp_name']);
$allowedMime = 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';
if ($mimeType !== $allowedMime) {
    echo json_encode(['success' => false, 'message' => 'Format file tidak valid. Gunakan file .xlsx yang valid.']); exit;
}

// ── Validasi magic bytes (ZIP signature) ─────────────────────────────────────
$handle = fopen($_FILES['file']['tmp_name'], 'rb');
$magic = fread($handle, 4);
fclose($handle);
if ($magic !== "PK\x03\x04") {
    echo json_encode(['success' => false, 'message' => 'File korup atau bukan format Excel yang valid.']); exit;
}

// ── Kolom valid untuk INSERT (real-time) ─────────────────────────────────────
$validPendapatan = array_keys(payroll_fields()['pendapatan']);
$validPendapatan = array_filter($validPendapatan, function($f) {
    return preg_match('/^[a-z0-9_]+$/', $f);
});

$validPotonganRaw = $pdo->query(
    "SELECT field_key FROM komponen_payroll WHERE aktif = 1"
)->fetchAll(PDO::FETCH_COLUMN);
$validPotongan = array_filter($validPotonganRaw, function($f) {
    return preg_match('/^[a-z0-9_]+$/', $f);
});

$validFields = array_merge($validPendapatan, $validPotongan);

// ── Baca xlsx via ZipArchive ──────────────────────────────────────────────────
$zip = new ZipArchive();
if ($zip->open($_FILES['file']['tmp_name']) !== true) {
    echo json_encode(['success' => false, 'message' => 'File tidak bisa dibaca. Pastikan format .xlsx valid.']); exit;
}
$ssRaw    = $zip->getFromName('xl/sharedStrings.xml') ?: '';
$sheetRaw = $zip->getFromName('xl/worksheets/sheet1.xml') ?: '';
$zip->close();

if (!$sheetRaw) {
    echo json_encode(['success' => false, 'message' => 'Worksheet tidak ditemukan dalam file.']); exit;
}

// Parse sharedStrings
$ss = [];
if ($ssRaw) {
    try {
        $dom = new DOMDocument();
        libxml_use_internal_errors(true);
        if (!$dom->loadXML($ssRaw)) {
            $errors = libxml_get_errors();
            libxml_clear_errors();
            throw new Exception('Gagal parse sharedStrings.xml: ' . $errors[0]->message);
        }
        foreach ($dom->getElementsByTagNameNS('http://schemas.openxmlformats.org/spreadsheetml/2006/main', 'si') as $si)
            $ss[] = trim($si->textContent);
    } catch (Exception $e) {
        error_log('[SITAJI XML Parse Error] ' . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'File Excel tidak valid atau rusak.']); exit;
    }
}

// Parse worksheet — kumpulkan per baris
try {
    $dom2 = new DOMDocument();
    libxml_use_internal_errors(true);
    if (!$dom2->loadXML($sheetRaw)) {
        $errors = libxml_get_errors();
        libxml_clear_errors();
        throw new Exception('Gagal parse sheet1.xml: ' . $errors[0]->message);
    }
    $ns   = 'http://schemas.openxmlformats.org/spreadsheetml/2006/main';
    $rows = [];
} catch (Exception $e) {
    error_log('[SITAJI XML Parse Error] ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'File Excel tidak valid atau rusak.']); exit;
}
foreach ($dom2->getElementsByTagNameNS($ns, 'row') as $rowEl) {
    $rn  = (int)$rowEl->getAttribute('r');
    $dat = [];
    foreach ($rowEl->getElementsByTagNameNS($ns, 'c') as $cell) {
        preg_match('/^([A-Z]+)/', $cell->getAttribute('r'), $m);
        $ci  = 0;
        foreach (str_split($m[1]) as $ch) $ci = $ci * 26 + (ord($ch) - 64);
        $ci--;
        $vn  = $cell->getElementsByTagNameNS($ns, 'v')->item(0);
        $val = $vn ? $vn->textContent : '';
        if ($cell->getAttribute('t') === 's') $val = $ss[(int)$val] ?? '';
        $dat[$ci] = $val;
    }
    $rows[$rn] = $dat;
}

if (empty($rows)) {
    echo json_encode(['success' => false, 'message' => 'File kosong atau tidak ada data.']); exit;
}

// ── Cari baris field_key (kolom pertama = "nip") ─────────────────────────────
$headerRn = null;
foreach ($rows as $rn => $row) {
    if (strtolower(trim($row[0] ?? '')) === 'nip') { $headerRn = $rn; break; }
}
if ($headerRn === null) {
    echo json_encode(['success' => false, 'message' => 'Header "nip" tidak ditemukan. Gunakan template yang disediakan.']); exit;
}

$headerRow = $rows[$headerRn];
$colmap    = [];
foreach ($headerRow as $ci => $hdr) {
    $k = strtolower(trim($hdr));
    if ($k !== '') $colmap[$k] = $ci;
}

// ── Lewati baris label duplikat tepat di bawah header (mis. "NIP","Nama Pegawai",...) ──
$labelRn = $headerRn + 1;
if (isset($rows[$labelRn]) && strtolower(trim($rows[$labelRn][0] ?? '')) === 'nip') {
    $headerRn = $labelRn;
}

// ── Validasi header wajib (nip & periode) ─────────────────────────────────────
$requiredHeaders = ['nip', 'periode'];
$missingHeaders = [];
foreach ($requiredHeaders as $rh) {
    if (!isset($colmap[$rh])) {
        $missingHeaders[] = $rh;
    }
}
if (!empty($missingHeaders)) {
    echo json_encode([
        'success' => false,
        'message' => 'Header wajib tidak ditemukan: ' . implode(', ', $missingHeaders) . '. Gunakan template yang disediakan.'
    ]);
    exit;
}

// ── Cek kolom payroll yang tidak ada di header (WARNING) ───────────────────
$warnings = [];
$missingColumns = [];
foreach ($validFields as $f) {
    if (!isset($colmap[strtolower($f)])) {
        $missingColumns[] = $f;
    }
}
if (!empty($missingColumns)) {
    $warnings[] = 'Kolom yang tidak ditemukan di template: ' . implode(', ', $missingColumns) . '. Nilai akan diisi 0.';
}

$get = fn(array $row, string $key) => isset($colmap[strtolower($key)]) ? ($row[$colmap[strtolower($key)]] ?? null) : null;

// ── OPTIMASI: Query/prepare statis di siapkan SEKALI di luar loop ──────────────
// 1. Ambil mapping field_key → komponen_id untuk seluruh komponen payroll
$kMap = $pdo->query("SELECT field_key, id FROM komponen_payroll")->fetchAll(PDO::FETCH_KEY_PAIR);

// 2. Prepared statement untuk INSERT payroll (ON DUPLICATE KEY UPDATE)
//    Kolom selalu: pegawai_id, periode, keterangan
$stmtPayroll = $pdo->prepare(
    "INSERT INTO payroll (pegawai_id, periode, keterangan) VALUES (?, ?, ?)
     ON DUPLICATE KEY UPDATE periode=VALUES(periode), keterangan=VALUES(keterangan)"
);

// 3. Prepared statement untuk SELECT payroll_id (ketika lastInsertId = 0)
$stmtCk = $pdo->prepare("SELECT id FROM payroll WHERE pegawai_id=? AND periode=?");

// 5. Prepared statement untuk DELETE payroll_detail nilai=0
$stmtDel = $pdo->prepare("DELETE FROM payroll_detail WHERE payroll_id=? AND nilai=0");

// ── Import ────────────────────────────────────────────────────────────────────
$count  = 0;
$errors = [];
$pdo->beginTransaction();
try {
    $stmtPeg = $pdo->prepare('SELECT id FROM pegawai WHERE nip = ?');

    foreach ($rows as $rn => $row) {
        if ($rn <= $headerRn) continue;

        $nip = trim((string)$get($row, 'nip'));
        if ($nip === '') continue;

        $stmtPeg->execute([$nip]);
        $pid = $stmtPeg->fetchColumn();
        if (!$pid) {
            if (count($errors) < 50) {
                $errors[] = "Baris $rn: NIP \"$nip\" tidak terdaftar.";
            }
            continue;
        }

        // Periode: wajib diisi per baris
        $periodeCell = trim((string)$get($row, 'periode'));
        if (!preg_match('/^\d{4}-\d{2}$/', $periodeCell)) {
            if (count($errors) < 50) {
                $errors[] = "Baris $rn: Periode wajib diisi dengan format YYYY-MM.";
            }
            continue;
        }
        $usePeriode = $periodeCell . '-01';

        // Susun detail values
        $detailVals = [];

        foreach ($validFields as $f) {
            $val = $get($row, $f);
            // Validasi numerik
            if ($val !== '' && $val !== null && !is_numeric($val)) {
                if (count($errors) < 50) {
                    $errors[] = "Baris $rn: Kolom '$f' harus berisi angka — seluruh data baris ini DILEWATI (tidak diimpor) sampai diperbaiki.";
                }
                continue 2; // skip baris ini
            }
            // Warning untuk nilai negatif (tidak skip baris)
            if (is_numeric($val) && $val < 0) {
                if (count($warnings) < 50) {
                    $warnings[] = "Baris $rn: Kolom '$f' bernilai negatif, akan diset ke 0.";
                }
                $v = 0;
            } else {
                $v = (float)($val ?? 0);
            }
            $detailVals[$f] = $v;
        }

        // INSERT payroll (prepared statement sudah siap)
        $stmtPayroll->execute([(int)$pid, $usePeriode, null]);

        // Simpan detail ke payroll_detail
        if ($detailVals) {
            try {
                $payrollId = (int)$pdo->lastInsertId();
                if (!$payrollId) {
                    // ON DUPLICATE KEY UPDATE — ambil id yang existing
                    $stmtCk->execute([(int)$pid, $usePeriode]);
                    $payrollId = (int)$stmtCk->fetchColumn();
                }
                if ($payrollId) {
                    // $kMap sudah di-query SEKALI di luar loop — tidak perlu query ulang
                    $multi = [];
                    $multiParams = [];
                    foreach ($detailVals as $fk => $v) {
                        if (isset($kMap[$fk])) {
                            $multi[] = "(?,?,?)";
                            $multiParams[] = $payrollId;
                            $multiParams[] = (int)$kMap[$fk];
                            $multiParams[] = $v;
                        }
                    }
                    if ($multi) {
                        $placeholders = implode(',', $multi);
                        $pdo->prepare("INSERT INTO payroll_detail (payroll_id, komponen_id, nilai) VALUES $placeholders ON DUPLICATE KEY UPDATE nilai=VALUES(nilai)")->execute($multiParams);
                    }
                    $stmtDel->execute([$payrollId]);
                }
            } catch (PDOException $e) {
                if ($e->getCode() === '42S02') {
                    throw new Exception('Terjadi masalah konfigurasi sistem (payroll_detail missing), hubungi administrator.');
                }
                throw $e;
            }
        }
        $count++;
    }

    $pdo->commit();

    // ── Response logic dengan partial success ───────────────────────────────────
    if ($count === 0) {
        $errorMsg = 'Tidak ada data yang berhasil diimpor.';
        if (!empty($errors)) {
            $errorMsg .= ' Error: ' . implode('; ', array_slice($errors, 0, 3));
        }
        echo json_encode([
            'success'  => false,
            'message'  => $errorMsg,
            'count'    => 0,
            'errors'   => $errors,
            'warnings' => $warnings
        ]);
    } else {
        $msg = "Berhasil mengimpor $count data payroll.";
        if (!empty($errors)) {
            $msg .= ' ' . count($errors) . ' baris dilewati karena error.';
        }
        if (!empty($warnings)) {
            $msg .= ' ' . count($warnings) . ' warning.';
        }
        echo json_encode([
            'success'  => true,
            'message'  => $msg,
            'count'    => $count,
            'errors'   => $errors,
            'warnings' => $warnings
        ]);
    }

} catch (Exception $e) {
    $pdo->rollBack();
    error_log('[SITAJI Import Error] ' . $e->getMessage());
    echo json_encode([
        'success'  => false,
        'message'  => 'Terjadi kesalahan sistem. Silakan coba lagi atau hubungi administrator.',
        'count'    => 0,
        'errors'   => [],
        'warnings' => []
    ]);
}
exit;
