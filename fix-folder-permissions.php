<?php
header('Content-Type: application/json');

// Include access control
require_once 'components/pages/access.php';

// Only allow admins to fix permissions
if (!isAdmin()) {
    echo json_encode(['success' => false, 'message' => 'Insufficient permissions']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

$path = $_POST['path'] ?? '';

if (empty($path)) {
    echo json_encode(['success' => false, 'message' => 'No path specified']);
    exit;
}

// Function to set proper permissions
function setProperPermissions($path, $isFile = false) {
    if (!file_exists($path)) {
        return false;
    }
    
    try {
        if ($isFile) {
            chmod($path, 0644);
        } else {
            chmod($path, 0755);
        }
        return true;
    } catch (Exception $e) {
        error_log("Permission setting failed for $path: " . $e->getMessage());
        return false;
    }
}

// Function to recursively fix permissions
function fixDirectoryPermissions($dir) {
    if (!is_dir($dir)) {
        return setProperPermissions($dir, true);
    }
    
    if (!setProperPermissions($dir, false)) {
        return false;
    }
    
    $items = array_diff(scandir($dir), ['.', '..']);
    
    foreach ($items as $item) {
        $itemPath = $dir . '/' . $item;
        
        if (is_dir($itemPath)) {
            if (!fixDirectoryPermissions($itemPath)) {
                return false;
            }
        } else {
            if (!setProperPermissions($itemPath, true)) {
                return false;
            }
        }
    }
    
    return true;
}

// Build full path
$docDir = __DIR__ . '/doc/';
$fullPath = $docDir . ltrim($path, '/');

// Security check
if (!file_exists($fullPath) || strpos(realpath($fullPath), realpath($docDir)) !== 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid path']);
    exit;
}

try {
    $success = fixDirectoryPermissions($fullPath);
    
    echo json_encode([
        'success' => $success, 
        'message' => $success ? 'Permissions fixed successfully' : 'Failed to fix permissions'
    ]);
} catch (Exception $e) {
    echo json_encode([
        'success' => false, 
        'message' => 'Error: ' . $e->getMessage()
    ]);
}
?>
