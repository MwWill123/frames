<?php
require_once 'config/config.php';

try {
    $db = getDB();
    echo "✅ Conectado ao PostgreSQL!<br>";
    
    // Testar tabelas
    $tables = ['users', 'roles', 'user_profiles', 'editor_profiles'];
    foreach ($tables as $table) {
        $stmt = $db->query("SELECT EXISTS (SELECT FROM information_schema.tables WHERE table_name = '$table')");
        echo $stmt->fetchColumn() ? "✅ Tabela '$table' existe<br>" : "❌ Tabela '$table' não existe<br>";
    }
    
} catch (Exception $e) {
    echo "❌ Erro: " . $e->getMessage();
}
?>