<?php
/**
 * SITAJI — Handler Import Pegawai dari Excel (.xlsx)
 * Dipanggil via AJAX POST dari modal #importPegawaiModal di pegawai.php
 * Response: JSON { success, message, count, errors, warnings }
 *
 * UPSERT berdasarkan NIP:
 *   - NIP baru  → INSERT pegawai + INSERT users (password: kemenag123)
 *   - NIP exist → UPDATE pegawai + SYNC users.pegawai_id (tidak overwrite password)
 */
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';
require_role('admin');

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Method tidak valid.', 'count' => 0, 'errors' => [], 'warnings' => []]); exit;
}
csrf_check();

if (empty($_FILES['file']['tmp_name'])) {
    echo json_encode(['success' => false, 'message' => 'File tidak ditemukan.', 'count' => 0, 'errors' => [], 'warnings' => []]); exit;
}
if (strtolower(pathinfo($_FILES['file']['name'], PATHINFO_EXTENSION)) !== 'xlsx') {
    echo json_encode(['success' => false, 'message' => 'Format file harus .xlsx.', 'count' => 0, 'errors' => [], 'warnings' => []]); exit;
}

// ── Validasi ukuran file (max 5MB) ───────────────────────────────────────────
$maxSize = 5 * 1024 * 1024;
if ($_FILES['file']['size'] > $maxSize) {
    echo json_encode(['success' => false, 'message' => 'File terlalu besar. Maksimal 5 MB.', 'count' => 0, 'errors' => [], 'warnings' => []]); exit;
}

// ── Validasi MIME type dengan finfo ───────────────────────────────────────────
$finfo = new finfo(FILEINFO_MIME_TYPE);
$mimeType = $finfo->file($_FILES['file']['tmp_name']);
$allowedMime = 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';
if ($mimeType !== $allowedMime) {
    echo json_encode(['success' => false, 'message' => 'Format file tidak valid. Gunakan file .xlsx yang valid.', 'count' => 0, 'errors' => [], 'warnings' => []]); exit;
}

// ── Validasi magic bytes (ZIP signature) ─────────────────────────────────────
$handle = fopen($_FILES['file']['tmp_name'], 'rb');
$magic = fread($handle, 4);
fclose($handle);
if ($magic !== "PK\x03\x04") {
    echo json_encode(['success' => false, 'message' => 'File korup atau bukan format Excel yang valid.', 'count' => 0, 'errors' => [], 'warnings' => []]); exit;
}

// ── Baca xlsx via ZipArchive ──────────────────────────────────────────────────
$zip = new ZipArchive();
if ($zip->open($_FILES['file']['tmp_name']) !== true) {
    echo json_encode(['success' => false, 'message' => 'File tidak bisa dibaca. Pastikan format .xlsx valid.', 'count' => 0, 'errors' => [], 'warnings' => []]); exit;
}
$ssRaw    = $zip->getFromName('xl/sharedStrings.xml') ?: '';
$sheetRaw = $zip->getFromName('xl/worksheets/sheet1.xml') ?: '';
$zip->close();

if (!$sheetRaw) {
    echo json_encode(['success' => false, 'message' => 'Worksheet tidak ditemukan dalam file.', 'count' => 0, 'errors' => [], 'warnings' => []]); exit;
}

// ── Parse sharedStrings ──────────────────────────────────────────────────────
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
        echo json_encode(['success' => false, 'message' => 'File Excel tidak valid atau rusak.', 'count' => 0, 'errors' => [], 'warnings' => []]); exit;
    }
}

// ── Parse worksheet — kumpulkan per baris ─────────────────────────────────────
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
    echo json_encode(['success' => false, 'message' => 'File Excel tidak valid atau rusak.', 'count' => 0, 'errors' => [], 'warnings' => []]); exit;
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
    echo json_encode(['success' => false, 'message' => 'File kosong atau tidak ada data.', 'count' => 0, 'errors' => [], 'warnings' => []]); exit;
}

// ── Cari baris header (baris dengan "nip" di kolom pertama) ──────────────────
$headerRn = null;
foreach ($rows as $rn => $row) {
    if (strtolower(trim($row[0] ?? '')) === 'nip') { $headerRn = $rn; break; }
}
if ($headerRn === null) {
    echo json_encode(['success' => false, 'message' => 'Header "nip" tidak ditemukan. Gunakan template yang disediakan.', 'count' => 0, 'errors' => [], 'warnings' => []]); exit;
}

// ── Build colmap (case-insensitive, strip parenthetical) ─────────────────────
$headerRow = $rows[$headerRn];
$colmap    = [];
foreach ($headerRow as $ci => $hdr) {
    $k = strtolower(trim($hdr));
    // Strip parenthetical: "status (aktif/nonaktif)" → "status"
    $k = preg_replace('/\s*\(.*\)$/', '', $k);
    if ($k !== '') $colmap[$k] = $ci;
}

