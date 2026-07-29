<?php
require_once __DIR__ . '/includes/auth.php';
$u = current_user();
if (!$u) { header('Location: ' . base_url('login')); exit; }
$redirectMap = [
    'admin'      => 'admin/dashboard',
    'pegawai'    => 'pegawai/dashboard',
    'stakeholder'=> 'stakeholder/daftar_periode',
];
header('Location: ' . base_url($redirectMap[$u['role']] ?? 'login'));
