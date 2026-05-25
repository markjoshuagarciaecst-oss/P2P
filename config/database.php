<?php
// ─────────────────────────────────────────────────────────────────────────────
// Database & Application Configuration
// ─────────────────────────────────────────────────────────────────────────────

// ── Database — fill in YOUR InfinityFree values here ─────────────────────────
define('DB_HOST', 'sql209.infinityfree.com');   // ← REPLACE with your MySQL Server from InfinityFree panel
define('DB_NAME', 'if0_42008218_skillswap');   // ← REPLACE with your Database Name
define('DB_USER', 'if0_42008218');   // ← REPLACE with your Database Username
define('DB_PASS', 'Hackyu33');     // ← REPLACE with your Database Password

// ── Application ───────────────────────────────────────────────────────────────
define('APP_NAME', 'Time for Skill');
define('APP_URL',  'https://timeforskill.infinityfree.me'); // your exact domain
define('DEFAULT_POINTS', 100);

// ── Session ───────────────────────────────────────────────────────────────────
if (session_status() === PHP_SESSION_NONE) {
    // Fix for shared hosting where sessions may not persist between redirects
    ini_set('session.cookie_path', '/');
    ini_set('session.use_cookies', 1);
    ini_set('session.use_only_cookies', 1);
    ini_set('session.cookie_httponly', 1);
    ini_set('session.gc_maxlifetime', 3600);
    ini_set('session.cookie_lifetime', 0);
    session_name('TIMEFORSKILL');
    session_start();
}

// ── Timezone ──────────────────────────────────────────────────────────────────
date_default_timezone_set('Asia/Manila');

// ── Auto-load classes ─────────────────────────────────────────────────────────
spl_autoload_register(function ($class) {
    $base = dirname(__DIR__);
    foreach (array('classes', 'config') as $dir) {
        $file = $base . '/' . $dir . '/' . $class . '.php';
        if (file_exists($file)) {
            require_once $file;
            return;
        }
    }
});

// ── Database connection (singleton) ───────────────────────────────────────────
class Database {
    private static $instance = null;
    private $connection;

    private function __construct() {
        try {
            $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4';
            $this->connection = new PDO($dsn, DB_USER, DB_PASS, array(
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ));
        } catch (PDOException $e) {
            die('Database connection failed. Please check your configuration.');
        }
    }

    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance->connection;
    }

    private function __clone() {}
    public function __wakeup() { throw new Exception('Cannot unserialize singleton'); }
}

// ── Helper functions ──────────────────────────────────────────────────────────
function redirect($url) {
    if (strpos($url, 'http') !== 0) {
        $url = APP_URL . '/' . ltrim($url, '/');
    }
    header('Location: ' . $url);
    exit();
}

function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

function getUserId() {
    return isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;
}

function sanitize($data) {
    return htmlspecialchars(trim($data), ENT_QUOTES, 'UTF-8');
}

function formatDate($date) {
    return date('M d, Y', strtotime($date));
}

function formatDateTime($datetime) {
    return date('M d, Y h:i A', strtotime($datetime));
}

function asset($path) {
    return APP_URL . '/' . ltrim($path, '/');
}

function generateRandomString($length = 10) {
    return bin2hex(random_bytes($length));
}
