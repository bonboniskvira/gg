<?php
// Start output buffering to prevent header issues
ob_start();

session_start();
require_once 'access.php';

// Clean any output that may have been generated
ob_clean();

// Only admins can delete categories
if (!isAdmin()) {
    ob_end_clean();
    header("Location: index.php?page=documents&error=" . urlencode('Insufficient permissions'));
    exit;
}

// Recursive directory deletion function
function deleteDirectory($dir) {
    if (!is_dir($dir)) {
        return false;
    }
    
    $files = array_diff(scandir($dir), ['.', '..']);
    
    foreach ($files as $file) {
        $filePath = $dir . DIRECTORY_SEPARATOR . $file;
        if (is_dir($filePath)) {
            deleteDirectory($filePath);
        } else {
            unlink($filePath);
        }
    }
    
    return rmdir($dir);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $category = trim($_POST['category'] ?? '');
    
    if (empty($category)) {
        ob_end_clean();
        header("Location: index.php?page=documents&error=" . urlencode('Chybí název kategorie'));
        exit;
    }
    
    // Prevent deletion of default categories
    $defaultCategories = ['general', 'contracts', 'reports', 'presentations', 'manuals', 'forms', 'training', 'ebooks', 'other'];
    if (in_array($category, $defaultCategories)) {
        ob_end_clean();
        header("Location: index.php?page=documents&error=" . urlencode('Výchozí kategorie nelze smazat'));
        exit;
    }
    
    $categoryPath = __DIR__ . "/doc/{$category}/";
    
    // Check if category exists
    if (!is_dir($categoryPath)) {
        ob_end_clean();
        header("Location: index.php?page=documents&error=" . urlencode('Kategorie neexistuje'));
        exit;
    }
    
    // Delete directory and all contents
    if (deleteDirectory($categoryPath)) {
        // Remove from categories.json config file
        $configFile = __DIR__ . '/doc/categories.json';
        if (file_exists($configFile)) {
            $existingData = file_get_contents($configFile);
            $categories = json_decode($existingData, true) ?: [];
            
            if (isset($categories[$category])) {
                unset($categories[$category]);
                file_put_contents($configFile, json_encode($categories, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            }
        }
        
        ob_end_clean();
        header("Location: index.php?page=documents&success=" . urlencode("Kategorie '{$category}' byla úspěšně smazána"));
    } else {
        ob_end_clean();
        header("Location: index.php?page=documents&error=" . urlencode('Nepodařilo se smazat kategorii'));
    }
    exit;
    
} else {
    ob_end_clean();
    header("Location: index.php?page=documents&error=" . urlencode('Neplatná metoda požadavku'));
    exit;
}
?>
