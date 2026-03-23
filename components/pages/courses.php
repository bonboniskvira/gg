<?php
// BUFFER OUTPUT IMMEDIATELY TO FIX "HEADERS ALREADY SENT"
ob_start();

// Set proper encoding for Czech characters  
mb_internal_encoding('UTF-8');

require_once 'access.php';

// Handle file move request
if (isset($_POST['move_course_file']) && isAdmin()) {
    // CLEAN BUFFER TO ENSURE NO HTML IS SENT BEFORE HEADERS
    while (ob_get_level()) {
        ob_end_clean();
    }

    $fileName = trim($_POST['fileName'] ?? '');
    $sourceCategory = trim($_POST['sourceCategory'] ?? '');
    $sourceSubfolder = trim($_POST['sourceSubfolder'] ?? '');
    $targetCategory = trim($_POST['targetCategory'] ?? '');
    $targetSubfolder = trim($_POST['targetSubfolder'] ?? '');

    // Validate inputs
    if (empty($fileName) || empty($sourceCategory) || empty($targetCategory)) {
        http_response_code(400);
        exit;
    }

    // Security check
    $inputs = [$fileName, $sourceCategory, $sourceSubfolder, $targetCategory, $targetSubfolder];
    foreach ($inputs as $input) {
        if (strpos($input, '..') !== false || strpos($input, '/') !== false || strpos($input, '\\') !== false) {
            http_response_code(400);
            exit;
        }
    }

    // Check if same location
    if ($sourceCategory === $targetCategory && $sourceSubfolder === $targetSubfolder) {
        http_response_code(400);
        exit;
    }

    try {
        // Build paths
        $courseBaseDir = getRoot('courses');
        $sourcePath = $courseBaseDir . $sourceCategory . '/' . ($sourceSubfolder ? $sourceSubfolder . '/' : '');
        $targetPath = $courseBaseDir . $targetCategory . '/' . ($targetSubfolder ? $targetSubfolder . '/' : '');
        $sourceFile = $sourcePath . $fileName;

        // Check source exists
        if (!file_exists($sourceFile)) {
            http_response_code(404);
            exit;
        }

        // Create target directory if needed
        if (!is_dir($targetPath)) {
            if (!mkdir($targetPath, 0755, true)) {
                http_response_code(500);
                exit;
            }
        }

        // Handle filename conflicts
        $targetFile = $targetPath . $fileName;
        $counter = 1;
        while (file_exists($targetFile)) {
            $info = pathinfo($fileName);
            $name = $info['filename'];
            $ext = isset($info['extension']) ? '.' . $info['extension'] : '';
            $targetFile = $targetPath . $name . "_({$counter})" . $ext;
            $counter++;
        }

        // Move the file
        if (rename($sourceFile, $targetFile)) {
            http_response_code(200);
            exit;
        } else {
            http_response_code(500);
            exit;
        }
    } catch (Exception $e) {
        http_response_code(500);
        exit;
    }
}

// Get existing categories - scan actual directories like the list page does
function getCourseCategories()
{
    $coursesDir = getRoot('courses');
    $categories = [];

    if (is_dir($coursesDir)) {
        $items = array_diff(scandir($coursesDir), ['.', '..']);

        foreach ($items as $item) {
            $itemPath = $coursesDir . $item;
            if (is_dir($itemPath)) {
                // Assign different colors to different categories
                $colors = ['#2ecc71', '#3498db', '#9b59b6', '#e74c3c', '#f39c12', '#1abc9c'];
                $colorIndex = crc32($item) % count($colors);

                $categories[$item] = [
                    'name' => ucfirst($item), // Capitalize first letter
                    'color' => $colors[$colorIndex]
                ];
            }
        }
    }

    // If no categories exist, provide defaults
    if (empty($categories)) {
        $categories = [
            'training' => ['name' => 'Školení', 'color' => '#2ecc71'],
            'materials' => ['name' => 'Materiály', 'color' => '#3498db']
        ];
    }

    return $categories;
}

// Get dynamic categories
$courseCategories = getCourseCategories();

// Helper function to format file size (only if not already declared)
if (!function_exists('formatBytes')) {
    function formatBytes($size, $precision = 2)
    {
        $units = array('B', 'KB', 'MB', 'GB', 'TB');
        for ($i = 0; $size > 1024 && $i < count($units) - 1; $i++) {
            $size /= 1024;
        }
        return round($size, $precision) . ' ' . $units[$i];
    }
}

// Helper function to get file type info
function getCourseFileTypeInfo($extension)
{
    $extension = strtolower($extension);

    switch ($extension) {
        // PDF files
        case 'pdf':
            return [
                'type' => 'pdf',
                'icon' => 'fas fa-file-pdf',
                'color' => '#dc3545',
                'viewable' => true
            ];

            // Document files
        case 'doc':
        case 'docx':
            return [
                'type' => 'document',
                'icon' => 'fas fa-file-word',
                'color' => '#007bff',
                'viewable' => false
            ];

            // Excel files
        case 'xls':
        case 'xlsx':
            return [
                'type' => 'spreadsheet',
                'icon' => 'fas fa-file-excel',
                'color' => '#28a745',
                'viewable' => false
            ];

            // PowerPoint files
        case 'ppt':
        case 'pptx':
            return [
                'type' => 'presentation',
                'icon' => 'fas fa-file-powerpoint',
                'color' => '#fd7e14',
                'viewable' => false
            ];

            // Image files
        case 'jpg':
        case 'jpeg':
        case 'png':
        case 'gif':
            return [
                'type' => 'image',
                'icon' => 'fas fa-file-image',
                'color' => '#6f42c1',
                'viewable' => true
            ];

            // Text files
        case 'txt':
        case 'rtf':
            return [
                'type' => 'text',
                'icon' => 'fas fa-file-alt',
                'color' => '#6c757d',
                'viewable' => false
            ];

        default:
            return [
                'type' => 'other',
                'icon' => 'fas fa-file',
                'color' => '#6c757d',
                'viewable' => false
            ];
    }
}

// Function to scan for subfolders and course files
function scanCoursesRecursive($dir, $categoryKey, $categoryInfo, $depth = 0)
{
    $result = ['folders' => [], 'documents' => []];

    if (!is_dir($dir)) return $result;

    $items = array_diff(scandir($dir), ['.', '..']);

    foreach ($items as $item) {
        $itemPath = $dir . $item;

        if (is_dir($itemPath)) {
            // It's a subfolder
            $subResult = scanCoursesRecursive($itemPath . '/', $categoryKey, $categoryInfo, $depth + 1);
            $result['folders'][$item] = [
                'name' => $item,
                'path' => $itemPath,
                'data' => $subResult,
                'depth' => $depth
            ];
        } else {
            // It's a file - add it directly
            $extension = strtolower(pathinfo($item, PATHINFO_EXTENSION));

            // Include multiple file types
            $allowedExtensions = ['pdf', 'doc', 'docx', 'txt', 'rtf', 'xls', 'xlsx', 'ppt', 'pptx', 'jpg', 'jpeg', 'png', 'gif'];

            if (in_array($extension, $allowedExtensions)) {
                $fileTypeInfo = getCourseFileTypeInfo($extension);

                $fileData = [
                    'name' => pathinfo($item, PATHINFO_FILENAME),
                    'original_name' => $item,
                    'file_path' => str_replace($_SERVER['DOCUMENT_ROOT'], '', $itemPath),
                    'file_size' => filesize($itemPath),
                    'file_extension' => $extension,
                    'date_added' => filemtime($itemPath),
                    'has_file' => true,
                    'category_info' => $categoryInfo,
                    'id' => pathinfo($item, PATHINFO_FILENAME),
                    'file_type_info' => $fileTypeInfo
                ];

                $result['documents'][] = $fileData;
            }
        }
    }

    return $result;
}

// Function to count all documents recursively
function countAllCourseDocuments($categoryData)
{
    $count = count($categoryData['documents']); // Count documents in main category

    // Add documents from all subfolders
    if (!empty($categoryData['folders'])) {
        foreach ($categoryData['folders'] as $folder) {
            $count += countAllCourseDocuments($folder['data']);
        }
    }

    return $count;
}
?>

