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

$docDir = getRoot('doc');
$subfolderData = [];

// Scan all directories in /doc/ for subfolders
if (is_dir($docDir)) {
    $categories = array_diff(scandir($docDir), ['.', '..', 'categories.json']);
    foreach ($categories as $categoryKey) {
        $categoryDir = $docDir . $categoryKey . '/';
        if (is_dir($categoryDir)) {
            $subfolders = [];
            $items = array_diff(scandir($categoryDir), ['.', '..']);
            
            foreach ($items as $item) {
                $itemPath = $categoryDir . $item;
                if (is_dir($itemPath)) {
                    $subfolders[] = $item;
                }
            }
            
            if (!empty($subfolders)) {
                $subfolderData[$categoryKey] = $subfolders;
            }
        }
    }
}

echo json_encode($subfolderData, JSON_UNESCAPED_UNICODE);
exit;
?>
