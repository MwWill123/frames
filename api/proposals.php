<?php
/**
 * Proposals API - FRAMES Platform
 * Marketplace de propostas: Editores propõem para projetos abertos
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
    // GET PROPOSALS
    // ==========================================
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        
        // Get proposals for a specific project (CLIENT view)
        if (isset($_GET['project_id'])) {
            $stmt = $db->prepare("
                SELECT 
                    p.*,
                    ep.display_name as editor_name,
                    ep.avatar_url as editor_avatar,
                    ep.bio as editor_bio,
                    ep.primary_software,
                    ep.average_rating,
                    ep.total_reviews,
                    ep.completed_projects_count,
                    (
                        SELECT json_agg(json_build_object(
                            'url', pa.file_url,
                            'thumbnail_url', pa.thumbnail_url,
                            'title', proj.title
                        ))
                        FROM project_assets pa
                        JOIN projects proj ON pa.project_id = proj.id
                        WHERE proj.editor_id = p.editor_id 
                        AND pa.file_type = 'FINAL_DELIVERY'
                        AND proj.status = 'COMPLETED'
                        LIMIT 3
                    ) as portfolio_samples
                FROM proposals p
                JOIN editor_profiles ep ON p.editor_id = ep.user_id
                WHERE p.project_id = ?
                ORDER BY p.created_at DESC
            ");
            $stmt->execute([$_GET['project_id']]);
            $proposals = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            echo json_encode(['success' => true, 'data' => $proposals]);
        }
        
        // Get my proposals (EDITOR view)
        elseif (isset($_GET['my_proposals'])) {
            $stmt = $db->prepare("
                SELECT 
                    p.*,
                    proj.title as project_title,
                    proj.description as project_description,
                    proj.budget_min,
                    proj.budget_max,
                    proj.deadline,
                    proj.video_specialty,
                    proj.status as project_status
                FROM proposals p
                JOIN projects proj ON p.project_id = proj.id
                WHERE p.editor_id = ?
                ORDER BY p.created_at DESC
            ");
            $stmt->execute([$user['id']]);
            $proposals = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            echo json_encode(['success' => true, 'data' => $proposals]);
        }
        
        // Get proposal stats (EDITOR)
        elseif (isset($_GET['stats'])) {
            $stmt = $db->prepare("
                SELECT 
                    COUNT(*) FILTER (WHERE status = 'PENDING') as pending,
                    COUNT(*) FILTER (WHERE status = 'ACCEPTED') as accepted,
                    COUNT(*) FILTER (WHERE status = 'REJECTED') as rejected,
                    COALESCE(AVG(proposed_price) FILTER (WHERE status = 'ACCEPTED'), 0) as avg_accepted_price
                FROM proposals
                WHERE editor_id = ?
            ");
            $stmt->execute([$user['id']]);
            $stats = $stmt->fetch(PDO::FETCH_ASSOC);
            
            echo json_encode(['success' => true, 'data' => $stats]);
        }
        
        else {
            throw new Exception("Parâmetro inválido");
        }
    }

    // ==========================================
    // CREATE PROPOSAL (EDITOR only)
    // ==========================================
    elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $input = file_get_contents("php://input");
        $data = json_decode($input);

        if (!$data || !isset($data->project_id)) {
            throw new Exception("project_id é obrigatório");
        }

        // Only editors can create proposals
        if ($user['role'] !== 'EDITOR') {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Apenas editores podem enviar propostas']);
            exit;
        }

        // Verify project exists and is OPEN
        $stmt = $db->prepare("SELECT status, client_id FROM projects WHERE id = ?");
        $stmt->execute([$data->project_id]);
        $project = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$project) {
            throw new Exception("Projeto não encontrado");
        }

        if ($project['status'] !== 'OPEN') {
            throw new Exception("Este projeto não está mais aceitando propostas");
        }

        // Check if editor already submitted proposal
        $stmt = $db->prepare("SELECT id FROM proposals WHERE project_id = ? AND editor_id = ?");
        $stmt->execute([$data->project_id, $user['id']]);
        
        if ($stmt->fetch()) {
            throw new Exception("Você já enviou uma proposta para este projeto");
        }

        // Validate required fields
        if (!isset($data->proposed_price) || !isset($data->delivery_days)) {
            throw new Exception("Preço e prazo de entrega são obrigatórios");
        }

        $db->beginTransaction();

        // Insert proposal
        $stmt = $db->prepare("
            INSERT INTO proposals (
                project_id,
                editor_id,
                proposed_price,
                delivery_days,
                cover_letter,
                status
            ) VALUES (?, ?, ?, ?, ?, 'PENDING')
            RETURNING id
        ");

        $stmt->execute([
            $data->project_id,
            $user['id'],
            $data->proposed_price,
            $data->delivery_days,
            $data->cover_letter ?? ''
        ]);

        $proposalId = $stmt->fetchColumn();

        // Create notification for client
        $stmt = $db->prepare("
            INSERT INTO notifications (
                user_id,
                type,
                title,
                message,
                related_id,
                related_type
            ) VALUES (?, 'new_proposal', ?, ?, ?, 'proposal')
        ");

        $editorName = "Um editor";
        $stmt->execute([
            $project['client_id'],
            'Nova Proposta Recebida',
            "Você recebeu uma nova proposta de $editorName",
            $proposalId
        ]);

        $db->commit();

        echo json_encode([
            'success' => true,
            'message' => 'Proposta enviada com sucesso!',
            'proposal_id' => $proposalId
        ]);
    }

    // ==========================================
    // UPDATE PROPOSAL STATUS (CLIENT accepts/rejects)
    // ==========================================
    elseif ($_SERVER['REQUEST_METHOD'] === 'PUT') {
        $input = file_get_contents("php://input");
        $data = json_decode($input);

        if (!$data || !isset($data->proposal_id) || !isset($data->action)) {
            throw new Exception("proposal_id e action são obrigatórios");
        }

        // Get proposal details
        $stmt = $db->prepare("
            SELECT 
                prop.*,
                proj.client_id,
                proj.title as project_title
            FROM proposals prop
            JOIN projects proj ON prop.project_id = proj.id
            WHERE prop.id = ?
        ");
        $stmt->execute([$data->proposal_id]);
        $proposal = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$proposal) {
            throw new Exception("Proposta não encontrada");
        }

        // Only project owner can accept/reject
        if ($user['id'] !== $proposal['client_id']) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Acesso negado']);
            exit;
        }

        $db->beginTransaction();

        if ($data->action === 'accept') {
            // Accept proposal
            $stmt = $db->prepare("
                UPDATE proposals 
                SET status = 'ACCEPTED', responded_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([$data->proposal_id]);

            // Reject all other proposals for this project
            $stmt = $db->prepare("
                UPDATE proposals 
                SET status = 'REJECTED', responded_at = NOW()
                WHERE project_id = ? AND id != ?
            ");
            $stmt->execute([$proposal['project_id'], $data->proposal_id]);

            // Update project: assign editor and change status
            $stmt = $db->prepare("
                UPDATE projects 
                SET 
                    editor_id = ?,
                    status = 'IN_PROGRESS',
                    agreed_price = ?
                WHERE id = ?
            ");
            $stmt->execute([
                $proposal['editor_id'],
                $proposal['proposed_price'],
                $proposal['project_id']
            ]);

            // Create escrow payment
            $stmt = $db->prepare("
                INSERT INTO escrow_payments (
                    project_id,
                    client_id,
                    editor_id,
                    total_amount,
                    status
                ) VALUES (?, ?, ?, ?, 'PENDING')
            ");
            $stmt->execute([
                $proposal['project_id'],
                $proposal['client_id'],
                $proposal['editor_id'],
                $proposal['proposed_price']
            ]);

            // Notify accepted editor
            $stmt = $db->prepare("
                INSERT INTO notifications (
                    user_id, type, title, message, related_id, related_type
                ) VALUES (?, 'proposal_accepted', ?, ?, ?, 'project')
            ");
            $stmt->execute([
                $proposal['editor_id'],
                'Proposta Aceita! 🎉',
                "Sua proposta para '{$proposal['project_title']}' foi aceita!",
                $proposal['project_id']
            ]);

            $message = 'Proposta aceita! O editor foi notificado.';
        } 
        else {
            // Reject proposal
            $stmt = $db->prepare("
                UPDATE proposals 
                SET status = 'REJECTED', responded_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([$data->proposal_id]);

            // Notify rejected editor
            $stmt = $db->prepare("
                INSERT INTO notifications (
                    user_id, type, title, message, related_id, related_type
                ) VALUES (?, 'proposal_rejected', ?, ?, ?, 'proposal')
            ");
            $stmt->execute([
                $proposal['editor_id'],
                'Proposta não aceita',
                "Sua proposta para '{$proposal['project_title']}' não foi aceita desta vez.",
                $data->proposal_id
            ]);

            $message = 'Proposta rejeitada.';
        }

        $db->commit();

        echo json_encode(['success' => true, 'message' => $message]);
    }

    // ==========================================
    // DELETE PROPOSAL (EDITOR withdraws)
    // ==========================================
    elseif ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
        $input = file_get_contents("php://input");
        $data = json_decode($input);

        if (!$data || !isset($data->proposal_id)) {
            throw new Exception("proposal_id é obrigatório");
        }

        // Verify ownership
        $stmt = $db->prepare("SELECT editor_id, status FROM proposals WHERE id = ?");
        $stmt->execute([$data->proposal_id]);
        $proposal = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$proposal || $proposal['editor_id'] !== $user['id']) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Acesso negado']);
            exit;
        }

        if ($proposal['status'] !== 'PENDING') {
            throw new Exception("Apenas propostas pendentes podem ser retiradas");
        }

        $stmt = $db->prepare("
            UPDATE proposals 
            SET status = 'WITHDRAWN'
            WHERE id = ?
        ");
        $stmt->execute([$data->proposal_id]);

        echo json_encode(['success' => true, 'message' => 'Proposta retirada']);
    }

} catch (Exception $e) {
    if ($db && $db->inTransaction()) {
        $db->rollBack();
    }
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
