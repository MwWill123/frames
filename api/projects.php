<?php
setCorsHeaders(); // MOVA PARA O TOPO, antes de tudo

header('Content-Type: application/json');
// Handling global para erros (sempre JSON)
set_error_handler(function ($severity, $message, $file, $line) {
    if (error_reporting() & $severity) {
        sendJSON(['success' => false, 'message' => "PHP Error: $message in $file on line $line"], 500);
    }
});
set_exception_handler(function ($exception) {
    sendJSON(['success' => false, 'message' => 'Exception: ' . $exception->getMessage()], 500);
});
register_shutdown_function(function () {
    $error = error_get_last();
    if ($error && $error['type'] === E_ERROR) {
        sendJSON(['success' => false, 'message' => 'Fatal Error: ' . $error['message']], 500);
    }
});
/**
 * Projects API
 * FRAMES Platform
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/auth.php';

header('Content-Type: application/json');
setCorsHeaders();

$method = $_SERVER['REQUEST_METHOD'];
$input = json_decode(file_get_contents('php://input'), true);
$action = $input['action'] ?? $_GET['action'] ?? null;

// Get authenticated user
$token = str_replace('Bearer ', '', $_SERVER['HTTP_AUTHORIZATION'] ?? '');
$auth = new Auth(getDatabase());
$authResult = $auth->verifyToken($token);

if (!$authResult['success']) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$userId = $authResult['data']->user_id;
$userRole = $authResult['data']->role;

// Route requests
switch ($method) {
    case 'GET':
        handleGetRequest($userId, $userRole);
        break;
    case 'POST':
        handlePostRequest($userId, $userRole, $input);
        break;
    case 'PUT':
        handlePutRequest($userId, $userRole, $input);
        break;
    case 'DELETE':
        handleDeleteRequest($userId, $userRole, $input);
        break;
}

/**
 * GET - Fetch projects
 */
function handleGetRequest($userId, $userRole) {
    $db = getDatabase();
    
    // Stats endpoint
    if (isset($_GET['action']) && $_GET['action'] === 'stats') {
        getProjectStats($db, $userId, $userRole);
        return;
    }
    
    // Single project
    if (isset($_GET['id'])) {
        getSingleProject($db, $_GET['id'], $userId, $userRole);
        return;
    }
    
    // List projects
    listProjects($db, $userId, $userRole);
}

