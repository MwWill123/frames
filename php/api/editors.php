<?php
/**
 * Editors API - PostgreSQL Version
 */

require_once '../config/config.php';  // ✅ Usar seu config.php atualizado

// Set CORS headers
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Content-Type: application/json');

// Handle OPTIONS
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Get database connection
$db = getDB();

// Route the request
switch ($_SERVER['REQUEST_METHOD']) {
    case 'GET':
        handleGetRequest($db);
        break;
    case 'POST':
        handlePostRequest($db);
        break;
    default:
        sendJSON(['error' => 'Método não permitido'], 405);
}

function handleGetRequest($db) {
    try {
        // Check if specific editor ID is requested
        if (isset($_GET['id'])) {
            $id = (int)$_GET['id'];
            $stmt = $db->prepare("
                SELECT 
                    ep.*,
                    u.email,
                    u.created_at,
                    u.last_login
                FROM editor_profiles ep
                JOIN users u ON ep.user_id = u.id
                WHERE ep.user_id = :id AND u.is_active = true
            ");
            $stmt->execute([':id' => $id]);
            $editor = $stmt->fetch();
            
            if ($editor) {
                sendJSON(['success' => true, 'data' => $editor]);
            } else {
                sendJSON(['error' => 'Editor não encontrado'], 404);
            }
        } else {
            // Fetch all active editors
            $query = "
                SELECT 
                    ep.*,
                    u.email,
                    u.created_at
                FROM editor_profiles ep
                JOIN users u ON ep.user_id = u.id
                WHERE u.is_active = true AND u.role_id = (SELECT id FROM roles WHERE role_name = 'EDITOR')
            ";
            
            $params = [];
            
            // Apply filters
            if (isset($_GET['featured']) && $_GET['featured'] === 'true') {
                $query .= " AND ep.featured = true";
            }
            
            if (isset($_GET['specialty'])) {
                $query .= " AND :specialty = ANY(ep.specialties)";
                $params[':specialty'] = $_GET['specialty'];
            }
            
            if (isset($_GET['software'])) {
                $query .= " AND ep.primary_software = :software";
                $params[':software'] = $_GET['software'];
            }
            
            // Order
            $orderBy = $_GET['orderBy'] ?? 'ep.created_at';
            $orderDir = $_GET['orderDir'] ?? 'DESC';
            $query .= " ORDER BY $orderBy $orderDir";
            
            // Pagination
            $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 20;
            $offset = isset($_GET['offset']) ? (int)$_GET['offset'] : 0;
            $query .= " LIMIT :limit OFFSET :offset";
            $params[':limit'] = $limit;
            $params[':offset'] = $offset;
            
            $stmt = $db->prepare($query);
            foreach ($params as $key => $value) {
                $stmt->bindValue($key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
            }
            $stmt->execute();
            $editors = $stmt->fetchAll();
            
            // Get total count
            $countQuery = "SELECT COUNT(*) FROM editor_profiles ep JOIN users u ON ep.user_id = u.id WHERE u.is_active = true";
            $countStmt = $db->prepare($countQuery);
            $countStmt->execute();
            $total = $countStmt->fetchColumn();
            
            sendJSON([
                'success' => true,
                'data' => $editors,
                'total' => $total,
                'limit' => $limit,
                'offset' => $offset
            ]);
        }
    } catch (Exception $e) {
        error_log("Get editors error: " . $e->getMessage());
        sendJSON(['error' => 'Erro ao buscar editores'], 500);
    }
}

function handlePostRequest($db) {
    // This would be for creating/updating editors
    // You need to implement proper authentication first
    sendJSON(['success' => false, 'message' => 'Endpoint em desenvolvimento'], 501);
}

function sendJSON($data, $statusCode = 200) {
    http_response_code($statusCode);
    echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    exit;
}
?>