<!-- ...existing CSS styles... -->
<style>
    /* Import existing styles and add course-specific styles */
    #courses .content-area {
        display: flex;
        flex-direction: column;
        gap: 30px;
    }
    .course-grid-container {
        height: auto !important;
        min-height: 0 !important;
        flex-grow: 0; /* Zabrání natahování ve flexu */
        align-self: flex-start; /* Zarovná se na začátek a nebude se natahovat na celou výšku */
        width: 100%;
    }

    .add-course-section {
        background: white;
        border-radius: 10px;
        padding: 25px;
        margin-bottom: 25px;
        box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
    }

    .add-course-section h2 {
        color: #2c3e50;
        margin-top: 0;
        margin-bottom: 20px;
        font-size: 20px;
    }

    .course-form {
        display: flex;
        flex-direction: column;
        gap: 15px;
    }

    .course-grid {
        display: flex;
        flex-direction: column;
        gap: 20px;
    }

    /* Course category styling - same as EDO */
    .course-category {
        background: white;
        border-radius: 10px;
        box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        overflow: hidden;
        transition: all 0.3s ease;
    }

    .course-category.expanded {
        grid-column: 1 / -1;
        max-width: none;
        transform: none !important;
        z-index: 10;
    }

    .course-category.expanded .category-content {
        max-height: none !important;
        padding: 20px !important;
        display: block !important;
        opacity: 1 !important;
        overflow: visible !important;
    }

    .course-category.expanded .category-toggle i {
        transform: rotate(180deg);
    }

    .course-category:hover:not(.expanded) {
        box-shadow: 0 5px 15px rgba(0, 0, 0, 0.15);
    }

    .course-category:not(.expanded) .category-content {
        max-height: 0 !important;
        padding: 0 20px !important;
        overflow: hidden !important;
        opacity: 0 !important;
        display: none !important;
    }

    .categories-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
        gap: 20px;
        margin-bottom: 30px;
        grid-auto-flow: row;
    }

    /* Copy all other styles from EDO... */
    .category-header {
        padding: 20px;
        background: #f8f9fa;
        border-bottom: 1px solid #e9ecef;
        cursor: pointer;
        transition: background 0.2s;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    .category-header:hover {
        background: #e9ecef;
    }

    .category-info h3 {
        margin: 0 0 5px 0;
        font-size: 18px;
        font-weight: 600;
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .course-count {
        font-size: 13px;
        color: #6c757d;
        font-weight: 500;
    }

    .category-toggle {
        color: #6c757d;
        transition: transform 0.3s ease;
    }

    .category-toggle i {
        transition: transform 0.3s ease;
    }

    .category-content {
        transition: all 0.3s ease;
        background: white;
    }

    /* Category actions styling */
    .category-actions {
        margin-bottom: 20px;
        display: flex;
        gap: 10px;
    }

    .add-course-subfolder-btn {
        background-color: #138496 !important;
        color: white !important;
        border: none;
        padding: 8px 16px;
        border-radius: 6px;
        font-size: 13px;
        cursor: pointer;
        display: flex;
        align-items: center;
        gap: 6px;
        transition: background 0.2s;
    }

    .add-course-subfolder-btn:hover {
        background: #138496 !important;
    }

    /* Action button styling for courses */
    .action-btn {
        background: #17a2b8;
        color: white;
        border: none;
        padding: 8px 16px;
        border-radius: 6px;
        font-size: 13px;
        cursor: pointer;
        display: flex;
        align-items: center;
        gap: 6px;
        transition: background 0.2s;
    }

    .action-btn:hover {
        background: #138496;
    }

    /* Subfolder styling */
    .course-subfolders-section {
        margin-bottom: 25px;
    }

    .course-subfolders-section h4 {
        color: #495057;
        font-size: 14px;
        font-weight: 600;
        margin: 0 0 12px 0;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    .course-subfolders-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
        gap: 12px;
    }

    .course-subfolder-card {
        background: #f8f9fa;
        border: 1px solid #e9ecef;
        border-radius: 8px;
        padding: 15px;
        cursor: pointer;
        transition: all 0.2s;
    }

    .course-subfolder-card:hover {
        background: #e9ecef;
        transform: translateY(-1px);
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
    }

    .course-subfolder-card.open {
        grid-column: 1 / -1;
        width: 100%;
        max-width: none;
        background: #fff;
        border-color: #dee2e6;
    }

    .course-subfolder-card.open .subfolder-content {
        max-height: 1000px;
        margin-top: 12px;
        padding-top: 12px;
        border-top: 1px solid #dee2e6;
    }

    .course-subfolder-card.open .subfolder-toggle {
        transform: rotate(90deg);
    }

    .subfolder-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    .subfolder-info {
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .subfolder-info h5 {
        margin: 0;
        font-size: 14px;
        font-weight: 600;
        color: #495057;
    }

    .subfolder-stats {
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .subfolder-toggle {
        color: #6c757d;
        font-size: 12px;
        transition: transform 0.2s;
    }

    .subfolder-content {
        max-height: 0;
        overflow: hidden;
        transition: max-height 0.3s ease, margin-top 0.3s ease, padding-top 0.3s ease;
        margin-top: 0;
        padding-top: 0;
    }

    .subfolder-documents {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
        gap: 10px;
    }

    .subfolder-document {
        background: white;
        border-radius: 8px;
        padding: 10px;
        display: flex;
        align-items: center;
        justify-content: space-between;
        box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
        transition: transform 0.2s;
    }

    .subfolder-document:hover {
        background: #f8f9fa;
        border-color: #dee2e6;
    }

    .doc-icon {
        flex-shrink: 0;
        font-size: 16px;
    }

    .doc-info {
        flex: 1;
        min-width: 0;
    }

    .doc-name {
        font-size: 12px;
        font-weight: 500;
        color: #495057;
        display: block;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .doc-size {
        font-size: 10px;
        color: #6c757d;
    }

    .doc-actions {
        display: flex;
        gap: 4px;
        flex-shrink: 0;
    }

    .empty-subfolder {
        text-align: center;
        padding: 15px;
        color: #6c757d;
        font-size: 12px;
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: 6px;
    }

    /* Documents Section */
    .course-documents-section {
        margin-top: 0 !important;
        padding-top: 0 !important;
    }

    .course-documents-section h4 {
        color: #495057;
        font-size: 14px;
        font-weight: 600;
        margin: 0 0 12px 0;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    .course-documents-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
        gap: 15px;
    }

    .course-card-grid {
        background: white;
        border: 1px solid #e9ecef;
        border-radius: 8px;
        overflow: hidden;
        transition: all 0.2s;
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
        opacity: 1 !important;
        display: block !important;
    }

    .course-card-grid:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
        border-color: #dee2e6;
    }

    .course-preview {
        height: 120px;
        display: flex;
        align-items: center;
        justify-content: center;
        background: #f8f9fa;
        border-bottom: 1px solid #e9ecef;
        overflow: hidden;
    }

    .file-preview-icon i {
        font-size: 48px;
        opacity: 0.8;
    }

    .course-info-grid {
        padding: 15px;
        display: flex;
        flex-direction: column;
        gap: 8px;
    }

    .course-title {
        font-size: 14px;
        font-weight: 600;
        color: #495057;
        margin: 0;
        line-height: 1.3;
        display: -webkit-box;
        -webkit-line-clamp: 2;
        line-clamp: 2;
        -webkit-box-orient: vertical;
        overflow: hidden;
    }

    .course-meta-grid {
        display: flex;
        justify-content: space-between;
        align-items: center;
        font-size: 11px;
        color: #6c757d;
        margin-top: auto;
        flex-wrap: wrap;
        gap: 8px;
    }

    .course-date,
    .course-size {
        display: flex;
        align-items: center;
        gap: 4px;
    }

    .course-actions-grid {
        display: flex;
        gap: 6px;
        justify-content: flex-end;
        margin-top: 8px;
        padding-top: 8px;
        border-top: 1px solid #f0f0f0;
    }

    .no-course {
        text-align: center;
        padding: 60px 20px;
        color: #6c757d;
        background: white;
        border-radius: 10px;
        box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
    }

    /* Modal styles - same as EDO */
    .modal {
        position: fixed;
        z-index: 1000;
        left: 0;
        top: 0;
        width: 100%;
        height: 100%;
        background-color: rgba(0, 0, 0, 0.5);
        display: flex;
        justify-content: center;
        align-items: center;
    }

    .modal-content {
        background: white;
        border-radius: 10px;
        width: 90%;
        max-width: 600px;
        max-height: 90vh;
        overflow-y: auto;
        box-shadow: 0 10px 25px rgba(0, 0, 0, 0.3);
    }

    .modal-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 25px 30px 20px;
        border-bottom: 1px solid #eee;
    }

    .modal-header h2 {
        margin: 0;
        color: #2c3e50;
        font-size: 20px;
    }

    .modal-close {
        background: none;
        border: none;
        font-size: 24px;
        cursor: pointer;
        color: #95a5a6;
        padding: 0;
        width: 30px;
        height: 30px;
        display: flex;
        align-items: center;
        justify-content: center;
        border-radius: 50%;
        transition: all 0.2s;
    }

    .modal-close:hover {
        background: #ecf0f1;
        color: #e74c3c;
    }

    /* Admin button styles - copied from list.php */
    .category-admin-actions {
        display: flex;
        gap: 4px;
        margin-left: auto;
        margin-right: 10px;
    }

    .admin-btn {
        background: #6c757d;
        color: white;
        border: none;
        padding: 6px 8px;
        border-radius: 4px;
        cursor: pointer;
        font-size: 12px;
        transition: background 0.2s;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .admin-btn:hover {
        background: #5a6268;
    }

    .admin-btn.edit-btn {
        background: #007bff;
    }

    .admin-btn.edit-btn:hover {
        background: #0056b3;
    }

    .admin-btn.delete-btn {
        background: #dc3545;
    }

    .admin-btn.delete-btn:hover {
        background: #c82333;
    }

    /* Drag and Drop Styles */
    .course-card-grid.dragging,
    .subfolder-document.dragging {
        opacity: 0.5;
        transform: scale(0.95);
        cursor: grabbing;
    }

    .course-subfolder-card.drag-over {
        background: #e3f2fd !important;
        border-color: #2196f3 !important;
        box-shadow: 0 0 15px rgba(33, 150, 243, 0.3) !important;
    }

    .course-category.drag-over {
        background: #f3e5f5 !important;
        border-color: #9c27b0 !important;
        box-shadow: 0 0 15px rgba(156, 39, 176, 0.3) !important;
    }

    .drag-handle {
        cursor: grab;
        color: #6c757d !important;
        transition: color 0.2s !important;
        position: absolute !important;
        top: 0px !important;
        right: 0px !important;
        font-size: 12px !important;
        opacity: 0.7;
    }

    .drag-handle:hover {
        color: #495057;
        opacity: 1;
    }

    .drag-handle:active {
        cursor: grabbing;
    }

    .drop-zone-indicator {
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: rgba(33, 150, 243, 0.1);
        border: 2px dashed #2196f3;
        border-radius: 8px;
        display: none;
        align-items: center;
        justify-content: center;
        font-size: 14px;
        font-weight: 600;
        color: #2196f3;
        z-index: 10;
    }

    .course-subfolder-card.drag-over .drop-zone-indicator,
    .course-category.drag-over .drop-zone-indicator {
        display: flex;
    }

    .draggable-file {
        cursor: grab;
        transition: transform 0.2s, box-shadow 0.2s;
    }

    .draggable-file:hover {
        transform: translateY(-1px);
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
    }

    .course-subfolder-card,
    .course-category {
        position: relative;
    }

    /* ...existing code... */
</style>

<div class="page-content" id="courses">
    <div class="content-area">
        <!-- Status Messages -->
        <div id="status-messages"></div>

        <!-- Course Grid Container -->
        <div class="course-grid-container">
            <?php
            $courseDir = getRoot('courses');
            $coursesByCategory = [];

            // Scan for course files and subfolders using dynamic categories
            if (is_dir($courseDir)) {
                foreach ($courseCategories as $categoryKey => $categoryInfo) {
                    $categoryDir = $courseDir . $categoryKey . '/';
                    if (is_dir($categoryDir)) {
                        $categoryData = scanCoursesRecursive($categoryDir, $categoryKey, $categoryInfo);
                        if (!empty($categoryData['documents']) || !empty($categoryData['folders'])) {
                            $coursesByCategory[$categoryKey] = [
                                'info' => $categoryInfo,
                                'data' => $categoryData
                            ];
                        } else if (isAdmin()) {
                            // Show empty categories to admins so they can manage them
                            $coursesByCategory[$categoryKey] = [
                                'info' => $categoryInfo,
                                'data' => $categoryData
                            ];
                        }
                    }
                }
            }

            if (empty($coursesByCategory)): ?>
                <div class="no-course">
                    <i class="fas fa-graduation-cap"></i>
                    <p>Zatím nejsou žádné kurzy v systému.</p>
                </div>
            <?php else: ?>
                <div class="categories-grid">
                    <?php foreach ($coursesByCategory as $categoryKey => $category): ?>
                        <div class="course-category droppable-zone"
                            data-category="<?= $categoryKey ?>"
                            style="border-top: 4px solid <?= $category['info']['color'] ?>"
                            ondrop="handleCourseDrop(event)"
                            ondragover="handleCourseDragOver(event)"
                            ondragenter="handleCourseDragEnter(event)"
                            ondragleave="handleCourseDragLeave(event)">

                            <div class="drop-zone-indicator">
                                <i class="fas fa-file-import"></i> &nbsp; Přetáhněte soubor do hlavní složky
                            </div>

                            <div class="category-header category-header-clickable" onclick="toggleCourseCategoryHeader('<?= $categoryKey ?>')">
                                <div class="category-info">
                                    <h3 style="color: <?= $category['info']['color'] ?>">
                                        <i class="fas fa-folder"></i>
                                        <?= htmlspecialchars($category['info']['name']) ?>
                                    </h3>
                                    <span class="course-count">
                                        <?= countAllCourseDocuments($category['data']) ?> souborů
                                        <?php if (!empty($category['data']['folders'])): ?>
                                            • <?= count($category['data']['folders']) ?> podsložek
                                        <?php endif; ?>
                                    </span>
                                </div>

                                <?php if (isAdmin()): ?>
                                    <div class="category-admin-actions" onclick="event.stopPropagation()">
                                        <button class="admin-btn edit-btn" onclick="editCourseCategory('<?= htmlspecialchars($categoryKey, ENT_QUOTES, 'UTF-8') ?>')" title="Přejmenovat kategorii">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <button class="admin-btn delete-btn" onclick="deleteCourseCategory('<?= htmlspecialchars($categoryKey, ENT_QUOTES, 'UTF-8') ?>')" title="Smazat kategorii">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </div>
                                <?php endif; ?>

                                <div class="category-toggle">
                                    <i class="fas fa-chevron-down"></i>
                                </div>
                            </div>

                            <div class="category-content" id="course-category-content-<?= $categoryKey ?>">
                                <?php if (canUpload()): ?>
                                    <div class="category-actions">
                                        <button class="action-btn add-course-subfolder-btn" onclick="showAddCourseSubfolderModal('<?= $categoryKey ?>')">
                                            <i class="fas fa-folder-plus"></i> Přidat podsložku
                                        </button>
                                    </div>
                                <?php endif; ?>

                                <!-- Subfolders Grid -->
                                <?php if (!empty($category['data']['folders'])): ?>
                                    <div class="course-subfolders-section">
                                        <h4>Podsložky</h4>
                                        <div class="course-subfolders-grid">
                                            <?php foreach ($category['data']['folders'] as $folderName => $folderData): ?>
                                                <?php renderCourseSubfolder($folderName, $folderData, $categoryKey, $category['info']); ?>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                <?php endif; ?>

                                <!-- Course files in main category -->
                                <?php if (!empty($category['data']['documents'])): ?>
                                    <div class="course-documents-section">
                                        <h4>Soubory kurzů</h4>
                                        <div class="course-documents-grid">
                                            <?php foreach ($category['data']['documents'] as $course): ?>
                                                <?php renderCourseCard($course, $categoryKey); ?>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- Add Course Section -->
        <?php if (canUpload()): ?>
            <div class="add-course-section">
                <h2>Nahrát soubor</h2>
                <form action="upload-category-form-handler.php" method="post" enctype="multipart/form-data" class="course-form">

                    <input type="hidden" name="type" value="courses">
                    <input type="hidden" name="redirect" value="courses">

                    <div class="form-row">
                        <div class="form-group">
                            <label for="custom-course-folder-name">Vytvořit novou kategorii (volitelné)</label>
                            <input type="text" id="custom-course-folder-name" name="custom_category" class="full-width"
                                   placeholder="Zadejte název pro novou kategorii..."
                                   onchange="toggleCourseFolderInputs()"
                                   oninput="toggleCourseFolderInputs()">
                            <small class="file-help">Pokud zadáte název, vytvoří se nová kategorie s tímto názvem</small>
                        </div>
                    </div>

                    <div class="form-row" id="existing-course-folder-row">
                        <div class="form-group">
                            <label for="course-category">Nebo vyberte existující kategorii</label>
                            <select id="course-category" name="category" class="full-width" onchange="updateCourseSubfolderOptions()">
                                <option value="">-- Vyberte kategorii --</option>
                                <?php foreach ($courseCategories as $key => $category): ?>
                                    <option value="<?= $key ?>"><?= htmlspecialchars($category['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="course-subfolder">Podsložka</label>
                            <select id="course-subfolder" name="subfolder" class="full-width">
                                <option value="">-- Hlavní složka --</option>
                            </select>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="course_file">Soubor *</label>
                            <div class="upload-area">
                                <label for="course_file" class="custom-file-button">Vyberte soubor</label>
                                <input type="file" name="file" id="course_file" accept=".pdf,.doc,.docx,.txt,.rtf,.xls,.xlsx,.ppt,.pptx,.jpg,.jpeg,.png,.gif" class="hidden-file-input" required>
                                <div id="selected-file" class="selected-files-info"></div>
                            </div>
                        </div>
                    </div>

                    <div class="form-row">
                        <button type="submit" class="upload-btn" name="course_submit">
                            <i class="fas fa-upload"></i> Nahrát soubor
                        </button>
                    </div>
                </form>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php
// Function to render course subfolder
function renderCourseSubfolder($folderName, $folderData, $categoryKey, $categoryInfo)
{
?>
    <div class="course-subfolder-card droppable-zone"
        data-category="<?= htmlspecialchars($categoryKey) ?>"
        data-subfolder="<?= htmlspecialchars($folderName) ?>"
        onclick="toggleCourseSubfolder('<?= $categoryKey ?>-<?= $folderName ?>')"
        ondrop="handleCourseDrop(event)"
        ondragover="handleCourseDragOver(event)"
        ondragenter="handleCourseDragEnter(event)"
        ondragleave="handleCourseDragLeave(event)">

        <div class="drop-zone-indicator">
            <i class="fas fa-file-import"></i> &nbsp; Přetáhněte soubor sem
        </div>

        <div class="subfolder-header">
            <div class="subfolder-info">
                <i class="fas fa-folder" style="color: <?= $categoryInfo['color'] ?>"></i>
                <h5><?= htmlspecialchars($folderName) ?></h5>
            </div>
            <div class="subfolder-stats">
                <span class="doc-count"><?= count($folderData['data']['documents']) ?></span>
                <?php if (isAdmin()): ?>
                    <button class="mini-btn edit-btn" onclick="event.stopPropagation(); editCourseSubfolder('<?= $categoryKey ?>', '<?= $folderName ?>')" title="Upravit podsložku">
                        <i class="fas fa-edit"></i>
                    </button>
                    <button class="mini-btn delete-btn" onclick="event.stopPropagation(); deleteCourseSubfolder('<?= $categoryKey ?>', '<?= $folderName ?>')" title="Smazat podsložku">
                        <i class="fas fa-trash"></i>
                    </button>
                <?php endif; ?>
                <i class="fas fa-chevron-right subfolder-toggle"></i>
            </div>
        </div>

        <div class="subfolder-content">
            <div class="subfolder-documents">
                <?php if (!empty($folderData['data']['documents'])): ?>
                    <?php foreach ($folderData['data']['documents'] as $course): ?>
                        <div class="subfolder-document draggable-file"
                            draggable="true"
                            data-file-name="<?= htmlspecialchars($course['original_name']) ?>"
                            data-current-category="<?= htmlspecialchars($categoryKey) ?>"
                            data-current-subfolder="<?= htmlspecialchars($folderName) ?>"
                            ondragstart="handleCourseDragStart(event)"
                            ondragend="handleCourseDragEnd(event)">
                            <div class="doc-icon">
                                <i class="<?= $course['file_type_info']['icon'] ?>" style="color: <?= $course['file_type_info']['color'] ?>;"></i>
                                <i class="fas fa-arrows-alt drag-handle"></i>
                            </div>
                            <div class="doc-info">
                                <span class="doc-name"><?= htmlspecialchars($course['name']) ?></span>
                                <span class="doc-size"><?= formatBytes($course['file_size']) ?></span>
                            </div>
                            <div class="doc-actions">
                                <?php if ($course['file_type_info']['viewable']): ?>
                                    <a href="<?= htmlspecialchars($course['file_path']) ?>" target="_blank" class="mini-btn view-btn" onclick="event.stopPropagation();" title="Zobrazit">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                <?php endif; ?>

                                <a href="<?= htmlspecialchars($course['file_path']) ?>" download class="mini-btn download-btn" onclick="event.stopPropagation();" title="Stáhnout">
                                    <i class="fas fa-download"></i>
                                </a>

                                <?php if (isAdmin()): ?>
                                    <button class="mini-btn edit-btn" onclick="event.stopPropagation(); editCourseFile('<?= addslashes($course['original_name']) ?>', '<?= $categoryKey ?>', '<?= addslashes($folderName) ?>', '<?= addslashes($course['name']) ?>')" title="Přejmenovat">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <button class="mini-btn delete-btn" onclick="event.stopPropagation(); deleteCourseFile('<?= addslashes($course['original_name']) ?>', '<?= $categoryKey ?>', '<?= addslashes($folderName) ?>')" title="Smazat">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="empty-subfolder">
                        <i class="fas fa-folder-open"></i>
                        <span>Prázdná složka</span>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
<?php
}

// Function to render course card
function renderCourseCard($course, $categoryKey)
{
?>
    <div class="course-card-grid draggable-file"
        draggable="true"
        data-file-name="<?= htmlspecialchars($course['original_name']) ?>"
        data-current-category="<?= htmlspecialchars($categoryKey) ?>"
        data-current-subfolder=""
        ondragstart="handleCourseDragStart(event)"
        ondragend="handleCourseDragEnd(event)">
        <div class="course-preview">
            <i class="fas fa-arrows-alt drag-handle"></i>
            <div class="file-preview-icon">
                <i class="<?= $course['file_type_info']['icon'] ?>" style="color: <?= $course['file_type_info']['color'] ?>; font-size: 32px;"></i>
            </div>
        </div>

        <div class="course-info-grid">
            <h5 class="course-title"><?= htmlspecialchars($course['name']) ?></h5>

            <div class="course-meta-grid">
                <span class="course-date">
                    <i class="fas fa-calendar"></i>
                    <?= date('d.m.Y', $course['date_added']) ?>
                </span>
                <span class="course-size">
                    <i class="fas fa-file"></i>
                    <?= formatBytes($course['file_size']) ?>
                </span>
            </div>

            <div class="course-actions-grid">
                <?php if ($course['file_type_info']['viewable']): ?>
                    <a href="<?= htmlspecialchars($course['file_path']) ?>" target="_blank" class="grid-btn view-btn">
                        <i class="fas fa-eye"></i>
                    </a>
                <?php endif; ?>

                <a href="<?= htmlspecialchars($course['file_path']) ?>" download class="grid-btn download-btn">
                    <i class="fas fa-download"></i>
                </a>

                <?php if (isAdmin()): ?>
                    <button class="grid-btn edit-btn" onclick="editCourseFile('<?= addslashes($course['original_name']) ?>', '<?= $categoryKey ?>', '', '<?= addslashes($course['name']) ?>')">
                        <i class="fas fa-edit"></i>
                    </button>
                    <button class="grid-btn delete-btn" onclick="deleteCourseFile('<?= addslashes($course['original_name']) ?>', '<?= $categoryKey ?>', '')">
                        <i class="fas fa-trash"></i>
                    </button>
                <?php endif; ?>
            </div>
        </div>
    </div>
<?php
}
?>

<!-- Course management modals and JavaScript - similar to EDO -->
<!-- Add Course Subfolder Modal -->
<div id="addCourseSubfolderModal" class="modal" style="display: none;">
    <div class="modal-content">
        <div class="modal-header">
            <h2>Přidat podsložku kurzu</h2>
            <button class="modal-close" onclick="closeAddCourseSubfolderModal()">&times;</button>
        </div>
        <form id="addCourseSubfolderForm">
            <input type="hidden" id="courseParentCategory" name="parent_category">

            <div class="form-group">
                <label for="courseSubfolderName">Název podsložky *</label>
                <input type="text" id="courseSubfolderName" name="subfolder_name" required
                    placeholder="Název nové podsložky" class="full-width">
            </div>

            <div class="modal-actions">
                <button type="button" class="btn btn-secondary" onclick="closeAddCourseSubfolderModal()">Zrušit</button>
                <button type="submit" class="btn btn-primary">Vytvořit podsložku</button>
            </div>
        </form>
    </div>
</div>

<!-- Edit Course Subfolder Modal -->
<div id="editCourseSubfolderModal" class="modal" style="display: none;">
    <div class="modal-content">
        <div class="modal-header">
            <h2>Upravit podsložku kurzu</h2>
            <button class="modal-close" onclick="closeEditCourseSubfolderModal()">&times;</button>
        </div>
        <form id="editCourseSubfolderForm" enctype="multipart/form-data">
            <input type="hidden" id="editCourseParentCategory" name="parent_category">
            <input type="hidden" id="editCourseOriginalName" name="original_name">

            <div class="form-group">
                <label for="editCourseSubfolderName">Název podsložky *</label>
                <input type="text" id="editCourseSubfolderName" name="new_name" required
                    placeholder="Nový název podsložky" class="full-width">
            </div>

            <div class="form-group">
                <label for="editCourseSubfolderFile">Nahrát soubor do podsložky (volitelné)</label>
                <div class="upload-area">
                    <label for="editCourseSubfolderFile" class="custom-file-button">Vyberte soubor</label>
                    <input type="file" name="subfolder_file" id="editCourseSubfolderFile" accept=".pdf,.doc,.docx,.txt,.rtf,.xls,.xlsx,.ppt,.pptx,.jpg,.jpeg,.png,.gif" class="hidden-file-input">
                    <div id="edit-course-subfolder-selected-file" class="selected-files-info"></div>
                </div>
                <small class="file-help">Podporované formáty: PDF, DOC, DOCX, TXT, RTF, XLS, XLSX, PPT, PPTX, JPG, PNG, GIF (max 50MB)</small>
            </div>

            <div class="form-group">
                <label for="editCourseSubfolderCustomFilename">Přejmenovat soubor (volitelné)</label>
                <input type="text" id="editCourseSubfolderCustomFilename" name="custom_filename" class="full-width" placeholder="Ponechte prázdné pro původní název">
                <small class="file-help">Zadejte pouze název bez přípony (přípona se doplní automaticky)</small>
            </div>

            <div class="modal-actions">
                <button type="button" class="btn btn-secondary" onclick="closeEditCourseSubfolderModal()">Zrušit</button>
                <button type="submit" class="btn btn-primary">Uložit změny</button>
            </div>
        </form>
    </div>
</div>

<!-- Edit Course File Modal -->
<div id="editCourseFileModal" class="modal" style="display: none;">
    <div class="modal-content">
        <div class="modal-header">
            <h2>Přejmenovat soubor kurzu</h2>
            <button class="modal-close" onclick="closeEditCourseFileModal()">&times;</button>
        </div>
        <form id="editCourseFileForm">
            <input type="hidden" id="editCourseFileCategory" name="category">
            <input type="hidden" id="editCourseFileSubfolder" name="subfolder">
            <input type="hidden" id="editCourseFileOriginalName" name="original_name">

            <div class="form-group">
                <label for="editCourseFileName">Název souboru *</label>
                <input type="text" id="editCourseFileName" name="new_name" required
                    placeholder="Nový název souboru (bez přípony)" class="full-width">
                <small class="file-help">Zadejte pouze název bez přípony - přípona zůstane zachována</small>
            </div>

            <div class="modal-actions">
                <button type="button" class="btn btn-secondary" onclick="closeEditCourseFileModal()">Zrušit</button>
                <button type="submit" class="btn btn-primary">Přejmenovat</button>
            </div>
        </form>
    </div>
</div>

<!-- Edit Course Category Modal -->
<div id="editCourseCategoryModal" class="modal" style="display: none;">
    <div class="modal-content">
        <div class="modal-header">
            <h2>Upravit kategorii kurzu</h2>
            <button class="modal-close" onclick="closeEditCourseCategoryModal()">&times;</button>
        </div>
        <form id="editCourseCategoryForm" enctype="multipart/form-data">
            <input type="hidden" id="editCourseOriginalName" name="original_name">

            <div class="form-group">
                <label for="editCourseCategoryName">Název kategorie *</label>
                <input type="text" id="editCourseCategoryName" name="new_name" required
                    placeholder="Nový název kategorie" class="full-width">
            </div>

            <div class="form-group">
                <label for="editCourseCategoryFile">Nahrát soubor do kategorie (volitelné)</label>
                <div class="upload-area">
                    <label for="editCourseCategoryFile" class="custom-file-button">Vyberte soubor</label>
                    <input type="file" name="category_file" id="editCourseCategoryFile" accept=".pdf,.doc,.docx,.txt,.rtf,.xls,.xlsx,.ppt,.pptx,.jpg,.jpeg,.png,.gif" class="hidden-file-input">
                    <div id="edit-course-category-selected-file" class="selected-files-info"></div>
                </div>
                <small class="file-help">Podporované formáty: PDF, DOC, DOCX, TXT, RTF, XLS, XLSX, PPT, PPTX, JPG, PNG, GIF (max 50MB)</small>
            </div>

            <div class="form-group">
                <label for="editCourseCategoryCustomFilename">Přejmenovat soubor (volitelné)</label>
                <input type="text" id="editCourseCategoryCustomFilename" name="custom_filename" class="full-width" placeholder="Ponechte prázdné pro původní název">
                <small class="file-help">Zadejte pouze název bez přípony (přípona se doplní automaticky)</small>
            </div>

            <div class="modal-actions">
                <button type="button" class="btn btn-secondary" onclick="closeEditCourseCategoryModal()">Zrušit</button>
                <button type="submit" class="btn btn-primary">Uložit změny</button>
            </div>
        </form>
    </div>
</div>

<script>
    // Course management JavaScript functions - adapted from EDO
    let courseSubfolderData = {};

    document.addEventListener('DOMContentLoaded', function() {
        // Only run if we're on the courses page
        if (!document.getElementById('courses')) {
            return;
        }

        // Load course subfolder data
        loadCourseSubfolderData();

        // Close all categories by default
        document.querySelectorAll('#courses .category-content').forEach(content => {
            if (content) {
                content.style.maxHeight = '0px';
            }
        });

        // Initialize form handlers
        initializeCourseFormHandlers();

        // ...existing course-specific code...

        const fileInput = document.getElementById('course_file');
        const selectedFile = document.getElementById('selected-file');

        if (fileInput && selectedFile) {
            fileInput.addEventListener('change', function() {
                if (this.files && this.files.length > 0) {
                    const file = this.files[0];
                    const fileSize = formatFileSize(file.size);
                    selectedFile.textContent = `Vybraný soubor: ${file.name} (${fileSize})`;
                } else {
                    selectedFile.textContent = 'Žádný soubor nebyl vybrán';
                }
            });
        }

        // Handle status messages
        const urlParams = new URLSearchParams(window.location.search);
        const status = urlParams.get('status');
        const message = urlParams.get('message');

        if (status && message) {
            const statusDiv = document.getElementById('status-messages');
            if (statusDiv) {
                const messageDiv = document.createElement('div');
                messageDiv.className = `status-message status-${status}`;
                messageDiv.textContent = decodeURIComponent(message);
                statusDiv.appendChild(messageDiv);

                const newUrl = window.location.pathname + window.location.hash;
                window.history.replaceState({}, '', newUrl);

                setTimeout(() => {
                    if (messageDiv && messageDiv.parentNode) {
                        messageDiv.remove();
                    }
                }, 5000);
            }
        }
    });

    // Load course subfolder data
    function loadCourseSubfolderData() {
        fetch('get-course-subfolders.php')
            .then(response => response.json())
            .then(data => {
                courseSubfolderData = data || {};
                updateCourseSubfolderOptions();
            })
            .catch(error => console.log('No course subfolder data available yet:', error.message));
    }

    // Update course subfolder dropdown
    function updateCourseSubfolderOptions() {
        const mainFolder = document.getElementById('course-category');
        const subfolderSelect = document.getElementById('course-subfolder');

        if (!mainFolder || !subfolderSelect) return;

        subfolderSelect.innerHTML = '<option value="">-- Hlavní složka --</option>';

        if (courseSubfolderData[mainFolder.value]) {
            courseSubfolderData[mainFolder.value].forEach(subfolder => {
                const option = document.createElement('option');
                option.value = subfolder;
                option.textContent = subfolder;
                subfolderSelect.appendChild(option);
            });
        }
    }

    // Toggle course category - same as EDO
    function toggleCourseCategoryHeader(categoryKey) {
        if (!document.getElementById('courses')) {
            return;
        }

        const categoryCard = document.querySelector(`#courses [data-category="${categoryKey}"]`);

        if (!categoryCard) {
            console.warn('Could not find course category card for:', categoryKey);
            return;
        }

        if (categoryCard.classList.contains('expanded')) {
            categoryCard.classList.remove('expanded');
        } else {
            document.querySelectorAll('#courses .course-category.expanded').forEach(card => {
                card.classList.remove('expanded');
            });
            categoryCard.classList.add('expanded');
        }
    }

    // Toggle course subfolder
    function toggleCourseSubfolder(subfolderId) {
        if (event) {
            event.stopPropagation();
            event.preventDefault();
        }

        if (!document.getElementById('courses')) {
            return;
        }

        const subfolderCard = document.querySelector(`[onclick="toggleCourseSubfolder('${subfolderId}')"]`);

        if (!subfolderCard) {
            console.warn('Could not find course subfolder card for:', subfolderId);
            return;
        }

        const content = subfolderCard.querySelector('.subfolder-content');

        if (subfolderCard.classList.contains('open')) {
            subfolderCard.classList.remove('open');
            if (content) {
                content.style.maxHeight = '';
            }
        } else {
            subfolderCard.classList.add('open');
            if (content) {
                setTimeout(() => {
                    const scrollHeight = content.scrollHeight;
                    content.style.maxHeight = Math.max(scrollHeight, 100) + 'px';
                }, 50);
            }
        }
    }

    // Course management functions - adapted from EDO
    function showAddCourseSubfolderModal(categoryKey) {
        if (event) {
            event.stopPropagation();
            event.preventDefault();
        }

        document.getElementById('courseParentCategory').value = categoryKey;
        document.getElementById('addCourseSubfolderModal').style.display = 'flex';
        document.getElementById('addCourseSubfolderForm').reset();
        setTimeout(() => document.getElementById('courseSubfolderName').focus(), 100);
    }

    function closeAddCourseSubfolderModal() {
        document.getElementById('addCourseSubfolderModal').style.display = 'none';
        document.getElementById('addCourseSubfolderForm').reset();
    }

    function editCourseSubfolder(categoryKey, subfolderName) {
        if (event) {
            event.stopPropagation();
            event.preventDefault();
        }

        document.getElementById('editCourseParentCategory').value = categoryKey;
        document.getElementById('editCourseOriginalName').value = subfolderName;
        document.getElementById('editCourseSubfolderName').value = subfolderName;
        document.getElementById('editCourseSubfolderModal').style.display = 'flex';
        setTimeout(() => document.getElementById('editCourseSubfolderName').focus(), 100);
    }

    function closeEditCourseSubfolderModal() {
        document.getElementById('editCourseSubfolderModal').style.display = 'none';
        document.getElementById('editCourseSubfolderForm').reset();
        document.getElementById('edit-course-subfolder-selected-file').textContent = '';
        document.getElementById('editCourseSubfolderCustomFilename').value = '';
    }

    function deleteCourseSubfolder(categoryKey, subfolderName) {
        if (event) {
            event.stopPropagation();
            event.preventDefault();
        }

        if (confirm(`Opravdu chcete smazat podsložku kurzu "${subfolderName}"?\n\nTato akce smaže podsložku a všechny soubory v ní. Toto nelze vrátit zpět!`)) {
            const formData = new FormData();
            // Parametry pro univerzální handler
            formData.append('type', 'courses');
            formData.append('category', categoryKey);
            formData.append('subfolder', subfolderName);

            fetch('delete-subfolder-handler.php', {
                method: 'POST',
                body: formData,
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            })
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        showStatusMessage('success', data.message);

                        // --- BLESKOVÉ SMAZÁNÍ Z DOMU ---
                        const card = document.querySelector(`[data-subfolder="${subfolderName}"]`);
                        if (card) {
                            card.style.transition = 'all 0.3s ease';
                            card.style.opacity = '0';
                            card.style.transform = 'scale(0.9)';
                            setTimeout(() => card.remove(), 300);
                        } else {
                            // Pojistka pro případ, že by neseděl selektor
                            setTimeout(() => location.reload(), 500);
                        }
                    } else {
                        alert(data.message);
                    }
                })
                .catch(err => {
                    console.error('Chyba:', err);
                    showStatusMessage('error', 'Chyba při mazání podsložky kurzu.');
                });
        }
    }

    function deleteCourseFile(fileName, category, subfolder = '') {
        if (!fileName || !category) return;

        if (confirm(`Opravdu chcete smazat soubor kurzu "${fileName}"?`)) {
            const formData = new FormData();
            // Identifikace pro univerzální handler
            formData.append('type', 'courses');
            formData.append('category', category);
            formData.append('subfolder', subfolder);
            formData.append('file_name', fileName);

            fetch('delete-file-handler.php', {
                method: 'POST',
                body: formData,
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        if (typeof showStatusMessage === 'function') {
                            showStatusMessage('success', data.message);
                        }

                        // --- JEN SMAZÁNÍ Z DOMU ---
                        const selector = `[data-file-name="${fileName}"][data-current-category="${category}"][data-current-subfolder="${subfolder}"]`;
                        const fileElement = document.querySelector(selector);

                        if (fileElement) {
                            // Rychlá animace a pryč s tím
                            fileElement.style.transition = 'all 0.3s ease';
                            fileElement.style.opacity = '0';
                            fileElement.style.transform = 'scale(0.9)';

                            setTimeout(() => fileElement.remove(), 300);
                        } else {
                            // Pokud selektor netrefíme, refresh to jistí
                            location.reload();
                        }
                    } else {
                        alert("Chyba: " + data.message);
                    }
                })
                .catch(error => console.error('Error:', error));
        }
    }

    function editCourseFile(originalName, category, subfolder, currentName) {
        document.getElementById('editCourseFileCategory').value = category;
        document.getElementById('editCourseFileSubfolder').value = subfolder || '';
        document.getElementById('editCourseFileOriginalName').value = originalName;

        const nameWithoutExt = currentName.replace(/\.[^/.]+$/, "");
        document.getElementById('editCourseFileName').value = nameWithoutExt;

        document.getElementById('editCourseFileModal').style.display = 'flex';
        setTimeout(() => document.getElementById('editCourseFileName').focus(), 100);
    }

    function closeEditCourseFileModal() {
        document.getElementById('editCourseFileModal').style.display = 'none';
        document.getElementById('editCourseFileForm').reset();
    }

    // Course category management functions
    function editCourseCategory(categoryKey) {
        if (event) event.stopPropagation();

        const modal = document.getElementById('editCourseCategoryModal');
        const originalInput = document.getElementById('editCourseOriginalName');
        const nameInput = document.getElementById('editCourseCategoryName');

        if (originalInput) originalInput.value = categoryKey;
        if (nameInput) nameInput.value = categoryKey;
        if (modal) modal.style.display = 'flex';
    }

    function closeEditCourseCategoryModal() {
        const modal = document.getElementById('editCourseCategoryModal');
        if (modal) modal.style.display = 'none';

        const form = document.getElementById('editCourseCategoryForm');
        if (form) form.reset();

        const selectedFileDiv = document.getElementById('edit-course-category-selected-file');
        if (selectedFileDiv) selectedFileDiv.textContent = '';

        const customFilenameInput = document.getElementById('editCourseCategoryCustomFilename');
        if (customFilenameInput) customFilenameInput.value = '';
    }

    function deleteCourseCategory(categoryKey) {
        if (event) event.stopPropagation();

        if (!confirm(`Smazat kategorii "${categoryKey}" a všechny soubory?`)) return;

        const formData = new FormData();
        formData.append('category', categoryKey);
        formData.append('type', 'courses');

        fetch('delete-category-handler.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    location.reload();
                } else {
                    alert('Chyba: ' + (data.message || 'Neznámá chyba'));
                }
            })
            .catch(() => alert('Chyba při mazání kategorie'));
    }

    // Toggle folder inputs
    function toggleCourseFolderInputs() {
        const customInput = document.getElementById('custom-course-folder-name');
        const existingRow = document.getElementById('existing-course-folder-row');
        const categorySelect = document.getElementById('course-category');
        const subfolderSelect = document.getElementById('course-subfolder');

        if (!customInput || !existingRow || !categorySelect) return;

        if (customInput.value.trim()) {
            existingRow.style.opacity = '0.5';
            categorySelect.disabled = true;
            subfolderSelect.disabled = true;
            categorySelect.value = '';
            subfolderSelect.value = '';
        } else {
            existingRow.style.opacity = '1';
            categorySelect.disabled = false;
            subfolderSelect.disabled = false;
        }
    }

    // Initialize course form handlers
    function initializeCourseFormHandlers() {
        // Handle course subfolder form submission
        const courseSubfolderForm = document.getElementById('addCourseSubfolderForm');
        if (courseSubfolderForm) {
            courseSubfolderForm.addEventListener('submit', function(e) {
                e.preventDefault();

                const formData = new FormData(this);

                fetch('add-course-subfolder.php', {
                        method: 'POST',
                        body: formData
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data && data.success) {
                            closeAddCourseSubfolderModal();
                            showStatusMessage('success', data.message);
                            setTimeout(() => location.reload(), 500);
                        } else {
                            showStatusMessage('error', data.message || 'Unknown error occurred');
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        showStatusMessage('error', 'Došlo k chybě při vytváření podsložky kurzu.');
                    });
            });

            // Handle edit course subfolder form submission
            const editCourseSubfolderForm = document.getElementById('editCourseSubfolderForm');
            if (editCourseSubfolderForm) {
                editCourseSubfolderForm.addEventListener('submit', function(e) {
                    e.preventDefault();

                    const formData = new FormData(this);
                    // 1. Identifikace pro univerzální handler (pro složku /courses/)
                    formData.append('type', 'courses');

                    const btn = this.querySelector('button[type="submit"]');
                    const originalText = btn.innerHTML;
                    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Ukládá se...';
                    btn.disabled = true;

                    // 2. Míříme na společný handler
                    fetch('edit-subfolder-handler.php', {
                        method: 'POST',
                        body: formData,
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest'
                        }
                    })
                        .then(res => res.json())
                        .then(data => {
                            if (data.success) {
                                if (typeof closeEditCourseSubfolderModal === 'function') closeEditCourseSubfolderModal();
                                showStatusMessage('success', data.message);
                                setTimeout(() => location.reload(), 500);
                            } else {
                                showStatusMessage('error', data.message || 'Neznámá chyba');
                            }
                        })
                        .catch(error => {
                            console.error('Error:', error);
                            showStatusMessage('error', 'Chyba při komunikaci se serverem.');
                        })
                        .finally(() => {
                            btn.innerHTML = originalText;
                            btn.disabled = false;
                        });
                });
            }

            // Handle edit course file form submission
            // Handler pro přejmenování souboru kurzu
            const editCourseFileForm = document.getElementById('editCourseFileForm');
            if (editCourseFileForm) {
                editCourseFileForm.addEventListener('submit', function(e) {
                    e.preventDefault();

                    // 1. Vytáhneme data z políček v modalu
                    const category = document.getElementById('editCourseFileCategory').value;
                    const subfolder = document.getElementById('editCourseFileSubfolder').value;
                    const originalName = document.getElementById('editCourseFileOriginalName').value;
                    const newName = document.getElementById('editCourseFileName').value;

                    if (!category || !originalName || !newName) {
                        alert('Všechna pole jsou povinná.');
                        return;
                    }

                    const formData = new FormData();
                    // 2. KLÍČOVÝ PARAMETR: 'courses' navede handler do správného rootu
                    formData.append('type', 'courses');
                    formData.append('category', category);
                    formData.append('subfolder', subfolder);
                    formData.append('original_name', originalName);
                    formData.append('new_name', newName);

                    const submitBtn = this.querySelector('button[type="submit"]');
                    const originalText = submitBtn.innerHTML;
                    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Ukládání...';
                    submitBtn.disabled = true;

                    // 3. Pálíme na univerzální handler
                    fetch('edit-file-handler.php', {
                        method: 'POST',
                        body: formData
                    })
                        .then(response => response.json())
                        .then(data => {
                            if (data.success) {
                                // Zavřeme modal (použijeme tvou existující funkci)
                                if (typeof closeEditCourseFileModal === 'function') closeEditCourseFileModal();

                                showStatusMessage('success', data.message);

                                // Refresh je u kurzů nutný, aby se v mřížce přepsaly linky na soubory
                                setTimeout(() => location.reload(), 600);
                            } else {
                                showStatusMessage('error', data.message || 'Neznámá chyba');
                                submitBtn.disabled = false;
                                submitBtn.innerHTML = originalText;
                            }
                        })
                        .catch((error) => {
                            console.error('Error:', error);
                            showStatusMessage('error', 'Chyba při komunikaci se serverem.');
                            submitBtn.disabled = false;
                            submitBtn.innerHTML = originalText;
                        });
                });
            }

            // Edit course category form - UNIVERZÁLNÍ ÚPRAVA PRO COURSES.PHP
            const editCourseCategoryForm = document.getElementById('editCourseCategoryForm');
            if (editCourseCategoryForm) {
                editCourseCategoryForm.addEventListener('submit', function(e) {
                    e.preventDefault();

                    const originalName = document.getElementById('editCourseOriginalName').value;
                    const newName = document.getElementById('editCourseCategoryName').value;

                    if (!originalName || !newName) {
                        alert('Chybí povinné údaje');
                        return;
                    }

                    const formData = new FormData(this);
                    // PŘIDÁNO: Typ složky pro univerzální handler
                    formData.append('type', 'courses');
                    formData.set('original_name', originalName);
                    formData.set('new_name', newName);

                    const submitBtn = this.querySelector('button[type="submit"]');
                    const originalText = submitBtn.innerHTML;
                    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Ukládá se...';
                    submitBtn.disabled = true;

                    // ZMĚNĚNO: Míříme na společný handler
                    fetch('edit-category-handler.php', {
                        method: 'POST',
                        body: formData
                    })
                        .then(response => response.json())
                        .then(data => {
                            if (data.success) {
                                closeEditCourseCategoryModal();
                                showStatusMessage('success', data.message);
                                setTimeout(() => location.reload(), 500);
                            } else {
                                showStatusMessage('error', data.message || 'Neznámá chyba');
                            }
                        })
                        .catch((error) => {
                            console.error('Error:', error);
                            showStatusMessage('error', 'Chyba při ukládání');
                        })
                        .finally(() => {
                            submitBtn.innerHTML = originalText;
                            submitBtn.disabled = false;
                        });
                });
            }

            // File input handler for edit subfolder
            const editCourseSubfolderFileInput = document.getElementById('editCourseSubfolderFile');
            const editCourseSubfolderSelectedFile = document.getElementById('edit-course-subfolder-selected-file');
            const editCourseSubfolderCustomFilename = document.getElementById('editCourseSubfolderCustomFilename');

            if (editCourseSubfolderFileInput && editCourseSubfolderSelectedFile) {
                editCourseSubfolderFileInput.addEventListener('change', function() {
                    if (this.files.length > 0) {
                        const file = this.files[0];
                        const fileSize = formatFileSize(file.size);
                        const fileName = file.name;
                        const nameWithoutExt = fileName.replace(/\.[^/.]+$/, "");

                        editCourseSubfolderSelectedFile.textContent = `Vybraný soubor: ${fileName} (${fileSize})`;

                        if (editCourseSubfolderCustomFilename && !editCourseSubfolderCustomFilename.value) {
                            editCourseSubfolderCustomFilename.value = nameWithoutExt;
                            editCourseSubfolderCustomFilename.placeholder = `Původní: ${nameWithoutExt}`;
                        }
                    } else {
                        editCourseSubfolderSelectedFile.textContent = '';
                        if (editCourseSubfolderCustomFilename) {
                            editCourseSubfolderCustomFilename.value = '';
                            editCourseSubfolderCustomFilename.placeholder = 'Ponechte prázdné pro původní název';
                        }
                    }
                });
            }

            // File input handler for edit category
            const editCourseCategoryFileInput = document.getElementById('editCourseCategoryFile');
            const editCourseCategorySelectedFile = document.getElementById('edit-course-category-selected-file');

            if (editCourseCategoryFileInput && editCourseCategorySelectedFile) {
                editCourseCategoryFileInput.addEventListener('change', function() {
                    if (this.files && this.files.length > 0) {
                        const file = this.files[0];
                        const fileSize = formatFileSize(file.size);
                        editCourseCategorySelectedFile.textContent = `Vybraný soubor: ${file.name} (${fileSize})`;

                        const customFilenameInput = document.getElementById('editCourseCategoryCustomFilename');
                        if (customFilenameInput && !customFilenameInput.value.trim()) {
                            const nameWithoutExt = file.name.replace(/\.[^/.]+$/, "");
                            customFilenameInput.value = nameWithoutExt;
                        }
                    } else {
                        editCourseCategorySelectedFile.textContent = 'Žádný soubor nebyl vybrán';
                    }
                });
            }
        }
    }

    // Make sure all functions are globally available
    window.editCourseCategory = editCourseCategory;
    window.deleteCourseCategory = deleteCourseCategory;
    window.toggleCourseFolderInputs = toggleCourseFolderInputs;

    // Drag and Drop for Courses - Use unique variable names to avoid conflicts
    let draggedCourseElement = null;
    let draggedCourseFileData = null;

    const supportsFileSystemAccessCourses = 'getAsFileSystemHandle' in DataTransferItem.prototype;

    function handleCourseDragStart(event) {
        console.log("🚀 Course drag started with File System Access API support:", supportsFileSystemAccessCourses);

        draggedCourseElement = event.target.closest('.draggable-file');
        if (!draggedCourseElement) return;
        draggedCourseElement.classList.add('dragging');

        draggedCourseFileData = {
            fileName: draggedCourseElement.getAttribute('data-file-name'),
            currentCategory: draggedCourseElement.getAttribute('data-current-category'),
            currentSubfolder: draggedCourseElement.getAttribute('data-current-subfolder') || ''
        };

        console.log("📁 Dragged course file data:", draggedCourseFileData);

        event.dataTransfer.effectAllowed = 'move';
        event.dataTransfer.setData('text/plain', JSON.stringify(draggedCourseFileData));

        if (supportsFileSystemAccessCourses) {
            try {
                const fileData = new Blob([JSON.stringify(draggedCourseFileData)], {
                    type: 'application/json'
                });
                const file = new File([fileData], 'course-move-data.json', {
                    type: 'application/json'
                });
                event.dataTransfer.items.add(file);
                console.log("✅ Enhanced course drag data set");
            } catch (error) {
                console.warn("⚠️ Could not set enhanced drag data:", error);
            }
        }
    }

    function handleCourseDragEnd(event) {
        console.log("🏁 Course drag ended");

        if (draggedCourseElement) {
            draggedCourseElement.classList.remove('dragging');
        }

        document.querySelectorAll('.droppable-zone').forEach(zone => {
            zone.classList.remove('drag-over');
        });

        draggedCourseElement = null;
        draggedCourseFileData = null;
    }

    function handleCourseDragOver(event) {
        event.preventDefault();
        event.dataTransfer.dropEffect = 'move';
    }

    function handleCourseDragEnter(event) {
        event.preventDefault();
        event.stopPropagation();

        if (!draggedCourseFileData) return;

        const dropZone = event.currentTarget;
        const targetCategory = dropZone.getAttribute('data-category');
        const targetSubfolder = dropZone.getAttribute('data-subfolder') || '';

        console.log("⬆️ Course drag enter zone:", {
            targetCategory,
            targetSubfolder
        });

        if (targetCategory === draggedCourseFileData.currentCategory &&
            targetSubfolder === draggedCourseFileData.currentSubfolder) {
            console.log("❌ Same location, no highlight");
            return;
        }

        dropZone.classList.add('drag-over');
        console.log("✅ Highlighted drop zone");
    }

    function handleCourseDragLeave(event) {
        event.preventDefault();
        event.stopPropagation();

        const dropZone = event.currentTarget;
        const rect = dropZone.getBoundingClientRect();
        const x = event.clientX;
        const y = event.clientY;

        if (x < rect.left || x > rect.right || y < rect.top || y > rect.bottom) {
            dropZone.classList.remove('drag-over');
        }
    }

    async function handleCourseDrop(event) {
        console.log("📥 Course drop detected");
        event.preventDefault();
        event.stopPropagation();

        const dropZone = event.currentTarget;
        const targetCategory = dropZone.getAttribute('data-category');
        const targetSubfolder = dropZone.getAttribute('data-subfolder') || '';

        dropZone.classList.remove('drag-over');

        let fileData = null;

        if (supportsFileSystemAccessCourses && event.dataTransfer.items.length > 0) {
            console.log("🔧 Attempting to use File System Access API");

            for (let i = 0; i < event.dataTransfer.items.length; i++) {
                const item = event.dataTransfer.items[i];

                try {
                    if (item.kind === 'file') {
                        const handle = await item.getAsFileSystemHandle();
                        console.log("📂 Got file system handle:", handle);

                        if (handle && handle.kind === 'file') {
                            fileData = {
                                fileName: handle.name,
                                currentCategory: draggedCourseFileData?.currentCategory,
                                currentSubfolder: draggedCourseFileData?.currentSubfolder
                            };

                            console.log("✅ Enhanced file data from File System Access API:", fileData);
                            break;
                        }
                    }
                } catch (error) {
                    console.warn("⚠️ File System Access API failed:", error);
                }
            }
        }

        if (!fileData && draggedCourseFileData) {
            console.log("🔄 Falling back to traditional drag data");
            fileData = draggedCourseFileData;
        }

        if (!fileData) {
            try {
                const transferData = event.dataTransfer.getData('text/plain');
                if (transferData) {
                    fileData = JSON.parse(transferData);
                    console.log("🔄 Got data from text transfer:", fileData);
                }
            } catch (error) {
                console.error("❌ Could not parse transfer data:", error);
            }
        }

        if (!fileData) {
            console.error("❌ No file data available for drop");
            showStatusMessage('error', 'Nepodařilo se získat informace o souboru');
            return;
        }

        console.log("🎯 Final drop target:", {
            targetCategory,
            targetSubfolder
        });

        if (targetCategory === fileData.currentCategory &&
            targetSubfolder === fileData.currentSubfolder) {
            console.log("❌ Same location, ignoring drop");
            return;
        }

        await moveCourseFile(fileData, targetCategory, targetSubfolder);
    }

    async function moveCourseFile(fileData, targetCategory, targetSubfolder) {
        console.log("🚚 Moving course file:", fileData.fileName);
        showStatusMessage('info', 'Přesouvám soubor...');

        const formData = new FormData();
        formData.append('fileName', fileData.fileName);
        formData.append('sourceCategory', fileData.currentCategory);
        formData.append('sourceSubfolder', fileData.currentSubfolder);
        formData.append('targetCategory', targetCategory);
        formData.append('targetSubfolder', targetSubfolder);
        formData.append('move_course_file', '1');

        try {
            const response = await fetch(window.location.href, {
                method: 'POST',
                body: formData
            });

            // After successful move, reload the page to reflect changes
            if (response.ok) {
                showStatusMessage('success', 'Soubor byl úspěšně přesunut');
                setTimeout(() => {
                    location.reload();
                }, 1000);
            } else {
                showStatusMessage('error', 'Chyba při přesunu souboru');
            }
        } catch (error) {
            console.error('Error moving file:', error);
            showStatusMessage('error', 'Chyba sítě: ' + error.message);
        }
    }

    // Helper function for file size formatting (if not already available globally)
    function formatFileSize(bytes) {
        if (bytes === 0) return '0 Bytes';
        const k = 1024;
        const sizes = ['Bytes', 'KB', 'MB', 'GB'];
        const i = Math.floor(Math.log(bytes) / Math.log(k));
        return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
    }

    // Helper function to show status messages (if not already available globally)
    function showStatusMessage(type, message) {
        const statusDiv = document.getElementById('status-messages');
        if (statusDiv) {
            const messageDiv = document.createElement('div');
            messageDiv.className = `status-message status-${type}`;
            messageDiv.textContent = message;
            statusDiv.appendChild(messageDiv);

            setTimeout(() => {
                if (messageDiv && messageDiv.parentNode) {
                    messageDiv.remove();
                }
            }, 5000);
        }
    }
</script>