<?php
function setCorsHeaders() {
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization');
    
    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        http_response_code(200);
        exit;
    }
}

setCorsHeaders();

header('Content-Type: application/json');

require_once __DIR__ . '/../config/database.php'; // PostgreSQL Supabase

define('UPLOAD_DIR', __DIR__ . '/../uploads/'); // caminho relativo para imagens

$method = $_SERVER['REQUEST_METHOD'];
$input = json_decode(file_get_contents('php://input'), true);

$db = getDatabase();

function sendJSON($data, $statusCode = 200) {
    http_response_code($statusCode);
    echo json_encode($data, JSON_PRETTY_PRINT);
    exit;
}

// GET - List editors (featured + limit for dashboard)
if ($method === 'GET') {
    try {
        $query = "SELECT 
            ep.user_id as id,
            ep.display_name as name,
            ep.bio as title,
            ep.primary_software as software,
            ep.average_rating as rating,
            ep.total_reviews as reviews,
            ep.avatar_url as image,
            ep.featured
        FROM editor_profiles ep
        JOIN users u ON ep.user_id = u.id
        WHERE u.role = 'EDITOR' AND u.is_active = TRUE";

        $params = [];

        if (isset($_GET['featured']) && $_GET['featured'] === 'true') {
            $query .= " AND ep.featured = TRUE";
        }

        if (isset($_GET['limit'])) {
            $query .= " LIMIT ?";
            $params[] = (int)$_GET['limit'];
        } else {
            $query .= " LIMIT 10"; // default
        }

        $stmt = $db->prepare($query);
        $stmt->execute($params);
        $editors = $stmt->fetchAll(PDO::FETCH_ASSOC);

        sendJSON(['success' => true, 'data' => $editors]);
    } catch (Exception $e) {
        sendJSON(['success' => false, 'message' => 'Error loading editors: ' . $e->getMessage()], 500);
    }
}

// POST - Create editor (exemplo básico)
if ($method === 'POST') {
    // Auth + validation aqui se precisar (use require auth.php se quiser)
    // Exemplo simples:
    try {
        $data = $input;
        $userId = $data['user_id']; // do auth
        $stmt = $db->prepare("
            INSERT INTO editor_profiles (user_id, display_name, bio, primary_software, avatar_url, featured)
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $userId,
            $data['display_name'] ?? '',
            $data['bio'] ?? '',
            $data['primary_software'] ?? 'PREMIERE_PRO',
            $data['avatar_url'] ?? '',
            $data['featured'] ?? false
        ]);

        sendJSON(['success' => true, 'message' => 'Editor created']);
    } catch (Exception $e) {
        sendJSON(['success' => false, 'message' => 'Create error: ' . $e->getMessage()], 500);
    }
}

// PUT - Update editor
if ($method === 'PUT') {
    // Similar ao POST
}

// DELETE - Delete editor
if ($method === 'DELETE') {
    try {
        $data = $input;
        $id = (int)$data['id']; // user_id do editor

        // Get image to delete
        $stmt = $db->prepare("SELECT avatar_url as image FROM editor_profiles WHERE user_id = ?");
        $stmt->execute([$id]);
        $editor = $stmt->fetch();

        // Delete from DB
        $stmt = $db->prepare("DELETE FROM editor_profiles WHERE user_id = ?");
        $stmt->execute([$id]);

        // Delete image file
        if (!empty($editor['image']) && file_exists(UPLOAD_DIR . basename($editor['image']))) {
            unlink(UPLOAD_DIR . basename($editor['image']));
        }

        sendJSON(['success' => true, 'message' => 'Editor deleted successfully']);
    } catch (PDOException $e) {
        sendJSON(['success' => false, 'message' => 'Failed to delete editor: ' . $e->getMessage()], 500);
    }
}

// Default
sendJSON(['success' => false, 'message' => 'Method not allowed'], 405);
?>