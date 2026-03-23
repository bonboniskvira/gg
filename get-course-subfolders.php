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

// Get existing categories - scan actual directories like the courses page does
function getCourseCategories()
{
    $coursesDir = __DIR__ . '/courses/';
    $categories = [];

    if (is_dir($coursesDir)) {
        $items = array_diff(scandir($coursesDir), ['.', '..']);

        foreach ($items as $item) {
            $itemPath = $coursesDir . $item;
            if (is_dir($itemPath)) {
                $categories[$item] = ucfirst($item);
            }
        }
    }

    // If no categories exist, provide defaults
    if (empty($categories)) {
        $categories = [
            'training' => 'Školení',
            'materials' => 'Materiály'
        ];
    }

    return $categories;
}

$courseDir = __DIR__ . '/courses/';
$subfolderData = [];

// Get dynamic categories
$courseCategories = getCourseCategories();

// Scan each category for subfolders
foreach ($courseCategories as $categoryKey => $categoryName) {
    $categoryDir = $courseDir . $categoryKey . '/';
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
