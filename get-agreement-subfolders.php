<?php
session_start();
require_once 'access.php';

header('Content-Type: application/json; charset=utf-8');

// Check if user has access
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$subfolders = [];
$agreementDir = __DIR__ . '/agreements/';

if (is_dir($agreementDir)) {
    $categories = array_diff(scandir($agreementDir), ['.', '..']);
    
    foreach ($categories as $category) {
        $categoryPath = $agreementDir . $category . '/';
        
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
?>
