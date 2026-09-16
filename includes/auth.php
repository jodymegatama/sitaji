<?php
// includes/auth.php
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

function current_user(): ?array {
    return $_SESSION['user'] ?? null;
}

function require_login(): void {
    if (!current_user()) {
        header('Location: ' . base_url('login'));
        exit;
    }
}

function require_role(string|array $role): void {
    require_login();
    $u = current_user();
    $allowed = is_array($role) ? in_array($u['role'], $role, true) : $u['role'] === $role;
    if (!$allowed) {
        http_response_code(403);
        die('Akses ditolak. Anda tidak memiliki hak akses ke halaman ini.');
    }
}

function base_url(string $path = ''): string {
    $name = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '/');
    $root = str_replace('\\', '/', $_SERVER['DOCUMENT_ROOT'] ?? '');

    // aaPanel Nginx VPS auto-detection: DOCUMENT_ROOT starts with /www/wwwroot/
    if (str_starts_with($root, '/www/wwwroot/')) {
        $dir = rtrim(dirname($name), '/');
        if (preg_match('#/(admin|pegawai|stakeholder)$#', $dir)) {
            $dir = preg_replace('#/(admin|pegawai|stakeholder)$#', '', $dir);
        }
        return $dir . '/' . ltrim($path, '/');
    }

    // Default (Laragon Apache local):
    if (preg_match('#^[A-Za-z]:/#', $name)) {
        if ($root !== '' && str_starts_with($name, $root)) {
            $name = substr($name, strlen($root));
        } else {
            $name = '/';
        }
    }
    $dir = rtrim(dirname($name), '/');
    if (preg_match('#/(admin|pegawai|stakeholder)$#', $dir)) {
        $dir = preg_replace('#/(admin|pegawai|stakeholder)$#', '', $dir);
    }
    return $dir . '/' . ltrim($path, '/');
}

function csrf_token(): string {
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(16));
    }
    return $_SESSION['csrf'];
}

function csrf_check(): void {
    $token = $_POST['csrf'] ?? '';
    if (empty($token) || !hash_equals($_SESSION['csrf'] ?? '', $token)) {
        http_response_code(419);
        die('CSRF token tidak valid. Silakan refresh halaman.');
    }
}
