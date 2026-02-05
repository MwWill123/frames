<?php
/**
 * File Upload API with Chunked Upload Support
 * FRAMES Platform
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/auth.php';

header('Content-Type: application/json');
setCorsHeaders();

// Configuration
define('UPLOAD_DIR', __DIR__ . '/../uploads/');
define('MAX_FILE_SIZE', 10 * 1024 * 1024 * 1024); // 10GB
define('ALLOWED_VIDEO_TYPES', ['video/mp4', 'video/quicktime', 'video/x-msvideo', 'video/x-matroska']);
define('ALLOWED_IMAGE_TYPES', ['image/jpeg', 'image/png', 'image/gif', 'image/webp']);
define('ALLOWED_DOC_TYPES', ['application/pdf', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document']);

// Authenticate user
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

// Handle upload
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    handleUpload($userId);
} else {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
}

/**
 * Handle file upload (chunked or regular)
 */
function handleUpload($userId) {
    $db = getDatabase();
    
    // Check if chunked upload
    $isChunked = isset($_POST['chunk']) && isset($_POST['chunks']);
    
    if ($isChunked) {
        handleChunkedUpload($userId, $db);
    } else {
        handleRegularUpload($userId, $db);
    }
}

/**
 * Handle regular (single file) upload
 */
function handleRegularUpload($userId, $db) {
    try {
        if (!isset($_FILES['file'])) {
            sendJSON(['success' => false, 'message' => 'No file provided'], 400);
            return;
        }
        
        $file = $_FILES['file'];
        
        // Validate file
        if ($file['error'] !== UPLOAD_ERR_OK) {
            sendJSON(['success' => false, 'message' => 'Upload error: ' . $file['error']], 400);
            return;
        }
        
        if ($file['size'] > MAX_FILE_SIZE) {
            sendJSON(['success' => false, 'message' => 'File too large'], 400);
            return;
        }
        
        $mimeType = mime_content_type($file['tmp_name']);
        if (!validateFileType($mimeType)) {
            sendJSON(['success' => false, 'message' => 'Invalid file type'], 400);
            return;
        }
        
        // Generate unique filename
        $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
        $uniqueId = uniqid() . '_' . time();
        $fileName = $uniqueId . '.' . $extension;
        $uploadPath = UPLOAD_DIR . $fileName;
        
        // Move uploaded file
        if (!move_uploaded_file($file['tmp_name'], $uploadPath)) {
            sendJSON(['success' => false, 'message' => 'Failed to save file'], 500);
            return;
        }
        
        // Get file metadata
        $fileSize = filesize($uploadPath);
        $fileType = $_POST['type'] ?? 'REFERENCE';
        
        // If video, extract metadata and queue for processing
        $metadata = [];
        if (isVideo($mimeType)) {
            $metadata = extractVideoMetadata($uploadPath);
            queueVideoProcessing($uniqueId, $uploadPath, $userId);
        }
        
        // Save to database
        $projectId = $_POST['project_id'] ?? null;
        
        $stmt = $db->prepare("
            INSERT INTO project_assets (
                project_id, uploader_id, file_name, file_url, file_size_bytes,
                mime_type, file_type, duration_seconds, resolution, processing_status
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            RETURNING id
        ");
        
        $fileUrl = '/uploads/' . $fileName;
        
        $stmt->execute([
            $projectId,
            $userId,
            $file['name'],
            $fileUrl,
            $fileSize,
            $mimeType,
            $fileType,
            $metadata['duration'] ?? null,
            $metadata['resolution'] ?? null,
            isVideo($mimeType) ? 'processing' : 'ready'
        ]);
        
        $assetId = $stmt->fetchColumn();
        
        sendJSON([
            'success' => true,
            'message' => 'File uploaded successfully',
            'asset_id' => $assetId,
            'file_url' => $fileUrl,
            'file_name' => $fileName,
            'file_size' => $fileSize,
            'processing' => isVideo($mimeType)
        ], 201);
        
    } catch (Exception $e) {
        error_log("Upload error: " . $e->getMessage());
        sendJSON(['success' => false, 'message' => 'Upload failed'], 500);
    }
}

/**
 * Handle chunked upload (for large files)
 */
function handleChunkedUpload($userId, $db) {
    try {
        $chunk = (int)$_POST['chunk'];
        $chunks = (int)$_POST['chunks'];
        $fileName = $_POST['fileName'];
        $uniqueId = $_POST['uploadId'] ?? uniqid() . '_' . time();
        
        if (!isset($_FILES['file'])) {
            sendJSON(['success' => false, 'message' => 'No chunk provided'], 400);
            return;
        }
        
        $file = $_FILES['file'];
        
        // Create chunks directory
        $chunksDir = UPLOAD_DIR . 'chunks/' . $uniqueId . '/';
        if (!is_dir($chunksDir)) {
            mkdir($chunksDir, 0755, true);
        }
        
        // Save chunk
        $chunkPath = $chunksDir . $chunk;
        if (!move_uploaded_file($file['tmp_name'], $chunkPath)) {
            sendJSON(['success' => false, 'message' => 'Failed to save chunk'], 500);
            return;
        }
        
        // Check if all chunks uploaded
        $uploadedChunks = glob($chunksDir . '*');
        
        if (count($uploadedChunks) === $chunks) {
            // Merge chunks
            $extension = pathinfo($fileName, PATHINFO_EXTENSION);
            $finalFileName = $uniqueId . '.' . $extension;
            $finalPath = UPLOAD_DIR . $finalFileName;
            
            $finalFile = fopen($finalPath, 'wb');
            
            for ($i = 0; $i < $chunks; $i++) {
                $chunkData = file_get_contents($chunksDir . $i);
                fwrite($finalFile, $chunkData);
            }
            
            fclose($finalFile);
            
            // Clean up chunks
            array_map('unlink', $uploadedChunks);
            rmdir($chunksDir);
            
            // Get file info
            $fileSize = filesize($finalPath);
            $mimeType = mime_content_type($finalPath);
            
            // Extract metadata and queue processing
            $metadata = [];
            if (isVideo($mimeType)) {
                $metadata = extractVideoMetadata($finalPath);
                queueVideoProcessing($uniqueId, $finalPath, $userId);
            }
            
            // Save to database
            $projectId = $_POST['project_id'] ?? null;
            $fileType = $_POST['type'] ?? 'DRAFT_VERSION';
            
            $stmt = $db->prepare("
                INSERT INTO project_assets (
                    project_id, uploader_id, file_name, file_url, file_size_bytes,
                    mime_type, file_type, duration_seconds, resolution, processing_status
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                RETURNING id
            ");
            
            $fileUrl = '/uploads/' . $finalFileName;
            
            $stmt->execute([
                $projectId,
                $userId,
                $fileName,
                $fileUrl,
                $fileSize,
                $mimeType,
                $fileType,
                $metadata['duration'] ?? null,
                $metadata['resolution'] ?? null,
                'processing'
            ]);
            
            $assetId = $stmt->fetchColumn();
            
            sendJSON([
                'success' => true,
                'message' => 'Upload complete',
                'asset_id' => $assetId,
                'file_url' => $fileUrl,
                'complete' => true
            ], 201);
        } else {
            // More chunks needed
            sendJSON([
                'success' => true,
                'message' => 'Chunk received',
                'uploadId' => $uniqueId,
                'chunk' => $chunk,
                'complete' => false,
                'progress' => round((count($uploadedChunks) / $chunks) * 100, 2)
            ]);
        }
        
    } catch (Exception $e) {
        error_log("Chunked upload error: " . $e->getMessage());
        sendJSON(['success' => false, 'message' => 'Upload failed'], 500);
    }
}

/**
 * Extract video metadata using FFmpeg
 */
function extractVideoMetadata($filePath) {
    $metadata = [];
    
    try {
        // Use ffprobe to get video info
        $command = sprintf(
            'ffprobe -v quiet -print_format json -show_format -show_streams %s 2>&1',
            escapeshellarg($filePath)
        );
        
        $output = shell_exec($command);
        $data = json_decode($output, true);
        
        if ($data) {
            // Get duration
            if (isset($data['format']['duration'])) {
                $metadata['duration'] = (int)$data['format']['duration'];
            }
            
            // Get resolution
            foreach ($data['streams'] as $stream) {
                if ($stream['codec_type'] === 'video') {
                    $metadata['resolution'] = $stream['width'] . 'x' . $stream['height'];
                    $metadata['codec'] = $stream['codec_name'];
                    $metadata['bitrate'] = $stream['bit_rate'] ?? null;
                    break;
                }
            }
        }
    } catch (Exception $e) {
        error_log("Metadata extraction error: " . $e->getMessage());
    }
    
    return $metadata;
}

/**
 * Queue video for processing
 */
function queueVideoProcessing($uniqueId, $filePath, $userId) {
    $db = getDatabase();
    
    try {
        // Add to processing queue
        $stmt = $db->prepare("
            INSERT INTO video_processing_queue (
                unique_id, file_path, user_id, status, created_at
            ) VALUES (?, ?, ?, 'pending', NOW())
        ");
        
        $stmt->execute([$uniqueId, $filePath, $userId]);
        
        // Trigger background worker (you can use a queue system like Redis/RabbitMQ)
        // For now, we'll use a simple approach
        exec("php " . __DIR__ . "/../workers/video-transcode.php {$uniqueId} > /dev/null 2>&1 &");
        
    } catch (Exception $e) {
        error_log("Queue processing error: " . $e->getMessage());
    }
}

/**
 * Validate file type
 */
function validateFileType($mimeType) {
    $allowed = array_merge(ALLOWED_VIDEO_TYPES, ALLOWED_IMAGE_TYPES, ALLOWED_DOC_TYPES);
    return in_array($mimeType, $allowed);
}

/**
 * Check if file is video
 */
function isVideo($mimeType) {
    return in_array($mimeType, ALLOWED_VIDEO_TYPES);
}

function sendJSON($data, $statusCode = 200) {
    http_response_code($statusCode);
    echo json_encode($data, JSON_PRETTY_PRINT);
    exit;
}

function setCorsHeaders() {
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization');
    
    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        http_response_code(200);
        exit;
    }
}
?>
