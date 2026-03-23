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

$edoDir = __DIR__ . '/edo/';
$subfolderData = [];

// Define EDO categories - Updated to match new structure
$edoCategories = [
    'physical' => 'Fyzické běhy',
    'online' => 'Online'
];

// Scan each category for subfolders
foreach ($edoCategories as $categoryKey => $categoryName) {
    $categoryDir = $edoDir . $categoryKey . '/';
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
