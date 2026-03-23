<?php
// BUFFER OUTPUT IMMEDIATELY TO FIX "HEADERS ALREADY SENT"
ob_start();

// Set proper encoding for Czech characters  
mb_internal_encoding('UTF-8');

require_once 'access.php';

// Handle file move request
if (isset($_POST['move_other_file']) && isAdmin()) {
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
        $otherBaseDir = getRoot('other');;
        $sourcePath = $otherBaseDir . $sourceCategory . '/' . ($sourceSubfolder ? $sourceSubfolder . '/' : '');
        $targetPath = $otherBaseDir . $targetCategory . '/' . ($targetSubfolder ? $targetSubfolder . '/' : '');
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

// Get existing categories - scan actual directories like the courses page does
function getOtherCategories()
{
    $otherDir = getRoot('other');;
    $categories = [];

    if (is_dir($otherDir)) {
        $items = array_diff(scandir($otherDir), ['.', '..']);

        foreach ($items as $item) {
            $itemPath = $otherDir . $item;
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

    // NO PREDEFINED CATEGORIES - categories are created only when folders exist or are uploaded to
    return $categories;
}

// Get dynamic categories
$otherCategories = getOtherCategories();

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
function getOtherFileTypeInfo($extension)
{
    $extension = strtolower($extension);

    switch ($extension) {
        // Images
        case 'jpg':
        case 'jpeg':
        case 'png':
        case 'gif':
        case 'bmp':
        case 'svg':
        case 'webp':
            return [
                'type' => 'image',
                'icon' => 'fas fa-image',
                'color' => '#e74c3c',
                'previewable' => true
            ];

            // Documents
        case 'pdf':
        case 'txt':
        case 'doc':
        case 'docx':
        case 'rtf':
        case 'odt':
            return [
                'type' => 'document',
                'icon' => 'fas fa-file-alt',
                'color' => '#007bff',
                'previewable' => false
            ];

            // Spreadsheets
        case 'xls':
        case 'xlsx':
        case 'csv':
        case 'ods':
            return [
                'type' => 'spreadsheet',
                'icon' => 'fas fa-file-excel',
                'color' => '#28a745',
                'previewable' => false
            ];

            // Archives
        case 'zip':
        case 'rar':
        case '7z':
        case 'tar':
        case 'gz':
            return [
                'type' => 'archive',
                'icon' => 'fas fa-file-archive',
                'color' => '#ffc107',
                'previewable' => false
            ];

            // Presentations
        case 'ppt':
        case 'pptx':
        case 'odp':
            return [
                'type' => 'presentation',
                'icon' => 'fas fa-file-powerpoint',
                'color' => '#e67e22',
                'previewable' => false
            ];

            // Audio
        case 'mp3':
        case 'wav':
        case 'ogg':
            return [
                'type' => 'audio',
                'icon' => 'fas fa-file-audio',
                'color' => '#9b59b6',
                'previewable' => false
            ];

            // Video
        case 'mp4':
        case 'avi':
        case 'mkv':
        case 'mov':
        case 'wmv':
            return [
                'type' => 'video',
                'icon' => 'fas fa-file-video',
                'color' => '#3498db',
                'previewable' => false
            ];

        default:
            return [
                'type' => 'other',
                'icon' => 'fas fa-file',
                'color' => '#6c757d',
                'previewable' => false
            ];
    }
}

// Function to scan for subfolders and OTHER files
function scanOtherRecursive($dir, $categoryKey, $categoryInfo, $depth = 0)
{
    $result = ['folders' => [], 'documents' => []];

    if (!is_dir($dir)) return $result;

    $items = array_diff(scandir($dir), ['.', '..']);

    foreach ($items as $item) {
        $itemPath = $dir . $item;

        if (is_dir($itemPath)) {
            // It's a subfolder
            $subResult = scanOtherRecursive($itemPath . '/', $categoryKey, $categoryInfo, $depth + 1);
            $result['folders'][$item] = [
                'name' => $item,
                'path' => $itemPath,
                'data' => $subResult,
                'depth' => $depth
            ];
        } else {
            // It's a file - add it directly
            $extension = strtolower(pathinfo($item, PATHINFO_EXTENSION));

            $fileTypeInfo = getOtherFileTypeInfo($extension);

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

    return $result;
}

// Function to count all documents recursively
if (!function_exists('countAllOtherDocuments')) {
    function countAllOtherDocuments($categoryData)
    {
        $count = count($categoryData['documents']); // Count documents in main category

        // Add documents from all subfolders
        if (!empty($categoryData['folders'])) {
            foreach ($categoryData['folders'] as $folder) {
                $count += countAllOtherDocuments($folder['data']);
            }
        }

        return $count;
    }
}
?>

<style>
    #other .content-area {
        display: flex;
        flex-direction: column;
        gap: 30px;
    }

    .add-other-section {
        background: white;
        border-radius: 10px;
        padding: 25px;
        margin-bottom: 25px;
        box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
    }

    .add-other-section h2 {
        color: #2c3e50;
        margin-top: 0;
        margin-bottom: 20px;
        font-size: 20px;
    }

    .other-form {
        display: flex;
        flex-direction: column;
        gap: 15px;
    }

    .other-grid {
        display: flex;
        flex-direction: column;
        gap: 20px;
    }

    /* Category styling */
    .other-category {
        background: white;
        border-radius: 10px;
        box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        overflow: hidden;
        transition: all 0.3s ease;
    }

    .other-category.expanded {
        grid-column: 1 / -1;
        max-width: none;
        transform: none !important;
        z-index: 10;
    }

    .other-category.expanded .category-content {
        max-height: none !important;
        padding: 20px !important;
        display: block !important;
        opacity: 1 !important;
        overflow: visible !important;
    }

    .other-category.expanded .category-toggle i {
        transform: rotate(180deg);
    }

    .other-category:hover:not(.expanded) {
        box-shadow: 0 5px 15px rgba(0, 0, 0, 0.15);
    }

    .other-category:not(.expanded) .category-content {
        max-height: 0 !important;
        padding: 0 20px !important;
        overflow: hidden !important;
        opacity: 0 !important;
        display: none !important;
    }

    .category-toggle i {
        transition: transform 0.3s ease;
    }

    .category-content {
        transition: all 0.3s ease;
        background: white;
    }

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

    .other-count {
        font-size: 13px;
        color: #6c757d;
        font-weight: 500;
    }

    .category-toggle {
        color: #6c757d;
        transition: transform 0.3s ease;
    }

    .categories-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
        gap: 20px;
        margin-bottom: 30px;
        grid-auto-flow: row;
    }

    .no-other {
        text-align: center;
        padding: 60px 20px;
        color: #6c757d;
        background: white;
        border-radius: 10px;
        box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
    }

    /* Subfolder styling */
    .other-subfolders-section {
        margin-bottom: 25px;
    }

    .other-subfolders-section h4 {
        color: #495057;
        font-size: 14px;
        font-weight: 600;
        margin: 0 0 12px 0;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    .other-subfolders-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
        gap: 12px;
    }

    .other-subfolder-card {
        background: #f8f9fa;
        border: 1px solid #e9ecef;
        border-radius: 8px;
        padding: 15px;
        cursor: pointer;
        transition: all 0.2s;
    }

    .other-subfolder-card:hover {
        background: #e9ecef;
        transform: translateY(-1px);
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
    }

    .other-subfolder-card.open {
        grid-column: 1 / -1;
        width: 100%;
        max-width: none;
        background: #fff;
        border-color: #dee2e6;
    }

    .other-subfolder-card.open .subfolder-content {
        max-height: 1000px;
        margin-top: 12px;
        padding-top: 12px;
        border-top: 1px solid #dee2e6;
    }

    .other-subfolder-card.open .subfolder-toggle {
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

    /* Subfolder documents grid */
    .subfolder-documents {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
        gap: 10px;
        padding: 0;
    }

    .subfolder-document {
        background: white;
        border: 1px solid #e9ecef;
        border-radius: 8px;
        padding: 10px;
        display: flex;
        align-items: center;
        justify-content: space-between;
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
        transition: all 0.2s;
        width: 100%;
        box-sizing: border-box;
        position: relative;
    }

    .subfolder-document:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        border-color: #dee2e6;
    }

    .doc-icon {
        font-size: 20px;
        margin-right: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
        width: 30px;
    }

    .doc-info {
        flex: 1;
        min-width: 0;
        margin-right: 10px;
    }

    .doc-name {
        display: block;
        font-weight: 500;
        color: #495057;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
        font-size: 13px;
    }

    .doc-size {
        display: block;
        font-size: 11px;
        color: #6c757d;
    }

    .doc-actions {
        display: flex;
        gap: 5px;
    }

    /* Documents Section */
    .other-documents-section {
        margin-top: 0 !important;
        padding-top: 0 !important;
    }

    .other-documents-section h4 {
        color: #495057;
        font-size: 14px;
        font-weight: 600;
        margin: 0 0 12px 0;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    .other-documents-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
        gap: 15px;
    }

    .other-card-grid {
        background: white;
        border: 1px solid #e9ecef;
        border-radius: 8px;
        overflow: hidden;
        transition: all 0.2s;
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
        opacity: 1 !important;
        display: block !important;
    }

    .other-card-grid:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
        border-color: #dee2e6;
    }

    .other-preview {
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

    .other-info-grid {
        padding: 15px;
        display: flex;
        flex-direction: column;
        gap: 8px;
    }

    .other-title {
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

    .other-meta-grid {
        display: flex;
        justify-content: space-between;
        align-items: center;
        font-size: 11px;
        color: #6c757d;
        margin-top: auto;
        flex-wrap: wrap;
        gap: 8px;
    }

    .other-date,
    .other-size {
        display: flex;
        align-items: center;
        gap: 4px;
    }

    .other-actions-grid {
        display: flex;
        gap: 6px;
        justify-content: flex-end;
        margin-top: 8px;
        padding-top: 8px;
        border-top: 1px solid #f0f0f0;
    }

    /* Admin buttons */
    .admin-btn {
        padding: 4px 8px;
        border: 1px solid transparent;
        border-radius: 4px;
        background: transparent;
        color: #6c757d;
        font-size: 12px;
        transition: all 0.2s;
        cursor: pointer;
    }

    .admin-btn:hover {
        background: #f0f0f0;
    }

    .admin-btn.edit-btn:hover {
        color: #007bff;
    }

    .admin-btn.delete-btn:hover {
        color: #dc3545;
    }

    /* Common button styles */
    .grid-btn,
    .mini-btn,
    .action-btn {
        padding: 6px 8px;
        border: 1px solid #dee2e6;
        border-radius: 4px;
        background: #f8f9fa;
        color: #495057;
        text-decoration: none;
        font-size: 12px;
        transition: all 0.2s;
        display: flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
    }

    .grid-btn:hover,
    .mini-btn:hover {
        background: #e9ecef;
        color: #495057;
    }

    .view-btn:hover {
        color: #007bff !important;
        border-color: #007bff !important;
    }

    .download-btn:hover {
        color: #28a745 !important;
        border-color: #28a745 !important;
    }

    .delete-btn:hover {
        color: #dc3545 !important;
        border-color: #dc3545 !important;
    }

    .edit-btn:hover {
        color: #007bff !important;
        border-color: #007bff !important;
    }

    .add-other-subfolder-btn {
        background: #17a2b8!important;
        color: white!important;
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

    .add-other-subfolder-btn:hover {
        background: #138496;
    }

    .category-actions {
        margin-bottom: 20px;
        display: flex;
        gap: 10px;
    }

    /* Modal styles */
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
        max-width: 500px;
        max-height: 90vh;
        overflow-y: auto;
        box-shadow: 0 10px 25px rgba(0, 0, 0, 0.3);
        display: flex;
        flex-direction: column;
    }

    .modal-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 20px 25px;
        border-bottom: 1px solid #eee;
    }

    .modal-header h2 {
        margin: 0;
        color: #2c3e50;
        font-size: 18px;
        font-weight: 600;
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

    .modal-body {
        padding: 25px;
        flex-grow: 1;
    }

    .form-group {
        margin-bottom: 15px;
    }

    .form-group label {
        display: block;
        margin-bottom: 8px;
        font-weight: 600;
        color: #34495e;
        font-size: 14px;
    }

    .form-group input,
    .form-group select {
        width: 100%;
        padding: 10px;
        border: 1px solid #ddd;
        border-radius: 5px;
        font-size: 14px;
        box-sizing: border-box;
        transition: border-color 0.2s;
    }

    .form-group input:focus,
    .form-group select:focus {
        outline: none;
        border-color: #007bff;
    }

    .full-width {
        width: 100%;
    }

    .file-help {
        color: #6c757d;
        font-size: 12px;
        margin-top: 5px;
        display: block;
    }

    .modal-actions {
        display: flex;
        gap: 10px;
        justify-content: flex-end;
        padding: 20px 25px;
        background: #f8f9fa;
        border-top: 1px solid #eee;
    }

    .btn {
        padding: 8px 16px;
        border: none;
        border-radius: 4px;
        cursor: pointer;
        font-size: 14px;
        transition: background 0.2s;
    }

    .btn-primary {
        background: #007bff;
        color: white;
    }

    .btn-primary:hover {
        background: #0056b3;
    }

    .btn-secondary {
        background: #6c757d;
        color: white;
    }

    .btn-secondary:hover {
        background: #5a6268;
    }

    .status-message {
        display: flex;
        align-items: center;
        padding: 10px 15px;
        margin-bottom: 15px;
        border-radius: 5px;
        opacity: 1;
        transition: opacity 0.3s ease;
    }

    .status-message i {
        font-size: 18px;
        margin-right: 10px;
    }

    .status-success {
        background: #d4edda;
        color: #155724;
    }

    .status-error {
        background: #f8d7da;
        color: #721c24;
    }

    .empty-category {
        text-align: center;
        padding: 40px 20px;
        color: #6c757d;
        background: #f8f9fa;
        border-radius: 8px;
        margin: 10px 0;
    }

    .empty-category i {
        font-size: 24px;
        margin-bottom: 10px;
        opacity: 0.5;
    }

    .empty-category p {
        margin: 0;
        font-size: 14px;
    }

    /* Drag and Drop Styles */
    .other-card-grid.dragging,
    .subfolder-document.dragging {
        opacity: 0.5;
        transform: scale(0.95);
        cursor: grabbing;
    }

    .other-subfolder-card.drag-over {
        background: #e3f2fd !important;
        border-color: #2196f3 !important;
        box-shadow: 0 0 15px rgba(33, 150, 243, 0.3) !important;
    }

    .other-category.drag-over {
        background: #f3e5f5 !important;
        border-color: #9c27b0 !important;
        box-shadow: 0 0 15px rgba(156, 39, 176, 0.3) !important;
    }

    .drag-handle {
        cursor: grab;
        color: #6c757d !important;
        transition: color 0.2s !important;
        position: absolute !important;
        top: 5px !important;
        right: 5px !important;
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

    .other-subfolder-card.drag-over .drop-zone-indicator,
    .other-category.drag-over .drop-zone-indicator {
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

    .other-subfolder-card,
    .other-category {
        position: relative;
    }

    .other-preview {
        position: relative;
    }
</style>

<!--Page Other-->
<div class="page-content" id="other">
    <div class="content-area">
        <!-- Status Messages -->
        <div id="status-messages"></div>

        <!-- Other Files Grid Container -->
        <div class="other-grid-container">
            <?php
            $otherDir = getRoot('other');;
            $otherByCategory = [];

            // Scan for other files and subfolders
            if (is_dir($otherDir)) {
                foreach ($otherCategories as $categoryKey => $categoryInfo) {
                    $categoryDir = $otherDir . $categoryKey . '/';
                    if (is_dir($categoryDir)) {
                        $categoryData = scanOtherRecursive($categoryDir, $categoryKey, $categoryInfo);
                        if (!empty($categoryData['documents']) || !empty($categoryData['folders'])) {
                            $otherByCategory[$categoryKey] = [
                                'info' => $categoryInfo,
                                'data' => $categoryData
                            ];
                        }
                    }
                }
            }

            if (empty($otherByCategory)): ?>
                <div class="no-other">
                    <i class="fas fa-file"></i>
                    <p>Zatím nejsou žádné Other soubory v systému.</p>
                    <?php if (canUpload()): ?>
                        <p>Nahrajte první soubor a vytvoří se automaticky kategorie.</p>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <div class="categories-grid">
                    <?php foreach ($otherByCategory as $categoryKey => $category): ?>
                        <div class="other-category droppable-zone" 
                            data-category="<?= $categoryKey ?>" 
                            style="border-top: 4px solid <?= $category['info']['color'] ?>"
                            ondrop="handleOtherDrop(event)"
                            ondragover="handleOtherDragOver(event)"
                            ondragenter="handleOtherDragEnter(event)"
                            ondragleave="handleOtherDragLeave(event)">

                            <div class="drop-zone-indicator">
                                <i class="fas fa-file-import"></i> &nbsp; Přetáhněte soubor do hlavní složky
                            </div>

                            <div class="category-header category-header-clickable" onclick="toggleOtherCategoryHeader('<?= $categoryKey ?>')">
                                <div class="category-info">
                                    <h3 style="color: <?= $category['info']['color'] ?>">
                                        <i class="fas fa-folder"></i>
                                        <?= htmlspecialchars($category['info']['name']) ?>
                                    </h3>
                                    <span class="other-count">
                                        <?= countAllOtherDocuments($category['data']) ?> souborů
                                        <?php if (!empty($category['data']['folders'])): ?>
                                            • <?= count($category['data']['folders']) ?> podsložek
                                        <?php endif; ?>
                                    </span>
                                </div>

                                <?php if (isAdmin()): ?>
                                    <div class="category-admin-actions" onclick="event.stopPropagation()">
                                        <button class="admin-btn edit-btn" onclick="editOtherCategory('<?= $categoryKey ?>')" title="Přejmenovat kategorii">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <button class="admin-btn delete-btn" onclick="deleteOtherCategory('<?= $categoryKey ?>')" title="Smazat kategorii">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </div>
                                <?php endif; ?>

                                <div class="category-toggle">
                                    <i class="fas fa-chevron-down"></i>
                                </div>
                            </div>

                            <div class="category-content" id="other-category-content-<?= $categoryKey ?>">
                                <?php if (canUpload()): ?>
                                    <div class="category-actions">
                                        <button class="action-btn add-other-subfolder-btn" onclick="showAddOtherSubfolderModal('<?= $categoryKey ?>')">
                                            <i class="fas fa-folder-plus"></i> Přidat podsložku
                                        </button>
                                    </div>
                                <?php endif; ?>

                                <!-- Subfolders Grid -->
                                <?php if (!empty($category['data']['folders'])): ?>
                                    <div class="other-subfolders-section">
                                        <h4>Podsložky</h4>
                                        <div class="other-subfolders-grid">
                                            <?php foreach ($category['data']['folders'] as $folderName => $folderData): ?>
                                                <?php renderOtherSubfolder($folderName, $folderData, $categoryKey, $category['info']); ?>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                <?php endif; ?>

                                <!-- Other files in main category -->
                                <?php if (!empty($category['data']['documents'])): ?>
                                    <div class="other-documents-section">
                                        <h4>Soubory</h4>
                                        <div class="other-documents-grid">
                                            <?php foreach ($category['data']['documents'] as $other): ?>
                                                <?php renderOtherCard($other, $categoryKey); ?>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                <?php else: ?>
                                    <!-- Show empty state for categories with no files -->
                                    <div class="other-documents-section">
                                        <h4>Soubory</h4>
                                        <div class="empty-category">
                                            <i class="fas fa-folder-open"></i>
                                            <p>Tato kategorie je prázdná</p>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- Add Other Section -->
        <?php if (canUpload()): ?>
            <div class="add-other-section">
                <h2>Nahrát soubor</h2>
                <form action="upload-category-form-handler.php" method="post" enctype="multipart/form-data" class="other-form">

                    <input type="hidden" name="type" value="other">
                    <input type="hidden" name="redirect" value="other">

                    <div class="form-row">
                        <div class="form-group">
                            <label for="custom-other-folder-name">Název kategorie *</label>
                            <input type="text" id="custom-other-folder-name" name="custom_category" class="full-width"
                                   placeholder="Zadejte název kategorie..."
                                   onchange="toggleOtherFolderInputs()"
                                   oninput="toggleOtherFolderInputs()">
                            <small class="file-help">Kategorie se vytvoří automaticky, pokud neexistuje</small>
                        </div>
                    </div>

                    <?php if (!empty($otherCategories)): ?>
                        <div class="form-row" id="existing-other-folder-row">
                            <div class="form-group">
                                <label for="other-category">Nebo vyberte existující kategorii</label>
                                <select id="other-category" name="category" class="full-width" onchange="updateOtherSubfolderOptions()">
                                    <option value="">-- Vyberte kategorii --</option>
                                    <?php foreach ($otherCategories as $key => $category): ?>
                                        <option value="<?= $key ?>"><?= htmlspecialchars($category['name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="form-group">
                                <label for="other-subfolder">Podsložka</label>
                                <select id="other-subfolder" name="subfolder" class="full-width">
                                    <option value="">-- Hlavní složka --</option>
                                </select>
                            </div>
                        </div>
                    <?php endif; ?>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="other_file">Soubor *</label>
                            <div class="upload-area">
                                <label for="other_file" class="custom-file-button">Vyberte soubor</label>
                                <input type="file" name="file" id="other_file" class="hidden-file-input" required>
                                <div id="selected-file" class="selected-files-info"></div>
                            </div>
                            <small class="file-help">Podporované formáty: Všechny typy souborů (max 100MB)</small>
                        </div>
                    </div>

                    <div class="form-row">
                        <button type="submit" class="upload-btn" name="other_submit">
                            <i class="fas fa-upload"></i> Nahrát soubor
                        </button>
                    </div>
                </form>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php
// Function to render Other subfolder
function renderOtherSubfolder($folderName, $folderData, $categoryKey, $categoryInfo)
{
?>
    <div class="other-subfolder-card droppable-zone" 
        data-category="<?= htmlspecialchars($categoryKey) ?>"
        data-subfolder="<?= htmlspecialchars($folderName) ?>"
        onclick="toggleOtherSubfolder('<?= $categoryKey ?>-<?= $folderName ?>')"
        ondrop="handleOtherDrop(event)"
        ondragover="handleOtherDragOver(event)"
        ondragenter="handleOtherDragEnter(event)"
        ondragleave="handleOtherDragLeave(event)">

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
                    <button class="mini-btn edit-btn" onclick="event.stopPropagation(); editOtherSubfolder('<?= $categoryKey ?>', '<?= $folderName ?>')" title="Upravit podsložku">
                        <i class="fas fa-edit"></i>
                    </button>
                    <button class="mini-btn delete-btn" onclick="event.stopPropagation(); deleteOtherSubfolder('<?= $categoryKey ?>', '<?= $folderName ?>')" title="Smazat podsložku">
                        <i class="fas fa-trash"></i>
                    </button>
                <?php endif; ?>
                <i class="fas fa-chevron-right subfolder-toggle"></i>
            </div>
        </div>

        <div class="subfolder-content">
            <div class="subfolder-documents">
                <?php if (!empty($folderData['data']['documents'])): ?>
                    <?php foreach ($folderData['data']['documents'] as $other): ?>
                        <div class="subfolder-document draggable-file"
                            draggable="true"
                            data-file-name="<?= htmlspecialchars($other['original_name']) ?>"
                            data-current-category="<?= htmlspecialchars($categoryKey) ?>"
                            data-current-subfolder="<?= htmlspecialchars($folderName) ?>"
                            ondragstart="handleOtherDragStart(event)"
                            ondragend="handleOtherDragEnd(event)">
                            <div class="doc-icon">
                                <i class="<?= $other['file_type_info']['icon'] ?>" style="color: <?= $other['file_type_info']['color'] ?>;"></i>
                                <i class="fas fa-arrows-alt drag-handle"></i>
                            </div>
                            <div class="doc-info">
                                <span class="doc-name"><?= htmlspecialchars($other['name']) ?></span>
                                <span class="doc-size"><?= formatBytes($other['file_size']) ?></span>
                            </div>
                            <div class="doc-actions">
                                <?php if ($other['file_type_info']['previewable']): ?>
                                    <button class="mini-btn view-btn" onclick="event.stopPropagation(); previewOtherFile('<?= htmlspecialchars($other['file_path']) ?>', '<?= $other['file_type_info']['type'] ?>')" title="Náhled">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                <?php endif; ?>

                                <a href="<?= htmlspecialchars($other['file_path']) ?>" download class="mini-btn download-btn" onclick="event.stopPropagation();" title="Stáhnout">
                                    <i class="fas fa-download"></i>
                                </a>

                                <?php if (isAdmin()): ?>
                                    <button class="mini-btn edit-btn" onclick="event.stopPropagation(); editOtherFile('<?= addslashes($other['original_name']) ?>', '<?= $categoryKey ?>', '<?= addslashes($folderName) ?>', '<?= addslashes($other['name']) ?>')" title="Přejmenovat">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <button class="mini-btn delete-btn" onclick="event.stopPropagation(); deleteOtherFile('<?= addslashes($other['original_name']) ?>', '<?= $categoryKey ?>', '<?= addslashes($folderName) ?>')" title="Smazat">
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

// Function to render Other card
function renderOtherCard($other, $categoryKey)
{
?>
    <div class="other-card-grid draggable-file"
        draggable="true"
        data-file-name="<?= htmlspecialchars($other['original_name']) ?>"
        data-current-category="<?= htmlspecialchars($categoryKey) ?>"
        data-current-subfolder=""
        ondragstart="handleOtherDragStart(event)"
        ondragend="handleOtherDragEnd(event)">
        <div class="other-preview">
            <i class="fas fa-arrows-alt drag-handle"></i>
            <div class="file-preview-icon">
                <i class="<?= $other['file_type_info']['icon'] ?>" style="color: <?= $other['file_type_info']['color'] ?>; font-size: 32px;"></i>
            </div>
        </div>

        <div class="other-info-grid">
            <h5 class="other-title"><?= htmlspecialchars($other['name']) ?></h5>

            <div class="other-meta-grid">
                <span class="other-date">
                    <i class="fas fa-calendar"></i>
                    <?= date('d.m.Y', $other['date_added']) ?>
                </span>
                <span class="other-size">
                    <i class="fas fa-file"></i>
                    <?= formatBytes($other['file_size']) ?>
                </span>
            </div>

            <div class="other-actions-grid">
                <?php if ($other['file_type_info']['previewable']): ?>
                    <button class="grid-btn view-btn" onclick="previewOtherFile('<?= htmlspecialchars($other['file_path']) ?>', '<?= $other['file_type_info']['type'] ?>')">
                        <i class="fas fa-eye"></i>
                    </button>
                <?php endif; ?>

                <a href="<?= htmlspecialchars($other['file_path']) ?>" download class="grid-btn download-btn">
                    <i class="fas fa-download"></i>
                </a>

                <?php if (isAdmin()): ?>
                    <button class="grid-btn edit-btn" onclick="editOtherFile('<?= addslashes($other['original_name']) ?>', '<?= $categoryKey ?>', '', '<?= addslashes($other['name']) ?>')">
                        <i class="fas fa-edit"></i>
                    </button>
                    <button class="grid-btn delete-btn" onclick="deleteOtherFile('<?= addslashes($other['original_name']) ?>', '<?= $categoryKey ?>', '')">
                        <i class="fas fa-trash"></i>
                    </button>
                <?php endif; ?>
            </div>
        </div>
    </div>
<?php
}
?>

<!-- Modals would go here - same structure as edo.php but with other- prefixes -->

<script>
    // Other file management JavaScript functions
    let otherSubfolderData = {};

    document.addEventListener('DOMContentLoaded', function() {
        // Only run if we're on the other page
        if (!document.getElementById('other')) {
            return;
        }

        // Load Other subfolder data
        loadOtherSubfolderData();

        // Register other menu item if it doesn't exist
        if (window.registerMenuItem && typeof window.registerMenuItem === 'function') {
            window.registerMenuItem('other', 'Other');
        }

        // Close all categories by default
        document.querySelectorAll('#other .category-content').forEach(content => {
            if (content) {
                content.style.maxHeight = '0px';
            }
        });

        // Initialize form handlers
        initializeOtherFormHandlers();

        // Status messages handling
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

        // File input handler
        const fileInput = document.getElementById('other_file');
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
    });

    // Load Other subfolder data
    function loadOtherSubfolderData() {
        fetch('get-other-subfolders.php')
            .then(response => response.json())
            .then(data => {
                otherSubfolderData = data || {};
                updateOtherSubfolderOptions();
            })
            .catch(error => console.log('No Other subfolder data available yet:', error.message));
    }

    // Update Other subfolder dropdown
    function updateOtherSubfolderOptions() {
        const mainFolder = document.getElementById('other-category');
        const subfolderSelect = document.getElementById('other-subfolder');

        if (!mainFolder || !subfolderSelect) return;

        subfolderSelect.innerHTML = '<option value="">-- Hlavní složka --</option>';

        if (otherSubfolderData[mainFolder.value]) {
            otherSubfolderData[mainFolder.value].forEach(subfolder => {
                const option = document.createElement('option');
                option.value = subfolder;
                option.textContent = subfolder;
                subfolderSelect.appendChild(option);
            });
        }
    }

    // Toggle Other category
    function toggleOtherCategoryHeader(categoryKey) {
        if (!document.getElementById('other')) {
            return;
        }

        const categoryCard = document.querySelector(`#other [data-category="${categoryKey}"]`);

        if (!categoryCard) {
            console.warn('Could not find Other category card for:', categoryKey);
            return;
        }

        if (categoryCard.classList.contains('expanded')) {
            categoryCard.classList.remove('expanded');
        } else {
            document.querySelectorAll('#other .other-category.expanded').forEach(card => {
                card.classList.remove('expanded');
            });
            categoryCard.classList.add('expanded');
        }
    }

    // Toggle Other subfolder
    function toggleOtherSubfolder(subfolderId) {
        if (event) {
            event.stopPropagation();
            event.preventDefault();
        }

        if (!document.getElementById('other')) {
            return;
        }

        const subfolderCard = document.querySelector(`[onclick="toggleOtherSubfolder('${subfolderId}')"]`);

        if (!subfolderCard) {
            console.warn('Could not find Other subfolder card for:', subfolderId);
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

    // Show add Other subfolder modal
    function showAddOtherSubfolderModal(categoryKey) {
        if (event) {
            event.stopPropagation();
            event.preventDefault();
        }

        const modal = document.createElement('div');
        modal.className = 'modal';
        modal.style.display = 'flex';
        modal.innerHTML = `
            <div class="modal-content">
                <div class="modal-header">
                    <h2>Přidat podsložku</h2>
                    <button class="modal-close" onclick="this.closest('.modal').remove()">&times;</button>
                </div>
                <form>
                    <div class="modal-body">
                        <div class="form-group">
                            <label for="otherSubfolderName">Název podsložky *</label>
                            <input type="text" id="otherSubfolderName" required placeholder="Název nové podsložky" class="full-width">
                        </div>
                    </div>
                    <div class="modal-actions">
                        <button type="button" class="btn btn-secondary" onclick="this.closest('.modal').remove()">Zrušit</button>
                        <button type="submit" class="btn btn-primary">Vytvořit podsložku</button>
                    </div>
                </form>
            </div>
        `;

        document.body.appendChild(modal);

        const form = modal.querySelector('form');
        form.addEventListener('submit', function(e) {
            e.preventDefault();

            const subfolderName = document.getElementById('otherSubfolderName').value.trim();
            if (!subfolderName) {
                alert('Zadejte název podsložky');
                return;
            }

            const formData = new FormData();
            formData.append('parent_category', categoryKey);
            formData.append('subfolder_name', subfolderName);

            fetch('add-other-subfolder.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        modal.remove();
                        showStatusMessage('success', data.message);
                        setTimeout(() => location.reload(), 500);
                    } else {
                        showStatusMessage('error', data.message);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    showStatusMessage('error', 'Došlo k chybě při vytváření podsložky.');
                });
        });

        setTimeout(() => document.getElementById('otherSubfolderName').focus(), 100);
    }

    // Edit Other subfolder
    function editOtherSubfolder(categoryKey, subfolderName) {
        if (event) {
            event.stopPropagation();
            event.preventDefault();
        }

        const modal = document.createElement('div');
        modal.className = 'modal';
        modal.style.display = 'flex';
        modal.innerHTML = `
            <div class="modal-content">
                <div class="modal-header">
                    <h2>Upravit podsložku</h2>
                    <button class="modal-close" onclick="this.closest('.modal').remove()">&times;</button>
                </div>
                <form enctype="multipart/form-data">
                    <div class="modal-body">
                        <div class="form-group">
                            <label for="newOtherSubfolderName">Název podsložky *</label>
                            <input type="text" id="newOtherSubfolderName" value="${subfolderName}" required placeholder="Nový název podsložky" class="full-width">
                        </div>
                        <div class="form-group">
                            <label for="otherSubfolderFile">Nahrát soubor do podsložky (volitelné)</label>
                            <input type="file" id="otherSubfolderFile">
                            <small class="file-help">Podporované formáty: Všechny typy souborů (max 100MB)</small>
                        </div>
                    </div>
                    <div class="modal-actions">
                        <button type="button" class="btn btn-secondary" onclick="this.closest('.modal').remove()">Zrušit</button>
                        <button type="submit" class="btn btn-primary">Uložit změny</button>
                    </div>
                </form>
            </div>
        `;

        document.body.appendChild(modal);

        const form = modal.querySelector('form');
        form.addEventListener('submit', function(e) {
            e.preventDefault();

            const newName = document.getElementById('newOtherSubfolderName').value.trim();
            if (!newName) {
                alert('Zadejte název podsložky');
                return;
            }

            const formData = new FormData();
            formData.append('parent_category', categoryKey);
            formData.append('original_name', subfolderName);
            formData.append('new_name', newName);

            const fileInput = document.getElementById('otherSubfolderFile');
            if (fileInput.files.length > 0) {
                formData.append('subfolder_file', fileInput.files[0]);
            }

            fetch('edit-other-subfolder.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        modal.remove();
                        showStatusMessage('success', data.message);
                        setTimeout(() => location.reload(), 500);
                    } else {
                        showStatusMessage('error', data.message);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    showStatusMessage('error', 'Došlo k chybě při upravování podsložky.');
                });
        });
    }

    // Delete Other subfolder - complete implementation
    function deleteOtherSubfolder(categoryKey, subfolderName) {
        if (event) {
            event.stopPropagation();
            event.preventDefault();
        }

        if (confirm(`Opravdu chcete smazat podsložku "${subfolderName}"?\n\nTato akce smaže podsložku a všechny soubory v ní. Toto nelze vrátit zpět!`)) {
            const formData = new FormData();
            // Parametry pro univerzální handler
            formData.append('type', 'other');
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
                    showStatusMessage('error', 'Chyba při mazání podsložky.');
                });
        }
    }

    // Complete the delete file functionality
    function deleteOtherFile(fileName, category, subfolder) {
        if (confirm(`Opravdu chcete smazat soubor "${fileName}"?\n\nTato akce je nevratná!`)) {
            const formData = new FormData();
            formData.append('other_file', fileName);
            formData.append('category', category);
            formData.append('subfolder', subfolder || '');

            fetch('delete-other.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        showStatusMessage('success', data.message);
                        setTimeout(() => location.reload(), 500);
                    } else {
                        showStatusMessage('error', data.message);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    showStatusMessage('error', 'Došlo k chybě při mazání souboru.');
                });
        }
    }

    // Edit Other file - complete functionality
    function editOtherFile(originalName, category, subfolder, currentName) {
        const modal = document.createElement('div');
        modal.className = 'modal';
        modal.style.display = 'flex';
        modal.innerHTML = `
            <div class="modal-content">
                <div class="modal-header">
                    <h2>Přejmenovat soubor</h2>
                    <button class="modal-close" onclick="this.closest('.modal').remove()">&times;</button>
                </div>
                <form>
                    <div class="modal-body">
                        <div class="form-group">
                            <label for="newOtherFileName">Název souboru *</label>
                            <input type="text" id="newOtherFileName" value="${currentName.replace(/\.[^/.]+$/, '')}" required placeholder="Nový název souboru (bez přípony)" class="full-width">
                            <small class="file-help">Zadejte pouze název bez přípony - přípona zůstane zachována</small>
                        </div>
                    </div>
                    <div class="modal-actions">
                        <button type="button" class="btn btn-secondary" onclick="this.closest('.modal').remove()">Zrušit</button>
                        <button type="submit" class="btn btn-primary">Přejmenovat</button>
                    </div>
                </form>
            </div>
        `;

        document.body.appendChild(modal);

        const form = modal.querySelector('form');
        form.addEventListener('submit', function(e) {
            e.preventDefault();

            const newName = document.getElementById('newOtherFileName').value.trim();
            if (!newName) {
                alert('Zadejte název souboru');
                return;
            }

            const formData = new FormData();
            formData.append('category', category);
            formData.append('subfolder', subfolder || '');
            formData.append('original_name', originalName);
            formData.append('new_name', newName);

            fetch('edit-other-file.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        modal.remove();
                        showStatusMessage('success', data.message);
                        setTimeout(() => location.reload(), 500);
                    } else {
                        showStatusMessage('error', data.message);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    showStatusMessage('error', 'Došlo k chybě při přejmenovávání souboru.');
                });
        });

        setTimeout(() => document.getElementById('newOtherFileName').focus(), 100);
    }

    // Edit Other category - complete implementation
    function editOtherCategory(categoryKey) {
        if (event) event.stopPropagation();

        const modal = document.createElement('div');
        modal.className = 'modal';
        modal.style.display = 'flex';
        modal.innerHTML = `
        <div class="modal-content">
            <div class="modal-header">
                <h2>Upravit kategorii</h2>
                <button class="modal-close" onclick="this.closest('.modal').remove()">&times;</button>
            </div>
            <form enctype="multipart/form-data">
                <div class="modal-body">
                    <div class="form-group">
                        <label for="newOtherCategoryName">Název kategorie *</label>
                        <input type="text" id="newOtherCategoryName" value="${categoryKey}" required placeholder="Nový název kategorie" class="full-width">
                    </div>
                    <div class="form-group">
                        <label for="otherCategoryFile">Nahrát soubor do kategorie (volitelné)</label>
                        <input type="file" id="otherCategoryFile">
                        <small class="file-help">Podporované formáty: Všechny typy souborů (max 100MB)</small>
                    </div>
                </div>
                <div class="modal-actions">
                    <button type="button" class="btn btn-secondary" onclick="this.closest('.modal').remove()">Zrušit</button>
                    <button type="submit" class="btn btn-primary">Uložit změny</button>
                </div>
            </form>
        </div>
    `;

        document.body.appendChild(modal);

        const form = modal.querySelector('form');
        form.addEventListener('submit', function(e) {
            e.preventDefault();

            const newName = document.getElementById('newOtherCategoryName').value.trim();
            if (!newName) {
                alert('Zadejte název kategorie');
                return;
            }

            const formData = new FormData();

            // PŘIDÁNO: Typ složky pro univerzální handler
            formData.append('type', 'other');
            formData.append('original_name', categoryKey);
            formData.append('new_name', newName);

            const fileInput = document.getElementById('otherCategoryFile');
            if (fileInput.files.length > 0) {
                // Ujistíme se, že název pole sedí s tím, co čeká PHP (category_file)
                formData.append('category_file', fileInput.files[0]);
            }

            const submitBtn = form.querySelector('button[type="submit"]');
            if (submitBtn) submitBtn.disabled = true;

            // ZMĚNĚNO: Míříme na společný edit-category-handler.php
            fetch('edit-category-handler.php', {
                method: 'POST',
                body: formData
            })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        modal.remove();
                        showStatusMessage('success', data.message);
                        // ZRYCHLENÝ RELOAD: 500ms
                        setTimeout(() => location.reload(), 500);
                    } else {
                        showStatusMessage('error', data.message);
                        if (submitBtn) submitBtn.disabled = false;
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    showStatusMessage('error', 'Došlo k chybě při komunikaci se serverem.');
                    if (submitBtn) submitBtn.disabled = false;
                });
        });
    }

    function deleteOtherCategory(categoryKey) {
        if (event) event.stopPropagation();
        if (!confirm(`Smazat kategorii "${categoryKey}" a všechny soubory?\n\nTato akce je nevratná!`)) return;

        const formData = new FormData();
        formData.append('category', categoryKey);
        formData.append('type', 'other');

        fetch('delete-category-handler.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showStatusMessage('success', data.message);
                    setTimeout(() => location.reload(), 500);
                } else {
                    showStatusMessage('error', data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showStatusMessage('error', 'Došlo k chybě při mazání kategorie.');
            });
    }

    // Preview Other file functionality
    function previewOtherFile(filePath, fileType) {
        if (fileType === 'image') {
            window.open(filePath, '_blank');
        } else {
            alert('Náhled pro: ' + filePath);
        }
    }

    // Toggle folder inputs for custom category creation
    function toggleOtherFolderInputs() {
        const customInput = document.getElementById('custom-other-folder-name');
        const existingRow = document.getElementById('existing-other-folder-row');
        const categorySelect = document.getElementById('other-category');
        const subfolderSelect = document.getElementById('other-subfolder');

        if (!customInput) return;

        if (customInput.value.trim()) {
            if (existingRow) {
                existingRow.style.opacity = '0.5';
            }
            if (categorySelect) {
                categorySelect.disabled = true;
                categorySelect.value = '';
            }
            if (subfolderSelect) {
                subfolderSelect.disabled = true;
                subfolderSelect.value = '';
            }
        } else {
            if (existingRow) {
                existingRow.style.opacity = '1';
            }
            if (categorySelect) {
                categorySelect.disabled = false;
            }
            if (subfolderSelect) {
                subfolderSelect.disabled = false;
            }
        }
    }

    // Initialize Other form handlers
    function initializeOtherFormHandlers() {
        // Placeholder for form handlers
        console.log('Other form handlers initialized');
    }

    function formatFileSize(bytes) {
        if (!bytes || bytes === 0) return '0 B';
        const k = 1024;
        const sizes = ['B', 'KB', 'MB', 'GB'];
        const i = Math.floor(Math.log(bytes) / Math.log(k));
        return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
    }

    // Properly expose functions globally ONLY if on other page
    if (document.getElementById('other')) {
        window.toggleOtherSubfolder = toggleOtherSubfolder;
        window.toggleOtherCategoryHeader = toggleOtherCategoryHeader;
        window.editOtherSubfolder = editOtherSubfolder;
        window.deleteOtherSubfolder = deleteOtherSubfolder;
        window.showAddOtherSubfolderModal = showAddOtherSubfolderModal;
        window.deleteOtherFile = deleteOtherFile;
        window.editOtherCategory = editOtherCategory;
        window.deleteOtherCategory = deleteOtherCategory;
        window.toggleOtherFolderInputs = toggleOtherFolderInputs;
        window.editOtherFile = editOtherFile;
        window.previewOtherFile = previewOtherFile;
    }

    // Drag and Drop for Other files - Use unique variable names
    let draggedOtherElement = null;
    let draggedOtherFileData = null;

    const supportsFileSystemAccessOther = 'getAsFileSystemHandle' in DataTransferItem.prototype;

    function handleOtherDragStart(event) {
        console.log("🚀 Other drag started");

        draggedOtherElement = event.target.closest('.draggable-file');
        if (!draggedOtherElement) return;
        draggedOtherElement.classList.add('dragging');

        draggedOtherFileData = {
            fileName: draggedOtherElement.getAttribute('data-file-name'),
            currentCategory: draggedOtherElement.getAttribute('data-current-category'),
            currentSubfolder: draggedOtherElement.getAttribute('data-current-subfolder') || ''
        };

        event.dataTransfer.effectAllowed = 'move';
        event.dataTransfer.setData('text/plain', JSON.stringify(draggedOtherFileData));

        if (supportsFileSystemAccessOther) {
            try {
                const fileData = new Blob([JSON.stringify(draggedOtherFileData)], {
                    type: 'application/json'
                });
                const file = new File([fileData], 'other-move-data.json', {
                    type: 'application/json'
                });
                event.dataTransfer.items.add(file);
            } catch (error) {
                console.warn("⚠️ Could not set enhanced drag data:", error);
            }
        }
    }

    function handleOtherDragEnd(event) {
        if (draggedOtherElement) {
            draggedOtherElement.classList.remove('dragging');
        }

        document.querySelectorAll('.droppable-zone').forEach(zone => {
            zone.classList.remove('drag-over');
        });

        draggedOtherElement = null;
        draggedOtherFileData = null;
    }

    function handleOtherDragOver(event) {
        event.preventDefault();
        event.dataTransfer.dropEffect = 'move';
    }

    function handleOtherDragEnter(event) {
        event.preventDefault();
        event.stopPropagation();

        if (!draggedOtherFileData) return;

        const dropZone = event.currentTarget;
        const targetCategory = dropZone.getAttribute('data-category');
        const targetSubfolder = dropZone.getAttribute('data-subfolder') || '';

        if (targetCategory === draggedOtherFileData.currentCategory &&
            targetSubfolder === draggedOtherFileData.currentSubfolder) {
            return;
        }

        dropZone.classList.add('drag-over');
    }

    function handleOtherDragLeave(event) {
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

    async function handleOtherDrop(event) {
        event.preventDefault();
        event.stopPropagation();

        const dropZone = event.currentTarget;
        const targetCategory = dropZone.getAttribute('data-category');
        const targetSubfolder = dropZone.getAttribute('data-subfolder') || '';

        dropZone.classList.remove('drag-over');

        let fileData = null;

        if (supportsFileSystemAccessOther && event.dataTransfer.items.length > 0) {
            for (let i = 0; i < event.dataTransfer.items.length; i++) {
                const item = event.dataTransfer.items[i];

                try {
                    if (item.kind === 'file') {
                        const handle = await item.getAsFileSystemHandle();

                        if (handle && handle.kind === 'file') {
                            fileData = {
                                fileName: handle.name,
                                currentCategory: draggedOtherFileData?.currentCategory,
                                currentSubfolder: draggedOtherFileData?.currentSubfolder
                            };
                            break;
                        }
                    }
                } catch (error) {
                    console.warn("⚠️ File System Access API failed:", error);
                }
            }
        }

        if (!fileData && draggedOtherFileData) {
            fileData = draggedOtherFileData;
        }

        if (!fileData) {
            try {
                const transferData = event.dataTransfer.getData('text/plain');
                if (transferData) {
                    fileData = JSON.parse(transferData);
                }
            } catch (error) {
                console.error("❌ Could not parse transfer data:", error);
            }
        }

        if (!fileData) {
            showStatusMessage('error', 'Nepodařilo se získat informace o souboru');
            return;
        }

        if (targetCategory === fileData.currentCategory &&
            targetSubfolder === fileData.currentSubfolder) {
            return;
        }

        await moveOtherFile(fileData, targetCategory, targetSubfolder);
    }

    async function moveOtherFile(fileData, targetCategory, targetSubfolder) {
        showStatusMessage('info', 'Přesouvám soubor...');

        const formData = new FormData();
        formData.append('fileName', fileData.fileName);
        formData.append('sourceCategory', fileData.currentCategory);
        formData.append('sourceSubfolder', fileData.currentSubfolder);
        formData.append('targetCategory', targetCategory);
        formData.append('targetSubfolder', targetSubfolder);
        formData.append('move_other_file', '1');

        try {
            const response = await fetch(window.location.href, {
                method: 'POST',
                body: formData
            });

            if (response.ok) {
                showStatusMessage('success', '✅ Soubor byl úspěšně přesunut!');
                setTimeout(() => {
                    location.reload();
                }, 1000);
            } else {
                showStatusMessage('error', '❌ Nepodařilo se přesunout soubor.');
            }
        } catch (error) {
            console.error('Error moving file:', error);
            showStatusMessage('error', '❌ Chyba při přesouvání souboru.');
        }
    }

    function showStatusMessage(type, message) {
        const statusDiv = document.getElementById('status-messages');
        if (statusDiv) {
            const messageDiv = document.createElement('div');
            messageDiv.className = `status-message status-${type}`;
            messageDiv.innerHTML = `<i class="fas ${type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle'}"></i> ${message}`;
            statusDiv.appendChild(messageDiv);

            setTimeout(() => {
                if (messageDiv && messageDiv.parentNode) {
                    messageDiv.remove();
                }
            }, 5000);
        }
    }
</script>