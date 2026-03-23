<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Start output buffering
ob_start();

require_once 'access.php';

// Clean any output that might have been generated
if (ob_get_level()) {
    ob_clean();
}

// Set content type for JSON response
header('Content-Type: application/json');
error_reporting(E_ALL);
ini_set('display_errors', 0);

// Check if user is logged in and is admin
if (!isset($_SESSION['role']) || empty($_SESSION['role']) || !isAdmin()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Přístup odepřen']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['filename']) && isset($_POST['category'])) {
    $filename = trim($_POST['filename']);
    $category = trim($_POST['category']);

    // Validate required fields
    if (empty($filename) || empty($category)) {
        echo json_encode(['success' => false, 'message' => 'Filename a kategorie jsou povinné']);
        exit;
    }

    // Construct file path
    $programsDir = __DIR__ . '/programs/';
    $categoryDir = $programsDir . $category . '/';
    $programFile = $categoryDir . $filename;

    // Check if program file exists
    if (!file_exists($programFile)) {
        echo json_encode(['success' => false, 'message' => 'Program neexistuje']);
        exit;
    }

    // Load program data
    $programData = json_decode(file_get_contents($programFile), true);

    if ($programData === null) {
        echo json_encode(['success' => false, 'message' => 'Nepodařilo se načíst data programu']);
        exit;
    }

    echo json_encode([
        'success' => true,
        'program' => $programData
    ]);
} else {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Nepovolená metoda']);
}
?>
