<?php
// Prevent any output before headers
ob_start();

session_start();
require_once 'access.php';

// Clean any previous output
ob_clean();

// Set headers
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-cache, must-revalidate');

// Check if user has access
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$pdfDir = __DIR__ . '/pdf/';
$subfolderData = [];

// Get categories by scanning the pdf directory
$pdfCategories = [];
if (is_dir($pdfDir)) {
    $items = array_diff(scandir($pdfDir), ['.', '..']);
    foreach ($items as $item) {
        if (is_dir($pdfDir . $item)) {
            $pdfCategories[$item] = str_replace('_', ' ', ucfirst($item));
        }
    }
}

// Scan each category for subfolders
foreach ($pdfCategories as $categoryKey => $categoryName) {
    $categoryDir = $pdfDir . $categoryKey . '/';
    $subfolders = [];
    
    if (is_dir($categoryDir)) {
        $items = array_diff(scandir($categoryDir), ['.', '..']);
        
        foreach ($items as $item) {
            $itemPath = $categoryDir . $item;
            if (is_dir($itemPath)) {
                $subfolders[] = $item;
            }
        }
    }
    
    if (!empty($subfolders)) {
        $subfolderData[$categoryKey] = $subfolders;
    }
}

echo json_encode($subfolderData, JSON_UNESCAPED_UNICODE);
exit;
?>
