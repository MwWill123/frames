#!/usr/bin/env php
<?php
/**
 * Video Transcoding Worker
 * FRAMES Platform
 * 
 * This script processes videos using FFmpeg to create web-optimized versions
 * 
 * Usage: php video-transcode.php <unique_id>
 */

require_once __DIR__ . '/../config/database.php';

// Configuration
define('UPLOAD_DIR', __DIR__ . '/../uploads/');
define('PROCESSED_DIR', '/var/www/frames/uploads/processed/');
define('THUMBNAILS_DIR', '/var/www/frames/uploads/thumbnails/');

// Create directories if they don't exist
if (!is_dir(PROCESSED_DIR)) mkdir(PROCESSED_DIR, 0755, true);
if (!is_dir(THUMBNAILS_DIR)) mkdir(THUMBNAILS_DIR, 0755, true);

// Get unique ID from command line
$uniqueId = $argv[1] ?? null;

if (!$uniqueId) {
    echo "Usage: php video-transcode.php <unique_id>\n";
    exit(1);
}

$db = getDatabase();

try {
    // Get processing job
    $stmt = $db->prepare("
        SELECT * FROM video_processing_queue 
        WHERE unique_id = ? AND status = 'pending'
    ");
    $stmt->execute([$uniqueId]);
    $job = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$job) {
        echo "Job not found or already processed\n";
        exit(1);
    }
    
    // Update status to processing
    $stmt = $db->prepare("UPDATE video_processing_queue SET status = 'processing', started_at = NOW() WHERE unique_id = ?");
    $stmt->execute([$uniqueId]);
    
    $inputPath = $job['file_path'];
    $outputBaseName = $uniqueId;
    
    echo "Processing video: $inputPath\n";
    
    // 1. Generate thumbnail
    echo "Generating thumbnail...\n";
    $thumbnailPath = generateThumbnail($inputPath, $outputBaseName);
    
    // 2. Generate animated GIF preview
    echo "Generating GIF preview...\n";
    $gifPath = generateGifPreview($inputPath, $outputBaseName);
    
    // 3. Transcode to 1080p
    echo "Transcoding to 1080p...\n";
    $video1080p = transcodeVideo($inputPath, $outputBaseName, 1080);
    
    // 4. Transcode to 720p
    echo "Transcoding to 720p...\n";
    $video720p = transcodeVideo($inputPath, $outputBaseName, 720);
    
    // 5. Transcode to 480p
    echo "Transcoding to 480p...\n";
    $video480p = transcodeVideo($inputPath, $outputBaseName, 480);
    
    // 6. Generate HLS playlist for adaptive streaming
    echo "Generating HLS playlist...\n";
    $hlsPath = generateHLS($inputPath, $outputBaseName);
    
    // 7. Update asset in database
    $stmt = $db->prepare("
        UPDATE project_assets 
        SET 
            processing_status = 'completed',
            processed_urls = ?,
            updated_at = NOW()
        WHERE file_url LIKE ?
    ");
    
    $processedUrls = json_encode([
        '1080p' => '/uploads/processed/' . basename($video1080p),
        '720p' => '/uploads/processed/' . basename($video720p),
        '480p' => '/uploads/processed/' . basename($video480p),
        'hls' => '/uploads/processed/' . basename($hlsPath),
        'thumbnail' => '/uploads/thumbnails/' . basename($thumbnailPath),
        'gif' => '/uploads/thumbnails/' . basename($gifPath)
    ]);
    
    $stmt->execute([$processedUrls, '%' . $uniqueId . '%']);
    
    // 8. Update processing queue
    $stmt = $db->prepare("
        UPDATE video_processing_queue 
        SET status = 'completed', completed_at = NOW(), processed_urls = ?
        WHERE unique_id = ?
    ");
    $stmt->execute([$processedUrls, $uniqueId]);
    
    // 9. Send notification to user
    sendProcessingCompleteNotification($db, $job['user_id']);
    
    echo "Processing complete!\n";
    exit(0);
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    
    // Update status to failed
    $stmt = $db->prepare("
        UPDATE video_processing_queue 
        SET status = 'failed', error_message = ?, completed_at = NOW()
        WHERE unique_id = ?
    ");
    $stmt->execute([$e->getMessage(), $uniqueId]);
    
    exit(1);
}

/**
 * Generate thumbnail from video
 */
function generateThumbnail($inputPath, $baseName) {
    $outputPath = THUMBNAILS_DIR . $baseName . '_thumb.jpg';
    
    $command = sprintf(
        'ffmpeg -i %s -ss 00:00:01 -vframes 1 -vf "scale=1280:-1" -q:v 2 %s 2>&1',
        escapeshellarg($inputPath),
        escapeshellarg($outputPath)
    );
    
    exec($command, $output, $returnCode);
    
    if ($returnCode !== 0) {
        throw new Exception("Thumbnail generation failed: " . implode("\n", $output));
    }
    
    return $outputPath;
}

/**
 * Generate animated GIF preview (first 3 seconds)
 */
function generateGifPreview($inputPath, $baseName) {
    $outputPath = THUMBNAILS_DIR . $baseName . '_preview.gif';
    
    $command = sprintf(
        'ffmpeg -i %s -t 3 -vf "fps=10,scale=480:-1:flags=lanczos" %s 2>&1',
        escapeshellarg($inputPath),
        escapeshellarg($outputPath)
    );
    
    exec($command, $output, $returnCode);
    
    if ($returnCode !== 0) {
        throw new Exception("GIF generation failed: " . implode("\n", $output));
    }
    
    return $outputPath;
}

/**
 * Transcode video to specific resolution
 */
function transcodeVideo($inputPath, $baseName, $height) {
    $outputPath = PROCESSED_DIR . $baseName . "_{$height}p.mp4";
    
    // Determine bitrate based on resolution
    $bitrates = [
        1080 => '5000k',
        720 => '2500k',
        480 => '1000k'
    ];
    
    $bitrate = $bitrates[$height];
    
    $command = sprintf(
        'ffmpeg -i %s -c:v libx264 -preset medium -crf 23 ' .
        '-vf "scale=-2:%d" -b:v %s -maxrate %s -bufsize %s ' .
        '-c:a aac -b:a 128k -movflags +faststart %s 2>&1',
        escapeshellarg($inputPath),
        $height,
        $bitrate,
        $bitrate,
        $bitrate,
        escapeshellarg($outputPath)
    );
    
    exec($command, $output, $returnCode);
    
    if ($returnCode !== 0) {
        throw new Exception("Transcoding to {$height}p failed: " . implode("\n", $output));
    }
    
    return $outputPath;
}

/**
 * Generate HLS playlist for adaptive streaming
 */
function generateHLS($inputPath, $baseName) {
    $outputDir = PROCESSED_DIR . $baseName . '_hls/';
    if (!is_dir($outputDir)) mkdir($outputDir, 0755, true);
    
    $playlistPath = $outputDir . 'playlist.m3u8';
    
    $command = sprintf(
        'ffmpeg -i %s -c:v libx264 -preset medium -crf 23 ' .
        '-c:a aac -b:a 128k ' .
        '-hls_time 10 -hls_playlist_type vod ' .
        '-hls_segment_filename %s %s 2>&1',
        escapeshellarg($inputPath),
        escapeshellarg($outputDir . 'segment_%d.ts'),
        escapeshellarg($playlistPath)
    );
    
    exec($command, $output, $returnCode);
    
    if ($returnCode !== 0) {
        throw new Exception("HLS generation failed: " . implode("\n", $output));
    }
    
    return $playlistPath;
}

/**
 * Send notification to user
 */
function sendProcessingCompleteNotification($db, $userId) {
    try {
        $stmt = $db->prepare("
            INSERT INTO notifications (
                user_id, type, title, message, created_at
            ) VALUES (?, 'video_processed', 'Vídeo Processado', 'Seu vídeo foi processado e está pronto para visualização!', NOW())
        ");
        $stmt->execute([$userId]);
    } catch (Exception $e) {
        error_log("Notification error: " . $e->getMessage());
    }
}

/**
 * Get database connection
 */
function getDatabase() {
    try {
        $dsn = "pgsql:host=localhost;dbname=frames_db";
        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ];
        
        return new PDO($dsn, 'postgres', 'your_password', $options);
    } catch (PDOException $e) {
        die("Database connection failed: " . $e->getMessage());
    }
}
?>