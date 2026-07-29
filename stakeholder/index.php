<?php
require_once __DIR__ . '/../includes/auth.php';
require_login();
$u = current_user();
if ($u['role'] === 'stakeholder') {
    header('Location: ' . base_url('stakeholder/dashboard'));
} else {
    header('Location: ' . base_url('login'));
}
exit;
