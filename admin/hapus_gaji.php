<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';
require_role('admin');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    die('Metode request tidak diizinkan.');
}
csrf_check();

$id = (int)($_POST['id'] ?? 0);
if ($id <= 0) {
    die('ID tidak valid.');
}

$pdo->prepare("DELETE FROM payroll WHERE id = ?")->execute([$id]);
header('Location: dashboard');
