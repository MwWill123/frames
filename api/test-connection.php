<?php
// Teste detalhado de conexão ao Supabase

// === SUAS CREDENCIAIS EXATAS DO SUPABASE (confira no dashboard) ===
define('DB_HOST', 'db.qbjchsarlehpysuncbfq.supabase.co'); // verifique no Supabase > Settings > Database
define('DB_PORT', '5432');
define('DB_NAME', 'postgres');
define('DB_USER', 'postgres');
define('DB_PASS', 'Y}AOS=8b8|h3'); // senha EXATA (copie do Supabase, sem espaços)

try {
    $dsn = "pgsql:host=" . DB_HOST . 
           ";port=" . DB_PORT . 
           ";dbname=" . DB_NAME . 
           ";sslmode=require;options=--client_encoding=UTF8";

    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ];

    $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
    
    echo "✅ Conexão com Supabase FUNCIONOU!<br><br>";
    
    // Teste query simples
    $stmt = $pdo->query("SELECT id, email, role FROM users LIMIT 3");
    $users = $stmt->fetchAll();
    echo "Usuários encontrados: " . count($users) . "<br>";
    echo "<pre>";
    print_r($users);
    echo "</pre>";
    
} catch (PDOException $e) {
    echo "❌ Erro detalhado de conexão:<br>";
    echo $e->getMessage() . "<br><br>";
    echo "Código de erro: " . $e->getCode() . "<br>";
} catch (Exception $e) {
    echo "❌ Erro geral: " . $e->getMessage();
}
?>