<?php
/**
 * Projects API - FRAMES Platform
 * COMPLETE with Marketplace Integration
 */

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

register_shutdown_function(function() {
    $error = error_get_last();
    if ($error && ($error['type'] === E_ERROR || $error['type'] === E_PARSE)) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'PHP Fatal Error: ' . $error['message']]);
    }
});

try {
    $db = getDatabase();
    $auth = new Auth($db);

    // Get token
    $headers = apache_request_headers();
    $token = $headers['Authorization'] ?? $_SERVER['HTTP_AUTHORIZATION'] ?? null;

    if (!$token) {
        throw new Exception("Token de autenticação não fornecido");
    }

    $user = $auth->verifyToken($token);
    if (!$user) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Sessão expirada']);
        exit;
    }

    // ==========================================
    // CREATE PROJECT (POST)
    // ==========================================
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $input = file_get_contents("php://input");
        $data = json_decode($input);

        if (!$data || ($data->action ?? '') !== 'create') {
            throw new Exception("Ação inválida ou dados vazios");
        }

        if (empty($data->title) || empty($data->description)) {
            throw new Exception("Título e descrição são obrigatórios");
        }

        // Array handling for PostgreSQL
        $refsPgArray = null;
        if (isset($data->reference_url) && is_array($data->reference_url)) {
            $cleanUrls = array_filter($data->reference_url);
            if (!empty($cleanUrls)) {
                $refsPgArray = "{" . implode(",", array_map(function($url) {
                    return '"' . str_replace('"', '\"', $url) . '"';
                }, $cleanUrls)) . "}";
            }
        }

        $sql = "INSERT INTO projects (
                    client_id, 
                    title, 
                    description, 
                    status, 
                    budget_type, 
                    budget_min, 
                    budget_max, 
                    deadline, 
                    video_specialty,
                    video_duration_min,
                    video_duration_max,
                    aspect_ratio,
                    reference_urls,
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
    // LIST PROJECTS (GET)
    // ==========================================
    elseif ($_SERVER['REQUEST_METHOD'] === 'GET') {
        
        // EDITOR: Get OPEN projects (Marketplace / Explore Projects)
        if (isset($_GET['status']) && $_GET['status'] === 'OPEN') {
            $query = "
                SELECT 
                    p.*,
                    p.video_specialty as specialty,
                    COALESCE(
                        (SELECT COUNT(*) FROM proposals WHERE project_id = p.id),
                        0
                    ) as proposals_count
                FROM projects p
                WHERE p.status = 'OPEN'
            ";
            
            $params = [];
            
            // Filter by specialty
            if (isset($_GET['specialty']) && !empty($_GET['specialty'])) {
                $query .= " AND p.video_specialty = ?";
                $params[] = $_GET['specialty'];
            }
            
            // Filter by budget range
            if (isset($_GET['budget_min'])) {
                $query .= " AND p.budget_max >= ?";
                $params[] = (float)$_GET['budget_min'];
            }
            if (isset($_GET['budget_max'])) {
                $query .= " AND p.budget_max <= ?";
                $params[] = (float)$_GET['budget_max'];
            }
            
            $query .= " ORDER BY p.created_at DESC";
            
            $stmt = $db->prepare($query);
            $stmt->execute($params);
            $projects = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            echo json_encode(['success' => true, 'data' => $projects]);
        }
        
        // CLIENT: Get stats (Dashboard)
        elseif (isset($_GET['action']) && $_GET['action'] === 'stats') {
            if ($user['role'] === 'CLIENT') {
                $stmt = $db->prepare("
                    SELECT 
                        CAST(COUNT(CASE WHEN status IN ('OPEN', 'IN_PROGRESS') THEN 1 END) AS INTEGER) as active,
                        CAST(COUNT(CASE WHEN status = 'COMPLETED' THEN 1 END) AS INTEGER) as completed,
                        COALESCE(SUM(budget_max), 0) as spent,
                        COUNT(DISTINCT editor_id) as editors
                    FROM projects 
                    WHERE client_id = ?
                ");
                $stmt->execute([$user['id']]);
            } else {
                // EDITOR: Get stats
                $stmt = $db->prepare("
                    SELECT 
                        CAST(COUNT(CASE WHEN status IN ('IN_PROGRESS', 'IN_REVIEW') THEN 1 END) AS INTEGER) as active,
                        CAST(COUNT(CASE WHEN status = 'COMPLETED' THEN 1 END) AS INTEGER) as completed,
                        COALESCE(SUM(agreed_price * 0.85), 0) as earnings,
                        COALESCE(
                            (SELECT AVG(rating) FROM projects WHERE editor_id = ? AND rating IS NOT NULL),
                            0
                        ) as rating
                    FROM projects 
                    WHERE editor_id = ?
                ");
                $stmt->execute([$user['id'], $user['id']]);
            }
            
            $stats = $stmt->fetch(PDO::FETCH_ASSOC);
            echo json_encode(['success' => true, 'data' => $stats]);
        }
        
        // CLIENT/EDITOR: Get my projects
        else {
            $whereClause = $user['role'] === 'CLIENT' ? 'client_id = ?' : 'editor_id = ?';
            
            $stmt = $db->prepare("
                SELECT 
                    p.*,
                    p.video_specialty as specialty,
                    COALESCE(
                        (SELECT COUNT(*) FROM proposals WHERE project_id = p.id),
                        0
                    ) as proposals_count
                FROM projects p
                WHERE p.$whereClause
                ORDER BY p.created_at DESC
            ");
            $stmt->execute([$user['id']]);
            $projects = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            echo json_encode(['success' => true, 'data' => $projects]);
        }
    }

    // ==========================================
    // UPDATE PROJECT (PUT)
    // ==========================================
    elseif ($_SERVER['REQUEST_METHOD'] === 'PUT') {
        $input = file_get_contents("php://input");
        $data = json_decode($input);

        if (!$data || !isset($data->project_id)) {
            throw new Exception("project_id é obrigatório");
        }

        // Verify ownership
        $stmt = $db->prepare("SELECT client_id, editor_id FROM projects WHERE id = ?");
        $stmt->execute([$data->project_id]);
        $project = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$project) {
            throw new Exception("Projeto não encontrado");
        }

        // Only owner can update
        if ($project['client_id'] !== $user['id'] && $project['editor_id'] !== $user['id']) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Acesso negado']);
            exit;
        }

        // Update status
        if (isset($data->status)) {
            $stmt = $db->prepare("UPDATE projects SET status = ? WHERE id = ?");
            $stmt->execute([$data->status, $data->project_id]);
        }

        // Update rating (client only)
        if (isset($data->rating) && $project['client_id'] === $user['id']) {
            $stmt = $db->prepare("
                UPDATE projects 
                SET rating = ?, review_comment = ? 
                WHERE id = ?
            ");
            $stmt->execute([
                $data->rating,
                $data->review ?? null,
                $data->project_id
            ]);
        }

        echo json_encode(['success' => true, 'message' => 'Projeto atualizado']);
    }

} catch (Exception $e) {
    http_response_code(500); 
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
