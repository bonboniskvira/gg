<?php
if (!isset($_GET['img']) || empty($_GET['img'])) {
    header('HTTP/1.1 400 Bad Request');
    exit;
}

$imageName = basename($_GET['img']); // Security: only filename, no path traversal
$imagePath = __DIR__ . '/img_bulletin/' . $imageName;

// Check if file exists and is in the correct directory
if (!file_exists($imagePath) || !is_readable($imagePath)) {
    header('HTTP/1.1 404 Not Found');
    exit;
}

// Get file info
$imageInfo = getimagesize($imagePath);
if (!$imageInfo) {
    header('HTTP/1.1 415 Unsupported Media Type');
    exit;
}

// Set appropriate headers
header('Content-Type: ' . $imageInfo['mime']);
header('Content-Length: ' . filesize($imagePath));
header('Cache-Control: public, max-age=3600'); // Cache for 1 hour

// Output the image
readfile($imagePath);
?>
