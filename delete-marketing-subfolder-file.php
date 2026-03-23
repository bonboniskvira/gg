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

$fileName = $_POST['marketing_file'] ?? '';
$category = $_POST['category'] ?? '';
$subfolder = $_POST['subfolder'] ?? '';

if (empty($fileName) || empty($category) || empty($subfolder)) {
    echo json_encode(['success' => false, 'message' => 'Chybí povinné údaje']);
    exit;
}

// Sanitize inputs
$category = basename($category);
$subfolder = basename($subfolder);
$fileName = basename($fileName);

try {
    $filePath = __DIR__ . '/marketing/' . $category . '/' . $subfolder . '/' . $fileName;
    
    if (!file_exists($filePath)) {
        echo json_encode(['success' => false, 'message' => 'Soubor nebyl nalezen']);
        exit;
    }

    if (unlink($filePath)) {
        echo json_encode(['success' => true, 'message' => 'Soubor byl úspěšně smazán z podsložky']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Nepodařilo se smazat soubor']);
    }

} catch (Exception $e) {
    error_log("Error deleting marketing subfolder file: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Systémová chyba']);
}
exit;
?>
