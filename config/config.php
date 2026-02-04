<?php
/**
 * Database Configuration - PostgreSQL Version
 * FRAMES - Video Editor Platform
 * Updated for Supabase PostgreSQL
 */

// ============================================
// ENVIRONMENT DETECTION
// ============================================
$is_production = (getenv('APP_ENV') === 'production' || $_SERVER['SERVER_NAME'] !== 'localhost');

// ============================================
// DATABASE CONFIGURATION (PostgreSQL)
// ============================================
if ($is_production) {
    // Production (Supabase) credentials from environment variables
    define('DB_HOST', getenv('DB_HOST') ?: 'db.your-project.supabase.co');
    define('DB_PORT', getenv('DB_PORT') ?: '5432');
    define('DB_NAME', getenv('DB_NAME') ?: 'postgres');
    define('DB_USER', getenv('DB_USER') ?: 'postgres');
    define('DB_PASS', getenv('DB_PASSWORD') ?: '');
    define('DB_SSL', true);
} else {
    // Local development (optional - keep your local setup)
    define('DB_HOST', 'localhost');
    define('DB_NAME', 'frames_db');
    define('DB_USER', 'postgres');  // Changed from 'root'
    define('DB_PASS', 'your_local_password');
    define('DB_PORT', '5432');
    define('DB_SSL', false);
}

// PostgreSQL connection charset
define('DB_CHARSET', 'UTF8');

// ============================================
// SITE CONFIGURATION
// ============================================
if ($is_production) {
    define('SITE_URL', getenv('SITE_URL') ?: 'https://your-site.vercel.app');
} else {
    define('SITE_URL', 'http://localhost/frames');
}

define('SITE_NAME', 'FRAMES');
define('SITE_TAGLINE', 'Unlock Your Visual Story');

// ============================================
// PATH CONFIGURATION
// ============================================
define('ROOT_PATH', dirname(__DIR__));  // Adjust if needed
define('UPLOAD_PATH', ROOT_PATH . '/uploads/');
define('MAX_UPLOAD_SIZE', 50 * 1024 * 1024); // 50MB

// ============================================
// ERROR REPORTING
// ============================================
if ($is_production) {
    error_reporting(0);
    ini_set('display_errors', 0);
    ini_set('log_errors', 1);
    ini_set('error_log', ROOT_PATH . '/logs/error.log');
} else {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
}

// ============================================
// TIMEZONE & LOCALE
// ============================================
date_default_timezone_set('America/Sao_Paulo');
setlocale(LC_ALL, 'pt_BR.utf8');

// ============================================
// SESSION CONFIGURATION
// ============================================
if ($is_production) {
    ini_set('session.cookie_secure', 1);  // HTTPS only
}
ini_set('session.cookie_httponly', 1);
ini_set('session.use_only_cookies', 1);
ini_set('session.cookie_samesite', 'Strict');

// ============================================
// DATABASE CONNECTION CLASS (PostgreSQL)
// ============================================
class Database {
    private static $instance = null;
    private $connection;
    
    private function __construct() {
        try {
            // Build DSN for PostgreSQL
            $dsn = "pgsql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME;
            
            // Add SSL if required
            if (defined('DB_SSL') && DB_SSL) {
                $dsn .= ";sslmode=require";
            }
            
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::ATTR_PERSISTENT => false,
                PDO::ATTR_STRINGIFY_FETCHES => false,
            ];
            
            $this->connection = new PDO($dsn, DB_USER, DB_PASS, $options);
            
            // Set client encoding
            $this->connection->exec("SET NAMES '" . DB_CHARSET . "'");
            $this->connection->exec("SET timezone = 'America/Sao_Paulo'");
            
        } catch (PDOException $e) {
            // Don't expose database errors in production
            if ($is_production) {
                error_log("Database connection failed: " . $e->getMessage());
                die("Service temporarily unavailable. Please try again later.");
            } else {
                die("Database connection failed: " . $e->getMessage());
            }
        }
    }
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    public function getConnection() {
        return $this->connection;
    }
    
    // Helper method for UUID generation (PostgreSQL)
    public function generateUuid() {
        $stmt = $this->connection->query("SELECT gen_random_uuid()");
        return $stmt->fetchColumn();
    }
    
    // Helper method for PostgreSQL-specific queries
    public function paginate($query, $page = 1, $perPage = 20, $params = []) {
        $offset = ($page - 1) * $perPage;
        $countQuery = "SELECT COUNT(*) FROM ($query) AS count_query";
        
        $stmt = $this->connection->prepare($countQuery);
        $stmt->execute($params);
        $total = $stmt->fetchColumn();
        
        $pagedQuery = $query . " LIMIT :limit OFFSET :offset";
        $params[':limit'] = $perPage;
        $params[':offset'] = $offset;
        
        $stmt = $this->connection->prepare($pagedQuery);
        $stmt->execute($params);
        $data = $stmt->fetchAll();
        
        return [
            'data' => $data,
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
            'total_pages' => ceil($total / $perPage)
        ];
    }
    
    // Prevent cloning and unserializing
    private function __clone() {}
    private function __wakeup() {}
}

