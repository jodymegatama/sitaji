<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/helpers.php';
require_role('admin');

$query = $_SERVER['QUERY_STRING'] ?? '';
$redirect = 'pemangku_kepentingan?tab=approval';
if ($query !== '') {
    $redirect .= '&' . $query;
}
header('Location: ' . $redirect);
exit;
