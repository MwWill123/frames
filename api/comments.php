<?php
/**
 * Comments API - FRAMES Platform
 * Timestamped video comments system
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

try {
    $db = getDatabase();
    $auth = new Auth($db);

    // Get token
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

    // Verify user
    $user = $auth->verifyToken($token);
    if (!$user) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Sessão expirada. Faça login novamente.']);
        exit;
    }

    // ==========================================
    // GET COMMENTS
    // ==========================================
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        if (!isset($_GET['asset_id'])) {
            throw new Exception("asset_id é obrigatório");
        }

        $asset_id = $_GET['asset_id'];

        // Verify user has access to this asset
        $stmt = $db->prepare("
            SELECT pa.project_id 
            FROM project_assets pa
            JOIN projects p ON pa.project_id = p.id
            WHERE pa.id = ? AND (p.client_id = ? OR p.editor_id = ?)
        ");
        $stmt->execute([$asset_id, $user['id'], $user['id']]);
        
        if (!$stmt->fetch()) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Acesso negado']);
            exit;
        }

        // Get comments with author info
        $stmt = $db->prepare("
            SELECT 
                c.*,
                COALESCE(ep.display_name, up.display_name, u.email) as author_name,
                u.role as author_role
            FROM video_comments c
            JOIN users u ON c.commenter_id = u.id
            LEFT JOIN editor_profiles ep ON u.id = ep.user_id
            LEFT JOIN user_profiles up ON u.id = up.user_id
            WHERE c.asset_id = ?
            ORDER BY c.timestamp_seconds ASC, c.created_at DESC
        ");
        $stmt->execute([$asset_id]);
        $comments = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode(['success' => true, 'data' => $comments]);
    }

    // ==========================================
    // CREATE COMMENT
    // ==========================================
    elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $input = file_get_contents("php://input");
        $data = json_decode($input);

        if (!$data || !isset($data->asset_id) || !isset($data->content)) {
            throw new Exception("asset_id e content são obrigatórios");
        }

        // Verify access
        $stmt = $db->prepare("
            SELECT pa.project_id 
            FROM project_assets pa
            JOIN projects p ON pa.project_id = p.id
            WHERE pa.id = ? AND (p.client_id = ? OR p.editor_id = ?)
        ");
        $stmt->execute([$data->asset_id, $user['id'], $user['id']]);
        
        if (!$stmt->fetch()) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Acesso negado']);
            exit;
        }

        // Insert comment
        $stmt = $db->prepare("
            INSERT INTO video_comments (
                asset_id, 
                commenter_id, 
                timestamp_seconds, 
                content, 
                priority,
                is_resolved,
                created_at
            ) VALUES (?, ?, ?, ?, ?, FALSE, NOW())
            RETURNING id
        ");

        $stmt->execute([
            $data->asset_id,
            $user['id'],
            $data->timestamp_seconds ?? 0,
            $data->content,
            $data->priority ?? 'normal'
        ]);

        $commentId = $stmt->fetchColumn();

        echo json_encode([
            'success' => true, 
            'message' => 'Comentário adicionado',
            'comment_id' => $commentId
        ]);
    }

    // ==========================================
    // UPDATE COMMENT (mark resolved)
    // ==========================================
    elseif ($_SERVER['REQUEST_METHOD'] === 'PUT') {
        $input = file_get_contents("php://input");
        $data = json_decode($input);

        if (!$data || !isset($data->comment_id)) {
            throw new Exception("comment_id é obrigatório");
        }

        // Verify ownership
        $stmt = $db->prepare("
            SELECT c.commenter_id, pa.project_id
            FROM video_comments c
            JOIN project_assets pa ON c.asset_id = pa.id
            JOIN projects p ON pa.project_id = p.id
            WHERE c.id = ? AND (p.client_id = ? OR p.editor_id = ? OR c.commenter_id = ?)
        ");
        $stmt->execute([$data->comment_id, $user['id'], $user['id'], $user['id']]);
        
        if (!$stmt->fetch()) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Acesso negado']);
            exit;
        }

        // Update
        $stmt = $db->prepare("
            UPDATE video_comments 
            SET is_resolved = ?, updated_at = NOW()
            WHERE id = ?
        ");

        $stmt->execute([
            $data->is_resolved ?? true,
            $data->comment_id
        ]);

        echo json_encode(['success' => true, 'message' => 'Comentário atualizado']);
    }

    // ==========================================
    // DELETE COMMENT
    // ==========================================
    elseif ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
        $input = file_get_contents("php://input");
        $data = json_decode($input);

        if (!$data || !isset($data->comment_id)) {
            throw new Exception("comment_id é obrigatório");
        }

        // Only commenter can delete
        $stmt = $db->prepare("SELECT commenter_id FROM video_comments WHERE id = ?");
        $stmt->execute([$data->comment_id]);
        $comment = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$comment || $comment['commenter_id'] !== $user['id']) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Acesso negado']);
            exit;
        }

        $stmt = $db->prepare("DELETE FROM video_comments WHERE id = ?");
        $stmt->execute([$data->comment_id]);

        echo json_encode(['success' => true, 'message' => 'Comentário deletado']);
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
