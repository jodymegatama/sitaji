<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers.php';
require_role('admin');
$activeNav = $activeNav ?? '';
$pageTitle = $pageTitle ?? 'SITAJI Admin';
?>
<!doctype html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($pageTitle) ?> | SITAJI</title>
<link rel="icon" type="image/x-icon" href="<?= base_url('assets/img/favicon.ico') ?>">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.8/css/dataTables.bootstrap5.min.css">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=JetBrains+Mono:wght@400;500;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= base_url('assets/css/style.css?v=6') ?>">
</head>
<body class="bg-app">

<nav class="navbar navbar-expand-xl sticky-top app-navbar">
  <div class="container-fluid px-4">
    <a class="navbar-brand d-flex align-items-center gap-2" href="<?= base_url('admin/dashboard') ?>">
      <img src="<?= base_url('assets/img/logo-sitaji.svg') ?>" alt="SITAJI" height="50" style="display:block">
      <span class="fw-bold" style="font-size:.85rem;color:var(--primary);text-transform:uppercase;letter-spacing:.05em">Admin</span>
    </a>
    <button class="navbar-toggler" data-bs-toggle="collapse" data-bs-target="#nav">
      <span class="navbar-toggler-icon"></span>
    </button>
    <div class="collapse navbar-collapse" id="nav">
      <ul class="navbar-nav mx-auto gap-2">
        <li class="nav-item">
          <a class="nav-pill <?= $activeNav==='payroll'?'active':'' ?>" href="<?= base_url('admin/dashboard') ?>"><i class="bi bi-cash-stack me-1"></i>Data Payroll</a>
        </li>
        <li class="nav-item">
          <a class="nav-pill <?= $activeNav==='payroll_komponen'?'active':'' ?>" href="<?= base_url('admin/manajemen_komponen_payroll') ?>"><i class="bi bi-wallet2 me-1"></i>Komponen Payroll</a>
        </li>
        <li class="nav-item">
          <a class="nav-pill <?= $activeNav==='pegawai'?'active':'' ?>" href="<?= base_url('admin/pegawai') ?>"><i class="bi bi-people me-1"></i>Pegawai</a>
        </li>
        <li class="nav-item">
          <a class="nav-pill <?= $activeNav==='pemangku'?'active':'' ?>" href="<?= base_url('admin/pemangku_kepentingan') ?>"><i class="bi bi-building me-1"></i>Pemangku Kepentingan</a>
        </li>
      </ul>
      <div class="d-flex align-items-center gap-3">
        <span class="text-muted small d-none d-md-inline"><?= date('l, d M Y') ?></span>
        <a href="<?= base_url('logout') ?>" class="btn-danger-modern"><i class="bi bi-box-arrow-right"></i> Keluar</a>
      </div>
    </div>
  </div>
</nav>

<main class="container-fluid px-4 py-4">
