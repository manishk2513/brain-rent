<?php
// =============================================
// config/db.php  —  MySQL Connection (PDO)
// =============================================

// Optional local override (ignored by git): config/db.local.php
// Example:
//   <?php
//   define('DB_PASSWORD', 'your_password');
//   define('DB_USER', 'root');
//
if (file_exists(__DIR__ . '/db.local.php')) {
    require __DIR__ . '/db.local.php';
}

defined('DB_SERVER') || define('DB_SERVER', 'localhost');       // MySQL host
defined('DB_NAME') || define('DB_NAME', 'brain_rent');
defined('DB_USER') || define('DB_USER', 'root');            // MySQL user (change if needed)
defined('DB_PASSWORD') || define('DB_PASSWORD', '1234');                // MySQL password (change if needed)
defined('DB_PORT') || define('DB_PORT', 3306);

define('PLATFORM_FEE_PERCENT', 15);       // 15% commission

if (!defined('APP_URL')) {
    // Auto-detect base URL so the project works under:
    // - XAMPP Apache: http://localhost/brain-rent/pages/index.php
    // - PHP built-in server: php -S localhost:8000 -t .
    $appUrl = 'http://localhost/brain-rent';

    if (PHP_SAPI !== 'cli' && !empty($_SERVER['HTTP_HOST']) && !empty($_SERVER['SCRIPT_NAME'])) {
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'];
        $basePath = rtrim(dirname(dirname($_SERVER['SCRIPT_NAME'])), '/\\');
        $appUrl = $scheme . '://' . $host . $basePath;
    }

    define('APP_URL', $appUrl);
}
define('APP_NAME', 'BrainRent');

// Razorpay Keys
define('RAZORPAY_KEY_ID', 'rzp_test_xxxxxxxxxxxx');
define('RAZORPAY_KEY_SECRET', 'xxxxxxxxxxxxxxxxxxxxxxx');

// Email (PHPMailer / SMTP)
define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_PORT', 587);
define('SMTP_USER', 'noreply@brainrent.com');
define('SMTP_PASS', 'your_app_password');
define('SMTP_FROM_NAME', 'BrainRent');

/**
 * Resolve stored upload URLs/paths to a local filesystem path.
 */
function resolveUploadedFilePath(string $value): string
{
    $value = trim($value);
    if ($value === '') {
        return $value;
    }

    if (is_file($value)) {
        return $value;
    }

    $path = $value;
    $urlPath = '';
    if (strpos($value, '://') !== false) {
        $parsed = parse_url($value);
        if (!empty($parsed['path'])) {
            $urlPath = $parsed['path'];
            $path = $parsed['path'];
        }
    }

    $path = urldecode($path);
    $path = str_replace('\\', '/', $path);
    $uploadsPos = strpos($path, '/uploads/');
    if ($uploadsPos !== false) {
        $path = substr($path, $uploadsPos + 1);
    } else {
        $path = ltrim($path, '/');
    }

    $root = realpath(__DIR__ . '/..') ?: (__DIR__ . '/..');
    $candidate = $root . '/' . $path;
    if (is_file($candidate)) {
        return $candidate;
    }

    if ($urlPath !== '' && !empty($_SERVER['DOCUMENT_ROOT'])) {
        $docRootPath = rtrim(str_replace('\\', '/', $_SERVER['DOCUMENT_ROOT']), '/');
        $fallback = $docRootPath . $urlPath;
        if (is_file($fallback)) {
            return $fallback;
        }
    }

    return $candidate;
}

class Database
{
    private static $instance = null;
    private $conn;

    private function __construct()
    {
        try {
            $dsn = 'mysql:host=' . DB_SERVER . ';dbname=' . DB_NAME . ';charset=utf8mb4;port=' . DB_PORT;
            $this->conn = new PDO($dsn, DB_USER, DB_PASSWORD, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);
        } catch (PDOException $e) {
            error_log('DB Connection Failed: ' . $e->getMessage());

            http_response_code(500);
            $message = 'Database connection failed';

            // Pages should not die with raw JSON; return JSON only for API/AJAX.
            if (self::wantsJsonResponse()) {
                header('Content-Type: application/json; charset=utf-8');
                die(json_encode(['success' => false, 'error' => $message]));
            }

            header('Content-Type: text/html; charset=utf-8');
            die('<!doctype html><html><head><meta charset="utf-8"><title>BrainRent - DB Error</title></head>' .
                '<body style="font-family:system-ui,-apple-system,Segoe UI,Roboto,Arial,sans-serif;max-width:860px;margin:40px auto;line-height:1.45">' .
                '<h2 style="margin:0 0 10px">' . htmlspecialchars($message, ENT_QUOTES, 'UTF-8') . '</h2>' .
                '<p style="margin:0 0 10px">MySQL/MariaDB is not reachable with the configured credentials.</p>' .
                '<p style="margin:0">Run <code>php database/setup_database.php</code> after fixing credentials in <code>config/db.php</code>.</p>' .
                '</body></html>');
        }
    }

    private static function wantsJsonResponse(): bool
    {
        if (PHP_SAPI === 'cli') {
            return false;
        }

        $uri = $_SERVER['REQUEST_URI'] ?? '';
        if (strpos($uri, '/api/') !== false) {
            return true;
        }

        $accept = $_SERVER['HTTP_ACCEPT'] ?? '';
        if (stripos($accept, 'application/json') !== false) {
            return true;
        }

        $xhr = $_SERVER['HTTP_X_REQUESTED_WITH'] ?? '';
        return strtolower($xhr) === 'xmlhttprequest';
    }

    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function getConnection(): PDO
    {
        return $this->conn;
    }

    /**
     * Execute a parameterised query and return all rows
     */
    public function fetchAll(string $sql, array $params = []): array
    {
        try {
            $stmt = $this->conn->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            $this->handleError($sql, $e);
            return [];
        }
    }

    /**
     * Return a single row
     */
    public function fetchOne(string $sql, array $params = []): ?array
    {
        try {
            $stmt = $this->conn->prepare($sql);
            $stmt->execute($params);
            $result = $stmt->fetch();
            return $result ?: null;
        } catch (PDOException $e) {
            $this->handleError($sql, $e);
            return null;
        }
    }

    /**
     * Execute INSERT/UPDATE/DELETE and return affected rows
     */
    public function execute(string $sql, array $params = []): int
    {
        try {
            $stmt = $this->conn->prepare($sql);
            $stmt->execute($params);
            return $stmt->rowCount();
        } catch (PDOException $e) {
            $this->handleError($sql, $e);
            return 0;
        }
    }

    /**
     * Execute INSERT and return new ID
     */
    public function insertGetId(string $sql, array $params = []): ?int
    {
        try {
            $stmt = $this->conn->prepare($sql);
            $stmt->execute($params);
            return (int) $this->conn->lastInsertId();
        } catch (PDOException $e) {
            $this->handleError($sql, $e);
            return null;
        }
    }

    private function handleError(string $sql, PDOException $e): void
    {
        error_log("SQL Error on: $sql => " . $e->getMessage());
    }

    // Prevent cloning
    private function __clone()
    {
    }
}
