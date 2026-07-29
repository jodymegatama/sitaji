<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';

header('Content-Type: application/json');

if (!current_user()) {
    echo json_encode(['draw' => 0, 'recordsTotal' => 0, 'recordsFiltered' => 0, 'data' => [], 'error' => 'Unauthenticated']);
    exit;
}

$draw   = (int)($_GET['draw'] ?? 1);
$start  = (int)($_GET['start'] ?? 0);
$length = (int)($_GET['length'] ?? 10);
$search = trim($_GET['search']['value'] ?? '');
$orderCol = (int)($_GET['order'][0]['column'] ?? 0);
$orderDir = strtoupper($_GET['order'][0]['dir'] ?? 'ASC') === 'DESC' ? 'DESC' : 'ASC';

$columns = ['nip', 'nama', 'golongan', 'jabatan'];
$orderBy = isset($columns[$orderCol]) ? $columns[$orderCol] : 'nama';

// Count total
$total = (int)$pdo->query("SELECT COUNT(*) FROM pegawai")->fetchColumn();

// Build filter
$where = '';
$params = [];
if ($search !== '') {
    $where = "WHERE nip LIKE :q OR nama LIKE :q2 OR golongan LIKE :q3 OR jabatan LIKE :q4";
    $params = ['q' => "%$search%", 'q2' => "%$search%", 'q3' => "%$search%", 'q4' => "%$search%"];
}

// Count filtered
$filtered = $total;
if ($where) {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM pegawai $where");
    $stmt->execute($params);
    $filtered = (int)$stmt->fetchColumn();
}

// Fetch page
$sql = "SELECT * FROM pegawai $where ORDER BY $orderBy $orderDir LIMIT $start, $length";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll();

$data = [];
foreach ($rows as $r) {
    $csrf = csrf_token();
    $aksi = '<a class="btn-action-modern" href="edit_pegawai?id='.$r['id'].'" title="Edit"><i class="bi bi-pencil"></i></a>';
    $aksi .= '<form method="post" action="hapus_pegawai" style="display:inline" onsubmit="return confirm(\'Hapus pegawai ini?\')">';
    $aksi .= '<input type="hidden" name="csrf" value="'.$csrf.'">';
    $aksi .= '<input type="hidden" name="id" value="'.$r['id'].'">';
    $aksi .= '<button type="submit" class="btn-action-modern text-danger" title="Hapus"><i class="bi bi-trash"></i></button></form>';

    $data[] = [
        '<i class="bi bi-person-circle me-2 text-primary"></i>' . e($r['nip']),
        '<div class="fw-semibold">' . e($r['nama']) . '</div><span class="text-success small">● Status: ' . ucfirst($r['status']) . '</span>',
        '<span class="badge-gol">' . e($r['golongan']) . '</span>',
        e($r['jabatan']),
        '<div class="text-end">' . $aksi . '</div>',
    ];
}

echo json_encode([
    'draw' => $draw,
    'recordsTotal' => $total,
    'recordsFiltered' => $filtered,
    'data' => $data,
]);
