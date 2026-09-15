<?php
// includes/db.php — koneksi PDO MySQL (auto local + VPS)
declare(strict_types=1);

error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');

// Deteksi environment: Windows (Laragon local) vs Linux (VPS)
$is_local = (PHP_OS_FAMILY === 'Windows') || str_contains(__DIR__, 'laragon') || str_contains($_SERVER['DOCUMENT_ROOT'] ?? '', 'laragon');

if ($is_local) {
    // Laragon local: MySQL user root tanpa password (default)
    define('DB_HOST', 'localhost');
    define('DB_NAME', 'sitaji');
    define('DB_USER', 'root');
    define('DB_PASS', '');
} else {
    // VPS aaPanel
    define('DB_HOST', 'localhost');
    define('DB_NAME', 'sitaji');
    define('DB_USER', 'sitaji');
    define('DB_PASS', 'c364DCY8aW3HTrRe');
}
const DB_CHARSET = 'utf8mb4';

try {
    $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET;
    $pdo = new PDO($dsn, DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ]);
} catch (PDOException $e) {
    http_response_code(500);
    die('Koneksi database gagal: ' . htmlspecialchars($e->getMessage()) . ' (host=' . DB_HOST . ' db=' . DB_NAME . ' user=' . DB_USER . ')');
}
