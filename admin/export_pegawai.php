<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/helpers.php';
require_role('admin');

$rows = $pdo->query("SELECT nip, nama, golongan, jabatan, status FROM pegawai ORDER BY nama")->fetchAll();

$headers = ['NIP', 'NAMA_PEGAWAI', 'GOLONGAN', 'JABATAN', 'STATUS'];

$sharedStrings = [];
$ssIndex = [];
function addSS(string $s): int {
    global $sharedStrings, $ssIndex;
    if (!isset($ssIndex[$s])) { $ssIndex[$s] = count($sharedStrings); $sharedStrings[] = $s; }
    return $ssIndex[$s];
}

function colL(int $n): string {
    $r = '';
    for ($n++; $n > 0; $n = intdiv($n-1, 26))
        $r = chr(65 + ($n-1) % 26) . $r;
    return $r;
}

const S_HDR = 1;
const S_BLD = 3;

function statusLabel(string $status): string {
    return strtolower(trim($status)) === 'aktif' ? 'Aktif' : 'Nonaktif';
}

$nCols = count($headers);

$x = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' . "\n";
$x .= '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"'
    . ' xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">';

$x .= '<cols>'
    . '<col min="1" max="1" width="22" customWidth="1"/>'
    . '<col min="2" max="2" width="30" customWidth="1"/>'
    . '<col min="3" max="3" width="12" customWidth="1"/>'
    . '<col min="4" max="4" width="25" customWidth="1"/>'
    . '<col min="5" max="5" width="12" customWidth="1"/>'
    . '</cols>';

$x .= '<sheetData>';

$x .= '<row r="1" ht="28" customHeight="1">'
    . '<c r="A1" s="2" t="s"><v>' . addSS('DATA PEGAWAI — ' . date('d/m/Y')) . '</v></c>'
    . '</row>';

$x .= '<row r="2" ht="22" customHeight="1">';
foreach ($headers as $ci => $h)
    $x .= '<c r="' . colL($ci) . '2" s="' . S_HDR . '" t="s"><v>' . addSS($h) . '</v></c>';
$x .= '</row>';

$rn = 3;
foreach ($rows as $row) {
    $x .= '<row r="' . $rn . '">';
    $x .= '<c r="A' . $rn . '" t="s"><v>' . addSS($row['nip']) . '</v></c>';
    $x .= '<c r="B' . $rn . '" t="s"><v>' . addSS($row['nama']) . '</v></c>';
    $x .= '<c r="C' . $rn . '" t="s"><v>' . addSS($row['golongan']) . '</v></c>';
    $x .= '<c r="D' . $rn . '" t="s"><v>' . addSS($row['jabatan']) . '</v></c>';
    $s = statusLabel($row['status']);
    $x .= '<c r="E' . $rn . '" s="' . S_BLD . '" t="s"><v>' . addSS($s) . '</v></c>';
    $x .= '</row>';
    $rn++;
}

$x .= '</sheetData>';
$x .= '<mergeCells count="1"><mergeCell ref="A1:E1"/></mergeCells>';
$x .= '<pageSetup orientation="landscape" fitToPage="1" fitToWidth="1" fitToHeight="0"/>';
$x .= '</worksheet>';

$cnt = count($sharedStrings);
$ssXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
       . '<sst xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" count="'.$cnt.'" uniqueCount="'.$cnt.'">';
foreach ($sharedStrings as $s)
    $ssXml .= '<si><t xml:space="preserve">' . htmlspecialchars($s, ENT_XML1, 'UTF-8') . '</t></si>';
$ssXml .= '</sst>';

$stylesXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">
 <numFmts count="0"/>
 <fonts count="3">
  <font><sz val="10"/><name val="Calibri"/></font>
  <font><b/><sz val="10"/><name val="Calibri"/></font>
  <font><b/><sz val="11"/><color rgb="FFFFFFFF"/><name val="Calibri"/></font>
 </fonts>
 <fills count="3">
  <fill><patternFill patternType="none"/></fill>
  <fill><patternFill patternType="gray125"/></fill>
  <fill><patternFill patternType="solid"><fgColor rgb="FF2563EB"/></patternFill></fill>
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
 <cellXfs count="4">
  <xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>
  <xf numFmtId="0" fontId="2" fillId="2" borderId="0" xfId="0" applyAlignment="1"><alignment horizontal="center" vertical="center"/></xf>
  <xf numFmtId="0" fontId="2" fillId="2" borderId="1" xfId="0" applyAlignment="1"><alignment horizontal="center" vertical="center"/></xf>
  <xf numFmtId="0" fontId="1" fillId="0" borderId="0" xfId="0"/>
 </cellXfs>
</styleSheet>';

$tmp = tempnam(sys_get_temp_dir(), 'xlsx_peg_');
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
 <sheets><sheet name="Data Pegawai" sheetId="1" r:id="rId1"/></sheets>
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
$zip->addFromString('xl/styles.xml',            $stylesXml);
$zip->close();

$filename = 'pegawai_' . date('Y-m-d') . '.xlsx';
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Content-Length: ' . filesize($tmp));
header('Cache-Control: max-age=0');
readfile($tmp);
unlink($tmp);
exit;
