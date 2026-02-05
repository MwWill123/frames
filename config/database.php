<?php
/**
 * Database Configuration for PostgreSQL (Supabase/Neon)
 * FRAMES Platform
 */

// === CREDENCIAIS DO SUPABASE (substitua pelos seus) ===
define('DB_HOST', 'db.qbjchsarlehpysuncbfq.supabase.co');     // ex: db.abcde12345.supabase.co
define('DB_PORT', '5432');
define('DB_NAME', 'postgres');                           // sempre "postgres" no Supabase
define('DB_USER', 'postgres');
define('DB_PASS', 'Y}AOS=8b8|h3');               // a senha que você definiu no Supabase

// SSL é obrigatório no Supabase
define('DB_SSLMODE', 'require');

// Classe de conexão (adaptada para PostgreSQL)
class Database {
    private static $instance = null;
    private $connection;

    private function __construct() {
        try {
            $dsn = "pgsql:host=" . DB_HOST .
                   ";port=" . DB_PORT .
                   ";dbname=" . DB_NAME .
                   ";sslmode=" . DB_SSLMODE;

            $options = [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ];

            $this->connection = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            die("Database connection failed: " . $e->getMessage());
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
}

// Função usada pelos seus arquivos (alguns chamam getDatabase(), outros getDB())
function getDatabase() {
    return Database::getInstance()->getConnection();
}

// Alias para compatibilidade (caso algum arquivo use getDB())
function getDB() {
    return getDatabase();
}

// CORS (mantenha se precisar, já que vários arquivos usam)
function setCorsHeaders() {
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization');
    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        http_response_code(200);
        exit;
    }
}
?>