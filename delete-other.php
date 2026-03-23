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

// Only admins can delete other files
if (!isAdmin()) {
    echo json_encode(['success' => false, 'message' => 'Insufficient permissions']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Handle both form data and URL encoded data
    $fileName = '';
    $category = '';
    $subfolder = '';
    
    // Check if it's FormData (from subfolder files)
    if (isset($_POST['other_file'])) {
        $fileName = $_POST['other_file'];
        $category = $_POST['category'] ?? '';
        $subfolder = $_POST['subfolder'] ?? '';
    }
    
    // Validate required parameters
    if (empty($fileName) || empty($category)) {
        echo json_encode(['success' => false, 'message' => 'Missing required parameters']);
        exit;
    }
    
    // Validate inputs to prevent directory traversal
    if (strpos($fileName, '..') !== false || strpos($fileName, '/') !== false || strpos($fileName, '\\') !== false ||
        strpos($category, '..') !== false || strpos($category, '/') !== false || strpos($category, '\\') !== false) {
        echo json_encode(['success' => false, 'message' => 'Invalid filename or category']);
        exit;
    }
    
    if (!empty($subfolder)) {
        if (strpos($subfolder, '..') !== false || strpos($subfolder, '/') !== false || strpos($subfolder, '\\') !== false) {
            echo json_encode(['success' => false, 'message' => 'Invalid subfolder name']);
            exit;
        }
    }
    
    // Construct file path
    if (!empty($subfolder)) {
        // File is in a subfolder
        $filePath = __DIR__ . "/other/{$category}/{$subfolder}/{$fileName}";
        $locationText = "ze složky '{$subfolder}'";
    } else {
        // File is in main category folder
        $filePath = __DIR__ . "/other/{$category}/{$fileName}";
        $locationText = "z hlavní složky";
    }
    
    if (file_exists($filePath)) {
        // Attempt to delete the file
        if (unlink($filePath)) {
            echo json_encode([
                'success' => true, 
                'message' => "Soubor '{$fileName}' byl úspěšně smazán {$locationText}."
            ]);
        } else {
            echo json_encode([
                'success' => false, 
                'message' => "Nepodařilo se smazat soubor '{$fileName}'."
            ]);
        }
    } else {
        echo json_encode(['success' => false, 'message' => "Soubor '{$fileName}' nebyl nalezen {$locationText}."]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
}
exit;
?>
