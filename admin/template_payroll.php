<?php
/**
 * SITAJI — Download Template Import Payroll (Real-time dari Database)
 *
 * Struktur template:
 *   Baris 1  : Judul (merge cell)
 *   Baris 2  : Keterangan grup kolom (Identitas | Pendapatan | Potongan per kategori)
 *   Baris 3  : field_key  ← baris yang dibaca oleh import_gaji.php
 *   Baris 4  : Label nama kolom
 *   Baris 5+ : Data pegawai aktif (NIP & nama terisi, periode & angka kosong)
 *
 * Kolom pendapatan  : dari helpers.php::payroll_fields()['pendapatan']
 * Kolom potongan    : REAL-TIME dari tabel komponen_payroll (aktif=1)
 */
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/helpers.php';
require_login(); // INTENTIONAL: Admin & pegawai boleh download template ini

// ── 1. Ambil komponen pendapatan (dari helpers — nanti bisa dari DB juga) ───
$pendapatanFields = payroll_fields()['pendapatan'];
// $pendapatanFields = ['gaji_pokok' => 'Gaji Pokok', ...]

// ── 2. Ambil komponen potongan REAL-TIME dari DB (hanya yang aktif) ─────────
$komponen = $pdo->query(
    "SELECT field_key, nama, kategori FROM komponen_payroll WHERE aktif = 1 AND tipe = 'potongan' ORDER BY id ASC"
)->fetchAll();

// Kelompokkan potongan per kategori (untuk header grup)
$potonganByKategori = [];
foreach ($komponen as $k) {
    $potonganByKategori[$k['kategori']][$k['field_key']] = $k['nama'];
}

// ── 3. Ambil daftar pegawai aktif ────────────────────────────────────────────
$pegawaiList = $pdo->query(
    "SELECT nip, nama, golongan, jabatan FROM pegawai WHERE status = 'aktif' ORDER BY nama ASC"
)->fetchAll();

// ── 4. Susun kolom ────────────────────────────────────────────────────────────
// Kolom identitas
$identitasCols   = ['nip' => 'NIP', 'nama' => 'Nama Pegawai', 'periode' => 'Periode (YYYY-MM)', 'golongan' => 'Golongan', 'jabatan' => 'Jabatan'];

// Semua kolom field_key (urutan: identitas → pendapatan → potongan per kategori)
$allFieldKeys    = array_keys($identitasCols);
$allLabels       = array_values($identitasCols);
$allGroupLabels  = []; // untuk baris grup

// Isi grup identitas
foreach (array_keys($identitasCols) as $k) $allGroupLabels[] = 'Identitas';

// Pendapatan
$pendapatanStart = count($allFieldKeys);
foreach ($pendapatanFields as $key => $label) {
    $allFieldKeys[]   = $key;
    $allLabels[]      = $label;
    $allGroupLabels[] = 'Pendapatan';
}

// Potongan per kategori
foreach ($potonganByKategori as $kategori => $fields) {
    foreach ($fields as $key => $label) {
        $allFieldKeys[]   = $key;
        $allLabels[]      = $label;
        $allGroupLabels[] = $kategori;
    }
}

$nCols = count($allFieldKeys);

// ── 5. Hitung batas merge per grup ───────────────────────────────────────────
$grupMerge = []; // [ ['label'=>'...', 'start'=>0, 'end'=>0], ... ]
$prevGrup  = null;
$grupStart = 0;
foreach ($allGroupLabels as $ci => $g) {
    if ($g !== $prevGrup) {
        if ($prevGrup !== null) {
            $grupMerge[] = ['label' => $prevGrup, 'start' => $grupStart, 'end' => $ci - 1];
        }
        $prevGrup  = $g;
        $grupStart = $ci;
    }
}
$grupMerge[] = ['label' => $prevGrup, 'start' => $grupStart, 'end' => $nCols - 1];

