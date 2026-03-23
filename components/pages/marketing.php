<?php
// Enforce login requirement first
session_start();

// BUFFER OUTPUT IMMEDIATELY TO FIX "HEADERS ALREADY SENT"
ob_start();

require_once __DIR__ . '/../../access.php';
requireLogin();

// Handle file move request
if (isset($_POST['move_marketing_file']) && isAdmin()) {
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
        $marketingBaseDir = getRoot('marketing');
        $sourcePath = $marketingBaseDir . $sourceCategory . '/' . ($sourceSubfolder ? $sourceSubfolder . '/' : '');
        $targetPath = $marketingBaseDir . $targetCategory . '/' . ($targetSubfolder ? $targetSubfolder . '/' : '');
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

// Helper function to format file size
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

// Function to scan for subfolders and files recursively
function scanMarketingRecursive($dir, $categoryKey, $depth = 0)
{
    $result = ['folders' => [], 'documents' => []];

    if (!is_dir($dir)) return $result;

    $items = array_diff(scandir($dir), ['.', '..']);

    foreach ($items as $item) {
        $itemPath = $dir . $item;

        if (is_dir($itemPath)) {
            // It's a subfolder
            $subResult = scanMarketingRecursive($itemPath . '/', $categoryKey, $depth + 1);
            $result['folders'][$item] = [
                'name' => $item,
                'path' => $itemPath,
                'data' => $subResult,
                'depth' => $depth
            ];
        } else {
            // It's a file - add it directly
            $extension = strtolower(pathinfo($item, PATHINFO_EXTENSION));

            // Include marketing files
            $allowedExtensions = ['pdf', 'doc', 'docx', 'txt', 'xls', 'xlsx', 'ppt', 'pptx', 'jpg', 'jpeg', 'png', 'gif', 'mp4', 'avi', 'mov'];

            if (in_array($extension, $allowedExtensions)) {
                $fileData = [
                    'name' => pathinfo($item, PATHINFO_FILENAME),
                    'original_name' => $item,
                    'file_path' => str_replace($_SERVER['DOCUMENT_ROOT'], '', $itemPath),
                    'file_size' => filesize($itemPath),
                    'file_extension' => $extension,
                    'date_added' => filemtime($itemPath),
                    'has_file' => true,
                    'id' => pathinfo($item, PATHINFO_FILENAME)
                ];

                $result['documents'][] = $fileData;
            }
        }
    }

    return $result;
}

// Function to render marketing subfolder
function renderMarketingSubfolder($folderName, $folderData, $categoryKey)
{
?>
    <div class="marketing-subfolder-card droppable-zone"
        data-category="<?= htmlspecialchars($categoryKey) ?>"
        data-subfolder="<?= htmlspecialchars($folderName) ?>"
        onclick="toggleMarketingSubfolder('<?= $categoryKey ?>-<?= $folderName ?>')"
        ondrop="handleMarketingDrop(event)"
        ondragover="handleMarketingDragOver(event)"
        ondragenter="handleMarketingDragEnter(event)"
        ondragleave="handleMarketingDragLeave(event)">

        <div class="drop-zone-indicator">
            <i class="fas fa-file-import"></i> &nbsp; Přetáhněte soubor sem
        </div>

        <div class="subfolder-header">
            <div class="subfolder-info">
                <i class="fas fa-folder" style="color: #3498db"></i>
                <h5><?= htmlspecialchars($folderName) ?></h5>
            </div>
            <div class="subfolder-stats">
                <span class="doc-count"><?= count($folderData['data']['documents']) ?></span>
                <?php if (isAdmin()): ?>
                    <button class="mini-btn edit-btn" onclick="event.stopPropagation(); editMarketingSubfolder('<?= $categoryKey ?>', '<?= $folderName ?>')" title="Upravit podsložku">
                        <i class="fas fa-edit"></i>
                    </button>
                    <button class="mini-btn delete-btn" onclick="event.stopPropagation(); deleteMarketingSubfolder('<?= $categoryKey ?>', '<?= $folderName ?>')" title="Smazat podsložku">
                        <i class="fas fa-trash"></i>
                    </button>
                <?php endif; ?>
                <i class="fas fa-chevron-right subfolder-toggle"></i>
            </div>
        </div>

        <div class="subfolder-content">
            <div class="subfolder-documents">
                <?php if (!empty($folderData['data']['documents'])): ?>
                    <?php foreach ($folderData['data']['documents'] as $material): ?>
                        <div class="subfolder-document draggable-file"
                            draggable="true"
                            data-file-name="<?= htmlspecialchars($material['original_name']) ?>"
                            data-current-category="<?= htmlspecialchars($categoryKey) ?>"
                            data-current-subfolder="<?= htmlspecialchars($folderName) ?>"
                            ondragstart="handleMarketingDragStart(event)"
                            ondragend="handleMarketingDragEnd(event)">
                            <div class="doc-icon">
                                <?php
                                $ext = $material['file_extension'];
                                switch ($ext) {
                                    case 'pdf':
                                        echo '<i class="fas fa-file-pdf" style="color: #e74c3c;"></i>';
                                        break;
                                    case 'doc':
                                    case 'docx':
                                        echo '<i class="fas fa-file-word" style="color: #3498db;"></i>';
                                        break;
                                    case 'xls':
                                    case 'xlsx':
                                        echo '<i class="fas fa-file-excel" style="color: #2ecc71;"></i>';
                                        break;
                                    case 'jpg':
                                    case 'jpeg':
                                    case 'png':
                                    case 'gif':
                                        echo '<i class="fas fa-file-image" style="color: #f39c12;"></i>';
                                        break;
                                    case 'mp4':
                                    case 'avi':
                                    case 'mov':
                                        echo '<i class="fas fa-file-video" style="color: #9b59b6;"></i>';
                                        break;
                                    default:
                                        echo '<i class="fas fa-file" style="color: #95a5a6;"></i>';
                                }
                                ?>
                                <i class="fas fa-arrows-alt drag-handle"></i>
                            </div>
                            <div class="doc-info">
                                <span class="doc-name"><?= htmlspecialchars($material['name']) ?></span>
                                <span class="doc-size"><?= formatBytes($material['file_size']) ?></span>
                            </div>
                            <div class="doc-actions">
                                <a href="<?= htmlspecialchars($material['file_path']) ?>" target="_blank" class="mini-btn view-btn" onclick="event.stopPropagation();" title="Zobrazit">
                                    <i class="fas fa-eye"></i>
                                </a>
                                <a href="<?= htmlspecialchars($material['file_path']) ?>" download class="mini-btn download-btn" onclick="event.stopPropagation();" title="Stáhnout">
                                    <i class="fas fa-download"></i>
                                </a>
                                <?php if (isAdmin()): ?>
                                    <button class="mini-btn edit-btn" onclick="event.stopPropagation(); editMarketingSubfolderFile('<?= addslashes($material['original_name']) ?>', '<?= $categoryKey ?>', '<?= addslashes($folderName) ?>', '<?= addslashes($material['name']) ?>')" title="Přejmenovat">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <button class="mini-btn delete-btn" onclick="event.stopPropagation(); deleteMarketingSubfolderFile('<?= addslashes($material['original_name']) ?>', '<?= $categoryKey ?>', '<?= addslashes($folderName) ?>')" title="Smazat">
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

// Function to render marketing card
function renderMarketingCard($material, $categoryKey)
{
?>
    <div class="marketing-card-grid draggable-file"
        draggable="true"
        data-file-name="<?= htmlspecialchars($material['original_name']) ?>"
        data-current-category="<?= htmlspecialchars($categoryKey) ?>"
        data-current-subfolder=""
        ondragstart="handleMarketingDragStart(event)"
        ondragend="handleMarketingDragEnd(event)">
        <div class="marketing-preview">
            <i class="fas fa-arrows-alt drag-handle"></i>
            <div class="file-preview-icon">
                <?php
                $ext = $material['file_extension'];
                switch ($ext) {
                    case 'pdf':
                        echo '<i class="fas fa-file-pdf" style="color: #e74c3c; font-size: 32px;"></i>';
                        break;
                    case 'doc':
                    case 'docx':
                        echo '<i class="fas fa-file-word" style="color: #3498db; font-size: 32px;"></i>';
                        break;
                    case 'xls':
                    case 'xlsx':
                        echo '<i class="fas fa-file-excel" style="color: #2ecc71; font-size: 32px;"></i>';
                        break;
                    case 'jpg':
                    case 'jpeg':
                    case 'png':
                    case 'gif':
                        echo '<i class="fas fa-file-image" style="color: #f39c12; font-size: 32px;"></i>';
                        break;
                    case 'mp4':
                    case 'avi':
                    case 'mov':
                        echo '<i class="fas fa-file-video" style="color: #9b59b6; font-size: 32px;"></i>';
                        break;
                    default:
                        echo '<i class="fas fa-file" style="color: #7f8c8d; font-size: 32px;"></i>';
                }
                ?>
            </div>
        </div>

        <div class="marketing-info-grid">
            <h5 class="marketing-title"><?= htmlspecialchars($material['name']) ?></h5>

            <div class="marketing-meta-grid">
                <span class="marketing-date">
                    <i class="fas fa-calendar"></i>
                    <?= date('d.m.Y', $material['date_added']) ?>
                </span>
                <span class="marketing-size">
                    <i class="fas fa-file"></i>
                    <?= formatBytes($material['file_size']) ?>
                </span>
            </div>

            <div class="marketing-actions-grid">
                <?php if (in_array($material['file_extension'], ['pdf', 'jpg', 'jpeg', 'png', 'gif'])): ?>
                    <a href="<?= htmlspecialchars($material['file_path']) ?>" target="_blank" class="grid-btn view-btn">
                        <i class="fas fa-eye"></i>
                    </a>
                <?php endif; ?>
                
                <a href="<?= htmlspecialchars($material['file_path']) ?>" download class="grid-btn download-btn">
                    <i class="fas fa-download"></i>
                </a>
                
                <?php if (isAdmin()): ?>
                    <button class="grid-btn edit-btn" onclick="editMarketingFile('<?= addslashes($material['original_name']) ?>', '<?= $categoryKey ?>', '<?= addslashes($material['name']) ?>')">
                        <i class="fas fa-edit"></i>
                    </button>
                    <button class="grid-btn delete-btn" onclick="deleteMarketingFile('<?= addslashes($material['original_name']) ?>', '<?= $categoryKey ?>')">
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
    /* Marketing page styling - adapted from agreements page */
    #marketing .content-area {
        display: flex;
        flex-direction: column;
        gap: 30px;
    }

    .marketing-category {
        background: white;
        border-radius: 10px;
        box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        overflow: hidden;
        transition: all 0.3s ease;
        position: relative;
    }

    .marketing-category.expanded {
        grid-column: 1 / -1;
        max-width: none;
        transform: none !important;
        z-index: 10;
    }

    .marketing-category.expanded .category-content {
        max-height: none !important;
        padding: 20px !important;
        display: block !important;
        opacity: 1 !important;
        overflow: visible !important;
    }

    .marketing-category.expanded .category-toggle i {
        transform: rotate(180deg);
    }

    .marketing-category:hover:not(.expanded) {
        box-shadow: 0 5px 15px rgba(0, 0, 0, 0.15);
    }

    .marketing-category:not(.expanded) .category-content {
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

    /* Marketing subfolder styling */
    .marketing-subfolders-section {
        margin-bottom: 25px;
    }

    .marketing-subfolders-section h4 {
        color: #495057;
        font-size: 14px;
        font-weight: 600;
        margin: 0 0 12px 0;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    .marketing-subfolders-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
        gap: 12px;
    }

    .marketing-subfolder-card {
        background: #f8f9fa;
        border: 1px solid #e9ecef;
        border-radius: 8px;
        padding: 15px;
        cursor: pointer;
        transition: all 0.2s;
        position: relative;
    }

    .marketing-subfolder-card:hover {
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
    .marketing-subfolder-card.open {
        grid-column: 1 / -1;
        width: 100%;
        max-width: none;
        background: #fff;
        border: 1px solid #dee2e6;
    }

    .marketing-subfolder-card.open .subfolder-content {
        max-height: 1000px;
        margin-top: 12px;
        padding-top: 12px;
        border-top: 1px solid #dee2e6;
    }

    .marketing-subfolder-card.open .subfolder-toggle {
        transform: rotate(90deg);
    }

    .subfolder-documents {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
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
        width: 100%;
        box-sizing: border-box;
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

    /* Marketing Documents Section */
    .marketing-documents-section {
        margin-top: 0 !important;
        padding-top: 0 !important;
    }

    .marketing-documents-section h4 {
        color: #495057;
        font-size: 14px;
        font-weight: 600;
        margin: 0 0 12px 0;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    .marketing-documents-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
        gap: 15px;
    }

    .marketing-card-grid {
        background: white;
        border: 1px solid #e9ecef;
        border-radius: 8px;
        overflow: hidden;
        transition: all 0.2s;
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
        opacity: 1 !important;
        display: block !important;
    }

    .marketing-card-grid:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
        border-color: #dee2e6;
    }

    .marketing-preview {
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

    .marketing-info-grid {
        padding: 15px;
        display: flex;
        flex-direction: column;
        gap: 8px;
    }

    .marketing-title {
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

    .marketing-meta-grid {
        display: flex;
        justify-content: space-between;
        align-items: center;
        font-size: 11px;
        color: #6c757d;
        margin-top: auto;
        flex-wrap: wrap;
        gap: 8px;
    }

    .marketing-date,
    .marketing-size {
        display: flex;
        align-items: center;
        gap: 4px;
    }

    .marketing-actions-grid {
        display: flex;
        gap: 6px;
        justify-content: flex-end;
        margin-top: 8px;
        padding-top: 8px;
        border-top: 1px solid #f0f0f0;
    }

    /* Legacy folder styling for backwards compatibility */
    .edo-folder-container h2 {
        margin-bottom: 20px;
        color: #495057;
    }

    .no-folders {
        text-align: center;
        padding: 60px 20px;
        color: #6c757d;
        background: white;
        border-radius: 10px;
        box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
    }

    .no-folders i {
        font-size: 48px;
        margin-bottom: 15px;
        color: #dee2e6;
    }

    .no-folders p {
        font-size: 16px;
        margin: 0;
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
    .marketing-grid-container {
        height: auto !important;
        min-height: 0 !important;
        flex-grow: 0; /* Zabrání natahování ve flexu */
        align-self: flex-start; /* Zarovná se na začátek a nebude se natahovat na celou výšku */
        width: 100%;
    }

    /* Add missing styles for upload section to match agreements */
    .add-marketing-section {
        background: white;
        border-radius: 10px;
        padding: 25px;
        margin-bottom: 25px;
        box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
    }

    .add-marketing-section h2 {
        color: #2c3e50;
        margin-top: 0;
        margin-bottom: 20px;
        font-size: 20px;
    }

    .marketing-form {
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

    .add-marketing-subfolder-btn {
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

    .add-marketing-subfolder-btn:hover {
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
    .marketing-card-grid.dragging,
    .subfolder-document.dragging {
        opacity: 0.5;
        transform: scale(0.95);
        cursor: grabbing;
    }

    .marketing-subfolder-card.drag-over {
        background: #e3f2fd !important;
        border-color: #2196f3 !important;
        box-shadow: 0 0 15px rgba(33, 150, 243, 0.3) !important;
    }

    .marketing-category.drag-over {
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

    .marketing-subfolder-card.drag-over .drop-zone-indicator,
    .marketing-category.drag-over .drop-zone-indicator {
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

<div class="page-content" id="marketing">
    <div class="content-area">
        <!-- Status Messages -->
        <div id="status-messages"></div>

        <!-- Marketing Grid Container -->
        <div class="marketing-grid-container">
            <?php
            $baseDir = getRoot('marketing');
            $marketingByCategory = [];

            if (is_dir($baseDir)) {
                $folders = array_filter(scandir($baseDir), function ($f) use ($baseDir) {
                    return $f !== '.' && $f !== '..' && is_dir($baseDir . $f);
                });

                foreach ($folders as $folder) {
                    $categoryData = scanMarketingRecursive($baseDir . $folder . '/', $folder);
                    if (!empty($categoryData['documents']) || !empty($categoryData['folders'])) {
                        $marketingByCategory[$folder] = [
                            'info' => ['name' => $folder, 'color' => '#3498db'],
                            'data' => $categoryData
                        ];
                    }
                }
            }

            if (empty($marketingByCategory)): ?>
                <div class="no-folders">
                    <i class="fas fa-folder-open"></i>
                    <p>Žádné marketingové složky zatím neexistují.</p>
                </div>
            <?php else: ?>
                <div class="categories-grid">
                    <?php foreach ($marketingByCategory as $categoryKey => $category): ?>
                        <div class="marketing-category droppable-zone"
                            data-category="<?= htmlspecialchars($categoryKey, ENT_QUOTES, 'UTF-8') ?>"
                            style="border-top: 4px solid <?= $category['info']['color'] ?>"
                            ondrop="handleMarketingDrop(event)"
                            ondragover="handleMarketingDragOver(event)"
                            ondragenter="handleMarketingDragEnter(event)"
                            ondragleave="handleMarketingDragLeave(event)">

                            <div class="drop-zone-indicator">
                                <i class="fas fa-file-import"></i> &nbsp; Přetáhněte soubor do hlavní složky
                            </div>

                            <div class="category-header category-header-clickable" onclick="toggleMarketingCategoryHeader('<?= htmlspecialchars($categoryKey, ENT_QUOTES, 'UTF-8') ?>')">
                                <div class="category-info">
                                    <h3 style="color: <?= $category['info']['color'] ?>">
                                        <i class="fas fa-folder"></i>
                                        <?= htmlspecialchars($category['info']['name'], ENT_QUOTES, 'UTF-8') ?>
                                    </h3>
                                    <span class="file-count">
                                        <?= count($category['data']['documents']) ?> souborů
                                        <?php if (!empty($category['data']['folders'])): ?>
                                            • <?= count($category['data']['folders']) ?> podsložek
                                        <?php endif; ?>
                                    </span>
                                </div>

                                <?php if (isAdmin()): ?>
                                    <div class="category-admin-actions" onclick="event.stopPropagation()">
                                        <button class="admin-btn edit-btn" onclick="editMarketingCategory('<?= htmlspecialchars($categoryKey, ENT_QUOTES, 'UTF-8') ?>')" title="Přejmenovat kategorii">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <button class="admin-btn delete-btn" onclick="deleteMarketingCategory('<?= htmlspecialchars($categoryKey, ENT_QUOTES, 'UTF-8') ?>')" title="Smazat kategorii">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </div>
                                <?php endif; ?>

                                <div class="category-toggle">
                                    <i class="fas fa-chevron-down"></i>
                                </div>
                            </div>

                            <div class="category-content" id="marketing-category-content-<?= htmlspecialchars($categoryKey, ENT_QUOTES, 'UTF-8') ?>">
                                <?php if (canUpload()): ?>
                                    <div class="category-actions">
                                        <button class="action-btn add-marketing-subfolder-btn" onclick="showAddMarketingSubfolderModal('<?= $categoryKey ?>')">
                                            <i class="fas fa-folder-plus"></i> Přidat podsložku
                                        </button>
                                    </div>
                                <?php endif; ?>

                                <!-- Subfolders Grid -->
                                <?php if (!empty($category['data']['folders'])): ?>
                                    <div class="marketing-subfolders-section">
                                        <h4>Podsložky</h4>
                                        <div class="marketing-subfolders-grid">
                                            <?php foreach ($category['data']['folders'] as $folderName => $folderData): ?>
                                                <?php renderMarketingSubfolder($folderName, $folderData, $categoryKey); ?>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                <?php endif; ?>

                                <!-- Marketing files in main category -->
                                <?php if (!empty($category['data']['documents'])): ?>
                                    <div class="marketing-documents-section">
                                        <h4>Marketingové materiály</h4>
                                        <div class="marketing-documents-grid">
                                            <?php foreach ($category['data']['documents'] as $material): ?>
                                                <?php renderMarketingCard($material, $categoryKey); ?>
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

        <!-- Add Marketing Section -->
        <?php if (canUpload()): ?>
            <div class="add-marketing-section">
                <h2>Nahrát marketingový materiál</h2>
                <form action="upload-category-form-handler.php" method="post" enctype="multipart/form-data" class="marketing-form">

                    <input type="hidden" name="type" value="marketing">
                    <input type="hidden" name="redirect" value="marketing">

                    <div class="form-row">
                        <div class="form-group">
                            <label for="custom-marketing-folder-name">Vytvořit novou kategorii (volitelné)</label>
                            <input type="text" id="custom-marketing-folder-name" name="custom_category" class="full-width"
                                   placeholder="Zadejte název pro novou kategorii..."
                                   onchange="toggleMarketingFolderInputs()"
                                   oninput="toggleMarketingFolderInputs()">
                            <small class="file-help">Pokud zadáte název, vytvoří se nová kategorie s tímto názvem</small>
                        </div>
                    </div>

                    <div class="form-row" id="existing-marketing-folder-row">
                        <div class="form-group">
                            <label for="marketing-category">Nebo vyberte existující kategorii</label>
                            <select id="marketing-category" name="category" class="full-width" onchange="updateMarketingSubfolderOptions()">
                                <option value="">-- Vyberte kategorii --</option>
                                <?php
                                $marketingDir = getRoot('marketing');
                                if (is_dir($marketingDir)) {
                                    $categories = array_filter(scandir($marketingDir), function ($f) use ($marketingDir) {
                                        return $f !== '.' && $f !== '..' && is_dir($marketingDir . $f);
                                    });
                                    foreach ($categories as $category): ?>
                                        <option value="<?= htmlspecialchars($category, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($category, ENT_QUOTES, 'UTF-8') ?></option>
                                    <?php endforeach;
                                }
                                ?>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="marketing-subfolder">Podsložka</label>
                            <select id="marketing-subfolder" name="subfolder" class="full-width">
                                <option value="">-- Hlavní složka --</option>
                            </select>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="marketing_file">Soubor *</label>
                            <div class="upload-area">
                                <label for="marketing_file" class="custom-file-button">Vyberte soubor</label>
                                <input type="file" name="file" id="marketing_file" accept=".pdf,.doc,.docx,.txt,.xls,.xlsx,.ppt,.pptx,.jpg,.jpeg,.png,.gif,.mp4,.avi,.mov" class="hidden-file-input" required>
                                <div id="selected-marketing-file" class="selected-files-info"></div>
                            </div>
                        </div>
                    </div>

                    <div class="form-row">
                        <button type="submit" class="upload-btn">
                            <i class="fas fa-upload"></i> Nahrát materiál
                        </button>
                    </div>
                </form>
            </div>
        <?php endif; ?>
        
        <?php if (isAdmin()): ?>
            <div class="edo-upload-container" style="display: none;">
                <h2>Nahrát nové materiály</h2>
                <form action="publish-marketing.php" method="post" enctype="multipart/form-data" class="edo-upload-form">
                    <div class="input-group">
                        <label for="folder_name">Název složky</label>
                        <input type="text" name="folder_name" id="folder_name" placeholder="Zadejte název složky" required>
                    </div>

                    <div class="upload-area">
                        <label for="marketing_files" class="custom-file-button">Vyberte soubory</label>
                        <input type="file" name="marketing_files[]" id="marketing_files" multiple required class="hidden-file-input">
                        <div id="selected-files" class="selected-files-info"></div>
                    </div>

                    <button type="submit" class="edo-upload-btn">
                        <i class="fas fa-folder-plus"></i> Vytvořit složku a nahrát soubory
                    </button>
                </form>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Add Marketing Subfolder Modal -->
<div id="addMarketingSubfolderModal" class="modal" style="display: none;">
    <div class="modal-content">
        <div class="modal-header">
            <h2>Přidat podsložku</h2>
            <button class="modal-close" onclick="closeAddMarketingSubfolderModal()">&times;</button>
        </div>
        <form id="addMarketingSubfolderForm">
            <input type="hidden" id="marketingParentCategory" name="parent_category">

            <div class="form-group">
                <label for="marketingSubfolderName">Název podsložky *</label>
                <input type="text" id="marketingSubfolderName" name="subfolder_name" required
                    placeholder="Název nové podsložky" class="full-width">
            </div>

            <div class="modal-actions">
                <button type="button" class="btn btn-secondary" onclick="closeAddMarketingSubfolderModal()">Zrušit</button>
                <button type="submit" class="btn btn-primary">Vytvořit podsložku</button>
            </div>
        </form>
    </div>
</div>

<!-- Edit Marketing Subfolder Modal -->
<div id="editMarketingSubfolderModal" class="modal" style="display: none;">
    <div class="modal-content">
        <div class="modal-header">
            <h2>Upravit podsložku</h2>
            <button class="modal-close" onclick="closeEditMarketingSubfolderModal()">&times;</button>
        </div>
        <form id="editMarketingSubfolderForm" enctype="multipart/form-data">
            <input type="hidden" id="editMarketingParentCategory" name="parent_category">
            <input type="hidden" id="editMarketingOriginalName" name="original_name">

            <div class="form-group">
                <label for="editMarketingSubfolderName">Název podsložky *</label>
                <input type="text" id="editMarketingSubfolderName" name="new_name" required
                    placeholder="Nový název podsložky" class="full-width">
            </div>

            <div class="form-group">
                <label for="editMarketingSubfolderFile">Nahrát soubor do podsložky (volitelné)</label>
                <div class="upload-area">
                    <label for="editMarketingSubfolderFile" class="custom-file-button">Vyberte soubor</label>
                    <input type="file" name="subfolder_file" id="editMarketingSubfolderFile" accept=".pdf,.doc,.docx,.txt,.xls,.xlsx,.ppt,.pptx,.jpg,.jpeg,.png,.gif,.mp4,.avi,.mov" class="hidden-file-input">
                    <div id="edit-marketing-subfolder-selected-file" class="selected-files-info"></div>
                </div>
                <small class="file-help">Podporované formáty: PDF, DOC, DOCX, TXT, XLS, XLSX, PPT, PPTX, JPG, PNG, GIF, MP4, AVI, MOV (max 50MB)</small>
            </div>

            <div class="form-group">
                <label for="editMarketingSubfolderCustomFilename">Přejmenovat soubor (volitelné)</label>
                <input type="text" id="editMarketingSubfolderCustomFilename" name="custom_filename" class="full-width" placeholder="Ponechte prázdné pro původní název">
                <small class="file-help">Zadejte pouze název bez přípony (přípona se doplní automaticky)</small>
            </div>

            <div class="modal-actions">
                <button type="button" class="btn btn-secondary" onclick="closeEditMarketingSubfolderModal()">Zrušit</button>
                <button type="submit" class="btn btn-primary">Uložit změny</button>
            </div>
        </form>
    </div>
</div>

<!-- Edit Marketing Category Modal -->
<div id="editMarketingCategoryModal" class="modal" style="display: none;">
    <div class="modal-content">
        <div class="modal-header">
            <h2>Upravit marketingovou kategorii</h2>
            <button class="modal-close" onclick="closeEditMarketingCategoryModal()">&times;</button>
        </div>
        <form id="editMarketingCategoryForm" enctype="multipart/form-data">
            <input type="hidden" id="editMarketingCategoryOriginalName" name="original_name">

            <div class="form-group">
                <label for="editMarketingCategoryName">Název kategorie *</label>
                <input type="text" id="editMarketingCategoryName" name="new_name" required
                    placeholder="Nový název kategorie" class="full-width">
            </div>

            <div class="form-group">
                <label for="editMarketingCategoryFile">Nahrát soubor do kategorie (volitelné)</label>
                <div class="upload-area">
                    <label for="editMarketingCategoryFile" class="custom-file-button">Vyberte soubor</label>
                    <input type="file" name="category_file" id="editMarketingCategoryFile" accept=".pdf,.doc,.docx,.txt,.xls,.xlsx,.ppt,.pptx,.jpg,.jpeg,.png,.gif,.mp4,.avi,.mov" class="hidden-file-input">
                    <div id="edit-marketing-category-selected-file" class="selected-files-info"></div>
                </div>
                <small class="file-help">Podporované formáty: PDF, DOC, DOCX, TXT, XLS, XLSX, PPT, PPTX, JPG, PNG, GIF, MP4, AVI, MOV (max 50MB)</small>
            </div>

            <div class="form-group">
                <label for="editMarketingCategoryCustomFilename">Přejmenovat soubor (volitelné)</label>
                <input type="text" id="editMarketingCategoryCustomFilename" name="custom_filename" class="full-width" placeholder="Ponechte prázdné pro původní název">
                <small class="file-help">Zadejte pouze název bez přípony (přípona se doplní automaticky)</small>
            </div>

            <div class="modal-actions">
                <button type="button" class="btn btn-secondary" onclick="closeEditMarketingCategoryModal()">Zrušit</button>
                <button type="submit" class="btn btn-primary">Uložit změny</button>
            </div>
        </form>
    </div>
</div>

<!-- Edit Marketing File Modal -->
<div id="editMarketingFileModal" class="modal" style="display: none;">
    <div class="modal-content">
        <div class="modal-header">
            <h2>Přejmenovat marketingový soubor</h2>
            <button class="modal-close" onclick="closeEditMarketingFileModal()">&times;</button>
        </div>
        <form id="editMarketingFileForm">
            <input type="hidden" id="editMarketingFileCategory" name="category">
            <input type="hidden" id="editMarketingFileOriginalName" name="original_name">

            <div class="form-group">
                <label for="editMarketingFileName">Název souboru *</label>
                <input type="text" id="editMarketingFileName" name="new_name" required
                    placeholder="Nový název souboru (bez přípony)" class="full-width">
                <small class="file-help">Zadejte pouze název bez přípony - přípona zůstane zachována</small>
            </div>

            <div class="modal-actions">
                <button type="button" class="btn btn-secondary" onclick="closeEditMarketingFileModal()">Zrušit</button>
                <button type="submit" class="btn btn-primary">Přejmenovat</button>
            </div>
        </form>
    </div>
</div>

<!-- Edit Marketing Subfolder File Modal -->
<div id="editMarketingSubfolderFileModal" class="modal" style="display: none;">
    <div class="modal-content">
        <div class="modal-header">
            <h2>Přejmenovat soubor v podsložce</h2>
            <button class="modal-close" onclick="closeEditMarketingSubfolderFileModal()">&times;</button>
        </div>
        <form id="editMarketingSubfolderFileForm">
            <input type="hidden" id="editMarketingSubfolderFileCategory" name="category">
            <input type="hidden" id="editMarketingSubfolderFileSubfolder" name="subfolder">
            <input type="hidden" id="editMarketingSubfolderFileOriginalName" name="original_name">

            <div class="form-group">
                <label for="editMarketingSubfolderFileName">Název souboru *</label>
                <input type="text" id="editMarketingSubfolderFileName" name="new_name" required
                    placeholder="Nový název souboru (bez přípony)" class="full-width">
                <small class="file-help">Zadejte pouze název bez přípony - přípona zůstane zachována</small>
            </div>

            <div class="modal-actions">
                <button type="button" class="btn btn-secondary" onclick="closeEditMarketingSubfolderFileModal()">Zrušit</button>
                <button type="submit" class="btn btn-primary">Přejmenovat</button>
            </div>
        </form>
    </div>
</div>

<script>
    // Marketing management JavaScript functions
    let marketingSubfolderData = null;

    document.addEventListener('DOMContentLoaded', function() {
        // Only run if we're on the marketing page
        if (!document.getElementById('marketing')) {
            return;
        }

        // Load subfolder data
        loadMarketingSubfolderData();

        // Initialize form handlers
        initializeMarketingFormHandlers();

        // Handle status messages from URL
        handleMarketingStatusMessages();

        // Close all categories by default
        document.querySelectorAll('#marketing .category-content').forEach(content => {
            if (content) {
                content.style.maxHeight = '0px';
            }
        });
    });

    // Load Marketing subfolder data
    function loadMarketingSubfolderData() {
        fetch('get-marketing-subfolders.php')
            .then(response => {
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                return response.text();
            })
            .then(text => {
                // Check if response is empty or contains HTML error
                if (!text.trim() || text.trim().startsWith('<')) {
                    console.log('Empty or invalid response from get-marketing-subfolders.php');
                    marketingSubfolderData = {};
                    return;
                }
                
                try {
                    const data = JSON.parse(text);
                    marketingSubfolderData = data || {};
                    updateMarketingSubfolderOptions();
                } catch (e) {
                    console.log('Failed to parse subfolder data:', e.message, 'Raw response:', text.substring(0, 200));
                    marketingSubfolderData = {};
                }
            })
            .catch(error => {
                console.log('Error loading subfolder data:', error.message);
                marketingSubfolderData = {};
            });
    }

    // Update Marketing subfolder dropdown
    function updateMarketingSubfolderOptions() {
        const mainFolder = document.getElementById('marketing-category');
        const subfolderSelect = document.getElementById('marketing-subfolder');

        if (!mainFolder || !subfolderSelect) return;

        subfolderSelect.innerHTML = '<option value="">-- Hlavní složka --</option>';

        if (marketingSubfolderData[mainFolder.value]) {
            marketingSubfolderData[mainFolder.value].forEach(subfolder => {
                const option = document.createElement('option');
                option.value = subfolder;
                option.textContent = subfolder;
                subfolderSelect.appendChild(option);
            });
        }
    }

    // Show add Marketing subfolder modal
    function showAddMarketingSubfolderModal(categoryKey) {
        if (event) {
            event.stopPropagation();
            event.preventDefault();
        }

        document.getElementById('marketingParentCategory').value = categoryKey;
        document.getElementById('addMarketingSubfolderModal').style.display = 'flex';
        document.getElementById('addMarketingSubfolderForm').reset();
        setTimeout(() => document.getElementById('marketingSubfolderName').focus(), 100);
    }

    function closeAddMarketingSubfolderModal() {
        document.getElementById('addMarketingSubfolderModal').style.display = 'none';
        document.getElementById('addMarketingSubfolderForm').reset();
    }

    // Edit Marketing subfolder
    function editMarketingSubfolder(categoryKey, subfolderName) {
        if (event) {
            event.stopPropagation();
            event.preventDefault();
        }

        document.getElementById('editMarketingParentCategory').value = categoryKey;
        document.getElementById('editMarketingOriginalName').value = subfolderName;
        document.getElementById('editMarketingSubfolderName').value = subfolderName;
        document.getElementById('editMarketingSubfolderModal').style.display = 'flex';
        setTimeout(() => document.getElementById('editMarketingSubfolderName').focus(), 100);
    }

    function closeEditMarketingSubfolderModal() {
        document.getElementById('editMarketingSubfolderModal').style.display = 'none';
        document.getElementById('editMarketingSubfolderForm').reset();
        document.getElementById('edit-marketing-subfolder-selected-file').textContent = '';
        document.getElementById('editMarketingSubfolderCustomFilename').value = '';
    }

    // Delete Marketing subfolder
    function deleteMarketingSubfolder(categoryKey, subfolderName) {
        if (event) {
            event.stopPropagation();
            event.preventDefault();
        }

        if (confirm(`Opravdu chcete smazat podsložku "${subfolderName}"?\n\nTato akce smaže podsložku a všechny soubory v ní. Toto nelze vrátit zpět!`)) {
            const formData = new FormData();
            // Parametry pro univerzální handler
            formData.append('type', 'marketing');
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

    // Delete marketing file function
    // --- JEDNOTNÁ FUNKCE PRO SMAZÁNÍ MARKETINGU ---
    function universalDeleteMarketing(fileName, category, subfolder = '') {
        if (!confirm(`Opravdu chcete smazat tento marketingový materiál: "${fileName}"?`)) return;

        const formData = new FormData();
        formData.append('type', 'marketing'); // Identifikátor pro handler
        formData.append('category', category);
        formData.append('subfolder', subfolder);
        formData.append('file_name', fileName);

        fetch('delete-file-handler.php', {
            method: 'POST',
            body: formData,
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    showStatusMessage('success', data.message);

                    // SELEKTOR PRO BLESKOVÉ ZMIZENÍ
                    // Hledáme prvek podle jména, kategorie a podsložky
                    const selector = `[data-file-name="${fileName}"][data-current-category="${category}"][data-current-subfolder="${subfolder}"]`;
                    const fileElement = document.querySelector(selector);

                    if (fileElement) {
                        // Sexy animace: prvek odletí dolů a zmizí
                        fileElement.style.transition = 'all 0.4s cubic-bezier(0.4, 0, 0.2, 1)';
                        fileElement.style.opacity = '0';
                        fileElement.style.transform = 'translateY(20px) scale(0.95)';

                        setTimeout(() => fileElement.remove(), 400);
                    } else {
                        // Kdyby náhodou selektor selhal, refresh to jistí
                        console.warn("Element nebyl nalezen v DOMu přes selektor: " + selector);
                        setTimeout(() => location.reload(), 500);
                    }
                } else {
                    alert("Chyba: " + data.message);
                }
            })
            .catch(err => {
                console.error('Chyba komunikace:', err);
                alert('Nepodařilo se spojit se serverem.');
            });
    }

    // --- PŘEMOSTĚNÍ STARÝCH NÁZVŮ (pro kompatibilitu s HTML bez jeho úpravy) ---
    function deleteMarketingFile(fileName, category) {
        universalDeleteMarketing(fileName, category, '');
    }

    function deleteMarketingSubfolderFile(fileName, category, subfolder) {
        universalDeleteMarketing(fileName, category, subfolder);
    }

    // Category management functions
    function editMarketingCategory(categoryKey) {
        if (event) event.stopPropagation();

        console.log('Opening edit modal for category:', categoryKey);

        const modal = document.getElementById('editMarketingCategoryModal');
        const originalInput = document.getElementById('editMarketingCategoryOriginalName');
        const nameInput = document.getElementById('editMarketingCategoryName');
        const customFilenameInput = document.getElementById('editMarketingCategoryCustomFilename');

        if (!modal || !originalInput || !nameInput) {
            console.error('Modal elements not found');
            alert('Chyba: Formulář pro úpravu nebyl nalezen');
            return;
        }

        // Clear form first
        const form = document.getElementById('editMarketingCategoryForm');
        if (form) form.reset();

        // Set values with debugging
        console.log('Setting original name:', categoryKey);
        originalInput.value = categoryKey;
        nameInput.value = categoryKey;
        if (customFilenameInput) customFilenameInput.value = '';

        // Verify values were set
        console.log('Original input value after setting:', originalInput.value);
        console.log('Name input value after setting:', nameInput.value);

        // Clear file selection display
        const selectedFileDiv = document.getElementById('edit-marketing-category-selected-file');
        if (selectedFileDiv) selectedFileDiv.textContent = '';

        // Show modal
        modal.style.display = 'flex';

        // Focus on name input
        setTimeout(() => {
            nameInput.focus();
            nameInput.select();
        }, 100);
    }

    function closeEditMarketingCategoryModal() {
        const modal = document.getElementById('editMarketingCategoryModal');
        if (modal) {
            modal.style.display = 'none';
        }
        
        const form = document.getElementById('editMarketingCategoryForm');
        if (form) {
            form.reset();
        }
        
        const selectedFileDiv = document.getElementById('edit-marketing-category-selected-file');
        if (selectedFileDiv) {
            selectedFileDiv.textContent = '';
        }
        
        const customFilenameInput = document.getElementById('editMarketingCategoryCustomFilename');
        if (customFilenameInput) {
            customFilenameInput.value = '';
        }
    }

    function deleteMarketingCategory(categoryKey) {
        if (event) event.stopPropagation();

        if (!confirm(`Opravdu chcete smazat kategorii "${categoryKey}" a všechny soubory?\n\nTato akce je nevratná!`)) return;

        const formData = new FormData();
        formData.append('category', categoryKey);
        formData.append('type', 'marketing');

        // Show loading state
        const statusContainer = document.getElementById('status-messages');
        if (statusContainer) {
            statusContainer.innerHTML = '<div class="status-message"><i class="fas fa-spinner fa-spin"></i> Mazání kategorie...</div>';
        }

        fetch('delete-category-handler.php', {
                method: 'POST',
                body: formData,
                headers: {
                    'Accept': 'application/json'
                }
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                return response.text();
            })
            .then(text => {
                // Check if response contains HTML error
                if (text.trim().startsWith('<')) {
                    throw new Error('Server returned HTML instead of JSON');
                }
                
                let data;
                try {
                    data = JSON.parse(text);
                } catch (e) {
                    throw new Error('Invalid JSON response: ' + text.substring(0, 100));
                }
                
                if (data.success) {
                    showStatusMessage('success', data.message || 'Kategorie byla smazána');
                    setTimeout(() => location.reload(), 500);
                } else {
                    showStatusMessage('error', data.message || 'Neznámá chyba při mazání kategorie');
                }
            })
            .catch(error => {
                console.error('Error deleting category:', error);
                showStatusMessage('error', 'Chyba při mazání kategorie: ' + error.message);
            });
    }

    // Edit marketing file
    function editMarketingFile(originalName, category, currentName) {
        console.log('Opening edit modal for:', originalName, category, currentName);
        
        document.getElementById('editMarketingFileCategory').value = category;
        document.getElementById('editMarketingFileOriginalName').value = originalName;
        
        // Remove extension from current name for editing
        const nameWithoutExt = currentName.replace(/\.[^/.]+$/, "");
        document.getElementById('editMarketingFileName').value = nameWithoutExt;
        
        document.getElementById('editMarketingFileModal').style.display = 'flex';
        setTimeout(() => document.getElementById('editMarketingFileName').focus(), 100);
    }

    function closeEditMarketingFileModal() {
        document.getElementById('editMarketingFileModal').style.display = 'none';
        document.getElementById('editMarketingFileForm').reset();
    }

    // Edit marketing subfolder file
    function editMarketingSubfolderFile(originalName, category, subfolder, currentName) {
        console.log('Opening edit modal for subfolder file:', originalName, category, subfolder, currentName);
        
        document.getElementById('editMarketingSubfolderFileCategory').value = category;
        document.getElementById('editMarketingSubfolderFileSubfolder').value = subfolder;
        document.getElementById('editMarketingSubfolderFileOriginalName').value = originalName;
        
        // Remove extension from current name for editing
        const nameWithoutExt = currentName.replace(/\.[^/.]+$/, "");
        document.getElementById('editMarketingSubfolderFileName').value = nameWithoutExt;
        
        document.getElementById('editMarketingSubfolderFileModal').style.display = 'flex';
        setTimeout(() => document.getElementById('editMarketingSubfolderFileName').focus(), 100);
    }

    function closeEditMarketingSubfolderFileModal() {
        document.getElementById('editMarketingSubfolderFileModal').style.display = 'none';
        document.getElementById('editMarketingSubfolderFileForm').reset();
    }

    // Delete marketing subfolder file
    function deleteMarketingSubfolderFile(fileName, category, subfolder) {
        if (!fileName || !category || !subfolder) return;
        
        if (confirm('Opravdu chcete smazat tento soubor z podsložky?')) {
            const formData = new FormData();
            formData.append('marketing_file', fileName);
            formData.append('category', category);
            formData.append('subfolder', subfolder);

            fetch('delete-marketing-subfolder-file.php', {
                method: 'POST',
                body: formData,
                headers: {
                    'Accept': 'application/json'
                }
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                return response.text();
            })
            .then(text => {
                // Check if response contains HTML error
                if (text.trim().startsWith('<')) {
                    throw new Error('Server returned HTML instead of JSON');
                }
                
                let data;
                try {
                    data = JSON.parse(text);
                } catch (e) {
                    throw new Error('Invalid JSON response');
                }
                
                if (data.success) {
                    showStatusMessage('success', data.message || 'Soubor byl smazán');
                    setTimeout(() => location.reload(), 1000);
                } else {
                    showStatusMessage('error', data.message || 'Chyba při mazání souboru');
                }
            })
            .catch(error => {
                console.error('Error deleting subfolder file:', error);
                showStatusMessage('error', 'Chyba při mazání souboru: ' + error.message);
            });
        }
    }

    // Toggle folder inputs
    function toggleMarketingFolderInputs() {
        const customInput = document.getElementById('custom-marketing-folder-name');
        const existingRow = document.getElementById('existing-marketing-folder-row');
        const categorySelect = document.getElementById('marketing-category');

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

    // Toggle Marketing category
    function toggleMarketingCategoryHeader(categoryKey) {
        if (!document.getElementById('marketing')) {
            return;
        }

        const categoryCard = document.querySelector(`#marketing [data-category="${categoryKey}"]`);

        if (!categoryCard) {
            console.warn('Could not find marketing category card for:', categoryKey);
            return;
        }

        if (categoryCard.classList.contains('expanded')) {
            categoryCard.classList.remove('expanded');
        } else {
            document.querySelectorAll('#marketing .marketing-category.expanded').forEach(card => {
                card.classList.remove('expanded');
            });
            categoryCard.classList.add('expanded');
        }
    }

    // Toggle Marketing subfolder
    function toggleMarketingSubfolder(subfolderId) {
        if (event) {
            event.stopPropagation();
            event.preventDefault();
        }
        
        if (!document.getElementById('marketing')) {
            return;
        }

        const subfolderCard = document.querySelector(`[onclick="toggleMarketingSubfolder('${subfolderId}')"]`);

        if (!subfolderCard) {
            console.warn('Could not find marketing subfolder card for:', subfolderId);
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

    // Status messages
    function handleMarketingStatusMessages() {
        const urlParams = new URLSearchParams(window.location.search);
        const status = urlParams.get('status');
        const message = urlParams.get('message');

        if (status && message) {
            showStatusMessage(status, decodeURIComponent(message));

            const cleanUrl = window.location.pathname + '#marketing';
            window.history.replaceState({}, document.title, cleanUrl);
        }
    }

    // Utility functions
    function formatFileSize(bytes) {
        if (!bytes || bytes === 0) return '0 B';
        const k = 1024;
        const sizes = ['B', 'KB', 'MB', 'GB'];
        const i = Math.floor(Math.log(bytes) / Math.log(k));
        return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
    }

    function showStatusMessage(type, message) {
        const container = document.getElementById('status-messages');
        if (!container) {
            console.log('Status message:', type, message);
            if (type === 'error') {
                alert(message);
            }
            return;
        }

        const div = document.createElement('div');
        div.className = `status-message status-${type}`;
        div.style.cssText = `
            padding: 12px 16px;
            margin: 10px 0;
            border-radius: 4px;
            display: flex;
            align-items: center;
            gap: 8px;
            background: ${type === 'success' ? '#d4edda' : '#f8d7da'};
            color: ${type === 'success' ? '#155724' : '#721c24'};
            border: 1px solid ${type === 'success' ? '#c3e6cb' : '#f5c6cb'};
            position: relative;
            z-index: 1000;
        `;
        div.innerHTML = `<i class="fas ${type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle'}"></i> ${message}`;

        // Clear existing messages
        container.innerHTML = '';
        container.appendChild(div);
        
        // Auto-hide success messages
        if (type === 'success') {
            setTimeout(() => {
                if (div.parentNode) {
                    div.remove();
                }
            }, 5000);
        }
    }

    // Expose functions globally for marketing page
    if (document.getElementById('marketing')) {
        window.toggleMarketingCategoryHeader = toggleMarketingCategoryHeader;
        window.toggleMarketingSubfolder = toggleMarketingSubfolder;
        window.deleteMarketingFile = deleteMarketingFile;
        window.editMarketingSubfolder = editMarketingSubfolder;
        window.closeEditMarketingSubfolderModal = closeEditMarketingSubfolderModal;
        window.editMarketingCategory = editMarketingCategory;
        window.closeEditMarketingCategoryModal = closeEditMarketingCategoryModal;
        window.deleteMarketingCategory = deleteMarketingCategory;
        window.editMarketingFile = editMarketingFile;
        window.closeEditMarketingFileModal = closeEditMarketingFileModal;
        window.editMarketingSubfolderFile = editMarketingSubfolderFile;
        window.closeEditMarketingSubfolderFileModal = closeEditMarketingSubfolderFileModal;
        window.deleteMarketingSubfolderFile = deleteMarketingSubfolderFile;
        window.toggleMarketingFolderInputs = toggleMarketingFolderInputs;
        window.showAddMarketingSubfolderModal = showAddMarketingSubfolderModal;
        window.closeAddMarketingSubfolderModal = closeAddMarketingSubfolderModal;
        window.deleteMarketingSubfolder = deleteMarketingSubfolder;
        window.updateMarketingSubfolderOptions = updateMarketingSubfolderOptions;
    }

    // Drag and Drop for Marketing - Use unique variable names
    let draggedMarketingElement = null;
    let draggedMarketingFileData = null;

    const supportsFileSystemAccessMarketing = 'getAsFileSystemHandle' in DataTransferItem.prototype;

    function handleMarketingDragStart(event) {
        console.log("🚀 Marketing drag started");

        draggedMarketingElement = event.target.closest('.draggable-file');
        if (!draggedMarketingElement) return;
        draggedMarketingElement.classList.add('dragging');

        draggedMarketingFileData = {
            fileName: draggedMarketingElement.getAttribute('data-file-name'),
            currentCategory: draggedMarketingElement.getAttribute('data-current-category'),
            currentSubfolder: draggedMarketingElement.getAttribute('data-current-subfolder') || ''
        };

        event.dataTransfer.effectAllowed = 'move';
        event.dataTransfer.setData('text/plain', JSON.stringify(draggedMarketingFileData));

        if (supportsFileSystemAccessMarketing) {
            try {
                const fileData = new Blob([JSON.stringify(draggedMarketingFileData)], {
                    type: 'application/json'
                });
                const file = new File([fileData], 'marketing-move-data.json', {
                    type: 'application/json'
                });
                event.dataTransfer.items.add(file);
            } catch (error) {
                console.warn("⚠️ Could not set enhanced drag data:", error);
            }
        }
    }

    function handleMarketingDragEnd(event) {
        if (draggedMarketingElement) {
            draggedMarketingElement.classList.remove('dragging');
        }

        document.querySelectorAll('.droppable-zone').forEach(zone => {
            zone.classList.remove('drag-over');
        });

        draggedMarketingElement = null;
        draggedMarketingFileData = null;
    }

    function handleMarketingDragOver(event) {
        event.preventDefault();
        event.dataTransfer.dropEffect = 'move';
    }

    function handleMarketingDragEnter(event) {
        event.preventDefault();
        event.stopPropagation();

        if (!draggedMarketingFileData) return;

        const dropZone = event.currentTarget;
        const targetCategory = dropZone.getAttribute('data-category');
        const targetSubfolder = dropZone.getAttribute('data-subfolder') || '';

        if (targetCategory === draggedMarketingFileData.currentCategory &&
            targetSubfolder === draggedMarketingFileData.currentSubfolder) {
            return;
        }

        dropZone.classList.add('drag-over');
    }

    function handleMarketingDragLeave(event) {
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

    async function handleMarketingDrop(event) {
        event.preventDefault();
        event.stopPropagation();

        const dropZone = event.currentTarget;
        const targetCategory = dropZone.getAttribute('data-category');
        const targetSubfolder = dropZone.getAttribute('data-subfolder') || '';

        dropZone.classList.remove('drag-over');

        let fileData = null;

        if (supportsFileSystemAccessMarketing && event.dataTransfer.items.length > 0) {
            for (let i = 0; i < event.dataTransfer.items.length; i++) {
                const item = event.dataTransfer.items[i];

                try {
                    if (item.kind === 'file') {
                        const handle = await item.getAsFileSystemHandle();

                        if (handle && handle.kind === 'file') {
                            fileData = {
                                fileName: handle.name,
                                currentCategory: draggedMarketingFileData?.currentCategory,
                                currentSubfolder: draggedMarketingFileData?.currentSubfolder
                            };
                            break;
                        }
                    }
                } catch (error) {
                    console.warn("⚠️ File System Access API failed:", error);
                }
            }
        }

        if (!fileData && draggedMarketingFileData) {
            fileData = draggedMarketingFileData;
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

        await moveMarketingFile(fileData, targetCategory, targetSubfolder);
    }

    async function moveMarketingFile(fileData, targetCategory, targetSubfolder) {
        showStatusMessage('info', 'Přesouvám soubor...');

        const formData = new FormData();
        formData.append('fileName', fileData.fileName);
        formData.append('sourceCategory', fileData.currentCategory);
        formData.append('sourceSubfolder', fileData.currentSubfolder);
        formData.append('targetCategory', targetCategory);
        formData.append('targetSubfolder', targetSubfolder);
        formData.append('move_marketing_file', '1');

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

    function initializeMarketingFormHandlers() {
        // Marketing file input handler
        const fileInput = document.getElementById('marketing_file');
        const selectedFile = document.getElementById('selected-marketing-file');

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

        // Handle Marketing subfolder form submission
        const marketingSubfolderForm = document.getElementById('addMarketingSubfolderForm');
        if (marketingSubfolderForm) {
            marketingSubfolderForm.addEventListener('submit', function(e) {
                e.preventDefault();

                const formData = new FormData(this);
                
                // Debug logging
                console.log('Submitting marketing subfolder form:', formData.get('parent_category'), formData.get('subfolder_name'));

                fetch('add-marketing-subfolder.php', {
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
                        console.log('Raw response from add-marketing-subfolder.php:', text);
                        try {
                            const data = JSON.parse(text);
                            if (data && data.success) {
                                closeAddMarketingSubfolderModal();
                                showStatusMessage('success', data.message);
                                setTimeout(() => location.reload(), 500);
                            } else {
                                showStatusMessage('error', data.message || 'Unknown error occurred');
                            }
                        } catch (e) {
                            console.error('Invalid JSON response:', text);
                            showStatusMessage('error', 'Server returned invalid response: ' + text);
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        showStatusMessage('error', 'Došlo k chybě při vytváření podsložky: ' + error.message);
                    });
            });
        }

        // Handle edit Marketing subfolder form submission
        const editMarketingSubfolderForm = document.getElementById('editMarketingSubfolderForm');
        if (editMarketingSubfolderForm) {
            editMarketingSubfolderForm.addEventListener('submit', function(e) {
                e.preventDefault();

                const formData = new FormData(this);
                // 1. Identifikace pro univerzální handler (složka /marketing/)
                formData.append('type', 'marketing');

                const btn = this.querySelector('button[type="submit"]');
                const originalText = btn.innerHTML;
                btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Ukládá se...';
                btn.disabled = true;

                // 2. Míříme na společný handler se správnou hlavičkou
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
                            if (typeof closeEditMarketingSubfolderModal === 'function') closeEditMarketingSubfolderModal();
                            showStatusMessage('success', data.message);
                            // Reload je nutný pro aktualizaci názvů a cest v DOMu
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

        // Add file input handler for edit subfolder modal
        const editSubfolderFileInput = document.getElementById('editMarketingSubfolderFile');
        const editSubfolderSelectedFile = document.getElementById('edit-marketing-subfolder-selected-file');

        if (editSubfolderFileInput && editSubfolderSelectedFile) {
            editSubfolderFileInput.addEventListener('change', function() {
                if (this.files && this.files.length > 0) {
                    const file = this.files[0];
                    const fileSize = formatFileSize(file.size);
                    editSubfolderSelectedFile.textContent = `Vybraný soubor: ${file.name} (${fileSize})`;

                    const customFilenameInput = document.getElementById('editMarketingSubfolderCustomFilename');
                    if (customFilenameInput && !customFilenameInput.value.trim()) {
                        const nameWithoutExt = file.name.replace(/\.[^/.]+$/, "");
                        customFilenameInput.value = nameWithoutExt;
                    }
                } else {
                    editSubfolderSelectedFile.textContent = 'Žádný soubor nebyl vybrán';
                }
            });
        }

        // Add file input handler for edit marketing category modal
        const editMarketingCategoryFileInput = document.getElementById('editMarketingCategoryFile');
        const editMarketingCategorySelectedFile = document.getElementById('edit-marketing-category-selected-file');

        if (editMarketingCategoryFileInput && editMarketingCategorySelectedFile) {
            editMarketingCategoryFileInput.addEventListener('change', function() {
                if (this.files && this.files.length > 0) {
                    const file = this.files[0];
                    const fileSize = formatFileSize(file.size);
                    editMarketingCategorySelectedFile.textContent = `Vybraný soubor: ${file.name} (${fileSize})`;
                    
                    // Auto-fill custom filename if empty
                    const customFilenameInput = document.getElementById('editMarketingCategoryCustomFilename');
                    if (customFilenameInput && !customFilenameInput.value.trim()) {
                        const nameWithoutExt = file.name.replace(/\.[^/.]+$/, "");
                        customFilenameInput.value = nameWithoutExt;
                    }
                } else {
                    editMarketingCategorySelectedFile.textContent = 'Žádný soubor nebyl vybrán';
                }
            });
        }

        // Edit marketing file form
        const editMarketingFileFormEl = document.getElementById('editMarketingFileForm');
        if (editMarketingFileFormEl) {
            editMarketingFileFormEl.addEventListener('submit', function(e) {
                e.preventDefault();

                const formData = new FormData(this);
                formData.append('type', 'marketing'); // Identifikace pro handler
                formData.append('subfolder', '');     // Jsme v rootu kategorie

                const submitBtn = this.querySelector('button[type="submit"]');
                const originalText = submitBtn.innerHTML;
                submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
                submitBtn.disabled = true;

                fetch('edit-file-handler.php', { method: 'POST', body: formData })
                    .then(res => res.json())
                    .then(data => {
                        if (data.success) {
                            closeEditMarketingFileModal();
                            showStatusMessage('success', data.message);
                            setTimeout(() => location.reload(), 600);
                        } else {
                            alert(data.message);
                            submitBtn.disabled = false;
                            submitBtn.innerHTML = originalText;
                        }
                    })
                    .catch(err => console.error('Chyba:', err));
            });
        }

        // Edit marketing subfolder file form
        const editMarketingSubfolderFileFormEl = document.getElementById('editMarketingSubfolderFileForm');
        if (editMarketingSubfolderFileFormEl) {
            editMarketingSubfolderFileFormEl.addEventListener('submit', function(e) {
                e.preventDefault();

                const formData = new FormData(this);
                formData.append('type', 'marketing'); // Identifikace pro handler

                const submitBtn = this.querySelector('button[type="submit"]');
                const originalText = submitBtn.innerHTML;
                submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
                submitBtn.disabled = true;

                fetch('edit-file-handler.php', { method: 'POST', body: formData })
                    .then(res => res.json())
                    .then(data => {
                        if (data.success) {
                            closeEditMarketingSubfolderFileModal();
                            showStatusMessage('success', data.message);
                            setTimeout(() => location.reload(), 600);
                        } else {
                            alert(data.message);
                            submitBtn.disabled = false;
                            submitBtn.innerHTML = originalText;
                        }
                    })
                    .catch(err => console.error('Chyba:', err));
            });
        }
        // --- TENTO BLOK VLOŽ SEM (Handler pro kategorii) ---
        const editMarketingCategoryForm = document.getElementById('editMarketingCategoryForm');
        if (editMarketingCategoryForm) {
            editMarketingCategoryForm.addEventListener('submit', function(e) {
                e.preventDefault();

                const originalName = document.getElementById('editMarketingCategoryOriginalName').value;
                const newName = document.getElementById('editMarketingCategoryName').value;

                if (!originalName || !newName) {
                    alert('Chybí povinné údaje');
                    return;
                }

                const formData = new FormData(this);
                formData.append('type', 'marketing');
                formData.set('original_name', originalName);
                formData.set('new_name', newName);

                const submitBtn = this.querySelector('button[type="submit"]');
                const originalText = submitBtn.innerHTML;
                submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Ukládá se...';
                submitBtn.disabled = true;

                fetch('edit-category-handler.php', {
                    method: 'POST',
                    body: formData
                })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            closeEditMarketingCategoryModal();
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
        // --- KONEC BLOKU ---
    }
</script>