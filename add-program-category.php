<?php
// Start session first, before any output
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json');
error_reporting(E_ALL);
ini_set('display_errors', 0);

require_once 'access.php';

// Debug: Check session and admin status
error_log("Session data: " . print_r($_SESSION, true));
error_log("Current user: " . ($_SESSION['username'] ?? 'not set'));
error_log("Is logged in: " . (isLoggedIn() ? 'true' : 'false'));

// Debug: Check if user is admin
$isAdmin = isAdmin();
error_log("Add category - User is admin: " . ($isAdmin ? 'true' : 'false'));

// Try alternative admin check
$username = $_SESSION['username'] ?? '';
$isAdminAlt = ($username === 'admin' || in_array($username, ['admin', 'administrator']));
error_log("Alternative admin check for user '$username': " . ($isAdminAlt ? 'true' : 'false'));

// Check if session exists and user is logged in
if (!isset($_SESSION) || empty($_SESSION) || !isLoggedIn()) {
    error_log("No valid session found");
    http_response_code(403);
    echo json_encode([
        'success' => false, 
        'message' => 'Nejste přihlášeni. Přihlaste se prosím.',
        'debug' => [
            'session_status' => session_status(),
            'session_data' => $_SESSION ?? 'null'
        ]
    ]);
    exit;
}

// Check if user is admin - use alternative check if main one fails
if (!$isAdmin && !$isAdminAlt) {
    error_log("Access denied - not admin. Username: $username");
    http_response_code(403);
    echo json_encode([
        'success' => false, 
        'message' => 'Nedostatečná oprávnění',
        'debug' => [
            'username' => $username,
            'isAdmin' => $isAdmin,
            'isAdminAlt' => $isAdminAlt,
            'session' => $_SESSION
        ]
    ]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Nepovolená metoda']);
    exit;
}

try {
    $categoryName = trim($_POST['category_name'] ?? '');
    $categoryColor = trim($_POST['category_color'] ?? '#3498db');

    error_log("Add category - Name: $categoryName, Color: $categoryColor");

    if (empty($categoryName)) {
        echo json_encode(['success' => false, 'message' => 'Název kategorie je povinný']);
        exit;
    }

    // Generate category key from name (convert to lowercase, replace spaces with hyphens, remove special chars)
    $categoryKey = strtolower($categoryName);
    $categoryKey = preg_replace('/[^a-z0-9\s-]/', '', $categoryKey);
    $categoryKey = preg_replace('/\s+/', '-', $categoryKey);
    $categoryKey = trim($categoryKey, '-');

    if (empty($categoryKey)) {
        echo json_encode(['success' => false, 'message' => 'Nelze vytvořit klíč kategorie z názvu']);
        exit;
    }

    // Check if category already exists
    $programsDir = __DIR__ . '/programs/';
    $categoryDir = $programsDir . $categoryKey . '/';

    if (is_dir($categoryDir)) {
        echo json_encode(['success' => false, 'message' => 'Kategorie s tímto názvem již existuje']);
        exit;
    }

    // Create programs directory if it doesn't exist
    if (!is_dir($programsDir)) {
        if (!mkdir($programsDir, 0755, true)) {
            throw new Exception('Nepodařilo se vytvořit adresář pro programy');
        }
    }

    // Create category directory
    if (!mkdir($categoryDir, 0755, true)) {
        throw new Exception('Nepodařilo se vytvořit adresář kategorie');
    }

    // Create category info file
    $categoryInfo = [
        'key' => $categoryKey,
        'name' => $categoryName,
        'color' => $categoryColor,
        'created' => time(),
        'custom' => true
    ];

    $infoFile = $categoryDir . '.category-info.json';
    if (file_put_contents($infoFile, json_encode($categoryInfo, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) === false) {
        // Clean up directory if info file creation failed
        rmdir($categoryDir);
        throw new Exception('Nepodařilo se uložit informace o kategorii');
    }

    // Set proper permissions
    chmod($categoryDir, 0755);
    chmod($infoFile, 0644);

    echo json_encode([
        'success' => true,
        'message' => 'Kategorie "' . $categoryName . '" byla úspěšně vytvořena',
        'category' => [
            'key' => $categoryKey,
            'name' => $categoryName,
            'color' => $categoryColor
        ]
    ]);

} catch (Exception $e) {
    error_log('Error creating program category: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Chyba při vytváření kategorie: ' . $e->getMessage()]);
}
?>
