<?php
/**
 * Projects API - FRAMES Platform
 * Corrigido para bater com o Schema (video_specialty, video_duration, etc)
 */

// Configurações de CORS
header("Access-Control-Allow-Origin: https://frames-silk.vercel.app");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Content-Type: application/json; charset=UTF-8");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/auth.php';

// Captura erro fatal do PHP para devolver JSON limpo
register_shutdown_function(function() {
    $error = error_get_last();
    if ($error && ($error['type'] === E_ERROR || $error['type'] === E_PARSE)) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'PHP Fatal Error: ' . $error['message']]);
    }
});

try {
    // 1. Conexão e Autenticação
    $db = getDatabase();
    $auth = new Auth($db);

    // Pega o token do header
    $headers = apache_request_headers();
    $token = null;
    if (isset($headers['Authorization'])) {
        $token = $headers['Authorization'];
    } elseif (isset($_SERVER['HTTP_AUTHORIZATION'])) {
        $token = $_SERVER['HTTP_AUTHORIZATION'];
    }

    if (!$token) {
        throw new Exception("Token de autenticação não fornecido");
    }

    // Verifica o usuário
    $user = $auth->verifyToken($token);
    if (!$user) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Sessão expirada ou inválida. Faça login novamente.']);
        exit;
    }

    // ==========================================
    // 2. CREATE PROJECT (POST)
    // ==========================================
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $input = file_get_contents("php://input");
        $data = json_decode($input);

        // Verifica ação
        if (!$data || ($data->action ?? '') !== 'create') {
            throw new Exception("Ação inválida ou dados vazios");
        }

        // Validação básica
        if (empty($data->title) || empty($data->description)) {
            throw new Exception("Título e descrição são obrigatórios");
        }

        // Tratamento de Array para o Postgres (reference_urls)
        // O Postgres espera formato texto: "{url1,url2}"
        $refsPgArray = null;
        if (isset($data->reference_url) && is_array($data->reference_url)) {
            // Limpa URLs vazias e escapa aspas
            $cleanUrls = array_filter($data->reference_url);
            if (!empty($cleanUrls)) {
                $refsPgArray = "{" . implode(",", array_map(function($url) {
                    return '"' . str_replace('"', '\"', $url) . '"';
                }, $cleanUrls)) . "}";
            }
        }

        // QUERY CORRIGIDA PARA O SEU SCHEMA
        $sql = "INSERT INTO projects (
                    client_id, 
                    title, 
                    description, 
                    status, 
                    budget_type, 
                    budget_min, 
                    budget_max, 
                    deadline, 
                    video_specialty,      -- Nome correto da coluna
                    video_duration_min,   -- Nome correto da coluna
                    video_duration_max,   -- Nome correto da coluna
                    aspect_ratio,
                    reference_urls,       -- Coluna de array
                    created_at
                ) VALUES (
                    :client_id, 
                    :title, 
                    :description, 
                    'OPEN', 
                    :budget_type, 
                    :budget_min, 
                    :budget_max, 
                    :deadline, 
                    :video_specialty, 
                    :video_duration_min,
                    :video_duration_max,
                    :aspect_ratio,
                    :reference_urls,
                    NOW()
                )";

        $stmt = $db->prepare($sql);
        
        $params = [
            ':client_id'          => $user['id'],
            ':title'              => $data->title,
            ':description'        => $data->description,
            ':budget_type'        => $data->budget_type ?? 'fixed',
            ':budget_min'         => $data->budget_min ?? 0,
            ':budget_max'         => $data->budget_max ?? 0,
            ':deadline'           => $data->deadline ?? null,
            
            // Mapeamento dos campos do JS para o Banco
            ':video_specialty'    => isset($data->specialty) ? strtoupper($data->specialty) : 'GENERAL',
            ':video_duration_min' => $data->duration_min ?? 0,
            ':video_duration_max' => $data->duration_max ?? 0,
            
            ':aspect_ratio'       => $data->aspect_ratio ?? '16:9',
            ':reference_urls'     => $refsPgArray
        ];

        if ($stmt->execute($params)) {
            echo json_encode(['success' => true, 'message' => 'Projeto criado com sucesso!']);
        } else {
            throw new Exception("Erro ao executar inserção no banco.");
        }
    }

    // ==========================================
    // 3. LIST PROJECTS (GET)
    // ==========================================
    elseif ($_SERVER['REQUEST_METHOD'] === 'GET') {
        
        // Se for pedido de estatísticas (Dashboard)
        if (isset($_GET['action']) && $_GET['action'] === 'stats') {
            $stmt = $db->prepare("
                SELECT 
                    COUNT(*) FILTER (WHERE status IN ('OPEN', 'IN_PROGRESS')) as active,
                    COUNT(*) FILTER (WHERE status = 'COMPLETED') as completed,
                    COALESCE(SUM(budget_max), 0) as spent,
                    COUNT(DISTINCT editor_id) as editors
                FROM projects 
                WHERE client_id = ?
            ");
            $stmt->execute([$user['id']]);
            $stats = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // Garante que retorna números e não null
            $response = [
                'active' => $stats['active'] ?? 0,
                'completed' => $stats['completed'] ?? 0,
                'spent' => $stats['spent'] ?? 0,
                'editors' => $stats['editors'] ?? 0
            ];
            
            echo json_encode(['success' => true, 'data' => $response]);
        } 
        // Se for listagem normal
        else {
            $stmt = $db->prepare("
                SELECT *, video_specialty as specialty -- Alias para compatibilidade com JS
                FROM projects 
                WHERE client_id = ? 
                ORDER BY created_at DESC
            ");
            $stmt->execute([$user['id']]);
            $projects = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode(['success' => true, 'data' => $projects]);
        }
    }

} catch (Exception $e) {
    http_response_code(500); 
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>