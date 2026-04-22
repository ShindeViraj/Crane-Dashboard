<?php
/**
 * Environment Configuration Loader
 * Reads .env file and provides config values
 */

function loadEnv($path = null) {
    if ($path === null) {
        $path = __DIR__ . '/../.env';
    }
    
    if (!file_exists($path)) {
        return false;
    }
    
    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);
        if (strpos($line, '#') === 0) continue;
        if (strpos($line, '=') === false) continue;
        
        list($key, $value) = explode('=', $line, 2);
        $key = trim($key);
        $value = trim($value, " \t\n\r\0\x0B\"'");
        
        if (!array_key_exists($key, $_ENV)) {
            $_ENV[$key] = $value;
            putenv("$key=$value");
        }
    }
    return true;
}

function env($key, $default = null) {
    $val = getenv($key);
    if ($val === false) {
        return isset($_ENV[$key]) ? $_ENV[$key] : $default;
    }
    return $val;
}

// Auto-load .env
loadEnv();

// Database config from .env
define('DB_HOST', env('DB_HOST', 'localhost'));
define('DB_NAME', env('DB_NAME', 'bml_iot'));
define('DB_USER', env('DB_USER', 'root'));
define('DB_PASS', env('DB_PASS', ''));
define('DB_CHARSET', 'utf8');  // ProFreeHost: use utf8 (not utf8mb4)
define('SESSION_SECRET', env('SESSION_SECRET', 'default_secret_change_me'));

/**
 * Get PDO database connection
 */
function getDbConnection() {
    static $pdo = null;
    
    if ($pdo === null) {
        $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => true,  // Required for some shared hosts
        ];
        
        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            // Log the real error server-side — never expose to clients
            error_log('[BML-IOT] DB connection failed: ' . $e->getMessage() . ' | ' . date('c'));

            if (php_sapi_name() !== 'cli' && strpos($_SERVER['REQUEST_URI'] ?? '', '/api/') !== false) {
                http_response_code(500);
                echo json_encode(['error' => 'Database connection failed. Please try again later.']);
                exit;
            }
            die('<div style="padding:40px;font-family:sans-serif;color:#ba1a1a;">Database connection failed. Please contact the system administrator.</div>');
        }
    }
    
    return $pdo;
}
?>
