<?php
// Prevent any output before headers
ob_start();

session_start();
require_once 'access.php';

// Clean any previous output
ob_clean();

// Set headers
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-cache, must-revalidate');

// Only admins can create subfolders
if (!isAdmin()) {
    echo json_encode(['success' => false, 'message' => 'Insufficient permissions']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $category = $_POST['parent_category'] ?? '';
    $subfolderName = trim($_POST['subfolder_name'] ?? '');
    
    if (empty($category) || empty($subfolderName)) {
        echo json_encode(['success' => false, 'message' => 'Missing required parameters']);
        exit;
    }
    
    // Use subfolder name as-is - no sanitization to preserve Czech characters
    $safeName = trim($subfolderName);
    
    if (empty($safeName)) {
        echo json_encode(['success' => false, 'message' => 'Invalid subfolder name']);
        exit;
    }
    
    // Create subfolder path
    $categoryPath = __DIR__ . "/seznam/{$category}/";
    $subfolderPath = $categoryPath . $safeName . '/';
    
    // Check if parent category exists
    if (!is_dir($categoryPath)) {
        // Create parent category if it doesn't exist
        if (!mkdir($categoryPath, 0777, true)) {
            echo json_encode(['success' => false, 'message' => 'Failed to create parent category']);
            exit;
        }
    }
    
    // Check if subfolder already exists
    if (is_dir($subfolderPath)) {
        echo json_encode(['success' => false, 'message' => 'Subfolder already exists']);
        exit;
    }
    
    // Create subfolder
    if (mkdir($subfolderPath, 0777, true)) {
        echo json_encode(['success' => true, 'message' => 'Podsložka byla úspěšně vytvořena'], JSON_UNESCAPED_UNICODE);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to create subfolder']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
}
exit;
?>