// ── 6. Shared strings & helpers ───────────────────────────────────────────────
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
function tsc(string $col, int $row, int $style, string $type, $val): string {
    $addr = $col . $row;
    if ($type === 's') return "<c r=\"$addr\" s=\"$style\" t=\"s\"><v>" . tss((string)$val) . "</v></c>";
    if ($type === 'n') return "<c r=\"$addr\" s=\"$style\"><v>" . (float)$val . "</v></c>";
    return "<c r=\"$addr\" s=\"$style\" t=\"s\"><v>" . tss('') . "</v></c>"; // blank
}

// ── 7. Styles ─────────────────────────────────────────────────────────────────
// Style index:
// 0  default
// 1  grup identitas  — abu gelap bg, putih bold center
// 2  grup pendapatan — hijau gelap bg, putih bold center
// 3  grup potongan   — merah gelap bg, putih bold center
// 4  field_key       — abu biru bg, bold center, border
// 5  field_key pendapatan — hijau muda bg, bold center, border
// 6  field_key potongan  — merah muda bg, bold center, border
// 7  label           — kuning muda bg, center, border
// 8  data identitas (NIP/nama) — putih, bold, border kiri
// 9  data periode    — putih, center, border
// 10 data angka      — putih, angka format, border
// 11 data angka pendapatan — hijau muda bg, angka format, border
// 12 data angka potongan  — merah muda bg, angka format, border
// 13 petunjuk header — biru gelap bg putih bold
// 14 petunjuk isi    — abu muda, italic kecil

