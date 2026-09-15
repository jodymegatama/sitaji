<?php
// includes/db.php — koneksi PDO MySQL (auto local + VPS, no .env needed)
declare(strict_types=1);

error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');

// Deteksi environment berdasarkan OS
$is_local = (PHP_OS_FAMILY === 'Windows');

if ($is_local) {
    // Laragon local: MySQL user root tanpa password
    $db_host = 'localhost';
    $db_name = 'sitaji';
    $db_user = 'root';
    $db_pass = '';
} else {
    // VPS aaPanel (Linux): baca dari .env jika ada, fallback ke konstanta
    $env_file = __DIR__ . '/../.env';
    if (is_file($env_file) && is_readable($env_file)) {
        $lines = file($env_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        $env = [];
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || $line[0] === '#') continue;
            if (!str_contains($line, '=')) continue;
            [$k, $v] = explode('=', $line, 2);
            $env[trim($k)] = trim($v);
        }
        $db_host = $env['DB_HOST'] ?? 'localhost';
        $db_name = $env['DB_NAME'] ?? 'sitaji';
        $db_user = $env['DB_USER'] ?? 'sitaji';
        $db_pass = $env['DB_PASS'] ?? '';
    } else {
        // Fallback jika .env tidak ada
        $db_host = 'localhost';
        $db_name = 'sitaji';
        $db_user = 'sitaji';
        $db_pass = '';
    }
}

try {
    $dsn = 'mysql:host=' . $db_host . ';dbname=' . $db_name . ';charset=utf8mb4';
    $pdo = new PDO($dsn, $db_user, $db_pass, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ]);
} catch (PDOException $e) {
    http_response_code(500);
    error_log('DB connection failed: ' . $e->getMessage());
    die('Koneksi database gagal. Silakan hubungi administrator sistem.');
}
