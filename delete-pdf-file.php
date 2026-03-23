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

$fileName = $_POST['pdf_file'] ?? '';

if (empty($fileName)) {
    echo json_encode(['success' => false, 'message' => 'Chybí název souboru']);
    exit;
}

// Sanitize input
$fileName = basename($fileName);

try {
    $filePath = __DIR__ . '/pdf/' . $fileName;
    
    if (!file_exists($filePath)) {
        echo json_encode(['success' => false, 'message' => 'Soubor neexistuje']);
        exit;
    }

    if (unlink($filePath)) {
        echo json_encode(['success' => true, 'message' => 'PDF soubor byl úspěšně smazán']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Nepodařilo se smazat soubor']);
    }

} catch (Exception $e) {
    error_log("Error deleting PDF file: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Systémová chyba']);
}
?>
