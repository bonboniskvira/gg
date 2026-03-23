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

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'edit_link') {
    $filename = trim($_POST['filename'] ?? '');
    $originalCategory = trim($_POST['original_category'] ?? '');
    $title = trim($_POST['link_title'] ?? '');
    $url = trim($_POST['link_url'] ?? '');
    $category = trim($_POST['link_category'] ?? 'general');
    $description = trim($_POST['link_description'] ?? '');

    // Validate required fields
    if (empty($filename) || empty($originalCategory) || empty($title) || empty($url)) {
        echo json_encode(['success' => false, 'message' => 'Chybí povinné údaje']);
        exit;
    }

    // Validate URL
    if (filter_var($url, FILTER_VALIDATE_URL) === false) {
        echo json_encode(['success' => false, 'message' => 'Neplatná URL adresa']);
        exit;
    }

    // Valid categories
    $validCategories = ['general', 'tools', 'courses', 'resources', 'partners'];
    if (!in_array($category, $validCategories)) {
        $category = 'general';
    }

    // Paths
    $linksDir = __DIR__ . '/links/';
    $originalFile = $linksDir . $originalCategory . '/' . $filename;

    // Check if original file exists
    if (!file_exists($originalFile)) {
        echo json_encode(['success' => false, 'message' => 'Původní soubor nebyl nalezen']);
        exit;
    }

    // Read original data
    $originalData = json_decode(file_get_contents($originalFile), true);
    if (!$originalData) {
        echo json_encode(['success' => false, 'message' => 'Chyba při čtení původních dat']);
        exit;
    }

    // Create updated link data
    $linkData = [
        'title' => $title,
        'url' => $url,
        'description' => $description,
        'date_added' => $originalData['date_added'] ?? time(),
        'date_modified' => time(),
        'category' => $category
    ];

    // If category changed, we need to move the file
    if ($category !== $originalCategory) {
        $newCategoryDir = $linksDir . $category . '/';
        
        // Create new category directory if it doesn't exist
        if (!is_dir($newCategoryDir)) {
            mkdir($newCategoryDir, 0777, true);
        }
        
        // Generate new filename (keep timestamp from original)
        $safeTitle = preg_replace('/[^a-z0-9]+/', '-', strtolower($title));
        $timestamp = $originalData['date_added'] ?? time();
        $newFilename = $newCategoryDir . $timestamp . '-' . $safeTitle . '.json';
        
        // Save to new location
        if (file_put_contents($newFilename, json_encode($linkData, JSON_PRETTY_PRINT))) {
            // Delete original file
            unlink($originalFile);
            echo json_encode(['success' => true, 'message' => 'Odkaz byl úspěšně upraven a přesunut']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Chyba při ukládání do nové kategorie']);
        }
    } else {
        // Same category, just update the file
        if (file_put_contents($originalFile, json_encode($linkData, JSON_PRETTY_PRINT))) {
            echo json_encode(['success' => true, 'message' => 'Odkaz byl úspěšně upraven']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Chyba při ukládání změn']);
        }
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Neplatný požadavek']);
}
?>
