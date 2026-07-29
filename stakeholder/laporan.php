<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/helpers.php';
require_role('stakeholder');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
}

$cu = current_user();
$stmt = $pdo->prepare("SELECT * FROM pemangku_kepentingan WHERE user_id = ? AND aktif = 1");
$stmt->execute([$cu['id']]);
$stakeholder = $stmt->fetch();
if (!$stakeholder) { http_response_code(403); die('Akun stakeholder tidak aktif.'); }
$pkId = (int)$stakeholder['id'];

$dari = $_POST['dari'] ?? '';
$sampai = $_POST['sampai'] ?? '';

$bulanNama = [1=>'Januari',2=>'Februari',3=>'Maret',4=>'April',5=>'Mei',6=>'Juni',
              7=>'Juli',8=>'Agustus',9=>'September',10=>'Oktober',11=>'November',12=>'Desember'];
$bulanSingkat = [1=>'Jan',2=>'Feb',3=>'Mar',4=>'Apr',5=>'Mei',6=>'Jun',
                 7=>'Jul',8=>'Agu',9=>'Sep',10=>'Okt',11=>'Nov',12=>'Des'];

$exportAll = false;
$rangeSql = '';

if ($dari === '' && $sampai === '') {
    $exportAll = true;
} else {
    if (!preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $dari) || !preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $sampai)) {
        $_SESSION['flash_error'] = 'Parameter rentang tidak valid.';
        $_SESSION['flash_type'] = 'danger';
        header('Location: daftar_periode'); exit;
    }
    $dP = explode('-', $dari); $bulanAwal = (int)$dP[1]; $tahunAwal = (int)$dP[0];
    $sP = explode('-', $sampai); $bulanAkhir = (int)$sP[1]; $tahunAkhir = (int)$sP[0];

    if ($tahunAkhir < $tahunAwal || ($tahunAkhir === $tahunAwal && $bulanAkhir < $bulanAwal)) {
        $_SESSION['flash_error'] = 'Periode Akhir tidak boleh sebelum Periode Awal.';
        $_SESSION['flash_type'] = 'danger';
        header('Location: daftar_periode'); exit;
    }

    $rangeSql = " AND (dp.tahun > ? OR (dp.tahun = ? AND dp.bulan >= ?))
                   AND (dp.tahun < ? OR (dp.tahun = ? AND dp.bulan <= ?))";

    // Generate daftar bulan yang diekspektasi dalam rentang
    $expectedMonths = [];
    $cursor = $tahunAwal * 12 + $bulanAwal;
    $end = $tahunAkhir * 12 + $bulanAkhir;
    while ($cursor <= $end) {
        $t = (int)floor(($cursor - 1) / 12);
        $b = ($cursor - 1) % 12 + 1;
        $expectedMonths[] = ['tahun' => $t, 'bulan' => $b, 'label' => $bulanNama[$b] . ' ' . $t];
        $cursor++;
    }

    // Query periode broadcast milik stakeholder ini dalam rentang
    $stmt = $pdo->prepare("SELECT tahun, bulan FROM dana_periode dp
        WHERE dp.pemangku_kepentingan_id = ? AND dp.status = 'broadcast' $rangeSql
        ORDER BY dp.tahun, dp.bulan");
    $stmt->execute([$pkId, $tahunAwal, $tahunAwal, $bulanAwal, $tahunAkhir, $tahunAkhir, $bulanAkhir]);
    $existingMonths = [];
    foreach ($stmt as $row) {
        $key = $row['tahun'] . '-' . str_pad((string)$row['bulan'], 2, '0', STR_PAD_LEFT);
        $existingMonths[$key] = true;
    }

    // Cari bulan yang hilang
    $missing = [];
    foreach ($expectedMonths as $em) {
        $key = $em['tahun'] . '-' . str_pad((string)$em['bulan'], 2, '0', STR_PAD_LEFT);
        if (!isset($existingMonths[$key])) {
            $missing[] = $em['label'];
        }
    }

    if (!empty($missing)) {
        if (count($missing) === 1) {
            $msg = 'Export gagal: data periode ' . $missing[0] . ' belum tersedia/belum broadcast. Lengkapi data periode tersebut terlebih dahulu atau sesuaikan rentang laporan.';
        } else {
            $msg = 'Export gagal: data periode ' . implode(', ', $missing) . ' belum tersedia/belum broadcast. Lengkapi data periode tersebut terlebih dahulu atau sesuaikan rentang laporan.';
        }
        $_SESSION['flash_error'] = $msg;
        $_SESSION['flash_type'] = 'danger';
        header('Location: daftar_periode'); exit;
    }
}

