<!--Page smlouvy - slozkovy system -->
<?php
// BUFFER OUTPUT IMMEDIATELY TO FIX "HEADERS ALREADY SENT"
ob_start();

// Set proper encoding for Czech characters  
mb_internal_encoding('UTF-8');

require_once 'access.php';

// Handle file move request
if (isset($_POST['move_agreement_file']) && isAdmin()) {
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
        $agreementBaseDir = getRoot('agreements');
        $sourcePath = $agreementBaseDir . $sourceCategory . '/' . ($sourceSubfolder ? $sourceSubfolder . '/' : '');
        $targetPath = $agreementBaseDir . $targetCategory . '/' . ($targetSubfolder ? $targetSubfolder . '/' : '');
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
function getAgreementFileTypeInfo($extension)
{
    $extension = strtolower($extension);

    switch ($extension) {
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
        case 'txt':
            return [
                'type' => 'text',
                'icon' => 'fas fa-file-alt',
                'color' => '#007bff',
                'viewable' => true
            ];
        case 'xls':
        case 'xlsx':
            return [
                'type' => 'spreadsheet',
                'icon' => 'fas fa-file-excel',
                'color' => '#2ecc71',
                'viewable' => false
            ];
        case 'ppt':
        case 'pptx':
            return [
                'type' => 'presentation',
                'icon' => 'fas fa-file-powerpoint',
                'color' => '#e67e22',
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
function getAgreementCategories()
{
    $agreementDir = getRoot('agreements');
    $categories = [];

    if (is_dir($agreementDir)) {
        $items = array_diff(scandir($agreementDir), ['.', '..']);

        foreach ($items as $item) {
            $itemPath = $agreementDir . $item;
            if (is_dir($itemPath)) {
                $categories[] = $item;
            }
        }
    }

    return $categories;
}

// Function to scan for subfolders and files
function scanAgreementsRecursive($dir, $categoryKey, $depth = 0)
{
    $result = ['folders' => [], 'documents' => []];

    if (!is_dir($dir)) return $result;

    $items = array_diff(scandir($dir), ['.', '..']);

    foreach ($items as $item) {
        $itemPath = $dir . $item;

        if (is_dir($itemPath)) {
            // It's a subfolder
            $subResult = scanAgreementsRecursive($itemPath . '/', $categoryKey, $depth + 1);
            $result['folders'][$item] = [
                'name' => $item,
                'path' => $itemPath,
                'data' => $subResult,
                'depth' => $depth
            ];
        } else {
            // It's a file - add it directly
            $extension = strtolower(pathinfo($item, PATHINFO_EXTENSION));

            // Include document files
            $allowedExtensions = ['pdf', 'doc', 'docx', 'txt', 'xls', 'xlsx', 'ppt', 'pptx', 'jpg', 'jpeg', 'png', 'gif'];

            if (in_array($extension, $allowedExtensions)) {
                $fileTypeInfo = getAgreementFileTypeInfo($extension);

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
if (!function_exists('countAllAgreementDocuments')) {
    function countAllAgreementDocuments($categoryData)
    {
        $count = count($categoryData['documents']); // Count documents in main category

        // Add documents from all subfolders
        if (!empty($categoryData['folders'])) {
            foreach ($categoryData['folders'] as $folder) {
                $count += countAllAgreementDocuments($folder['data']);
            }
        }

        return $count;
    }
}

// Function to render agreement subfolder
function renderAgreementSubfolder($folderName, $folderData, $categoryKey, $categoryInfo)
{
?>
    <div class="agreement-subfolder-card droppable-zone"
        data-category="<?= htmlspecialchars($categoryKey) ?>"
        data-subfolder="<?= htmlspecialchars($folderName) ?>"
        onclick="toggleAgreementSubfolder('<?= $categoryKey ?>-<?= $folderName ?>')"
        ondrop="handleAgreementDrop(event)"
        ondragover="handleAgreementDragOver(event)"
        ondragenter="handleAgreementDragEnter(event)"
        ondragleave="handleAgreementDragLeave(event)">

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
                    <button class="mini-btn edit-btn" onclick="event.stopPropagation(); editAgreementSubfolder('<?= $categoryKey ?>', '<?= $folderName ?>')" title="Upravit podsložku">
                        <i class="fas fa-edit"></i>
                    </button>
                    <button class="mini-btn delete-btn" onclick="event.stopPropagation(); deleteAgreementSubfolder('<?= $categoryKey ?>', '<?= $folderName ?>')" title="Smazat podsložku">
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
                            ondragstart="handleAgreementDragStart(event)"
                            ondragend="handleAgreementDragEnd(event)">
                            <div class="doc-icon">
                                <i class="<?= $item['file_type_info']['icon'] ?>" style="color: <?= $item['file_type_info']['color'] ?>;"></i>
                                <i class="fas fa-arrows-alt drag-handle"></i>
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
                                    <button class="mini-btn edit-btn" onclick="event.stopPropagation(); editAgreementFile('<?= addslashes($item['original_name']) ?>', '<?= $categoryKey ?>', '<?= addslashes($folderName) ?>', '<?= addslashes($item['name']) ?>')" title="Přejmenovat">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <button class="mini-btn delete-btn" onclick="event.stopPropagation(); deleteAgreementItem('<?= addslashes($item['original_name']) ?>', '<?= $categoryKey ?>', '<?= addslashes($folderName) ?>')" title="Smazat">
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

// Function to render AGREEMENT card
function renderAgreementCard($item, $categoryKey)
{
?>
    <div class="agreement-card-grid draggable-file"
        draggable="true"
        data-file-name="<?= htmlspecialchars($item['original_name']) ?>"
        data-current-category="<?= htmlspecialchars($categoryKey) ?>"
        data-current-subfolder=""
        ondragstart="handleAgreementDragStart(event)"
        ondragend="handleAgreementDragEnd(event)">
        <div class="agreement-preview">
            <i class="fas fa-arrows-alt drag-handle"></i>
            <div class="file-preview-icon">
                <i class="<?= $item['file_type_info']['icon'] ?>" style="color: <?= $item['file_type_info']['color'] ?>; font-size: 32px;"></i>
            </div>
        </div>

        <div class="agreement-info-grid">
            <h5 class="agreement-title"><?= htmlspecialchars($item['name']) ?></h5>

            <div class="agreement-meta-grid">
                <span class="agreement-date">
                    <i class="fas fa-calendar"></i>
                    <?= date('d.m.Y', $item['date_added']) ?>
                </span>
                <span class="agreement-size">
                    <i class="fas fa-file"></i>
                    <?= formatBytes($item['file_size']) ?>
                </span>
            </div>

            <div class="agreement-actions-grid">
                <?php if ($item['file_type_info']['viewable']): ?>
                    <a href="<?= htmlspecialchars($item['file_path']) ?>" target="_blank" class="grid-btn view-btn">
                        <i class="fas fa-eye"></i>
                    </a>
                <?php endif; ?>

                <a href="<?= htmlspecialchars($item['file_path']) ?>" download class="grid-btn download-btn">
                    <i class="fas fa-download"></i>
                </a>

                <?php if (isAdmin()): ?>
                    <button class="grid-btn edit-btn" onclick="editAgreementFile('<?= addslashes($item['original_name']) ?>', '<?= $categoryKey ?>', '', '<?= addslashes($item['name']) ?>')">
                        <i class="fas fa-edit"></i>
                    </button>
                    <button class="grid-btn delete-btn" onclick="deleteAgreementItem('<?= addslashes($item['original_name']) ?>', '<?= $categoryKey ?>', '')">
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
    /* Agreement page styling - adapted from other pages */
    #agreements .content-area {
        display: flex;
        flex-direction: column;
        gap: 30px;
    }
    .agreement-grid-container {
        height: auto !important;
        min-height: 0 !important;
        flex-grow: 0; /* Zabrání natahování ve flexu */
        align-self: flex-start; /* Zarovná se na začátek a nebude se natahovat na celou výšku */
        width: 100%;
    }
    .agreement-category {
        background: white;
        border-radius: 10px;
        box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        overflow: hidden;
        transition: all 0.3s ease;
        position: relative;
    }

    .agreement-category.expanded {
        grid-column: 1 / -1;
        max-width: none;
        transform: none !important;
        z-index: 10;
    }

    .agreement-category.expanded .category-content {
        max-height: none !important;
        padding: 20px !important;
        display: block !important;
        opacity: 1 !important;
        overflow: visible !important;
    }

    .agreement-category.expanded .category-toggle i {
        transform: rotate(180deg);
    }

    .agreement-category:hover:not(.expanded) {
        box-shadow: 0 5px 15px rgba(0, 0, 0, 0.15);
    }

    .agreement-category:not(.expanded) .category-content {
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

    .file-count {
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

    /* Subfolder styling */
    .agreement-subfolders-section {
        margin-bottom: 25px;
    }

    .agreement-subfolders-section h4 {
        color: #495057;
        font-size: 14px;
        font-weight: 600;
        margin: 0 0 12px 0;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    .agreement-subfolders-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
        gap: 12px;
    }

    .agreement-subfolder-card {
        background: #f8f9fa;
        border: 1px solid #e9ecef;
        border-radius: 8px;
        padding: 15px;
        cursor: pointer;
        transition: all 0.2s;
        position: relative;
    }

    .agreement-subfolder-card:hover {
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
    .agreement-subfolder-card.open {
        grid-column: 1 / -1;
        width: 100%;
        max-width: none;
        background: #fff;
        border-color: #dee2e6;
    }

    .agreement-subfolder-card.open .subfolder-content {
        max-height: 1000px;
        margin-top: 12px;
        padding-top: 12px;
        border-top: 1px solid #dee2e6;
    }

    .agreement-subfolder-card.open .subfolder-toggle {
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
    .agreement-documents-section {
        margin-top: 0 !important;
        padding-top: 0 !important;
    }

    .agreement-documents-section h4 {
        color: #495057;
        font-size: 14px;
        font-weight: 600;
        margin: 0 0 12px 0;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    .agreement-documents-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
        gap: 15px;
    }

    .agreement-card-grid {
        background: white;
        border: 1px solid #e9ecef;
        border-radius: 8px;
        overflow: hidden;
        transition: all 0.2s;
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
        opacity: 1 !important;
        display: block !important;
    }

    .agreement-card-grid:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
        border-color: #dee2e6;
    }

    .agreement-preview {
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

    .agreement-info-grid {
        padding: 15px;
        display: flex;
        flex-direction: column;
        gap: 8px;
    }

    .agreement-title {
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

    .agreement-meta-grid {
        display: flex;
        justify-content: space-between;
        align-items: center;
        font-size: 11px;
        color: #6c757d;
        margin-top: auto;
        flex-wrap: wrap;
        gap: 8px;
    }

    .agreement-date,
    .agreement-size {
        display: flex;
        align-items: center;
        gap: 4px;
    }

    .agreement-actions-grid {
        display: flex;
        gap: 6px;
        justify-content: flex-end;
        margin-top: 8px;
        padding-top: 8px;
        border-top: 1px solid #f0f0f0;
    }

    /* Additional modal form styles */
    .form-group {
        flex: 1;
        min-width: 200px;
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

    /* Add missing styles for admin buttons and upload section */
    .add-agreement-section {
        background: white;
        border-radius: 10px;
        padding: 25px;
        margin-bottom: 25px;
        box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
    }

    .add-agreement-section h2 {
        color: #2c3e50;
        margin-top: 0;
        margin-bottom: 20px;
        font-size: 20px;
    }

    .agreement-form {
        display: flex;
        flex-direction: column;
        gap: 15px;
    }

    .form-row {
        display: flex;
        gap: 15px;
        flex-wrap: wrap;
    }

    .category-actions {
        margin-bottom: 20px;
        display: flex;
        gap: 10px;
    }

    .add-agreement-subfolder-btn {
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

    .add-agreement-subfolder-btn:hover {
        background: #138496;
    }

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

    /* Drag and Drop Styles */
    .agreement-card-grid.dragging,
    .subfolder-document.dragging {
        opacity: 0.5;
        transform: scale(0.95);
        cursor: grabbing;
    }

    .agreement-subfolder-card.drag-over {
        background: #e3f2fd !important;
        border-color: #2196f3 !important;
        box-shadow: 0 0 15px rgba(33, 150, 243, 0.3) !important;
    }

    .agreement-category.drag-over {
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

    .agreement-subfolder-card.drag-over .drop-zone-indicator,
    .agreement-category.drag-over .drop-zone-indicator {
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
</style>

<div class="page-content" id="agreements">
    <div class="content-area">
        <!-- Status Messages -->
        <div id="status-messages"></div>

        <!-- Agreement Grid Container -->
        <div class="agreement-grid-container">
            <?php
            $agreementDir = getRoot('agreements');
            $agreementsByCategory = [];

            // Scan for files and subfolders
            if (is_dir($agreementDir)) {
                $categories = getAgreementCategories();

                foreach ($categories as $categoryKey) {
                    $categoryDir = $agreementDir . $categoryKey . '/';
                    if (is_dir($categoryDir)) {
                        $categoryData = scanAgreementsRecursive($categoryDir, $categoryKey);
                        if (!empty($categoryData['documents']) || !empty($categoryData['folders'])) {
                            $agreementsByCategory[$categoryKey] = [
                                'info' => ['name' => $categoryKey, 'color' => '#e74c3c'],
                                'data' => $categoryData
                            ];
                        } else if (isAdmin()) {
                            // Show empty categories to admins
                            $agreementsByCategory[$categoryKey] = [
                                'info' => ['name' => $categoryKey, 'color' => '#e74c3c'],
                                'data' => $categoryData
                            ];
                        }
                    }
                }
            }

            if (empty($agreementsByCategory)): ?>
                <div class="no-agreements">
                    <i class="fas fa-file-contract"></i>
                    <p>Zatím nejsou žádné smlouvy v systému.</p>
                </div>
            <?php else: ?>
                <div class="categories-grid">
                    <?php foreach ($agreementsByCategory as $categoryKey => $category): ?>
                        <div class="agreement-category droppable-zone"
                            data-category="<?= htmlspecialchars($categoryKey, ENT_QUOTES, 'UTF-8') ?>"
                            style="border-top: 4px solid <?= $category['info']['color'] ?>"
                            ondrop="handleAgreementDrop(event)"
                            ondragover="handleAgreementDragOver(event)"
                            ondragenter="handleAgreementDragEnter(event)"
                            ondragleave="handleAgreementDragLeave(event)">

                            <div class="drop-zone-indicator">
                                <i class="fas fa-file-import"></i> &nbsp; Přetáhněte soubor do hlavní složky
                            </div>

                            <div class="category-header category-header-clickable" onclick="toggleAgreementCategoryHeader('<?= htmlspecialchars($categoryKey, ENT_QUOTES, 'UTF-8') ?>')">
                                <div class="category-info">
                                    <h3 style="color: <?= $category['info']['color'] ?>">
                                        <i class="fas fa-folder"></i>
                                        <?= htmlspecialchars($category['info']['name'], ENT_QUOTES, 'UTF-8') ?>
                                    </h3>
                                    <span class="agreement-count">
                                        <?= countAllAgreementDocuments($category['data']) ?> smluv
                                        <?php if (!empty($category['data']['folders'])): ?>
                                            • <?= count($category['data']['folders']) ?> podsložek
                                        <?php endif; ?>
                                    </span>
                                </div>

                                <?php if (isAdmin()): ?>
                                    <div class="category-admin-actions" onclick="event.stopPropagation()">
                                        <button class="admin-btn edit-btn" onclick="editAgreementCategory('<?= htmlspecialchars($categoryKey, ENT_QUOTES, 'UTF-8') ?>')" title="Přejmenovat kategorii">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <button class="admin-btn delete-btn" onclick="deleteAgreementCategory('<?= htmlspecialchars($categoryKey, ENT_QUOTES, 'UTF-8') ?>')" title="Smazat kategorii">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </div>
                                <?php endif; ?>

                                <div class="category-toggle">
                                    <i class="fas fa-chevron-down"></i>
                                </div>
                            </div>

                            <div class="category-content" id="agreement-category-content-<?= htmlspecialchars($categoryKey, ENT_QUOTES, 'UTF-8') ?>">
                                <?php if (canUpload()): ?>
                                    <div class="category-actions">
                                        <button class="action-btn add-agreement-subfolder-btn" onclick="showAddAgreementSubfolderModal('<?= $categoryKey ?>')">
                                            <i class="fas fa-folder-plus"></i> Přidat podsložku
                                        </button>
                                    </div>
                                <?php endif; ?>

                                <!-- Subfolders Grid -->
                                <?php if (!empty($category['data']['folders'])): ?>
                                    <div class="agreement-subfolders-section">
                                        <h4>Podsložky</h4>
                                        <div class="agreement-subfolders-grid">
                                            <?php foreach ($category['data']['folders'] as $folderName => $folderData): ?>
                                                <?php renderAgreementSubfolder($folderName, $folderData, $categoryKey, $category['info']); ?>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                <?php endif; ?>

                                <!-- Files in main category -->
                                <?php if (!empty($category['data']['documents'])): ?>
                                    <div class="agreement-documents-section">
                                        <h4>Smlouvy</h4>
                                        <div class="agreement-documents-grid">
                                            <?php foreach ($category['data']['documents'] as $item): ?>
                                                <?php renderAgreementCard($item, $categoryKey); ?>
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

        <!-- Add Agreement Section -->
        <?php if (canUpload()): ?>
            <div class="add-agreement-section">
                <h2>Nahrát smlouvu</h2>
                <form action="upload-category-form-handler.php" method="post" enctype="multipart/form-data" class="agreement-form">

                    <input type="hidden" name="type" value="agreements">
                    <input type="hidden" name="redirect" value="agreements">

                    <div class="form-row">
                        <div class="form-group">
                            <label for="custom-agreement-folder-name">Vytvořit novou kategorii (volitelné)</label>
                            <input type="text" id="custom-agreement-folder-name" name="custom_category" class="full-width"
                                   placeholder="Zadejte název pro novou kategorii..."
                                   onchange="toggleAgreementFolderInputs()"
                                   oninput="toggleAgreementFolderInputs()">
                            <small class="file-help">Pokud zadáte název, vytvoří se nová kategorie s tímto názvem</small>
                        </div>
                    </div>

                    <div class="form-row" id="existing-agreement-folder-row">
                        <div class="form-group">
                            <label for="agreement-category">Nebo vyberte existující kategorii</label>
                            <select id="agreement-category" name="category" class="full-width" onchange="updateAgreementSubfolderOptions()">
                                <option value="">-- Vyberte kategorii --</option>
                                <?php
                                $existingCategories = getAgreementCategories();
                                foreach ($existingCategories as $category): ?>
                                    <option value="<?= htmlspecialchars($category, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($category, ENT_QUOTES, 'UTF-8') ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="agreement-subfolder">Podsložka</label>
                            <select id="agreement-subfolder" name="subfolder" class="full-width">
                                <option value="">-- Hlavní složka --</option>
                            </select>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="agreement_file">Soubor *</label>
                            <div class="upload-area">
                                <label for="agreement_file" class="custom-file-button">Vyberte soubor</label>
                                <input type="file" name="file" id="agreement_file" accept=".pdf,.doc,.docx,.txt,.xls,.xlsx,.ppt,.pptx,.jpg,.jpeg,.png,.gif" class="hidden-file-input" required>
                                <div id="selected-agreement-file" class="selected-files-info"></div>
                            </div>
                        </div>
                    </div>

                    <div class="form-row">
                        <button type="submit" class="upload-btn">
                            <i class="fas fa-upload"></i> Nahrát smlouvu
                        </button>
                    </div>
                </form>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Add Agreement Subfolder Modal -->
<div id="addAgreementSubfolderModal" class="modal" style="display: none;">
    <div class="modal-content">
        <div class="modal-header">
            <h2>Přidat podsložku</h2>
            <button class="modal-close" onclick="closeAddAgreementSubfolderModal()">&times;</button>
        </div>
        <form id="addAgreementSubfolderForm">
            <input type="hidden" id="agreementParentCategory" name="parent_category">

            <div class="form-group">
                <label for="agreementSubfolderName">Název podsložky *</label>
                <input type="text" id="agreementSubfolderName" name="subfolder_name" required
                    placeholder="Název nové podsložky" class="full-width">
            </div>

            <div class="modal-actions">
                <button type="button" class="btn btn-secondary" onclick="closeAddAgreementSubfolderModal()">Zrušit</button>
                <button type="submit" class="btn btn-primary">Vytvořit podsložku</button>
            </div>
        </form>
    </div>
</div>

<!-- Edit Agreement Subfolder Modal -->
<div id="editAgreementSubfolderModal" class="modal" style="display: none;">
    <div class="modal-content">
        <div class="modal-header">
            <h2>Upravit podsložku</h2>
            <button class="modal-close" onclick="closeEditAgreementSubfolderModal()">&times;</button>
        </div>
        <form id="editAgreementSubfolderForm" enctype="multipart/form-data">
            <input type="hidden" id="editAgreementParentCategory" name="parent_category">
            <input type="hidden" id="editAgreementOriginalName" name="original_name">

            <div class="form-group">
                <label for="editAgreementSubfolderName">Název podsložky *</label>
                <input type="text" id="editAgreementSubfolderName" name="new_name" required
                    placeholder="Nový název podsložky" class="full-width">
            </div>

            <div class="form-group">
                <label for="editAgreementSubfolderFile">Nahrát soubor do podsložky (volitelné)</label>
                <div class="upload-area">
                    <label for="editAgreementSubfolderFile" class="custom-file-button">Vyberte soubor</label>
                    <input type="file" name="subfolder_file" id="editAgreementSubfolderFile" accept=".pdf,.doc,.docx,.txt,.xls,.xlsx,.ppt,.pptx,.jpg,.jpeg,.png,.gif" class="hidden-file-input">
                    <div id="edit-agreement-subfolder-selected-file" class="selected-files-info"></div>
                </div>
                <small class="file-help">Podporované formáty: PDF, DOC, DOCX, TXT, XLS, XLSX, PPT, PPTX, JPG, PNG, GIF (max 50MB)</small>
            </div>

            <div class="form-group">
                <label for="editAgreementSubfolderCustomFilename">Přejmenovat soubor (volitelné)</label>
                <input type="text" id="editAgreementSubfolderCustomFilename" name="custom_filename" class="full-width" placeholder="Ponechte prázdné pro původní název">
                <small class="file-help">Zadejte pouze název bez přípony (přípona se doplní automaticky)</small>
            </div>

            <div class="modal-actions">
                <button type="button" class="btn btn-secondary" onclick="closeEditAgreementSubfolderModal()">Zrušit</button>
                <button type="submit" class="btn btn-primary">Uložit změny</button>
            </div>
        </form>
    </div>
</div>

<!-- Edit Agreement Category Modal -->
<div id="editAgreementCategoryModal" class="modal" style="display: none;">
    <div class="modal-content">
        <div class="modal-header">
            <h2>Upravit kategorii smluv</h2>
            <button class="modal-close" onclick="closeEditAgreementCategoryModal()">&times;</button>
        </div>
        <form id="editAgreementCategoryForm" enctype="multipart/form-data">
            <input type="hidden" id="editAgreementCategoryOriginalName" name="original_name">

            <div class="form-group">
                <label for="editAgreementCategoryName">Název kategorie *</label>
                <input type="text" id="editAgreementCategoryName" name="new_name" required
                    placeholder="Nový název kategorie" class="full-width">
            </div>

            <div class="form-group">
                <label for="editAgreementCategoryFile">Nahrát soubor do kategorie (volitelné)</label>
                <div class="upload-area">
                    <label for="editAgreementCategoryFile" class="custom-file-button">Vyberte soubor</label>
                    <input type="file" name="category_file" id="editAgreementCategoryFile" accept=".pdf,.doc,.docx,.txt,.xls,.xlsx,.ppt,.pptx,.jpg,.jpeg,.png,.gif" class="hidden-file-input">
                    <div id="edit-agreement-category-selected-file" class="selected-files-info"></div>
                </div>
                <small class="file-help">Podporované formáty: PDF, DOC, DOCX, TXT, XLS, XLSX, PPT, PPTX, JPG, PNG, GIF (max 50MB)</small>
            </div>

            <div class="form-group">
                <label for="editAgreementCategoryCustomFilename">Přejmenovat soubor (volitelné)</label>
                <input type="text" id="editAgreementCategoryCustomFilename" name="custom_filename" class="full-width" placeholder="Ponechte prázdné pro původní název">
                <small class="file-help">Zadejte pouze název bez přípony (přípona se doplní automaticky)</small>
            </div>

            <div class="modal-actions">
                <button type="button" class="btn btn-secondary" onclick="closeEditAgreementCategoryModal()">Zrušit</button>
                <button type="submit" class="btn btn-primary">Uložit změny</button>
            </div>
        </form>
    </div>
</div>

<!-- Edit Agreement File Modal -->
<div id="editAgreementFileModal" class="modal" style="display: none;">
    <div class="modal-content">
        <div class="modal-header">
            <h2>Přejmenovat soubor smlouvy</h2>
            <button class="modal-close" onclick="closeEditAgreementFileModal()">&times;</button>
        </div>
        <form id="editAgreementFileForm">
            <input type="hidden" id="editAgreementFileCategory" name="category">
            <input type="hidden" id="editAgreementFileSubfolder" name="subfolder">
            <input type="hidden" id="editAgreementFileOriginalName" name="original_name">

            <div class="form-group">
                <label for="editAgreementFileName">Název souboru *</label>
                <input type="text" id="editAgreementFileName" name="new_name" required
                    placeholder="Nový název souboru (bez přípony)" class="full-width">
                <small class="file-help">Zadejte pouze název bez přípony - přípona zůstane zachována</small>
            </div>

            <div class="modal-actions">
                <button type="button" class="btn btn-secondary" onclick="closeEditAgreementFileModal()">Zrušit</button>
                <button type="submit" class="btn btn-primary">Přejmenovat</button>
            </div>
        </form>
    </div>
</div>

<script>
    // Agreement management JavaScript functions
    let agreementSubfolderData = null;

    document.addEventListener('DOMContentLoaded', function() {
        // Only run if we're on the agreements page
        if (!document.getElementById('agreements')) {
            return;
        }

        // Load subfolder data
        loadAgreementSubfolderData();

        // Initialize form handlers
        initializeAgreementFormHandlers();

        // Handle status messages from URL
        handleAgreementStatusMessages();
    });

    // Load Agreement subfolder data
    function loadAgreementSubfolderData() {
        fetch('get-agreement-subfolders.php')
            .then(response => {
                if (!response.ok) {
                    throw new Error('Network response was not ok');
                }
                return response.text();
            })
            .then(text => {
                try {
                    const data = JSON.parse(text);
                    agreementSubfolderData = data || {};
                    updateAgreementSubfolderOptions();
                } catch (e) {
                    console.log('No Agreement subfolder data available yet:', e.message);
                    agreementSubfolderData = {};
                }
            })
            .catch(error => {
                console.log('No Agreement subfolder data available yet:', error.message);
                agreementSubfolderData = {};
            });
    }

    // Update Agreement subfolder dropdown
    function updateAgreementSubfolderOptions() {
        const mainFolder = document.getElementById('agreement-category');
        const subfolderSelect = document.getElementById('agreement-subfolder');

        if (!mainFolder || !subfolderSelect) return;

        subfolderSelect.innerHTML = '<option value="">-- Hlavní složka --</option>';

        if (agreementSubfolderData[mainFolder.value]) {
            agreementSubfolderData[mainFolder.value].forEach(subfolder => {
                const option = document.createElement('option');
                option.value = subfolder;
                option.textContent = subfolder;
                subfolderSelect.appendChild(option);
            });
        }
    }

    // Simple toggle for agreement categories
    function toggleAgreementCategoryHeader(categoryKey) {
        if (!document.getElementById('agreements')) {
            return;
        }

        const categoryCard = document.querySelector(`#agreements [data-category="${categoryKey}"]`);

        if (!categoryCard) {
            console.warn('Could not find category card for:', categoryKey);
            return;
        }

        if (categoryCard.classList.contains('expanded')) {
            categoryCard.classList.remove('expanded');
        } else {
            document.querySelectorAll('#agreements .agreement-category.expanded').forEach(card => {
                card.classList.remove('expanded');
            });
            categoryCard.classList.add('expanded');
        }
    }

    // Toggle Agreement subfolder
    function toggleAgreementSubfolder(subfolderId) {
        if (event) {
            event.stopPropagation();
            event.preventDefault();
        }

        if (!document.getElementById('agreements')) {
            return;
        }

        const subfolderCard = document.querySelector(`[onclick="toggleAgreementSubfolder('${subfolderId}')"]`);

        if (!subfolderCard) {
            console.warn('Could not find Agreement subfolder card for:', subfolderId);
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

    // Show add Agreement subfolder modal
    function showAddAgreementSubfolderModal(categoryKey) {
        if (event) {
            event.stopPropagation();
            event.preventDefault();
        }

        document.getElementById('agreementParentCategory').value = categoryKey;
        document.getElementById('addAgreementSubfolderModal').style.display = 'flex';
        document.getElementById('addAgreementSubfolderForm').reset();
        setTimeout(() => document.getElementById('agreementSubfolderName').focus(), 100);
    }

    function closeAddAgreementSubfolderModal() {
        document.getElementById('addAgreementSubfolderModal').style.display = 'none';
        document.getElementById('addAgreementSubfolderForm').reset();
    }

    // Edit Agreement subfolder
    function editAgreementSubfolder(categoryKey, subfolderName) {
        if (event) {
            event.stopPropagation();
            event.preventDefault();
        }

        document.getElementById('editAgreementParentCategory').value = categoryKey;
        document.getElementById('editAgreementOriginalName').value = subfolderName;
        document.getElementById('editAgreementSubfolderName').value = subfolderName;
        document.getElementById('editAgreementSubfolderModal').style.display = 'flex';
        setTimeout(() => document.getElementById('editAgreementSubfolderName').focus(), 100);
    }

    function closeEditAgreementSubfolderModal() {
        document.getElementById('editAgreementSubfolderModal').style.display = 'none';
        document.getElementById('editAgreementSubfolderForm').reset();
        document.getElementById('edit-agreement-subfolder-selected-file').textContent = '';
        document.getElementById('editAgreementSubfolderCustomFilename').value = '';
    }

    // Delete Agreement subfolder
    function deleteAgreementSubfolder(categoryKey, subfolderName) {
        if (event) {
            event.stopPropagation();
            event.preventDefault();
        }

        if (confirm(`Opravdu chcete smazat podsložku "${subfolderName}"?\n\nTato akce smaže podsložku a všechny soubory v ní. Toto nelze vrátit zpět!`)) {
            const formData = new FormData();
            formData.append('category', categoryKey);
            formData.append('subfolder', subfolderName);

            fetch('delete-agreement-subfolder.php', {
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
                            showAgreementStatusMessage('success', data.message);
                            setTimeout(() => location.reload(), 500);
                        } else {
                            showAgreementStatusMessage('error', data.message || 'Unknown error occurred');
                        }
                    } catch (e) {
                        console.error('Invalid JSON response:', text);
                        showAgreementStatusMessage('error', 'Server returned invalid response. Check console for details.');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    showAgreementStatusMessage('error', 'Došlo k chybě při mazání podsložky.');
                });
        }
    }

    // Delete function - updated to handle subfolders
    function deleteAgreementItem(fileName, category, subfolder) {
        if (!confirm(`Opravdu chcete smazat soubor "${fileName}"?`)) return;

        const formData = new FormData();
        formData.append('type', 'agreements');
        formData.append('category', category);
        formData.append('subfolder', subfolder || '');
        formData.append('file_name', fileName);

        // Míříme na univerzální handler s profi headery
        fetch('delete-file-handler.php', {
            method: 'POST',
            body: formData,
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    // Použijeme tvou funkci pro zobrazení zprávy (pokud ji tam máš pod tímhle názvem)
                    if (typeof showAgreementStatusMessage === 'function') {
                        showAgreementStatusMessage('success', data.message);
                    }

                    // --- BLESKOVÉ SMAZÁNÍ SOUBORU Z DOMU ---
                    // Najdeme ten správný element podle jména, kategorie a podsložky
                    const selector = `[data-file-name="${fileName}"][data-current-category="${category}"]` +
                        (subfolder ? `[data-current-subfolder="${subfolder}"]` : `[data-current-subfolder=""]`);

                    const fileElement = document.querySelector(selector);

                    if (fileElement) {
                        // Efektní zmizení
                        fileElement.style.transition = 'all 0.3s ease';
                        fileElement.style.opacity = '0';
                        fileElement.style.transform = 'scale(0.8) translateY(-10px)';

                        setTimeout(() => {
                            fileElement.remove();

                            // EXTRA: Pokud jsi smazal poslední soubor v podsložce,
                            // mohl bys tu checknout, jestli tam nezůstalo prázdno a hodit tam tu ikonu "Prázdná složka"
                        }, 300);
                    } else {
                        // Kdyby náhodou selektor selhal (třeba kvůli divným znakům), tak to jistíme refreshem
                        setTimeout(() => location.reload(), 500);
                    }
                } else {
                    // Pokud PHP vrátilo success: false
                    alert(data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Chyba při komunikaci se serverem. Koukni do konzole (F12).');
            });
    }

    // Edit agreement file - updated to handle subfolders
    function editAgreementFile(originalName, category, subfolder, currentName) {
        console.log('Opening edit modal for:', originalName, category, subfolder, currentName);

        document.getElementById('editAgreementFileCategory').value = category;
        document.getElementById('editAgreementFileSubfolder').value = subfolder || '';
        document.getElementById('editAgreementFileOriginalName').value = originalName;

        // Remove extension from current name for editing
        const nameWithoutExt = currentName.replace(/\.[^/.]+$/, "");
        document.getElementById('editAgreementFileName').value = nameWithoutExt;

        document.getElementById('editAgreementFileModal').style.display = 'flex';
        setTimeout(() => document.getElementById('editAgreementFileName').focus(), 100);
    }

    function closeEditAgreementFileModal() {
        document.getElementById('editAgreementFileModal').style.display = 'none';
        document.getElementById('editAgreementFileForm').reset();
    }

    // Category management functions
    function editAgreementCategory(categoryKey) {
        if (event) {
            event.stopPropagation();
            event.preventDefault();
        }

        console.log('Editing Agreement category:', categoryKey);

        const modal = document.getElementById('editAgreementCategoryModal');
        const originalInput = document.getElementById('editAgreementCategoryOriginalName');
        const nameInput = document.getElementById('editAgreementCategoryName');

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

    function closeEditAgreementCategoryModal() {
        const modal = document.getElementById('editAgreementCategoryModal');
        if (modal) modal.style.setProperty('display', 'none', 'important');

        const form = document.getElementById('editAgreementCategoryForm');
        if (form) form.reset();

        const selectedFileDiv = document.getElementById('edit-agreement-category-selected-file');
        if (selectedFileDiv) selectedFileDiv.textContent = '';

        const customFilenameInput = document.getElementById('editAgreementCategoryCustomFilename');
        if (customFilenameInput) customFilenameInput.value = '';
    }

    function deleteAgreementCategory(categoryKey) {
        if (event) {
            event.stopPropagation();
            event.preventDefault();
        }

        if (!confirm(`Smazat kategorii "${categoryKey}" a všechny smlouvy?`)) return;

        const formData = new FormData();
        formData.append('category', categoryKey);
        formData.append('type', 'agreements');

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
                        showAgreementStatusMessage('success', data.message);
                        setTimeout(() => location.reload(), 500);
                    } else {
                        showAgreementStatusMessage('error', data.message || 'Neznámá chyba');
                    }
                } catch (e) {
                    console.error('Invalid JSON response:', text);
                    showAgreementStatusMessage('error', 'Chyba při zpracování odpovědi serveru');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showAgreementStatusMessage('error', 'Chyba při mazání kategorie');
            });
    }

    // Add missing status message function
    function showAgreementStatusMessage(type, message) {
        const container = document.getElementById('status-messages');
        if (!container) return;

        const div = document.createElement('div');
        div.className = `status-message status-${type}`;
        div.innerHTML = `<i class="fas ${type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle'}"></i> ${message}`;

        container.appendChild(div);
        (() => div.remove(), 500);
    }

    // Form handling
    function initializeAgreementFormHandlers() {
        const fileInput = document.getElementById('agreement_file');
        const selectedFile = document.getElementById('selected-agreement-file');

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

        // Add file input handler for edit category modal
        const editCategoryFileInput = document.getElementById('editAgreementCategoryFile');
        const editCategorySelectedFile = document.getElementById('edit-agreement-category-selected-file');

        if (editCategoryFileInput && editCategorySelectedFile) {
            editCategoryFileInput.addEventListener('change', function() {
                if (this.files && this.files.length > 0) {
                    const file = this.files[0];
                    const fileSize = formatFileSize(file.size);
                    editCategorySelectedFile.textContent = `Vybraný soubor: ${file.name} (${fileSize})`;

                    const customFilenameInput = document.getElementById('editAgreementCategoryCustomFilename');
                    if (customFilenameInput && !customFilenameInput.value.trim()) {
                        const nameWithoutExt = file.name.replace(/\.[^/.]+$/, "");
                        customFilenameInput.value = nameWithoutExt;
                    }
                } else {
                    editCategorySelectedFile.textContent = 'Žádný soubor nebyl vybrán';
                }
            });
        }
// Edit category form - UNIVERZÁLNÍ ÚPRAVA PRO AGREEMENTS.PHP
        const editCategoryForm = document.getElementById('editAgreementCategoryForm');
        if (editCategoryForm) {
            editCategoryForm.addEventListener('submit', function(e) {
                e.preventDefault();

                const originalName = document.getElementById('editAgreementCategoryOriginalName').value;
                const newName = document.getElementById('editAgreementCategoryName').value;

                if (!originalName || !newName) {
                    alert('Chybí povinné údaje');
                    return;
                }

                const formData = new FormData(this);
                // PŘIDÁNO: Identifikátor pro univerzální handler
                formData.append('type', 'agreements');
                formData.set('original_name', originalName);
                formData.set('new_name', newName);

                const submitBtn = this.querySelector('button[type="submit"]');
                const originalText = submitBtn.innerHTML;
                submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Ukládá se...';
                submitBtn.disabled = true;

                // ZMĚNĚNO: Teď to pálí na společný edit-category-handler.php
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
                                closeEditAgreementCategoryModal();
                                showAgreementStatusMessage('success', data.message);
                                setTimeout(() => location.reload(), 500);
                            } else {
                                showAgreementStatusMessage('error', data.message || 'Neznámá chyba');
                            }
                        } catch (e) {
                            console.error('Invalid JSON response:', text);
                            showAgreementStatusMessage('error', 'Chyba při zpracování odpovědi serveru');
                        }
                    })
                    .catch((error) => {
                        console.error('Error:', error);
                        showAgreementStatusMessage('error', 'Chyba při ukládání');
                    })
                    .finally(() => {
                        submitBtn.innerHTML = originalText;
                        submitBtn.disabled = false;
                    });
            });
        }

        // Handle Agreement subfolder form submission
        const agreementSubfolderForm = document.getElementById('addAgreementSubfolderForm');
        if (agreementSubfolderForm) {
            agreementSubfolderForm.addEventListener('submit', function(e) {
                e.preventDefault();

                const formData = new FormData(this);

                fetch('add-agreement-subfolder.php', {
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
                                closeAddAgreementSubfolderModal();
                                showAgreementStatusMessage('success', data.message);
                                setTimeout(() => location.reload(), 500);
                            } else {
                                showAgreementStatusMessage('error', data.message || 'Unknown error occurred');
                            }
                        } catch (e) {
                            console.error('Invalid JSON response:', text);
                            showAgreementStatusMessage('error', 'Server returned invalid response. Check console for details.');
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        showAgreementStatusMessage('error', 'Došlo k chybě při vytváření podsložky.');
                    });
            });
        }

        // Handle edit Agreement subfolder form submission
        const editAgreementSubfolderForm = document.getElementById('editAgreementSubfolderForm');
        if (editAgreementSubfolderForm) {
            editAgreementSubfolderForm.addEventListener('submit', function(e) {
                e.preventDefault();

                const formData = new FormData(this);
                // 1. Identifikace pro univerzální handler (aby věděl, že jde o složku /agreements/)
                formData.append('type', 'agreements');

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
                            if (typeof closeEditAgreementSubfolderModal === 'function') closeEditAgreementSubfolderModal();
                            showAgreementStatusMessage('success', data.message);
                            setTimeout(() => location.reload(), 500);
                        } else {
                            showAgreementStatusMessage('error', data.message || 'Neznámá chyba');
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        showAgreementStatusMessage('error', 'Chyba při komunikaci se serverem.');
                    })
                    .finally(() => {
                        btn.innerHTML = originalText;
                        btn.disabled = false;
                    });
            });
        }

        // Edit agreement file form
        // Handler pro přejmenování SMLOUVY
        const editAgreementFileForm = document.getElementById('editAgreementFileForm');
        if (editAgreementFileForm) {
            editAgreementFileForm.addEventListener('submit', function(e) {
                e.preventDefault();

                // 1. Vytáhneme data z modalu
                const category = document.getElementById('editAgreementFileCategory').value;
                const subfolder = document.getElementById('editAgreementFileSubfolder').value;
                const originalName = document.getElementById('editAgreementFileOriginalName').value;
                const newName = document.getElementById('editAgreementFileName').value;

                if (!category || !originalName || !newName) {
                    alert('Všechna pole jsou povinná.');
                    return;
                }

                const formData = new FormData();
                // 2. TADY JE TA IDENTIFIKACE: 'agreements' (pošle PHP do správné složky)
                formData.append('type', 'agreements');
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
                            if (typeof closeEditAgreementFileModal === 'function') closeEditAgreementFileModal();

                            if (typeof showAgreementStatusMessage === 'function') {
                                showAgreementStatusMessage('success', data.message);
                            }

                            // Reload je u smluv nutný kvůli přegenerování odkazů na soubory
                            setTimeout(() => location.reload(), 600);
                        } else {
                            alert(data.message);
                            submitBtn.disabled = false;
                            submitBtn.innerHTML = originalText;
                        }
                    })
                    .catch((error) => {
                        console.error('Error:', error);
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
    function toggleAgreementFolderInputs() {
        const customInput = document.getElementById('custom-agreement-folder-name');
        const existingRow = document.getElementById('existing-agreement-folder-row');
        const categorySelect = document.getElementById('agreement-category');

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
    function handleAgreementStatusMessages() {
        const urlParams = new URLSearchParams(window.location.search);
        const status = urlParams.get('status');
        const message = urlParams.get('message');

        if (status && message) {
            showAgreementStatusMessage(status, decodeURIComponent(message));

            const cleanUrl = window.location.pathname + '#agreements';
            window.history.replaceState({}, document.title, cleanUrl);
        }
    }

    // Expose functions globally with updated function names
    if (document.getElementById('agreements')) {
        window.deleteAgreementItem = deleteAgreementItem;
        window.toggleAgreementCategoryHeader = toggleAgreementCategoryHeader;
        window.editAgreementCategory = editAgreementCategory;
        window.closeEditAgreementCategoryModal = closeEditAgreementCategoryModal;
        window.deleteAgreementCategory = deleteAgreementCategory;
        window.toggleAgreementFolderInputs = toggleAgreementFolderInputs;
        window.editAgreementFile = editAgreementFile;
        window.closeEditAgreementFileModal = closeEditAgreementFileModal;
        window.showAddAgreementSubfolderModal = showAddAgreementSubfolderModal;
        window.closeAddAgreementSubfolderModal = closeAddAgreementSubfolderModal;
        window.editAgreementSubfolder = editAgreementSubfolder;
        window.closeEditAgreementSubfolderModal = closeEditAgreementSubfolderModal;
        window.toggleAgreementSubfolder = toggleAgreementSubfolder;
        window.deleteAgreementSubfolder = deleteAgreementSubfolder;
    }

    // Drag and Drop for Agreements - Use unique variable names
    let draggedAgreementElement = null;
    let draggedAgreementFileData = null;

    const supportsFileSystemAccessAgreements = 'getAsFileSystemHandle' in DataTransferItem.prototype;

    function handleAgreementDragStart(event) {
        console.log("🚀 Agreement drag started");

        draggedAgreementElement = event.target.closest('.draggable-file');
        if (!draggedAgreementElement) return;
        draggedAgreementElement.classList.add('dragging');

        draggedAgreementFileData = {
            fileName: draggedAgreementElement.getAttribute('data-file-name'),
            currentCategory: draggedAgreementElement.getAttribute('data-current-category'),
            currentSubfolder: draggedAgreementElement.getAttribute('data-current-subfolder') || ''
        };

        event.dataTransfer.effectAllowed = 'move';
        event.dataTransfer.setData('text/plain', JSON.stringify(draggedAgreementFileData));

        if (supportsFileSystemAccessAgreements) {
            try {
                const fileData = new Blob([JSON.stringify(draggedAgreementFileData)], {
                    type: 'application/json'
                });
                const file = new File([fileData], 'agreement-move-data.json', {
                    type: 'application/json'
                });
                event.dataTransfer.items.add(file);
            } catch (error) {
                console.warn("⚠️ Could not set enhanced drag data:", error);
            }
        }
    }

    function handleAgreementDragEnd(event) {
        if (draggedAgreementElement) {
            draggedAgreementElement.classList.remove('dragging');
        }

        document.querySelectorAll('.droppable-zone').forEach(zone => {
            zone.classList.remove('drag-over');
        });

        draggedAgreementElement = null;
        draggedAgreementFileData = null;
    }

    function handleAgreementDragOver(event) {
        event.preventDefault();
        event.dataTransfer.dropEffect = 'move';
    }

    function handleAgreementDragEnter(event) {
        event.preventDefault();
        event.stopPropagation();

        if (!draggedAgreementFileData) return;

        const dropZone = event.currentTarget;
        const targetCategory = dropZone.getAttribute('data-category');
        const targetSubfolder = dropZone.getAttribute('data-subfolder') || '';

        if (targetCategory === draggedAgreementFileData.currentCategory &&
            targetSubfolder === draggedAgreementFileData.currentSubfolder) {
            return;
        }

        dropZone.classList.add('drag-over');
    }

    function handleAgreementDragLeave(event) {
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

    async function handleAgreementDrop(event) {
        event.preventDefault();
        event.stopPropagation();

        const dropZone = event.currentTarget;
        const targetCategory = dropZone.getAttribute('data-category');
        const targetSubfolder = dropZone.getAttribute('data-subfolder') || '';

        dropZone.classList.remove('drag-over');

        let fileData = null;

        if (supportsFileSystemAccessAgreements && event.dataTransfer.items.length > 0) {
            for (let i = 0; i < event.dataTransfer.items.length; i++) {
                const item = event.dataTransfer.items[i];

                try {
                    if (item.kind === 'file') {
                        const handle = await item.getAsFileSystemHandle();

                        if (handle && handle.kind === 'file') {
                            fileData = {
                                fileName: handle.name,
                                currentCategory: draggedAgreementFileData?.currentCategory,
                                currentSubfolder: draggedAgreementFileData?.currentSubfolder
                            };
                            break;
                        }
                    }
                } catch (error) {
                    console.warn("⚠️ File System Access API failed:", error);
                }
            }
        }

        if (!fileData && draggedAgreementFileData) {
            fileData = draggedAgreementFileData;
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
            showAgreementStatusMessage('error', 'Nepodařilo se získat informace o souboru');
            return;
        }

        if (targetCategory === fileData.currentCategory &&
            targetSubfolder === fileData.currentSubfolder) {
            return;
        }

        await moveAgreementFile(fileData, targetCategory, targetSubfolder);
    }

    async function moveAgreementFile(fileData, targetCategory, targetSubfolder) {
        console.log("🚚 Moving agreement file:", fileData.fileName);
        showAgreementStatusMessage('info', 'Přesouvám soubor...');

        const formData = new FormData();
        formData.append('fileName', fileData.fileName);
        formData.append('sourceCategory', fileData.currentCategory);
        formData.append('sourceSubfolder', fileData.currentSubfolder);
        formData.append('targetCategory', targetCategory);
        formData.append('targetSubfolder', targetSubfolder);
        formData.append('move_agreement_file', '1');

        try {
            const response = await fetch(window.location.href, {
                method: 'POST',
                body: formData
            });

            if (response.ok) {
                showAgreementStatusMessage('success', '✅ Soubor byl úspěšně přesunut!');
                setTimeout(() => {
                    location.reload();
                }, 1000);
            } else {
                showAgreementStatusMessage('error', '❌ Nepodařilo se přesunout soubor.');
            }
        } catch (error) {
            console.error('Error moving file:', error);
            showAgreementStatusMessage('error', '❌ Chyba při přesouvání souboru.');
        }
    }
</script>