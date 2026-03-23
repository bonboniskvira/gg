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
    $originalCategory = trim($_POST['original_category'] ?? '');
    $newCategoryName = trim($_POST['category_name'] ?? '');
    $newCategoryColor = trim($_POST['category_color'] ?? '#3498db');

    if (empty($originalCategory) || empty($newCategoryName)) {
        echo json_encode(['success' => false, 'message' => 'Všechna pole jsou povinná']);
        exit;
    }

    $programsDir = __DIR__ . '/programs/';
    $categoryDir = $programsDir . $originalCategory . '/';
    $infoFile = $categoryDir . '.category-info.json';

    // Create category directory if it doesn't exist (for default categories)
    if (!is_dir($categoryDir)) {
        mkdir($categoryDir, 0755, true);
    }

    // Check if category exists
    if (!is_dir($categoryDir)) {
        echo json_encode(['success' => false, 'message' => 'Kategorie neexistuje']);
        exit;
    }

    // Load existing category info or create new one
    $categoryInfo = [];
    if (file_exists($infoFile)) {
        $categoryInfo = json_decode(file_get_contents($infoFile), true) ?: [];
    }

    // Update category info
    $categoryInfo['name'] = $newCategoryName;
    $categoryInfo['color'] = $newCategoryColor;
    $categoryInfo['modified'] = time();
    $categoryInfo['custom'] = true; // Mark as custom since we're editing it

    // Create directory if it doesn't exist
    if (!is_dir($categoryDir)) {
        mkdir($categoryDir, 0755, true);
    }

    // Save updated info
    if (file_put_contents($infoFile, json_encode($categoryInfo, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) === false) {
        throw new Exception('Nepodařilo se uložit změny');
    }

    chmod($infoFile, 0644);

    echo json_encode([
        'success' => true,
        'message' => 'Kategorie byla úspěšně upravena',
        'category' => [
            'key' => $originalCategory,
            'name' => $newCategoryName,
            'color' => $newCategoryColor
        ]
    ]);

} catch (Exception $e) {
    error_log('Error editing program category: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Chyba při úpravě kategorie: ' . $e->getMessage()]);
}
?>
