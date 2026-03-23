<?php
// Start session first, before any output
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json');
error_reporting(E_ALL);
ini_set('display_errors', 0);

require_once 'access.php';

// Check if user is admin
if (!isLoggedIn() || !isAdmin()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Nedostatečná oprávnění']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Nepovolená metoda']);
    exit;
}

try {
    $category = trim($_POST['category'] ?? '');

    if (empty($category)) {
        echo json_encode(['success' => false, 'message' => 'Kategorie je povinná']);
        exit;
    }

    $programsDir = __DIR__ . '/programs/';
    $categoryDir = $programsDir . $category . '/';

    // Check if category exists
    if (!is_dir($categoryDir)) {
        echo json_encode(['success' => false, 'message' => 'Kategorie neexistuje']);
        exit;
    }

    // Check if it's a custom category
    $infoFile = $categoryDir . '.category-info.json';
    $isCustom = false;
    
    if (file_exists($infoFile)) {
        $categoryInfo = json_decode(file_get_contents($infoFile), true);
        $isCustom = $categoryInfo['custom'] ?? false;
    }

    /*
    // Don't allow deletion of default categories
    $defaultCategories = ['design', 'development', 'office', 'media', 'security', 'productivity'];
    if (in_array($category, $defaultCategories) && !$isCustom) {
        echo json_encode(['success' => false, 'message' => 'Nelze smazat výchozí kategorii']);
        exit;
    }
    */

    // Function to recursively delete directory
    function deleteDirectory($dir) {
        if (!is_dir($dir)) {
            return false;
        }
        
        $items = array_diff(scandir($dir), ['.', '..']);
        foreach ($items as $item) {
            $path = $dir . DIRECTORY_SEPARATOR . $item;
            if (is_dir($path)) {
                deleteDirectory($path);
            } else {
                unlink($path);
            }
        }
        return rmdir($dir);
    }

    // Delete the entire category directory
    if (deleteDirectory($categoryDir)) {
        echo json_encode([
            'success' => true,
            'message' => 'Kategorie byla úspěšně smazána'
        ]);
    } else {
        throw new Exception('Nepodařilo se smazat kategorii');
    }

} catch (Exception $e) {
    error_log('Error deleting program category: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Chyba při mazání kategorie: ' . $e->getMessage()]);
}
?>
