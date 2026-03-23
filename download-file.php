<?php
session_start();
require_once __DIR__ . '/access.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

// Get file path and type
$filePath = $_GET['file'] ?? '';
$fileType = $_GET['type'] ?? '';
$forceDownload = isset($_GET['download']) && $_GET['download'] == '1';

if (empty($filePath) || empty($fileType)) {
    http_response_code(400);
    echo "Missing file parameters";
    exit;
}

// Security: Prevent directory traversal
if (strpos($filePath, '..') !== false || strpos($filePath, '\\') !== false) {
    http_response_code(403);
    echo "Invalid file path";
    exit;
}

// Build full file path
$fullPath = __DIR__ . '/' . $filePath;

// Check if file exists
if (!file_exists($fullPath) || !is_file($fullPath)) {
    http_response_code(404);
    echo "File not found: " . htmlspecialchars($filePath);
    exit;
}

// Get file info
$fileInfo = pathinfo($fullPath);
$fileName = $fileInfo['basename'];
$fileExtension = strtolower($fileInfo['extension']);
$fileSize = filesize($fullPath);

// Set appropriate content type
$contentTypes = [
    'pdf' => 'application/pdf',
    'mp3' => 'audio/mpeg',
    'mp4' => 'video/mp4',
    'wav' => 'audio/wav',
    'avi' => 'video/x-msvideo',
    'mov' => 'video/quicktime',
    'wmv' => 'video/x-ms-wmv',
    'flv' => 'video/x-flv',
    'webm' => 'video/webm',
    'mkv' => 'video/x-matroska',
    'ogg' => 'audio/ogg',
    'm4a' => 'audio/mp4',
    'aac' => 'audio/aac',
    'txt' => 'text/plain',
    'doc' => 'application/msword',
    'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    'rtf' => 'application/rtf'
];

$contentType = $contentTypes[$fileExtension] ?? 'application/octet-stream';

// Clear any previous output
if (ob_get_level()) {
    ob_end_clean();
}

// Handle range requests for media files (for better streaming support)
$isMediaFile = in_array($fileExtension, ['mp3', 'mp4', 'wav', 'ogg', 'm4a', 'aac', 'avi', 'mov', 'wmv', 'flv', 'webm', 'mkv']);

if ($isMediaFile && isset($_SERVER['HTTP_RANGE']) && !$forceDownload) {
    // Handle range request for streaming
    $range = $_SERVER['HTTP_RANGE'];
    
    if (preg_match('/bytes=(\d+)-(\d*)/', $range, $matches)) {
        $start = intval($matches[1]);
        $end = !empty($matches[2]) ? intval($matches[2]) : $fileSize - 1;
        
        if ($start > $fileSize - 1 || $end > $fileSize - 1) {
            http_response_code(416);
            header("Content-Range: bytes */$fileSize");
            exit;
        }
        
        $length = $end - $start + 1;
        
        header('HTTP/1.1 206 Partial Content');
        header('Accept-Ranges: bytes');
        header("Content-Range: bytes $start-$end/$fileSize");
        header('Content-Length: ' . $length);
        header('Content-Type: ' . $contentType);
        
        if (!$forceDownload) {
            header('Content-Disposition: inline; filename="' . $fileName . '"');
        } else {
            header('Content-Disposition: attachment; filename="' . $fileName . '"');
        }
        
        // Output the requested range
        $file = fopen($fullPath, 'rb');
        if ($file) {
            fseek($file, $start);
            echo fread($file, $length);
            fclose($file);
        }
        exit;
    }
}

// Set standard headers
header('Content-Type: ' . $contentType);
header('Content-Length: ' . $fileSize);
header('Accept-Ranges: bytes');

if ($forceDownload) {
    // Force download
    header('Content-Disposition: attachment; filename="' . $fileName . '"');
} else {
    // Display inline (for viewing/streaming in browser)
    header('Content-Disposition: inline; filename="' . $fileName . '"');
}

// Add cache control for media files
if ($isMediaFile && !$forceDownload) {
    header('Cache-Control: public, max-age=3600');
} else {
    header('Cache-Control: public, max-age=3600');
}

// Output file
readfile($fullPath);
exit;
?>
