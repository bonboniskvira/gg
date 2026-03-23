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

// Check if user is logged in and is admin
if (!isset($_SESSION['role']) || empty($_SESSION['role']) || !isAdmin()) {
    echo '<script>alert("Přístup odepřen"); window.location.href = "index.php";</script>';
    exit;
}

// Handle image upload
function handleImageUpload($uploadedFile, $programTitle) {
    if (!isset($uploadedFile) || $uploadedFile['error'] === UPLOAD_ERR_NO_FILE) {
        return null; // No file uploaded
    }
    
    if ($uploadedFile['error'] !== UPLOAD_ERR_OK) {
        throw new Exception('Chyba při nahrávání souboru');
    }
    
    // Validate file size (5MB max)
    $maxSize = 5 * 1024 * 1024; // 5MB
    if ($uploadedFile['size'] > $maxSize) {
        throw new Exception('Soubor je příliš velký (max. 5MB)');
    }
    
    // Validate file type
    $allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mimeType = finfo_file($finfo, $uploadedFile['tmp_name']);
    finfo_close($finfo);
    
    if (!in_array($mimeType, $allowedTypes)) {
        throw new Exception('Neplatný typ souboru. Povolené jsou pouze JPG, PNG a GIF');
    }
    
    // Create upload directory
    $uploadDir = __DIR__ . '/uploads/programs/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }
    
    // Generate unique filename
    $extension = pathinfo($uploadedFile['name'], PATHINFO_EXTENSION);
    $safeTitle = preg_replace('/[^a-z0-9]+/', '-', strtolower($programTitle));
    $filename = time() . '-' . $safeTitle . '.' . $extension;
    $targetPath = $uploadDir . $filename;
    
    // Move uploaded file
    if (!move_uploaded_file($uploadedFile['tmp_name'], $targetPath)) {
        throw new Exception('Chyba při ukládání souboru');
    }
    
    // Return relative path for storage in JSON
    return 'uploads/programs/' . $filename;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['program_title']) && isset($_POST['program_url'])) {
    try {
        $title = trim($_POST['program_title']);
        $url = trim($_POST['program_url']);
        $category = trim($_POST['program_category'] ?? 'productivity');
        $description = trim($_POST['program_description'] ?? '');
        $price = trim($_POST['program_price'] ?? '');
        $license = trim($_POST['program_license'] ?? '');

        // Validate URL
        if (filter_var($url, FILTER_VALIDATE_URL) === false) {
            echo '<script>alert("Neplatná URL adresa."); window.location.href = "index.php#programs";</script>';
            exit;
        }

        // Valid categories
        $validCategories = ['design', 'development', 'office', 'media', 'security', 'productivity'];
        if (!in_array($category, $validCategories)) {
            $category = 'productivity'; // Default if invalid
        }

        // Handle image upload
        $imagePath = null;
        if (isset($_FILES['program_image'])) {
            $imagePath = handleImageUpload($_FILES['program_image'], $title);
        }

        // Create directory structure if it doesn't exist
        $programsDir = __DIR__ . '/programs/';
        $categoryDir = $programsDir . $category . '/';

        if (!is_dir($programsDir)) {
            mkdir($programsDir, 0777, true);
        }

        if (!is_dir($categoryDir)) {
            mkdir($categoryDir, 0777, true);
        }

        // Create program data
        $dateAdded = time();
        $programData = [
            'title' => $title,
            'url' => $url,
            'description' => $description,
            'price' => $price,
            'license_type' => $license,
            'image' => $imagePath,
            'date_added' => $dateAdded,
            'category' => $category
        ];

        // Generate filename from sanitized title and creation date
        $safeTitle = preg_replace('/[^a-z0-9]+/', '-', strtolower($title));
        $filename = $categoryDir . $dateAdded . '-' . $safeTitle . '.json';

        // Save program data
        file_put_contents($filename, json_encode($programData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        // Use JavaScript to show success message and refresh
        echo '<script>
            alert("Program byl úspěšně přidán.");
            window.location.href = "index.php#programs";
        </script>';
        exit;
        
    } catch (Exception $e) {
        echo '<script>alert("' . addslashes($e->getMessage()) . '"); window.location.href = "index.php#programs";</script>';
        exit;
    }
}

// If we get here, something went wrong
echo '<script>
    alert("Chyba při ukládání programu.");
    window.location.href = "index.php#programs";
</script>';
?>