// ============================================
// HELPER FUNCTIONS
// ============================================

// Helper function to get database connection
function getDB() {
    return Database::getInstance()->getConnection();
}

// Response helper function
function sendJSON($data, $statusCode = 200) {
    http_response_code($statusCode);
    header('Content-Type: application/json');
    echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

// Sanitize input helper
function sanitizeInput($data) {
    if (is_array($data)) {
        return array_map('sanitizeInput', $data);
    }
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
    return $data;
}

// Validate email helper
function validateEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL);
}

// Generate unique filename
function generateUniqueFilename($originalName) {
    $extension = pathinfo($originalName, PATHINFO_EXTENSION);
    $filename = uniqid() . '_' . time() . '.' . strtolower($extension);
    return $filename;
}

// Check if request is AJAX
function isAjax() {
    return isset($_SERVER['HTTP_X_REQUESTED_WITH']) && 
           strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
}

// CORS headers for API
function setCorsHeaders() {
    $allowed_origins = $is_production 
        ? ['https://your-site.vercel.app']
        : ['http://localhost:3000', 'http://localhost:8000'];
    
    if (isset($_SERVER['HTTP_ORIGIN']) && in_array($_SERVER['HTTP_ORIGIN'], $allowed_origins)) {
        header("Access-Control-Allow-Origin: " . $_SERVER['HTTP_ORIGIN']);
    }
    
    header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
    header('Access-Control-Allow-Credentials: true');
    
    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        http_response_code(200);
        exit;
    }
}

// PostgreSQL-specific helper: Array to PostgreSQL array format
function arrayToPgArray($array) {
    if (empty($array)) return '{}';
    return '{' . implode(',', array_map(function($item) {
        return '"' . str_replace('"', '""', $item) . '"';
    }, $array)) . '}';
}

// Convert PostgreSQL array string to PHP array
function pgArrayToPhp($pgArray) {
    if (empty($pgArray) || $pgArray[0] !== '{') return [];
    $pgArray = substr($pgArray, 1, -1); // Remove braces
    $result = [];
    $inQuotes = false;
    $current = '';
    
    for ($i = 0; $i < strlen($pgArray); $i++) {
        $char = $pgArray[$i];
        
        if ($char === '"' && ($i === 0 || $pgArray[$i-1] !== '\\')) {
            $inQuotes = !$inQuotes;
        } elseif ($char === ',' && !$inQuotes) {
            $result[] = $current;
            $current = '';
        } else {
            $current .= $char;
        }
    }
    
    if ($current !== '') {
        $result[] = $current;
    }
    
    return $result;
}

// ============================================
// AUTOLOAD CONFIGURATION
// ============================================
spl_autoload_register(function ($class) {
    $prefix = 'App\\';
    $base_dir = ROOT_PATH . '/php/';
    
    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }
    
    $relative_class = substr($class, $len);
    $file = $base_dir . str_replace('\\', '/', $relative_class) . '.php';
    
    if (file_exists($file)) {
        require $file;
    }
});

// ============================================
// INITIALIZATION
// ============================================
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Auto-detect environment
if (file_exists(ROOT_PATH . '/.env.production')) {
    define('ENVIRONMENT', 'production');
} elseif (file_exists(ROOT_PATH . '/.env.development')) {
    define('ENVIRONMENT', 'development');
} else {
    define('ENVIRONMENT', $is_production ? 'production' : 'development');
}
?>