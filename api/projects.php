<?php
/**
 * Projects API - FRAMES Platform
 * Complete CRUD with proper error handling
 */

// Error handlers
set_error_handler(function ($severity, $message, $file, $line) {
    if (error_reporting() & $severity) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => "PHP Error: $message"]);
        exit;
    }
});

set_exception_handler(function ($exception) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Exception: ' . $exception->getMessage()]);
    exit;
});

setCorsHeaders();
header('Content-Type: application/json');

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/auth.php';

$method = $_SERVER['REQUEST_METHOD'];
$input = json_decode(file_get_contents('php://input'), true) ?: [];
$action = $input['action'] ?? $_GET['action'] ?? null;

// Authentication
$token = str_replace('Bearer ', '', $_SERVER['HTTP_AUTHORIZATION'] ?? '');
$db = getDatabase();
$auth = new Auth($db);
$authResult = $auth->verifyToken($token);

if (!$authResult['success']) {
    sendJSON(['success' => false, 'message' => 'Unauthorized'], 401);
}

$userId = $authResult['data']->user_id;
$userRole = $authResult['data']->role;

// ==================== ROUTING ====================
switch ($method) {
    case 'GET':
        if ($action === 'stats') {
            getProjectStats($db, $userId, $userRole);
        } elseif (isset($_GET['id'])) {
            getSingleProject($db, $_GET['id'], $userId, $userRole);
        } else {
            listProjects($db, $userId, $userRole);
        }
        break;
        
    case 'POST':
        if ($action === 'create') {
            createProject($db, $userId, $userRole, $input);
        } else {
            sendJSON(['success' => false, 'message' => 'Invalid action. Use action=create'], 400);
        }
        break;
        
    case 'PUT':
        updateProject($db, $userId, $userRole, $input);
        break;
        
    case 'DELETE':
        deleteProject($db, $userId, $userRole, $input);
        break;
        
    default:
        sendJSON(['success' => false, 'message' => 'Method not allowed'], 405);
}

// ==================== FUNCTIONS ====================

