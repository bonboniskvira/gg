<?php
session_start();
require_once 'access.php';

// Check authentication - must be logged in to access files
if (!isLoggedIn()) {
    http_response_code(403);
    header('Location: login.php?error=' . urlencode('Pro přístup k souborům se musíte přihlásit'));
    exit;
}

// Get the requested file
$requestedFile = $_GET['file'] ?? '';

if (empty($requestedFile)) {
    http_response_code(404);
    die('File not specified');
}

// Sanitize the file path
$requestedFile = ltrim($requestedFile, '/');

// Build the full file path
$fullPath = __DIR__ . '/' . $requestedFile;

// Security checks
if (!file_exists($fullPath)) {
    http_response_code(404);
    die('File not found');
}

// Ensure the file is within allowed directories
$realPath = realpath($fullPath);
$allowedPaths = [
    realpath(__DIR__ . '/doc'),
    realpath(__DIR__ . '/videos')
];

$pathAllowed = false;
foreach ($allowedPaths as $allowedPath) {
    if ($allowedPath && strpos($realPath, $allowedPath) === 0) {
        $pathAllowed = true;
        break;
    }
}

if (!$pathAllowed) {
    http_response_code(403);
    die('Access denied');
}

// Get file info
$fileSize = filesize($realPath);
$fileExtension = strtolower(pathinfo($realPath, PATHINFO_EXTENSION));
$fileName = basename($realPath);

// Set appropriate content type
$mimeTypes = [
    'pdf' => 'application/pdf',
    'doc' => 'application/msword',
    'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    'xls' => 'application/vnd.ms-excel',
    'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
    'ppt' => 'application/vnd.ms-powerpoint',
    'pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
    'txt' => 'text/plain',
    'jpg' => 'image/jpeg',
    'jpeg' => 'image/jpeg',
    'png' => 'image/png',
    'gif' => 'image/gif',
    'mp4' => 'video/mp4',
    'avi' => 'video/x-msvideo',
    'mov' => 'video/quicktime',
    'mkv' => 'video/x-matroska',
    'webm' => 'video/webm',
    'mp3' => 'audio/mpeg',
    'wav' => 'audio/wav',
    'aac' => 'audio/aac'
];

$contentType = $mimeTypes[$fileExtension] ?? 'application/octet-stream';

// Handle range requests for video/audio streaming
$range = $_SERVER['HTTP_RANGE'] ?? '';

if ($range && in_array($fileExtension, ['mp4', 'avi', 'mov', 'mkv', 'webm', 'mp3', 'wav', 'aac'])) {
    // Parse range header
    if (preg_match('/bytes=(\d+)-(\d*)/', $range, $matches)) {
        $start = intval($matches[1]);
        $end = $matches[2] ? intval($matches[2]) : $fileSize - 1;
        
        // Validate range
        if ($start > $end || $start >= $fileSize) {
            http_response_code(416);
            header("Content-Range: bytes */$fileSize");
            exit;
        }
        
        $end = min($end, $fileSize - 1);
        $length = $end - $start + 1;
        
        // Send partial content
        http_response_code(206);
        header('Accept-Ranges: bytes');
        header("Content-Range: bytes $start-$end/$fileSize");
        header('Content-Length: ' . $length);
        header('Content-Type: ' . $contentType);
        
        // Open file and seek to start position
        $file = fopen($realPath, 'rb');
        fseek($file, $start);
        
        // Send the requested range
        $remaining = $length;
        while (!feof($file) && $remaining > 0) {
            $chunkSize = min(8192, $remaining);
            echo fread($file, $chunkSize);
            $remaining -= $chunkSize;
            flush();
        }
        
        fclose($file);
        exit;
    }
}

// Set headers for normal file serving
header('Content-Type: ' . $contentType);
header('Content-Length: ' . $fileSize);

// Set cache headers
header('Cache-Control: private, max-age=3600');
header('Expires: ' . gmdate('D, d M Y H:i:s', time() + 3600) . ' GMT');

// Set filename for downloads (if requested)
if (isset($_GET['download']) || in_array($fileExtension, ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'txt'])) {
    header('Content-Disposition: attachment; filename="' . $fileName . '"');
} else {
    header('Content-Disposition: inline; filename="' . $fileName . '"');
}

// Security headers for media files
if (!isAdmin()) {
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: SAMEORIGIN');
}

// Serve the file
readfile($realPath);
exit;
?>
