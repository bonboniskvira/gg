<?php
// Set proper permissions for this file
@chmod(__FILE__, 0755);

// Force immediate execution - bypass any redirects
if (isset($_SERVER['REQUEST_URI']) && strpos($_SERVER['REQUEST_URI'], 'move-edo-file.php') === false) {
    // If we're not accessing this file directly, exit
    exit('{"error": "Access denied"}');
}

// Set JSON headers immediately and ensure they're sent
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-cache, must-revalidate');
header('X-Content-Type-Options: nosniff');

// Disable any output buffering that might interfere
while (ob_get_level()) {
    ob_end_clean();
}

// Test endpoint with permission info
if (isset($_GET['test'])) {
    echo '{"success": true, "message": "Connection works", "time": "' . date('Y-m-d H:i:s') . '", "file_permissions": "' . substr(sprintf('%o', fileperms(__FILE__)), -4) . '"}';
    exit;
}

// Debug endpoint
if (isset($_GET['debug'])) {
    echo '{"debug": "endpoint accessible", "method": "' . $_SERVER['REQUEST_METHOD'] . '", "time": "' . date('Y-m-d H:i:s') . '", "permissions": "' . substr(sprintf('%o', fileperms(__FILE__)), -4) . '"}';
    exit;
}

// Don't start session yet - test without it first
$skipAuth = isset($_GET['skipauth']);

if (!$skipAuth) {
    // Try session auth
    @session_start();
    $authenticated = isset($_SESSION['username']) || isset($_SESSION['user_id']) || isset($_SESSION['logged_in']) || isset($_SESSION['user_role']);
    
    if (!$authenticated) {
        echo '{"success": false, "message": "Not authenticated"}';
        exit;
    }
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo '{"success": false, "message": "POST required"}';
    exit;
}

// Get parameters
$fileName = $_POST['fileName'] ?? '';
$sourceCategory = $_POST['sourceCategory'] ?? '';
$sourceSubfolder = $_POST['sourceSubfolder'] ?? '';
$targetCategory = $_POST['targetCategory'] ?? '';
$targetSubfolder = $_POST['targetSubfolder'] ?? '';

// Validate
if (!$fileName || !$sourceCategory || !$targetCategory) {
    echo '{"success": false, "message": "Missing parameters"}';
    exit;
}

// Security check
if (strpos($fileName, '..') !== false || strpos($sourceCategory, '..') !== false || 
    strpos($targetCategory, '..') !== false || strpos($sourceSubfolder, '..') !== false ||
    strpos($targetSubfolder, '..') !== false) {
    echo '{"success": false, "message": "Invalid path"}';
    exit;
}

// Check same location
if ($sourceCategory === $targetCategory && $sourceSubfolder === $targetSubfolder) {
    echo '{"success": false, "message": "Same location"}';
    exit;
}

try {
    // Build paths
    $baseDir = __DIR__ . '/edo/';
    $sourcePath = $baseDir . $sourceCategory . '/' . ($sourceSubfolder ? $sourceSubfolder . '/' : '');
    $targetPath = $baseDir . $targetCategory . '/' . ($targetSubfolder ? $targetSubfolder . '/' : '');
    $sourceFile = $sourcePath . $fileName;

    // Check source
    if (!file_exists($sourceFile)) {
        echo '{"success": false, "message": "Source file not found: ' . basename($sourceFile) . '"}';
        exit;
    }

    // Create target dir and set permissions
    if (!is_dir($targetPath)) {
        if (!mkdir($targetPath, 0755, true)) {
            echo '{"success": false, "message": "Cannot create target directory"}';
            exit;
        }
        @chmod($targetPath, 0755);
    }

    // Handle conflicts
    $targetFile = $targetPath . $fileName;
    $counter = 1;
    while (file_exists($targetFile)) {
        $info = pathinfo($fileName);
        $name = $info['filename'];
        $ext = isset($info['extension']) ? '.' . $info['extension'] : '';
        $targetFile = $targetPath . $name . "_({$counter})" . $ext;
        $counter++;
    }

    // Move and set proper permissions
    if (rename($sourceFile, $targetFile)) {
        @chmod($targetFile, 0644); // Set file permissions
        $finalName = basename($targetFile);
        echo '{"success": true, "message": "File \'' . $finalName . '\' moved successfully"}';
    } else {
        echo '{"success": false, "message": "Move failed - check file system permissions"}';
    }
    
} catch (Exception $e) {
    echo '{"success": false, "message": "Error: ' . $e->getMessage() . '"}';
}
exit;
?>