function getProjectStats($db, $userId, $userRole) {
    try {
        $whereClause = $userRole === 'CLIENT' ? 'client_id = ?' : 'editor_id = ?';
        
        $stmt = $db->prepare("
            SELECT 
                COUNT(*) FILTER (WHERE status IN ('OPEN', 'IN_PROGRESS', 'IN_REVIEW')) as active,
                COUNT(*) FILTER (WHERE status = 'COMPLETED') as completed,
                COALESCE(SUM(agreed_price) FILTER (WHERE status = 'COMPLETED'), 0) as spent,
                COUNT(DISTINCT editor_id) FILTER (WHERE editor_id IS NOT NULL) as editors
            FROM projects
            WHERE $whereClause AND deleted_at IS NULL
        ");
        $stmt->execute([$userId]);
        $stats = $stmt->fetch(PDO::FETCH_ASSOC);
        
        sendJSON(['success' => true, 'data' => $stats]);
        
    } catch (Exception $e) {
        error_log("Get stats error: " . $e->getMessage());
        sendJSON(['success' => false, 'message' => 'Error fetching stats'], 500);
    }
}

function getSingleProject($db, $projectId, $userId, $userRole) {
    try {
        $stmt = $db->prepare("
            SELECT 
                p.*,
                c.email as client_email,
                up_c.display_name as client_name,
                e.email as editor_email,
                ep.display_name as editor_name,
                ep.avatar_url as editor_avatar
            FROM projects p
            JOIN users c ON p.client_id = c.id
            LEFT JOIN user_profiles up_c ON c.id = up_c.user_id
            LEFT JOIN users e ON p.editor_id = e.id
            LEFT JOIN editor_profiles ep ON e.id = ep.user_id
            WHERE p.id = ?
        ");
        $stmt->execute([$projectId]);
        $project = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$project) {
            sendJSON(['success' => false, 'message' => 'Project not found'], 404);
            return;
        }
        
        // Check permission
        if ($userRole === 'CLIENT' && $project['client_id'] !== $userId) {
            sendJSON(['success' => false, 'message' => 'Access denied'], 403);
            return;
        }
        
        if ($userRole === 'EDITOR' && $project['editor_id'] !== $userId) {
            sendJSON(['success' => false, 'message' => 'Access denied'], 403);
            return;
        }
        
        // Get project assets
        $stmt = $db->prepare("SELECT * FROM project_assets WHERE project_id = ? ORDER BY created_at DESC");
        $stmt->execute([$projectId]);
        $project['assets'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Get proposals if client
        if ($userRole === 'CLIENT') {
            $stmt = $db->prepare("
                SELECT 
                    prop.*,
                    ep.display_name as editor_name,
                    ep.avatar_url as editor_avatar,
                    ep.average_rating,
                    ep.total_reviews
                FROM proposals prop
                JOIN editor_profiles ep ON prop.editor_id = ep.user_id
                WHERE prop.project_id = ?
                ORDER BY prop.created_at DESC
            ");
            $stmt->execute([$projectId]);
            $project['proposals'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
        
        sendJSON(['success' => true, 'data' => $project]);
        
    } catch (Exception $e) {
        error_log("Get project error: " . $e->getMessage());
        sendJSON(['success' => false, 'message' => 'Error fetching project'], 500);
    }
}

function listProjects($db, $userId, $userRole) {
    try {
        $query = "
            SELECT 
                p.*,
                c.email as client_email,
                up_c.display_name as client_name,
                e.email as editor_email,
                ep.display_name as editor_name
            FROM projects p
            JOIN users c ON p.client_id = c.id
            LEFT JOIN user_profiles up_c ON c.id = up_c.user_id
            LEFT JOIN users e ON p.editor_id = e.id
            LEFT JOIN editor_profiles ep ON e.id = ep.user_id
            WHERE 1=1
        ";
        
        $params = [];
        
        // Filter by role
        if ($userRole === 'CLIENT') {
            $query .= " AND p.client_id = ?";
            $params[] = $userId;
        } elseif ($userRole === 'EDITOR') {
            $query .= " AND (p.editor_id = ? OR p.status = 'OPEN')";
            $params[] = $userId;
        }
        
        // Filter by status
        if (isset($_GET['status'])) {
            $query .= " AND p.status = ?";
            $params[] = $_GET['status'];
        }
        
        // Search
        if (isset($_GET['search'])) {
            $search = '%' . $_GET['search'] . '%';
            $query .= " AND (p.title LIKE ? OR p.description LIKE ?)";
            $params[] = $search;
            $params[] = $search;
        }
        
        // Order
        $orderBy = $_GET['orderBy'] ?? 'created_at';
        $orderDir = $_GET['orderDir'] ?? 'DESC';
        $query .= " ORDER BY p.$orderBy $orderDir";
        
        // Pagination
        $page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
        $limit = isset($_GET['limit']) ? min(50, (int)$_GET['limit']) : 20;
        $offset = ($page - 1) * $limit;
        
        $query .= " LIMIT ? OFFSET ?";
        $params[] = $limit;
        $params[] = $offset;
        
        $stmt = $db->prepare($query);
        $stmt->execute($params);
        $projects = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        sendJSON(['success' => true, 'data' => $projects]);
        
    } catch (Exception $e) {
        error_log("List projects error: " . $e->getMessage());
        sendJSON(['success' => false, 'message' => 'Error fetching projects'], 500);
    }
}

/**
 * POST - Create project
 */
function handlePostRequest($userId, $userRole, $input) {
    if ($input['action'] !== 'create') {
        sendJSON(['error' => 'Invalid action'], 400);
        return;
    }
    
    if ($userRole !== 'CLIENT') {
        sendJSON(['error' => 'Only clients can create projects'], 403);
        return;
    }
    
    $db = getDatabase();
    
    try {
        // Validate required fields
        $required = ['title', 'description', 'specialty', 'budget_min', 'budget_max', 'deadline'];
        foreach ($required as $field) {
            if (!isset($input[$field]) || empty($input[$field])) {
                sendJSON(['success' => false, 'message' => "Field $field is required"], 400);
                return;
            }
        }
        
        $db->beginTransaction();
        
        // Create project
        $stmt = $db->prepare("
            INSERT INTO projects (
                client_id, title, description, video_specialty,
                budget_type, budget_min, budget_max, deadline,
                aspect_ratio, video_duration_min, video_duration_max,
                preferred_software, requirements_document, reference_urls
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            RETURNING id
        ");
        
        $stmt->execute([
            $userId,
            $input['title'],
            $input['description'],
            $input['specialty'],
            $input['budget_type'] ?? 'fixed',
            $input['budget_min'],
            $input['budget_max'],
            $input['deadline'],
            $input['aspect_ratio'] ?? '16:9',
            $input['duration_min'] ?? null,
            $input['duration_max'] ?? null,
            $input['preferred_software'] ?? null,
            $input['additional_notes'] ?? null,
            isset($input['reference_url']) ? json_encode($input['reference_url']) : null
        ]);
        
        $projectId = $stmt->fetchColumn();
        
        // Add uploaded files as assets
        if (isset($input['uploaded_files']) && is_array($input['uploaded_files'])) {
            $stmt = $db->prepare("
                INSERT INTO project_assets (project_id, uploader_id, file_name, file_url, file_type)
                VALUES (?, ?, ?, ?, 'REFERENCE')
            ");
            
            foreach ($input['uploaded_files'] as $fileUrl) {
                $fileName = basename($fileUrl);
                $stmt->execute([$projectId, $userId, $fileName, $fileUrl]);
            }
        }
        
        $db->commit();
        
        // Send notification to matching editors
        notifyMatchingEditors($db, $projectId, $input['specialty']);
        
        sendJSON([
            'success' => true,
            'message' => 'Project created successfully',
            'project_id' => $projectId
        ], 201);
        
    } catch (Exception $e) {
        $db->rollBack();
        error_log("Create project error: " . $e->getMessage());
        sendJSON(['success' => false, 'message' => 'Error creating project'], 500);
    }
}

/**
 * PUT - Update project
 */
function handlePutRequest($userId, $userRole, $input) {
    if (!isset($input['project_id'])) {
        sendJSON(['error' => 'Project ID required'], 400);
        return;
    }
    
    $db = getDatabase();
    
    try {
        // Check ownership
        $stmt = $db->prepare("SELECT client_id, editor_id, status FROM projects WHERE id = ?");
        $stmt->execute([$input['project_id']]);
        $project = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$project) {
            sendJSON(['success' => false, 'message' => 'Project not found'], 404);
            return;
        }
        
        // Check permission
        if ($userRole === 'CLIENT' && $project['client_id'] !== $userId) {
            sendJSON(['error' => 'Access denied'], 403);
            return;
        }
        
        if ($userRole === 'EDITOR' && $project['editor_id'] !== $userId) {
            sendJSON(['error' => 'Access denied'], 403);
            return;
        }
        
        // Build update query
        $updates = [];
        $params = [];
        
        $allowedFields = ['title', 'description', 'budget_min', 'budget_max', 'deadline', 'status'];
        
        foreach ($allowedFields as $field) {
            if (isset($input[$field])) {
                $updates[] = "$field = ?";
                $params[] = $input[$field];
            }
        }
        
        if (empty($updates)) {
            sendJSON(['error' => 'No fields to update'], 400);
            return;
        }
        
        $params[] = $input['project_id'];
        $query = "UPDATE projects SET " . implode(', ', $updates) . " WHERE id = ?";
        
        $stmt = $db->prepare($query);
        $stmt->execute($params);
        
        sendJSON(['success' => true, 'message' => 'Project updated']);
        
    } catch (Exception $e) {
        error_log("Update project error: " . $e->getMessage());
        sendJSON(['success' => false, 'message' => 'Error updating project'], 500);
    }
}

/**
 * DELETE - Delete project
 */
function handleDeleteRequest($userId, $userRole, $input) {
    if (!isset($input['project_id'])) {
        sendJSON(['error' => 'Project ID required'], 400);
        return;
    }
    
    if ($userRole !== 'CLIENT' && $userRole !== 'ADMIN') {
        sendJSON(['error' => 'Permission denied'], 403);
        return;
    }
    
    $db = getDatabase();
    
    try {
        // Check ownership
        if ($userRole === 'CLIENT') {
            $stmt = $db->prepare("SELECT client_id FROM projects WHERE id = ?");
            $stmt->execute([$input['project_id']]);
            $project = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$project || $project['client_id'] !== $userId) {
                sendJSON(['error' => 'Access denied'], 403);
                return;
            }
        }
        
        // Soft delete
        $stmt = $db->prepare("UPDATE projects SET deleted_at = NOW() WHERE id = ?");
        $stmt->execute([$input['project_id']]);
        
        sendJSON(['success' => true, 'message' => 'Project deleted']);
        
    } catch (Exception $e) {
        error_log("Delete project error: " . $e->getMessage());
        sendJSON(['success' => false, 'message' => 'Error deleting project'], 500);
    }
}

/**
 * Helper: Notify matching editors
 */
function notifyMatchingEditors($db, $projectId, $specialty) {
    try {
        $stmt = $db->prepare("
            SELECT user_id FROM editor_profiles 
            WHERE ? = ANY(specialties) AND available_for_hire = TRUE
        ");
        $stmt->execute([$specialty]);
        $editors = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        $stmt = $db->prepare("
            INSERT INTO notifications (user_id, type, title, message, related_id, related_type)
            VALUES (?, 'new_project', 'Novo Projeto Disponível', ?, ?, 'project')
        ");
        
        foreach ($editors as $editorId) {
            $stmt->execute([
                $editorId,
                "Um novo projeto na categoria $specialty está disponível!",
                $projectId
            ]);
        }
    } catch (Exception $e) {
        error_log("Notify editors error: " . $e->getMessage());
    }
}

function sendJSON($data, $statusCode = 200) {
    http_response_code($statusCode);
    echo json_encode($data, JSON_PRETTY_PRINT);
    exit;
}

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
