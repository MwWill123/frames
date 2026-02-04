<?php
// config/database.php

class Database {
    private static $connection = null;
    
    public static function getConnection() {
        if (self::$connection === null) {
            $host = getenv('DB_HOST') ?: 'localhost';
            $port = getenv('DB_PORT') ?: '5432';
            $dbname = getenv('DB_NAME') ?: 'frames_db';
            $user = getenv('DB_USER') ?: 'postgres';
            $password = getenv('DB_PASSWORD') ?: '';
            
            try {
                $dsn = "pgsql:host=$host;port=$port;dbname=$dbname";
                self::$connection = new PDO($dsn, $user, $password, [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                    PDO::ATTR_PERSISTENT => true
                ]);
                
                // Forçar UTF-8
                self::$connection->exec("SET NAMES 'UTF8'");
                
            } catch (PDOException $e) {
                error_log("Database connection failed: " . $e->getMessage());
                throw new Exception("Database connection error. Please try again later.");
            }
        }
        return self::$connection;
    }
    
    public static function closeConnection() {
        self::$connection = null;
    }
}

// Testar conexão (opcional)
if (php_sapi_name() === 'cli' && isset($argv[0]) && basename($argv[0]) == 'database.php') {
    try {
        $conn = Database::getConnection();
        echo "✅ Connected to PostgreSQL successfully!\n";
        
        // Verificar tabelas
        $stmt = $conn->query("SELECT table_name FROM information_schema.tables WHERE table_schema = 'public'");
        $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        echo "📊 Found " . count($tables) . " tables:\n";
        foreach ($tables as $table) {
            echo "  - $table\n";
        }
        
    } catch (Exception $e) {
        echo "❌ Error: " . $e->getMessage() . "\n";
    }
}

require_once '../config/config.php';

// Suas queries continuam funcionando
$db = getDB();
$stmt = $db->prepare("SELECT * FROM users WHERE id = :id");
$stmt->execute([':id' => 1]);
$user = $stmt->fetch();
?>