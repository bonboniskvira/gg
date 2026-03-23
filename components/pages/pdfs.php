<?php
// BUFFER OUTPUT IMMEDIATELY TO FIX "HEADERS ALREADY SENT"
ob_start();

// Set proper encoding for Czech characters  
mb_internal_encoding('UTF-8');

require_once 'access.php';

// Handle file move request
if (isset($_POST['move_pdf_file']) && isAdmin()) {
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
        $pdfBaseDir = getRoot('pdf');
        $sourcePath = $pdfBaseDir . $sourceCategory . '/' . ($sourceSubfolder ? $sourceSubfolder . '/' : '');
        $targetPath = $pdfBaseDir . $targetCategory . '/' . ($targetSubfolder ? $targetSubfolder . '/' : '');
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

// Get existing categories - scan actual directories
function getPdfCategories()
{
    $pdfDir = getRoot('pdf');
    $categories = [];

    if (is_dir($pdfDir)) {
        $items = array_diff(scandir($pdfDir), ['.', '..']);

        foreach ($items as $item) {
            $itemPath = $pdfDir . $item;
            if (is_dir($itemPath)) {
                // Assign different colors to different categories
                $colors = ['#e74c3c', '#3498db', '#2ecc71', '#f39c12', '#9b59b6', '#1abc9c'];
                $colorIndex = crc32($item) % count($colors);

                $categories[$item] = [
                    'name' => str_replace('_', ' ', ucfirst($item)), // Convert underscores to spaces and capitalize
                    'color' => $colors[$colorIndex]
                ];
            }
        }
    }
    
    // If no categories exist, create a default one to allow uploads
    if (empty($categories)) {
        $defaultCategoryDir = $pdfDir . 'general';
        if (!is_dir($defaultCategoryDir)) {
            mkdir($defaultCategoryDir, 0777, true);
        }
        $categories['general'] = ['name' => 'Obecné', 'color' => '#3498db'];
    }

    return $categories;
}

// Get dynamic categories
$pdfCategories = getPdfCategories();

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

// Function to scan for subfolders and PDF files
function scanPdfRecursive($dir, $categoryKey, $categoryInfo, $depth = 0)
{
    $result = ['folders' => [], 'documents' => []];

    if (!is_dir($dir)) return $result;

    $items = array_diff(scandir($dir), ['.', '..']);

    foreach ($items as $item) {
        $itemPath = $dir . $item;

        if (is_dir($itemPath)) {
            // It's a subfolder
            $subResult = scanPdfRecursive($itemPath . '/', $categoryKey, $categoryInfo, $depth + 1);
            $result['folders'][$item] = [
                'name' => $item,
                'path' => $itemPath,
                'data' => $subResult,
                'depth' => $depth
            ];
        } else {
            // It's a file - add it directly
            $extension = strtolower(pathinfo($item, PATHINFO_EXTENSION));

            if ($extension === 'pdf') {
                $fileData = [
                    'name' => pathinfo($item, PATHINFO_FILENAME),
                    'original_name' => $item,
                    'file_path' => str_replace($_SERVER['DOCUMENT_ROOT'], '', $itemPath),
                    'file_size' => filesize($itemPath),
                    'file_extension' => $extension,
                    'date_added' => filemtime($itemPath),
                    'category_info' => $categoryInfo,
                    'id' => pathinfo($item, PATHINFO_FILENAME)
                ];

                $result['documents'][] = $fileData;
            }
        }
    }

    return $result;
}

// Function to count all documents recursively (including subfolders)
function countAllPdfDocuments($categoryData)
{
    $count = count($categoryData['documents']); // Count files in main folder

    // Add files from all subfolders recursively
    if (!empty($categoryData['folders'])) {
        foreach ($categoryData['folders'] as $subfolder) {
            $count += countAllPdfDocuments($subfolder['data']);
        }
    }

    return $count;
}
?>

<style>
    #pdfs .content-area {
        display: flex;
        flex-direction: column;
        gap: 30px;
    }

    .add-pdf-section {
        background: white;
        border-radius: 10px;
        padding: 25px;
        margin-bottom: 25px;
        box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
    }

    .add-pdf-section h2 {
        color: #2c3e50;
        margin-top: 0;
        margin-bottom: 20px;
        font-size: 20px;
    }
    .pdf-grid-container {
        height: auto !important;
        min-height: 0 !important;
        flex-grow: 0; /* Zabrání natahování ve flexu */
        align-self: flex-start; /* Zarovná se na začátek a nebude se natahovat na celou výšku */
        width: 100%;
    }

    .pdf-form {
        display: flex;
        flex-direction: column;
        gap: 15px;
    }

    .pdf-grid {
        display: flex;
        flex-direction: column;
        gap: 20px;
    }

    /* Category styling matching edo.php */
    .pdf-category {
        background: white;
        border-radius: 10px;
        box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        overflow: hidden;
        transition: all 0.3s ease;
    }

    .pdf-category.expanded {
        grid-column: 1 / -1;
        max-width: none;
        transform: none !important;
        z-index: 10;
    }

    .pdf-category.expanded .category-content {
        max-height: none !important;
        padding: 20px !important;
        display: block !important;
        opacity: 1 !important;
        overflow: visible !important;
    }

    .pdf-category.expanded .category-toggle i {
        transform: rotate(180deg);
    }

    .pdf-category:hover:not(.expanded) {
        box-shadow: 0 5px 15px rgba(0, 0, 0, 0.15);
    }

    .pdf-category:not(.expanded) .category-content {
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

    .pdf-count {
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

    .no-pdf {
        text-align: center;
        padding: 60px 20px;
        color: #6c757d;
        background: white;
        border-radius: 10px;
        box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
    }

    /* Category actions styling */
    .category-actions {
        margin-bottom: 20px;
        display: flex;
        gap: 10px;
    }

    .add-pdf-subfolder-btn {
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

    .add-pdf-subfolder-btn:hover {
        background: #138496;
    }

    /* Subfolder styling */
    .pdf-subfolders-section {
        margin-bottom: 25px;
    }

    .pdf-subfolders-section h4 {
        color: #495057;
        font-size: 14px;
        font-weight: 600;
        margin: 0 0 12px 0;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    .pdf-subfolders-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
        gap: 12px;
    }

    .pdf-subfolder-card {
        background: #f8f9fa;
        border: 1px solid #e9ecef;
        border-radius: 8px;
        padding: 15px;
        cursor: pointer;
        transition: all 0.2s;
    }

    .pdf-subfolder-card:hover {
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
    .pdf-subfolder-card.open {
        grid-column: 1 / -1;
        width: 100%;
        max-width: none;
        background: #fff;
        border-color: #dee2e6;
    }

    .pdf-subfolder-card.open .subfolder-content {
        max-height: 1000px;
        margin-top: 12px;
        padding-top: 12px;
        border-top: 1px solid #dee2e6;
    }

    .pdf-subfolder-card.open .subfolder-toggle {
        transform: rotate(90deg);
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
        color: #e74c3c;
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
    .pdf-documents-section {
        margin-top: 0 !important;
        padding-top: 0 !important;
    }

    .pdf-documents-section h4 {
        color: #495057;
        font-size: 14px;
        font-weight: 600;
        margin: 0 0 12px 0;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    .pdf-documents-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
        gap: 15px;
    }

    .pdf-card-grid {
        background: white;
        border: 1px solid #e9ecef;
        border-radius: 8px;
        overflow: hidden;
        transition: all 0.2s;
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
        opacity: 1 !important;
        display: block !important;
    }

    .pdf-card-grid:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
        border-color: #dee2e6;
    }

    .pdf-preview {
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
        color: #e74c3c;
    }

    .pdf-info-grid {
        padding: 15px;
        display: flex;
        flex-direction: column;
        gap: 8px;
    }

    .pdf-title {
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

    .pdf-meta-grid {
        display: flex;
        justify-content: space-between;
        align-items: center;
        font-size: 11px;
        color: #6c757d;
        margin-top: auto;
        flex-wrap: wrap;
        gap: 8px;
    }

    .pdf-date,
    .pdf-size {
        display: flex;
        align-items: center;
        gap: 4px;
    }

    .pdf-actions-grid {
        display: flex;
        gap: 6px;
        justify-content: flex-end;
        margin-top: 8px;
        padding-top: 8px;
        border-top: 1px solid #f0f0f0;
    }

    .grid-btn {
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
    }

    .grid-btn:hover {
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

    .mini-btn {
        padding: 4px 6px;
        border: 1px solid #dee2e6;
        border-radius: 3px;
        background: #f8f9fa;
        color: #6c757d;
        font-size: 10px;
        cursor: pointer;
        transition: all 0.2s;
    }

    .mini-btn:hover {
        background: #e9ecef;
    }

    .edit-btn:hover {
        color: #007bff !important;
        border-color: #007bff !important;
    }

    .admin-btn {
        background: none;
        border: 1px solid #dee2e6;
        border-radius: 4px;
        width: 28px;
        height: 28px;
        display: flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        transition: all 0.2s;
        font-size: 12px;
        color: #6c757d;
    }

    .admin-btn:hover {
        background: #f8f9fa;
        border-color: #adb5bd;
    }

    /* Modal styles for PDF file editing */
    .modal {
        display: none;
        position: fixed;
        z-index: 1000;
        left: 0;
        top: 0;
        width: 100%;
        height: 100%;
        background: rgba(0, 0, 0, 0.8);
        justify-content: center;
        align-items: center;
    }

    .modal-content {
        background: #fff;
        border-radius: 8px;
        padding: 20px;
        max-width: 800px;
        width: 90%;
        box-shadow: 0 4px 20px rgba(0, 0, 0, 0.2);
        position: relative;
    }

    .modal-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 15px;
    }

    .modal-header h2 {
        margin: 0;
        font-size: 18px;
        color: #333;
    }

    .modal-close {
        position: absolute;
        top: 10px;
        right: 10px;
        background: transparent;
        border: none;
        cursor: pointer;
        font-size: 18px;
        color: #999;
    }

    .modal-close:hover {
        color: #333;
    }

    .form-group {
        margin-bottom: 15px;
    }

    .form-group label {
        display: block;
        margin-bottom: 8px;
        font-weight: 600;
        color: #2c3e50;
    }

    .form-group input,
    .form-group select {
        width: 100%;
        padding: 10px;
        border: 1px solid #ddd;
        border-radius: 5px;
        font-size: 14px;
        box-sizing: border-box;
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
        margin-top: 20px;
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

    /* Drag and Drop Styles */
    .pdf-card-grid.dragging,
    .subfolder-document.dragging {
        opacity: 0.5;
        transform: scale(0.95);
        cursor: grabbing;
    }

    .pdf-subfolder-card.drag-over {
        background: #e3f2fd !important;
        border-color: #2196f3 !important;
        box-shadow: 0 0 15px rgba(33, 150, 243, 0.3) !important;
    }

    .pdf-category.drag-over {
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

    .pdf-subfolder-card.drag-over .drop-zone-indicator,
    .pdf-category.drag-over .drop-zone-indicator {
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

    .pdf-subfolder-card,
    .pdf-category {
        position: relative;
    }

    .pdf-preview {
        position: relative;
    }
</style>

<div class="page-content" id="pdfs">
    <div class="content-area">
        <!-- Status Messages -->
        <div id="status-messages"></div>

        <!-- PDF Grid Container -->
        <div class="pdf-grid-container">
            <?php
            $pdfDir = getRoot('pdf');
            $pdfByCategory = [];

            // Scan for PDF files and subfolders
            if (is_dir($pdfDir)) {
                foreach ($pdfCategories as $categoryKey => $categoryInfo) {
                    $categoryDir = $pdfDir . $categoryKey . '/';
                    if (is_dir($categoryDir)) {
                        $categoryData = scanPdfRecursive($categoryDir, $categoryKey, $categoryInfo);
                        if (!empty($categoryData['documents']) || !empty($categoryData['folders'])) {
                            $pdfByCategory[$categoryKey] = [
                                'info' => $categoryInfo,
                                'data' => $categoryData
                            ];
                        }
                    }
                }
            }

            if (empty($pdfByCategory)): ?>
                <div class="no-pdf">
                    <i class="fas fa-file-pdf"></i>
                    <p>Zatím nejsou žádné PDF soubory v systému.</p>
                </div>
            <?php else: ?>
                <div class="categories-grid">
                    <?php foreach ($pdfByCategory as $categoryKey => $category): ?>
                        <div class="pdf-category droppable-zone"
                            data-category="<?= $categoryKey ?>"
                            style="border-top: 4px solid <?= $category['info']['color'] ?>"
                            ondrop="handlePdfDrop(event)"
                            ondragover="handlePdfDragOver(event)"
                            ondragenter="handlePdfDragEnter(event)"
                            ondragleave="handlePdfDragLeave(event)">

                            <div class="drop-zone-indicator">
                                <i class="fas fa-file-import"></i> &nbsp; Přetáhněte soubor do hlavní složky
                            </div>

                            <div class="category-header category-header-clickable" onclick="togglePdfCategoryHeader('<?= $categoryKey ?>')">
                                <div class="category-info">
                                    <h3 style="color: <?= $category['info']['color'] ?>">
                                        <i class="fas fa-folder"></i>
                                        <?= htmlspecialchars($category['info']['name']) ?>
                                    </h3>
                                    <span class="pdf-count">
                                        <?= countAllPdfDocuments($category['data']) ?> souborů
                                        <?php if (!empty($category['data']['folders'])): ?>
                                            • <?= count($category['data']['folders']) ?> podsložek
                                        <?php endif; ?>
                                    </span>
                                </div>

                                <?php if (isAdmin()): ?>
                                    <div class="category-admin-actions" onclick="event.stopPropagation()">
                                        <button class="admin-btn edit-btn" onclick="showRenamePdfCategoryModal('<?= $categoryKey ?>', '<?= htmlspecialchars($category['info']['name']) ?>')" title="Upravit kategorii">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <button class="admin-btn delete-btn" onclick="deletePdfCategory('<?= $categoryKey ?>', '<?= htmlspecialchars($category['info']['name']) ?>')" title="Smazat kategorii">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </div>
                                <?php endif; ?>

                                <div class="category-toggle">
                                    <i class="fas fa-chevron-down"></i>
                                </div>
                            </div>

                            <div class="category-content" id="pdf-category-content-<?= $categoryKey ?>">
                                <?php if (canUpload()): ?>
                                    <div class="category-actions">
                                        <button class="action-btn add-pdf-subfolder-btn" onclick="showAddPdfSubfolderModal('<?= $categoryKey ?>')">
                                            <i class="fas fa-folder-plus"></i> Přidat podsložku
                                        </button>
                                    </div>
                                <?php endif; ?>

                                <!-- Subfolders Grid -->
                                <?php if (!empty($category['data']['folders'])): ?>
                                    <div class="pdf-subfolders-section">
                                        <h4>Podsložky</h4>
                                        <div class="pdf-subfolders-grid">
                                            <?php foreach ($category['data']['folders'] as $folderName => $folderData): ?>
                                                <?php renderPdfSubfolder($folderName, $folderData, $categoryKey, $category['info']); ?>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                <?php endif; ?>

                                <!-- PDF files in main category -->
                                <?php if (!empty($category['data']['documents'])): ?>
                                    <div class="pdf-documents-section">
                                        <h4>PDF dokumenty</h4>
                                        <div class="pdf-documents-grid">
                                            <?php foreach ($category['data']['documents'] as $pdf): ?>
                                                <?php renderPdfCard($pdf, $categoryKey, ''); ?>
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

        <!-- Add PDF Section -->
        <?php if (canUpload()): ?>
            <div class="add-pdf-section">
                <h2>Nahrát soubor</h2>
                <form action="upload-category-form-handler.php" method="post" enctype="multipart/form-data" class="pdf-form">

                    <input type="hidden" name="type" value="pdf">
                    <input type="hidden" name="redirect" value="pdfs">

                    <div class="form-row">
                        <div class="form-group">
                            <label for="custom-pdf-folder-name">Vytvořit novou kategorii (volitelné)</label>
                            <input type="text" id="custom-pdf-folder-name" name="custom_category" class="full-width"
                                   placeholder="Zadejte název pro novou PDF kategorii..."
                                   onchange="togglePdfFolderInputs()"
                                   oninput="togglePdfFolderInputs()">
                            <small class="file-help">Pokud zadáte název, vytvoří se nová hlavní kategorie s tímto názvem</small>
                        </div>
                    </div>

                    <div class="form-row" id="existing-pdf-folder-row">
                        <div class="form-group">
                            <label for="pdf-category">Nebo vyberte existující kategorii</label>
                            <select id="pdf-category" name="category" class="full-width" onchange="updatePdfSubfolderOptions()">
                                <option value="">-- Vyberte kategorii --</option>
                                <?php foreach ($pdfCategories as $key => $category): ?>
                                    <option value="<?= $key ?>"><?= htmlspecialchars($category['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="pdf-subfolder">Podsložka</label>
                            <select id="pdf-subfolder" name="subfolder" class="full-width">
                                <option value="">-- Hlavní složka --</option>
                            </select>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="pdf_file">PDF soubor *</label>
                            <div class="upload-area">
                                <label for="pdf_file" class="custom-file-button">Vyberte PDF</label>
                                <input type="file" name="file" id="pdf_file" accept=".pdf" class="hidden-file-input" required>
                                <div id="selected-file" class="selected-files-info"></div>
                            </div>
                            <small class="file-help">Podporované formáty: PDF (max 10MB)</small>
                        </div>
                    </div>

                    <div class="form-row">
                        <button type="submit" class="upload-btn" name="pdf_submit">
                            <i class="fas fa-upload"></i> Nahrát soubor
                        </button>
                    </div>
                </form>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php
// Function to render PDF subfolder
function renderPdfSubfolder($folderName, $folderData, $categoryKey, $categoryInfo)
{
?>
    <div class="pdf-subfolder-card droppable-zone"
        data-category="<?= $categoryKey ?>"
        data-subfolder="<?= htmlspecialchars($folderName) ?>"
        onclick="togglePdfSubfolder('<?= $categoryKey ?>-<?= htmlspecialchars($folderName) ?>')"
        ondrop="handlePdfDrop(event)"
        ondragover="handlePdfDragOver(event)"
        ondragenter="handlePdfDragEnter(event)"
        ondragleave="handlePdfDragLeave(event)">

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
                    <button class="mini-btn edit-btn" onclick="event.stopPropagation(); editPdfSubfolder('<?= $categoryKey ?>', '<?= htmlspecialchars($folderName) ?>')" title="Upravit podsložku">
                        <i class="fas fa-edit"></i>
                    </button>
                    <button class="mini-btn delete-btn" onclick="event.stopPropagation(); deletePdfSubfolder('<?= $categoryKey ?>', '<?= htmlspecialchars($folderName) ?>')" title="Smazat podsložku">
                        <i class="fas fa-trash"></i>
                    </button>
                <?php endif; ?>
                <i class="fas fa-chevron-right subfolder-toggle"></i>
            </div>
        </div>

        <div class="subfolder-content">
            <div class="subfolder-documents">
                <?php if (!empty($folderData['data']['documents'])): ?>
                    <?php foreach ($folderData['data']['documents'] as $pdf): ?>
                        <div class="subfolder-document draggable-file"
                            draggable="true"
                            data-file-name="<?= htmlspecialchars($pdf['original_name']) ?>"
                            data-current-category="<?= htmlspecialchars($categoryKey) ?>"
                            data-current-subfolder="<?= htmlspecialchars($folderName) ?>"
                            ondragstart="handlePdfDragStart(event)"
                            ondragend="handlePdfDragEnd(event)">
                            <div class="doc-icon">
                                <i class="fas fa-file-pdf"></i>
                                <i class="fas fa-arrows-alt drag-handle"></i>
                            </div>
                            <div class="doc-info">
                                <span class="doc-name" title="<?= htmlspecialchars($pdf['original_name']) ?>"><?= htmlspecialchars($pdf['name']) ?></span>
                                <span class="doc-size"><?= formatBytes($pdf['file_size']) ?></span>
                            </div>
                            <div class="doc-actions">
                                <a href="<?= htmlspecialchars($pdf['file_path']) ?>" target="_blank" class="mini-btn view-btn" title="Zobrazit" onclick="event.stopPropagation()">
                                    <i class="fas fa-eye"></i>
                                </a>
                                <a href="<?= htmlspecialchars($pdf['file_path']) ?>" download class="mini-btn download-btn" title="Stáhnout" onclick="event.stopPropagation()">
                                    <i class="fas fa-download"></i>
                                </a>
                                <?php if (isAdmin()): ?>
                                    <button class="mini-btn edit-btn" onclick="event.stopPropagation(); editPdfFile('<?= addslashes(htmlspecialchars($pdf['original_name'])) ?>', '<?= $categoryKey ?>', '<?= addslashes(htmlspecialchars($folderName)) ?>', '<?= addslashes(htmlspecialchars($pdf['name'])) ?>')" title="Přejmenovat">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <button class="mini-btn delete-btn" onclick="event.stopPropagation(); deletePdf('<?= addslashes(htmlspecialchars($pdf['original_name'])) ?>', '<?= $categoryKey ?>', '<?= addslashes(htmlspecialchars($folderName)) ?>')" title="Smazat">
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

// Function to render PDF card
function renderPdfCard($pdf, $categoryKey, $subfolderName = '', $isSubfolder = false)
{
    if ($isSubfolder) { ?>
        <div class="subfolder-document draggable-file"
            draggable="true"
            data-file-name="<?= htmlspecialchars($pdf['original_name']) ?>"
            data-current-category="<?= htmlspecialchars($categoryKey) ?>"
            data-current-subfolder="<?= htmlspecialchars($subfolderName) ?>"
            ondragstart="handlePdfDragStart(event)"
            ondragend="handlePdfDragEnd(event)">
            <div class="doc-icon">
                <i class="fas fa-file-pdf"></i>
                <i class="fas fa-arrows-alt drag-handle"></i>
            </div>
            <div class="doc-info">
                <span class="doc-name" title="<?= htmlspecialchars($pdf['original_name']) ?>"><?= htmlspecialchars($pdf['name']) ?></span>
                <span class="doc-size"><?= formatBytes($pdf['file_size']) ?></span>
            </div>
            <div class="doc-actions">
                <a href="<?= htmlspecialchars($pdf['file_path']) ?>" target="_blank" class="mini-btn view-btn" title="Zobrazit" onclick="event.stopPropagation()">
                    <i class="fas fa-eye"></i>
                </a>
                <a href="<?= htmlspecialchars($pdf['file_path']) ?>" download class="mini-btn download-btn" title="Stáhnout" onclick="event.stopPropagation()">
                    <i class="fas fa-download"></i>
                </a>
                <?php if (isAdmin()): ?>
                    <button class="mini-btn edit-btn" onclick="event.stopPropagation(); editPdfFile('<?= addslashes(htmlspecialchars($pdf['original_name'])) ?>', '<?= $categoryKey ?>', '<?= addslashes(htmlspecialchars($subfolderName)) ?>', '<?= addslashes(htmlspecialchars($pdf['name'])) ?>')" title="Přejmenovat">
                        <i class="fas fa-edit"></i>
                    </button>
                    <button class="mini-btn delete-btn" onclick="event.stopPropagation(); deletePdf('<?= addslashes(htmlspecialchars($pdf['original_name'])) ?>', '<?= $categoryKey ?>', '<?= addslashes(htmlspecialchars($subfolderName)) ?>')" title="Smazat">
                        <i class="fas fa-trash"></i>
                    </button>
                <?php endif; ?>
            </div>
        </div>
    <?php } else { ?>
        <div class="pdf-card-grid draggable-file"
            draggable="true"
            data-file-name="<?= htmlspecialchars($pdf['original_name']) ?>"
            data-current-category="<?= htmlspecialchars($categoryKey) ?>"
            data-current-subfolder=""
            ondragstart="handlePdfDragStart(event)"
            ondragend="handlePdfDragEnd(event)">
            <a href="<?= htmlspecialchars($pdf['file_path']) ?>" target="_blank" class="pdf-preview">
                <i class="fas fa-arrows-alt drag-handle"></i>
                <div class="file-preview-icon">
                    <i class="fas fa-file-pdf" style="color: #e74c3c; font-size: 32px;"></i>
                </div>
            </a>

            <div class="pdf-info-grid">
                <h5 class="pdf-title" title="<?= htmlspecialchars($pdf['original_name']) ?>"><?= htmlspecialchars($pdf['name']) ?></h5>

                <div class="pdf-meta-grid">
                    <span class="pdf-date">
                        <i class="fas fa-calendar"></i>
                        <?= date('d.m.Y', $pdf['date_added']) ?>
                    </span>
                    <span class="pdf-size">
                        <i class="fas fa-file"></i>
                        <?= formatBytes($pdf['file_size']) ?>
                    </span>
                </div>

                <div class="pdf-actions-grid">
                    <a href="<?= htmlspecialchars($pdf['file_path']) ?>" target="_blank" class="grid-btn view-btn" onclick="event.stopPropagation()">
                        <i class="fas fa-eye"></i>
                    </a>
                    <a href="<?= htmlspecialchars($pdf['file_path']) ?>" download class="grid-btn download-btn" onclick="event.stopPropagation()">
                        <i class="fas fa-download"></i>
                    </a>
                    <?php if (isAdmin()): ?>
                        <button class="grid-btn edit-btn" onclick="event.stopPropagation(); editPdfFile('<?= addslashes(htmlspecialchars($pdf['original_name'])) ?>', '<?= $categoryKey ?>', '', '<?= addslashes(htmlspecialchars($pdf['name'])) ?>')">
                            <i class="fas fa-edit"></i>
                        </button>
                        <button class="grid-btn delete-btn" onclick="event.stopPropagation(); deletePdf('<?= addslashes(htmlspecialchars($pdf['original_name'])) ?>', '<?= $categoryKey ?>', '')">
                            <i class="fas fa-trash"></i>
                        </button>
                    <?php endif; ?>
                </div>
            </div>
        </div>
<?php
    }
}
?>

<!-- Add PDF Subfolder Modal -->
<div id="addPdfSubfolderModal" class="modal" style="display: none;">
    <div class="modal-content">
        <div class="modal-header">
            <h2>Přidat PDF podsložku</h2>
            <button class="modal-close" onclick="closeAddPdfSubfolderModal()">&times;</button>
        </div>
        <form id="addPdfSubfolderForm">
            <input type="hidden" id="pdfParentCategory" name="parent_category">

            <div class="form-group">
                <label for="pdfSubfolderName">Název podsložky *</label>
                <input type="text" id="pdfSubfolderName" name="subfolder_name" required
                    placeholder="Název nové podsložky" class="full-width">
            </div>

            <div class="modal-actions">
                <button type="button" class="btn btn-secondary" onclick="closeAddPdfSubfolderModal()">Zrušit</button>
                <button type="submit" class="btn btn-primary">Vytvořit podsložku</button>
            </div>
        </form>
    </div>
</div>

<!-- Edit PDF Subfolder Modal -->
<div id="editPdfSubfolderModal" class="modal" style="display: none;">
    <div class="modal-content">
        <div class="modal-header">
            <h2>Upravit PDF podsložku</h2>
            <button class="modal-close" onclick="closeEditPdfSubfolderModal()">&times;</button>
        </div>
        <form id="editPdfSubfolderForm" enctype="multipart/form-data">
            <input type="hidden" id="editPdfParentCategory" name="parent_category">
            <input type="hidden" id="editPdfOriginalName" name="original_name">

            <div class="form-group">
                <label for="editPdfSubfolderName">Název podsložky *</label>
                <input type="text" id="editPdfSubfolderName" name="new_name" required
                    placeholder="Nový název podsložky" class="full-width">
            </div>

            <div class="form-group">
                <label for="editPdfSubfolderFile">Nahrát soubor do podsložky (volitelné)</label>
                <div class="upload-area">
                    <label for="editPdfSubfolderFile" class="custom-file-button">Vyberte PDF</label>
                    <input type="file" name="subfolder_file" id="editPdfSubfolderFile" accept=".pdf" class="hidden-file-input">
                    <div id="edit-pdf-subfolder-selected-file" class="selected-files-info"></div>
                </div>
                <small class="file-help">Podporované formáty: PDF (max 10MB)</small>
            </div>

            <div class="form-group">
                <label for="editPdfSubfolderCustomFilename">Přejmenovat PDF (volitelné)</label>
                <input type="text" id="editPdfSubfolderCustomFilename" name="custom_filename" class="full-width" placeholder="Ponechte prázdné pro původní název">
                <small class="file-help">Zadejte pouze název bez přípony (přípona se doplní automaticky)</small>
            </div>

            <div class="modal-actions">
                <button type="button" class="btn btn-secondary" onclick="closeEditPdfSubfolderModal()">Zrušit</button>
                <button type="submit" class="btn btn-primary">Uložit změny</button>
            </div>
        </form>
    </div>
</div>

<!-- Rename PDF Category Modal -->
<div id="pdf_renameCategoryModal" class="modal" style="display: none;">
    <div class="modal-content">
        <div class="modal-header">
            <h2>Upravit PDF kategorii</h2>
            <button class="modal-close" onclick="closeRenamePdfCategoryModal()">&times;</button>
        </div>
        <form id="pdf_renameCategoryForm" enctype="multipart/form-data">

            <input type="hidden" id="pdf_originalCategory" name="original_name">

            <div class="form-group">
                <label for="pdf_newCategoryName">Název kategorie *</label>
                <input type="text" id="pdf_newCategoryName" name="new_name" required
                    placeholder="Nový název kategorie" class="full-width">
            </div>

            <div class="form-group">
                <label for="pdf_category_file">Nahrát soubor do kategorie (volitelné)</label>
                <div class="upload-area">
                    <label for="pdf_category_file" class="custom-file-button">Vyberte PDF</label>
                    <input type="file" name="category_file" id="pdf_category_file" accept=".pdf" class="hidden-file-input">
                    <div id="rename-pdf-category-selected-file" class="selected-files-info"></div>
                </div>
                <small class="file-help">Podporované formáty: PDF (max 10MB)</small>
            </div>

            <div class="form-group">
                <label for="pdf_custom_filename">Přejmenovat PDF (volitelné)</label>
                <input type="text" id="pdf_custom_filename" name="custom_filename" class="full-width" placeholder="Ponechte prázdné pro původní název">
                <small class="file-help">Zadejte pouze název bez přípony (přípona se doplní automaticky)</small>
            </div>

            <div class="modal-actions">
                <button type="button" class="btn btn-secondary" onclick="closeRenamePdfCategoryModal()">Zrušit</button>
                <button type="submit" class="btn btn-primary">Uložit změny</button>
            </div>
        </form>
    </div>
</div>

<!-- Edit PDF File Modal -->
<div id="editPdfFileModal" class="modal" style="display: none;">
    <div class="modal-content">
        <div class="modal-header">
            <h2>Přejmenovat PDF soubor</h2>
            <button class="modal-close" onclick="closeEditPdfFileModal()">&times;</button>
        </div>
        <form id="editPdfFileForm">
            <input type="hidden" id="editPdfFileCategory" name="category">
            <input type="hidden" id="editPdfFileSubfolder" name="subfolder">
            <input type="hidden" id="editPdfFileOriginalName" name="original_name">

            <div class="form-group">
                <label for="editPdfFileName">Název souboru *</label>
                <input type="text" id="editPdfFileName" name="new_name" required
                    placeholder="Nový název souboru (bez přípony)" class="full-width">
                <small class="file-help">Zadejte pouze název bez přípony - přípona zůstane zachována</small>
            </div>

            <div class="modal-actions">
                <button type="button" class="btn btn-secondary" onclick="closeEditPdfFileModal()">Zrušit</button>
                <button type="submit" class="btn btn-primary">Přejmenovat</button>
            </div>
        </form>
    </div>
</div>

<script>
    // PDF management JavaScript functions
    let pdfSubfolderData = {};

    document.addEventListener('DOMContentLoaded', function() {
        // Only run if we're on the pdfs page
        if (!document.getElementById('pdfs')) {
            return;
        }

        // Load PDF subfolder data
        loadPdfSubfolderData();

        // Register pdf menu item if it doesn't exist
        if (window.registerMenuItem && typeof window.registerMenuItem === 'function') {
            window.registerMenuItem('pdfs', 'PDFs');
        }

        // Close all categories by default
        document.querySelectorAll('#pdfs .category-content').forEach(content => {
            if (content) {
                content.style.maxHeight = '0px';
            }
        });

        // Initialize form handlers
        initializePdfFormHandlers();

        // Initialize PDF folder input toggle
        togglePdfFolderInputs();

        // Status messages handling
        const urlParams = new URLSearchParams(window.location.search);
        const status = urlParams.get('status');
        const message = urlParams.get('message');

        if (status && message) {
            showStatusMessage(status, decodeURIComponent(message));
            const newUrl = window.location.pathname + window.location.hash;
            window.history.replaceState({}, '', newUrl);
        }

        // File input handler
        const fileInput = document.getElementById('pdf_file');
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

    // Load PDF subfolder data
    function loadPdfSubfolderData() {
        fetch('get-pdf-subfolders.php')
            .then(response => response.json())
            .then(data => {
                pdfSubfolderData = data || {};
                updatePdfSubfolderOptions();
            })
            .catch(error => console.log('No PDF subfolder data available yet:', error.message));
    }

    // Update PDF subfolder dropdown
    function updatePdfSubfolderOptions() {
        const mainFolder = document.getElementById('pdf-category');
        const subfolderSelect = document.getElementById('pdf-subfolder');

        if (!mainFolder || !subfolderSelect) return;

        subfolderSelect.innerHTML = '<option value="">-- Hlavní složka --</option>';

        if (pdfSubfolderData[mainFolder.value]) {
            pdfSubfolderData[mainFolder.value].forEach(subfolder => {
                const option = document.createElement('option');
                option.value = subfolder;
                option.textContent = subfolder;
                subfolderSelect.appendChild(option);
            });
        }
    }

    // Toggle PDF category
    function togglePdfCategoryHeader(categoryKey) {
        if (!document.getElementById('pdfs')) return;
        const categoryCard = document.querySelector(`#pdfs [data-category="${categoryKey}"]`);
        if (!categoryCard) return;

        if (categoryCard.classList.contains('expanded')) {
            categoryCard.classList.remove('expanded');
        } else {
            document.querySelectorAll('#pdfs .pdf-category.expanded').forEach(card => card.classList.remove('expanded'));
            categoryCard.classList.add('expanded');
        }
    }

    // Toggle PDF subfolder
    function togglePdfSubfolder(subfolderId) {
        if (event) event.stopPropagation();
        const subfolderCard = document.querySelector(`[onclick="togglePdfSubfolder('${subfolderId}')"]`);
        if (!subfolderCard) return;

        const content = subfolderCard.querySelector('.subfolder-content');
        if (subfolderCard.classList.contains('open')) {
            subfolderCard.classList.remove('open');
            if (content) content.style.maxHeight = '';
        } else {
            subfolderCard.classList.add('open');
            if (content) {
                setTimeout(() => {
                    content.style.maxHeight = Math.max(content.scrollHeight, 100) + 'px';
                }, 50);
            }
        }
    }

    // Show add PDF subfolder modal
    function showAddPdfSubfolderModal(categoryKey) {
        if (event) event.stopPropagation();
        document.getElementById('pdfParentCategory').value = categoryKey;
        document.getElementById('addPdfSubfolderModal').style.display = 'flex';
        document.getElementById('addPdfSubfolderForm').reset();
        setTimeout(() => document.getElementById('pdfSubfolderName').focus(), 100);
    }

    function closeAddPdfSubfolderModal() {
        document.getElementById('addPdfSubfolderModal').style.display = 'none';
    }

    // Edit PDF subfolder
    function editPdfSubfolder(categoryKey, subfolderName) {
        if (event) event.stopPropagation();
        document.getElementById('editPdfParentCategory').value = categoryKey;
        document.getElementById('editPdfOriginalName').value = subfolderName;
        document.getElementById('editPdfSubfolderName').value = subfolderName;
        document.getElementById('editPdfSubfolderModal').style.display = 'flex';
        setTimeout(() => document.getElementById('editPdfSubfolderName').focus(), 100);
    }

    function closeEditPdfSubfolderModal() {
        document.getElementById('editPdfSubfolderModal').style.display = 'none';
        document.getElementById('editPdfSubfolderForm').reset();
        document.getElementById('edit-pdf-subfolder-selected-file').textContent = '';
    }

    // Rename PDF category
    function showRenamePdfCategoryModal(categoryKey, currentName) {
        if (event) event.stopPropagation();
        document.getElementById('pdf_originalCategory').value = categoryKey;
        document.getElementById('pdf_newCategoryName').value = currentName;
        document.getElementById('pdf_renameCategoryModal').style.display = 'flex';
        setTimeout(() => document.getElementById('pdf_newCategoryName').focus(), 100);
    }

    function closeRenamePdfCategoryModal() {
        document.getElementById('pdf_renameCategoryModal').style.display = 'none';
        document.getElementById('pdf_renameCategoryForm').reset();
        document.getElementById('rename-pdf-category-selected-file').textContent = '';
    }

    function deletePdfCategory(categoryKey, categoryName) {
        if (event) event.stopPropagation();
        if (confirm(`Opravdu chcete smazat PDF kategorii "${categoryName}"?\n\nTato akce smaže kategorii a všechny soubory a podsložky v ní. Toto nelze vrátit zpět!`)) {
            const formData = new FormData();
            formData.append('category', categoryKey);
            formData.append('type', 'pdf');
            fetch('delete-category-handler.php', { method: 'POST', body: formData })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        showStatusMessage('success', data.message);
                        setTimeout(() => location.reload(), 500);
                    } else {
                        showStatusMessage('error', data.message);
                    }
                })
                .catch(error => showStatusMessage('error', 'Došlo k chybě při mazání kategorie.'));
        }
    }

    // Delete PDF subfolder
    function deletePdfSubfolder(categoryKey, subfolderName) {
        if (event) {
            event.stopPropagation();
            event.preventDefault();
        }

        if (confirm(`Opravdu chcete smazat podsložku "${subfolderName}"?\n\nTato akce smaže podsložku a všechny soubory v ní. Toto nelze vrátit zpět!`)) {
            const formData = new FormData();
            // Parametry pro univerzální handler
            formData.append('type', 'pdf');
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

    // Delete pdf function
    function deletePdf(fileName, category, subfolder = '') {
        if (!fileName || !category) return;

        if (confirm(`Opravdu chcete smazat PDF soubor "${fileName}"?`)) {
            const formData = new FormData();
            // Identifikace pro náš univerzální handler
            formData.append('type', 'pdf');
            formData.append('category', category);
            formData.append('subfolder', subfolder);
            formData.append('file_name', fileName);

            fetch('delete-file-handler.php', {
                method: 'POST',
                body: formData,
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Použijeme tvou funkci pro zobrazení zprávy
                        showStatusMessage('success', data.message);

                        // --- BLESKOVÉ ODSTRANĚNÍ Z DOMU ---
                        // Selektor trefí prvek v hlavní mřížce i v podsložkách
                        const selector = `[data-file-name="${fileName}"][data-current-category="${category}"][data-current-subfolder="${subfolder}"]`;
                        const fileElement = document.querySelector(selector);

                        if (fileElement) {
                            // Sexy animace: prvek se smrskne a zmizí
                            fileElement.style.transition = 'all 0.4s cubic-bezier(0.4, 0, 0.2, 1)';
                            fileElement.style.opacity = '0';
                            fileElement.style.transform = 'scale(0.8) translateY(-10px)';
                            fileElement.style.pointerEvents = 'none';

                            setTimeout(() => {
                                fileElement.remove();
                            }, 400);
                        } else {
                            // Fallback: pokud JS prvek nenajde, refresh to jistí
                            setTimeout(() => location.reload(), 500);
                        }
                    } else {
                        showStatusMessage('error', data.message);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    showStatusMessage('error', 'Chyba při komunikaci se serverem.');
                });
        }
    }

    // Edit PDF file
    function editPdfFile(originalName, category, subfolder, currentName) {
        document.getElementById('editPdfFileCategory').value = category;
        document.getElementById('editPdfFileSubfolder').value = subfolder || '';
        document.getElementById('editPdfFileOriginalName').value = originalName;
        const nameWithoutExt = currentName.replace(/\.[^/.]+$/, "");
        document.getElementById('editPdfFileName').value = nameWithoutExt;
        document.getElementById('editPdfFileModal').style.display = 'flex';
        setTimeout(() => document.getElementById('editPdfFileName').focus(), 100);
    }

    function closeEditPdfFileModal() {
        document.getElementById('editPdfFileModal').style.display = 'none';
    }

    function initializePdfFormHandlers() {
        const addSubfolderForm = document.getElementById('addPdfSubfolderForm');
        if (addSubfolderForm) {
            addSubfolderForm.addEventListener('submit', function(e) {
                e.preventDefault();
                const formData = new FormData(this);
                fetch('add-pdf-subfolder.php', { method: 'POST', body: formData })
                    .then(res => res.json()).then(handleFormResponse).catch(handleFormError);
            });
        }

        const editPdfSubfolderForm = document.getElementById('editPdfSubfolderForm');
        if (editPdfSubfolderForm) {
            editPdfSubfolderForm.addEventListener('submit', function(e) {
                e.preventDefault();

                const formData = new FormData(this);
                // 1. Identifikace pro univerzální mozek (složka /pdf/)
                formData.append('type', 'pdf');

                const btn = this.querySelector('button[type="submit"]');
                const originalText = btn.innerHTML;
                if (btn) {
                    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Ukládá se...';
                    btn.disabled = true;
                }

                // 2. Míříme na společný edit-subfolder-handler.php
                fetch('edit-subfolder-handler.php', {
                    method: 'POST',
                    body: formData,
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                })
                    .then(res => res.json())
                    .then(data => {
                        // Použijeme tvé stávající funkce pro odpovědi, pokud je preferuješ,
                        // nebo to vyřešíme přímo tady
                        if (data.success) {
                            if (typeof closeEditPdfSubfolderModal === 'function') closeEditPdfSubfolderModal();
                            if (typeof showStatusMessage === 'function') {
                                showStatusMessage('success', data.message);
                            }
                            setTimeout(() => location.reload(), 500);
                        } else {
                            alert(data.message);
                        }
                    })
                    .catch(err => {
                        console.error('Error:', err);
                        if (typeof handleFormError === 'function') {
                            handleFormError(err);
                        } else {
                            alert('Chyba při komunikaci se serverem.');
                        }
                    })
                    .finally(() => {
                        if (btn) {
                            btn.innerHTML = originalText;
                            btn.disabled = false;
                        }
                    });
            });
        }

// Edit category form v pdfs.php - UNIVERZÁLNÍ ÚPRAVA
        const renameCategoryForm = document.getElementById('pdf_renameCategoryForm');
        if (renameCategoryForm) {
            renameCategoryForm.addEventListener('submit', function(e) {
                e.preventDefault();

                const formData = new FormData(this);

                // PŘIDÁNO: Identifikátor pro univerzální handler
                formData.append('type', 'pdf');

                const submitBtn = this.querySelector('button[type="submit"]');
                if (submitBtn) {
                    submitBtn.disabled = true;
                    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
                }

                // ZMĚNĚNO: Míříme na společný edit-category-handler.php
                fetch('edit-category-handler.php', {
                    method: 'POST',
                    body: formData
                })
                    .then(res => res.json())
                    .then(data => {
                        if (data.success) {
                            // Zavření modalu (předpokládám, že se jmenuje takto, případně uprav)
                            if (typeof closeRenameModal === 'function') closeRenameModal();

                            if (typeof showStatusMessage === 'function') {
                                showStatusMessage('success', data.message);
                            }

                            // ZRYCHLENÝ RELOAD: Jen 500ms, ať nečekáš jak na Vánoce
                            setTimeout(() => location.reload(), 500);
                        } else {
                            if (typeof showStatusMessage === 'function') {
                                showStatusMessage('error', data.message);
                            } else {
                                alert(data.message);
                            }
                            if (submitBtn) {
                                submitBtn.disabled = false;
                                submitBtn.innerHTML = 'Uložit změny';
                            }
                        }
                    })
                    .catch(err => {
                        console.error('Error:', err);
                        if (typeof showStatusMessage === 'function') {
                            showStatusMessage('error', 'Chyba při komunikaci se serverem');
                        }
                    });
            });
        }

        // Handler pro přejmenování PDF souboru
        const editPdfFileFormEl = document.getElementById('editPdfFileForm');
        if (editPdfFileFormEl) {
            editPdfFileFormEl.addEventListener('submit', function(e) {
                e.preventDefault();

                // 1. Sesbíráme data z hidden inputů v modalu
                const category = document.getElementById('editPdfFileCategory').value;
                const subfolder = document.getElementById('editPdfFileSubfolder').value;
                const originalName = document.getElementById('editPdfFileOriginalName').value;
                const newName = document.getElementById('editPdfFileName').value;

                if (!category || !originalName || !newName) {
                    alert('Všechna pole jsou povinná.');
                    return;
                }

                const formData = new FormData();
                // 2. KLÍČOVÝ PARAMETR: 'pdf' (pro getRoot v access.php)
                formData.append('type', 'pdf');
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
                            // Zavřeme modal (použijeme tvou funkci)
                            if (typeof closeEditPdfFileModal === 'function') closeEditPdfFileModal();

                            showStatusMessage('success', data.message);

                            // Refresh je nutný, aby se v HTML přepsaly odkazy na PDF
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

        function handleFormResponse(data) {
            if (data.success) {
                showStatusMessage('success', data.message);
                setTimeout(() => location.reload(), 500);
            } else {
                showStatusMessage('error', data.message || 'Neznámá chyba.');
            }
        }

        function handleFormError(error) {
            showStatusMessage('error', 'Došlo k chybě při komunikaci se serverem.');
            console.error('Form submission error:', error);
        }

        // File input handlers for modals
        const editSubfolderFileInput = document.getElementById('editPdfSubfolderFile');
        if (editSubfolderFileInput) {
            editSubfolderFileInput.addEventListener('change', function() {
                const selectedFileInfo = document.getElementById('edit-pdf-subfolder-selected-file');
                const customFilenameInput = document.getElementById('editPdfSubfolderCustomFilename');
                if (this.files.length > 0) {
                    const file = this.files[0];
                    selectedFileInfo.textContent = `Vybráno: ${file.name} (${formatFileSize(file.size)})`;
                    if (!customFilenameInput.value) {
                        customFilenameInput.value = file.name.replace(/\.[^/.]+$/, "");
                    }
                } else {
                    selectedFileInfo.textContent = '';
                }
            });
        }

        const renameCategoryFileInput = document.getElementById('pdf_category_file');
        if (renameCategoryFileInput) {
            renameCategoryFileInput.addEventListener('change', function() {
                const selectedFileInfo = document.getElementById('rename-pdf-category-selected-file');
                const customFilenameInput = document.getElementById('pdf_custom_filename');
                if (this.files.length > 0) {
                    const file = this.files[0];
                    selectedFileInfo.textContent = `Vybráno: ${file.name} (${formatFileSize(file.size)})`;
                    if (!customFilenameInput.value) {
                        customFilenameInput.value = file.name.replace(/\.[^/.]+$/, "");
                    }
                } else {
                    selectedFileInfo.textContent = '';
                }
            });
        }
    }

    function showStatusMessage(type, message) {
        const statusContainer = document.getElementById('status-messages');
        if (!statusContainer) return;
        const messageDiv = document.createElement('div');
        messageDiv.className = `status-message status-${type}`;
        messageDiv.innerHTML = `<i class="fas ${type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle'}"></i> ${message}`;
        statusContainer.appendChild(messageDiv);
        setTimeout(() => {
            messageDiv.style.opacity = '0';
            setTimeout(() => messageDiv.remove(), 300);
        }, 5000);
    }

    function formatFileSize(bytes) {
        if (!bytes || bytes === 0) return '0 B';
        const k = 1024;
        const sizes = ['B', 'KB', 'MB', 'GB'];
        const i = Math.floor(Math.log(bytes) / Math.log(k));
        return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
    }

    // Toggle between custom folder input and existing folder dropdown for PDFs
    function togglePdfFolderInputs() {
        const customFolderInput = document.getElementById('custom-pdf-folder-name');
        const existingFolderRow = document.getElementById('existing-pdf-folder-row');
        const pdfCategory = document.getElementById('pdf-category');
        const pdfSubfolder = document.getElementById('pdf-subfolder');

        if (customFolderInput && existingFolderRow && pdfCategory && pdfSubfolder) {
            if (customFolderInput.value.trim()) {
                existingFolderRow.style.opacity = '0.5';
                pdfCategory.disabled = true;
                pdfSubfolder.disabled = true;
                pdfCategory.value = '';
                pdfSubfolder.innerHTML = '<option value="">-- Nová kategorie --</option>';
            } else {
                existingFolderRow.style.opacity = '1';
                pdfCategory.disabled = false;
                pdfSubfolder.disabled = false;
                updatePdfSubfolderOptions();
            }
        }
    }

    // Expose functions to global scope
    window.togglePdfCategoryHeader = togglePdfCategoryHeader;
    window.togglePdfSubfolder = togglePdfSubfolder;
    window.showAddPdfSubfolderModal = showAddPdfSubfolderModal;
    window.closeAddPdfSubfolderModal = closeAddPdfSubfolderModal;
    window.editPdfSubfolder = editPdfSubfolder;
    window.closeEditPdfSubfolderModal = closeEditPdfSubfolderModal;
    window.deletePdfSubfolder = deletePdfSubfolder;
    window.showRenamePdfCategoryModal = showRenamePdfCategoryModal;
    window.closeRenamePdfCategoryModal = closeRenamePdfCategoryModal;
    window.deletePdfCategory = deletePdfCategory;
    window.deletePdf = deletePdf;
    window.editPdfFile = editPdfFile;
    window.closeEditPdfFileModal = closeEditPdfFileModal;
    window.togglePdfFolderInputs = togglePdfFolderInputs;
    window.updatePdfSubfolderOptions = updatePdfSubfolderOptions;

    // Drag and Drop for PDFs - Use unique variable names
    let draggedPdfElement = null;
    let draggedPdfFileData = null;

    const supportsFileSystemAccessPdf = 'getAsFileSystemHandle' in DataTransferItem.prototype;

    function handlePdfDragStart(event) {
        console.log("🚀 PDF drag started");

        draggedPdfElement = event.target.closest('.draggable-file');
        if (!draggedPdfElement) return;
        draggedPdfElement.classList.add('dragging');

        draggedPdfFileData = {
            fileName: draggedPdfElement.getAttribute('data-file-name'),
            currentCategory: draggedPdfElement.getAttribute('data-current-category'),
            currentSubfolder: draggedPdfElement.getAttribute('data-current-subfolder') || ''
        };

        event.dataTransfer.effectAllowed = 'move';
        event.dataTransfer.setData('text/plain', JSON.stringify(draggedPdfFileData));

        if (supportsFileSystemAccessPdf) {
            try {
                const fileData = new Blob([JSON.stringify(draggedPdfFileData)], {
                    type: 'application/json'
                });
                const file = new File([fileData], 'pdf-move-data.json', {
                    type: 'application/json'
                });
                event.dataTransfer.items.add(file);
            } catch (error) {
                console.warn("⚠️ Could not set enhanced drag data:", error);
            }
        }
    }

    function handlePdfDragEnd(event) {
        if (draggedPdfElement) {
            draggedPdfElement.classList.remove('dragging');
        }

        document.querySelectorAll('.droppable-zone').forEach(zone => {
            zone.classList.remove('drag-over');
        });

        draggedPdfElement = null;
        draggedPdfFileData = null;
    }

    function handlePdfDragOver(event) {
        event.preventDefault();
        event.dataTransfer.dropEffect = 'move';
    }

    function handlePdfDragEnter(event) {
        event.preventDefault();
        event.stopPropagation();

        if (!draggedPdfFileData) return;

        const dropZone = event.currentTarget;
        const targetCategory = dropZone.getAttribute('data-category');
        const targetSubfolder = dropZone.getAttribute('data-subfolder') || '';

        if (targetCategory === draggedPdfFileData.currentCategory &&
            targetSubfolder === draggedPdfFileData.currentSubfolder) {
            return;
        }

        dropZone.classList.add('drag-over');
    }

    function handlePdfDragLeave(event) {
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

    async function handlePdfDrop(event) {
        event.preventDefault();
        event.stopPropagation();

        const dropZone = event.currentTarget;
        const targetCategory = dropZone.getAttribute('data-category');
        const targetSubfolder = dropZone.getAttribute('data-subfolder') || '';

        dropZone.classList.remove('drag-over');

        let fileData = null;

        if (supportsFileSystemAccessPdf && event.dataTransfer.items.length > 0) {
            for (let i = 0; i < event.dataTransfer.items.length; i++) {
                const item = event.dataTransfer.items[i];

                try {
                    if (item.kind === 'file') {
                        const handle = await item.getAsFileSystemHandle();

                        if (handle && handle.kind === 'file') {
                            fileData = {
                                fileName: handle.name,
                                currentCategory: draggedPdfFileData?.currentCategory,
                                currentSubfolder: draggedPdfFileData?.currentSubfolder
                            };
                            break;
                        }
                    }
                } catch (error) {
                    console.warn("⚠️ File System Access API failed:", error);
                }
           
            }
        }

        if (!fileData && draggedPdfFileData) {
            fileData = draggedPdfFileData;
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

        await movePdfFile(fileData, targetCategory, targetSubfolder);
    }

    async function movePdfFile(fileData, targetCategory, targetSubfolder) {
        showStatusMessage('info', 'Přesouvám soubor...');

        const formData = new FormData();
        formData.append('fileName', fileData.fileName);
        formData.append('sourceCategory', fileData.currentCategory);
        formData.append('sourceSubfolder', fileData.currentSubfolder);
        formData.append('targetCategory', targetCategory);
        formData.append('targetSubfolder', targetSubfolder);
        formData.append('move_pdf_file', '1');

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
</script>