function getProjectStats($db, $userId, $userRole) {
    try {
        $whereClause = $userRole === 'CLIENT' ? 'client_id = ?' : 'editor_id = ?';
        
        $stmt = $db->prepare("
            SELECT 
                CAST(COUNT(CASE WHEN status IN ('OPEN', 'IN_PROGRESS', 'IN_REVIEW') THEN 1 END) AS INTEGER) as active,
                CAST(COUNT(CASE WHEN status = 'COMPLETED' THEN 1 END) AS INTEGER) as completed,
                CAST(COALESCE(SUM(CASE WHEN status = 'COMPLETED' THEN budget_max ELSE 0 END), 0) AS NUMERIC) as spent,
                CAST(COUNT(DISTINCT editor_id) FILTER (WHERE editor_id IS NOT NULL) AS INTEGER) as editors
            FROM projects
            WHERE $whereClause
        ");
        $stmt->execute([$userId]);
        $stats = $stmt->fetch(PDO::FETCH_ASSOC);
        
        sendJSON(['success' => true, 'data' => $stats ?: [
            'active' => 0,
            'completed' => 0,
            'spent' => 0,
            'editors' => 0
        ]]);
        
    } catch (Exception $e) {
        error_log("Stats error: " . $e->getMessage());
        sendJSON(['success' => false, 'message' => 'Error fetching stats'], 500);
    }
}

function getSingleProject($db, $projectId, $userId, $userRole) {
    try {
        $stmt = $db->prepare("
            SELECT p.* FROM projects p WHERE p.id = ?
        ");
        $stmt->execute([$projectId]);
        $project = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$project) {
            sendJSON(['success' => false, 'message' => 'Project not found'], 404);
        }
        
        // Permission check
        if ($userRole === 'CLIENT' && $project['client_id'] !== $userId) {
            sendJSON(['success' => false, 'message' => 'Access denied'], 403);
        }
        
        sendJSON(['success' => true, 'data' => $project]);
        
    } catch (Exception $e) {
        error_log("Get project error: " . $e->getMessage());
        sendJSON(['success' => false, 'message' => 'Error fetching project'], 500);
    }
}

function listProjects($db, $userId, $userRole) {
    try {
        $query = "SELECT * FROM projects WHERE 1=1";
        $params = [];
        
        if ($userRole === 'CLIENT') {
            $query .= " AND client_id = ?";
            $params[] = $userId;
        } elseif ($userRole === 'EDITOR') {
            $query .= " AND (editor_id = ? OR status = 'OPEN')";
            $params[] = $userId;
        }
        
        $query .= " ORDER BY created_at DESC LIMIT 20";
        
        $stmt = $db->prepare($query);
        $stmt->execute($params);
        $projects = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        sendJSON(['success' => true, 'data' => $projects]);
        
    } catch (Exception $e) {
        error_log("List projects error: " . $e->getMessage());
        sendJSON(['success' => false, 'message' => 'Error listing projects'], 500);
    }
}

function createProject($db, $userId, $userRole, $input) {
    if ($userRole !== 'CLIENT') {
        sendJSON(['success' => false, 'message' => 'Only clients can create projects'], 403);
    }
    
    try {
        // Validate required fields
        $required = ['title', 'description', 'specialty', 'deadline'];
        foreach ($required as $field) {
            if (empty($input[$field])) {
                sendJSON(['success' => false, 'message' => "Field '$field' is required"], 400);
            }
        }
        
        // Budget handling
        $budgetMin = $input['budget_min'] ?? $input['budget'] ?? 500;
        $budgetMax = $input['budget_max'] ?? $input['budget'] ?? 1000;
        
        $db->beginTransaction();
        
        $stmt = $db->prepare("
            INSERT INTO projects (
                client_id, title, description, video_specialty,
                budget_type, budget_min, budget_max, deadline, status,
                aspect_ratio, video_duration_min, video_duration_max
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'OPEN', ?, ?, ?)
            RETURNING id
        ");
        
        $stmt->execute([
            $userId,
            $input['title'],
            $input['description'],
            strtoupper($input['specialty']),
            $input['budget_type'] ?? 'fixed',
            $budgetMin,
            $budgetMax,
            $input['deadline'],
            $input['aspect_ratio'] ?? '16:9',
            $input['duration_min'] ?? null,
            $input['duration_max'] ?? null
        ]);
        
        $projectId = $stmt->fetchColumn();
        $db->commit();
        
        sendJSON([
            'success' => true,
            'message' => 'Projeto criado com sucesso!',
            'project_id' => $projectId
        ], 201);
        
    } catch (Exception $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        error_log("Create project error: " . $e->getMessage());
        sendJSON(['success' => false, 'message' => 'Erro ao criar projeto: ' . $e->getMessage()], 500);
    }
}

function updateProject($db, $userId, $userRole, $input) {
    try {
        if (empty($input['project_id'])) {
            sendJSON(['success' => false, 'message' => 'Project ID required'], 400);
        }
        
        $stmt = $db->prepare("SELECT client_id FROM projects WHERE id = ?");
        $stmt->execute([$input['project_id']]);
        $project = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$project) {
            sendJSON(['success' => false, 'message' => 'Project not found'], 404);
        }
        
        if ($userRole === 'CLIENT' && $project['client_id'] !== $userId) {
            sendJSON(['success' => false, 'message' => 'Access denied'], 403);
        }
        
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
            sendJSON(['success' => false, 'message' => 'No fields to update'], 400);
        }
        
        $params[] = $input['project_id'];
        $query = "UPDATE projects SET " . implode(', ', $updates) . " WHERE id = ?";
        
        $stmt = $db->prepare($query);
        $stmt->execute($params);
        
        sendJSON(['success' => true, 'message' => 'Project updated']);
        
    } catch (Exception $e) {
        error_log("Update error: " . $e->getMessage());
        sendJSON(['success' => false, 'message' => 'Error updating project'], 500);
    }
}

function deleteProject($db, $userId, $userRole, $input) {
    try {
        if (empty($input['project_id'])) {
            sendJSON(['success' => false, 'message' => 'Project ID required'], 400);
        }
        
        if ($userRole !== 'CLIENT' && $userRole !== 'ADMIN') {
            sendJSON(['success' => false, 'message' => 'Permission denied'], 403);
        }
        
        $stmt = $db->prepare("UPDATE projects SET status = 'CANCELLED' WHERE id = ? AND client_id = ?");
        $stmt->execute([$input['project_id'], $userId]);
        
        sendJSON(['success' => true, 'message' => 'Project deleted']);
        
    } catch (Exception $e) {
        error_log("Delete error: " . $e->getMessage());
        sendJSON(['success' => false, 'message' => 'Error deleting project'], 500);
    }
}

function sendJSON($data, $statusCode = 200) {
    http_response_code($statusCode);
    echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
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