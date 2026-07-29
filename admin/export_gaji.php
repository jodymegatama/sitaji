<?php
/**
 * SITAJI — Export Payroll ke Excel (.xlsx) TANPA library eksternal
 * Menggunakan Office Open XML (OOXML) murni PHP + ZipArchive bawaan PHP
 */
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/helpers.php';
require_role('admin');

// ── 1. Validasi periode ──────────────────────────────────────────────────────
$periodeRaw = trim($_GET['periode'] ?? '');
if (!$periodeRaw || !preg_match('/^\d{4}-\d{2}$/', $periodeRaw)) {
    http_response_code(400);
    die('Parameter periode tidak valid. Gunakan format YYYY-MM.');
}
$periodeDb    = $periodeRaw . '-01';
$periodeLabel = date('F Y', strtotime($periodeDb));

// ── 2. Ambil data ────────────────────────────────────────────────────────────
try {
    $stmt = $pdo->prepare("
        SELECT p.*, pg.nip, pg.nama, pg.golongan, pg.jabatan,
               COALESCE(ds.pendapatan_sum, 0) AS _detail_sum_pendapatan,
               COALESCE(ds.potongan_sum, 0) AS _detail_sum_potongan
        FROM payroll p
        JOIN pegawai pg ON pg.id = p.pegawai_id
        LEFT JOIN (
            SELECT pd.payroll_id,
                   SUM(CASE WHEN k.tipe='pendapatan' THEN pd.nilai ELSE 0 END) AS pendapatan_sum,
                   SUM(CASE WHEN k.tipe='potongan' THEN pd.nilai ELSE 0 END) AS potongan_sum
            FROM payroll_detail pd
            JOIN komponen_payroll k ON k.id = pd.komponen_id
            GROUP BY pd.payroll_id
        ) ds ON ds.payroll_id = p.id
        WHERE p.periode = ?
        ORDER BY pg.nama ASC
    ");
    $stmt->execute([$periodeDb]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Export Gaji Error: " . $e->getMessage());
    if ($e->getCode() === '42S02') {
        http_response_code(500);
        die('Terjadi masalah konfigurasi sistem, hubungi administrator.');
    }
    throw $e;
}

// ── 3. Susun kolom ───────────────────────────────────────────────────────────
$fields    = payroll_fields();
$allCols   = array_merge(
    array_keys($fields['pendapatan']),
    array_keys($fields['potongan_umum']),
    array_keys($fields['koperasi'])
);
$allLabels = array_merge(
    array_values($fields['pendapatan']),
    array_values($fields['potongan_umum']),
    array_values($fields['koperasi'])
);

// Tambah kolom detail dinamis
try {
    $stmtDc = $pdo->prepare("
        SELECT DISTINCT k.field_key, k.nama
        FROM payroll_detail pd
        JOIN komponen_payroll k ON k.id = pd.komponen_id
        JOIN payroll p ON p.id = pd.payroll_id
        WHERE p.periode = ? AND pd.nilai != 0
        ORDER BY k.field_key
    ");
    $stmtDc->execute([$periodeDb]);
    $detailCols = $stmtDc->fetchAll();
} catch (PDOException $e) {
    error_log("Export Gaji DetailCols Error: " . $e->getMessage());
    if ($e->getCode() === '42S02') {
        $detailCols = []; // Graceful degradation: no dynamic cols
    } else {
        throw $e;
    }
}
$detailColKeys = [];
$detailColLabels = [];
foreach ($detailCols as $dc) {
    if (!in_array($dc['field_key'], $allCols, true)) {
        $detailColKeys[] = $dc['field_key'];
        $detailColLabels[] = $dc['nama'] . ' (dinamis)';
    }
}

$headerRow = array_merge(['No','NIP','Nama','Golongan','Jabatan'], $allLabels, $detailColLabels, ['BRUTO','POTONGAN','THP']);

// Detail data per payroll_id
$detailByPayroll = [];
try {
    $detailAll = $pdo->query("
        SELECT pd.payroll_id, k.field_key, pd.nilai
        FROM payroll_detail pd
        JOIN komponen_payroll k ON k.id = pd.komponen_id
        WHERE pd.nilai != 0
    ")->fetchAll();
    foreach ($detailAll as $d) {
        $detailByPayroll[$d['payroll_id']][$d['field_key']] = (float)$d['nilai'];
    }
} catch (PDOException $e) {
    error_log("Export Gaji DetailAll Error: " . $e->getMessage());
    if ($e->getCode() === '42S02') {
        // detailByPayroll remains empty
    } else {
        throw $e;
    }
}

$dataRows = [];
$no = 1;
foreach ($rows as $r) {
    $row = [$no++, $r['nip'], $r['nama'], $r['golongan'] ?? '', $r['jabatan'] ?? ''];
    foreach ($allCols as $col) $row[] = (int)($detailByPayroll[$r['id']][$col] ?? 0);
    foreach ($detailColKeys as $dk) $row[] = (int)($detailByPayroll[$r['id']][$dk] ?? 0);
    $row[] = (int)total_bruto($r);
    $row[] = (int)total_potongan($r);
    $row[] = (int)take_home($r);
    $dataRows[] = $row;
}

$totalRow = ['','','','','TOTAL'];
foreach ($allCols as $col) {
    $sum = 0;
    foreach ($rows as $r) $sum += (int)($detailByPayroll[$r['id']][$col] ?? 0);
    $totalRow[] = (int)$sum;
}
foreach ($detailColKeys as $dk) {
    $sum = 0;
    foreach ($rows as $r) $sum += (int)($detailByPayroll[$r['id']][$dk] ?? 0);
    $totalRow[] = (int)$sum;
}
$totalRow[] = (int)array_sum(array_map('total_bruto', $rows));
$totalRow[] = (int)array_sum(array_map('total_potongan', $rows));
$totalRow[] = (int)(array_sum(array_map('total_bruto', $rows)) - array_sum(array_map('total_potongan', $rows)));

// ── 4. Shared strings ────────────────────────────────────────────────────────
$sharedStrings = [];
$ssIndex = [];
function addSS(string $s): int {
    global $sharedStrings, $ssIndex;
    if (!isset($ssIndex[$s])) {
        $ssIndex[$s] = count($sharedStrings);
        $sharedStrings[] = $s;
    }
    return $ssIndex[$s];
}

// ── 5. Konversi index kolom ke huruf ─────────────────────────────────────────
function colL(int $n): string {
    $r = '';
    for ($n++; $n > 0; $n = intdiv($n-1, 26))
        $r = chr(65 + ($n-1) % 26) . $r;
    return $r;
}

// Style index
const S_HDR  = 1; // header hijau
const S_TTL  = 2; // judul bold center
const S_BLD  = 3; // bold
const S_NUM  = 4; // angka biasa
const S_GRN  = 5; // angka hijau (bruto)
const S_RED  = 6; // angka merah (potongan)
const S_BLU  = 7; // angka biru (thp)
const S_EVN  = 8; // angka baris genap
const S_TOT  = 9; // total bold

// ── 6. Worksheet XML ─────────────────────────────────────────────────────────
function buildSheet(array $hdr, array $data, array $tot, string $label): string {
    $nCols = count($hdr);
    $x = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' . "\n";
    $x .= '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"'
        . ' xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">';

    // Column widths
    $x .= '<cols>'
        . '<col min="1" max="1" width="6"  customWidth="1"/>'
        . '<col min="2" max="2" width="22" customWidth="1"/>'
        . '<col min="3" max="3" width="30" customWidth="1"/>'
        . '<col min="4" max="4" width="12" customWidth="1"/>'
        . '<col min="5" max="5" width="25" customWidth="1"/>'
        . '<col min="6" max="' . $nCols . '" width="15" customWidth="1"/>'
        . '</cols>';

    $x .= '<sheetData>';

    // Baris 1: Judul
    $x .= '<row r="1" ht="28" customHeight="1">'
        . '<c r="A1" s="' . S_TTL . '" t="s"><v>' . addSS('LAPORAN PAYROLL — ' . strtoupper($label)) . '</v></c>'
        . '</row>';

    // Baris 2: Header
    $x .= '<row r="2" ht="22" customHeight="1">';
    foreach ($hdr as $ci => $h)
        $x .= '<c r="' . colL($ci) . '2" s="' . S_HDR . '" t="s"><v>' . addSS((string)$h) . '</v></c>';
    $x .= '</row>';

    // Baris data
    $rn = 3;
    $nRow = count($hdr);
    foreach ($data as $row) {
        $even = ($rn % 2 === 0);
        $x .= '<row r="' . $rn . '">';
        foreach ($row as $ci => $val) {
            $addr = colL($ci) . $rn;
            if ($ci === 0) {
                $x .= '<c r="' . $addr . '" s="' . S_BLD . '"><v>' . (int)$val . '</v></c>';
            } elseif ($ci <= 4) {
                $x .= '<c r="' . $addr . '" t="s"><v>' . addSS((string)$val) . '</v></c>';
            } else {
                if ($ci === $nRow-1)      $s = S_BLU;
                elseif ($ci === $nRow-2)  $s = S_RED;
                elseif ($ci === $nRow-3)  $s = S_GRN;
                elseif ($even)            $s = S_EVN;
                else                      $s = S_NUM;
                $x .= '<c r="' . $addr . '" s="' . $s . '"><v>' . (int)$val . '</v></c>';
            }
        }
        $x .= '</row>';
        $rn++;
    }

    // Baris total
    $x .= '<row r="' . $rn . '" ht="20" customHeight="1">';
    foreach ($tot as $ci => $val) {
        $addr = colL($ci) . $rn;
        if ($ci <= 4) {
            $x .= '<c r="' . $addr . '" s="' . S_BLD . '" t="s"><v>' . addSS((string)$val) . '</v></c>';
        } else {
            $nT = count($tot);
            if ($ci === $nT-1)      $s = S_BLU;
            elseif ($ci === $nT-2)  $s = S_RED;
            elseif ($ci === $nT-3)  $s = S_GRN;
            else                    $s = S_TOT;
            $x .= '<c r="' . $addr . '" s="' . $s . '"><v>' . (int)$val . '</v></c>';
        }
    }
    $x .= '</row>';

    $x .= '</sheetData>';
    $x .= '<mergeCells count="1"><mergeCell ref="A1:' . colL($nCols-1) . '1"/></mergeCells>';
    $x .= '<pageSetup orientation="landscape" fitToPage="1" fitToWidth="1" fitToHeight="0"/>';
    $x .= '</worksheet>';
    return $x;
}

// ── 7. Styles XML ────────────────────────────────────────────────────────────
function buildStyles(): string {
    return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">
 <numFmts count="1">
  <numFmt numFmtId="164" formatCode="#,##0"/>
 </numFmts>
 <fonts count="6">
  <font><sz val="10"/><name val="Calibri"/></font>
  <font><b/><sz val="10"/><name val="Calibri"/></font>
  <font><b/><sz val="11"/><color rgb="FFFFFFFF"/><name val="Calibri"/></font>
  <font><b/><sz val="10"/><color rgb="FF16A34A"/><name val="Calibri"/></font>
  <font><sz val="10"/><color rgb="FFDC2626"/><name val="Calibri"/></font>
  <font><b/><sz val="10"/><color rgb="FF2563EB"/><name val="Calibri"/></font>
 </fonts>
 <fills count="4">
  <fill><patternFill patternType="none"/></fill>
  <fill><patternFill patternType="gray125"/></fill>
  <fill><patternFill patternType="solid"><fgColor rgb="FF16A34A"/></patternFill></fill>
  <fill><patternFill patternType="solid"><fgColor rgb="FFF0FDF4"/></patternFill></fill>
 </fills>
 <borders count="2">
  <border><left/><right/><top/><bottom/><diagonal/></border>
  <border>
   <left style="thin"><color auto="1"/></left>
   <right style="thin"><color auto="1"/></right>
   <top style="thin"><color auto="1"/></top>
   <bottom style="thin"><color auto="1"/></bottom>
   <diagonal/>
  </border>
 </borders>
 <cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>
 <cellXfs count="10">
  <xf numFmtId="0"   fontId="0" fillId="0" borderId="0" xfId="0"/>
  <xf numFmtId="0"   fontId="2" fillId="2" borderId="1" xfId="0" applyAlignment="1"><alignment horizontal="center" vertical="center"/></xf>
  <xf numFmtId="0"   fontId="2" fillId="2" borderId="0" xfId="0" applyAlignment="1"><alignment horizontal="center" vertical="center"/></xf>
  <xf numFmtId="0"   fontId="1" fillId="0" borderId="0" xfId="0"/>
  <xf numFmtId="164" fontId="0" fillId="0" borderId="0" xfId="0"/>
  <xf numFmtId="164" fontId="3" fillId="0" borderId="0" xfId="0"/>
  <xf numFmtId="164" fontId="4" fillId="0" borderId="0" xfId="0"/>
  <xf numFmtId="164" fontId="5" fillId="0" borderId="0" xfId="0"/>
  <xf numFmtId="164" fontId="0" fillId="3" borderId="0" xfId="0"/>
  <xf numFmtId="164" fontId="1" fillId="0" borderId="0" xfId="0"/>
 </cellXfs>
</styleSheet>';
}

// ── 8. Build sheet (isi shared strings) ──────────────────────────────────────
$sheetXml = buildSheet($headerRow, $dataRows, $totalRow, $periodeLabel);

// ── 9. Shared strings XML ─────────────────────────────────────────────────────
$cnt = count($sharedStrings);
$ssXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
       . '<sst xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" count="'.$cnt.'" uniqueCount="'.$cnt.'">';
foreach ($sharedStrings as $s)
    $ssXml .= '<si><t xml:space="preserve">' . htmlspecialchars($s, ENT_XML1, 'UTF-8') . '</t></si>';
$ssXml .= '</sst>';

// ── 10. Buat ZIP (xlsx) ───────────────────────────────────────────────────────
$tmp = tempnam(sys_get_temp_dir(), 'xlsx_');
$zip = new ZipArchive();
$zip->open($tmp, ZipArchive::OVERWRITE);

$zip->addFromString('[Content_Types].xml',
'<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
 <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
 <Default Extension="xml"  ContentType="application/xml"/>
 <Override PartName="/xl/workbook.xml"          ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>
 <Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>
 <Override PartName="/xl/sharedStrings.xml"     ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sharedStrings+xml"/>
 <Override PartName="/xl/styles.xml"            ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>
</Types>');

$zip->addFromString('_rels/.rels',
'<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
 <Relationship Id="rId1"
   Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument"
   Target="xl/workbook.xml"/>
</Relationships>');

$zip->addFromString('xl/workbook.xml',
'<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"
          xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">
 <sheets><sheet name="Payroll ' . htmlspecialchars($periodeLabel, ENT_XML1) . '" sheetId="1" r:id="rId1"/></sheets>
</workbook>');

$zip->addFromString('xl/_rels/workbook.xml.rels',
'<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
 <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet"     Target="worksheets/sheet1.xml"/>
 <Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/sharedStrings" Target="sharedStrings.xml"/>
 <Relationship Id="rId3" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles"        Target="styles.xml"/>
</Relationships>');

$zip->addFromString('xl/worksheets/sheet1.xml', $sheetXml);
$zip->addFromString('xl/sharedStrings.xml',     $ssXml);
$zip->addFromString('xl/styles.xml',            buildStyles());
$zip->close();

// ── 11. Kirim ke browser ──────────────────────────────────────────────────────
$filename = 'payroll_' . date('F_Y', strtotime($periodeDb)) . '.xlsx';
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Content-Length: ' . filesize($tmp));
header('Cache-Control: max-age=0');
readfile($tmp);
unlink($tmp);
exit;
