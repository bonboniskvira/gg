<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Start output buffering
ob_start();

header('Content-Type: application/json');
error_reporting(E_ALL);
ini_set('display_errors', 0);

require_once 'access.php';

// Check if user is admin
if (!isAdmin()) {
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
    $filename = trim($_POST['filename'] ?? '');
    $category = trim($_POST['category'] ?? '');

    if (empty($filename) || empty($category)) {
        echo json_encode(['success' => false, 'message' => 'Filename a kategorie jsou povinné']);
        exit;
    }

    $programsDir = __DIR__ . '/programs/';
    $categoryDir = $programsDir . $category . '/';
    $programFile = $categoryDir . $filename;

    // Check if program file exists
    if (!file_exists($programFile)) {
        echo json_encode(['success' => false, 'message' => 'Program neexistuje']);
        exit;
    }

    // Load program data to get image path
    $programData = json_decode(file_get_contents($programFile), true);

    // Delete the program file
    if (unlink($programFile)) {
        // Also delete associated image if it exists
        if (!empty($programData['image']) && file_exists($programData['image'])) {
            unlink($programData['image']);
        }

        echo json_encode([
            'success' => true,
            'message' => 'Program byl úspěšně smazán'
        ]);
    } else {
        throw new Exception('Nepodařilo se smazat program');
    }

} catch (Exception $e) {
    error_log('Error deleting program: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Chyba při mazání programu: ' . $e->getMessage()]);
}
?>
