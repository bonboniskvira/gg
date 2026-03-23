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

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['link_title']) && isset($_POST['link_url'])) {
    $title = trim($_POST['link_title']);
    $url = trim($_POST['link_url']);
    $category = trim($_POST['link_category'] ?? 'general');
    $description = trim($_POST['link_description'] ?? '');

    // Validate URL
    if (filter_var($url, FILTER_VALIDATE_URL) === false) {
        echo '<script>alert("Neplatná URL adresa."); window.location.href = "index.php#links";</script>';
        exit;
    }

    // Valid categories
    $validCategories = ['general', 'tools', 'courses', 'resources', 'partners'];
    if (!in_array($category, $validCategories)) {
        $category = 'general'; // Default if invalid
    }

    // Create directory structure if it doesn't exist
    $linksDir = __DIR__ . '/links/';
    $categoryDir = $linksDir . $category . '/';

    if (!is_dir($linksDir)) {
        mkdir($linksDir, 0777, true);
    }

    if (!is_dir($categoryDir)) {
        mkdir($categoryDir, 0777, true);
    }

    // Create link data
    $linkData = [
        'title' => $title,
        'url' => $url,
        'description' => $description,
        'date_added' => time(),
        'category' => $category
    ];

    // Generate filename from sanitized title and current timestamp
    $safeTitle = preg_replace('/[^a-z0-9]+/', '-', strtolower($title));
    $filename = $categoryDir . time() . '-' . $safeTitle . '.json';

    // Save link data
    file_put_contents($filename, json_encode($linkData, JSON_PRETTY_PRINT));

    // Use JavaScript to show success message and refresh
    echo '<script>
        alert("Odkaz byl úspěšně přidán.");
        window.location.href = "index.php#links";
    </script>';
    exit;
}

// If we get here, something went wrong
echo '<script>
    alert("Chyba při ukládání odkazu.");
    window.location.href = "index.php#links";
</script>';
?>


