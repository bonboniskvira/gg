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

// Only admins can create main categories
if (!isAdmin()) {
    echo json_encode(['success' => false, 'message' => 'Insufficient permissions']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $categoryName = trim($_POST['category_name'] ?? '');
    $categoryColor = trim($_POST['category_color'] ?? '#3498db');
    
    if (empty($categoryName)) {
        echo json_encode(['success' => false, 'message' => 'Název kategorie je povinný']);
        exit;
    }
    
    // Additional validation - prevent dangerous characters but allow Czech characters, spaces, numbers
    if (preg_match('/[<>:"/\\|?*]/', $categoryName) || strlen($categoryName) > 50) {
        echo json_encode(['success' => false, 'message' => 'Název obsahuje nepovolené znaky nebo je příliš dlouhý (max 50 znaků)']);
        exit;
    }
    
    // Validate color format
    if (!preg_match('/^#[0-9a-fA-F]{6}$/', $categoryColor)) {
        $categoryColor = '#3498db'; // fallback to default color
    }
    
    // Create a safe directory name (for filesystem)
    $safeDirName = preg_replace('/[^a-zA-Z0-9_-]/', '_', $categoryName);
    $safeDirName = strtolower(trim($safeDirName, '_'));
    
    // Make sure it's not empty after sanitization
    if (empty($safeDirName)) {
        $safeDirName = 'category_' . time();
    }
    
    // Create category directory
    $categoryPath = __DIR__ . "/doc/{$safeDirName}/";
    
    // Check if directory already exists
    if (is_dir($categoryPath)) {
        echo json_encode(['success' => false, 'message' => 'Kategorie s podobným názvem již existuje']);
        exit;
    }
    
    // Create directory
    if (mkdir($categoryPath, 0777, true)) {
        // Save category info to a config file
        $configFile = __DIR__ . '/doc/categories.json';
        $categories = [];
        
        // Load existing categories
        if (file_exists($configFile)) {
            $existingData = file_get_contents($configFile);
            $categories = json_decode($existingData, true) ?: [];
        }
        
        // Add new category
        $categories[$safeDirName] = [
            'name' => $categoryName,
            'color' => $categoryColor,
            'created' => time()
        ];
        
        // Save updated categories
        if (file_put_contents($configFile, json_encode($categories, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE))) {
            echo json_encode(['success' => true, 'message' => "Kategorie '{$categoryName}' byla úspěšně vytvořena"], JSON_UNESCAPED_UNICODE);
        } else {
            // Directory was created but config save failed - clean up
            rmdir($categoryPath);
            echo json_encode(['success' => false, 'message' => 'Chyba při ukládání konfigurace kategorie']);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Chyba při vytváření adresáře kategorie']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
}
exit;
?>
