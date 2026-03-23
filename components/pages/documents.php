<?php

// BUFFER OUTPUT IMMEDIATELY TO FIX "HEADERS ALREADY SENT"
ob_start();

// Set proper encoding for Czech characters
mb_internal_encoding('UTF-8');
mb_http_output('UTF-8');

require_once 'access.php';

// Handle file move request
if (isset($_POST['move_document_file']) && isAdmin()) {
    // CLEAN BUFFER: Remove any HTML that was generated before this point
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

    try {
        // Build paths
        $docBaseDir = getRoot('doc');

        // Construct paths carefully
        $sourcePath = $docBaseDir . $sourceCategory . '/';
        if (!empty($sourceSubfolder)) $sourcePath .= $sourceSubfolder . '/';

        $targetPath = $docBaseDir . $targetCategory . '/';
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

// Get existing categories - scan actual directories
function getDocumentCategories()
{
    $docDir = getRoot('doc');
    $categories = [];

    if (is_dir($docDir)) {
        $items = array_diff(scandir($docDir), ['.', '..', 'categories.json']);

        foreach ($items as $item) {
            $itemPath = $docDir . $item;
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
    
    // If no categories exist, provide a default
    if (empty($categories)) {
        $categories = [
            'general' => ['name' => 'Obecné', 'color' => '#3498db']
        ];
    }

    return $categories;
}

// Get dynamic categories
$documentCategories = getDocumentCategories();

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

// Helper function to get file type info
function getDocFileTypeInfo($extension)
{
    $extension = strtolower($extension);

    switch ($extension) {
        case 'pdf':
            return ['icon' => 'fas fa-file-pdf', 'color' => '#e74c3c'];
        case 'doc':
        case 'docx':
            return ['icon' => 'fas fa-file-word', 'color' => '#3498db'];
        case 'xls':
        case 'xlsx':
            return ['icon' => 'fas fa-file-excel', 'color' => '#2ecc71'];
        case 'ppt':
        case 'pptx':
            return ['icon' => 'fas fa-file-powerpoint', 'color' => '#e67e22'];
        case 'jpg':
        case 'jpeg':
        case 'png':
        case 'gif':
            return ['icon' => 'fas fa-file-image', 'color' => '#f39c12'];
        default:
            return ['icon' => 'fas fa-file', 'color' => '#95a5a6'];
    }
}

// Function to scan for subfolders and documents
function scanDocumentsRecursive($dir, $categoryKey, $categoryInfo, $depth = 0)
{
    $result = ['folders' => [], 'documents' => []];

    if (!is_dir($dir)) return $result;

    $items = array_diff(scandir($dir), ['.', '..']);

    foreach ($items as $item) {
        $itemPath = $dir . $item;

        if (is_dir($itemPath)) {
            // It's a subfolder
            $subResult = scanDocumentsRecursive($itemPath . '/', $categoryKey, $categoryInfo, $depth + 1);
            $result['folders'][$item] = [
                'name' => $item,
                'path' => $itemPath,
                'data' => $subResult,
                'depth' => $depth
            ];
        } else {
            // It's a file - add it directly
            $extension = strtolower(pathinfo($item, PATHINFO_EXTENSION));

            // Skip system files and only include document files
            $allowedExtensions = ['pdf', 'doc', 'docx', 'txt', 'xls', 'xlsx', 'ppt', 'pptx', 'jpg', 'jpeg', 'png', 'gif'];

            if (in_array($extension, $allowedExtensions)) {
                $fileTypeInfo = getDocFileTypeInfo($extension);
                $fileData = [
                    'name' => pathinfo($item, PATHINFO_FILENAME), // filename without extension
                    'original_name' => $item, // full filename with extension
                    'file_path' => str_replace(__DIR__ . '/../../', '', $itemPath),
                    'file_size' => filesize($itemPath),
                    'file_extension' => $extension,
                    'date_added' => filemtime($itemPath),
                    'has_file' => true,
                    'category_info' => $categoryInfo,
                    'id' => pathinfo($item, PATHINFO_FILENAME), // use filename as ID
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

<!--Page Documents -->
<div class="page-content" id="documents">
    <div class="content-area">
        <!-- Status Messages -->
        <div id="status-messages"></div>

        <!-- Documents Grid Container -->
        <div class="documents-grid-container">
            <?php
            $documentsDir = getRoot('doc');
            $documentsByCategory = [];

            // Scan for documents and subfolders
            if (is_dir($documentsDir)) {
                foreach ($documentCategories as $categoryKey => $categoryInfo) {
                    $categoryDir = $documentsDir . $categoryKey . '/';
                    if (is_dir($categoryDir)) {
                        $categoryData = scanDocumentsRecursive($categoryDir, $categoryKey, $categoryInfo);
                        if (!empty($categoryData['documents']) || !empty($categoryData['folders'])) {
                            $documentsByCategory[$categoryKey] = [
                                'info' => $categoryInfo,
                                'data' => $categoryData
                            ];
                        }
                    }
                }
            }

            if (empty($documentsByCategory)): ?>
                <div class="no-documents">
                    <i class="fas fa-file-alt"></i>
                    <p>Zatím nejsou žádné dokumenty v systému.</p>
                </div>
            <?php else: ?>
                <div class="categories-grid">
                    <?php foreach ($documentsByCategory as $categoryKey => $category): ?>
                        <div class="category-card droppable-zone" 
                            data-category="<?= htmlspecialchars($categoryKey) ?>"
                            data-subfolder=""
                            ondrop="handleDocumentDrop(event)"
                            ondragover="handleDocumentDragOver(event)"
                            ondragenter="handleDocumentDragEnter(event)"
                            ondragleave="handleDocumentDragLeave(event)"
                            style="border-top: 4px solid <?= $category['info']['color'] ?>">

                            <div class="drop-zone-indicator">
                                <i class="fas fa-file-import"></i> &nbsp; Přetáhněte soubor sem
                            </div>
                            <div class="category-header category-header-clickable" onclick="toggleCategoryHeader('<?= $categoryKey ?>')">
                                <div class="category-info">
                                    <h3 style="color: <?= $category['info']['color'] ?>">
                                        <i class="fas fa-folder"></i>
                                        <?= htmlspecialchars($category['info']['name']) ?>
                                    </h3>
                                    <span class="document-count">
                                        <?= countAllDocuments($category['data']) ?> souborů
                                        <?php if (!empty($category['data']['folders'])): ?>
                                            • <?= count($category['data']['folders']) ?> podsložek
                                        <?php endif; ?>
                                    </span>
                                </div>
                                
                                <?php if (isAdmin()): ?>
                                    <div class="category-admin-actions" onclick="event.stopPropagation()">
                                        <button class="admin-btn edit-btn" onclick="editDocumentCategory('<?= htmlspecialchars($categoryKey, ENT_QUOTES, 'UTF-8') ?>')" title="Upravit kategorii">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <button class="admin-btn delete-btn" onclick="deleteDocumentCategory('<?= htmlspecialchars($categoryKey, ENT_QUOTES, 'UTF-8') ?>')" title="Smazat kategorii">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </div>
                                <?php endif; ?>

                                <div class="category-toggle">
                                    <i class="fas fa-chevron-down"></i>
                                </div>
                            </div>

                            <div class="category-content" id="category-content-<?= $categoryKey ?>">
                                <?php if (canUpload()): ?>
                                    <div class="category-actions">
                                        <button class="action-btn add-subfolder-btn" onclick="showAddSubfolderModal('<?= $categoryKey ?>')">
                                            <i class="fas fa-folder-plus"></i> Přidat podsložku
                                        </button>
                                    </div>
                                <?php endif; ?>

                                <!-- Subfolders Grid -->
                                <?php if (!empty($category['data']['folders'])): ?>
                                    <div class="subfolders-section">
                                        <h4>Podsložky</h4>
                                        <div class="subfolders-grid">
                                            <?php foreach ($category['data']['folders'] as $folderName => $folderData): ?>
                                                <?php renderSubfolder($folderName, $folderData, $categoryKey, $category['info']); ?>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                <?php endif; ?>

                                <!-- Documents in main category -->
                                <?php if (!empty($category['data']['documents'])): ?>
                                    <div class="documents-section">
                                        <h4>Dokumenty</h4>
                                        <div class="documents-grid">
                                            <?php foreach ($category['data']['documents'] as $document): ?>
                                                <?php renderDocumentCard($document, $categoryKey); ?>
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

        <!--Document Section -->
        <?php if (canUpload()): ?>
            <div class="add-document-section">
                <h2>Nahrát soubor</h2>

                <form action="upload-category-form-handler.php" method="post" enctype="multipart/form-data" class="document-form">

                    <input type="hidden" name="type" value="doc">

                    <input type="hidden" name="redirect" value="documents">

                    <div class="form-row">
                        <div class="form-group">
                            <label for="custom-folder-name">Vytvořit novou kategorii (volitelné)</label>
                            <input type="text" id="custom-folder-name" name="custom_category" class="full-width"
                                   placeholder="Zadejte název pro novou kategorii..."
                                   onchange="toggleFolderInputs()"
                                   oninput="toggleFolderInputs()">
                            <small class="file-help">Pokud zadáte název, vytvoří se nová kategorie s tímto názvem</small>
                        </div>
                    </div>

                    <div class="form-row" id="existing-folder-row">
                        <div class="form-group">
                            <label for="document-category">Nebo vyberte existující kategorii</label>
                            <select id="document-category" name="category" class="full-width" onchange="updateSubfolderOptions()">
                                <option value="">-- Vyberte kategorii --</option>
                                <?php foreach ($documentCategories as $key => $category): ?>
                                    <option value="<?= $key ?>"><?= htmlspecialchars($category['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="document-subfolder">Podsložka</label>
                            <select id="document-subfolder" name="subfolder" class="full-width">
                                <option value="">-- Hlavní složka --</option>
                            </select>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="document_file">Vyberte soubor *</label>
                            <div class="upload-area">
                                <label for="document_file" class="custom-file-button">Vyberte soubor</label>
                                <input type="file" name="file" id="document_file" accept=".pdf,.doc,.docx,.txt,.xls,.xlsx,.ppt,.pptx,.jpg,.jpeg,.png,.gif" class="hidden-file-input" required>
                                <div id="selected-file-info" class="selected-files-info"></div>
                            </div>
                            <small class="file-help">Podporované formáty: PDF, DOC, JPG atd. (max 50MB)</small>
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

<?php
// Function to render subfolder
function renderSubfolder($folderName, $folderData, $categoryKey, $categoryInfo)
{
?>
    <div class="subfolder-card droppable-zone"
        data-category="<?= htmlspecialchars($categoryKey) ?>"
        data-subfolder="<?= htmlspecialchars($folderName) ?>"
        onclick="toggleSubfolder('<?= $categoryKey ?>-<?= $folderName ?>')"
        ondrop="handleDocumentDrop(event)"
        ondragover="handleDocumentDragOver(event)"
        ondragenter="handleDocumentDragEnter(event)"
        ondragleave="handleDocumentDragLeave(event)">

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
                    <button class="mini-btn edit-btn" onclick="event.stopPropagation(); editDocumentSubfolder('<?= $categoryKey ?>', '<?= $folderName ?>')" title="Upravit podsložku">
                        <i class="fas fa-edit"></i>
                    </button>
                    <button class="mini-btn delete-btn" onclick="event.stopPropagation(); deleteDocumentSubfolder('<?= $categoryKey ?>', '<?= $folderName ?>')" title="Smazat podsložku">
                        <i class="fas fa-trash"></i>
                    </button>
                <?php endif; ?>
                <i class="fas fa-chevron-right subfolder-toggle"></i>
            </div>
        </div>

        <div class="subfolder-content">
            <div class="subfolder-documents">
                <?php if (!empty($folderData['data']['documents'])): ?>
                    <?php foreach ($folderData['data']['documents'] as $document): ?>
                        <div class="subfolder-document <?= isAdmin() ? 'draggable-file' : '' ?>"
                             draggable="<?= isAdmin() ? 'true' : 'false' ?>"
                             data-file-name="<?= htmlspecialchars($document['original_name']) ?>"
                             data-current-category="<?= htmlspecialchars($categoryKey) ?>"
                             data-current-subfolder="<?= htmlspecialchars($folderName) ?>"
                                <?php if (isAdmin()): ?>
                                    ondragstart="handleDocumentDragStart(event)"
                                    ondragend="handleDocumentDragEnd(event)"
                                <?php endif; ?>>

                            <?php
                            // Ořezání systémové cesty na webovou URL
                            $webUrl = str_replace("/data/web/virtuals/380859/virtual/www", "", $document['file_path']);
                            $webUrl = str_replace('//', '/', $webUrl); // Pojistka proti double-slash
                            ?>

                            <?php if (isAdmin()): ?>
                                <i class="fas fa-arrows-alt drag-handle"></i>
                            <?php endif; ?>
                            <div class="doc-icon">
                                <i class="<?= $document['file_type_info']['icon'] ?>" style="color: <?= $document['file_type_info']['color'] ?>;"></i>
                            </div>
                            <div class="doc-info">
                                <span class="doc-name"><?= htmlspecialchars($document['name']) ?></span>
                                <span class="doc-size"><?= formatBytes($document['file_size']) ?></span>
                            </div>
                            <div class="doc-actions">
                                <a href="<?= htmlspecialchars($webUrl) ?>" target="_blank" class="mini-btn view-btn" title="Zobrazit" onclick="event.stopPropagation()">
                                    <i class="fas fa-eye"></i>
                                </a>
                                <a href="<?= htmlspecialchars($webUrl) ?>" download class="mini-btn download-btn" title="Stáhnout" onclick="event.stopPropagation()">
                                    <i class="fas fa-download"></i>
                                </a>
                                <?php if (isAdmin()): ?>
                                    <button class="mini-btn edit-btn" onclick="event.stopPropagation(); editDocumentFile('<?= addslashes($document['original_name']) ?>', '<?= $categoryKey ?>', '<?= addslashes($folderName) ?>', '<?= addslashes($document['name']) ?>')" title="Přejmenovat">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <button class="mini-btn delete-btn" onclick="event.stopPropagation(); deleteDocumentFile('<?= addslashes($document['original_name']) ?>', '<?= $categoryKey ?>', '<?= addslashes($folderName) ?>')" title="Smazat">
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

// Function to render document card  
function renderDocumentCard($document, $categoryKey)
{
?>
    <div class="document-card-grid <?= isAdmin() ? 'draggable-file' : '' ?>"
        draggable="<?= isAdmin() ? 'true' : 'false' ?>"
        data-file-name="<?= htmlspecialchars($document['original_name']) ?>"
        data-current-category="<?= htmlspecialchars($categoryKey) ?>"
        data-current-subfolder=""
        <?php if (isAdmin()): ?>
        ondragstart="handleDocumentDragStart(event)"
        ondragend="handleDocumentDragEnd(event)"
        <?php endif; ?>>
        <div class="document-preview">
            <?php if (isAdmin()): ?>
            <i class="fas fa-arrows-alt drag-handle"></i>
            <?php endif; ?>
            <div class="document-icon">
                <i class="<?= $document['file_type_info']['icon'] ?>" style="color: <?= $document['file_type_info']['color'] ?>; font-size: 32px;"></i>
            </div>
        </div>

        <div class="document-info-grid">
            <h5 class="document-title"><?= htmlspecialchars($document['name']) ?></h5>

            <div class="document-meta-grid">
                <span class="document-date">
                    <i class="fas fa-calendar"></i>
                    <?= date('d.m.Y', $document['date_added']) ?>
                </span>
                <span class="document-size">
                    <i class="fas fa-file"></i>
                    <?= formatBytes($document['file_size']) ?>
                </span>
            </div>

            <div class="document-actions-grid">
                <?php
                // Převedeme systémovou cestu na URL (ořežeme všechno až k web rootu)
                // Použijeme podobný trik jako v agreements, aby to bylo dynamické
                $webUrl = str_replace("/data/web/virtuals/380859/virtual/www", "", $document['file_path']);
                // Pro jistotu vyčistíme dvojitá lomítka, kdyby tam nějaká zbyla
                $webUrl = str_replace('//', '/', $webUrl);
                ?>

                <a href="<?= htmlspecialchars($webUrl) ?>" target="_blank" class="grid-btn view-btn" onclick="event.stopPropagation()">
                    <i class="fas fa-eye"></i>
                </a>

                <a href="<?= htmlspecialchars($webUrl) ?>" download class="grid-btn download-btn" onclick="event.stopPropagation()">
                    <i class="fas fa-download"></i>
                </a>

                <?php if (isAdmin()): ?>
                    <button class="grid-btn edit-btn" onclick="event.stopPropagation(); editDocumentFile('<?= addslashes($document['original_name']) ?>', '<?= $categoryKey ?>', '', '<?= addslashes($document['name']) ?>')">
                        <i class="fas fa-edit"></i>
                    </button>
                    <button class="grid-btn delete-btn" onclick="event.stopPropagation(); deleteDocumentFile('<?= addslashes($document['original_name']) ?>', '<?= $categoryKey ?>', '')">
                        <i class="fas fa-trash"></i>
                    </button>
                <?php endif; ?>
            </div>
        </div>
    </div>
<?php
}
?>

<!-- Add Subfolder Modal -->
<div id="addSubfolderModal" class="modal" style="display: none;">
    <div class="modal-content">
        <div class="modal-header">
            <h2>Přidat podsložku</h2>
            <button class="modal-close" onclick="closeAddSubfolderModal()">&times;</button>
        </div>
        <form id="addSubfolderForm">
            <input type="hidden" id="parentCategory" name="parent_category">

            <div class="form-group">
                <label for="subfolderName">Název podsložky *</label>
                <input type="text" id="subfolderName" name="subfolder_name" required
                    placeholder="Název nové podsložky" class="full-width">
            </div>

            <div class="modal-actions">
                <button type="button" class="btn btn-secondary" onclick="closeAddSubfolderModal()">Zrušit</button>
                <button type="submit" class="btn btn-primary">Vytvořit podsložku</button>
            </div>
        </form>
    </div>
</div>

<!-- Edit Subfolder Modal -->
<div id="editSubfolderModal" class="modal" style="display: none;">
    <div class="modal-content">
        <div class="modal-header">
            <h2>Upravit podsložku</h2>
            <button class="modal-close" onclick="closeEditSubfolderModal()">&times;</button>
        </div>
        <form id="editSubfolderForm" enctype="multipart/form-data">
            <input type="hidden" id="editParentCategory" name="parent_category">
            <input type="hidden" id="editOriginalName" name="original_name">

            <div class="form-group">
                <label for="editSubfolderName">Název podsložky *</label>
                <input type="text" id="editSubfolderName" name="new_name" required
                    placeholder="Nový název podsložky" class="full-width">
            </div>

            <div class="form-group">
                <label for="editSubfolderFile">Nahrát soubor do podsložky (volitelné)</label>
                <div class="upload-area">
                    <label for="editSubfolderFile" class="custom-file-button">Vyberte soubor</label>
                    <input type="file" name="subfolder_file" id="editSubfolderFile" accept=".pdf,.doc,.docx,.txt,.xls,.xlsx,.ppt,.pptx,.jpg,.jpeg,.png,.gif" class="hidden-file-input">
                    <div id="edit-subfolder-selected-file" class="selected-files-info"></div>
                </div>
                <small class="file-help">Podporované formáty: PDF, DOC, DOCX, TXT, XLS, XLSX, PPT, PPTX, JPG, PNG, GIF (max 50MB)</small>
            </div>

            <div class="form-group">
                <label for="editSubfolderCustomFilename">Přejmenovat soubor (volitelné)</label>
                <input type="text" id="editSubfolderCustomFilename" name="custom_filename" class="full-width" placeholder="Ponechte prázdné pro původní název">
                <small class="file-help">Zadejte pouze název bez přípony (přípona se doplní automaticky)</small>
            </div>

            <div class="modal-actions">
                <button type="button" class="btn btn-secondary" onclick="closeEditSubfolderModal()">Zrušit</button>
                <button type="submit" class="btn btn-primary">Uložit změny</button>
            </div>
        </form>
    </div>
</div>

<!-- Edit Document File Modal -->
<div id="editDocumentFileModal" class="modal" style="display: none;">
    <div class="modal-content">
        <div class="modal-header">
            <h2>Přejmenovat soubor</h2>
            <button class="modal-close" onclick="closeEditDocumentFileModal()">&times;</button>
        </div>
        <form id="editDocumentFileForm">
            <input type="hidden" id="editDocumentFileCategory" name="category">
            <input type="hidden" id="editDocumentFileSubfolder" name="subfolder">
            <input type="hidden" id="editDocumentFileOriginalName" name="original_name">

            <div class="form-group">
                <label for="editDocumentFileName">Nový název souboru *</label>
                <input type="text" id="editDocumentFileName" name="new_name" required
                    placeholder="Nový název souboru (bez přípony)" class="full-width">
                <small class="file-help">Zadejte pouze název bez přípony - přípona zůstane zachována</small>
            </div>

            <div class="modal-actions">
                <button type="button" class="btn btn-secondary" onclick="closeEditDocumentFileModal()">Zrušit</button>
                <button type="submit" class="btn btn-primary">Přejmenovat</button>
            </div>
        </form>
    </div>
</div>

<!-- Edit Document Category Modal -->
<div id="editDocumentCategoryModal" class="modal" style="display: none;">
    <div class="modal-content">
        <div class="modal-header">
            <h2>Upravit kategorii dokumentů</h2>
            <button class="modal-close" onclick="closeEditDocumentCategoryModal()">&times;</button>
        </div>
        <form id="editDocumentCategoryForm" enctype="multipart/form-data">
            <input type="hidden" id="editDocOriginalName" name="original_name">

            <div class="form-group">
                <label for="editDocCategoryName">Název kategorie *</label>
                <input type="text" id="editDocCategoryName" name="new_name" required
                    placeholder="Nový název kategorie" class="full-width">
            </div>

            <div class="form-group">
                <label for="editDocCategoryFile">Nahrát soubor do kategorie (volitelné)</label>
                <div class="upload-area">
                    <label for="editDocCategoryFile" class="custom-file-button">Vyberte soubor</label>
                    <input type="file" name="category_file" id="editDocCategoryFile" accept=".pdf,.doc,.docx,.txt,.xls,.xlsx,.ppt,.pptx,.jpg,.jpeg,.png,.gif" class="hidden-file-input">
                    <div id="edit-doc-category-selected-file" class="selected-files-info"></div>
                </div>
                <small class="file-help">Podporované formáty: Dokumenty, Obrázky (max 50MB)</small>
            </div>

            <div class="form-group">
                <label for="editDocCategoryCustomFilename">Přejmenovat soubor (volitelné)</label>
                <input type="text" id="editDocCategoryCustomFilename" name="custom_filename" class="full-width" placeholder="Ponechte prázdné pro původní název">
                <small class="file-help">Zadejte pouze název bez přípony (přípona se doplní automaticky)</small>
            </div>

            <div class="modal-actions">
                <button type="button" class="btn btn-secondary" onclick="closeEditDocumentCategoryModal()">Zrušit</button>
                <button type="submit" class="btn btn-primary">Uložit změny</button>
            </div>
        </form>
    </div>
</div>

<style>
    /* Grid Layout Styles */
    .documents-grid-container {
        padding: 20px 0;
    }

    .categories-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
        gap: 20px;
        margin-bottom: 30px;
        grid-auto-flow: row;
    }

    .category-card {
        background: white;
        border-radius: 10px;
        box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        overflow: hidden;
        transition: all 0.3s ease;
    }

    .category-card.expanded {
        grid-column: 1 / -1;
        max-width: none;
        transform: none !important;
        z-index: 10;
    }

    .category-card.expanded .category-content {
        max-height: none !important;
        padding: 20px !important;
        display: block !important;
        opacity: 1 !important;
        overflow: visible !important;
    }

    .category-card.expanded .category-toggle i {
        transform: rotate(180deg);
    }

    .category-card:hover:not(.expanded) {
        box-shadow: 0 5px 15px rgba(0, 0, 0, 0.15);
    }

    .category-card:not(.expanded) .category-content {
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

    .document-count {
        font-size: 13px;
        color: #6c757d;
        font-weight: 500;
    }

    .category-toggle {
        color: #6c757d;
        transition: transform 0.3s ease;
    }

    .category-toggle.open {
        transform: rotate(180deg);
    }

    .category-content {
        padding: 20px;
        transition: all 0.3s ease;
    }

    .category-actions {
        margin-bottom: 20px;
        display: flex;
        gap: 10px;
    }

    .add-subfolder-btn {
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

    .add-subfolder-btn:hover {
        background: #138496;
    }

    /* Subfolder Styling - Copied from edo.php */
    .subfolders-section {
        margin-bottom: 25px;
    }

    .subfolders-section h4 {
        color: #495057;
        font-size: 14px;
        font-weight: 600;
        margin: 0 0 12px 0;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    .subfolders-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
        gap: 12px;
    }

    .subfolder-card {
        background: #f8f9fa;
        border: 1px solid #e9ecef;
        border-radius: 8px;
        padding: 15px;
        cursor: pointer;
        transition: all 0.2s;
    }

    .subfolder-card:hover {
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
    .subfolder-card.open {
        grid-column: 1 / -1;
        width: 100%;
        max-width: none;
        background: #fff;
        border-color: #dee2e6;
    }

    .subfolder-card.open .subfolder-content {
        max-height: 1000px;
        /* Large enough value */
        margin-top: 12px;
        padding-top: 12px;
        border-top: 1px solid #dee2e6;
    }

    .subfolder-card.open .subfolder-toggle {
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
    .documents-section h4 {
        color: #495057;
        font-size: 14px;
        font-weight: 600;
        margin: 0 0 12px 0;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    .documents-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
        gap: 15px;
    }

    .document-card-grid {
        background: white;
        border: 1px solid #e9ecef;
        border-radius: 8px;
        overflow: hidden;
        transition: all 0.2s;
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
    }

    .document-card-grid:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
        border-color: #dee2e6;
    }

    .document-preview {
        height: 120px;
        display: flex;
        align-items: center;
        justify-content: center;
        background: #f8f9fa;
        border-bottom: 1px solid #e9ecef;
        overflow: hidden;
    }

    .document-thumbnail {
        width: 100%;
        height: 100%;
        object-fit: cover;
        cursor: pointer;
        transition: transform 0.2s;
    }

    .document-thumbnail:hover {
        transform: scale(1.05);
    }

    .document-icon {
        font-size: 48px;
        opacity: 0.8;
    }

    .document-info-grid {
        padding: 15px;
        display: flex;
        flex-direction: column;
        gap: 8px;
    }

    .document-title {
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

    .document-description {
        font-size: 12px;
        color: #6c757d;
        line-height: 1.4;
        margin: 0;
        display: -webkit-box;
        -webkit-line-clamp: 2;
        line-clamp: 2;
        -webkit-box-orient: vertical;
        overflow: hidden;
    }

    .document-meta-grid {
        display: flex;
        justify-content: space-between;
        align-items: center;
        font-size: 11px;
        color: #6c757d;
        margin-top: auto;
        flex-wrap: wrap;
        gap: 8px;
    }

    .document-date,
    .document-size {
        display: flex;
        align-items: center;
        gap: 4px;
    }

    .document-actions-grid {
        display: flex;
        gap: 6px;
        justify-content: flex-end;
        margin-top: 8px;
        padding-top: 8px;
        border-top: 1px solid #f0f0f0;
    }





    /* Add Document Section */
    .add-document-section {
        background: white;
        border-radius: 10px;
        padding: 25px;
        margin-top: 30px;
        box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
    }

    .add-document-section h2 {
        color: #2c3e50;
        margin-top: 0;
        margin-bottom: 20px;
        font-size: 20px;
    }

    .document-form {
        display: flex;
        flex-direction: column;
        gap: 15px;
    }

    .form-row {
        display: flex;
        gap: 20px;
        flex-wrap: wrap;
    }

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
    .form-group select,
    .form-group textarea {
        width: 100%;
        padding: 10px;
        border: 1px solid #ddd;
        border-radius: 5px;
        font-size: 14px;
        box-sizing: border-box;
    }

    .form-group input:focus,
    .form-group select:focus,
    .form-group textarea:focus {
        outline: none;
        border-color: #3498db;
        box-shadow: 0 0 0 2px rgba(52, 152, 219, 0.2);
    }



    /* File input styling */
    .hidden-file-input {
        width: 0.1px;
        height: 0.1px;
        opacity: 0;
        overflow: hidden;
        position: absolute;
        z-index: -1;
    }

    .custom-file-button {
        display: inline-block;
        padding: 10px 15px;
        background: #3498db;
        color: white;
        border-radius: 5px;
        cursor: pointer;
        font-weight: 500;
        transition: background 0.2s;
    }

    .custom-file-button:hover {
        background: #2980b9;
    }
    .documents-grid-container {
        height: auto !important;
        min-height: 0 !important;
        flex-grow: 0; /* Zabrání natahování ve flexu */
        align-self: flex-start; /* Zarovná se na začátek a nebude se natahovat na celou výšku */
        width: 100%;
    }
    .selected-files-info {
        margin-top: 8px;
        font-size: 14px;
        color: #34495e;
        padding: 5px;
    }

    .file-help {
        color: #6c757d;
        font-size: 12px;
        margin-top: 5px;
        display: block;
    }

    .no-documents {
        text-align: center;
        padding: 60px 20px;
        color: #6c757d;
        background: white;
        border-radius: 10px;
        box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
    }

    .no-documents i {
        font-size: 48px;
        margin-bottom: 20px;
        opacity: 0.5;
    }

    /* Modal Styles */
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
        padding: 20px 25px 15px;
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

    #addSubfolderForm,
    #editSubfolderForm,
    #renameCategoryForm,
    #editDocumentFileForm,
    #editDocumentCategoryForm,
    #renameDocumentForm {
        padding: 20px 25px;
    }

    #addSubfolderForm .form-group,
    #editSubfolderForm .form-group,
    #renameCategoryForm .form-group,
    #editDocumentFileForm .form-group,
    #editDocumentCategoryForm .form-group,
    #renameDocumentForm .form-group {
        margin-bottom: 20px;
    }

    .modal-actions {
        display: flex;
        gap: 10px;
        justify-content: flex-end;
        margin-top: 25px;
        padding: 20px 0 0;
        border-top: 1px solid #eee;
    }

    .btn {
        padding: 10px 20px;
        border: none;
        border-radius: 5px;
        cursor: pointer;
        font-size: 14px;
        font-weight: 600;
        transition: background 0.2s;
    }

    .btn-primary {
        background: #3498db;
        color: white;
    }

    .btn-primary:hover {
        background: #2980b9;
    }

    .btn-secondary {
        background: #95a5a6;
        color: white;
    }

    .btn-secondary:hover {
        background: #7f8c8d;
    }

    /* Image Modal */
    .image-modal .modal-content {
        max-width: 90vw;
        max-height: 90vh;
    }

    .image-modal-body {
        padding: 20px;
        text-align: center;
    }

    .modal-preview-image {
        max-width: 100%;
        max-height: 70vh;
        object-fit: contain;
        border-radius: 8px;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
    }

    /* Status Messages */
    #status-messages {
        position: fixed;
        top: 80px;
        right: 20px;
        z-index: 2000;
        width: 350px;
        max-width: 90%;
    }
    .status-message {
        padding: 15px 20px;
        border-radius: 8px;
        margin-bottom: 10px;
        font-weight: 500;
        box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        display: flex;
        align-items: center;
        gap: 10px;
        opacity: 0;
        transform: translateX(20px);
        animation: slideInStatus 0.3s forwards;
    }

    @keyframes slideInStatus {
        to {
            opacity: 1;
            transform: translateX(0);
        }
    }

    .status-success {
        background: #d4edda;
        color: #155724;
        border: 1px solid #c3e6cb;
    }

    .status-warning {
        background: #fff3cd;
        color: #856404;
        border: 1px solid #ffeaa7;
    }

    .status-error {
        background: #f8d7da;
        color: #721c24;
        border: 1px solid #f5c6cb;
    }

    /* Admin Button Styles */
    .category-admin-actions {
        display: flex;
        gap: 8px;
        z-index: 2;
        flex-shrink: 0;
    }


    /* Responsive Design */
    @media (max-width: 768px) {
        .categories-grid {
            grid-template-columns: 1fr;
            gap: 15px;
        }

        .subfolders-grid {
            grid-template-columns: 1fr;
        }

        .subfolder-documents {
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 10px;
        }

        .subfolder-document {
            padding: 10px;
            gap: 8px;
        }

        .doc-name {
            font-size: 12px;
        }

        .doc-size {
            font-size: 10px;
        }

        .mini-btn {
            width: 22px;
            height: 22px;
            font-size: 11px;
        }

        .documents-grid {
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 10px;
        }

        .form-row {
            flex-direction: column;
            gap: 15px;
        }

        .form-group {
            min-width: auto;
        }

        .modal-content {
            width: 95%;
            margin: 20px;
        }

        .document-preview {
            height: 100px;
        }

        .document-icon {
            font-size: 36px;
        }

        .document-info-grid {
            padding: 12px;
        }

        .document-title {
            font-size: 13px;
        }

        .document-description {
            font-size: 11px;
        }

        .category-info h3 {
            font-size: 16px;
        }
    }

    @media (max-width: 480px) {
        .documents-grid {
            grid-template-columns: 1fr;
        }

        .subfolders-grid {
            grid-template-columns: 1fr;
        }

        .subfolder-documents {
            grid-template-columns: 1fr;
            gap: 8px;
        }

        .subfolder-document {
            padding: 8px 10px;
            gap: 8px;
        }

        .doc-name {
            font-size: 13px;
        }

        .doc-size {
            font-size: 11px;
        }

        .mini-btn {
            width: 24px;
            height: 24px;
            font-size: 12px;
        }

        .categories-grid {
            grid-template-columns: 1fr;
        }

        .category-content {
            padding: 15px;
        }
    }

    /* Drag and Drop Styles */
    .document-card-grid.dragging {
        opacity: 0.5;
        transform: scale(0.95);
        cursor: grabbing;
    }

    .subfolder-document.dragging {
        opacity: 0.5;
        transform: scale(0.95);
        cursor: grabbing;
    }

    .subfolder-card.drag-over {
        background: #e3f2fd !important;
        border-color: #2196f3 !important;
        border: 2px solid #2196f3 !important;
        box-shadow: 0 0 15px rgba(33, 150, 243, 0.5) !important;
    }

    .category-card.drag-over {
        background: #f3e5f5 !important;
        border-color: #9c27b0 !important;
        border: 2px solid #9c27b0 !important;
        box-shadow: 0 0 15px rgba(156, 39, 176, 0.5) !important;
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

    .subfolder-card.drag-over .drop-zone-indicator {
        display: flex;
    }

    .category-card.drag-over .drop-zone-indicator {
        display: flex;
    }

    .subfolder-card {
        position: relative;
    }

    .category-card {
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

<script>
    // Document management JavaScript functions
    let subfolderData = {};

    document.addEventListener('DOMContentLoaded', function() {
        // Only run if we're on the documents page
        if (!document.getElementById('documents')) {
            return;
        }

        // Load subfolder data
        loadSubfolderData();

        // Close all categories by default
        document.querySelectorAll('#documents .category-content').forEach(content => {
            if (content) {
                content.style.maxHeight = '0px';
            }
        });

        // Initialize form handlers
        initializeDocumentFormHandlers();
        
        // File input handler
        const fileInput = document.getElementById('document_file');
        const selectedFile = document.getElementById('selected-file-info');

        if (fileInput && selectedFile) {
            fileInput.addEventListener('change', function() {
                if (this.files && this.files.length > 0) {
                    const file = this.files[0];
                    const fileSize = formatFileSize(file.size);
                    selectedFile.textContent = `Vybraný soubor: ${file.name} (${fileSize})`;
                } else {
                    selectedFile.textContent = '';
                }
            });
        }
    });

    // Load subfolder data
    function loadSubfolderData() {
        fetch('get-subfolder-form-handler.php?type=doc')
            .then(response => {
                if (!response.ok) throw new Error('Network error or 404');
                return response.json();
            })
            .then(data => {
                console.log('Načtená data podsložek (doc):', data);
                subfolderData = data || {};
                updateSubfolderOptions();
            })
            .catch(error => console.error('Chyba při načítání podsložek dokumentů:', error));
    }

    // Update subfolder dropdown
    // Update subfolder dropdown
    function updateSubfolderOptions() {
        const mainFolder = document.getElementById('document-category');
        const subfolderSelect = document.getElementById('document-subfolder');

        if (!mainFolder || !subfolderSelect) return;

        subfolderSelect.innerHTML = '<option value="">-- Hlavní složka --</option>';

        // subfolderData[mainFolder.value] obsahuje pole názvů podsložek
        if (subfolderData[mainFolder.value]) {
            subfolderData[mainFolder.value].forEach(subfolder => {
                const option = document.createElement('option');
                option.value = subfolder;
                option.textContent = subfolder;
                subfolderSelect.appendChild(option);
            });
        }
    }

    // Toggle category
    function toggleCategoryHeader(categoryKey) {
        if (!document.getElementById('documents')) return;

        const categoryCard = document.querySelector(`#documents [data-category="${categoryKey}"]`);
        if (!categoryCard) return;

        if (categoryCard.classList.contains('expanded')) {
            categoryCard.classList.remove('expanded');
        } else {
            document.querySelectorAll('#documents .category-card.expanded').forEach(card => {
                card.classList.remove('expanded');
            });
            categoryCard.classList.add('expanded');
        }
    }

    // Toggle subfolder
    function toggleSubfolder(subfolderId) {
        if (event) event.stopPropagation();
        if (!document.getElementById('documents')) return;

        const subfolderCard = document.querySelector(`[onclick="toggleSubfolder('${subfolderId}')"]`);
        if (!subfolderCard) return;

        subfolderCard.classList.toggle('open');
    }

    // Show add subfolder modal
    function showAddSubfolderModal(categoryKey) {
        if (event) event.stopPropagation();
        document.getElementById('parentCategory').value = categoryKey;
        document.getElementById('addSubfolderModal').style.display = 'flex';
        document.getElementById('addSubfolderForm').reset();
        setTimeout(() => document.getElementById('subfolderName').focus(), 100);
    }

    function closeAddSubfolderModal() {
        document.getElementById('addSubfolderModal').style.display = 'none';
    }

    // Edit subfolder
    function editDocumentSubfolder(categoryKey, subfolderName) {
        if (event) event.stopPropagation();
        document.getElementById('editParentCategory').value = categoryKey;
        document.getElementById('editOriginalName').value = subfolderName;
        document.getElementById('editSubfolderName').value = subfolderName;
        document.getElementById('editSubfolderModal').style.display = 'flex';
        setTimeout(() => document.getElementById('editSubfolderName').focus(), 100);
    }

    function closeEditSubfolderModal() {
        document.getElementById('editSubfolderModal').style.display = 'none';
        document.getElementById('editSubfolderForm').reset();
        document.getElementById('edit-subfolder-selected-file').textContent = '';
        document.getElementById('editSubfolderCustomFilename').value = '';
    }

    // Delete subfolder
    function deleteDocumentSubfolder(categoryKey, subfolderName) {
        if (event) event.stopPropagation();
        if (!confirm(`Opravdu chcete smazat podsložku "${subfolderName}" a všechny soubory v ní?`)) return;

        const formData = new FormData();
        formData.append('type', 'doc');
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
                    // Najdeme kartu v HTML a bleskově ji odstraníme
                    const card = document.querySelector(`[data-subfolder="${subfolderName}"]`);
                    if (card) {
                        card.style.transition = 'all 0.3s ease';
                        card.style.opacity = '0';
                        setTimeout(() => card.remove(), 300);
                    } else {
                        // Pokud ji JS nenajde, uděláme reload jako pojistku
                        location.reload();
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

    // Delete file
    function deleteDocumentFile(fileName, category, subfolder) {
        if (event) event.stopPropagation();

        if (confirm(`Opravdu chcete smazat soubor "${fileName}"?`)) {
            const formData = new FormData();
            // Identifikace pro univerzální handler
            formData.append('type', 'doc');
            formData.append('category', category);
            formData.append('subfolder', subfolder || '');
            formData.append('file_name', fileName); // Handler čeká 'file_name'

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
                        // Použijeme tvou globální funkci pro zprávy (pokud ji máš i tady)
                        if (typeof showStatusMessage === 'function') {
                            showStatusMessage('success', data.message);
                        }

                        // --- BLESKOVÁ AKTUALIZACE DOMU (Sexy animace) ---
                        const fileElement = document.querySelector(`[data-file-name="${fileName}"]`);

                        if (fileElement) {
                            fileElement.style.transition = 'all 0.4s cubic-bezier(0.4, 0, 0.2, 1)';
                            fileElement.style.opacity = '0';
                            fileElement.style.transform = 'scale(0.8) translateY(-20px)';

                            setTimeout(() => {
                                fileElement.remove();

                                // Pokud jsi se nakonec rozhodl, že ten refresh je jistota,
                                // můžeš ho sem dát jako fallback, ale animace je animace. ;)
                                // location.reload();
                            }, 400);
                        } else {
                            // Pojistka: pokud JS nenajde element v DOMu, radši refreshni
                            location.reload();
                        }
                    } else {
                        if (typeof showStatusMessage === 'function') {
                            showStatusMessage('error', data.message);
                        } else {
                            alert(data.message);
                        }
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Chyba při komunikaci se serverem.');
                });
        }
    }

    // Edit file
    function editDocumentFile(originalName, category, subfolder, currentName) {
        if (event) event.stopPropagation();
        document.getElementById('editDocumentFileCategory').value = category;
        document.getElementById('editDocumentFileSubfolder').value = subfolder || '';
        document.getElementById('editDocumentFileOriginalName').value = originalName;
        document.getElementById('editDocumentFileName').value = currentName.replace(/\.[^/.]+$/, "");
        document.getElementById('editDocumentFileModal').style.display = 'flex';
        setTimeout(() => document.getElementById('editDocumentFileName').focus(), 100);
    }

    function closeEditDocumentFileModal() {
        document.getElementById('editDocumentFileModal').style.display = 'none';
    }

    // Edit category
    function editDocumentCategory(categoryKey) {
        if (event) event.stopPropagation();
        document.getElementById('editDocOriginalName').value = categoryKey;
        document.getElementById('editDocCategoryName').value = categoryKey;
        document.getElementById('editDocumentCategoryModal').style.display = 'flex';
        setTimeout(() => document.getElementById('editDocCategoryName').focus(), 100);
    }

    function closeEditDocumentCategoryModal() {
        document.getElementById('editDocumentCategoryModal').style.display = 'none';
        document.getElementById('editDocumentCategoryForm').reset();
        document.getElementById('edit-doc-category-selected-file').textContent = '';
    }

    // Delete category
    function deleteDocumentCategory(categoryKey) {
        if (event) event.stopPropagation();
        if (confirm(`Smazat kategorii "${categoryKey}" a všechny soubory?`)) {
            const formData = new FormData();
            formData.append('category', categoryKey);
            formData.append('type', 'doc');

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
                .catch(error => showStatusMessage('error', 'Chyba při mazání kategorie.'));
        }
    }

    function showStatusMessage(type, message) {
        const container = document.getElementById('status-messages');
        if (!container) return;
        const div = document.createElement('div');
        div.className = `status-message status-${type}`;
        div.innerHTML = `<i class="fas ${type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle'}"></i> ${message}`;
        container.appendChild(div);
        setTimeout(() => {
            div.style.animation = 'slideOutStatus 0.3s forwards';
            setTimeout(() => div.remove(), 300);
        }, 5000);
    }

    function initializeDocumentFormHandlers() {
        // Add subfolder
        document.getElementById('addSubfolderForm')?.addEventListener('submit', function(e) {
            e.preventDefault();
            const formData = new FormData(this);
            fetch('add-document-subfolder.php', { method: 'POST', body: formData })
                .then(res => res.json()).then(data => {
                    if (data.success) {
                        closeAddSubfolderModal();
                        showStatusMessage('success', data.message);
                        setTimeout(() => location.reload(), 500);
                    } else { showStatusMessage('error', data.message); }
                }).catch(() => showStatusMessage('error', 'Chyba při vytváření podsložky.'));
        });

        // Editace podsložky - MÍŘÍME NA UNIVERZÁLNÍ HANDLER
        document.getElementById('editSubfolderForm')?.addEventListener('submit', function(e) {
            e.preventDefault();

            const formData = new FormData(this);

            // 1. PŘIDÁME TYP: Tohle řekne handleru, že má hledat v getRoot('doc')
            formData.append('type', 'doc');

            // 2. ZMĚNÍME URL: Pálíme na ten nový společný soubor
            fetch('edit-subfolder-handler.php', {
                method: 'POST',
                body: formData,
                headers: {
                    // Tohle zajistí, že tě access.php neodhlásí při vypršení session
                    'X-Requested-With': 'XMLHttpRequest'
                }
            })
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        closeEditSubfolderModal();
                        showStatusMessage('success', data.message);
                        // Reload je u přejmenování složek nutný kvůli aktualizaci cest v HTML
                        setTimeout(() => location.reload(), 500);
                    } else {
                        showStatusMessage('error', data.message);
                    }
                })
                .catch(() => showStatusMessage('error', 'Chyba při komunikaci se serverem.'));
        });

        // Edit file
        // Odeslání formuláře pro přejmenování souboru v documents.php
        const editDocFileForm = document.getElementById('editDocumentFileForm');
        if (editDocFileForm) {
            editDocFileForm.addEventListener('submit', function(e) {
                e.preventDefault();

                const category = document.getElementById('editDocumentFileCategory').value;
                const subfolder = document.getElementById('editDocumentFileSubfolder').value;
                const originalName = document.getElementById('editDocumentFileOriginalName').value;
                const newName = document.getElementById('editDocumentFileName').value;

                if (!category || !originalName || !newName) {
                    alert('Všechna pole jsou povinná');
                    return;
                }

                const formData = new FormData();
                formData.append('type', 'doc'); // Tady říkáme, že jde o dokumenty
                formData.append('category', category);
                formData.append('subfolder', subfolder);
                formData.append('original_name', originalName);
                formData.append('new_name', newName);

                const submitBtn = this.querySelector('button[type="submit"]');
                const originalBtnText = submitBtn.innerHTML;
                submitBtn.disabled = true;
                submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';

                fetch('edit-file-handler.php', {
                    method: 'POST',
                    body: formData
                })
                    .then(res => res.json())
                    .then(data => {
                        if (data.success) {
                            closeEditDocumentFileModal();
                            showStatusMessage('success', data.message);
                            // Tady je reload nejlepší, aby se přegenerovaly odkazy ke stažení atd.
                            setTimeout(() => location.reload(), 600);
                        } else {
                            alert(data.message);
                            submitBtn.disabled = false;
                            submitBtn.innerHTML = originalBtnText;
                        }
                    })
                    .catch(err => {
                        console.error('Error:', err);
                        alert('Chyba komunikace se serverem.');
                        submitBtn.disabled = false;
                        submitBtn.innerHTML = originalBtnText;
                    });
            });
        }

        // Edit category
// Upravený JS handler v documents.php
        document.getElementById('editDocumentCategoryForm')?.addEventListener('submit', function(e) {
            e.preventDefault();
            const formData = new FormData(this);

            // TADY JE TA ZMĚNA: Přidáme typ 'doc' (v videos.php bys dal 'videos')
            formData.append('type', 'doc');

            // Míříme na náš nový UNIVERZÁLNÍ soubor
            fetch('edit-category-handler.php', {
                method: 'POST',
                body: formData
            })
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        closeEditDocumentCategoryModal();
                        showStatusMessage('success', data.message);
                        (() => location.reload(), 500);
                    } else {
                        showStatusMessage('error', data.message);
                    }
                })
                .catch(() => showStatusMessage('error', 'Chyba při komunikaci se serverem.'));
        });

        // File input handlers for modals
        document.getElementById('editSubfolderFile')?.addEventListener('change', function() {
            const file = this.files[0];
            if (file) {
                document.getElementById('edit-subfolder-selected-file').textContent = `Vybráno: ${file.name}`;
                const nameWithoutExt = file.name.replace(/\.[^/.]+$/, "");
                document.getElementById('editSubfolderCustomFilename').value = nameWithoutExt;
            } else {
                document.getElementById('edit-subfolder-selected-file').textContent = '';
            }
        });

        document.getElementById('editDocCategoryFile')?.addEventListener('change', function() {
            const file = this.files[0];
            if (file) {
                document.getElementById('edit-doc-category-selected-file').textContent = `Vybráno: ${file.name}`;
                const nameWithoutExt = file.name.replace(/\.[^/.]+$/, "");
                document.getElementById('editDocCategoryCustomFilename').value = nameWithoutExt;
            } else {
                document.getElementById('edit-doc-category-selected-file').textContent = '';
            }
        });
    }

    function toggleFolderInputs() {
        const customInput = document.getElementById('custom-folder-name');
        const existingRow = document.getElementById('existing-folder-row');
        if (!customInput || !existingRow) return;

        if (customInput.value.trim()) {
            existingRow.style.opacity = '0.5';
            existingRow.querySelectorAll('select').forEach(s => s.disabled = true);
        } else {
            existingRow.style.opacity = '1';
            existingRow.querySelectorAll('select').forEach(s => s.disabled = false);
        }
    }

    function formatFileSize(bytes) {
        if (!bytes || bytes === 0) return '0 B';
        const k = 1024;
        const sizes = ['B', 'KB', 'MB', 'GB'];
        const i = Math.floor(Math.log(bytes) / Math.log(k));
        return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
    }

    // --- DRAG AND DROP LOGIC ---
    var draggedDocumentElement = null;
    var draggedDocumentFileData = null;

    function handleDocumentDragStart(event) {
        draggedDocumentElement = event.currentTarget;
        draggedDocumentFileData = {
            fileName: draggedDocumentElement.dataset.fileName,
            sourceCategory: draggedDocumentElement.dataset.currentCategory,
            sourceSubfolder: draggedDocumentElement.dataset.currentSubfolder
        };
        draggedDocumentElement.classList.add('dragging');
        event.dataTransfer.effectAllowed = 'move';
    }

    function handleDocumentDragEnd(event) {
        draggedDocumentElement.classList.remove('dragging');
        document.querySelectorAll('#documents .droppable-zone').forEach(zone => {
            zone.classList.remove('drag-over');
        });
    }

    function handleDocumentDragOver(event) {
        event.preventDefault();
        event.dataTransfer.dropEffect = 'move';
    }

    function handleDocumentDragEnter(event) {
        if (event.currentTarget.classList.contains('droppable-zone')) {
            event.currentTarget.classList.add('drag-over');
        }
    }

    function handleDocumentDragLeave(event) {
        if (event.currentTarget.classList.contains('droppable-zone')) {
            event.currentTarget.classList.remove('drag-over');
        }
    }

    async function handleDocumentDrop(event) {
        event.preventDefault();
        event.stopPropagation();

        const dropZone = event.currentTarget;
        dropZone.classList.remove('drag-over');

        if (!draggedDocumentFileData) return;

        const targetCategory = dropZone.dataset.category;
        const targetSubfolder = dropZone.dataset.subfolder || '';

        // Check if same location
        if (draggedDocumentFileData.sourceCategory === targetCategory &&
            draggedDocumentFileData.sourceSubfolder === targetSubfolder) {
            return;
        }

        await moveDocumentFile(draggedDocumentFileData, targetCategory, targetSubfolder);
    }

    async function moveDocumentFile(fileData, targetCategory, targetSubfolder) {
        const formData = new FormData();
        formData.append('move_document_file', '1');
        formData.append('fileName', fileData.fileName);
        formData.append('sourceCategory', fileData.sourceCategory);
        formData.append('sourceSubfolder', fileData.sourceSubfolder);
        formData.append('targetCategory', targetCategory);
        formData.append('targetSubfolder', targetSubfolder);

        try {
            const response = await fetch(window.location.href, {
                method: 'POST',
                body: formData
            });

            if (response.ok) {
                showStatusMessage('success', 'Soubor byl úspěšně přesunut!');
                (() => location.reload(), 500);
            } else if (response.status === 400) {
                showStatusMessage('error', 'Neplatné zadání.');
            } else if (response.status === 404) {
                showStatusMessage('error', 'Soubor nebyl nalezen.');
            } else {
                showStatusMessage('error', 'Chyba při přesunu souboru.');
            }
        } catch (error) {
            showStatusMessage('error', 'Chyba při komunikaci se serverem.');
        }
    }
</script>