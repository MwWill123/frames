<?php
/**
 * Editors API
 * FRAMES - Video Editor Platform
 * Handles CRUD operations for video editors
 */

require_once '../php/config.php';

// Set CORS headers
setCorsHeaders();

// Get request method
$method = $_SERVER['REQUEST_METHOD'];

// Route the request
switch ($method) {
    case 'GET':
        handleGetRequest();
        break;
    case 'POST':
        handlePostRequest();
        break;
    case 'PUT':
        handlePutRequest();
        break;
    case 'DELETE':
        handleDeleteRequest();
        break;
    default:
        sendJSON(['error' => 'Method not allowed'], 405);
}

/**
 * Handle GET requests - Fetch editors
 */
function handleGetRequest() {
    $db = getDB();
    
    // Check if specific editor ID is requested
    if (isset($_GET['id'])) {
        $id = (int)$_GET['id'];
        $stmt = $db->prepare("SELECT * FROM editors WHERE id = ?");
        $stmt->execute([$id]);
        $editor = $stmt->fetch();
        
        if ($editor) {
            sendJSON(['success' => true, 'data' => $editor]);
        } else {
            sendJSON(['error' => 'Editor not found'], 404);
        }
    } else {
        // Fetch all editors with optional filters
        $query = "SELECT * FROM editors WHERE 1=1";
        $params = [];
        
        // Apply search filter
        if (isset($_GET['search']) && !empty($_GET['search'])) {
            $search = '%' . $_GET['search'] . '%';
            $query .= " AND (name LIKE ? OR title LIKE ? OR software LIKE ?)";
            $params = array_merge($params, [$search, $search, $search]);
        }
        
        // Apply format filter
        if (isset($_GET['format']) && !empty($_GET['format'])) {
            $query .= " AND format = ?";
            $params[] = $_GET['format'];
        }
        
        // Apply software filter
        if (isset($_GET['software']) && !empty($_GET['software'])) {
            $query .= " AND software = ?";
            $params[] = $_GET['software'];
        }
        
        // Apply featured filter
        if (isset($_GET['featured'])) {
            $query .= " AND featured = ?";
            $params[] = $_GET['featured'] ? 1 : 0;
        }
        
        // Add ordering
        $orderBy = isset($_GET['orderBy']) ? $_GET['orderBy'] : 'created_at';
        $orderDir = isset($_GET['orderDir']) && $_GET['orderDir'] === 'ASC' ? 'ASC' : 'DESC';
        $query .= " ORDER BY {$orderBy} {$orderDir}";
        
        // Add pagination
        $page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
        $perPage = isset($_GET['perPage']) ? max(1, min(100, (int)$_GET['perPage'])) : 20;
        $offset = ($page - 1) * $perPage;
        
        $query .= " LIMIT ? OFFSET ?";
        $params[] = $perPage;
        $params[] = $offset;
        
        // Execute query
        $stmt = $db->prepare($query);
        $stmt->execute($params);
        $editors = $stmt->fetchAll();
        
        // Get total count for pagination
        $countQuery = "SELECT COUNT(*) as total FROM editors WHERE 1=1";
        $countParams = array_slice($params, 0, -2); // Remove LIMIT and OFFSET params
        
        if (isset($_GET['search']) && !empty($_GET['search'])) {
            $search = '%' . $_GET['search'] . '%';
            $countQuery .= " AND (name LIKE ? OR title LIKE ? OR software LIKE ?)";
        }
        if (isset($_GET['format']) && !empty($_GET['format'])) {
            $countQuery .= " AND format = ?";
        }
        if (isset($_GET['software']) && !empty($_GET['software'])) {
            $countQuery .= " AND software = ?";
        }
        if (isset($_GET['featured'])) {
            $countQuery .= " AND featured = ?";
        }
        
        $countStmt = $db->prepare($countQuery);
        $countStmt->execute($countParams);
        $total = $countStmt->fetch()['total'];
        
        sendJSON([
            'success' => true,
            'data' => $editors,
            'pagination' => [
                'page' => $page,
                'perPage' => $perPage,
                'total' => $total,
                'totalPages' => ceil($total / $perPage)
            ]
        ]);
    }
}

/**
 * Handle POST requests - Create new editor
 */
