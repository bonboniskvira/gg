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

// Only admins can edit other files
if (!isAdmin()) {
    echo json_encode(['success' => false, 'message' => 'Insufficient permissions']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $category = trim($_POST['category'] ?? '');
    $subfolder = trim($_POST['subfolder'] ?? '');
    $originalName = trim($_POST['original_name'] ?? '');
    $newName = trim($_POST['new_name'] ?? '');
    
    if (empty($category) || empty($originalName) || empty($newName)) {
        echo json_encode(['success' => false, 'message' => 'Missing required parameters']);
        exit;
    }
    
    // Validate inputs to prevent directory traversal
    if (strpos($originalName, '..') !== false || strpos($originalName, '/') !== false || strpos($originalName, '\\') !== false ||
        strpos($category, '..') !== false || strpos($category, '/') !== false || strpos($category, '\\') !== false ||
        strpos($newName, '..') !== false || strpos($newName, '/') !== false || strpos($newName, '\\') !== false) {
        echo json_encode(['success' => false, 'message' => 'Invalid filename or category']);
        exit;
    }
    
    if (!empty($subfolder)) {
        if (strpos($subfolder, '..') !== false || strpos($subfolder, '/') !== false || strpos($subfolder, '\\') !== false) {
            echo json_encode(['success' => false, 'message' => 'Invalid subfolder name']);
            exit;
        }
    }
    
    // Construct file paths
    if (!empty($subfolder)) {
        // File is in a subfolder
        $originalPath = __DIR__ . "/other/{$category}/{$subfolder}/{$originalName}";
        $locationText = "v podsložce '{$subfolder}'";
    } else {
        // File is in main category folder
        $originalPath = __DIR__ . "/other/{$category}/{$originalName}";
        $locationText = "v hlavní složce";
    }
    
    // Check if original file exists
    if (!file_exists($originalPath)) {
        echo json_encode(['success' => false, 'message' => "Soubor '{$originalName}' nebyl nalezen {$locationText}."]);
        exit;
    }
    
    // Get file extension from original file
    $fileExtension = pathinfo($originalName, PATHINFO_EXTENSION);
    $newFileName = $newName . '.' . $fileExtension;
    
    // Construct new file path
    if (!empty($subfolder)) {
        $newPath = __DIR__ . "/other/{$category}/{$subfolder}/{$newFileName}";
    } else {
        $newPath = __DIR__ . "/other/{$category}/{$newFileName}";
    }
    
    // Check if new filename already exists
    if ($originalPath !== $newPath && file_exists($newPath)) {
        echo json_encode(['success' => false, 'message' => "Soubor s názvem '{$newFileName}' již existuje {$locationText}."]);
        exit;
    }
    
    // Rename the file
    if (rename($originalPath, $newPath)) {
        echo json_encode([
            'success' => true, 
            'message' => "Soubor byl úspěšně přejmenován na '{$newFileName}' {$locationText}."
        ]);
    } else {
        echo json_encode([
            'success' => false, 
            'message' => "Nepodařilo se přejmenovat soubor '{$originalName}' {$locationText}."
        ]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
}

// End output buffering and send response
ob_end_flush();
exit;
?>
