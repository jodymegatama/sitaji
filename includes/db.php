<?php
// includes/db.php — koneksi PDO MySQL (production-safe, no hardcoded credentials)
// Baca konfigurasi dari environment variable atau file .env (di luar repo)
declare(strict_types=1);

error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');

/**
 * Load .env file (di luar web root, tidak di-commit ke git).
 * Format: KEY=value per baris.
 * Path优先级:
 *   1. /www/wwwroot/sitaji/.env        (VPS aaPanel, document root)
 *   2. C:/laragon/www/sitaji/.env      (local Laragon)
 *   3. __DIR__/.env                    (fallback: same dir as db.php)
 */
function load_env(): void {
    $candidates = [
        '/www/wwwroot/sitaji.kemenagkabpasuruan.id/.env',  // VPS aaPanel
        'C:/laragon/www/sitaji/.env',                       // Local Laragon
        __DIR__ . '/../.env',                               // Relative fallback
    ];
    foreach ($candidates as $path) {
        if (is_file($path) && is_readable($path)) {
            $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            foreach ($lines as $line) {
                $line = trim($line);
                if ($line === '' || str_starts_with($line, '#')) continue;
                if (!str_contains($line, '=')) continue;
                [$key, $val] = explode('=', $line, 2);
                $key = trim($key);
                $val = trim($val);
                // Remove surrounding quotes
                if ((str_starts_with($val, '"') && str_ends_with($val, '"')) ||
                    (str_starts_with($val, "'") && str_ends_with($val, "'"))) {
                    $val = substr($val, 1, -1);
                }
                if (!getenv($key)) {
                    putenv("$key=$val");
                    $_ENV[$key] = $val;
                }
            }
            break;
        }
    }
}
load_env();

// Baca konfigurasi dari environment (diatur via .env file)
$env = getenv('APP_ENV') ?: 'local';  // 'local' | 'production'

if ($env === 'local' || $env === '' || PHP_OS_FAMILY === 'Windows') {
    // Local Laragon — default root tanpa password
    $db_host = getenv('DB_HOST') ?: 'localhost';
    $db_name = getenv('DB_NAME') ?: 'sitaji';
    $db_user = getenv('DB_USER') ?: 'root';
    $db_pass = getenv('DB_PASS') ?: '';
} else {
    // Production VPS — wajib ada di .env
    $db_host = getenv('DB_HOST') ?: 'localhost';
    $db_name = getenv('DB_NAME') ?: 'sitaji';
    $db_user = getenv('DB_USER') ?: '';
    $db_pass = getenv('DB_PASS') ?: '';
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
