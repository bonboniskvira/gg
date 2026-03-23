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

$subfolders = [];
$marketingDir = __DIR__ . '/marketing/';

if (is_dir($marketingDir)) {
    $categories = array_diff(scandir($marketingDir), ['.', '..']);
    
    foreach ($categories as $category) {
        $categoryPath = $marketingDir . $category . '/';
        
        if (is_dir($categoryPath)) {
            $items = array_diff(scandir($categoryPath), ['.', '..']);
            $categorySubfolders = [];
            
            foreach ($items as $item) {
                $itemPath = $categoryPath . $item;
                if (is_dir($itemPath)) {
                    $categorySubfolders[] = $item;
                }
            }
            
            if (!empty($categorySubfolders)) {
                $subfolders[$category] = $categorySubfolders;
            }
        }
    }
}

echo json_encode($subfolders, JSON_UNESCAPED_UNICODE);
exit;
?>
