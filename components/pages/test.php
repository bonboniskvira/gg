<?php
// BUFFER OUTPUT IMMEDIATELY TO FIX "HEADERS ALREADY SENT"
ob_start();

// Set proper encoding for Czech characters  
mb_internal_encoding('UTF-8');

require_once 'access.php';

// Handle file move request
if (isset($_POST['move_test_file']) && isAdmin()) {
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
        $testBaseDir = getRoot('test');
        $sourcePath = $testBaseDir . $sourceCategory . '/' . ($sourceSubfolder ? $sourceSubfolder . '/' : '');
        $targetPath = $testBaseDir . $targetCategory . '/' . ($targetSubfolder ? $targetSubfolder . '/' : '');
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

// Helper function to get audio/video duration (basic estimation)
function getTestDuration($filePath, $fileExtension)
{
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

// Helper function to get file type info - renamed to avoid conflicts
function getTestFileTypeInfo($extension)
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

        // Presentation files
        case 'ppt':
        case 'pptx':
            return [
                'type' => 'presentation',
                'icon' => 'fas fa-file-powerpoint',
                'color' => '#d35400',
                'playable' => false
            ];

        // Image files
        case 'jpg':
        case 'jpeg':
        case 'png':
        case 'gif':
            return [
                'type' => 'image',
                'icon' => 'fas fa-image',
                'color' => '#2ecc71',
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

// Get existing categories - scan actual directories instead of predefined list
function getTestCategories()
{
    $testDir = getRoot('test');
    $categories = [];

    if (is_dir($testDir)) {
        $items = array_diff(scandir($testDir), ['.', '..']);

        foreach ($items as $item) {
            $itemPath = $testDir . $item;
            if (is_dir($itemPath)) {
                $categories[] = $item;
            }
        }
    }

    // Enhanced sorting with debugging - sort categories by leading numbers, then alphabetically
    usort($categories, function($a, $b) {
        // Extract leading numbers from category names
        preg_match('/^(\d+)/', $a, $matchesA);
        preg_match('/^(\d+)/', $b, $matchesB);
        
        $hasNumberA = !empty($matchesA[1]);
        $hasNumberB = !empty($matchesB[1]);
        
        if ($hasNumberA && $hasNumberB) {
            // Both have numbers - sort by number value
            $numA = (int)$matchesA[1];
            $numB = (int)$matchesB[1];
            return $numA - $numB;
        } elseif ($hasNumberA && !$hasNumberB) {
            // A has number, B doesn't - numbered categories come first
            return -1;
        } elseif (!$hasNumberA && $hasNumberB) {
            // A doesn't have number, B has - numbered categories come first
            return 1;
        } else {
            // Neither has numbers - sort alphabetically
            return strcasecmp($a, $b);
        }
    });

    return $categories;
}

// Function to scan for subfolders and TEST files - simplified without predefined categories
function scanTestRecursive($dir, $categoryKey, $depth = 0)
{
    $result = ['folders' => [], 'documents' => []];

    if (!is_dir($dir)) return $result;

    $items = array_diff(scandir($dir), ['.', '..']);

    foreach ($items as $item) {
        $itemPath = $dir . $item;

        if (is_dir($itemPath)) {
            // It's a subfolder
            $subResult = scanTestRecursive($itemPath . '/', $categoryKey, $depth + 1);
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
            $allowedExtensions = ['mp3', 'wav', 'ogg', 'm4a', 'aac', 'mp4', 'avi', 'mov', 'wmv', 'flv', 'webm', 'mkv', 'pdf', 'txt', 'doc', 'docx', 'rtf', 'ppt', 'pptx', 'png', 'jpg', 'jpeg', 'gif'];

            if (in_array($extension, $allowedExtensions)) {
                $fileTypeInfo = getTestFileTypeInfo($extension);
                $duration = getTestDuration($itemPath, $extension);

                $fileData = [
                    'name' => pathinfo($item, PATHINFO_FILENAME),
                    'original_name' => $item,
                    'file_path' => str_replace($_SERVER['DOCUMENT_ROOT'], '', $itemPath),
                    'file_size' => filesize($itemPath),
                    'file_extension' => $extension,
                    'date_added' => filemtime($itemPath),
                    'duration' => $duration,
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
    function countAllDocuments($categoryData) {
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

// Function to render TEST subfolder
function renderTestSubfolder($folderName, $folderData, $categoryKey, $categoryInfo)
{
?>
    <div class="test-subfolder-card droppable-zone" 
         data-category="<?= htmlspecialchars($categoryKey) ?>"
         data-subfolder="<?= htmlspecialchars($folderName) ?>"
         onclick="toggleTestSubfolder('<?= $categoryKey ?>-<?= $folderName ?>')"
         ondrop="handleTestDrop(event)" 
         ondragover="handleTestDragOver(event)"
         ondragenter="handleTestDragEnter(event)"
         ondragleave="handleTestDragLeave(event)">
        
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
                    <button class="mini-btn edit-btn" onclick="event.stopPropagation(); editTestSubfolder('<?= $categoryKey ?>', '<?= $folderName ?>')" title="Upravit podsložku">
                        <i class="fas fa-edit"></i>
                    </button>
                    <button class="mini-btn delete-btn" onclick="event.stopPropagation(); deleteTestSubfolder('<?= $categoryKey ?>', '<?= $folderName ?>')" title="Smazat podsložku">
                        <i class="fas fa-trash"></i>
                    </button>
                <?php endif; ?>
                <i class="fas fa-chevron-right subfolder-toggle"></i>
            </div>
        </div>

        <div class="subfolder-content">
            <div class="subfolder-documents">
                <?php if (!empty($folderData['data']['documents'])): ?>
                    <?php foreach ($folderData['data']['documents'] as $test): ?>
                        <div class="subfolder-document draggable-file"
                             draggable="true"
                             data-file-name="<?= htmlspecialchars($test['original_name']) ?>"
                             data-current-category="<?= htmlspecialchars($categoryKey) ?>"
                             data-current-subfolder="<?= htmlspecialchars($folderName) ?>"
                             ondragstart="handleTestDragStart(event)"
                             ondragend="handleTestDragEnd(event)">
                            
                            <div class="doc-icon">
                                <i class="<?= $test['file_type_info']['icon'] ?>" style="color: <?= $test['file_type_info']['color'] ?>;"></i>
                                <i class="fas fa-arrows-alt drag-handle"></i>
                            </div>
                            <div class="doc-info">
                                <span class="doc-name"><?= htmlspecialchars($test['name']) ?></span>
                                <span class="doc-size"><?= formatBytes($test['file_size']) ?></span>
                            </div>
                            <div class="doc-actions">
                                <?php if ($test['file_type_info']['playable']): ?>
                                    <button class="mini-btn play-btn" onclick="event.stopPropagation(); toggleTest(this, '<?= htmlspecialchars($test['file_path']) ?>', '<?= $test['file_type_info']['type'] ?>')" title="Přehrát">
                                        <i class="fas fa-play"></i>
                                    </button>
                                <?php endif; ?>

                                <?php if ($test['file_extension'] === 'pdf'): ?>
                                    <a href="<?= htmlspecialchars($test['file_path']) ?>" target="_blank" class="mini-btn view-btn" onclick="event.stopPropagation();" title="Zobrazit">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                <?php endif; ?>

                                <a href="<?= htmlspecialchars($test['file_path']) ?>" download class="mini-btn download-btn" onclick="event.stopPropagation();" title="Stáhnout">
                                    <i class="fas fa-download"></i>
                                </a>

                                <?php if (isAdmin()): ?>
                                    <button class="mini-btn edit-btn" onclick="event.stopPropagation(); editTestFile('<?= addslashes($test['original_name']) ?>', '<?= $categoryKey ?>', '<?= addslashes($folderName) ?>', '<?= addslashes($test['name']) ?>')" title="Přejmenovat">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <button class="mini-btn delete-btn" onclick="event.stopPropagation(); deleteTestFile('<?= addslashes($test['original_name']) ?>', '<?= $categoryKey ?>', '<?= addslashes($folderName) ?>')" title="Smazat">
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

// Function to render TEST card
function renderTestCard($test, $categoryKey)
{
?>
    <div class="test-card-grid draggable-file"
         draggable="true" 
         data-file-name="<?= htmlspecialchars($test['original_name']) ?>"
         data-current-category="<?= htmlspecialchars($categoryKey) ?>"
         data-current-subfolder=""
         ondragstart="handleTestDragStart(event)"
         ondragend="handleTestDragEnd(event)">
        <div class="test-preview">
            <i class="fas fa-arrows-alt drag-handle"></i>
            <div class="file-preview-icon">
                <i class="<?= $test['file_type_info']['icon'] ?>" style="color: <?= $test['file_type_info']['color'] ?>; font-size: 32px;"></i>
            </div>
        </div>

        <div class="test-info-grid">
            <h5 class="test-title"><?= htmlspecialchars($test['name']) ?></h5>

            <div class="test-meta-grid">
                <span class="test-date">
                    <i class="fas fa-calendar"></i>
                    <?= date('d.m.Y', $test['date_added']) ?>
                </span>
                <span class="test-size">
                    <i class="fas fa-file"></i>
                    <?= formatBytes($test['file_size']) ?>
                </span>
                <?php if ($test['duration']): ?>
                    <span class="test-duration">
                        <i class="fas fa-clock"></i>
                        <?= $test['duration'] ?>
                    </span>
                <?php endif; ?>
            </div>

            <div class="test-actions-grid">
                <?php if ($test['file_type_info']['playable']): ?>
                    <button class="grid-btn play-btn" onclick="toggleTest(this, '<?= $test['file_path'] ?>', '<?= $test['file_type_info']['type'] ?>')">
                        <i class="fas fa-play"></i>
                    </button>
                <?php endif; ?>

                <?php if ($test['file_extension'] === 'pdf'): ?>
                    <a href="<?= htmlspecialchars($test['file_path']) ?>" target="_blank" class="grid-btn view-btn">
                        <i class="fas fa-eye"></i>
                    </a>
                <?php endif; ?>

                <a href="<?= htmlspecialchars($test['file_path']) ?>" download class="grid-btn download-btn">
                    <i class="fas fa-download"></i>
                </a>

                <?php if (isAdmin()): ?>
                    <button class="grid-btn edit-btn" onclick="editTestFile('<?= addslashes($test['original_name']) ?>', '<?= $categoryKey ?>', '<?= addslashes($test['name']) ?>')">
                        <i class="fas fa-edit"></i>
                    </button>
                    <button class="grid-btn delete-btn" onclick="deleteTest('<?= addslashes($test['original_name']) ?>', '<?= $categoryKey ?>')">
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
    /* TEST page styling - copied and adapted from EDO */
    #test .content-area {
        display: flex;
        flex-direction: column;
        gap: 30px;
    }

    .add-test-section {
        background: white;
        border-radius: 10px;
        padding: 25px;
        margin-bottom: 25px;
        box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
    }

    .add-test-section h2 {
        color: #2c3e50;
        margin-top: 0;
        margin-bottom: 20px;
        font-size: 20px;
    }

    .test-form {
        display: flex;
        flex-direction: column;
        gap: 15px;
    }

    .test-grid {
        display: flex;
        flex-direction: column;
        gap: 20px;
    }

    .test-category {
        background: white;
        border-radius: 10px;
        box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        overflow: hidden;
        transition: all 0.3s ease;
    }

    .test-category:hover {
        box-shadow: 0 5px 15px rgba(0, 0, 0, 0.15);
    }

    /* FIX: Define Grid for subfolders so expansion works */
    .test-subfolders-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
        gap: 12px;
    }

    .test-subfolder-card {
        background: #f8f9fa;
        border-radius: 8px;
        padding: 15px;
        /* margin-bottom: 15px; Removed to let grid gap handle spacing */
        cursor: pointer;
        transition: all 0.3s ease;
        position: relative;
    }

    .test-subfolder-card:hover {
        background: #e9ecef;
        transform: translateY(-1px);
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
    }

    .test-subfolder-card.open {
        /* FIX: Expand full width */
        grid-column: 1 / -1;
        width: 100%;
        max-width: none;
        background: #fff;
        border: 1px solid #dee2e6;
    }

    .test-subfolder-card.open .subfolder-content {
        max-height: 1000px;
        margin-top: 15px;
        padding-top: 15px;
        border-top: 1px solid #dee2e6;
    }

    .test-subfolder-card.open .subfolder-toggle {
        transform: rotate(90deg);
    }

    .subfolder-content {
        max-height: 0;
        overflow: hidden;
        transition: max-height 0.3s ease, margin-top 0.3s ease, padding-top 0.3s ease;
        margin-top: 0;
        padding-top: 0;
    }

    /* FIX: Proper grid for documents inside subfolder */
    .subfolder-documents {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
        gap: 10px;
    }
    .test-grid-container {
        height: auto !important;
        min-height: 0 !important;
        flex-grow: 0; /* Zabrání natahování ve flexu */
        align-self: flex-start; /* Zarovná se na začátek a nebude se natahovat na celou výšku */
        width: 100%;
    }
    .empty-subfolder {
        text-align: center;
        padding: 20px;
        color: #6c757d;
        font-style: italic;
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: 8px;
    }

    .subfolder-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 10px;
    }

    .subfolder-info {
        display: flex;
        align-items: center;
    }

    .subfolder-info i {
        font-size: 24px;
        margin-right: 10px;
    }

    .subfolder-stats {
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .subfolder-toggle {
        font-size: 18px;
        color: #007bff;
        cursor: pointer;
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
        width: 100%; /* Fix width */
        box-sizing: border-box;
    }

    .subfolder-document:hover {
        transform: translateY(-2px);
    }

    .doc-icon {
        font-size: 28px;
        margin-right: 10px;
        position: relative;
    }

    .doc-info {
        flex-grow: 1;
    }

    .doc-name {
        font-weight: bold;
        color: #2c3e50;
    }

    .doc-size {
        font-size: 12px;
        color: #7f8c8d;
    }

    .doc-actions {
        display: flex;
        align-items: center;
        gap: 5px;
    }

    .mini-btn {
        background: transparent;
        border: none;
        cursor: pointer;
        transition: color 0.2s;
    }

    .mini-btn:hover {
        color: #007bff;
    }

    .grid-btn {
        background: #007bff;
        color: white;
        border: none;
        padding: 8px 12px;
        border-radius: 4px;
        cursor: pointer;
        transition: background 0.2s;
    }

    .grid-btn:hover {
        background: #0056b3;
    }

    .no-test {
        text-align: center;
        color: #7f8c8d;
        font-style: italic;
    }

    .categories-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
        gap: 20px;
        grid-auto-flow: row;
        margin-bottom: 30px;
    }

    @media (max-width: 768px) {
        .categories-grid {
            grid-template-columns: 1fr;
        }
    }

    @media (max-width: 1200px) {
        .add-test-section {
            width: 100%;
            height: fit-content;
        }

        .test-grid {
            grid-template-columns: 1fr;
        }
    }

    .add-category-section {
        background: white;
        border-radius: 10px;
        padding: 25px;
        margin-bottom: 25px;
        box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
    }

    .add-category-section h2 {
        color: #2c3e50;
        margin-top: 0;
        margin-bottom: 20px;
        font-size: 20px;
    }

    .category-management {
        display: flex;
        flex-direction: column;
        gap: 15px;
    }

    .category-management .form-group {
        margin-bottom: 0;
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

    .category-header .test-count {
        font-size: 14px;
        color: #666;
    }



    .test-documents-section {
        margin-top: 15px;
    }

    .test-documents-section h4 {
        font-size: 16px;
        margin-bottom: 10px;
        color: #2c3e50;
    }

    .test-documents-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
        gap: 15px;
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

    /* Media player modal */
    .modal {
        display: none;
        position: fixed;
        z-index: 1000;
        left: 0;
        top: 0;
        width: 100%;
        height: 100%;
        overflow: hidden;
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

    .media-player-body {
        display: flex;
        flex-direction: column;
        align-items: center;
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

    /* Responsive adjustments */
    @media (max-width: 768px) {
        .test-documents-grid {
            grid-template-columns: 1fr;
        }

        .modal-content {
            width: 95%;
        }
    }

    /* Fix the category toggle functionality */
    .test-category {
        background: white;
        border-radius: 10px;
        box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        overflow: hidden;
        transition: all 0.3s ease;
    }

    .test-category.expanded {
        grid-column: 1 / -1;
        max-width: none;
        transform: none !important;
        z-index: 10;
    }

    .test-category.expanded .category-content {
        max-height: none !important;
        padding: 20px !important;
        display: block !important;
        opacity: 1 !important;
        overflow: visible !important;
    }

    .test-category.expanded .category-toggle i {
        transform: rotate(180deg);
    }

    .test-category:hover:not(.expanded) {
        transform: translateY(-2px);
        box-shadow: 0 5px 15px rgba(0, 0, 0, 0.15);
    }

    .test-category:not(.expanded) .category-content {
        max-height: 0 !important;
        padding: 0 20px !important;
        overflow: hidden !important;
        opacity: 0 !important;
        display: none !important;
    }

    .category-toggle i {
        transition: transform 0.3s ease;
    }

    /* Make sure the content inside is visible */
    .category-content {
        transition: all 0.3s ease;
        background: white;
    }

    .test-documents-section {
        margin-top: 0 !important;
        padding-top: 0 !important;
    }

    .test-documents-section h4 {
        font-size: 16px;
        margin-bottom: 15px;
        color: #2c3e50;
        font-weight: 600;
    }

    .test-documents-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
        gap: 15px;
    }

    /* Fix the test cards to be visible */
    .test-card-grid {
        background: white;
        border: 1px solid #e9ecef;
        border-radius: 8px;
        overflow: hidden;
        transition: all 0.2s;
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
        opacity: 1 !important;
        display: block !important;
        position: relative;
    }

    .test-card-grid:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
        border-color: #dee2e6;
    }

    .test-preview {
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

    .test-info-grid {
        padding: 15px;
        display: flex;
        flex-direction: column;
        gap: 8px;
    }

    .test-title {
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

    .test-meta-grid {
        display: flex;
        justify-content: space-between;
        align-items: center;
        font-size: 11px;
        color: #6c757d;
        margin-top: auto;
        flex-wrap: wrap;
        gap: 8px;
    }

    .test-date,
    .test-size,
    .test-duration {
        display: flex;
        align-items: center;
        gap: 4px;
    }

    .test-actions-grid {
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

    .add-test-subfolder-btn {
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

    .add-test-subfolder-btn:hover {
        background: #138496;
    }

    /* Drag and Drop Styles */
    .test-card-grid.dragging,
    .subfolder-document.dragging {
        opacity: 0.5;
        transform: scale(0.95);
        cursor: grabbing;
    }

    .test-subfolder-card.drag-over {
        background: #e3f2fd !important;
        border-color: #2196f3 !important;
        box-shadow: 0 0 15px rgba(33, 150, 243, 0.3) !important;
    }

    .test-category.drag-over {
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

    .test-subfolder-card.drag-over .drop-zone-indicator,
    .test-category.drag-over .drop-zone-indicator {
        display: flex;
    }

    .test-subfolder-card {
        position: relative;
    }

    .test-category {
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

<div class="page-content" id="test">
    <div class="content-area">
        <!-- Status Messages -->
        <div id="status-messages"></div>

        <!-- TEST Grid Container -->
        <div class="test-grid-container">
            <?php
            $testDir = getRoot('test');
            $testByCategory = [];

            // Scan for TEST files and subfolders - updated to use actual directories
            if (is_dir($testDir)) {
                $categories = getTestCategories();
                
                // Debug: Log all found categories
                error_log("TEST Categories found: " . implode(', ', $categories));

                foreach ($categories as $categoryKey) {
                    $categoryDir = $testDir . $categoryKey . '/';
                    if (is_dir($categoryDir)) {
                        $categoryData = scanTestRecursive($categoryDir, $categoryKey);
                        // PŘIDAT TUTO PODMÍNKU:
                        if (!empty($categoryData['documents']) || !empty($categoryData['folders'])) {
                            $testByCategory[$categoryKey] = [
                                    'info' => ['name' => $categoryKey, 'color' => '#3498db'],
                                    'data' => $categoryData
                            ];
                        }
                    }
                }
                
                // Debug: Log processed categories
                error_log("TEST Categories processed: " . implode(', ', array_keys($testByCategory)));
            }

            if (empty($testByCategory)): ?>
                <div class="no-test">
                    <i class="fas fa-file-audio"></i>
                    <p>Zatím nejsou žádné soubory v systému.</p>
                </div>
            <?php else: ?>
                <div class="categories-grid">
                    <?php foreach ($testByCategory as $categoryKey => $category): ?>
                        <div class="test-category droppable-zone" 
                             data-category="<?= htmlspecialchars($categoryKey, ENT_QUOTES, 'UTF-8') ?>" 
                             style="border-top: 4px solid <?= $category['info']['color'] ?>"
                             ondrop="handleTestDrop(event)" 
                             ondragover="handleTestDragOver(event)"
                             ondragenter="handleTestDragEnter(event)"
                             ondragleave="handleTestDragLeave(event)">
                            
                            <div class="drop-zone-indicator">
                                <i class="fas fa-file-import"></i> &nbsp; Přetáhněte soubor do hlavní složky
                            </div>

                            <div class="category-header category-header-clickable" onclick="toggleTestCategoryHeader('<?= htmlspecialchars($categoryKey, ENT_QUOTES, 'UTF-8') ?>')">
                                <div class="category-info">
                                    <h3 style="color: <?= $category['info']['color'] ?>">
                                        <i class="fas fa-folder"></i>
                                        <?= htmlspecialchars($category['info']['name'], ENT_QUOTES, 'UTF-8') ?>
                                    </h3>
                                    <span class="test-count">
                                        <?= count($category['data']['documents']) ?> souborů
                                        <?php if (!empty($category['data']['folders'])): ?>
                                            • <?= count($category['data']['folders']) ?> podsložek
                                        <?php endif; ?>
                                    </span>
                                </div>

                                <?php if (isAdmin()): ?>
                                    <div class="category-admin-actions" onclick="event.stopPropagation()">
                                        <button class="admin-btn edit-btn" onclick="editTestCategory('<?= htmlspecialchars($categoryKey, ENT_QUOTES, 'UTF-8') ?>')" title="Přejmenovat kategorii">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <button class="admin-btn delete-btn" onclick="deleteTestCategory('<?= htmlspecialchars($categoryKey, ENT_QUOTES, 'UTF-8') ?>')" title="Smazat kategorii">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </div>
                                <?php endif; ?>

                                <div class="category-toggle">
                                    <i class="fas fa-chevron-down"></i>
                                </div>
                            </div>

                            <div class="category-content" id="test-category-content-<?= htmlspecialchars($categoryKey, ENT_QUOTES, 'UTF-8') ?>">
                                <?php if (canUpload()): ?>
                                    <div class="category-actions">
                                        <button class="action-btn add-test-subfolder-btn" onclick="showAddTestSubfolderModal('<?= $categoryKey ?>')">
                                            <i class="fas fa-folder-plus"></i> Přidat podsložku
                                        </button>
                                    </div>
                                <?php endif; ?>

                                <!-- Subfolders Grid -->
                                <?php if (!empty($category['data']['folders'])): ?>
                                    <div class="test-subfolders-section">
                                        <h4>Podsložky</h4>
                                        <div class="test-subfolders-grid">
                                            <?php foreach ($category['data']['folders'] as $folderName => $folderData): ?>
                                                <?php renderTestSubfolder($folderName, $folderData, $categoryKey, $category['info']); ?>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                <?php endif; ?>

                                <!-- TEST files in main category -->
                                <?php if (!empty($category['data']['documents'])): ?>
                                    <div class="test-documents-section">
                                        <h4>Soubory</h4>
                                        <div class="test-documents-grid">
                                            <?php foreach ($category['data']['documents'] as $test): ?>
                                                <?php renderTestCard($test, $categoryKey); ?>
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

        <!-- Add TEST Section - Merged with category creation like documents.php -->
        <?php if (canUpload()): ?>
            <div class="add-test-section">
                <h2>Nahrát soubor</h2>
                <form action="upload-category-form-handler.php" method="post" enctype="multipart/form-data" class="test-form">

                    <input type="hidden" name="type" value="test">
                    <input type="hidden" name="redirect" value="test">

                    <div class="form-row">
                        <div class="form-group">
                            <label for="custom-folder-name">Vytvořit novou kategorii (volitelné)</label>
                            <input type="text" id="custom-folder-name" name="custom_category" class="full-width"
                                   placeholder="Zadejte název pro novou kategorii..."
                                   onchange="toggleTestFolderInputs()"
                                   oninput="toggleTestFolderInputs()">
                            <small class="file-help">Pokud zadáte název, vytvoří se nová kategorie s tímto názvem</small>
                        </div>
                    </div>

                    <div class="form-row" id="existing-folder-row">
                        <div class="form-group">
                            <label for="test-category">Nebo vyberte existující kategorii</label>
                            <select id="test-category" name="category" class="full-width">
                                <option value="">-- Vyberte kategorii --</option>
                                <?php
                                $existingCategories = getTestCategories();
                                foreach ($existingCategories as $category): ?>
                                    <option value="<?= htmlspecialchars($category, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($category, ENT_QUOTES, 'UTF-8') ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="test_file">Soubor *</label>
                            <div class="upload-area">
                                <label for="test_file" class="custom-file-button">Vyberte soubor</label>
                                <input type="file" name="file" id="test_file" accept=".mp3,.wav,.ogg,.m4a,.aac,.mp4,.avi,.mov,.wmv,.flv,.webm,.mkv,.pdf,.txt,.doc,.docx,.rtf,.ppt,.pptx,.png,.jpg,.jpeg" class="hidden-file-input" required>
                                <div id="selected-file" class="selected-files-info"></div>
                            </div>
                            <small class="file-help">Podporované formáty: Audio, Video, Dokumenty, Prezentace, Obrázky (max 500MB)</small>
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

<!-- Media Player Modal -->
<div id="mediaPlayerModal" class="modal" style="display: none;">
    <div class="modal-content media-modal">
        <div class="modal-header">
            <h2 id="playerTitle">Přehrávač médií</h2>
            <button class="modal-close" onclick="closeMediaPlayer()">&times;</button>
        </div>
        <div class="media-player-body">
            <!-- Audio Player -->
            <audio id="globalAudioPlayer" controls preload="metadata" style="width: 100%; display: none;">
                Your browser does not support the audio element.
            </audio>

            <!-- Video Player -->
            <video id="globalVideoPlayer" controls preload="metadata" style="width: 100%; max-height: 70vh; display: none;">
                Your browser does not support the video element.
            </video>
        </div>
    </div>
</div>

<!-- Edit Category Modal -->
<div id="editCategoryModal" class="modal" style="display: none;">
    <div class="modal-content">
        <div class="modal-header">
            <h2>Upravit kategorii</h2>
            <button class="modal-close" onclick="closeEditCategoryModal()">&times;</button>
        </div>
        <form id="editCategoryForm" enctype="multipart/form-data">
            <input type="hidden" id="editOriginalName" name="original_name">

            <div class="form-group">
                <label for="editCategoryName">Název kategorie *</label>
                <input type="text" id="editCategoryName" name="new_name" required
                    placeholder="Nový název kategorie" class="full-width">
            </div>

            <div class="form-group">
                <label for="editCategoryFile">Nahrát soubor do kategorie (volitelné)</label>
                <div class="upload-area">
                    <label for="editCategoryFile" class="custom-file-button">Vyberte soubor</label>
                    <input type="file" name="category_file" id="editCategoryFile" accept=".mp3,.wav,.ogg,.m4a,.aac,.mp4,.avi,.mov,.wmv,.flv,.webm,.mkv,.pdf,.txt,.doc,.docx,.rtf,.ppt,.pptx,.png,.jpg,.jpeg" class="hidden-file-input">
                    <div id="edit-category-selected-file" class="selected-files-info"></div>
                </div>
                <small class="file-help">Podporované formáty: Audio, Video, PDF, Text (max 500MB)</small>
            </div>

            <div class="form-group">
                <label for="editCategoryCustomFilename">Přejmenovat soubor (volitelné)</label>
                <input type="text" id="editCategoryCustomFilename" name="custom_filename" class="full-width" placeholder="Ponechte prázdné pro původní název">
                <small class="file-help">Zadejte pouze název bez přípony (přípona se doplní automaticky)</small>
            </div>

            <div class="modal-actions">
                <button type="button" class="btn btn-secondary" onclick="closeEditCategoryModal()">Zrušit</button>
                <button type="submit" class="btn btn-primary">Uložit změny</button>
            </div>
        </form>
    </div>
</div>

<!-- Add TEST Subfolder Modal -->
<div id="addTestSubfolderModal" class="modal" style="display: none;">
    <div class="modal-content">
        <div class="modal-header">
            <h2>Přidat podsložku</h2>
            <button class="modal-close" onclick="closeAddTestSubfolderModal()">&times;</button>
        </div>
        <form id="addTestSubfolderForm">
            <input type="hidden" id="testParentCategory" name="parent_category">

            <div class="form-group">
                <label for="testSubfolderName">Název podsložky *</label>
                <input type="text" id="testSubfolderName" name="subfolder_name" required
                    placeholder="Název nové podsložky" class="full-width">
            </div>

            <div class="modal-actions">
                <button type="button" class="btn btn-secondary" onclick="closeAddTestSubfolderModal()">Zrušit</button>
                <button type="submit" class="btn btn-primary">Vytvořit podsložku</button>
            </div>
        </form>
    </div>
</div>

<!-- Edit TEST Subfolder Modal -->
<div id="editTestSubfolderModal" class="modal" style="display: none;">
    <div class="modal-content">
        <div class="modal-header">
            <h2>Upravit TEST podsložku</h2>
            <button class="modal-close" onclick="closeEditTestSubfolderModal()">&times;</button>
        </div>
        <form id="editTestSubfolderForm" enctype="multipart/form-data">
            <input type="hidden" id="editTestParentCategory" name="parent_category">
            <input type="hidden" id="editTestOriginalName" name="original_name">

            <div class="form-group">
                <label for="editTestSubfolderName">Název podsložky *</label>
                <input type="text" id="editTestSubfolderName" name="new_name" required
                    placeholder="Nový název podsložky" class="full-width">
            </div>

            <div class="form-group">
                <label for="editTestSubfolderFile">Nahrát soubor do podsložky (volitelné)</label>
                <div class="upload-area">
                    <label for="editTestSubfolderFile" class="custom-file-button">Vyberte soubor</label>
                    <input type="file" name="subfolder_file" id="editTestSubfolderFile" accept=".mp3,.wav,.ogg,.m4a,.aac,.mp4,.avi,.mov,.wmv,.flv,.webm,.mkv,.pdf,.txt,.doc,.docx,.rtf,.ppt,.pptx,.png,.jpg,.jpeg" class="hidden-file-input">
                    <div id="edit-test-subfolder-selected-file" class="selected-files-info"></div>
                </div>
                <small class="file-help">Podporované formáty: Audio, Video, PDF, Text (max 500MB)</small>
            </div>

            <div class="form-group">
                <label for="editTestSubfolderCustomFilename">Přejmenovat soubor (volitelné)</label>
                <input type="text" id="editTestSubfolderCustomFilename" name="custom_filename" class="full-width" placeholder="Ponechte prázdné pro původní název">
                <small class="file-help">Zadejte pouze název bez přípony (přípona se doplní automaticky)</small>
            </div>

            <div class="modal-actions">
                <button type="button" class="btn btn-secondary" onclick="closeEditTestSubfolderModal()">Zrušit</button>
                <button type="submit" class="btn btn-primary">Uložit změny</button>
            </div>
        </form>
    </div>
</div>

<!-- Edit Test File Modal - ADD MISSING HIDDEN FIELD -->
<div id="editTestFileModal" class="modal" style="display: none;">
    <div class="modal-content">
        <div class="modal-header">
            <h2>Přejmenovat soubor</h2>
            <button class="modal-close" onclick="closeEditTestFileModal()">&times;</button>
        </div>
        <form id="editTestFileForm">
            <input type="hidden" id="editTestFileCategory" name="category">
            <input type="hidden" id="editTestFileSubfolder" name="subfolder">
            <input type="hidden" id="editTestFileOriginalName" name="original_name">

            <div class="form-group">
                <label for="editTestFileName">Název souboru *</label>
                <input type="text" id="editTestFileName" name="new_name" required
                    placeholder="Nový název souboru (bez přípony)" class="full-width">
                <small class="file-help">Zadejte pouze název bez přípony - přípona zůstane zachována</small>
            </div>

            <div class="modal-actions">
                <button type="button" class="btn btn-secondary" onclick="closeEditTestFileModal()">Zrušit</button>
                <button type="submit" class="btn btn-primary">Přejmenovat</button>
            </div>
        </form>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Only run if we're on the test page
        if (!document.getElementById('test')) {
            return;
        }

        // Initialize form handlers
        initializeTestFormHandlers();
        
        // Handle status messages from URL
        handleStatusMessages();
        
        // Initialize modal close button event listeners
        initializeModalCloseButtons();
    });

    // Add function to initialize modal close button event listeners
    function initializeModalCloseButtons() {
        // Close buttons for all modals
        const modalCloseButtons = document.querySelectorAll('.modal-close');
        modalCloseButtons.forEach(button => {
            button.addEventListener('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                const modal = this.closest('.modal');
                if (modal) {
                    modal.style.display = 'none';
                    
                    // Reset specific modals
                    if (modal.id === 'mediaPlayerModal') {
                        closeMediaPlayer();
                    } else if (modal.id === 'editCategoryModal') {
                        closeEditCategoryModal();
                    } else if (modal.id === 'addTestSubfolderModal') {
                        closeAddTestSubfolderModal();
                    } else if (modal.id === 'editTestSubfolderModal') {
                        closeEditTestSubfolderModal();
                    } else if (modal.id === 'editTestFileModal') {
                        closeEditTestFileModal();
                    }
                }
            });
        });
        
        // Click outside modal to close
        const modals = document.querySelectorAll('.modal');
        modals.forEach(modal => {
            modal.addEventListener('click', function(e) {
                if (e.target === modal) {
                    modal.style.display = 'none';
                    
                    // Reset specific modals
                    if (modal.id === 'mediaPlayerModal') {
                        closeMediaPlayer();
                    } else if (modal.id === 'editCategoryModal') {
                        closeEditCategoryModal();
                    } else if (modal.id === 'addTestSubfolderModal') {
                        closeAddTestSubfolderModal();
                    } else if (modal.id === 'editTestSubfolderModal') {
                        closeEditTestSubfolderModal();
                    } else if (modal.id === 'editTestFileModal') {
                        closeEditTestFileModal();
                    }
                }
            });
        });
        
        // ESC key to close modals
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                const openModal = document.querySelector('.modal[style*="flex"], .modal[style*="block"]');
                if (openModal) {
                    openModal.style.display = 'none';
                    
                    // Reset specific modals
                    if (openModal.id === 'mediaPlayerModal') {
                        closeMediaPlayer();
                    } else if (openModal.id === 'editCategoryModal') {
                        closeEditCategoryModal();
                    } else if (openModal.id === 'addTestSubfolderModal') {
                        closeAddTestSubfolderModal();
                    } else if (openModal.id === 'editTestSubfolderModal') {
                        closeEditTestSubfolderModal();
                    } else if (openModal.id === 'editTestFileModal') {
                        closeEditTestFileModal();
                    }
                }
            }
        });
    }

    // Simple toggle for test categories - matches documents.php pattern
    function toggleTestCategoryHeader(categoryKey) {
        // Check if we're on the test page
        if (!document.getElementById('test')) {
            return;
        }

        const categoryCard = document.querySelector(`#test [data-category="${categoryKey}"]`);

        if (!categoryCard) {
            console.warn('Could not find category card for:', categoryKey);
            return;
        }

        // Simple logic: if it has 'expanded' class, remove it. If not, add it.
        if (categoryCard.classList.contains('expanded')) {
            categoryCard.classList.remove('expanded');
        } else {
            // First remove 'expanded' from all other cards
            document.querySelectorAll('#test .test-category.expanded').forEach(card => {
                card.classList.remove('expanded');
            });
            // Then add 'expanded' to this card
            categoryCard.classList.add('expanded');
        }
    }

    // Simple media player
    function toggleTest(button, filePath, fileType) {
        const modal = document.getElementById('mediaPlayerModal');
        const audioPlayer = document.getElementById('globalAudioPlayer');
        const videoPlayer = document.getElementById('globalVideoPlayer');
        const title = document.getElementById('playerTitle');

        if (!modal || !audioPlayer || !videoPlayer) return;

        // Hide both players
        audioPlayer.style.display = 'none';
        videoPlayer.style.display = 'none';

        if (fileType === 'audio') {
            audioPlayer.src = filePath;
            audioPlayer.style.display = 'block';
            title.textContent = 'Přehrávač audia';
        } else if (fileType === 'video') {
            videoPlayer.src = filePath;
            videoPlayer.style.display = 'block';
            title.textContent = 'Přehrávač videa';
        }

        modal.style.display = 'flex';
    }

    function closeMediaPlayer() {
        const modal = document.getElementById('mediaPlayerModal');
        const audioPlayer = document.getElementById('globalAudioPlayer');
        const videoPlayer = document.getElementById('globalVideoPlayer');

        if (audioPlayer) {
            audioPlayer.pause();
            audioPlayer.src = '';
            audioPlayer.style.display = 'none';
        }

        if (videoPlayer) {
            videoPlayer.pause();
            videoPlayer.src = '';
            videoPlayer.style.display = 'none';
        }

        if (modal) {
            modal.style.display = 'none';
        }
    }

    // Simple delete function - fix syntax error
    function deleteTest(fileName, category) {
        if (!confirm('Opravdu chcete smazat tento soubor?')) return;

        fetch('delete-test.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded'
                },
                body: 'test_file=' + encodeURIComponent(fileName) + '&category=' + encodeURIComponent(category)
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    location.reload();
                } else {
                    alert('Chyba: ' + (data.message || 'Neznámá chyba'));
                }
            })
            .catch(() => alert('Chyba při mazání souboru'));
    }

    // Category management
    function editTestCategory(categoryKey) {
        if (event) event.stopPropagation();

        const modal = document.getElementById('editCategoryModal');
        const originalInput = document.getElementById('editOriginalName');
        const nameInput = document.getElementById('editCategoryName');

        if (originalInput) originalInput.value = categoryKey;
        if (nameInput) nameInput.value = categoryKey;
        if (modal) modal.style.display = 'flex';
    }

    function closeEditCategoryModal() {
        const modal = document.getElementById('editCategoryModal');
        if (modal) modal.style.display = 'none';
        
        const form = document.getElementById('editCategoryForm');
        if (form) form.reset();
        
        // Clear file input displays
        const selectedFileDiv = document.getElementById('edit-category-selected-file');
        if (selectedFileDiv) selectedFileDiv.textContent = '';
        
        const customFilenameInput = document.getElementById('editCategoryCustomFilename');
        if (customFilenameInput) customFilenameInput.value = '';
    }

    function deleteTestCategory(categoryKey) {
        if (event) event.stopPropagation();

        if (!confirm(`Smazat kategorie "${categoryKey}" a všechny soubory?`)) return;

        const formData = new FormData();
        formData.append('category', categoryKey);
        formData.append('type', 'test');

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

    // FIX THE editTestFile FUNCTION - handle different parameter counts
    function editTestFile(originalName, category, subfolderOrCurrentName, currentName) {
        console.log('Opening edit modal for:', originalName, category, subfolderOrCurrentName, currentName);
        
        let subfolder = '';
        let actualCurrentName = '';
        
        // Handle different parameter patterns
        if (arguments.length === 3) {
            // Called from main category: editTestFile(originalName, category, currentName)
            actualCurrentName = subfolderOrCurrentName;
        } else if (arguments.length === 4) {
            // Called from subfolder: editTestFile(originalName, category, subfolder, currentName)
            subfolder = subfolderOrCurrentName;
            actualCurrentName = currentName;
        }
        
        document.getElementById('editTestFileCategory').value = category;
        document.getElementById('editTestFileSubfolder').value = subfolder || '';
        document.getElementById('editTestFileOriginalName').value = originalName;
        
        // Remove extension from current name for editing
        if (actualCurrentName) {
            const nameWithoutExt = actualCurrentName.replace(/\.[^/.]+$/, "");
            document.getElementById('editTestFileName').value = nameWithoutExt;
        }
        
        document.getElementById('editTestFileModal').style.display = 'flex';
        setTimeout(() => document.getElementById('editTestFileName').focus(), 100);
    }

    function closeEditTestFileModal() {
        document.getElementById('editTestFileModal').style.display = 'none';
        document.getElementById('editTestFileForm').reset();
    }

    // Handle edit test file form submission
    // Handler pro přejmenování TEST souboru
    const editTestFileForm = document.getElementById('editTestFileForm');
    if (editTestFileForm) {
        editTestFileForm.addEventListener('submit', function(e) {
            e.preventDefault();

            // 1. Získání dat z modalu
            const category = document.getElementById('editTestFileCategory').value;
            const subfolder = document.getElementById('editTestFileSubfolder').value;
            const originalName = document.getElementById('editTestFileOriginalName').value;
            const newName = document.getElementById('editTestFileName').value;

            if (!category || !originalName || !newName) {
                alert('Všechny údaje jsou povinné.');
                return;
            }

            const formData = new FormData();
            // 2. KLÍČ: 'test' pošle handler do správného rootu
            formData.append('type', 'test');
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
                        if (typeof closeEditTestFileModal === 'function') closeEditTestFileModal();

                        showStatusMessage('success', data.message);

                        // Reload je nutný, aby se v HTML projevily změny názvů a cest k souborům
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

    // Form handling - updated to include subfolder handlers
    function initializeTestFormHandlers() {
        const fileInput = document.getElementById('test_file');
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

        // Add file input handler for edit category modal
        const editCategoryFileInput = document.getElementById('editCategoryFile');
        const editCategorySelectedFile = document.getElementById('edit-category-selected-file');

        if (editCategoryFileInput && editCategorySelectedFile) {
            editCategoryFileInput.addEventListener('change', function() {
                if (this.files && this.files.length > 0) {
                    const file = this.files[0];
                    const fileSize = formatFileSize(file.size);
                    editCategorySelectedFile.textContent = `Vybraný soubor: ${file.name} (${fileSize})`;
                    
                    // Auto-fill custom filename if empty
                    const customFilenameInput = document.getElementById('editCategoryCustomFilename');
                    if (customFilenameInput && !customFilenameInput.value.trim()) {
                        const nameWithoutExt = file.name.replace(/\.[^/.]+$/, "");
                        customFilenameInput.value = nameWithoutExt;
                    }
                } else {
                    editCategorySelectedFile.textContent = 'Žádný soubor nebyl vybrán';
                }
            });
        }

        // Add file input handler for edit test subfolder modal
        const editTestSubfolderFileInput = document.getElementById('editTestSubfolderFile');
        const editTestSubfolderSelectedFile = document.getElementById('edit-test-subfolder-selected-file');

        if (editTestSubfolderFileInput && editTestSubfolderSelectedFile) {
            editTestSubfolderFileInput.addEventListener('change', function() {
                if (this.files && this.files.length > 0) {
                    const file = this.files[0];
                    const fileSize = formatFileSize(file.size);
                    editTestSubfolderSelectedFile.textContent = `Vybraný soubor: ${file.name} (${fileSize})`;
                    
                    // Auto-fill custom filename if empty
                    const customFilenameInput = document.getElementById('editTestSubfolderCustomFilename');
                    if (customFilenameInput && !customFilenameInput.value.trim()) {
                        const nameWithoutExt = file.name.replace(/\.[^/.]+$/, "");
                        customFilenameInput.value = nameWithoutExt;
                    }
                } else {
                    editTestSubfolderSelectedFile.textContent = 'Žádný soubor nebyl vybrán';
                }
            });
        }

        // Edit category form
        const editForm = document.getElementById('editCategoryForm');
        if (editForm) {
            editForm.addEventListener('submit', function(e) {
                e.preventDefault();

                // Get the values
                const originalName = document.getElementById('editOriginalName').value;
                const newName = document.getElementById('editCategoryName').value;

                if (!originalName || !newName) {
                    alert('Chybí povinné údaje');
                    return;
                }

                // Create FormData with correct parameter names that PHP expects
                const formData = new FormData(this); // This will include the file automatically
                formData.set('parent_category', originalName); // PHP expects 'parent_category'
                formData.set('original_name', originalName);   // PHP expects 'original_name'  
                formData.set('new_name', newName);             // PHP expects 'new_name'

                // Show loading state
                const submitBtn = this.querySelector('button[type="submit"]');
                const originalText = submitBtn.innerHTML;
                submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Ukládá se...';
                submitBtn.disabled = true;

                fetch('../../edit-test-subfolder.php', {
                        method: 'POST',
                        body: formData
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            closeEditCategoryModal();
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
                        // Restore button state
                        submitBtn.innerHTML = originalText;
                        submitBtn.disabled = false;
                    });
            });
        }

        // Edit test file form
        const editTestFileForm = document.getElementById('editTestFileForm');
        if (editTestFileForm) {
            editTestFileForm.addEventListener('submit', function(e) {
                e.preventDefault();

                const category = document.getElementById('editTestFileCategory').value;
                const subfolder = document.getElementById('editTestFileSubfolder').value;
                const originalName = document.getElementById('editTestFileOriginalName').value;
                const newName = document.getElementById('editTestFileName').value;

                if (!category || !originalName || !newName) {
                    alert('Chybí povinné údaje');
                    return;
                }

                const formData = new FormData();
                formData.append('category', category);
                formData.append('subfolder', subfolder);
                formData.append('original_name', originalName);
                formData.append('new_name', newName);

                const submitBtn = this.querySelector('button[type="submit"]');
                const originalText = submitBtn.innerHTML;
                submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Ukládá se...';
                submitBtn.disabled = true;

                fetch('../../edit-test-file.php', {
                        method: 'POST',
                        body: formData
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            closeEditTestFileModal();
                            showStatusMessage('success', data.message);
                            setTimeout(() => location.reload(), 500);
                        } else {
                            showStatusMessage('error', data.message || 'Neznámá chyba');
                        }
                    })
                    .catch((error) => {
                        console.error('Error:', error);
                        showStatusMessage('error', 'Chyba při přejmenovávání souboru');
                    })
                    .finally(() => {
                        submitBtn.innerHTML = originalText;
                        submitBtn.disabled = false;
                    });
            });
        }

        // Handle Test subfolder form submission
        const testSubfolderForm = document.getElementById('addTestSubfolderForm');
        if (testSubfolderForm) {
            testSubfolderForm.addEventListener('submit', function(e) {
                e.preventDefault();
                
                const formData = new FormData(this);
                
                fetch('add-test-subfolder.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => { // FIX: Syntax error fixed here
                    if (data.success) {
                        closeAddTestSubfolderModal();
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
        }

        // Handle edit Test subfolder form submission
        const editTestSubfolderForm = document.getElementById('editTestSubfolderForm');
        if (editTestSubfolderForm) {
            editTestSubfolderForm.addEventListener('submit', function(e) {
                e.preventDefault();
                
                const formData = new FormData(this);
                
                const submitBtn = this.querySelector('button[type="submit"]');
                const originalText = submitBtn.innerHTML;
                submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Ukládá se...';
                submitBtn.disabled = true;
                
                fetch('../../edit-test-subfolder.php', {
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
                        console.log('Parsed JSON:', data);
                        if (data && data.success) {
                            closeEditTestSubfolderModal();
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
                    console.error('Fetch error:', error);
                    showStatusMessage('error', 'Došlo k chybě při upravování podsložky.');
                })
                .finally(() => {
                    submitBtn.innerHTML = originalText;
                    submitBtn.disabled = false;
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
    function toggleTestFolderInputs() {
        const customInput = document.getElementById('custom-folder-name');
        const existingRow = document.getElementById('existing-folder-row');
        const categorySelect = document.getElementById('test-category');

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

            // Clean URL
            const cleanUrl = window.location.pathname + '#test';
            window.history.replaceState({}, document.title, cleanUrl);
        }
    }

    function showStatusMessage(type, message) {
        const container = document.getElementById('status-messages');
        if (!container) return;

        const div = document.createElement('div');
        div.className = `status-message status-${type}`;
        div.innerHTML = `<i class="fas ${type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle'}"></i> ${message}`;

        container.appendChild(div);

        setTimeout(() => div.remove(), 5000);
    }

    // Expose functions globally
    if (document.getElementById('test')) {
        window.toggleTest = toggleTest;
        window.closeMediaPlayer = closeMediaPlayer;
        window.deleteTest = deleteTest;
        window.toggleTestCategoryHeader = toggleTestCategoryHeader;
        window.editTestCategory = editTestCategory;
        window.closeEditCategoryModal = closeEditCategoryModal;
        window.deleteTestCategory = deleteTestCategory;
        window.toggleTestFolderInputs = toggleTestFolderInputs;
        window.editTestFile = editTestFile;
        window.closeEditTestFileModal = closeEditTestFileModal;
        window.showAddTestSubfolderModal = showAddTestSubfolderModal;
        window.closeAddTestSubfolderModal = closeAddTestSubfolderModal;
        window.editTestSubfolder = editTestSubfolder;
        window.closeEditTestSubfolderModal = closeEditTestSubfolderModal;
        window.deleteTestSubfolder = deleteTestSubfolder; 
        window.deleteTestFile = deleteTestFile;
        window.toggleTestSubfolder = toggleTestSubfolder;
    }

    // Add missing subfolder management functions
    function showAddTestSubfolderModal(categoryKey) {
        const modal = document.getElementById('addTestSubfolderModal');
        const parentInput = document.getElementById('testParentCategory');
        
        if (parentInput) parentInput.value = categoryKey;
        if (modal) modal.style.display = 'flex';
        
        // Focus on name input after modal opens
        setTimeout(() => {
            const nameInput = document.getElementById('testSubfolderName');
            if (nameInput) nameInput.focus();
        }, 100);
    }

    function closeAddTestSubfolderModal() {
        const modal = document.getElementById('addTestSubfolderModal');
        if (modal) modal.style.display = 'none';
        
        const form = document.getElementById('addTestSubfolderForm');
        if (form) form.reset();
    }

    function editTestSubfolder(categoryKey, folderName) {
        if (event) event.stopPropagation();
        
        const modal = document.getElementById('editTestSubfolderModal');
        const parentInput = document.getElementById('editTestParentCategory');
        const originalInput = document.getElementById('editTestOriginalName');
        const nameInput = document.getElementById('editTestSubfolderName');
        
        if (parentInput) parentInput.value = categoryKey;
        if (originalInput) originalInput.value = folderName;
        if (nameInput) nameInput.value = folderName;
        if (modal) modal.style.display = 'flex';
        
        // Focus on name input after modal opens
        setTimeout(() => {
            if (nameInput) nameInput.focus();
        }, 100);
    }

    function closeEditTestSubfolderModal() {
        const modal = document.getElementById('editTestSubfolderModal');
        if (modal) modal.style.display = 'none';
        
        const form = document.getElementById('editTestSubfolderForm');
        if (form) form.reset();
        
        // Clear file input displays
        const selectedFileDiv = document.getElementById('edit-test-subfolder-selected-file');
        if (selectedFileDiv) selectedFileDiv.textContent = '';
        
        const customFilenameInput = document.getElementById('editTestSubfolderCustomFilename');
        if (customFilenameInput) customFilenameInput.value = '';
    }

    function deleteTestSubfolder(categoryKey, folderName) {
        if (event) event.stopPropagation();

        if (!confirm(`Opravdu chcete smazat podsložku "${folderName}" a všechny soubory v ní?`)) return;

        const formData = new FormData();
        // Parametry pro univerzální handler
        formData.append('type', 'test');
        formData.append('category', categoryKey);
        formData.append('subfolder', folderName);

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
                    const card = document.querySelector(`[data-subfolder="${folderName}"]`);
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
            .catch(error => {
                console.error('Error:', error);
                showStatusMessage('error', 'Chyba při mazání podsložky.');
            });
    }

    // --- JEDNOTNÁ FUNKCE PRO SMAZÁNÍ (PRO TESTY) ---
    function universalDeleteTest(fileName, category, subfolder = '') {
        if (!confirm(`Opravdu chcete smazat soubor "${fileName}"?`)) return;

        const formData = new FormData();
        formData.append('type', 'test'); // Vždy 'test' pro tuto stránku
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

                    // SELEKTOR PRO ANIMACI (hledáme podle jména, kategorie a podsložky)
                    const selector = `[data-file-name="${fileName}"][data-current-category="${category}"][data-current-subfolder="${subfolder}"]`;
                    const fileElement = document.querySelector(selector);

                    if (fileElement) {
                        fileElement.style.transition = 'all 0.4s cubic-bezier(0.4, 0, 0.2, 1)';
                        fileElement.style.opacity = '0';
                        fileElement.style.transform = 'scale(0.8) translateY(-20px)';
                        setTimeout(() => fileElement.remove(), 400);
                    } else {
                        // Pokud JS prvek netrefí (třeba v hlavní kategorii), zkusíme to jen podle jména
                        const fallback = document.querySelector(`[data-file-name="${fileName}"]`);
                        if (fallback) fallback.remove();
                    }
                } else {
                    alert(data.message);
                }
            })
            .catch(err => console.error('Chyba:', err));
    }

    // --- PŘEMOSTĚNÍ STARÝCH FUNKCÍ (aby se nemuselo sahat do HTML) ---
    function deleteTest(fileName, category) {
        universalDeleteTest(fileName, category, '');
    }

    function deleteTestFile(fileName, category, subfolder) {
        universalDeleteTest(fileName, category, subfolder);
    }

    function toggleTestSubfolder(folderId) {
        const card = document.querySelector(`[onclick*="${folderId}"]`);
        if (!card) return;
        
        if (card.classList.contains('open')) {
            card.classList.remove('open');
        } else {
            card.classList.add('open');
        }
    }

    // Modern Drag and Drop with File System Access API
    let draggedElement = null;
    let draggedFileData = null;

    // Check if File System Access API is available
    const supportsFileSystemAccess = 'getAsFileSystemHandle' in DataTransferItem.prototype;

    function handleTestDragStart(event) {
        console.log("🚀 Drag started with File System Access API support:", supportsFileSystemAccess);

        draggedElement = event.target;
        draggedElement.classList.add('dragging');

        // Get file information from data attributes
        draggedFileData = {
            fileName: event.target.getAttribute('data-file-name'),
            currentCategory: event.target.getAttribute('data-current-category'),
            currentSubfolder: event.target.getAttribute('data-current-subfolder') || ''
        };

        console.log("📁 Dragged file data:", draggedFileData);

        // Set up modern drag data transfer
        event.dataTransfer.effectAllowed = 'move';

        // Set traditional data for fallback
        event.dataTransfer.setData('text/plain', JSON.stringify(draggedFileData));

        // If File System Access API is available, set up additional data
        if (supportsFileSystemAccess) {
            try {
                // Create a virtual file handle for the drag operation
                const fileData = new Blob([JSON.stringify(draggedFileData)], {
                    type: 'application/json'
                });
                const file = new File([fileData], 'file-move-data.json', {
                    type: 'application/json'
                });

                // Add the file to the data transfer
                event.dataTransfer.items.add(file);
                console.log("✅ Enhanced drag data set with File System Access API");
            } catch (error) {
                console.warn("⚠️ Could not set enhanced drag data:", error);
            }
        }
    }

    function handleTestDragEnd(event) {
        console.log("🏁 Drag ended");

        if (draggedElement) {
            draggedElement.classList.remove('dragging');
        }

        // Clean up all drop zones
        document.querySelectorAll('.droppable-zone').forEach(zone => {
            zone.classList.remove('drag-over');
        });

        draggedElement = null;
        draggedFileData = null;
    }

    function handleTestDragOver(event) {
        event.preventDefault();
        event.dataTransfer.dropEffect = 'move';
    }

    function handleTestDragEnter(event) {
        event.preventDefault();
        event.stopPropagation();

        if (!draggedFileData) return;

        const dropZone = event.currentTarget;
        const targetCategory = dropZone.getAttribute('data-category');
        const targetSubfolder = dropZone.getAttribute('data-subfolder') || '';

        console.log("⬆️ Drag enter zone:", {
            targetCategory,
            targetSubfolder
        });

        // Don't highlight if same location
        if (targetCategory === draggedFileData.currentCategory &&
            targetSubfolder === draggedFileData.currentSubfolder) {
            console.log("❌ Same location, no highlight");
            return;
        }

        dropZone.classList.add('drag-over');
        console.log("✅ Highlighted drop zone");
    }

    function handleTestDragLeave(event) {
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

    async function handleTestDrop(event) {
        console.log("📥 Drop detected with File System Access API support:", supportsFileSystemAccess);
        event.preventDefault();
        event.stopPropagation();

        const dropZone = event.currentTarget;
        const targetCategory = dropZone.getAttribute('data-category');
        const targetSubfolder = dropZone.getAttribute('data-subfolder') || '';

        dropZone.classList.remove('drag-over');

        let fileData = null;

        // Try to get data using File System Access API first
        if (supportsFileSystemAccess && event.dataTransfer.items.length > 0) {
            console.log("🔧 Attempting to use File System Access API");

            for (let i = 0; i < event.dataTransfer.items.length; i++) {
                const item = event.dataTransfer.items[i];

                try {
                    if (item.kind === 'file') {
                        // Try to get file system handle
                        const handle = await item.getAsFileSystemHandle();
                        console.log("📂 Got file system handle:", handle);

                        if (handle && handle.kind === 'file') {
                            // Extract file info from the handle
                            fileData = {
                                fileName: handle.name,
                                // We still need the category/subfolder info from our drag data
                                currentCategory: draggedFileData?.currentCategory,
                                currentSubfolder: draggedFileData?.currentSubfolder
                            };

                            console.log("✅ Enhanced file data from File System Access API:", fileData);
                            break;
                        }
                    }
                } catch (error) {
                    console.warn("⚠️ File System Access API failed for item:", error);
                    // Continue to try other items or fall back
                }
            }
        }

        // Fall back to traditional method if File System Access API didn't work
        if (!fileData && draggedFileData) {
            console.log("🔄 Falling back to traditional drag data");
            fileData = draggedFileData;
        }

        // Also try to get data from data transfer as fallback
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
        console.log("📁 Source file data:", fileData);

        // Check if same location
        if (targetCategory === fileData.currentCategory &&
            targetSubfolder === fileData.currentSubfolder) {
            console.log("❌ Same location, ignoring drop");
            return;
        }

        // Perform the move
        await moveTestFile(fileData, targetCategory, targetSubfolder);
    }

    async function moveTestFile(fileData, targetCategory, targetSubfolder) {
        console.log("🚚 Moving file:", fileData.fileName);
        showStatusMessage('info', 'Přesouvám soubor...');

        const formData = new FormData();
        formData.append('fileName', fileData.fileName);
        formData.append('sourceCategory', fileData.currentCategory);
        formData.append('sourceSubfolder', fileData.currentSubfolder);
        formData.append('targetCategory', targetCategory);
        formData.append('targetSubfolder', targetSubfolder);
        formData.append('move_test_file', '1');

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