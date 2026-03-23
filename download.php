<?php
// Include necessary configurations
require_once __DIR__ . '/config/config.php';

// Initialize session if not started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Check user authentication
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

if (isset($_GET['file']) && isset($_GET['type'])) {
    $file = $_GET['file'];
    $type = $_GET['type'];
    
    // Prevent directory traversal
    $file = str_replace('..', '', $file);
    
    // Define base path based on type
    switch ($type) {
        case 'documents':
            $basePath = BASE_PATH . '/uploads/documents/';
            break;
        case 'edo':
            $basePath = BASE_PATH . '/uploads/edo/';
            break;
        default:
            die('Invalid file type.');
    }
    
    $filePath = $basePath . $file;
    
    // Check if file exists
    if (file_exists($filePath) && is_file($filePath)) {
        // Set headers for download
        header('Content-Description: File Transfer');
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename=' . basename($filePath));
        header('Expires: 0');
        header('Cache-Control: must-revalidate');
        header('Pragma: public');
        header('Content-Length: ' . filesize($filePath));
        
        // Clean output buffer
        ob_clean();
        flush();
        
        // Read file and output
        readfile($filePath);
        exit;
    } else {
        die('File not found.');
    }
} else {
    die('Invalid request.');
}
?>
