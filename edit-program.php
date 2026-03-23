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
    $filename = trim($_POST['filename'] ?? '');
    $originalCategory = trim($_POST['original_category'] ?? '');
    $title = trim($_POST['program_title'] ?? '');
    $url = trim($_POST['program_url'] ?? '');
    $category = trim($_POST['program_category'] ?? '');
    $price = trim($_POST['program_price'] ?? '');
    $license = trim($_POST['program_license'] ?? '');
    $description = trim($_POST['program_description'] ?? '');

    if (empty($filename) || empty($originalCategory) || empty($title) || empty($url) || empty($category)) {
        echo json_encode(['success' => false, 'message' => 'Všechna povinná pole musí být vyplněna']);
        exit;
    }

    $programsDir = __DIR__ . '/programs/';
    $originalCategoryDir = $programsDir . $originalCategory . '/';
    $newCategoryDir = $programsDir . $category . '/';
    $originalFile = $originalCategoryDir . $filename;

    // Check if original file exists
    if (!file_exists($originalFile)) {
        echo json_encode(['success' => false, 'message' => 'Program neexistuje']);
        exit;
    }

    // Load existing program data
    $programData = json_decode(file_get_contents($originalFile), true);

    if ($programData === null) {
        throw new Exception('Nepodařilo se načíst data programu');
    }

    // Update program data
    $programData['title'] = $title;
    $programData['url'] = $url;
    $programData['category'] = $category;
    $programData['price'] = $price;
    $programData['license_type'] = $license;
    $programData['description'] = $description;
    $programData['modified'] = time();

    // Handle image upload if new image is provided
    if (isset($_FILES['program_image']) && $_FILES['program_image']['error'] === UPLOAD_ERR_OK) {
        $uploadDir = __DIR__ . '/uploads/programs/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        $imageExtension = pathinfo($_FILES['program_image']['name'], PATHINFO_EXTENSION);
        $imageFilename = uniqid('program_') . '.' . $imageExtension;
        $imagePath = $uploadDir . $imageFilename;

        if (move_uploaded_file($_FILES['program_image']['tmp_name'], $imagePath)) {
            // Delete old image if it exists
            if (!empty($programData['image']) && file_exists($programData['image'])) {
                unlink($programData['image']);
            }
            $programData['image'] = 'uploads/programs/' . $imageFilename;
            chmod($imagePath, 0644);
        }
    }

    // Create new category directory if it doesn't exist
    if (!is_dir($newCategoryDir)) {
        mkdir($newCategoryDir, 0755, true);
    }

    // Generate new filename based on creation date and new title
    $dateAdded = $programData['date_added'] ?? time();
    $safeTitle = preg_replace('/[^a-z0-9]+/', '-', strtolower($title));
    $newFilename = $dateAdded . '-' . $safeTitle . '.json';
    $newFile = $newCategoryDir . $newFilename;

    // Save updated program data
    if (file_put_contents($newFile, json_encode($programData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) === false) {
        throw new Exception('Nepodařilo se uložit změny');
    }

    chmod($newFile, 0644);

    // Delete original file if category changed or filename changed
    if ($originalFile !== $newFile && file_exists($originalFile)) {
        unlink($originalFile);
    }

    echo json_encode([
        'success' => true,
        'message' => 'Program byl úspěšně upraven'
    ]);

} catch (Exception $e) {
    error_log('Error editing program: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Chyba při úpravě programu: ' . $e->getMessage()]);
}

function sanitizeFilename($filename) {
    $filename = preg_replace('/[^aA-Zz0-9._-]/', '_', $filename);
    return substr($filename, 0, 50);
}
?>
