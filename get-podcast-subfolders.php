<?php
// Prevent any output before headers
ob_start();

// Clean any previous output
ob_clean();

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-cache, must-revalidate');

require_once 'access.php';

$subfolders = [];

try {
    $podcastDir = getRoot('podcast');
    
    if (is_dir($podcastDir)) {
        $categories = array_diff(scandir($podcastDir), ['.', '..']);
        
        foreach ($categories as $category) {
            $categoryPath = $podcastDir . $category;
            if (is_dir($categoryPath)) {
                $items = array_diff(scandir($categoryPath), ['.', '..']);
                $categorySubfolders = [];
                
                foreach ($items as $item) {
                    $itemPath = $categoryPath . '/' . $item;
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
    
    echo json_encode($subfolders);
} catch (Exception $e) {
    echo json_encode([]);
}

// End output buffering and send response
ob_end_flush();
exit;
?>
