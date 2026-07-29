<?php
// includes/helpers.php
declare(strict_types=1);

function rupiah($n): string {
    return 'Rp ' . number_format((float)$n, 0, ',', '.');
}

function e(?string $s): string {
    return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}

function periode_label(string $date): string {
    // 2026-06-01 -> "June 2026"
    $t = strtotime($date);
    return $t ? date('F Y', $t) : $date;
}

/** Daftar field pendapatan & potongan dari database */
function payroll_fields(): array {
    static $cache = null;
    if ($cache !== null) return $cache;

    global $pdo;
    $rows = $pdo->query("SELECT field_key, nama, tipe, kategori FROM komponen_payroll WHERE aktif=1 ORDER BY urutan ASC, id ASC")->fetchAll();

    $result = [
        'pendapatan'    => [],
        'potongan_umum' => [],
        'koperasi'      => [],
    ];

    foreach ($rows as $r) {
        if ($r['tipe'] === 'pendapatan') {
            $result['pendapatan'][$r['field_key']] = $r['nama'];
        } elseif ($r['tipe'] === 'potongan' && $r['kategori'] === 'Potongan Umum') {
            $result['potongan_umum'][$r['field_key']] = $r['nama'];
        } elseif ($r['tipe'] === 'potongan' && $r['kategori'] === 'Koperasi atau Bank & Pinjaman') {
            $result['koperasi'][$r['field_key']] = $r['nama'];
        }
    }

    $cache = $result;
    return $result;
}

function total_bruto(array $row): float {
    return (float)($row['_detail_sum_pendapatan'] ?? 0);
}

function total_potongan(array $row): float {
    return (float)($row['_detail_sum_potongan'] ?? 0);
}

function take_home(array $row): float {
    return total_bruto($row) - total_potongan($row);
}

/** Ambil map nilai komponen untuk satu payroll_id: [field_key => nilai] */
function payroll_values_map(int $payroll_id): array {
    global $pdo;
    $stmt = $pdo->prepare("
        SELECT k.field_key, pd.nilai
        FROM payroll_detail pd
        JOIN komponen_payroll k ON k.id = pd.komponen_id
        WHERE pd.payroll_id = ?
    ");
    $stmt->execute([$payroll_id]);
    return $stmt->fetchAll(PDO::FETCH_KEY_PAIR) ?: [];
}

/** Ambil detail payroll untuk satu payroll_id, return array [field_key => nilai] dan dua key virtual */
function payroll_detail_rows(int $payroll_id): array {
    global $pdo;
    $rows = $pdo->prepare("
        SELECT k.field_key, k.nama, k.tipe, k.kategori, pd.nilai, pd.komponen_id
        FROM payroll_detail pd
        JOIN komponen_payroll k ON k.id = pd.komponen_id
        WHERE pd.payroll_id = ?
    ");
    $rows->execute([$payroll_id]);
    $result = $rows->fetchAll();
    return $result;
}
