<?php
/**
 * SITAJI — Download Template Import Pegawai (Manual XML)
 *
 * Struktur template:
 *   Baris 1  : Judul (merge cell)
 *   Baris 2  : Header kolom (warna biru)
 *   Baris 3  : Baris contoh data
 *   Baris 4-10 : Baris kosong untuk diisi admin
 *   Baris 12+ : Petunjuk pengisian
 *
 * Kolom: NIP | NAMA_PEGAWAI | GOLONGAN | JABATAN | STATUS
 * Kolom internal (id, created_at) TIDAK di-export.
 */
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/helpers.php';
require_role('admin');

// ── Shared strings & helpers ──────────────────────────────────────────────────
$sharedStrings = [];
$ssIndex       = [];

function tss(string $s): int {
    global $sharedStrings, $ssIndex;
    if (!isset($ssIndex[$s])) { $ssIndex[$s] = count($sharedStrings); $sharedStrings[] = $s; }
    return $ssIndex[$s];
}

function tcol(int $n): string {
    $r = '';
    for ($n++; $n > 0; $n = intdiv($n - 1, 26)) $r = chr(65 + ($n - 1) % 26) . $r;
    return $r;
}

// ── Styles ────────────────────────────────────────────────────────────────────
function tplStyles(): string { return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">
 <numFmts count="0"/>
 <fonts count="5">
  <font><sz val="10"/><name val="Calibri"/></font>
  <font><b/><sz val="11"/><color rgb="FFFFFFFF"/><name val="Calibri"/></font>
  <font><b/><sz val="11"/><color rgb="FF1E3A5F"/><name val="Calibri"/></font>
  <font><b/><sz val="10"/><color rgb="FF166534"/><name val="Calibri"/></font>
  <font><sz val="9"/><color rgb="FF64748B"/><name val="Calibri"/></font>
 </fonts>
 <fills count="5">
  <fill><patternFill patternType="none"/></fill>
  <fill><patternFill patternType="gray125"/></fill>
  <fill><patternFill patternType="solid"><fgColor rgb="FF2563EB"/></patternFill></fill>
  <fill><patternFill patternType="solid"><fgColor rgb="FFF0F9FF"/></patternFill></fill>
  <fill><patternFill patternType="solid"><fgColor rgb="FFF8FAFC"/></patternFill></fill>
 </fills>
 <borders count="2">
  <border><left/><right/><top/><bottom/><diagonal/></border>
  <border>
   <left style="thin"><color rgb="FFE2E8F0"/></left><right style="thin"><color rgb="FFE2E8F0"/></right>
   <top style="thin"><color rgb="FFE2E8F0"/></top><bottom style="thin"><color rgb="FFE2E8F0"/></bottom><diagonal/>
  </border>
 </borders>
 <cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>
 <cellXfs count="6">
  <xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>
  <xf numFmtId="0" fontId="1" fillId="2" borderId="0" xfId="0" applyAlignment="1"><alignment horizontal="center" vertical="center" wrapText="1"/></xf>
  <xf numFmtId="0" fontId="2" fillId="3" borderId="1" xfId="0" applyAlignment="1"><alignment horizontal="center" vertical="center" wrapText="1"/></xf>
  <xf numFmtId="0" fontId="0" fillId="0" borderId="1" xfId="0" applyAlignment="1"><alignment vertical="center"/></xf>
  <xf numFmtId="0" fontId="3" fillId="0" borderId="1" xfId="0" applyAlignment="1"><alignment vertical="center"/></xf>
  <xf numFmtId="0" fontId="4" fillId="4" borderId="0" xfId="0" applyAlignment="1"><alignment wrapText="1"/></xf>
 </cellXfs>
</styleSheet>'; }

// ── Kolom template ────────────────────────────────────────────────────────────
$headers    = ['NIP', 'NAMA_PEGAWAI', 'GOLONGAN', 'JABATAN', 'STATUS (aktif/nonaktif)'];
$nCols      = count($headers);

// ── Baris contoh data ─────────────────────────────────────────────────────────
$sampleData = ['199401312025051004', 'JODY MEGATAMA', 'II/c', 'Staff Administrasi', 'aktif'];

// ── Build worksheet XML ───────────────────────────────────────────────────────
$x  = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' . "\n";
$x .= '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"'
    . ' xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">';

// Lebar kolom
$x .= '<cols>'
    . '<col min="1" max="1" width="22" customWidth="1"/>'   // NIP
    . '<col min="2" max="2" width="28" customWidth="1"/>'   // NAMA_PEGAWAI
    . '<col min="3" max="3" width="12" customWidth="1"/>'   // GOLONGAN
    . '<col min="4" max="4" width="24" customWidth="1"/>'   // JABATAN
    . '<col min="5" max="5" width="20" customWidth="1"/>'   // STATUS
    . '</cols>';

$x .= '<sheetData>';

// === BARIS 1: Judul ===
$x .= '<row r="1" ht="32" customHeight="1">'
    . '<c r="A1" s="1" t="s"><v>' . tss('TEMPLATE IMPORT PEGAWAI — SITAJI') . '</v></c>'
    . '</row>';

// === BARIS 2: Header kolom ===
$x .= '<row r="2" ht="24" customHeight="1">';
foreach ($headers as $ci => $hdr) {
    $x .= '<c r="' . tcol($ci) . '2" s="2" t="s"><v>' . tss($hdr) . '</v></c>';
}
$x .= '</row>';

// === BARIS 3: Data contoh ===
$x .= '<row r="3" ht="20" customHeight="1">';
foreach ($sampleData as $ci => $val) {
    $style = ($ci === 0) ? 4 : 3; // NIP bold
    $x .= '<c r="' . tcol($ci) . '3" s="' . $style . '" t="s"><v>' . tss($val) . '</v></c>';
}
$x .= '</row>';

// === BARIS 4-10: Baris kosong ===
for ($rn = 4; $rn <= 10; $rn++) {
    $x .= '<row r="' . $rn . '" ht="20" customHeight="1">';
    for ($ci = 0; $ci < $nCols; $ci++) {
        $x .= '<c r="' . tcol($ci) . $rn . '" s="3" t="s"><v>' . tss('') . '</v></c>';
    }
    $x .= '</row>';
}

// === BARIS 12+: Petunjuk pengisian ===
$petunjuk = [
    'PETUNJUK PENGISIAN TEMPLATE',
    '1. Kolom NIP wajib diisi 18 digit dan bersifat unik (tidak boleh duplikat).',
    '2. Kolom NAMA_PEGAWAI wajib diisi sesuai nama lengkap pegawai.',
    '3. Kolom GOLONGAN diisi dengan kode golongan, contoh: IV/a, III/d, II/c.',
    '4. Kolom JABATAN diisi dengan nama jabatan, contoh: Guru Ahli Madya.',
    '5. Kolom STATUS diisi "aktif" atau "nonaktif" (huruf kecil).',
    '6. Baris contoh (baris ke-3) sudah terisi — silakan dihapus atau ditimpa.',
    '7. Data yang diimpor akan otomatis membuatkan akun login dengan password: kemenag123.',
    '8. NIP yang sudah terdaftar di sistem akan dilewati (skip) saat import.',
];

$rn = 12;
$x .= '<row r="' . $rn . '" ht="22" customHeight="1">'
    . '<c r="A' . $rn . '" s="1" t="s"><v>' . tss('PETUNJUK PENGISIAN') . '</v></c>'
    . '</row>';
$rn++;

foreach ($petunjuk as $p) {
    $x .= '<row r="' . $rn . '" ht="18" customHeight="1">'
        . '<c r="A' . $rn . '" s="5" t="s"><v>' . tss($p) . '</v></c>'
        . '</row>';
    $rn++;
}

$x .= '</sheetData>';

// Merge cells untuk judul
$x .= '<mergeCells count="1">';
$x .= '<mergeCell ref="A1:' . tcol($nCols - 1) . '1"/>';
$x .= '</mergeCells>';

$x .= '<sheetView workbookViewId="0"><selection activeCell="A3" sqref="A3"/></sheetView>';
$x .= '<pageSetup orientation="landscape" fitToPage="1" fitToWidth="1" fitToHeight="0"/>';
$x .= '<freezePanes ySplit="2" xSplit="0" topLeftCell="A3" activePane="bottomLeft" state="frozen"/>';
$x .= '</worksheet>';

// ── Build file ────────────────────────────────────────────────────────────────
$cnt   = count($sharedStrings);
$ssXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
       . '<sst xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" count="' . $cnt . '" uniqueCount="' . $cnt . '">';
foreach ($sharedStrings as $s)
    $ssXml .= '<si><t xml:space="preserve">' . htmlspecialchars($s, ENT_XML1, 'UTF-8') . '</t></si>';
$ssXml .= '</sst>';

$tmp = tempnam(sys_get_temp_dir(), 'tpl_');
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
 <sheets><sheet name="Template Pegawai" sheetId="1" r:id="rId1"/></sheets>
</workbook>');

$zip->addFromString('xl/_rels/workbook.xml.rels',
'<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
 <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet"     Target="worksheets/sheet1.xml"/>
 <Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/sharedStrings" Target="sharedStrings.xml"/>
 <Relationship Id="rId3" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles"        Target="styles.xml"/>
</Relationships>');

$zip->addFromString('xl/worksheets/sheet1.xml', $x);
$zip->addFromString('xl/sharedStrings.xml',     $ssXml);
$zip->addFromString('xl/styles.xml',            tplStyles());
$zip->close();

$filename = 'template_pegawai.xlsx';
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Content-Length: ' . filesize($tmp));
header('Cache-Control: no-cache, no-store');
readfile($tmp);
unlink($tmp);
exit;
