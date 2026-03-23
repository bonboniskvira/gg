<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Start output buffering before including any files
ob_start();

require_once 'access.php';

// Clean any output that might have been generated
if (ob_get_level()) {
    ob_clean();
}

header('Content-Type: application/json');

// Check if user is logged in first
if (!isset($_SESSION['role']) || empty($_SESSION['role'])) {
    echo json_encode(['success' => false, 'message' => 'Nejste přihlášeni']);
    exit;
}

// Ensure user is admin
if (!isAdmin()) {
    echo json_encode(['success' => false, 'message' => 'Nemáte dostatečná oprávnění pro tuto akci']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Neplatná metoda požadavku']);
    exit;
}

$fileName = $_POST['course_file'] ?? '';
$category = $_POST['category'] ?? '';

if (empty($fileName) || empty($category)) {
    echo json_encode(['success' => false, 'message' => 'Chybí povinné údaje']);
    exit;
}

// Sanitize inputs
$category = basename($category);
$fileName = basename($fileName);

try {
    // Function to find and delete file recursively
    function findAndDeleteFile($dir, $fileName) {
        if (!is_dir($dir)) {
            return false;
        }

        $items = array_diff(scandir($dir), ['.', '..']);
        
        foreach ($items as $item) {
            $itemPath = $dir . '/' . $item;
            
            if (is_dir($itemPath)) {
                // Recursively search in subdirectories
                if (findAndDeleteFile($itemPath, $fileName)) {
                    return true;
                }
            } else {
                // Check if this is the file we're looking for
                if ($item === $fileName) {
                    return unlink($itemPath);
                }
            }
        }
        
        return false;
    }

    $categoryPath = __DIR__ . '/courses/' . $category;
    
    if (!is_dir($categoryPath)) {
        echo json_encode(['success' => false, 'message' => 'Kurz neexistuje']);
        exit;
    }

    if (findAndDeleteFile($categoryPath, $fileName)) {
        echo json_encode(['success' => true, 'message' => 'Soubor byl úspěšně smazán']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Soubor nebyl nalezen']);
    }

} catch (Exception $e) {
    error_log("Error deleting course file: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Systémová chyba']);
}
?>
