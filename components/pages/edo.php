<?php
// BUFFER OUTPUT IMMEDIATELY TO FIX "HEADERS ALREADY SENT"
ob_start();

// Set proper encoding for Czech characters  
mb_internal_encoding('UTF-8');

require_once 'access.php';


// Handle file move request
if (isset($_POST['move_edo_file']) && isAdmin()) {
    // CLEAN BUFFER: Remove any HTML (like header.php) that was generated before this point
    while (ob_get_level()) {
        ob_end_clean();
    }

    // Use basename() to automatically strip hacks like "../" or "/"
    $fileName = basename($_POST['fileName'] ?? '');
    $sourceCategory = basename($_POST['sourceCategory'] ?? '');
    $sourceSubfolder = basename($_POST['sourceSubfolder'] ?? '');
    $targetCategory = basename($_POST['targetCategory'] ?? '');
    $targetSubfolder = basename($_POST['targetSubfolder'] ?? '');

    // Validate inputs
    if (empty($fileName) || empty($sourceCategory) || empty($targetCategory)) {
        http_response_code(400);
        exit;
    }

    // Check if same location

    try {
        // Build paths
        $edoBaseDir = getRoot('edo');

        // Construct paths carefully
        $sourcePath = $edoBaseDir . $sourceCategory . '/';
        if (!empty($sourceSubfolder)) $sourcePath .= $sourceSubfolder . '/';

        $targetPath = $edoBaseDir . $targetCategory . '/';
        if (!empty($targetSubfolder)) $targetPath .= $targetSubfolder . '/';

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
function getEdoCategories()
{
    $edoDir = getRoot('edo');
    $categories = [];

    if (is_dir($edoDir)) {
        $items = array_diff(scandir($edoDir), ['.', '..']);

        foreach ($items as $item) {
            $itemPath = $edoDir . $item;
            if (is_dir($itemPath)) {
                // Assign different colors to different categories
                $colors = ['#2ecc71', '#3498db', '#9b59b6', '#e74c3c', '#f39c12', '#1abc9c'];
                $colorIndex = crc32($item) % count($colors);

                $categories[$item] = [
                    'name' => str_replace('_', ' ', ucfirst($item)), // Convert underscores to spaces and capitalize
                    'color' => $colors[$colorIndex]
                ];
            }
        }
    }

    // If no categories exist, provide defaults
    if (empty($categories)) {
        $categories = [
            'physical' => ['name' => 'Fyzické běhy', 'color' => '#2ecc71'],
            'online' => ['name' => 'Online', 'color' => '#3498db']
        ];
    }

    return $categories;
}

// Get dynamic categories
$edoCategories = getEdoCategories();

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

// Helper function to get audio/video duration (basic estimation)
function getEdoDuration($filePath, $fileExtension)
{
    if (!file_exists($filePath)) return null;
    $fileSize = filesize($filePath);

    // For video files, estimate differently
    if (in_array($fileExtension, ['mp4', 'avi', 'mov', 'wmv', 'flv', 'webm', 'mkv'])) {
        // Rough estimation: Video at ~500KB per second
        $estimatedSeconds = $fileSize / 500000;
    } else if (in_array($fileExtension, ['mp3', 'wav', 'ogg', 'm4a', 'aac'])) {
        // Rough estimation: MP3 at 128kbps ≈ 16KB per second
        $estimatedSeconds = $fileSize / 16000;
    } else {
        // For other files, don't show duration
        return null;
    }

    $minutes = floor($estimatedSeconds / 60);
    $seconds = floor($estimatedSeconds % 60);
    return sprintf('%02d:%02d', $minutes, $seconds);
}

// Helper function to get file type info
function getFileTypeInfo($extension)
{
    $extension = strtolower($extension);

    switch ($extension) {
        // Audio files
        case 'mp3':
        case 'wav':
        case 'ogg':
        case 'm4a':
        case 'aac':
            return [
                'type' => 'audio',
                'icon' => 'fas fa-headphones',
                'color' => '#9b59b6',
                'playable' => true
            ];

            // Video files
        case 'mp4':
        case 'avi':
        case 'mov':
        case 'wmv':
        case 'flv':
        case 'webm':
        case 'mkv':
            return [
                'type' => 'video',
                'icon' => 'fas fa-video',
                'color' => '#e74c3c',
                'playable' => true
            ];

            // PDF files
        case 'pdf':
            return [
                'type' => 'pdf',
                'icon' => 'fas fa-file-pdf',
                'color' => '#dc3545',
                'playable' => false
            ];

            // Text files
        case 'txt':
        case 'doc':
        case 'docx':
        case 'rtf':
            return [
                'type' => 'text',
                'icon' => 'fas fa-file-alt',
                'color' => '#007bff',
                'playable' => false
            ];

        default:
            return [
                'type' => 'other',
                'icon' => 'fas fa-file',
                'color' => '#6c757d',
                'playable' => false
            ];
    }
}

// Function to scan for subfolders and EDO files
function scanEdoRecursive($dir, $categoryKey, $categoryInfo, $depth = 0)
{
    $result = ['folders' => [], 'documents' => []];

    if (!is_dir($dir)) return $result;

    $items = array_diff(scandir($dir), ['.', '..']);

    foreach ($items as $item) {
        $itemPath = $dir . $item;

        if (is_dir($itemPath)) {
            // It's a subfolder
            $subResult = scanEdoRecursive($itemPath . '/', $categoryKey, $categoryInfo, $depth + 1);
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
            $allowedExtensions = ['mp3', 'wav', 'ogg', 'm4a', 'aac', 'mp4', 'avi', 'mov', 'wmv', 'flv', 'webm', 'mkv', 'pdf', 'txt', 'doc', 'docx', 'rtf'];

            if (in_array($extension, $allowedExtensions)) {
                $fileTypeInfo = getFileTypeInfo($extension);
                $duration = getEdoDuration($itemPath, $extension);

                $fileData = [
                    'name' => pathinfo($item, PATHINFO_FILENAME),
                    'original_name' => $item,
                    'file_path' => str_replace($_SERVER['DOCUMENT_ROOT'], '', $itemPath),
                    'file_size' => filesize($itemPath),
                    'file_extension' => $extension,
                    'date_added' => filemtime($itemPath),
                    'duration' => $duration,
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
if (!function_exists('countAllDocuments')) {
    function countAllDocuments($categoryData)
    {
        $count = count($categoryData['documents']); // Count documents in main category

        // Add documents from all subfolders
        if (!empty($categoryData['folders'])) {
            foreach ($categoryData['folders'] as $folder) {
                $count += countAllDocuments($folder['data']);
            }
        }

        return $count;
    }
}
?>

<style>
    #edo .content-area {
        display: flex;
        flex-direction: column;
        gap: 30px;
    }

    .add-edo-section {
        background: white;
        border-radius: 10px;
        padding: 25px;
        margin-bottom: 25px;
        box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
    }

    .add-edo-section h2 {
        color: #2c3e50;
        margin-top: 0;
        margin-bottom: 20px;
        font-size: 20px;
    }

    .edo-form {
        display: flex;
        flex-direction: column;
        gap: 15px;
    }

    .edo-grid {
        display: flex;
        flex-direction: column;
        gap: 20px;
    }

    /* Fix the category toggle functionality - match documents.php pattern */
    .edo-category {
        background: white;
        border-radius: 10px;
        box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        overflow: hidden;
        transition: all 0.3s ease;
    }

    .edo-category.expanded {
        grid-column: 1 / -1;
        max-width: none;
        transform: none !important;
        z-index: 10;
    }

    .edo-category.expanded .category-content {
        max-height: none !important;
        padding: 20px !important;
        display: block !important;
        opacity: 1 !important;
        overflow: visible !important;
    }

    .edo-category.expanded .category-toggle i {
        transform: rotate(180deg);
    }

    .edo-category:hover:not(.expanded) {
        box-shadow: 0 5px 15px rgba(0, 0, 0, 0.15);
    }

    .edo-category:not(.expanded) .category-content {
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

    .edo-count {
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

    .edo-list {
        padding: 0;
        max-height: 0;
        overflow: hidden;
        transition: max-height 0.3s ease-in-out;
    }

    .edo-list.open {
        max-height: auto;
    }

    .edo-card {
        padding: 20px 25px;
        border-bottom: 1px solid #f0f0f0;
        transition: background 0.2s;
    }

    .edo-card:hover {
        background: #f8f9fa;
    }

    .edo-name {
        font-weight: 600;
        font-size: 16px;
        color: #2c3e50;
        margin: 0;
    }

    .no-edo {
        text-align: center;
        padding: 60px 20px;
        color: #6c757d;
        background: white;
        border-radius: 10px;
        box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
    }

    /* Add category actions styling */
    .category-actions {
        margin-bottom: 20px;
        display: flex;
        gap: 10px;
    }

    .add-edo-subfolder-btn {
        background: linear-gradient(135deg, #17a2b8 0%, #138496 100%) !important;
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

    .add-edo-subfolder-btn:hover {
        background: #138496;
    }

    /* Subfolder styling - Fix the toggle functionality */
    .edo-subfolders-section {
        margin-bottom: 25px;
    }

    .edo-subfolders-section h4 {
        color: #495057;
        font-size: 14px;
        font-weight: 600;
        margin: 0 0 12px 0;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    .edo-subfolders-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
        gap: 12px;
    }

    .edo-subfolder-card {
        background: #f8f9fa;
        border: 1px solid #e9ecef;
        border-radius: 8px;
        padding: 15px;
        cursor: pointer;
        transition: all 0.2s;
    }

    .edo-subfolder-card:hover {
        background: #e9ecef;
        transform: translateY(-1px);
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
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

    /* Default state - content hidden */
    .subfolder-content {
        max-height: 0;
        overflow: hidden;
        transition: max-height 0.3s ease, margin-top 0.3s ease, padding-top 0.3s ease;
        margin-top: 0;
        padding-top: 0;
    }

    /* When open - show content and expand card */
    .edo-subfolder-card.open {
        grid-column: 1 / -1;
        width: 100%;
        max-width: none;
        background: #fff;
        border-color: #dee2e6;
    }

    .edo-subfolder-card.open .subfolder-content {
        max-height: 1000px;
        /* Large enough value */
        margin-top: 12px;
        padding-top: 12px;
        border-top: 1px solid #dee2e6;
    }

    .edo-subfolder-card.open .subfolder-toggle {
        transform: rotate(90deg);
    }

    .subfolder-documents {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(320px, 1fr)) !important;
        gap: 10px;
    }

    .subfolder-document {
        background: white;
        width: 100%;
        box-sizing: border-box;
        border-radius: 8px;
        position: relative;
        padding: 10px !important;
        display: flex;
        gap: 10px;
        align-items: center;
        justify-content: space-between;
        box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
        transition: transform 0.2s;
        overflow: hidden;
    }

    .subfolder-document:hover {
        background: #f8f9fa;
        border-color: #dee2e6;
    }

    .doc-icon {
        flex-shrink: 0;
        font-size: 16px;
        position: relative;
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
    .edo-documents-section {
        margin-top: 0 !important;
        padding-top: 0 !important;
    }

    .edo-documents-section h4 {
        color: #495057;
        font-size: 14px;
        font-weight: 600;
        margin: 0 0 12px 0;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    .edo-documents-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
        gap: 15px;
    }

    .edo-card-grid {
        background: white;
        border: 1px solid #e9ecef;
        border-radius: 8px;
        overflow: hidden;
        transition: all 0.2s;
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
        opacity: 1 !important;
        display: block !important;
    }

    .edo-card-grid:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
        border-color: #dee2e6;
    }

    .edo-preview {
        height: 120px;
        display: flex;
        align-items: center;
        justify-content: center;
        background: #f8f9fa;
        border-bottom: 1px solid #e9ecef;
        overflow: hidden;
        position: relative;
    }

    .file-preview-icon i {
        font-size: 48px;
        opacity: 0.8;
    }

    .edo-info-grid {
        padding: 15px;
        display: flex;
        flex-direction: column;
        gap: 8px;
    }

    .edo-title {
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

    .edo-meta-grid {
        display: flex;
        justify-content: space-between;
        align-items: center;
        font-size: 11px;
        color: #6c757d;
        margin-top: auto;
        flex-wrap: wrap;
        gap: 8px;
    }

    .edo-date,
    .edo-size,
    .edo-duration {
        display: flex;
        align-items: center;
        gap: 4px;
    }

    .edo-actions-grid {
        display: flex;
        gap: 6px;
        justify-content: flex-end;
        margin-top: 8px;
        padding-top: 8px;
        border-top: 1px solid #f0f0f0;
    }

    /* Media modal styling */
    .media-modal .modal-content {
        max-width: 90vw;
        max-height: 90vh;
    }

    .media-player-body {
        padding: 20px;
        text-align: center;
    }

    #globalVideoPlayer {
        border-radius: 8px;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
    }

    /* File type specific styling */
    .view-btn:hover {
        color: #007bff !important;
        border-color: #007bff !important;
    }

    .mini-btn.view-btn:hover {
        color: #007bff !important;
    }

    /* EDO Modal Styles - copied from videos.php */
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

    /* Video Modal Styles */
    .video-modal .modal-content {
        max-width: 90vw;
        max-height: 90vh;
    }

    .video-modal-body {
        padding: 20px;
        text-align: center;
    }

    .modal-preview-video,
    .modal-preview-audio {
        max-width: 100%;
        max-height: 70vh;
        border-radius: 8px;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
        /* Disable text selection */
        -webkit-user-select: none;
        -moz-user-select: none;
        -ms-user-select: none;
        user-select: none;
    }

    /* Additional protection against downloads for non-admins */
    <?php if (!isAdmin()): ?>.modal-preview-video::-webkit-media-controls-download-button {
        display: none;
    }

    .modal-preview-audio::-webkit-media-controls-download-button {
        display: none;
    }

    .modal-preview-video::-webkit-media-controls-enclosure {
        overflow: hidden;
    }

    .modal-preview-audio::-webkit-media-controls-enclosure {
        overflow: hidden;
    }

    <?php endif; ?>

    /* Drag and Drop Styles */
    .edo-card-grid.dragging {
        opacity: 0.5;
        transform: scale(0.95);
        cursor: grabbing;
    }

    .subfolder-document.dragging {
        opacity: 0.5;
        transform: scale(0.95);
        cursor: grabbing;
    }

    .edo-subfolder-card.drag-over {
        background: #e3f2fd !important;
        border-color: #2196f3 !important;
        box-shadow: 0 0 15px rgba(33, 150, 243, 0.3) !important;
    }

    .edo-category.drag-over {
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

    .edo-subfolder-card.drag-over .drop-zone-indicator {
        display: flex;
    }

    .edo-category.drag-over .drop-zone-indicator {
        display: flex;
    }

    .edo-subfolder-card {
        position: relative;
    }

    .edo-category {
        position: relative;
    }

    /* Enhanced visual feedback */
    .draggable-file {
        cursor: grab;
        transition: transform 0.2s, box-shadow 0.2s;
    }

    .draggable-file:hover {
        transform: translateY(-1px);
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
    }
</style>

<div class="page-content" id="edo">
    <div class="content-area">
        <div id="status-messages"></div>

        <div class="edo-grid-container">
            <?php
            $edoDir = getRoot('edo');
            $edoByCategory = [];

            // Scan for EDO files and subfolders
            if (is_dir($edoDir)) {
                foreach ($edoCategories as $categoryKey => $categoryInfo) {
                    $categoryDir = $edoDir . $categoryKey . '/';
                    if (is_dir($categoryDir)) {
                        $categoryData = scanEdoRecursive($categoryDir, $categoryKey, $categoryInfo);
                        if (!empty($categoryData['documents']) || !empty($categoryData['folders'])) {
                            $edoByCategory[$categoryKey] = [
                                'info' => $categoryInfo,
                                'data' => $categoryData
                            ];
                        }
                    }
                }
            }

            if (empty($edoByCategory)): ?>
                <div class="no-edo">
                    <i class="fas fa-file-audio"></i>
                    <p>Zatím nejsou žádné EDO soubory v systému.</p>
                </div>
            <?php else: ?>
                <div class="categories-grid">
                    <?php foreach ($edoByCategory as $categoryKey => $category): ?>
                        <div class="edo-category droppable-zone"
                            data-category="<?= $categoryKey ?>"
                            style="border-top: 4px solid <?= $category['info']['color'] ?>"
                            ondrop="handleEdoDrop(event)"
                            ondragover="handleEdoDragOver(event)"
                            ondragenter="handleEdoDragEnter(event)"
                            ondragleave="handleEdoDragLeave(event)">

                            <div class="drop-zone-indicator">
                                <i class="fas fa-file-import"></i> &nbsp; Přetáhněte soubor do hlavní složky
                            </div>

                            <div class="category-header category-header-clickable" onclick="toggleEdoCategoryHeader('<?= $categoryKey ?>')">
                                <div class="category-info">
                                    <h3 style="color: <?= $category['info']['color'] ?>">
                                        <i class="fas fa-folder"></i>
                                        <?= htmlspecialchars($category['info']['name']) ?>
                                    </h3>
                                    <span class="edo-count">
                                        <?= countAllDocuments($category['data']) ?> souborů
                                        <?php if (!empty($category['data']['folders'])): ?>
                                            • <?= count($category['data']['folders']) ?> podsložek
                                        <?php endif; ?>
                                    </span>
                                </div>

                                <?php if (isAdmin()): ?>
                                    <div class="category-admin-actions" onclick="event.stopPropagation()">
                                        <button class="admin-btn edit-btn" onclick="editEdoCategory('<?= htmlspecialchars($categoryKey, ENT_QUOTES, 'UTF-8') ?>')" title="Přejmenovat kategorii">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <button class="admin-btn delete-btn" onclick="deleteEdoCategory('<?= htmlspecialchars($categoryKey, ENT_QUOTES, 'UTF-8') ?>')" title="Smazat kategorii">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </div>
                                <?php endif; ?>

                                <div class="category-toggle">
                                    <i class="fas fa-chevron-down"></i>
                                </div>
                            </div>

                            <div class="category-content" id="edo-category-content-<?= $categoryKey ?>">
                                <?php if (canUpload()): ?>
                                    <div class="category-actions">
                                        <button class="action-btn add-edo-subfolder-btn" onclick="showAddEdoSubfolderModal('<?= $categoryKey ?>')">
                                            <i class="fas fa-folder-plus"></i> Přidat podsložku
                                        </button>
                                    </div>
                                <?php endif; ?>

                                <?php if (!empty($category['data']['folders'])): ?>
                                    <div class="edo-subfolders-section">
                                        <h4>Podsložky</h4>
                                        <div class="edo-subfolders-grid">
                                            <?php foreach ($category['data']['folders'] as $folderName => $folderData): ?>
                                                <?php renderEdoSubfolder($folderName, $folderData, $categoryKey, $category['info']); ?>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                <?php endif; ?>

                                <?php if (!empty($category['data']['documents'])): ?>
                                    <div class="edo-documents-section">
                                        <h4>EDO soubory</h4>
                                        <div class="edo-documents-grid">
                                            <?php foreach ($category['data']['documents'] as $edo): ?>
                                                <?php renderEdoCard($edo, $categoryKey); ?>
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

        <?php if (canUpload()): ?>
            <div class="add-edo-section">
                <h2>Nahrát soubor</h2>
                <form action="upload-category-form-handler.php" method="post" enctype="multipart/form-data" class="edo-form">

                    <input type="hidden" name="type" value="edo">
                    <input type="hidden" name="redirect" value="edo">

                    <div class="form-row">
                        <div class="form-group">
                            <label for="custom-edo-folder-name">Vytvořit novou kategorii (volitelné)</label>
                            <input type="text" id="custom-edo-folder-name" name="custom_category" class="full-width"
                                   placeholder="Zadejte název pro novou kategorii..."
                                   onchange="toggleEdoFolderInputs()"
                                   oninput="toggleEdoFolderInputs()">
                            <small class="file-help">Pokud zadáte název, vytvoří se nová kategorie s tímto názvem</small>
                        </div>
                    </div>

                    <div class="form-row" id="existing-edo-folder-row">
                        <div class="form-group">
                            <label for="edo-category">Nebo vyberte existující kategorii</label>
                            <select id="edo-category" name="category" class="full-width" onchange="updateEdoSubfolderOptions()">
                                <option value="">-- Vyberte kategorii --</option>
                                <?php foreach ($edoCategories as $key => $category): ?>
                                    <option value="<?= $key ?>"><?= htmlspecialchars($category['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="edo-subfolder">Podsložka</label>
                            <select id="edo-subfolder" name="subfolder" class="full-width">
                                <option value="">-- Hlavní složka --</option>
                            </select>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="edo_file">Soubor *</label>
                            <div class="upload-area">
                                <label for="edo_file" class="custom-file-button">Vyberte soubor</label>
                                <input type="file" name="file" id="edo_file" accept=".mp3,.wav,.ogg,.m4a,.aac,.mp4,.avi,.mov,.wmv,.flv,.webm,.mkv,.pdf,.txt,.doc,.docx,.rtf" class="hidden-file-input" required>
                                <div id="selected-file" class="selected-files-info"></div>
                            </div>
                        </div>
                    </div>

                    <div class="form-row">
                        <button type="submit" class="upload-btn" name="edo_submit">
                            <i class="fas fa-upload"></i> Nahrát soubor
                        </button>
                    </div>
                </form>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php
// Function to render EDO subfolder
function renderEdoSubfolder($folderName, $folderData, $categoryKey, $categoryInfo)
{
?>
    <div class="edo-subfolder-card droppable-zone"
        data-category="<?= htmlspecialchars($categoryKey) ?>"
        data-subfolder="<?= htmlspecialchars($folderName) ?>"
        onclick="toggleEdoSubfolder('<?= $categoryKey ?>-<?= $folderName ?>')"
        ondrop="handleEdoDrop(event)"
        ondragover="handleEdoDragOver(event)"
        ondragenter="handleEdoDragEnter(event)"
        ondragleave="handleEdoDragLeave(event)">

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
                    <button class="mini-btn edit-btn" onclick="event.stopPropagation(); editEdoSubfolder('<?= $categoryKey ?>', '<?= $folderName ?>')" title="Upravit podsložku">
                        <i class="fas fa-edit"></i>
                    </button>
                    <button class="mini-btn delete-btn" onclick="event.stopPropagation(); deleteEdoSubfolder('<?= $categoryKey ?>', '<?= $folderName ?>')" title="Smazat podsložku">
                        <i class="fas fa-trash"></i>
                    </button>
                <?php endif; ?>
                <i class="fas fa-chevron-right subfolder-toggle"></i>
            </div>
        </div>

        <div class="subfolder-content">
            <div class="subfolder-documents">
                <?php if (!empty($folderData['data']['documents'])): ?>
                    <?php foreach ($folderData['data']['documents'] as $edo): ?>
                        <div class="subfolder-document <?= isAdmin() ? 'draggable-file' : '' ?>"
                            draggable="<?= isAdmin() ? 'true' : 'false' ?>"
                            data-file-name="<?= htmlspecialchars($edo['original_name']) ?>"
                            data-current-category="<?= htmlspecialchars($categoryKey) ?>"
                            data-current-subfolder="<?= htmlspecialchars($folderName) ?>"
                            <?php if (isAdmin()): ?>
                            ondragstart="handleEdoDragStart(event)"
                            ondragend="handleEdoDragEnd(event)"
                            <?php endif; ?>>

                            <div class="doc-icon">
                                <i class="<?= $edo['file_type_info']['icon'] ?>" style="color: <?= $edo['file_type_info']['color'] ?>;"></i>
                                <?php if (isAdmin()): ?>
                                <i class="fas fa-arrows-alt drag-handle"></i>
                                <?php endif; ?>
                            </div>
                            <div class="doc-info">
                                <span class="doc-name"><?= htmlspecialchars($edo['name']) ?></span>
                                <span class="doc-size"><?= formatBytes($edo['file_size']) ?></span>
                            </div>
                            <div class="doc-actions">
                                <?php if ($edo['file_type_info']['playable']): ?>
                                    <button class="mini-btn play-btn" onclick="event.stopPropagation(); toggleEdo(this, '<?= htmlspecialchars($edo['file_path']) ?>', '<?= $edo['file_type_info']['type'] ?>')" title="Přehrát">
                                        <i class="fas fa-play"></i>
                                    </button>
                                <?php endif; ?>

                                <?php if ($edo['file_extension'] === 'pdf'): ?>
                                    <a href="<?= htmlspecialchars($edo['file_path']) ?>" target="_blank" class="mini-btn view-btn" onclick="event.stopPropagation();" title="Zobrazit">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                <?php endif; ?>

                                <a href="<?= htmlspecialchars($edo['file_path']) ?>" download class="mini-btn download-btn" onclick="event.stopPropagation();" title="Stáhnout">
                                    <i class="fas fa-download"></i>
                                </a>

                                <?php if (isAdmin()): ?>
                                    <button class="mini-btn edit-btn" onclick="event.stopPropagation(); editEdoFile('<?= addslashes($edo['original_name']) ?>', '<?= $categoryKey ?>', '<?= addslashes($folderName) ?>', '<?= addslashes($edo['name']) ?>')" title="Přejmenovat">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <button class="mini-btn delete-btn" onclick="event.stopPropagation(); deleteEdoFile('<?= addslashes($edo['original_name']) ?>', '<?= $categoryKey ?>', '<?= addslashes($folderName) ?>')" title="Smazat">
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

// Function to render EDO card
function renderEdoCard($edo, $categoryKey)
{
?>
    <div class="edo-card-grid <?= isAdmin() ? 'draggable-file' : '' ?>"
        draggable="<?= isAdmin() ? 'true' : 'false' ?>"
        data-file-name="<?= htmlspecialchars($edo['original_name']) ?>"
        data-current-category="<?= htmlspecialchars($categoryKey) ?>"
        data-current-subfolder=""
        <?php if (isAdmin()): ?>
        ondragstart="handleEdoDragStart(event)"
        ondragend="handleEdoDragEnd(event)"
        <?php endif; ?>>

        <div class="edo-preview">
            <?php if (isAdmin()): ?>
            <i class="fas fa-arrows-alt drag-handle"></i>
            <?php endif; ?>
            <div class="file-preview-icon">
                <i class="<?= $edo['file_type_info']['icon'] ?>" style="color: <?= $edo['file_type_info']['color'] ?>; font-size: 32px;"></i>

            </div>
        </div>

        <div class="edo-info-grid">
            <h5 class="edo-title"><?= htmlspecialchars($edo['name']) ?></h5>

            <div class="edo-meta-grid">
                <span class="edo-date">
                    <i class="fas fa-calendar"></i>
                    <?= date('d.m.Y', $edo['date_added']) ?>
                </span>
                <span class="edo-size">
                    <i class="fas fa-file"></i>
                    <?= formatBytes($edo['file_size']) ?>
                </span>
                <?php if ($edo['duration']): ?>
                    <span class="edo-duration">
                        <i class="fas fa-clock"></i>
                        <?= $edo['duration'] ?>
                    </span>
                <?php endif; ?>
            </div>

            <div class="edo-actions-grid">
                <?php if ($edo['file_type_info']['playable']): ?>
                    <button class="grid-btn play-btn" onclick="toggleEdo(this, '<?= htmlspecialchars($edo['file_path']) ?>', '<?= $edo['file_type_info']['type'] ?>')">
                        <i class="fas fa-play"></i>
                    </button>
                <?php endif; ?>

                <?php if ($edo['file_extension'] === 'pdf'): ?>
                    <a href="<?= htmlspecialchars($edo['file_path']) ?>" target="_blank" class="grid-btn view-btn">
                        <i class="fas fa-eye"></i>
                    </a>
                <?php endif; ?>

                <a href="<?= htmlspecialchars($edo['file_path']) ?>" download class="grid-btn download-btn">
                    <i class="fas fa-download"></i>
                </a>

                <?php if (isAdmin()): ?>
                    <button class="grid-btn edit-btn" onclick="editEdoFile('<?= addslashes($edo['original_name']) ?>', '<?= $categoryKey ?>', '', '<?= addslashes($edo['name']) ?>')">
                        <i class="fas fa-edit"></i>
                    </button>
                    <button class="grid-btn delete-btn" onclick="deleteEdoFile('<?= addslashes($edo['original_name']) ?>', '<?= $categoryKey ?>', '')">
                        <i class="fas fa-trash"></i>
                    </button>
                <?php endif; ?>
            </div>
        </div>
    </div>
<?php
}
?>

<div id="addEdoSubfolderModal" class="modal" style="display: none;">
    <div class="modal-content">
        <div class="modal-header">
            <h2>Přidat EDO podsložku</h2>
            <button class="modal-close" onclick="closeAddEdoSubfolderModal()">&times;</button>
        </div>
        <form id="addEdoSubfolderForm">
            <input type="hidden" id="edoParentCategory" name="parent_category">

            <div class="form-group">
                <label for="edoSubfolderName">Název podsložky *</label>
                <input type="text" id="edoSubfolderName" name="subfolder_name" required
                    placeholder="Název nové podsložky" class="full-width">
            </div>

            <div class="modal-actions">
                <button type="button" class="btn btn-secondary" onclick="closeAddEdoSubfolderModal()">Zrušit</button>
                <button type="submit" class="btn btn-primary">Vytvořit podsložku</button>
            </div>
        </form>
    </div>
</div>

<div id="editEdoSubfolderModal" class="modal" style="display: none;">
    <div class="modal-content">
        <div class="modal-header">
            <h2>Upravit EDO podsložku</h2>
            <button class="modal-close" onclick="closeEditEdoSubfolderModal()">&times;</button>
        </div>
        <form id="editEdoSubfolderForm" enctype="multipart/form-data">
            <input type="hidden" id="editEdoSubfolderParentCategory" name="parent_category">
            <input type="hidden" id="editEdoSubfolderOriginalName" name="original_name">

            <div class="form-group">
                <label for="editEdoSubfolderName">Název podsložky *</label>
                <input type="text" id="editEdoSubfolderName" name="new_name" required
                    placeholder="Nový název podsložky" class="full-width">
            </div>

            <div class="form-group">
                <label for="editEdoSubfolderFile">Nahrát soubor do podsložky (volitelné)</label>
                <div class="upload-area">
                    <label for="editEdoSubfolderFile" class="custom-file-button">Vyberte soubor</label>
                    <input type="file" name="subfolder_file" id="editEdoSubfolderFile" accept=".mp3,.wav,.ogg,.m4a,.aac,.mp4,.avi,.mov,.wmv,.flv,.webm,.mkv,.pdf,.txt,.doc,.docx,.rtf,.ppt,pptx" class="hidden-file-input">
                    <div id="edit-edo-subfolder-selected-file" class="selected-files-info"></div>
                </div>
                <small class="file-help">Podporované formáty: Audio, Video, PDF, Text (max 500MB)</small>
            </div>

            <div class="form-group">
                <label for="editEdoSubfolderCustomFilename">Přejmenovat soubor (volitelné)</label>
                <input type="text" id="editEdoSubfolderCustomFilename" name="custom_filename" class="full-width" placeholder="Ponechte prázdné pro původní název">
                <small class="file-help">Zadejte pouze název bez přípony (přípona se doplní automaticky)</small>
            </div>

            <div class="modal-actions">
                <button type="button" class="btn btn-secondary" onclick="closeEditEdoSubfolderModal()">Zrušit</button>
                <button type="submit" class="btn btn-primary">Uložit změny</button>
            </div>
        </form>
    </div>
</div>

<div id="edoVideoPreviewModal" class="modal" style="display: none;">
    <div class="modal-content video-modal">
        <div class="modal-header">
            <h2 id="edoVideoModalTitle">Přehrát média</h2>
            <button class="modal-close" onclick="closeEdoVideoModal()">&times;</button>
        </div>
        <div class="video-modal-body">
            <?php if (isAdmin()): ?>
                <video id="edoModalVideo" controls style="display: none;" class="modal-preview-video">
                    <source id="edoModalVideoSource" src="" type="">
                    Váš prohlížeč nepodporuje video tag.
                </video>
                <audio id="edoModalAudio" controls style="display: none;" class="modal-preview-audio">
                    <source id="edoModalAudioSource" src="" type="">
                    Váš prohlížeč nepodporuje audio tag.
                </audio>
            <?php else: ?>
                <video id="edoModalVideo" controls controlsList="nodownload" style="display: none;" class="modal-preview-video" oncontextmenu="return false;">
                    <source id="edoModalVideoSource" src="" type="">
                    Váš prohlížeč nepodporuje video tag.
                    </source>
                    <audio id="edoModalAudio" controls controlsList="nodownload" style="display: none;" class="modal-preview-audio" oncontextmenu="return false;">
                        <source id="edoModalAudioSource" src="" type="">
                        Váš prohlížeč nepodporuje audio tag.
                    </audio>
                <?php endif; ?>
        </div>
    </div>
</div>

<div id="editEdoFileModal" class="modal" style="display: none;">
    <div class="modal-content">
        <div class="modal-header">
            <h2>Přejmenovat EDO soubor</h2>
            <button class="modal-close" onclick="closeEditEdoFileModal()">&times;</button>
        </div>
        <form id="editEdoFileForm">
            <input type="hidden" id="editEdoFileCategory" name="category">
            <input type="hidden" id="editEdoFileSubfolder" name="subfolder">
            <input type="hidden" id="editEdoFileOriginalName" name="original_name">

            <div class="form-group">
                <label for="editEdoFileName">Název souboru *</label>
                <input type="text" id="editEdoFileName" name="new_name" required
                    placeholder="Nový název souboru (bez přípony)" class="full-width">
                <small class="file-help">Zadejte pouze název bez přípony - přípona zůstane zachována</small>
            </div>

            <div class="modal-actions">
                <button type="button" class="btn btn-secondary" onclick="closeEditEdoFileModal()">Zrušit</button>
                <button type="submit" class="btn btn-primary">Přejmenovat</button>
            </div>
        </form>
    </div>
</div>

<div id="editEdoCategoryModal" class="modal" style="display: none;">
    <div class="modal-content">
        <div class="modal-header">
            <h2>Upravit kategorii EDO</h2>
            <button class="modal-close" onclick="closeEditEdoCategoryModal()">&times;</button>
        </div>
        <form id="editEdoCategoryForm" enctype="multipart/form-data">
            <input type="hidden" id="editEdoCategoryOriginalName" name="original_name">

            <div class="form-group">
                <label for="editEdoCategoryName">Název kategorie *</label>
                <input type="text" id="editEdoCategoryName" name="new_name" required
                    placeholder="Nový název kategorie" class="full-width">
            </div>

            <div class="form-group">
                <label for="editEdoCategoryFile">Nahrát soubor do kategorie (volitelné)</label>
                <div class="upload-area">
                    <label for="editEdoCategoryFile" class="custom-file-button">Vyberte soubor</label>
                    <input type="file" name="category_file" id="editEdoCategoryFile" accept=".mp3,.wav,.ogg,.m4a,.aac,.mp4,.avi,.mov,.wmv,.flv,.webm,.mkv,.pdf,.txt,.doc,.docx,.rtf" class="hidden-file-input">
                    <div id="edit-edo-category-selected-file" class="selected-files-info"></div>
                </div>
                <small class="file-help">Podporované formáty: Audio, Video, PDF, Text (max 500MB)</small>
            </div>

            <div class="form-group">
                <label for="editEdoCategoryCustomFilename">Přejmenovat soubor (volitelné)</label>
                <input type="text" id="editEdoCategoryCustomFilename" name="custom_filename" class="full-width" placeholder="Ponechte prázdné pro původní název">
                <small class="file-help">Zadejte pouze název bez přípony (přípona se doplní automaticky)</small>
            </div>

            <div class="modal-actions">
                <button type="button" class="btn btn-secondary" onclick="closeEditEdoCategoryModal()">Zrušit</button>
                <button type="submit" class="btn btn-primary">Uložit změny</button>
            </div>
        </form>
    </div>
</div>

<script>
    // "Safety Bubble" - wraps code so it doesn't crash with other files
    (function() {
        // Local variables that won't conflict with global scope
        var edoSubfolderData = {};
        var draggedEdoElement = null;
        var draggedEdoFileData = null;

        // Check for File System Access API support
        var supportsEdoFileSystemAccess = 'getAsFileSystemHandle' in DataTransferItem.prototype;

        // --- DATA LOADING ---
        function loadEdoSubfolderData() {
            fetch('get-subfolder-form-handler.php?type=edo')
                .then(response => response.json())
                .then(data => {
                    edoSubfolderData = data || {};
                    updateEdoSubfolderOptions();
                })
                .catch(error => console.log('No EDO subfolder data available yet:', error.message));
        }

        function updateEdoSubfolderOptions() {
            const mainFolder = document.getElementById('edo-category');
            const subfolderSelect = document.getElementById('edo-subfolder');
            if (!mainFolder || !subfolderSelect) return;

            subfolderSelect.innerHTML = '<option value="">-- Hlavní složka --</option>';

            if (edoSubfolderData[mainFolder.value]) {
                edoSubfolderData[mainFolder.value].forEach(subfolder => {
                    const option = document.createElement('option');
                    option.value = subfolder;
                    option.textContent = subfolder;
                    subfolderSelect.appendChild(option);
                });
            }
        }

        // --- TOGGLES ---
        function toggleEdoCategoryHeader(categoryKey) {
            if (!document.getElementById('edo')) return;
            const categoryCard = document.querySelector(`#edo .edo-category[data-category="${categoryKey}"]`);
            if (!categoryCard) return;

            if (categoryCard.classList.contains('expanded')) {
                categoryCard.classList.remove('expanded');
            } else {
                categoryCard.classList.add('expanded');
            }
        }

        function toggleEdoSubfolder(subfolderId) {
            if (window.event) {
                window.event.stopPropagation();
                window.event.preventDefault();
            }

            // Try multiple ways to find the card to be safe
            let subfolderCard = document.querySelector(`[onclick="toggleEdoSubfolder('${subfolderId}')"]`);

            // Fallback for ID mismatch
            if (!subfolderCard) {
                // Try to find the card that contains the matching data attributes
                const parts = subfolderId.split('-');
                if (parts.length >= 2) {
                    // heuristic search
                    const cat = parts[0];
                    const sub = parts.slice(1).join('-'); // handle names with dashes
                    subfolderCard = document.querySelector(`.edo-subfolder-card[data-category="${cat}"][data-subfolder="${sub}"]`);
                }
            }

            if (!subfolderCard) {
                console.warn('Could not find EDO subfolder card for:', subfolderId);
                return;
            }

            const content = subfolderCard.querySelector('.subfolder-content');

            if (subfolderCard.classList.contains('open')) {
                subfolderCard.classList.remove('open');
                if (content) content.style.maxHeight = '';
            } else {
                subfolderCard.classList.add('open');
                if (content) {
                    setTimeout(() => {
                        const scrollHeight = content.scrollHeight;
                        content.style.maxHeight = Math.max(scrollHeight, 500) + 'px';
                    }, 50);
                }
            }
        }

        // --- MODALS (ADD/EDIT SUBFOLDER) ---
        function showAddEdoSubfolderModal(categoryKey) {
            if (window.event) {
                window.event.stopPropagation();
                window.event.preventDefault();
            }
            document.getElementById('edoParentCategory').value = categoryKey;
            document.getElementById('addEdoSubfolderModal').style.display = 'flex';
            document.getElementById('addEdoSubfolderForm').reset();
            setTimeout(() => {
                const el = document.getElementById('edoSubfolderName');
                if (el) el.focus();
            }, 100);
        }

        function closeAddEdoSubfolderModal() {
            document.getElementById('addEdoSubfolderModal').style.display = 'none';
            document.getElementById('addEdoSubfolderForm').reset();
        }

        function editEdoSubfolder(categoryKey, subfolderName) {
            if (window.event) {
                window.event.stopPropagation();
                window.event.preventDefault();
            }
            document.getElementById('editEdoSubfolderParentCategory').value = categoryKey;
            document.getElementById('editEdoSubfolderOriginalName').value = subfolderName;
            document.getElementById('editEdoSubfolderName').value = subfolderName;
            document.getElementById('editEdoSubfolderModal').style.display = 'flex';
            setTimeout(() => document.getElementById('editEdoSubfolderName').focus(), 100);
        }

        function closeEditEdoSubfolderModal() {
            document.getElementById('editEdoSubfolderModal').style.display = 'none';
            document.getElementById('editEdoSubfolderForm').reset();
            const info = document.getElementById('edit-edo-subfolder-selected-file');
            if (info) info.textContent = '';
            document.getElementById('editEdoSubfolderCustomFilename').value = '';
        }

        function deleteEdoSubfolder(categoryKey, subfolderName) {
            if (window.event) {
                window.event.stopPropagation();
                window.event.preventDefault();
            }

            if (confirm(`Opravdu chcete smazat EDO podsložku "${subfolderName}"?\n\nTato akce smaže podsložku a všechny soubory v ní. Toto nelze vrátit zpět!`)) {
                const formData = new FormData();
                // Parametry pro univerzální handler
                formData.append('type', 'edo');
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
                                // Pojistka, pokud by selektor selhal
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

        // --- FILES (EDIT/DELETE) ---
        function deleteEdoFile(fileName, category, subfolder) {
            // Pro jistotu zajistíme, aby subfolder nebyl undefined, pokud ho PHP nepošle
            const cleanSubfolder = subfolder || '';

            if (confirm(`Opravdu chcete smazat soubor "${fileName}"?\n\nTato akce je nevratná!`)) {
                const formData = new FormData();
                // Identifikace pro náš univerzální handler
                formData.append('type', 'edo');
                formData.append('category', category);
                formData.append('subfolder', cleanSubfolder);
                formData.append('file_name', fileName);

                fetch('delete-file-handler.php', {
                    method: 'POST',
                    body: formData,
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                })
                    .then(r => r.json())
                    .then(data => {
                        if (data.success) {
                            showStatusMessage('success', data.message);

                            // --- BLESKOVÉ ODSTRANĚNÍ Z DOMU ---
                            // Selektor, který najde přesný soubor v konkrétní kategorii a podsložce
                            const selector = `[data-file-name="${fileName}"][data-current-category="${category}"][data-current-subfolder="${cleanSubfolder}"]`;
                            const fileElement = document.querySelector(selector);

                            if (fileElement) {
                                // Přidáme animaci: prvek zčervená a zmizí dolů
                                fileElement.style.transition = 'all 0.4s cubic-bezier(0.4, 0, 0.2, 1)';
                                fileElement.style.background = 'rgba(220, 53, 69, 0.1)';
                                fileElement.style.opacity = '0';
                                fileElement.style.transform = 'scale(0.9) translateY(20px)';

                                setTimeout(() => {
                                    fileElement.remove();

                                    // Pokud chceš po smazání aktualizovat i ty malé badge s počty,
                                    // musel bys zavolat tu logiku, co jsme řešili u smluv.
                                }, 400);
                            } else {
                                // Pokud JS prvek nenajde, refresh je jistota
                                setTimeout(() => location.reload(), 500);
                            }
                        } else {
                            showStatusMessage('error', data.message);
                        }
                    })
                    .catch(e => {
                        console.error('Chyba při mazání:', e);
                        showStatusMessage('error', 'Chyba sítě nebo serveru.');
                    });
            }
        }

        function editEdoFile(originalName, category, subfolder, currentName) {
            document.getElementById('editEdoFileCategory').value = category;
            document.getElementById('editEdoFileSubfolder').value = subfolder || '';
            document.getElementById('editEdoFileOriginalName').value = originalName;

            const nameWithoutExt = currentName.replace(/\.[^/.]+$/, "");
            document.getElementById('editEdoFileName').value = nameWithoutExt;

            document.getElementById('editEdoFileModal').style.display = 'flex';
            setTimeout(() => document.getElementById('editEdoFileName').focus(), 100);
        }

        function closeEditEdoFileModal() {
            document.getElementById('editEdoFileModal').style.display = 'none';
            document.getElementById('editEdoFileForm').reset();
        }

        // --- CATEGORIES (EDIT/DELETE) ---
        function editEdoCategory(categoryKey) {
            if (window.event) {
                window.event.stopPropagation();
                window.event.preventDefault();
            }
            const modal = document.getElementById('editEdoCategoryModal');
            const originalInput = document.getElementById('editEdoCategoryOriginalName');
            const nameInput = document.getElementById('editEdoCategoryName');

            if (originalInput) originalInput.value = categoryKey;
            if (nameInput) nameInput.value = categoryKey;
            if (modal) {
                modal.style.display = 'flex';
                setTimeout(() => nameInput.focus(), 100);
            }
        }

        function closeEditEdoCategoryModal() {
            const modal = document.getElementById('editEdoCategoryModal');
            if (modal) modal.style.display = 'none';
            const form = document.getElementById('editEdoCategoryForm');
            if (form) form.reset();
            const info = document.getElementById('edit-edo-category-selected-file');
            if (info) info.textContent = '';
        }

        function deleteEdoCategory(categoryKey) {
            if (window.event) {
                window.event.stopPropagation();
                window.event.preventDefault();
            }
            if (!confirm(`Smazat kategorie "${categoryKey}" a všechny soubory?`)) return;

            const formData = new FormData();
            formData.append('category', categoryKey);
            formData.append('type', 'edo');

            fetch('delete-category-handler.php', {
                    method: 'POST',
                    body: formData
                })
                .then(r => r.text())
                .then(text => {
                    try {
                        const data = JSON.parse(text);
                        if (data && data.success) {
                            showStatusMessage('success', data.message);
                            setTimeout(() => location.reload(), 500);
                        } else {
                            showStatusMessage('error', data.message || 'Error');
                        }
                    } catch (e) {
                        showStatusMessage('error', 'Server Error');
                    }
                })
                .catch(e => showStatusMessage('error', 'Chyba sítě'));
        }

        function showStatusMessage(type, message) {
            const container = document.getElementById('status-messages');
            if (!container) return;
            const div = document.createElement('div');
            div.className = `status-message status-${type}`;
            div.innerHTML = `<i class="fas ${type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle'}"></i> ${message}`;
            container.appendChild(div);
            setTimeout(() => div.remove(), 500);
        }

        function toggleEdoFolderInputs() {
            // Logic kept from original if needed, currently empty
        }

        // --- DRAG AND DROP LOGIC (FIXED SCOPE) ---
        function handleEdoDragStart(event) {
            draggedEdoElement = event.target;
            draggedEdoElement.classList.add('dragging');

            draggedEdoFileData = {
                fileName: event.target.getAttribute('data-file-name'),
                currentCategory: event.target.getAttribute('data-current-category'),
                currentSubfolder: event.target.getAttribute('data-current-subfolder') || ''
            };

            event.dataTransfer.effectAllowed = 'move';
            event.dataTransfer.setData('text/plain', JSON.stringify(draggedEdoFileData));
        }

        function handleEdoDragEnd(event) {
            if (draggedEdoElement) draggedEdoElement.classList.remove('dragging');
            document.querySelectorAll('.droppable-zone').forEach(zone => zone.classList.remove('drag-over'));
            draggedEdoElement = null;
            draggedEdoFileData = null;
        }

        function handleEdoDragOver(event) {
            event.preventDefault();
            event.dataTransfer.dropEffect = 'move';
        }

        function handleEdoDragEnter(event) {
            event.preventDefault();
            event.stopPropagation();
            if (!draggedEdoFileData) return;

            const dropZone = event.currentTarget;
            const targetCategory = dropZone.getAttribute('data-category');
            const targetSubfolder = dropZone.getAttribute('data-subfolder') || '';

            if (targetCategory === draggedEdoFileData.currentCategory &&
                targetSubfolder === draggedEdoFileData.currentSubfolder) {
                return;
            }
            dropZone.classList.add('drag-over');
        }

        function handleEdoDragLeave(event) {
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

        async function handleEdoDrop(event) {
            event.preventDefault();
            event.stopPropagation();

            const dropZone = event.currentTarget;
            const targetCategory = dropZone.getAttribute('data-category');
            const targetSubfolder = dropZone.getAttribute('data-subfolder') || '';
            dropZone.classList.remove('drag-over');

            let fileData = draggedEdoFileData;

            if (!fileData) {
                try {
                    const transferData = event.dataTransfer.getData('text/plain');
                    if (transferData) fileData = JSON.parse(transferData);
                } catch (error) {
                    console.error("Could not parse transfer data:", error);
                }
            }

            if (!fileData) {
                showStatusMessage('error', 'Nepodařilo se získat informace o souboru');
                return;
            }

            if (targetCategory === fileData.currentCategory && targetSubfolder === fileData.currentSubfolder) {
                return;
            }

            await moveEdoFile(fileData, targetCategory, targetSubfolder);
        }

        async function moveEdoFile(fileData, targetCategory, targetSubfolder) {
            showStatusMessage('info', 'Přesouvám soubor...');

            const formData = new FormData();
            formData.append('fileName', fileData.fileName);
            formData.append('sourceCategory', fileData.currentCategory);
            formData.append('sourceSubfolder', fileData.currentSubfolder);
            formData.append('targetCategory', targetCategory);
            formData.append('targetSubfolder', targetSubfolder);
            formData.append('move_edo_file', '1');

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

        // --- FORM SUBMISSION HANDLERS ---
        function initializeEdoFormHandlers() {
            // Add Subfolder
            const edoSubfolderForm = document.getElementById('addEdoSubfolderForm');
            if (edoSubfolderForm) {
                edoSubfolderForm.addEventListener('submit', function(e) {
                    e.preventDefault();
                    const formData = new FormData(this);
                    fetch('add-edo-subfolder.php', {
                            method: 'POST',
                            body: formData
                        })
                        .then(r => r.text()).then(text => {
                            try {
                                const data = JSON.parse(text);
                                if (data.success) {
                                    closeAddEdoSubfolderModal();
                                    showStatusMessage('success', data.message);
                                    setTimeout(() => location.reload(), 500);
                                } else {
                                    showStatusMessage('error', data.message);
                                }
                            } catch (e) {
                                showStatusMessage('error', 'Server error');
                            }
                        });
                });
            }

            // Edit Subfolder
            const editEdoSubfolderForm = document.getElementById('editEdoSubfolderForm');
            if (editEdoSubfolderForm) {
                editEdoSubfolderForm.addEventListener('submit', function(e) {
                    e.preventDefault();

                    const formData = new FormData(this);
                    // 1. Řekneme handleru, že jsme v sekci EDO
                    formData.append('type', 'edo');

                    const btn = this.querySelector('button[type="submit"]');
                    const originalText = btn.innerHTML;
                    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Ukládá se...';
                    btn.disabled = true;

                    // 2. Pálíme na univerzální handler se správnou hlavičkou
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
                                if (typeof closeEditEdoSubfolderModal === 'function') closeEditEdoSubfolderModal();
                                showStatusMessage('success', data.message);
                                setTimeout(() => location.reload(), 500);
                            } else {
                                showStatusMessage('error', data.message);
                            }
                        })
                        .catch(err => {
                            console.error('Error:', err);
                            showStatusMessage('error', 'Chyba při komunikaci se serverem.');
                        })
                        .finally(() => {
                            btn.innerHTML = originalText;
                            btn.disabled = false;
                        });
                });
            }

            // Edit File
            const editEdoFileForm = document.getElementById('editEdoFileForm');
            if (editEdoFileForm) {
                editEdoFileForm.addEventListener('submit', function(e) {
                    e.preventDefault();

                    const formData = new FormData(this);

                    // PŘIDÁNO: Aby univerzální handler věděl, že upravuje soubor v /edo
                    formData.append('type', 'edo');

                    const btn = this.querySelector('button[type="submit"]');
                    const originalText = btn.innerHTML;
                    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Ukládá se...';
                    btn.disabled = true;

                    // ZMĚNĚNO: Míříme na univerzální souborový handler (edit-file-handler.php)
                    fetch('edit-file-handler.php', {
                        method: 'POST',
                        body: formData
                    })
                        .then(r => r.json())
                        .then(data => {
                            if (data.success) {
                                if (typeof closeEditEdoFileModal === 'function') closeEditEdoFileModal();
                                if (typeof showStatusMessage === 'function') {
                                    showStatusMessage('success', data.message);
                                }
                                setTimeout(() => location.reload(), 500);
                            } else {
                                if (typeof showStatusMessage === 'function') {
                                    showStatusMessage('error', data.message);
                                } else {
                                    alert(data.message);
                                }
                            }
                        })
                        .catch(err => {
                            console.error('Error:', err);
                            if (typeof showStatusMessage === 'function') {
                                showStatusMessage('error', 'Chyba při komunikaci se serverem.');
                            }
                        })
                        .finally(() => {
                            btn.innerHTML = originalText;
                            btn.disabled = false;
                        });
                });
            }

            // Edit Category
            const editEdoCategoryForm = document.getElementById('editEdoCategoryForm');
            if (editEdoCategoryForm) {
                editEdoCategoryForm.addEventListener('submit', function(e) {
                    e.preventDefault();
                    const formData = new FormData(this);

                    // PŘIDÁNO: Identifikátor pro univerzální handler
                    formData.append('type', 'edo');

                    const btn = this.querySelector('button[type="submit"]');
                    const originalText = btn.innerHTML;
                    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Ukládá se...';
                    btn.disabled = true;

                    // ZMĚNĚNO: Teď to pálí na ten univerzální PHP skript
                    fetch('edit-category-handler.php', {
                        method: 'POST',
                        body: formData
                    }).then(r => r.text()).then(text => {
                        try {
                            const data = JSON.parse(text);
                            if (data.success) {
                                closeEditEdoCategoryModal();
                                showStatusMessage('success', data.message);
                                setTimeout(() => location.reload(), 500);
                            } else {
                                showStatusMessage('error', data.message);
                            }
                        } catch (e) {
                            showStatusMessage('error', 'Server error');
                            console.error('Chyba při parsování JSONu:', text);
                        }
                    }).finally(() => {
                        btn.innerHTML = originalText;
                        btn.disabled = false;
                    });
                });
            }
        }

        // --- INITIALIZATION ---
        document.addEventListener('DOMContentLoaded', function() {
            if (!document.getElementById('edo')) return;

            loadEdoSubfolderData();
            initializeEdoFormHandlers();

            // Status messages
            const urlParams = new URLSearchParams(window.location.search);
            if (urlParams.get('status')) {
                showStatusMessage(urlParams.get('status'), decodeURIComponent(urlParams.get('message')));
                window.history.replaceState({}, document.title, window.location.pathname + '#edo');
            }

            // File Inputs Visual Feedback
            const inputs = [{
                    id: 'edo_file',
                    disp: 'selected-file'
                },
                {
                    id: 'editEdoSubfolderFile',
                    disp: 'edit-edo-subfolder-selected-file'
                },
                {
                    id: 'editEdoCategoryFile',
                    disp: 'edit-edo-category-selected-file'
                }
            ];

            inputs.forEach(item => {
                const input = document.getElementById(item.id);
                const display = document.getElementById(item.disp);
                if (input && display) {
                    input.addEventListener('change', function() {
                        if (this.files && this.files.length > 0) {
                            display.textContent = `Vybraný soubor: ${this.files[0].name}`;
                        } else {
                            display.textContent = '';
                        }
                    });
                }
            });
        });

        // Close modals on Escape
        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape') {
                closeEditEdoFileModal();
            }
        });

        // --- EXPORT FUNCTIONS TO HTML ---
        window.toggleEdoCategoryHeader = toggleEdoCategoryHeader;
        window.toggleEdoSubfolder = toggleEdoSubfolder;
        window.showAddEdoSubfolderModal = showAddEdoSubfolderModal;
        window.closeAddEdoSubfolderModal = closeAddEdoSubfolderModal;
        window.editEdoSubfolder = editEdoSubfolder;
        window.closeEditEdoSubfolderModal = closeEditEdoSubfolderModal;
        window.deleteEdoSubfolder = deleteEdoSubfolder;
        window.deleteEdoFile = deleteEdoFile;
        window.editEdoFile = editEdoFile;
        window.closeEditEdoFileModal = closeEditEdoFileModal;
        window.editEdoCategory = editEdoCategory;
        window.closeEditEdoCategoryModal = closeEditEdoCategoryModal;
        window.deleteEdoCategory = deleteEdoCategory;
        window.handleEdoDrop = handleEdoDrop;
        window.handleEdoDragOver = handleEdoDragOver;
        window.handleEdoDragEnter = handleEdoDragEnter;
        window.handleEdoDragLeave = handleEdoDragLeave;
        window.handleEdoDragStart = handleEdoDragStart;
        window.handleEdoDragEnd = handleEdoDragEnd;
        window.updateEdoSubfolderOptions = updateEdoSubfolderOptions;
        window.toggleEdoFolderInputs = toggleEdoFolderInputs;

    })(); // End Safety Bubble
</script>