function handlePostRequest() {
    $db = getDB();
    
    // Get POST data
    $data = json_decode(file_get_contents('php://input'), true);
    
    // Validate required fields
    $required = ['name', 'title', 'software'];
    foreach ($required as $field) {
        if (!isset($data[$field]) || empty($data[$field])) {
            sendJSON(['error' => "Field '{$field}' is required"], 400);
        }
    }
    
    // Sanitize inputs
    $name = sanitizeInput($data['name']);
    $title = sanitizeInput($data['title']);
    $software = sanitizeInput($data['software']);
    $format = isset($data['format']) ? sanitizeInput($data['format']) : 'general';
    $rating = isset($data['rating']) ? (int)$data['rating'] : 0;
    $reviews = isset($data['reviews']) ? (int)$data['reviews'] : 0;
    $image = isset($data['image']) ? sanitizeInput($data['image']) : '';
    $featured = isset($data['featured']) ? (int)$data['featured'] : 0;
    $description = isset($data['description']) ? sanitizeInput($data['description']) : '';
    
    // Insert into database
    $stmt = $db->prepare("
        INSERT INTO editors (name, title, software, format, rating, reviews, image, featured, description, created_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
    ");
    
    try {
        $stmt->execute([$name, $title, $software, $format, $rating, $reviews, $image, $featured, $description]);
        $newId = $db->lastInsertId();
        
        sendJSON([
            'success' => true,
            'message' => 'Editor created successfully',
            'id' => $newId
        ], 201);
    } catch (PDOException $e) {
        sendJSON(['error' => 'Failed to create editor: ' . $e->getMessage()], 500);
    }
}

/**
 * Handle PUT requests - Update editor
 */
function handlePutRequest() {
    $db = getDB();
    
    // Get PUT data
    $data = json_decode(file_get_contents('php://input'), true);
    
    // Validate ID
    if (!isset($data['id']) || empty($data['id'])) {
        sendJSON(['error' => 'Editor ID is required'], 400);
    }
    
    $id = (int)$data['id'];
    
    // Check if editor exists
    $stmt = $db->prepare("SELECT id FROM editors WHERE id = ?");
    $stmt->execute([$id]);
    if (!$stmt->fetch()) {
        sendJSON(['error' => 'Editor not found'], 404);
    }
    
    // Build update query dynamically
    $updates = [];
    $params = [];
    
    $allowedFields = ['name', 'title', 'software', 'format', 'rating', 'reviews', 'image', 'featured', 'description'];
    
    foreach ($allowedFields as $field) {
        if (isset($data[$field])) {
            $updates[] = "{$field} = ?";
            $params[] = in_array($field, ['rating', 'reviews', 'featured']) 
                ? (int)$data[$field] 
                : sanitizeInput($data[$field]);
        }
    }
    
    if (empty($updates)) {
        sendJSON(['error' => 'No fields to update'], 400);
    }
    
    $params[] = $id;
    $query = "UPDATE editors SET " . implode(', ', $updates) . ", updated_at = NOW() WHERE id = ?";
    
    try {
        $stmt = $db->prepare($query);
        $stmt->execute($params);
        
        sendJSON([
            'success' => true,
            'message' => 'Editor updated successfully'
        ]);
    } catch (PDOException $e) {
        sendJSON(['error' => 'Failed to update editor: ' . $e->getMessage()], 500);
    }
}

/**
 * Handle DELETE requests - Delete editor
 */
function handleDeleteRequest() {
    $db = getDB();
    
    // Get DELETE data
    $data = json_decode(file_get_contents('php://input'), true);
    
    // Validate ID
    if (!isset($data['id']) || empty($data['id'])) {
        sendJSON(['error' => 'Editor ID is required'], 400);
    }
    
    $id = (int)$data['id'];
    
    // Check if editor exists
    $stmt = $db->prepare("SELECT id, image FROM editors WHERE id = ?");
    $stmt->execute([$id]);
    $editor = $stmt->fetch();
    
    if (!$editor) {
        sendJSON(['error' => 'Editor not found'], 404);
    }
    
    try {
        // Delete from database
        $stmt = $db->prepare("DELETE FROM editors WHERE id = ?");
        $stmt->execute([$id]);
        
        // Delete image file if exists
        if (!empty($editor['image']) && file_exists(UPLOAD_PATH . $editor['image'])) {
            unlink(UPLOAD_PATH . $editor['image']);
        }
        
        sendJSON([
            'success' => true,
            'message' => 'Editor deleted successfully'
        ]);
    } catch (PDOException $e) {
        sendJSON(['error' => 'Failed to delete editor: ' . $e->getMessage()], 500);
    }
}
?>