// ── Validasi header wajib ────────────────────────────────────────────────────
$requiredHeaders = ['nip', 'nama_pegawai', 'golongan', 'jabatan', 'status'];
$missingHeaders  = [];
foreach ($requiredHeaders as $rh) {
    if (!isset($colmap[$rh])) {
        $missingHeaders[] = $rh;
    }
}
if (!empty($missingHeaders)) {
    echo json_encode([
        'success'  => false,
        'message'  => 'Header wajib tidak ditemukan: ' . implode(', ', $missingHeaders) . '. Gunakan template yang disediakan.',
        'count'    => 0,
        'errors'   => [],
        'warnings' => []
    ]);
    exit;
}

$warnings = [];
$get = fn(array $row, string $key) => isset($colmap[strtolower($key)]) ? ($row[$colmap[strtolower($key)]] ?? null) : null;

// ── Import dengan UPSERT ─────────────────────────────────────────────────────
$count        = 0;
$errors       = [];
$nipsInFile   = [];
$pdo->beginTransaction();
try {
    // Pre-load NIP existing dari database
    $existingNIPs = $pdo->query("SELECT nip FROM pegawai")->fetchAll(PDO::FETCH_COLUMN);
    $existingNIPs = array_flip($existingNIPs); // nip → index (fast lookup)

    $stmtFindUser = $pdo->prepare('SELECT id FROM users WHERE username = ? LIMIT 1');
    $stmtUpsertPeg = $pdo->prepare(
        "INSERT INTO pegawai (nip, nama, golongan, jabatan, status) VALUES (?, ?, ?, ?, ?)
         ON DUPLICATE KEY UPDATE
            nama     = VALUES(nama),
            golongan = VALUES(golongan),
            jabatan  = VALUES(jabatan),
            status   = VALUES(status)"
    );
    $stmtInsertUser = $pdo->prepare(
        "INSERT INTO users (username, password_hash, role, pegawai_id) VALUES (?, ?, 'pegawai', ?)"
    );
    $stmtSyncUser = $pdo->prepare(
        "UPDATE users SET pegawai_id = ? WHERE id = ?"
    );
    $stmtGetPegId = $pdo->prepare('SELECT id FROM pegawai WHERE nip = ? LIMIT 1');

    $passwordHash = password_hash('kemenag123', PASSWORD_DEFAULT);

    foreach ($rows as $rn => $row) {
        if ($rn <= $headerRn) continue;

        // ── NIP ──────────────────────────────────────────────────────────────
        $nip = trim((string)($get($row, 'nip') ?? ''));
        if ($nip === '') continue; // baris kosong — skip senyap

        if (strlen($nip) > 25) {
            if (count($errors) < 50) $errors[] = "Baris $rn: NIP \"$nip\" melebihi 25 karakter.";
            continue;
        }

        if (in_array($nip, $nipsInFile, true)) {
            if (count($errors) < 50) $errors[] = "Baris $rn: NIP \"$nip\" duplikat dalam file.";
            continue;
        }
        $nipsInFile[] = $nip;

        // ── Nama Pegawai ────────────────────────────────────────────────────
        $nama = trim((string)($get($row, 'nama_pegawai') ?? ''));
        if ($nama === '') {
            if (count($errors) < 50) $errors[] = "Baris $rn: Nama Pegawai wajib diisi.";
            continue;
        }

        // ── Status ──────────────────────────────────────────────────────────
        $status = strtolower(trim((string)($get($row, 'status') ?? '')));
        if ($status !== 'aktif' && $status !== 'nonaktif') {
            if (count($errors) < 50) $errors[] = "Baris $rn: Status \"$status\" tidak valid. Gunakan 'aktif' atau 'nonaktif'.";
            continue;
        }

        // ── Golongan & Jabatan (optional) ────────────────────────────────────
        $golongan = trim((string)($get($row, 'golongan') ?? ''));
        $jabatan  = trim((string)($get($row, 'jabatan') ?? ''));

        // ── UPSERT pegawai ──────────────────────────────────────────────────
        $isUpdate = isset($existingNIPs[$nip]);

        $stmtUpsertPeg->execute([
            $nip,
            $nama,
            $golongan !== '' ? $golongan : null,
            $jabatan  !== '' ? $jabatan  : null,
            $status
        ]);

        // Dapatkan pegawai_id (baik insert maupun update)
        $stmtGetPegId->execute([$nip]);
        $pegawaiId = (int)$stmtGetPegId->fetchColumn();

        // ── Users: auto-create atau sync ─────────────────────────────────────
        $stmtFindUser->execute([$nip]);
        $userId = $stmtFindUser->fetchColumn();

        if ($userId) {
            // User sudah ada → sync pegawai_id jika berbeda
            if ($pegawaiId > 0) {
                $stmtSyncUser->execute([$pegawaiId, (int)$userId]);
            }
        } else {
            // User belum ada → buat baru (password default, JANGAN overwrite)
            $stmtInsertUser->execute([$nip, $passwordHash, $pegawaiId]);
        }

        $count++;
    }

    $pdo->commit();

    // ── Response logic dengan partial success ────────────────────────────────
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
        $msg = "$count data pegawai berhasil diimpor.";
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
    error_log('[SITAJI Import Pegawai Error] ' . $e->getMessage());
    echo json_encode([
        'success'  => false,
        'message'  => 'Terjadi kesalahan sistem. Silakan coba lagi atau hubungi administrator.',
        'count'    => 0,
        'errors'   => [],
        'warnings' => []
    ]);
}
exit;
