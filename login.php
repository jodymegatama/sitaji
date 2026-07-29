<?php
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/helpers.php';

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF Protection
    $token = $_POST['csrf'] ?? '';
    if (empty($token) || !hash_equals($_SESSION['csrf'] ?? '', $token)) {
        $error = 'Sesi keamanan kadaluwarsa. Silakan refresh halaman dan coba lagi.';
    } else {
        // Rate limit: max 5 percobaan gagal dalam 5 menit
        $attempts = (int)($_SESSION['login_attempts'] ?? 0);
        $lastAttempt = (int)($_SESSION['login_last_attempt'] ?? 0);

        if ($attempts >= 5 && (time() - $lastAttempt) < 300) {
            $remaining = ceil((300 - (time() - $lastAttempt)) / 60);
            $error = 'Terlalu banyak percobaan, coba lagi dalam ' . $remaining . ' menit.';
        } else {
            $username = trim($_POST['username'] ?? '');
            $password = (string)($_POST['password'] ?? '');
            if ($username === '' || $password === '') {
                $error = 'Username dan password wajib diisi.';
            } else {
                $stmt = $pdo->prepare('SELECT * FROM users WHERE username = ? LIMIT 1');
                $stmt->execute([$username]);
                $user = $stmt->fetch();
                if ($user && password_verify($password, $user['password_hash'])) {
                    $_SESSION['login_attempts'] = 0;
                    session_regenerate_id(true);
                    $_SESSION['csrf'] = bin2hex(random_bytes(16));
                    $_SESSION['user'] = [
                        'id'         => (int)$user['id'],
                        'username'   => $user['username'],
                        'role'       => $user['role'],
                        'pegawai_id' => $user['pegawai_id'] ? (int)$user['pegawai_id'] : null,
                    ];
                    $redirectMap = [
                        'admin'      => 'admin/dashboard',
                        'pegawai'    => 'pegawai/dashboard',
                        'stakeholder'=> 'stakeholder/dashboard',
                    ];
                    header('Location: ' . base_url($redirectMap[$user['role']] ?? 'login'));
                    exit;
                }
                $_SESSION['login_attempts'] = $attempts + 1;
                $_SESSION['login_last_attempt'] = time();
                $error = 'Kredensial salah. Periksa kembali Username/NIP & Password Anda.';
            }
        }
    }
}
?>
<!doctype html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Login | SITAJI</title>
<link rel="icon" type="image/x-icon" href="assets/img/favicon.ico">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=JetBrains+Mono:wght@400;500;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="assets/css/style.css?v=5">
</head>
<body>
<div class="login-wrap">
  <div class="login-card">
    <div class="text-center mb-4">
      <div class="login-logo mb-3"><img src="assets/img/logo-sitaji.svg" alt="SITAJI" height="165" style="display:block"></div>
      <!-- <h3 class="fw-bold mb-1">SITAJI</h3> -->
      <p class="text-muted small mb-0"><strong>SITAJI</strong> — Sistem Transparansi Gaji<br><span class="text-muted" style="font-size:.85rem">Kantor Kementerian Agama Kabupaten Pasuruan</span></p>
    </div>

    <?php if ($error): ?>
      <div class="alert alert-danger small"><?= e($error) ?></div>
    <?php endif; ?>

    <form method="post" data-validate novalidate>
      <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
      <div class="mb-3">
        <label for="loginUsername" class="form-label fw-semibold">Username</label>
        <div class="input-group">
          <input type="text" id="loginUsername" name="username" class="form-control form-control-lg" placeholder="ID Pegawai / NIP" required>
          <span class="input-group-text bg-white"><i class="bi bi-person"></i></span>
        </div>
      </div>
      <div class="mb-4">
        <label for="loginPassword" class="form-label fw-semibold">Password</label>
        <div class="input-group">
          <input type="password" id="loginPassword" name="password" class="form-control form-control-lg" placeholder="••••••••" required>
          <button type="button" class="input-group-text bg-white" data-toggle-pwd="#loginPassword"><i class="bi bi-eye-slash"></i></button>
        </div>
      </div>
      <button class="btn btn-brand btn-lg btn-pill">Masuk ke Sistem <i class="bi bi-arrow-right ms-1"></i></button>
    </form>

    <p class="text-center text-muted small mt-4 mb-0">
      &copy; <?= date('Y') ?> <strong class="text-primary">SITAJI</strong> - Tim Prakom Kankemenag Kab Pasuruan .All Rights Reserved
    </p>
  </div>
</div>
<script src="assets/js/app.js"></script>
</body>
</html>