function tplStyles(): string { return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">
 <numFmts count="1"><numFmt numFmtId="164" formatCode="#,##0"/></numFmts>
 <fonts count="7">
  <font><sz val="9"/><name val="Calibri"/></font>
  <font><b/><sz val="9"/><color rgb="FFFFFFFF"/><name val="Calibri"/></font>
  <font><b/><sz val="9"/><name val="Calibri"/></font>
  <font><sz val="9"/><color rgb="FF475569"/><name val="Calibri"/></font>
  <font><b/><sz val="10"/><color rgb="FFFFFFFF"/><name val="Calibri"/></font>
  <font><b/><sz val="9"/><color rgb="FF166534"/><name val="Calibri"/></font>
  <font><b/><sz val="9"/><color rgb="FF991B1B"/><name val="Calibri"/></font>
 </fonts>
 <fills count="12">
  <fill><patternFill patternType="none"/></fill>
  <fill><patternFill patternType="gray125"/></fill>
  <fill><patternFill patternType="solid"><fgColor rgb="FF334155"/></patternFill></fill>
  <fill><patternFill patternType="solid"><fgColor rgb="FF166534"/></patternFill></fill>
  <fill><patternFill patternType="solid"><fgColor rgb="FF991B1B"/></patternFill></fill>
  <fill><patternFill patternType="solid"><fgColor rgb="FFE2E8F0"/></patternFill></fill>
  <fill><patternFill patternType="solid"><fgColor rgb="FFDCFCE7"/></patternFill></fill>
  <fill><patternFill patternType="solid"><fgColor rgb="FFFEE2E2"/></patternFill></fill>
  <fill><patternFill patternType="solid"><fgColor rgb="FFFFF9C4"/></patternFill></fill>
  <fill><patternFill patternType="solid"><fgColor rgb="FF1E3A5F"/></patternFill></fill>
  <fill><patternFill patternType="solid"><fgColor rgb="FFF8FAFC"/></patternFill></fill>
  <fill><patternFill patternType="solid"><fgColor rgb="FFFAFAFA"/></patternFill></fill>
 </fills>
 <borders count="3">
  <border><left/><right/><top/><bottom/><diagonal/></border>
  <border>
   <left style="thin"><color rgb="FFCBD5E1"/></left><right style="thin"><color rgb="FFCBD5E1"/></right>
   <top style="thin"><color rgb="FFCBD5E1"/></top><bottom style="thin"><color rgb="FFCBD5E1"/></bottom><diagonal/>
  </border>
  <border>
   <left style="medium"><color rgb="FF334155"/></left><right style="medium"><color rgb="FF334155"/></right>
   <top style="medium"><color rgb="FF334155"/></top><bottom style="medium"><color rgb="FF334155"/></bottom><diagonal/>
  </border>
 </borders>
 <cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>
 <cellXfs count="15">
  <xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>
  <xf numFmtId="0" fontId="1" fillId="2" borderId="0" xfId="0" applyAlignment="1"><alignment horizontal="center" vertical="center" wrapText="1"/></xf>
  <xf numFmtId="0" fontId="1" fillId="3" borderId="0" xfId="0" applyAlignment="1"><alignment horizontal="center" vertical="center" wrapText="1"/></xf>
  <xf numFmtId="0" fontId="1" fillId="4" borderId="0" xfId="0" applyAlignment="1"><alignment horizontal="center" vertical="center" wrapText="1"/></xf>
  <xf numFmtId="0" fontId="2" fillId="5" borderId="1" xfId="0" applyAlignment="1"><alignment horizontal="center" vertical="center" wrapText="1"/></xf>
  <xf numFmtId="0" fontId="5" fillId="6" borderId="1" xfId="0" applyAlignment="1"><alignment horizontal="center" vertical="center" wrapText="1"/></xf>
  <xf numFmtId="0" fontId="6" fillId="7" borderId="1" xfId="0" applyAlignment="1"><alignment horizontal="center" vertical="center" wrapText="1"/></xf>
  <xf numFmtId="0" fontId="3" fillId="8" borderId="1" xfId="0" applyAlignment="1"><alignment horizontal="center" vertical="center" wrapText="1"/></xf>
  <xf numFmtId="0" fontId="2" fillId="10" borderId="1" xfId="0"/>
  <xf numFmtId="0" fontId="3" fillId="10" borderId="1" xfId="0" applyAlignment="1"><alignment horizontal="center"/></xf>
  <xf numFmtId="164" fontId="0" fillId="10" borderId="1" xfId="0" applyAlignment="1"><alignment horizontal="right"/></xf>
  <xf numFmtId="164" fontId="5" fillId="6" borderId="1" xfId="0" applyAlignment="1"><alignment horizontal="right"/></xf>
  <xf numFmtId="164" fontId="6" fillId="7" borderId="1" xfId="0" applyAlignment="1"><alignment horizontal="right"/></xf>
  <xf numFmtId="0" fontId="4" fillId="9" borderId="0" xfId="0" applyAlignment="1"><alignment horizontal="center" vertical="center"/></xf>
  <xf numFmtId="0" fontId="3" fillId="11" borderId="0" xfId="0"/>
 </cellXfs>
</styleSheet>'; }

// Tentukan style per kolom index (untuk baris data)
function dataStyle(int $ci, array $allGroupLabels): int {
    $g = $allGroupLabels[$ci] ?? '';
    if ($ci === 0) return 8;  // NIP bold
    if ($ci === 1) return 8;  // Nama bold
    if ($ci === 2) return 9;  // Periode center
    if ($ci <= 4)  return 9;  // Golongan/jabatan center
    if ($g === 'Pendapatan') return 11;
    if ($g !== 'Identitas')  return 12;
    return 10;
}
function keyStyle(string $grup): int {
    if ($grup === 'Pendapatan') return 5;
    if ($grup === 'Identitas')  return 4;
    return 6;
}
function grupStyle(string $grup): int {
    if ($grup === 'Pendapatan') return 2;
    if ($grup === 'Identitas')  return 1;
    return 3;
}

// ── 8. Build worksheet ────────────────────────────────────────────────────────
function buildTplSheet(
    array $allFieldKeys, array $allLabels, array $allGroupLabels,
    array $grupMerge, array $pegawaiList, int $nCols
): string {
    $x  = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' . "\n";
    $x .= '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"'
        . ' xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">';

    // Lebar kolom
    $x .= '<cols>'
        . '<col min="1" max="1" width="22" customWidth="1"/>'  // NIP
        . '<col min="2" max="2" width="28" customWidth="1"/>'  // Nama
        . '<col min="3" max="3" width="16" customWidth="1"/>'  // Periode
        . '<col min="4" max="4" width="10" customWidth="1"/>'  // Golongan
        . '<col min="5" max="5" width="22" customWidth="1"/>'  // Jabatan
        . '<col min="6" max="' . $nCols . '" width="13" customWidth="1"/>'
        . '</cols>';

    $x .= '<sheetData>';

    // === BARIS 1: Judul utama ===
    $x .= '<row r="1" ht="32" customHeight="1">'
        . '<c r="A1" s="13" t="s"><v>' . tss('TEMPLATE IMPORT PAYROLL — SITAJI (Generated: ' . date('d/m/Y H:i') . ')') . '</v></c>'
        . '</row>';

    // === BARIS 2: Grup label ===
    $x .= '<row r="2" ht="22" customHeight="1">';
    foreach ($grupMerge as $grp) {
        $colAddr = tcol($grp['start']) . '2';
        $x .= '<c r="' . $colAddr . '" s="' . grupStyle($grp['label']) . '" t="s"><v>' . tss($grp['label']) . '</v></c>';
    }
    $x .= '</row>';

    // === BARIS 3: field_key (dibaca saat import) ===
    $x .= '<row r="3" ht="20" customHeight="1">';
    foreach ($allFieldKeys as $ci => $key) {
        $x .= '<c r="' . tcol($ci) . '3" s="' . keyStyle($allGroupLabels[$ci]) . '" t="s"><v>' . tss($key) . '</v></c>';
    }
    $x .= '</row>';

    // === BARIS 4: Label keterangan ===
    $x .= '<row r="4" ht="18" customHeight="1">';
    foreach ($allLabels as $ci => $lbl) {
        $x .= '<c r="' . tcol($ci) . '4" s="7" t="s"><v>' . tss($lbl) . '</v></c>';
    }
    $x .= '</row>';

    // === BARIS 5+: Data pegawai ===
    $rn = 5;
    foreach ($pegawaiList as $peg) {
        $x .= '<row r="' . $rn . '">';
        foreach ($allFieldKeys as $ci => $key) {
            $addr = tcol($ci) . $rn;
            $s    = dataStyle($ci, $allGroupLabels);
            if ($key === 'nip') {
                $x .= '<c r="' . $addr . '" s="' . $s . '" t="s"><v>' . tss($peg['nip']) . '</v></c>';
            } elseif ($key === 'nama') {
                $x .= '<c r="' . $addr . '" s="' . $s . '" t="s"><v>' . tss($peg['nama']) . '</v></c>';
            } elseif ($key === 'golongan') {
                $x .= '<c r="' . $addr . '" s="' . $s . '" t="s"><v>' . tss($peg['golongan'] ?? '') . '</v></c>';
            } elseif ($key === 'jabatan') {
                $x .= '<c r="' . $addr . '" s="' . $s . '" t="s"><v>' . tss($peg['jabatan'] ?? '') . '</v></c>';
            } elseif ($key === 'periode') {
                // Kosong — admin isi manual
                $x .= '<c r="' . $addr . '" s="' . $s . '" t="s"><v>' . tss('') . '</v></c>';
            } else {
                // Kolom angka — isi 0
                $x .= '<c r="' . $addr . '" s="' . $s . '"><v>0</v></c>';
            }
        }
        $x .= '</row>';
        $rn++;
    }

    // Baris kosong extra (5 baris) untuk pegawai tambahan manual
    for ($i = 0; $i < 5; $i++) {
        $x .= '<row r="' . $rn . '">';
        foreach ($allFieldKeys as $ci => $key) {
            $s    = dataStyle($ci, $allGroupLabels);
            $addr = tcol($ci) . $rn;
            if ($ci >= 5) {
                $x .= '<c r="' . $addr . '" s="' . $s . '"><v>0</v></c>';
            } else {
                $x .= '<c r="' . $addr . '" s="' . $s . '" t="s"><v>' . tss('') . '</v></c>';
            }
        }
        $x .= '</row>';
        $rn++;
    }

    // Baris petunjuk (2 baris kosong gap dulu)
    $rn += 2;
    $petunjuk = [
        'PETUNJUK PENGISIAN TEMPLATE',
        '1. Baris ke-3 (field_key) adalah nama kolom yang dibaca sistem — JANGAN diubah.',
        '2. Kolom NIP & Nama sudah terisi otomatis dari database. Jangan ubah NIP.',
        '3. Isi kolom "Periode" dengan format YYYY-MM, contoh: ' . date('Y-m') . '. Semua baris harus diisi.',
        '4. Kolom angka (pendapatan & potongan) diisi tanpa titik/koma pemisah. Gunakan 0 jika tidak ada.',
        '5. Jika ada pegawai baru, tambahkan di baris kosong di bawah — pastikan NIP terdaftar di sistem.',
        '6. Data yang sudah ada di periode yang sama akan ditimpa (overwrite) secara otomatis.',
        '7. Template ini di-generate real-time — komponen potongan mengikuti konfigurasi saat ini.',
        '8. Kolom Golongan & Jabatan bersifat informasi, tidak diimpor ke payroll.',
    ];
    $x .= '<row r="' . $rn . '" ht="20" customHeight="1"><c r="B' . $rn . '" s="13" t="s"><v>' . tss('PETUNJUK PENGISIAN') . '</v></c></row>';
    $rn++;
    foreach ($petunjuk as $p) {
        $x .= '<row r="' . $rn . '"><c r="B' . $rn . '" s="14" t="s"><v>' . tss($p) . '</v></c></row>';
        $rn++;
    }

    $x .= '</sheetData>';

    // Merge cells
    $merges   = [];
    $merges[] = 'A1:' . tcol($nCols - 1) . '1'; // judul
    foreach ($grupMerge as $grp) {
        if ($grp['start'] < $grp['end']) {
            $merges[] = tcol($grp['start']) . '2:' . tcol($grp['end']) . '2';
        }
    }
    // Merge petunjuk header
    $pRow = count($pegawaiList) + 5 + 2 + 5; // approximate
    // Hitung ulang baris petunjuk header yang tepat
    $pHeaderRow = 5 + count($pegawaiList) + 5 + 2;
    if ($nCols > 1) $merges[] = 'B' . $pHeaderRow . ':' . tcol($nCols - 1) . $pHeaderRow;

    $x .= '<mergeCells count="' . count($merges) . '">';
    foreach ($merges as $m) $x .= '<mergeCell ref="' . $m . '"/>';
    $x .= '</mergeCells>';

    $x .= '<sheetView workbookViewId="0"><selection activeCell="C5" sqref="C5"/></sheetView>';
    $x .= '<pageSetup orientation="landscape" fitToPage="1" fitToWidth="1" fitToHeight="0"/>';
    $x .= '<freezePanes ySplit="4" xSplit="2" topLeftCell="C5" activePane="bottomRight" state="frozen"/>';
    $x .= '</worksheet>';
    return $x;
}

// ── 9. Build file ─────────────────────────────────────────────────────────────
$sheetXml = buildTplSheet($allFieldKeys, $allLabels, $allGroupLabels, $grupMerge, $pegawaiList, $nCols);

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

$periodeLabel = date('F Y');
$zip->addFromString('xl/workbook.xml',
'<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"
          xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">
 <sheets><sheet name="Template Import" sheetId="1" r:id="rId1"/></sheets>
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
$zip->addFromString('xl/styles.xml',            tplStyles());
$zip->close();

$filename = 'template_import_payroll_' . date('Ymd_Hi') . '.xlsx';
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Content-Length: ' . filesize($tmp));
header('Cache-Control: no-cache, no-store');
readfile($tmp);
unlink($tmp);
exit;
