<?php
// BUFFER OUTPUT IMMEDIATELY TO FIX "HEADERS ALREADY SENT"
ob_start();

// Set proper encoding for Czech characters  
mb_internal_encoding('UTF-8');

require_once 'access.php';

// Handle file move request
if (isset($_POST['move_list_file']) && isAdmin()) {
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
        $listBaseDir = getRoot('seznam');
        $sourcePath = $listBaseDir . $sourceCategory . '/' . ($sourceSubfolder ? $sourceSubfolder . '/' : '');
        $targetPath = $listBaseDir . $targetCategory . '/' . ($targetSubfolder ? $targetSubfolder . '/' : '');
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
function getListFileTypeInfo($extension)
{
    $extension = strtolower($extension);

    switch ($extension) {
        case 'txt':
            return [
                'type' => 'text',
                'icon' => 'fas fa-file-alt',
                'color' => '#007bff',
                'viewable' => true
            ];
        case 'pdf':
            return [
                'type' => 'pdf',
                'icon' => 'fas fa-file-pdf',
                'color' => '#dc3545',
                'viewable' => true
            ];
        case 'doc':
        case 'docx':
            return [
                'type' => 'document',
                'icon' => 'fas fa-file-word',
                'color' => '#2980b9',
                'viewable' => false
            ];
        case 'jpg':
        case 'jpeg':
        case 'png':
        case 'gif':
            return [
                'type' => 'image',
                'icon' => 'fas fa-file-image',
                'color' => '#e67e22',
                'viewable' => true
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

// Get existing categories - scan actual directories
function getListCategories()
{
    $listDir = getRoot('seznam');
    $categories = [];

    if (is_dir($listDir)) {
        $items = array_diff(scandir($listDir), ['.', '..']);

        foreach ($items as $item) {
            $itemPath = $listDir . $item;
            if (is_dir($itemPath)) {
                $categories[] = $item;
            }
        }
    }

    return $categories;
}

// Function to scan for subfolders and files
function scanListRecursive($dir, $categoryKey, $depth = 0)
{
    $result = ['folders' => [], 'documents' => []];

    if (!is_dir($dir)) return $result;

    $items = array_diff(scandir($dir), ['.', '..']);

    foreach ($items as $item) {
        $itemPath = $dir . $item;

        if (is_dir($itemPath)) {
            // It's a subfolder
            $subResult = scanListRecursive($itemPath . '/', $categoryKey, $depth + 1);
            $result['folders'][$item] = [
                'name' => $item,
                'path' => $itemPath,
                'data' => $subResult,
                'depth' => $depth
            ];
        } else {
            // It's a file - add it directly
            $extension = strtolower(pathinfo($item, PATHINFO_EXTENSION));

            // Include text files and documents
            $allowedExtensions = ['txt', 'pdf', 'doc', 'docx', 'jpg', 'jpeg', 'png', 'gif'];

            if (in_array($extension, $allowedExtensions)) {
                $fileTypeInfo = getListFileTypeInfo($extension);

                $fileData = [
                    'name' => pathinfo($item, PATHINFO_FILENAME),
                    'original_name' => $item,
                    'file_path' => str_replace($_SERVER['DOCUMENT_ROOT'], '', $itemPath),
                    'file_size' => filesize($itemPath),
                    'file_extension' => $extension,
                    'date_added' => filemtime($itemPath),
                    'has_file' => true,
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

// Function to render list subfolder
function renderListSubfolder($folderName, $folderData, $categoryKey, $categoryInfo)
{
?>
    <div class="list-subfolder-card droppable-zone" 
         data-category="<?= htmlspecialchars($categoryKey) ?>"
         data-subfolder="<?= htmlspecialchars($folderName) ?>"
         onclick="toggleListSubfolder('<?= $categoryKey ?>-<?= $folderName ?>')"
         ondrop="handleListDrop(event)" 
         ondragover="handleListDragOver(event)"
         ondragenter="handleListDragEnter(event)"
         ondragleave="handleListDragLeave(event)">
        
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
                    <button class="mini-btn edit-btn" onclick="event.stopPropagation(); editListSubfolder('<?= $categoryKey ?>', '<?= $folderName ?>')" title="Upravit podsložku">
                        <i class="fas fa-edit"></i>
                    </button>
                    <button class="mini-btn delete-btn" onclick="event.stopPropagation(); deleteListSubfolder('<?= $categoryKey ?>', '<?= $folderName ?>')" title="Smazat podsložku">
                        <i class="fas fa-trash"></i>
                    </button>
                <?php endif; ?>
                <i class="fas fa-chevron-right subfolder-toggle"></i>
            </div>
        </div>

        <div class="subfolder-content">
            <div class="subfolder-documents">
                <?php if (!empty($folderData['data']['documents'])): ?>
                    <?php foreach ($folderData['data']['documents'] as $item): ?>
                        <div class="subfolder-document draggable-file" 
                             draggable="true"
                             data-file-name="<?= htmlspecialchars($item['original_name']) ?>"
                             data-current-category="<?= htmlspecialchars($categoryKey) ?>"
                             data-current-subfolder="<?= htmlspecialchars($folderName) ?>"
                             ondragstart="handleListDragStart(event)"
                             ondragend="handleListDragEnd(event)">
                            
                            <i class="fas fa-arrows-alt drag-handle"></i>
                            
                            <div class="doc-icon">
                                <i class="<?= $item['file_type_info']['icon'] ?>" style="color: <?= $item['file_type_info']['color'] ?>;"></i>
                            </div>
                            <div class="doc-info">
                                <span class="doc-name"><?= htmlspecialchars($item['name']) ?></span>
                                <span class="doc-size"><?= formatBytes($item['file_size']) ?></span>
                            </div>
                            <div class="doc-actions">
                                <?php if ($item['file_type_info']['viewable']): ?>
                                    <a href="<?= htmlspecialchars($item['file_path']) ?>" target="_blank" class="mini-btn view-btn" onclick="event.stopPropagation();" title="Zobrazit">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                <?php endif; ?>

                                <a href="<?= htmlspecialchars($item['file_path']) ?>" download class="mini-btn download-btn" onclick="event.stopPropagation();" title="Stáhnout">
                                    <i class="fas fa-download"></i>
                                </a>

                                <?php if (isAdmin()): ?>
                                    <button class="mini-btn edit-btn" onclick="event.stopPropagation(); editListFile('<?= addslashes($item['original_name']) ?>', '<?= $categoryKey ?>', '<?= addslashes($folderName) ?>', '<?= addslashes($item['name']) ?>')" title="Přejmenovat">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <button class="mini-btn delete-btn" onclick="event.stopPropagation(); deleteListItem('<?= addslashes($item['original_name']) ?>', '<?= $categoryKey ?>', '<?= addslashes($folderName) ?>')" title="Smazat">
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

// Function to render LIST card
function renderListCard($item, $categoryKey)
{
?>
    <div class="list-card-grid draggable-file" 
         draggable="true" 
         data-file-name="<?= htmlspecialchars($item['original_name']) ?>"
         data-current-category="<?= htmlspecialchars($categoryKey) ?>"
         data-current-subfolder=""
         ondragstart="handleListDragStart(event)"
         ondragend="handleListDragEnd(event)">
        
        <div class="list-preview">
            <div class="file-preview-icon">
                <i class="<?= $item['file_type_info']['icon'] ?>" style="color: <?= $item['file_type_info']['color'] ?>; font-size: 32px;"></i>
            </div>
            <i class="fas fa-arrows-alt drag-handle"></i>
        </div>

        <div class="list-info-grid">
            <h5 class="list-title"><?= htmlspecialchars($item['name']) ?></h5>

            <div class="list-meta-grid">
                <span class="list-date">
                    <i class="fas fa-calendar"></i>
                    <?= date('d.m.Y', $item['date_added']) ?>
                </span>
                <span class="list-size">
                    <i class="fas fa-file"></i>
                    <?= formatBytes($item['file_size']) ?>
                </span>
            </div>

            <div class="list-actions-grid">
                <?php if ($item['file_type_info']['viewable']): ?>
                    <a href="<?= htmlspecialchars($item['file_path']) ?>" target="_blank" class="grid-btn view-btn">
                        <i class="fas fa-eye"></i>
                    </a>
                <?php endif; ?>

                <a href="<?= htmlspecialchars($item['file_path']) ?>" download class="grid-btn download-btn">
                    <i class="fas fa-download"></i>
                </a>

                <?php if (isAdmin()): ?>
                    <button class="grid-btn edit-btn" onclick="editListFile('<?= addslashes($item['original_name']) ?>', '<?= $categoryKey ?>', '', '<?= addslashes($item['name']) ?>')">
                        <i class="fas fa-edit"></i>
                    </button>
                    <button class="grid-btn delete-btn" onclick="deleteListItem('<?= addslashes($item['original_name']) ?>', '<?= $categoryKey ?>', '')">
                        <i class="fas fa-trash"></i>
                    </button>
                <?php endif; ?>
            </div>
        </div>
    </div>
<?php
}
?>

<style>
    /* List page styling - copied and adapted from test.php */
    #list .content-area {
        display: flex;
        flex-direction: column;
        gap: 30px;
    }

    .add-list-section {
        background: white;
        border-radius: 10px;
        padding: 25px;
        margin-bottom: 25px;
        box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
    }

    .add-list-section h2 {
        color: #2c3e50;
        margin-top: 0;
        margin-bottom: 20px;
        font-size: 20px;
    }

    .list-form {
        display: flex;
        flex-direction: column;
        gap: 15px;
    }
    .list-grid-container {
        height: auto !important;
        min-height: 0 !important;
        flex-grow: 0; /* Zabrání natahování ve flexu */
        align-self: flex-start; /* Zarovná se na začátek a nebude se natahovat na celou výšku */
        width: 100%;
    }

    .list-category {
        background: white;
        border-radius: 10px;
        box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        overflow: hidden;
        transition: all 0.3s ease;
    }

    .list-category:hover {
        box-shadow: 0 5px 15px rgba(0, 0, 0, 0.15);
    }

    .list-category.expanded {
        grid-column: 1 / -1;
        max-width: none;
        transform: none !important;
        z-index: 10;
    }

    .list-category.expanded .category-content {
        max-height: none !important;
        padding: 20px !important;
        display: block !important;
        opacity: 1 !important;
        overflow: visible !important;
    }

    .list-category.expanded .category-toggle i {
        transform: rotate(180deg);
    }

    .list-category:hover:not(.expanded) {
        transform: translateY(-2px);
        box-shadow: 0 5px 15px rgba(0, 0, 0, 0.15);
    }

    .list-category:not(.expanded) .category-content {
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

    .list-documents-section {
        margin-top: 0 !important;
        padding-top: 0 !important;
    }

    .list-documents-section h4 {
        font-size: 16px;
        margin-bottom: 15px;
        color: #2c3e50;
        font-weight: 600;
    }

    .list-documents-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
        gap: 15px;
    }

    .list-card-grid {
        background: white;
        border: 1px solid #e9ecef;
        border-radius: 8px;
        overflow: hidden;
        transition: all 0.2s;
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
        opacity: 1 !important;
        display: block !important;
    }

    .list-card-grid:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
        border-color: #dee2e6;
    }

    .list-preview {
        height: 120px;
        display: flex;
        align-items: center;
        justify-content: center;
        background: #f8f9fa;
        border-bottom: 1px solid #e9ecef;
        overflow: hidden;
        position: relative;
    }

    .file-preview-icon {
        position: relative;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .drag-handle {
        cursor: grab;
        color: #6c757d;
        transition: color 0.2s;
        position: absolute;
        top: 5px;
        right: 5px;
        font-size: 12px;
        opacity: 0.7;
    }

    .drag-handle:hover {
        color: #495057;
        opacity: 1;
    }

    .drag-handle:active {
        cursor: grabbing;
    }

    .list-info-grid {
        padding: 15px;
        display: flex;
        flex-direction: column;
        gap: 8px;
    }

    .list-title {
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

    .list-meta-grid {
        display: flex;
        justify-content: space-between;
        align-items: center;
        font-size: 11px;
        color: #6c757d;
        margin-top: auto;
        flex-wrap: wrap;
        gap: 8px;
    }

    .list-date,
    .list-size {
        display: flex;
        align-items: center;
        gap: 4px;
    }

    .list-actions-grid {
        display: flex;
        gap: 6px;
        justify-content: flex-end;
        margin-top: 8px;
        padding-top: 8px;
        border-top: 1px solid #f0f0f0;
    }

    .no-list {
        text-align: center;
        color: #7f8c8d;
        font-style: italic;
    }

    .categories-grid {
        display: grid;
        grid-template-columns: repeat(2, 1fr);
        gap: 20px;
    }

    @media (max-width: 768px) {
        .categories-grid {
            grid-template-columns: 1fr;
        }
    }


    .category-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 10px 15px;
        background: #f1f1f1;
        border-bottom: 1px solid #dee2e6;
    }

    .category-header h3 {
        margin: 0;
        font-size: 18px;
        font-weight: 500;
        color: #333;
    }

    .category-header .list-count {
        font-size: 14px;
        color: #666;
    }

    .grid-btn {
        background: #007bff;
        color: white;
        border: none;
        padding: 8px 12px;
        border-radius: 4px;
        cursor: pointer;
        transition: background 0.2s;
        text-decoration: none;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .grid-btn:hover {
        background: #0056b3;
    }

    .grid-btn.view-btn {
        background: #28a745;
    }

    .grid-btn.view-btn:hover {
        background: #218838;
    }


    /* Custom styles for file upload inputs */
    .upload-area {
        position: relative;
        overflow: hidden;
        border: 2px dashed #007bff;
        border-radius: 4px;
        padding: 10px;
        cursor: pointer;
        transition: border-color 0.2s;
    }

    .upload-area:hover {
        border-color: #0056b3;
    }

    .custom-file-button {
        display: inline-block;
        padding: 10px 20px;
        background: #007bff;
        color: white;
        border-radius: 4px;
        cursor: pointer;
        transition: background 0.2s;
    }

    .custom-file-button:hover {
        background: #0056b3;
    }

    .hidden-file-input {
        position: absolute;
        width: 100%;
        height: 100%;
        top: 0;
        left: 0;
        opacity: 0;
        cursor: pointer;
    }

    .selected-files-info {
        margin-top: 10px;
        font-size: 14px;
        color: #333;
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

    /* Modal styling */
    .modal {
        display: none !important;
        position: fixed !important;
        z-index: 1000 !important;
        left: 0;
        top: 0;
        width: 100%;
        height: 100%;
        background: rgba(0, 0, 0, 0.8) !important;
        justify-content: center !important;
        align-items: center !important;
    }

    .modal[style*="flex"] {
        display: flex !important;
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

    /* Add category actions styling */
    .category-actions {
        margin-bottom: 20px;
        display: flex;
        gap: 10px;
    }

    .add-list-subfolder-btn {
        background-color: #138496 !important;
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

    .add-list-subfolder-btn:hover {
        background: #138496;
    }

    /* Subfolder styling */
    .list-subfolders-section {
        margin-bottom: 25px;
    }

    .list-subfolders-section h4 {
        color: #495057;
        font-size: 14px;
        font-weight: 600;
        margin: 0 0 12px 0;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    .list-subfolders-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
        gap: 12px;
    }

    .list-subfolder-card {
        background: #f8f9fa;
        border: 1px solid #e9ecef;
        border-radius: 8px;
        padding: 15px;
        cursor: pointer;
        transition: all 0.2s;
    }

    .list-subfolder-card:hover {
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
    .list-subfolder-card.open {
        grid-column: 1 / -1;
        width: 100%;
        max-width: none;
        background: #fff;
        border-color: #dee2e6;
    }

    .list-subfolder-card.open .subfolder-content {
        max-height: 1000px;
        /* Large enough value */
        margin-top: 12px;
        padding-top: 12px;
        border-top: 1px solid #dee2e6;
    }

    .list-subfolder-card.open .subfolder-toggle {
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
        position: relative;
        width: fit-content;
    }

    .subfolder-document .drag-handle {
        position: absolute;
        top: 2px;
        right: 2px;
        font-size: 10px;
        z-index: 5;
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

    .mini-btn {
        background: transparent;
        border: none;
        cursor: pointer;
        transition: color 0.2s;
        padding: 4px;
        font-size: 12px;
        color: #6c757d;
    }

    .mini-btn:hover {
        color: #007bff;
    }

    /* Add missing admin button styles from edo.php */
    .category-admin-actions {
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .admin-btn {
        background: transparent;
        border: none;
        cursor: pointer;
        transition: color 0.2s;
        padding: 4px;
        font-size: 12px;
        color: #6c757d;
    }

    .admin-btn:hover {
        color: #007bff;
    }

    .admin-btn.edit-btn:hover {
        color: #ffc107;
    }

    .admin-btn.delete-btn:hover {
        color: #dc3545;
    }

    .upload-btn {
        background: #007bff;
        color: white;
        border: none;
        padding: 12px 24px;
        border-radius: 5px;
        cursor: pointer;
        font-size: 14px;
        display: flex;
        align-items: center;
        gap: 8px;
        transition: background 0.2s;
    }

    .upload-btn:hover {
        background: #0056b3;
    }

    .upload-btn i {
        font-size: 16px;
    }

    /* Add drag and drop styles */
    .list-card-grid.dragging {
        opacity: 0.5;
        transform: scale(0.95);
        cursor: grabbing;
    }

    .subfolder-document.dragging {
        opacity: 0.5;
        transform: scale(0.95);
        cursor: grabbing;
    }

    .list-subfolder-card.drag-over {
        background: #e3f2fd !important;
        border-color: #2196f3 !important;
        box-shadow: 0 0 15px rgba(33, 150, 243, 0.3) !important;
    }

    .list-category.drag-over {
        background: #f3e5f5 !important;
        border-color: #9c27b0 !important;
        box-shadow: 0 0 15px rgba(156, 39, 176, 0.3) !important;
    }

    .drag-handle {
        cursor: grab;
        color: #6c757d;
        transition: color 0.2s;
        position: absolute;
        top: 5px;
        right: 5px;
        font-size: 12px;
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

    .list-subfolder-card.drag-over .drop-zone-indicator {
        display: flex;
    }

    .list-category.drag-over .drop-zone-indicator {
        display: flex;
    }

    .list-subfolder-card {
        position: relative;
    }

    .list-category {
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

<div class="page-content" id="list">
    <div class="content-area">
        <!-- Status Messages -->
        <div id="status-messages"></div>

        <!-- List Grid Container -->
        <div class="list-grid-container">
            <?php
            $listDir = getRoot('seznam');
            $listByCategory = [];

            // Scan for files and subfolders
            if (is_dir($listDir)) {
                $categories = getListCategories();

                foreach ($categories as $categoryKey) {
                    $categoryDir = $listDir . $categoryKey . '/';
                    if (is_dir($categoryDir)) {
                        $categoryData = scanListRecursive($categoryDir, $categoryKey);
                        if (!empty($categoryData['documents']) || !empty($categoryData['folders'])) {
                            $listByCategory[$categoryKey] = [
                                'info' => ['name' => $categoryKey, 'color' => '#3498db'],
                                'data' => $categoryData
                            ];
                        } else if (isAdmin()) {
                            // Show empty categories to admins
                            $listByCategory[$categoryKey] = [
                                'info' => ['name' => $categoryKey, 'color' => '#3498db'],
                                'data' => $categoryData
                            ];
                        }
                    }
                }
            }

            if (empty($listByCategory)): ?>
                <div class="no-list">
                    <i class="fas fa-list"></i>
                    <p>Zatím nejsou žádné položky v systému.</p>
                </div>
            <?php else: ?>
                <div class="categories-grid">
                    <?php foreach ($listByCategory as $categoryKey => $category): ?>
                        <div class="list-category droppable-zone" 
                             data-category="<?= htmlspecialchars($categoryKey, ENT_QUOTES, 'UTF-8') ?>" 
                             style="border-top: 4px solid <?= $category['info']['color'] ?>"
                             ondrop="handleListDrop(event)" 
                             ondragover="handleListDragOver(event)"
                             ondragenter="handleListDragEnter(event)"
                             ondragleave="handleListDragLeave(event)">
                            
                            <div class="drop-zone-indicator">
                                <i class="fas fa-file-import"></i> &nbsp; Přetáhněte soubor do hlavní složky
                            </div>

                            <div class="category-header category-header-clickable" onclick="toggleListCategoryHeader('<?= htmlspecialchars($categoryKey, ENT_QUOTES, 'UTF-8') ?>')">
                                <div class="category-info">
                                    <h3 style="color: <?= $category['info']['color'] ?>">
                                        <i class="fas fa-folder"></i>
                                        <?= htmlspecialchars($category['info']['name'], ENT_QUOTES, 'UTF-8') ?>
                                    </h3>
                                    <span class="list-count">
                                        <?= countAllDocuments($category['data']) ?> položek
                                        <?php if (!empty($category['data']['folders'])): ?>
                                            • <?= count($category['data']['folders']) ?> podsložek
                                        <?php endif; ?>
                                    </span>
                                </div>

                                <?php if (isAdmin()): ?>
                                    <div class="category-admin-actions" onclick="event.stopPropagation()">
                                        <button class="admin-btn edit-btn" onclick="editListCategory('<?= htmlspecialchars($categoryKey, ENT_QUOTES, 'UTF-8') ?>')" title="Přejmenovat kategorii">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <button class="admin-btn delete-btn" onclick="deleteListCategory('<?= htmlspecialchars($categoryKey, ENT_QUOTES, 'UTF-8') ?>')" title="Smazat kategorii">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </div>
                                <?php endif; ?>

                                <div class="category-toggle">
                                    <i class="fas fa-chevron-down"></i>
                                </div>
                            </div>

                            <div class="category-content" id="list-category-content-<?= htmlspecialchars($categoryKey, ENT_QUOTES, 'UTF-8') ?>">
                                <?php if (canUpload()): ?>
                                    <div class="category-actions">
                                        <button class="action-btn add-list-subfolder-btn" onclick="showAddListSubfolderModal('<?= $categoryKey ?>')">
                                            <i class="fas fa-folder-plus"></i> Přidat podsložku
                                        </button>
                                    </div>
                                <?php endif; ?>

                                <!-- Subfolders Grid -->
                                <?php if (!empty($category['data']['folders'])): ?>
                                    <div class="list-subfolders-section">
                                        <h4>Podsložky</h4>
                                        <div class="list-subfolders-grid">
                                            <?php foreach ($category['data']['folders'] as $folderName => $folderData): ?>
                                                <?php renderListSubfolder($folderName, $folderData, $categoryKey, $category['info']); ?>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                <?php endif; ?>

                                <!-- Files in main category -->
                                <?php if (!empty($category['data']['documents'])): ?>
                                    <div class="list-documents-section">
                                        <h4>Položky</h4>
                                        <div class="list-documents-grid">
                                            <?php foreach ($category['data']['documents'] as $item): ?>
                                                <?php renderListCard($item, $categoryKey); ?>
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

        <!-- Add List Section -->
        <?php if (canUpload()): ?>
            <div class="add-list-section">
                <h2>Nahrát položku</h2>
                <form action="upload-category-form-handler.php" method="post" enctype="multipart/form-data" class="list-form">

                    <input type="hidden" name="type" value="seznam">
                    <input type="hidden" name="redirect" value="list">

                    <div class="form-row">
                        <div class="form-group">
                            <label for="custom-folder-name">Vytvořit novou kategorii (volitelné)</label>
                            <input type="text" id="custom-folder-name" name="custom_category" class="full-width"
                                   placeholder="Zadejte název pro novou kategorii..."
                                   onchange="toggleListFolderInputs()"
                                   oninput="toggleListFolderInputs()">
                            <small class="file-help">Pokud zadáte název, vytvoří se nová kategorie s tímto názvem</small>
                        </div>
                    </div>

                    <div class="form-row" id="existing-folder-row">
                        <div class="form-group">
                            <label for="list-category">Nebo vyberte existující kategorii</label>
                            <select id="list-category" name="category" class="full-width" onchange="updateListSubfolderOptions()">
                                <option value="">-- Vyberte kategorii --</option>
                                <?php
                                $existingCategories = getListCategories();
                                foreach ($existingCategories as $category): ?>
                                    <option value="<?= htmlspecialchars($category, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($category, ENT_QUOTES, 'UTF-8') ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="list-subfolder">Podsložka</label>
                            <select id="list-subfolder" name="subfolder" class="full-width">
                                <option value="">-- Hlavní složka --</option>
                            </select>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="list_file">Soubor *</label>
                            <div class="upload-area">
                                <label for="list_file" class="custom-file-button">Vyberte soubor</label>
                                <input type="file" name="file" id="list_file" accept=".txt,.pdf,.doc,.docx,.jpg,.jpeg,.png,.gif" class="hidden-file-input" required>
                                <div id="selected-file" class="selected-files-info"></div>
                            </div>
                        </div>
                    </div>

                    <div class="form-row">
                        <button type="submit" class="upload-btn">
                            <i class="fas fa-upload"></i> Nahrát soubor
                        </button>
                    </div>
                </form>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Add List Subfolder Modal -->
<div id="addListSubfolderModal" class="modal" style="display: none;">
    <div class="modal-content">
        <div class="modal-header">
            <h2>Přidat podsložku</h2>
            <button class="modal-close" onclick="closeAddListSubfolderModal()">&times;</button>
        </div>
        <form id="addListSubfolderForm">
            <input type="hidden" id="listParentCategory" name="parent_category">

            <div class="form-group">
                <label for="listSubfolderName">Název podsložky *</label>
                <input type="text" id="listSubfolderName" name="subfolder_name" required
                    placeholder="Název nové podsložky" class="full-width">
            </div>

            <div class="modal-actions">
                <button type="button" class="btn btn-secondary" onclick="closeAddListSubfolderModal()">Zrušit</button>
                <button type="submit" class="btn btn-primary">Vytvořit podsložku</button>
            </div>
        </form>
    </div>
</div>

<!-- Edit List Subfolder Modal -->
<div id="editListSubfolderModal" class="modal" style="display: none;">
    <div class="modal-content">
        <div class="modal-header">
            <h2>Upravit podsložku</h2>
            <button class="modal-close" onclick="closeEditListSubfolderModal()">&times;</button>
        </div>
        <form id="editListSubfolderForm" enctype="multipart/form-data">
            <input type="hidden" id="editListParentCategory" name="parent_category">
            <input type="hidden" id="editListOriginalName" name="original_name">

            <div class="form-group">
                <label for="editListSubfolderName">Název podsložky *</label>
                <input type="text" id="editListSubfolderName" name="new_name" required
                    placeholder="Nový název podsložky" class="full-width">
            </div>

            <div class="form-group">
                <label for="editListSubfolderFile">Nahrát soubor do podsložky (volitelné)</label>
                <div class="upload-area">
                    <label for="editListSubfolderFile" class="custom-file-button">Vyberte soubor</label>
                    <input type="file" name="subfolder_file" id="editListSubfolderFile" accept=".txt,.pdf,.doc,.docx,.jpg,.jpeg,.png,.gif" class="hidden-file-input">
                    <div id="edit-list-subfolder-selected-file" class="selected-files-info"></div>
                </div>
                <small class="file-help">Podporované formáty: TXT, PDF, DOC, DOCX, JPG, PNG, GIF (max 50MB)</small>
            </div>

            <div class="form-group">
                <label for="editListSubfolderCustomFilename">Přejmenovat soubor (volitelné)</label>
                <input type="text" id="editListSubfolderCustomFilename" name="custom_filename" class="full-width" placeholder="Ponechte prázdné pro původní název">
                <small class="file-help">Zadejte pouze název bez přípony (přípona se doplní automaticky)</small>
            </div>

            <div class="modal-actions">
                <button type="button" class="btn btn-secondary" onclick="closeEditListSubfolderModal()">Zrušit</button>
                <button type="submit" class="btn btn-primary">Uložit změny</button>
            </div>
        </form>
    </div>
</div>

<!-- Edit Category Modal -->
<div id="editListCategoryModal" class="modal" style="display: none;">
    <div class="modal-content">
        <div class="modal-header">
            <h2>Upravit kategorii</h2>
            <button class="modal-close" onclick="closeEditListCategoryModal()">&times;</button>
        </div>
        <form id="editListCategoryForm" enctype="multipart/form-data">
            <input type="hidden" id="editListOriginalName" name="original_name">

            <div class="form-group">
                <label for="editListCategoryName">Název kategorie *</label>
                <input type="text" id="editListCategoryName" name="new_name" required
                    placeholder="Nový název kategorie" class="full-width">
            </div>

            <div class="form-group">
                <label for="editListCategoryFile">Nahrát soubor do kategorie (volitelné)</label>
                <div class="upload-area">
                    <label for="editListCategoryFile" class="custom-file-button">Vyberte soubor</label>
                    <input type="file" name="category_file" id="editListCategoryFile" accept=".txt,.pdf,.doc,.docx,.jpg,.jpeg,.png,.gif" class="hidden-file-input">
                    <div id="edit-list-category-selected-file" class="selected-files-info"></div>
                </div>
                <small class="file-help">Podporované formáty: TXT, PDF, DOC, DOCX, JPG, PNG, GIF (max 50MB)</small>
            </div>

            <div class="form-group">
                <label for="editListCategoryCustomFilename">Přejmenovat soubor (volitelné)</label>
                <input type="text" id="editListCategoryCustomFilename" name="custom_filename" class="full-width" placeholder="Ponechte prázdné pro původní název">
                <small class="file-help">Zadejte pouze název bez přípony (přípona se doplní automaticky)</small>
            </div>

            <div class="modal-actions">
                <button type="button" class="btn btn-secondary" onclick="closeEditListCategoryModal()">Zrušit</button>
                <button type="submit" class="btn btn-primary">Uložit změny</button>
            </div>
        </form>
    </div>
</div>

<!-- Edit List File Modal -->
<div id="editListFileModal" class="modal" style="display: none;">
    <div class="modal-content">
        <div class="modal-header">
            <h2>Přejmenovat soubor</h2>
            <button class="modal-close" onclick="closeEditListFileModal()">&times;</button>
        </div>
        <form id="editListFileForm">
            <input type="hidden" id="editListFileCategory" name="category">
            <input type="hidden" id="editListFileSubfolder" name="subfolder">
            <input type="hidden" id="editListFileOriginalName" name="original_name">

            <div class="form-group">
                <label for="editListFileName">Název souboru *</label>
                <input type="text" id="editListFileName" name="new_name" required
                    placeholder="Nový název souboru (bez přípony)" class="full-width">
                <small class="file-help">Zadejte pouze název bez přípony - přípona zůstane zachována</small>
            </div>

            <div class="modal-actions">
                <button type="button" class="btn btn-secondary" onclick="closeEditListFileModal()">Zrušit</button>
                <button type="submit" class="btn btn-primary">Přejmenovat</button>
            </div>
        </form>
    </div>
</div>

<script>
    // List management JavaScript functions
    let listSubfolderData = {};

    document.addEventListener('DOMContentLoaded', function() {
        // Only run if we're on the list page
        if (!document.getElementById('list')) {
            return;
        }

        // Load subfolder data
        loadListSubfolderData();

        // Initialize form handlers
        initializeListFormHandlers();

        // Handle status messages from URL
        handleStatusMessages();
    });

    // Load List subfolder data - COPIED FROM EDO.PHP EXACT PATTERN
    function loadListSubfolderData() {
        fetch('get-list-subfolders.php')
            .then(response => {
                if (!response.ok) {
                    throw new Error('Network response was not ok');
                }
                return response.text();
            })
            .then(text => {
                try {
                    const data = JSON.parse(text);
                    listSubfolderData = data || {};
                    updateListSubfolderOptions();
                } catch (e) {
                    console.log('No List subfolder data available yet:', e.message);
                    // Set empty object instead of failing
                    listSubfolderData = {};
                }
            })
            .catch(error => {
                console.log('No List subfolder data available yet:', error.message);
                listSubfolderData = {};
            });
    }

    // Update List subfolder dropdown
    function updateListSubfolderOptions() {
        const mainFolder = document.getElementById('list-category');
        const subfolderSelect = document.getElementById('list-subfolder');

        if (!mainFolder || !subfolderSelect) return;

        subfolderSelect.innerHTML = '<option value="">-- Hlavní složka --</option>';

        if (listSubfolderData[mainFolder.value]) {
            listSubfolderData[mainFolder.value].forEach(subfolder => {
                const option = document.createElement('option');
                option.value = subfolder;
                option.textContent = subfolder;
                subfolderSelect.appendChild(option);
            });
        }
    }

    // Simple toggle for list categories
    function toggleListCategoryHeader(categoryKey) {
        if (!document.getElementById('list')) {
            return;
        }

        const categoryCard = document.querySelector(`#list [data-category="${categoryKey}"]`);

        if (!categoryCard) {
            console.warn('Could not find category card for:', categoryKey);
            return;
        }

        if (categoryCard.classList.contains('expanded')) {
            categoryCard.classList.remove('expanded');
        } else {
            document.querySelectorAll('#list .list-category.expanded').forEach(card => {
                card.classList.remove('expanded');
            });
            categoryCard.classList.add('expanded');
        }
    }

    // Toggle List subfolder
    function toggleListSubfolder(subfolderId) {
        if (event) {
            event.stopPropagation();
            event.preventDefault();
        }

        if (!document.getElementById('list')) {
            return;
        }

        const subfolderCard = document.querySelector(`[onclick="toggleListSubfolder('${subfolderId}')"]`);

        if (!subfolderCard) {
            console.warn('Could not find List subfolder card for:', subfolderId);
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

    // Show add List subfolder modal
    function showAddListSubfolderModal(categoryKey) {
        if (event) {
            event.stopPropagation();
            event.preventDefault();
        }

        document.getElementById('listParentCategory').value = categoryKey;
        document.getElementById('addListSubfolderModal').style.display = 'flex';
        document.getElementById('addListSubfolderForm').reset();
        setTimeout(() => document.getElementById('listSubfolderName').focus(), 100);
    }

    function closeAddListSubfolderModal() {
        document.getElementById('addListSubfolderModal').style.display = 'none';
        document.getElementById('addListSubfolderForm').reset();
    }

    // Edit List subfolder
    function editListSubfolder(categoryKey, subfolderName) {
        if (event) {
            event.stopPropagation();
            event.preventDefault();
        }

        document.getElementById('editListParentCategory').value = categoryKey;
        document.getElementById('editListOriginalName').value = subfolderName;
        document.getElementById('editListSubfolderName').value = subfolderName;
        document.getElementById('editListSubfolderModal').style.display = 'flex';
        setTimeout(() => document.getElementById('editListSubfolderName').focus(), 100);
    }

    function closeEditListSubfolderModal() {
        document.getElementById('editListSubfolderModal').style.display = 'none';
        document.getElementById('editListSubfolderForm').reset();
        document.getElementById('edit-list-subfolder-selected-file').textContent = '';
        document.getElementById('editListSubfolderCustomFilename').value = '';
    }

    // Delete List subfolder - EXACT COPY FROM EDO.PHP
    function deleteListSubfolder(categoryKey, subfolderName) {
        if (event) {
            event.stopPropagation();
            event.preventDefault();
        }

        if (confirm(`Opravdu chcete smazat podsložku "${subfolderName}"?\n\nTato akce smaže podsložku a všechny soubory v ní. Toto nelze vrátit zpět!`)) {
            const formData = new FormData();
            // Parametry pro tvůj univerzální handler
            formData.append('type', 'seznam');
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
                            // Pojistka, kdyby selektor selhal
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

    // Delete function - updated to handle subfolders
    function deleteListItem(fileName, category, subfolder) {
        const cleanSubfolder = subfolder || '';

        if (!confirm(`Opravdu chcete smazat položku "${fileName}"?`)) return;

        const formData = new FormData();
        // Identifikace pro univerzální handler - typ složky je 'seznam'
        formData.append('type', 'seznam');
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
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Pokud máš v list.php funkci na status message, použijeme ji
                    if (typeof showStatusMessage === 'function') {
                        showStatusMessage('success', data.message);
                    }

                    // --- BLESKOVÉ ODSTRANĚNÍ Z DOMU ---
                    // Selektor najde prvek se správným jménem, kategorií i podsložkou
                    const selector = `[data-file-name="${fileName}"][data-current-category="${category}"][data-current-subfolder="${cleanSubfolder}"]`;
                    const fileElement = document.querySelector(selector);

                    if (fileElement) {
                        // Sexy animace: prvek se trochu zmenší a vybledne
                        fileElement.style.transition = 'all 0.4s cubic-bezier(0.4, 0, 0.2, 1)';
                        fileElement.style.opacity = '0';
                        fileElement.style.transform = 'scale(0.8)';
                        fileElement.style.pointerEvents = 'none';

                        setTimeout(() => {
                            fileElement.remove();
                        }, 400);
                    } else {
                        // Fallback: pokud JS prvek nenajde, refresh to jistí
                        setTimeout(() => location.reload(), 500);
                    }
                } else {
                    alert('Chyba: ' + (data.message || 'Neznámá chyba'));
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Chyba při komunikaci se serverem.');
            });
    }

    // Edit list file - updated to handle subfolders
    function editListFile(originalName, category, subfolder, currentName) {
        console.log('Opening edit modal for:', originalName, category, subfolder, currentName);

        document.getElementById('editListFileCategory').value = category;
        document.getElementById('editListFileSubfolder').value = subfolder || '';
        document.getElementById('editListFileOriginalName').value = originalName;

        // Remove extension from current name for editing
        const nameWithoutExt = currentName.replace(/\.[^/.]+$/, "");
        document.getElementById('editListFileName').value = nameWithoutExt;

        document.getElementById('editListFileModal').style.display = 'flex';
        setTimeout(() => document.getElementById('editListFileName').focus(), 100);
    }

    function closeEditListFileModal() {
        document.getElementById('editListFileModal').style.display = 'none';
        document.getElementById('editListFileForm').reset();
    }

    // Category management functions - FIXED WITH UNIQUE IDS AND IMPORTANT
    function editListCategory(categoryKey) {
        if (event) {
            event.stopPropagation();
            event.preventDefault();
        }

        console.log('Editing List category:', categoryKey);

        const modal = document.getElementById('editListCategoryModal');
        const originalInput = document.getElementById('editListOriginalName');
        const nameInput = document.getElementById('editListCategoryName');

        if (originalInput) originalInput.value = categoryKey;
        if (nameInput) nameInput.value = categoryKey;
        if (modal) {
            console.log('Showing modal:', modal);
            modal.style.setProperty('display', 'flex', 'important');
            setTimeout(() => {
                if (nameInput) nameInput.focus();
            }, 100);
        } else {
            console.error('Modal not found!');
        }
    }

    function closeEditListCategoryModal() {
        const modal = document.getElementById('editListCategoryModal');
        if (modal) modal.style.setProperty('display', 'none', 'important');

        const form = document.getElementById('editListCategoryForm');
        if (form) form.reset();

        const selectedFileDiv = document.getElementById('edit-list-category-selected-file');
        if (selectedFileDiv) selectedFileDiv.textContent = '';

        const customFilenameInput = document.getElementById('editListCategoryCustomFilename');
        if (customFilenameInput) customFilenameInput.value = '';
    }

    function deleteListCategory(categoryKey) {
        if (event) {
            event.stopPropagation();
            event.preventDefault();
        }

        if (!confirm(`Smazat kategorii "${categoryKey}" a všechny soubory?`)) return;

        const formData = new FormData();
        formData.append('category', categoryKey);
        formData.append('type', 'seznam');

        fetch('delete-category-handler.php', {
                method: 'POST',
                body: formData
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error('Network response was not ok');
                }
                return response.text();
            })
            .then(text => {
                try {
                    const data = JSON.parse(text);
                    if (data && data.success) {
                        showStatusMessage('success', data.message);
                        setTimeout(() => location.reload(), 500);
                    } else {
                        showStatusMessage('error', data.message || 'Neznámá chyba');
                    }
                } catch (e) {
                    console.error('Invalid JSON response:', text);
                    showStatusMessage('error', 'Chyba při zpracování odpovědi serveru');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showStatusMessage('error', 'Chyba při mazání kategorie');
            });
    }

    // Add missing status message function - EXACT COPY FROM EDO.PHP
    function showStatusMessage(type, message) {
        const container = document.getElementById('status-messages');
        if (!container) return;

        const div = document.createElement('div');
        div.className = `status-message status-${type}`;
        div.innerHTML = `<i class="fas ${type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle'}"></i> ${message}`;

        container.appendChild(div);
        setTimeout(() => div.remove(), 500);
    }

    // Form handling - FIXED WITH UNIQUE IDS
    function initializeListFormHandlers() {
        const fileInput = document.getElementById('list_file');
        const selectedFile = document.getElementById('selected-file');

        if (fileInput && selectedFile) {
            fileInput.addEventListener('change', function() {
                if (this.files && this.files.length > 0) {
                    const file = this.files[0];
                    selectedFile.textContent = `Vybraný soubor: ${file.name}`;
                } else {
                    selectedFile.textContent = 'Žádný soubor nebyl vybrán';
                }
            });
        }

        // Add file input handler for edit category modal - FIXED IDS
        const editCategoryFileInput = document.getElementById('editListCategoryFile');
        const editCategorySelectedFile = document.getElementById('edit-list-category-selected-file');

        if (editCategoryFileInput && editCategorySelectedFile) {
            editCategoryFileInput.addEventListener('change', function() {
                if (this.files && this.files.length > 0) {
                    const file = this.files[0];
                    const fileSize = formatFileSize(file.size);
                    editCategorySelectedFile.textContent = `Vybraný soubor: ${file.name} (${fileSize})`;

                    const customFilenameInput = document.getElementById('editListCategoryCustomFilename');
                    if (customFilenameInput && !customFilenameInput.value.trim()) {
                        const nameWithoutExt = file.name.replace(/\.[^/.]+$/, "");
                        customFilenameInput.value = nameWithoutExt;
                    }
                } else {
                    editCategorySelectedFile.textContent = 'Žádný soubor nebyl vybrán';
                }
            });
        }

        // Edit category form - UNIVERZÁLNÍ ÚPRAVA
        const editCategoryForm = document.getElementById('editListCategoryForm');
        if (editCategoryForm) {
            editCategoryForm.addEventListener('submit', function(e) {
                e.preventDefault();

                const originalName = document.getElementById('editListOriginalName').value;
                const newName = document.getElementById('editListCategoryName').value;

                if (!originalName || !newName) {
                    alert('Chybí povinné údaje');
                    return;
                }

                const formData = new FormData(this);
                // Tady to sjednotíme s ostatními stránkami
                formData.set('original_name', originalName);
                formData.set('new_name', newName);

                // PŘIDÁNO: Identifikátor složky pro univerzální handler
                formData.append('type', 'seznam');

                const submitBtn = this.querySelector('button[type="submit"]');
                const originalText = submitBtn.innerHTML;
                submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Ukládá se...';
                submitBtn.disabled = true;

                // ZMĚNĚNO: Míříme na tvůj univerzální soubor
                fetch('edit-category-handler.php', {
                    method: 'POST',
                    body: formData
                })
                    .then(response => {
                        if (!response.ok) {
                            throw new Error('Network response was not ok');
                        }
                        return response.text();
                    })
                    .then(text => {
                        try {
                            const data = JSON.parse(text);
                            if (data && data.success) {
                                closeEditListCategoryModal();
                                showStatusMessage('success', data.message);
                                setTimeout(() => location.reload(), 500);
                            } else {
                                showStatusMessage('error', data.message || 'Neznámá chyba');
                            }
                        } catch (e) {
                            console.error('Invalid JSON response:', text);
                            showStatusMessage('error', 'Chyba při zpracování odpovědi serveru');
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

        // Handle List subfolder form submission
        const listSubfolderForm = document.getElementById('addListSubfolderForm');
        if (listSubfolderForm) {
            listSubfolderForm.addEventListener('submit', function(e) {
                e.preventDefault();

                const formData = new FormData(this);

                fetch('add-list-subfolder.php', {
                        method: 'POST',
                        body: formData
                    })
                    .then(response => {
                        if (!response.ok) {
                            throw new Error('Network response was not ok');
                        }
                        return response.text();
                    })
                    .then(text => {
                        try {
                            const data = JSON.parse(text);
                            if (data && data.success) {
                                closeAddListSubfolderModal();
                                showStatusMessage('success', data.message);
                                setTimeout(() => location.reload(), 500);
                            } else {
                                showStatusMessage('error', data.message || 'Unknown error occurred');
                            }
                        } catch (e) {
                            console.error('Invalid JSON response:', text);
                            showStatusMessage('error', 'Server returned invalid response. Check console for details.');
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        showStatusMessage('error', 'Došlo k chybě při vytváření podsložky.');
                    });
            });
        }

        // Handle edit List subfolder form submission
        const editListSubfolderForm = document.getElementById('editListSubfolderForm');
        if (editListSubfolderForm) {
            editListSubfolderForm.addEventListener('submit', function(e) {
                e.preventDefault();

                const formData = new FormData(this);
                // 1. Identifikace pro univerzální handler
                formData.append('type', 'seznam');

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
                            if (typeof closeEditListSubfolderModal === 'function') closeEditListSubfolderModal();
                            showStatusMessage('success', data.message);
                            setTimeout(() => location.reload(), 500);
                        } else {
                            showStatusMessage('error', data.message);
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

        // Edit list file form
        // Handler pro přejmenování položky v Seznamu
        const editListFileForm = document.getElementById('editListFileForm');
        if (editListFileForm) {
            editListFileForm.addEventListener('submit', function(e) {
                e.preventDefault();

                // 1. Vytáhneme hodnoty z modalu
                const category = document.getElementById('editListFileCategory').value;
                const subfolder = document.getElementById('editListFileSubfolder').value;
                const originalName = document.getElementById('editListFileOriginalName').value;
                const newName = document.getElementById('editListFileName').value;

                if (!category || !originalName || !newName) {
                    alert('Všechna pole jsou povinná.');
                    return;
                }

                const formData = new FormData();
                // 2. KLÍČOVÁ ZMĚNA: Používáme univerzální identifikátor 'seznam'
                formData.append('type', 'seznam');
                formData.append('category', category);
                formData.append('subfolder', subfolder);
                formData.append('original_name', originalName);
                formData.append('new_name', newName);

                const submitBtn = this.querySelector('button[type="submit"]');
                const originalText = submitBtn.innerHTML;
                submitBtn.disabled = true;
                submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Ukládání...';

                // 3. Míříme na společný edit-file-handler.php
                fetch('edit-file-handler.php', {
                    method: 'POST',
                    body: formData
                })
                    .then(res => res.json())
                    .then(data => {
                        if (data.success) {
                            closeEditListFileModal(); // Zavřeme modal
                            showStatusMessage('success', data.message);

                            // Refresh po smazání/přejmenování je v list.php nutný pro správné cesty v HTML
                            setTimeout(() => location.reload(), 600);
                        } else {
                            alert(data.message);
                            submitBtn.disabled = false;
                            submitBtn.innerHTML = originalText;
                        }
                    })
                    .catch(err => {
                        console.error('Error:', err);
                        alert('Chyba při komunikaci se serverem.');
                        submitBtn.disabled = false;
                        submitBtn.innerHTML = originalText;
                    });
            });
        }
    }

    function formatFileSize(bytes) {
        if (!bytes || bytes === 0) return '0 B';
        const k = 1024;
        const sizes = ['B', 'KB', 'MB', 'GB'];
        const i = Math.floor(Math.log(bytes) / Math.log(k));
        return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
    }

    // Toggle folder inputs
    function toggleListFolderInputs() {
        const customInput = document.getElementById('custom-folder-name');
        const existingRow = document.getElementById('existing-folder-row');
        const categorySelect = document.getElementById('list-category');

        if (!customInput || !existingRow || !categorySelect) return;

        if (customInput.value.trim()) {
            existingRow.style.opacity = '0.5';
            categorySelect.disabled = true;
            categorySelect.value = '';
        } else {
            existingRow.style.opacity = '1';
            categorySelect.disabled = false;
        }
    }

    // Status messages
    function handleStatusMessages() {
        const urlParams = new URLSearchParams(window.location.search);
        const status = urlParams.get('status');
        const message = urlParams.get('message');

        if (status && message) {
            showStatusMessage(status, decodeURIComponent(message));

            const cleanUrl = window.location.pathname + '#list';
            window.history.replaceState({}, document.title, cleanUrl);
        }
    }

    // Expose functions globally with updated function names
    if (document.getElementById('list')) {
        window.deleteListItem = deleteListItem;
        window.toggleListCategoryHeader = toggleListCategoryHeader;
        window.editListCategory = editListCategory;
        window.closeEditCategoryModal = closeEditListCategoryModal;
        window.closeEditListCategoryModal = closeEditListCategoryModal;
        window.deleteListCategory = deleteListCategory;
        window.toggleListFolderInputs = toggleListFolderInputs;
        window.editListFile = editListFile;
        window.closeEditListFileModal = closeEditListFileModal;
        window.showAddListSubfolderModal = showAddListSubfolderModal;
        window.closeAddListSubfolderModal = closeAddListSubfolderModal;
        window.editListSubfolder = editListSubfolder;
        window.closeEditListSubfolderModal = closeEditListSubfolderModal;
        window.toggleListSubfolder = toggleListSubfolder;
    }

    // Modern Drag and Drop functionality for List files
    let draggedListElement = null;
    let draggedListFileData = null;

    // Check if File System Access API is available
    const supportsListFileSystemAccess = 'getAsFileSystemHandle' in DataTransferItem.prototype;

    function handleListDragStart(event) {
        console.log("🚀 List drag started with File System Access API support:", supportsListFileSystemAccess);
        
        draggedListElement = event.target;
        draggedListElement.classList.add('dragging');
        
        // Get file information from data attributes
        draggedListFileData = {
            fileName: event.target.getAttribute('data-file-name'),
            currentCategory: event.target.getAttribute('data-current-category'),
            currentSubfolder: event.target.getAttribute('data-current-subfolder') || ''
        };
        
        console.log("📁 Dragged List file data:", draggedListFileData);
        
        // Set up modern drag data transfer
        event.dataTransfer.effectAllowed = 'move';
        
        // Set traditional data for fallback
        event.dataTransfer.setData('text/plain', JSON.stringify(draggedListFileData));
        
        // If File System Access API is available, set up additional data
        if (supportsListFileSystemAccess) {
            try {
                // Create a virtual file handle for the drag operation
                const fileData = new Blob([JSON.stringify(draggedListFileData)], { type: 'application/json' });
                const file = new File([fileData], 'list-file-move-data.json', { type: 'application/json' });
                
                // Add the file to the data transfer
                event.dataTransfer.items.add(file);
                console.log("✅ Enhanced List drag data set with File System Access API");
            } catch (error) {
                console.warn("⚠️ Could not set enhanced List drag data:", error);
            }
        }
    }

    function handleListDragEnd(event) {
        console.log("🏁 List drag ended");
        
        if (draggedListElement) {
            draggedListElement.classList.remove('dragging');
        }
        
        // Clean up all drop zones
        document.querySelectorAll('#list .droppable-zone').forEach(zone => {
            zone.classList.remove('drag-over');
        });
        
        draggedListElement = null;
        draggedListFileData = null;
    }

    function handleListDragOver(event) {
        event.preventDefault();
        event.dataTransfer.dropEffect = 'move';
    }

    function handleListDragEnter(event) {
        event.preventDefault();
        event.stopPropagation();
        
        if (!draggedListFileData) return;
        
        const dropZone = event.currentTarget;
        const targetCategory = dropZone.getAttribute('data-category');
        const targetSubfolder = dropZone.getAttribute('data-subfolder') || '';
        
        console.log("⬆️ List drag enter zone:", { targetCategory, targetSubfolder });
        
        // Don't highlight if same location
        if (targetCategory === draggedListFileData.currentCategory && 
            targetSubfolder === draggedListFileData.currentSubfolder) {
            console.log("❌ Same location, no highlight");
            return;
        }
        
        dropZone.classList.add('drag-over');
        console.log("✅ Highlighted List drop zone");
    }

    function handleListDragLeave(event) {
        event.preventDefault();
        event.stopPropagation();
        
        const dropZone = event.currentTarget;
        const rect = dropZone.getBoundingClientRect();
        const x = event.clientX;
        const y = event.clientY;
        
        // Only remove drag-over if mouse is completely outside the drop zone
        if (x < rect.left || x > rect.right || y < rect.top || y > rect.bottom) {
            dropZone.classList.remove('drag-over');
        }
    }

    async function handleListDrop(event) {
        console.log("📥 List drop detected with File System Access API support:", supportsListFileSystemAccess);
        event.preventDefault();
        event.stopPropagation();
        
        const dropZone = event.currentTarget;
        const targetCategory = dropZone.getAttribute('data-category');
        const targetSubfolder = dropZone.getAttribute('data-subfolder') || '';
        
        dropZone.classList.remove('drag-over');
        
        let fileData = null;
        
        // Try to get data using File System Access API first
        if (supportsListFileSystemAccess && event.dataTransfer.items.length > 0) {
            console.log("🔧 Attempting to use File System Access API for List");
            
            for (let i = 0; i < event.dataTransfer.items.length; i++) {
                const item = event.dataTransfer.items[i];
                
                try {
                    if (item.kind === 'file') {
                        // Try to get file system handle
                        const handle = await item.getAsFileSystemHandle();
                        console.log("📂 Got List file system handle:", handle);
                        
                        if (handle && handle.kind === 'file') {
                            // Extract file info from the handle
                            fileData = {
                                fileName: handle.name,
                                // We still need the category/subfolder info from our drag data
                                currentCategory: draggedListFileData?.currentCategory,
                                currentSubfolder: draggedListFileData?.currentSubfolder
                            };
                            
                            console.log("✅ Enhanced List file data from File System Access API:", fileData);
                            break;
                        }
                    }
                } catch (error) {
                    console.warn("⚠️ List File System Access API failed for item:", error);
                    // Continue to try other items or fall back
                }
            }
        }
        
        // Fall back to traditional method if File System Access API didn't work
        if (!fileData && draggedListFileData) {
            console.log("🔄 Falling back to traditional List drag data");
            fileData = draggedListFileData;
        }
        
        // Also try to get data from data transfer as fallback
        if (!fileData) {
            try {
                const transferData = event.dataTransfer.getData('text/plain');
                if (transferData) {
                    fileData = JSON.parse(transferData);
                    console.log("🔄 Got List data from text transfer:", fileData);
                }
            } catch (error) {
                console.error("❌ Could not parse List transfer data:", error);
            }
        }
        
        if (!fileData) {
            console.error("❌ No List file data available for drop");
            showStatusMessage('error', 'Nepodařilo se získat informace o souboru');
            return;
        }
        
        console.log("🎯 Final List drop target:", { targetCategory, targetSubfolder });
        console.log("📁 Source List file data:", fileData);
        
        // Check if same location
        if (targetCategory === fileData.currentCategory && 
            targetSubfolder === fileData.currentSubfolder) {
            console.log("❌ Same location, ignoring List drop");
            return;
        }
        
        // Perform the move
        await moveListFile(fileData, targetCategory, targetSubfolder);
    }

    async function moveListFile(fileData, targetCategory, targetSubfolder) {
        console.log("🚚 Moving List file:", fileData.fileName);
        showStatusMessage('info', 'Přesouvám soubor...');

        const formData = new FormData();
        formData.append('fileName', fileData.fileName);
        formData.append('sourceCategory', fileData.currentCategory);
        formData.append('sourceSubfolder', fileData.currentSubfolder);
        formData.append('targetCategory', targetCategory);
        formData.append('targetSubfolder', targetSubfolder);
        formData.append('move_list_file', '1');

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
</script>