// ── Ambil data periode ─────────────────────────────────────────────────────────
$list = $pdo->prepare("
    SELECT dp.*,
           COALESCE(SUM(dm.nominal), 0) AS total_pemanfaatan
    FROM dana_periode dp
    LEFT JOIN dana_pemanfaatan dm ON dm.periode_id = dp.id
    WHERE dp.pemangku_kepentingan_id = ? $rangeSql
    GROUP BY dp.id
    ORDER BY dp.tahun, dp.bulan
");
$listParams = [$pkId];
if (!$exportAll) {
    $listParams = array_merge([$pkId], [$tahunAwal, $tahunAwal, $bulanAwal, $tahunAkhir, $tahunAkhir, $bulanAkhir]);
}
$list->execute($listParams);
$rows = $list->fetchAll();

// ── Ambil rincian pemanfaatan ───────────────────────────────────────────────────
$rincianList = $pdo->prepare("
    SELECT dp.bulan, dp.tahun, dm.keterangan, dm.nominal
    FROM dana_pemanfaatan dm
    JOIN dana_periode dp ON dp.id = dm.periode_id
    WHERE dp.pemangku_kepentingan_id = ? $rangeSql
    ORDER BY dp.tahun, dp.bulan, dm.id
");
$rincianList->execute($listParams);
$rincianRows = $rincianList->fetchAll();

// ── Build data rows ────────────────────────────────────────────────────────────
if ($exportAll) {
    $labelA = 'ALL';
    $labelB = 'ALL';
    $periodeLabel = 'LAPORAN_DANA_ALL';
} else {
    $labelA = str_pad((string)$bulanAwal, 2, '0', STR_PAD_LEFT) . '_' . $tahunAwal;
    $labelB = str_pad((string)$bulanAkhir, 2, '0', STR_PAD_LEFT) . '_' . $tahunAkhir;
    $periodeLabel = 'LAPORAN_DANA_' . $labelA . ($labelA !== $labelB ? '-' . $labelB : '');
}

$headerRow = ['No', 'Periode', 'Saldo Awal', 'Pemasukan', 'Pemanfaatan', 'Sisa Saldo', 'Status'];
$dataRows = [];
$no = 1;
$totSaldoAwal = 0;
$totPemasukan = 0;
$totPemanfaatan = 0;
$totSisa = 0;
foreach ($rows as $r) {
    $saldoAwal = (float)($r['saldo_awal'] ?? 0);
    $pemasukan = (float)$r['total_pemasukan'];
    $pemanfaatan = (float)$r['total_pemanfaatan'];
    $sisa = $saldoAwal + $pemasukan - $pemanfaatan;
    $periodeTeks = $bulanNama[(int)$r['bulan']] . ' ' . $r['tahun'];
    $dataRows[] = [$no++, $periodeTeks, (int)$saldoAwal, (int)$pemasukan, (int)$pemanfaatan, (int)$sisa, ucfirst(str_replace('_', ' ', $r['status']))];
    $totSaldoAwal += $saldoAwal;
    $totPemasukan += $pemasukan;
    $totPemanfaatan += $pemanfaatan;
    $totSisa += $sisa;
}
$totalRow = ['', 'TOTAL', (int)$totSaldoAwal, (int)$totPemasukan, (int)$totPemanfaatan, (int)$totSisa, ''];

// ── OOXML Export ──────────────────────────────────────────────────────────────
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

function colL(int $n): string {
    $r = '';
    for ($n++; $n > 0; $n = intdiv($n-1, 26))
        $r = chr(65 + ($n-1) % 26) . $r;
    return $r;
}

define('S_HDR', 1);
define('S_TTL', 2);
define('S_BLD', 3);
define('S_NUM', 4);
define('S_EVN', 8);
define('S_TOT', 9);

function buildSheet(array $hdr, array $data, array $tot, string $label): string {
    $nCols = count($hdr);
    $x = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' . "\n";
    $x .= '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"'
        . ' xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">';

    $x .= '<cols>'
        . '<col min="1" max="1" width="6"  customWidth="1"/>'
        . '<col min="2" max="2" width="22" customWidth="1"/>'
        . '<col min="3" max="7" width="18" customWidth="1"/>'
        . '</cols>';

    $x .= '<sheetData>';

    $x .= '<row r="1" ht="28" customHeight="1">'
        . '<c r="A1" s="' . S_TTL . '" t="s"><v>' . addSS('LAPORAN DANA — ' . $label) . '</v></c>'
        . '</row>';

    $x .= '<row r="2" ht="22" customHeight="1">';
    foreach ($hdr as $ci => $h)
        $x .= '<c r="' . colL($ci) . '2" s="' . S_HDR . '" t="s"><v>' . addSS((string)$h) . '</v></c>';
    $x .= '</row>';

    $rn = 3;
    $nRow = count($hdr);
    foreach ($data as $row) {
        $even = ($rn % 2 === 0);
        $x .= '<row r="' . $rn . '">';
        foreach ($row as $ci => $val) {
            $addr = colL($ci) . $rn;
            if ($ci === 0) {
                $x .= '<c r="' . $addr . '" s="' . S_BLD . '"><v>' . (int)$val . '</v></c>';
            } elseif ($ci === 1 || $ci === $nRow-1) {
                $x .= '<c r="' . $addr . '" t="s"><v>' . addSS((string)$val) . '</v></c>';
            } else {
                $s = $even ? S_EVN : S_NUM;
                $x .= '<c r="' . $addr . '" s="' . $s . '"><v>' . (int)$val . '</v></c>';
            }
        }
        $x .= '</row>';
        $rn++;
    }

    $x .= '<row r="' . $rn . '" ht="20" customHeight="1">';
    foreach ($tot as $ci => $val) {
        $addr = colL($ci) . $rn;
        if ($ci <= 1 || $ci === $nRow-1) {
            $x .= '<c r="' . $addr . '" s="' . S_BLD . '" t="s"><v>' . addSS((string)$val) . '</v></c>';
        } else {
            $x .= '<c r="' . $addr . '" s="' . S_TOT . '"><v>' . (int)$val . '</v></c>';
        }
    }
    $x .= '</row>';

    $x .= '</sheetData>';
    $x .= '<mergeCells count="1"><mergeCell ref="A1:' . colL($nCols-1) . '1"/></mergeCells>';
    $x .= '<pageSetup orientation="landscape" fitToPage="1" fitToWidth="1" fitToHeight="0"/>';
    $x .= '</worksheet>';
    return $x;
}

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

$sheetXml = buildSheet($headerRow, $dataRows, $totalRow, $periodeLabel);

// ── Sheet 2: Rincian Pemanfaatan ──────────────────────────────────────────────
function buildSheetRincian(array $hdr, array $grouped, array $bulanNama): string {
    $nCols = count($hdr);
    $x = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' . "\n";
    $x .= '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"'
        . ' xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">';
    $x .= '<cols><col min="1" max="1" width="22" customWidth="1"/><col min="2" max="2" width="40" customWidth="1"/><col min="3" max="3" width="18" customWidth="1"/></cols>';
    $x .= '<sheetData>';
    $x .= '<row r="1" ht="28" customHeight="1"><c r="A1" s="'.S_TTL.'" t="s"><v>'.addSS('RINCIAN PEMANFAATAN DANA').'</v></c></row>';
    $x .= '<row r="2" ht="22" customHeight="1">';
    foreach ($hdr as $ci => $h) $x .= '<c r="'.colL($ci).'2" s="'.S_HDR.'" t="s"><v>'.addSS((string)$h).'</v></c>';
    $x .= '</row>';
    $rn = 3;
    foreach ($grouped as $group) {
        $first = $group[0];
        $periodeLabel2 = $bulanNama[(int)$first['bulan']] . ' ' . $first['tahun'];
        $groupSubtotal = 0;
        foreach ($group as $r) {
            $even = ($rn % 2 === 0);
            $x .= '<row r="'.$rn.'">';
            $x .= '<c r="A'.$rn.'" t="s"><v>'.addSS($periodeLabel2).'</v></c>';
            $x .= '<c r="B'.$rn.'" t="s"><v>'.addSS($r['keterangan']).'</v></c>';
            $nom = (int)$r['nominal'];
            $x .= '<c r="C'.$rn.'" s="'.($even?S_EVN:S_NUM).'"><v>'.$nom.'</v></c>';
            $x .= '</row>';
            $groupSubtotal += $nom;
            $rn++;
        }
        $x .= '<row r="'.$rn.'" ht="20" customHeight="1">';
        $x .= '<c r="A'.$rn.'" s="'.S_BLD.'" t="s"><v>'.addSS('Subtotal '.$periodeLabel2).'</v></c>';
        $x .= '<c r="B'.$rn.'" s="'.S_BLD.'"></c>';
        $x .= '<c r="C'.$rn.'" s="'.S_TOT.'"><v>'.$groupSubtotal.'</v></c>';
        $x .= '</row>';
        $rn++;
    }
    $x .= '</sheetData>';
    $x .= '<mergeCells count="1"><mergeCell ref="A1:C1"/></mergeCells>';
    $x .= '</worksheet>';
    return $x;
}

$groupedRincian = [];
foreach ($rincianRows as $r) {
    $key = $r['tahun'] . '-' . str_pad((string)$r['bulan'], 2, '0', STR_PAD_LEFT);
    $groupedRincian[$key][] = $r;
}
$headerRow2 = ['Periode', 'Keterangan', 'Nominal (Rp)'];
$sheet2Xml = buildSheetRincian($headerRow2, $groupedRincian, $bulanNama);

$cnt = count($sharedStrings);
$ssXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
       . '<sst xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" count="'.$cnt.'" uniqueCount="'.$cnt.'">';
foreach ($sharedStrings as $s)
    $ssXml .= '<si><t xml:space="preserve">' . htmlspecialchars($s, ENT_XML1, 'UTF-8') . '</t></si>';
$ssXml .= '</sst>';

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
 <Override PartName="/xl/worksheets/sheet2.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>
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

$sheet1Name = htmlspecialchars($periodeLabel, ENT_XML1);
$zip->addFromString('xl/workbook.xml',
'<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"
          xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">
 <sheets>
  <sheet name="Ringkasan" sheetId="1" r:id="rId1"/>
  <sheet name="Rincian Pemanfaatan" sheetId="2" r:id="rId4"/>
 </sheets>
</workbook>');

$zip->addFromString('xl/_rels/workbook.xml.rels',
'<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
 <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet"     Target="worksheets/sheet1.xml"/>
 <Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/sharedStrings" Target="sharedStrings.xml"/>
 <Relationship Id="rId3" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles"        Target="styles.xml"/>
 <Relationship Id="rId4" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet"     Target="worksheets/sheet2.xml"/>
</Relationships>');

$zip->addFromString('xl/worksheets/sheet1.xml', $sheetXml);
$zip->addFromString('xl/worksheets/sheet2.xml', $sheet2Xml);
$zip->addFromString('xl/sharedStrings.xml',     $ssXml);
$zip->addFromString('xl/styles.xml',            buildStyles());
$zip->close();

if ($exportAll) {
    $filename = 'laporan_dana_all.xlsx';
} else {
    $filename = 'laporan_dana_' . $labelA . ($labelA !== $labelB ? '-' . $labelB : '') . '.xlsx';
}
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Content-Length: ' . filesize($tmp));
header('Cache-Control: max-age=0');
readfile($tmp);
unlink($tmp);
