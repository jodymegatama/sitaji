<?php
// includes/mark_broadcast_read.php
// AJAX endpoint: mark broadcast notifications as read for the current user.
// Only inserts records for the exact periode_ids sent — never marks all.
// Accepts: POST with periode_ids[] array and CSRF token.
// Returns: JSON { ok: true } or { ok: false, error: "..." }

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Method not allowed']);
    exit;
}

csrf_check();

$cu = current_user();
if (!$cu) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'Not authenticated']);
    exit;
}

$userId = (int)$cu['id'];
$periodeIds = $_POST['periode_ids'] ?? [];

// Validate: must be array of positive integers
$cleanIds = [];
foreach ($periodeIds as $id) {
    $intId = (int)$id;
    if ($intId > 0) {
        $cleanIds[] = $intId;
    }
}

if (empty($cleanIds)) {
    echo json_encode(['ok' => true]);
    exit;
}

// Verify each periode_id actually exists and is broadcast/revisi_pending
// before inserting — prevents marking non-existent or irrelevant periods.
$placeholders = implode(',', array_fill(0, count($cleanIds), '?'));
$validStmt = $pdo->prepare("
    SELECT id FROM dana_periode
    WHERE id IN ($placeholders) AND status IN ('broadcast', 'revisi_pending')
");
$validStmt->execute($cleanIds);
$validIds = array_column($validStmt->fetchAll(), 'id');
$validIds = array_map('intval', $validIds);

if (empty($validIds)) {
    echo json_encode(['ok' => true]);
    exit;
}

// Insert only verified IDs with ON DUPLICATE KEY UPDATE (idempotent)
$insertParams = [];
foreach ($validIds as $vid) {
    $insertParams[] = $vid;
    $insertParams[] = $userId;
}
$placeholders2 = [];
foreach ($validIds as $vid) {
    $placeholders2[] = "(?, ?, NOW())";
}
$placeholders2Str = implode(', ', $placeholders2);

try {
    $sql = "INSERT INTO dana_notifikasi_dibaca (periode_id, user_id, dibaca_at)
            VALUES $placeholders2Str
            ON DUPLICATE KEY UPDATE dibaca_at = NOW()";
    $insertStmt = $pdo->prepare($sql);
    $insertStmt->execute($insertParams);
    echo json_encode(['ok' => true]);
} catch (PDOException $e) {
    error_log("mark_broadcast_read error: " . $e->getMessage());
    echo json_encode(['ok' => false, 'error' => 'Database error']);
}
