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

// Check if user is logged in and is admin
if (!isset($_SESSION['role']) || empty($_SESSION['role']) || !isAdmin()) {
    echo json_encode(['success' => false, 'message' => 'Přístup odepřen']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_link') {
    $filename = trim($_POST['filename'] ?? '');
    $category = trim($_POST['category'] ?? '');

    // Validate required fields
    if (empty($filename) || empty($category)) {
        echo json_encode(['success' => false, 'message' => 'Chybí povinné údaje']);
        exit;
    }

    // Validate category
    $validCategories = ['general', 'tools', 'courses', 'resources', 'partners'];
    if (!in_array($category, $validCategories)) {
        echo json_encode(['success' => false, 'message' => 'Neplatná kategorie']);
        exit;
    }

    // Construct file path
    $linksDir = __DIR__ . '/links/';
    $filePath = $linksDir . $category . '/' . $filename;

    // Check if file exists
    if (!file_exists($filePath)) {
        echo json_encode(['success' => false, 'message' => 'Soubor nebyl nalezen']);
        exit;
    }

    // Verify it's a JSON file to prevent deletion of other files
    if (pathinfo($filePath, PATHINFO_EXTENSION) !== 'json') {
        echo json_encode(['success' => false, 'message' => 'Neplatný typ souboru']);
        exit;
    }

    // Try to read the file to verify it's a valid link file
    $linkData = json_decode(file_get_contents($filePath), true);
    if (!$linkData || !isset($linkData['title']) || !isset($linkData['url'])) {
        echo json_encode(['success' => false, 'message' => 'Neplatný formát souboru']);
        exit;
    }

    // Delete the file
    if (unlink($filePath)) {
        echo json_encode(['success' => true, 'message' => 'Odkaz byl úspěšně smazán']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Chyba při mazání souboru']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Neplatný požadavek']);
}
?>
