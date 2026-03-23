<?php

// BUFFER OUTPUT IMMEDIATELY TO FIX "HEADERS ALREADY SENT"
ob_start();

// Set proper encoding for Czech characters

mb_internal_encoding('UTF-8');

mb_http_output('UTF-8');

require_once 'access.php';

// Handle file move request
if (isset($_POST['move_podcast_file']) && isAdmin()) {
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
        $podcastBaseDir = getRoot('podcast');

        // Construct paths carefully
        $sourcePath = $podcastBaseDir . $sourceCategory . '/';
        if (!empty($sourceSubfolder)) $sourcePath .= $sourceSubfolder . '/';

        $targetPath = $podcastBaseDir . $targetCategory . '/';
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

// Set proper permissions for podcast directories and files

$podcastsDir = getRoot('podcast');

// Ensure podcast directory exists and has proper permissions

if (!is_dir($podcastsDir)) {

    mkdir($podcastsDir, 0755, true);
} else {

    chmod($podcastsDir, 0755);
}

// Get existing podcast categories dynamically like edo.php

function getPodcastCategories()

{

    $podcastDir = getRoot('podcast');

    $categories = [];
// NEFUNGUJE TO, NAHRAVA SE TO DO ROOTU, NE DO PODCASTS, PROTOZE SE TO ZDE POKUSI VYTVORIT NOVOU STRUKTURU, KDE JE KATEGORIE PODLE JMEN, ALE TO NECHCEME, CHCEME ABY SE TO NAHRALO DO EXISTUJICI STRUKTURY PODCASTS, KDE JSOU PODLE JMEN KATEGORIE, A PODLE NICH PODLE JMEN PODKATEGORIE, A TEPRVE DO NICH SE NAHRAVAJI SOUBORY. PROTO JE TADY POTREBA OPRAVIT CESTU A UJISTIT SE, ZE SE TO NAHRAVA DO SPRAVNE STRUKTURY.
// PROSTE SE TO VYPLIVNE V TOM ROOTU A V ROOT-PORADCE NE
//
    if (is_dir($podcastDir)) {

        $items = array_diff(scandir($podcastDir), ['.', '..']);

        foreach ($items as $item) {

            $itemPath = $podcastDir . $item;

            if (is_dir($itemPath)) {

                // Assign different colors to different categories

                $colors = ['#3498db', '#e74c3c', '#2ecc71', '#f39c12', '#9b59b6', '#16a085', '#e67e22', '#e64e72', '#95a5a6'];

                $colorIndex = crc32($item) % count($colors);

                $categories[$item] = [

                        'name' => ucfirst($item), // Capitalize first letter

                        'color' => $colors[$colorIndex]

                ];
            }
        }
    }

    // If no categories exist, provide defaults

    return $categories;
}

// Get dynamic categories

$podcastCategories = getPodcastCategories();

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

// Helper function to get audio duration (basic estimation)

function getAudioDuration($filePath)
{

    $fileSize = filesize($filePath);

    // Rough estimation: MP3 at 128kbps ≈ 16KB per second

    $estimatedSeconds = $fileSize / 16000;

    $minutes = floor($estimatedSeconds / 60);

    $seconds = floor($estimatedSeconds % 60);

    return sprintf('%02d:%02d', $minutes, $seconds);
}

// Function to convert folder/file names to display names (remove underscores)

function formatDisplayName($name)
{

    return str_replace('_', ' ', $name);
}

// Function to scan for subfolders and podcast files (like edo.php)
function scanPodcastsRecursive($dir, $categoryKey, $categoryInfo, $depth = 0)
{
    $result = ['folders' => [], 'documents' => []];
    if (!is_dir($dir)) return $result;

    $items = array_diff(scandir($dir), ['.', '..']);
    foreach ($items as $item) {
        $itemPath = $dir . $item;

        if (is_dir($itemPath)) {
            // It's a subfolder
            $subResult = scanPodcastsRecursive($itemPath . '/', $categoryKey, $categoryInfo, $depth + 1);
            $result['folders'][$item] = [
                    'name' => $item,
                    'path' => $itemPath,
                    'data' => $subResult,
                    'depth' => $depth
            ];
        } else {
            // It's a file - add it directly
            $extension = strtolower(pathinfo($item, PATHINFO_EXTENSION));
            $allowedExtensions = ['mp3', 'wav', 'ogg', 'm4a', 'aac'];

            if (in_array($extension, $allowedExtensions)) {
                $duration = getAudioDuration($itemPath);

                $fileData = [
                        'name' => pathinfo($item, PATHINFO_FILENAME),
                        'original_name' => $item, // full filename with extension
                        'file_path' => str_replace(__DIR__ . '/../../', '', $itemPath),
                        'file_size' => filesize($itemPath),
                        'file_extension' => $extension,
                        'date_added' => filemtime($itemPath),
                        'duration' => $duration,
                        'category' => $categoryKey,
                        'category_info' => $categoryInfo,
                        'has_file' => true,
                        'id' => pathinfo($item, PATHINFO_FILENAME), // use filename as ID
                        'file_type_info' => getPodcastFileTypeInfo($extension)
                ];

                $result['documents'][] = $fileData;
            }
        }
    }

    return $result;
}

// Helper function to get file type info for podcasts
function getPodcastFileTypeInfo($extension)
{
    $extension = strtolower($extension);

    switch ($extension) {
        case 'mp3':
            return ['icon' => 'fas fa-music', 'color' => '#e74c3c'];
        case 'wav':
            return ['icon' => 'fas fa-waveform', 'color' => '#3498db'];
        case 'ogg':
            return ['icon' => 'fas fa-volume-high', 'color' => '#2ecc71'];
        default:
            return ['icon' => 'fas fa-headphones', 'color' => '#9b59b6'];
    }
}

// Function to count all documents recursively (only if not already declared)
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

// Set permissions for each category directory and its files

foreach ($podcastCategories as $categoryKey => $categoryInfo) {

    $categoryDir = $podcastsDir . $categoryKey . '/';

    if (is_dir($categoryDir)) {

        // Set directory permissions

        chmod($categoryDir, 0755);

        // Set permissions for all files in the directory

        $files = array_diff(scandir($categoryDir), ['.', '..']);

        foreach ($files as $file) {

            $filePath = $categoryDir . $file;

            if (is_file($filePath)) {

                chmod($filePath, 0644);
            }
        }
    }
}

?>


<!-- ...existing CSS styles... -->

<style>
    /* Podcasts page styling - copied and adapted from documents */
    #podcasts .content-area {
        display: flex;
        flex-direction: column;
        gap: 30px;
    }

    .add-podcast-section {
        background: white;
        border-radius: 10px;
        padding: 25px;
        margin-bottom: 25px;
        box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
    }

    .add-podcast-section h2 {
        color: #2c3e50;
        margin-top: 0;
        margin-bottom: 20px;
        font-size: 20px;
    }

    .podcast-form {
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

    .selected-files-info {
        margin-top: 8px;
        font-size: 14px;
        color: #34495e;
        padding: 5px;
    }

    /* Podcasts Grid */
    .podcasts-grid {
        display: flex;
        flex-direction: column;
        gap: 20px;
    }

    /* Podcast Category */
    .podcast-category {
        background: white;
        border-radius: 10px;
        box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        overflow: hidden;
        transition: all 0.3s ease;
    }

    .podcast-category:hover {
        box-shadow: 0 5px 15px rgba(0, 0, 0, 0.15);
    }

    .category-header {
        padding: 20px 25px;
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
        font-size: 20px;
        font-weight: 600;
    }

    .podcast-count {
        font-size: 14px;
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

    /* Podcasts List */
    .podcasts-list {
        padding: 0;
        max-height: 0;
        overflow: hidden;
        transition: max-height 0.3s ease-in-out, padding 0.3s ease-in-out;
        margin: 0;
    }

    .podcasts-list.open {
        padding: 20px;
    }

    /* Podcast Cards Grid - Updated to match PDF layout */
    .podcasts-grid-container {
        padding: 20px 0;
    }

    /* Podcast Card - Updated to match PDF card styling exactly */
    .podcast-card {
        background: white;
        border: 1px solid #e9ecef;
        border-radius: 8px;
        overflow: hidden;
        transition: all 0.2s;
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
        display: flex;
        flex-direction: column;
    }

    .podcast-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
        border-color: #dee2e6;
    }

    /* Podcast Preview - matching PDF preview */
    .podcast-preview {
        height: 120px;
        display: flex;
        align-items: center;
        justify-content: center;
        background: #f8f9fa;
        border-bottom: 1px solid #e9ecef;
        overflow: hidden;
    }

    .file-preview-icon {
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .file-preview-icon i {
        font-size: 48px !important;
        opacity: 0.8;
    }

    /* Podcast Info - matching PDF info layout */
    .podcast-info {
        padding: 15px;
        display: flex;
        flex-direction: column;
        gap: 8px;
        flex: 1;
    }

    .podcast-name {
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

    .file-indicator {
        font-size: 10px;
        margin-left: 4px;
    }

    /* Podcast Meta - matching PDF meta layout */
    .podcast-meta {
        display: flex;
        justify-content: space-between;
        align-items: center;
        font-size: 11px;
        color: #6c757d;
        margin-top: auto;
        flex-wrap: wrap;
        gap: 8px;
    }

    .podcast-date,
    .podcast-file-info,
    .podcast-duration {
        display: flex;
        align-items: center;
        gap: 4px;
        font-size: 11px;
        color: #6c757d;
    }

    .podcast-date i,
    .podcast-file-info i,
    .podcast-duration i {
        font-size: 10px;
        width: 12px;
    }

    /* Podcast Actions - matching PDF actions layout exactly */
    .podcast-actions {
        display: flex;
        gap: 6px;
        justify-content: flex-end;
        margin-top: 8px;
        padding-top: 8px;
        border-top: 1px solid #f0f0f0;
    }

    /* Use grid-btn styling to match PDFs exactly */
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
        cursor: pointer;
    }

    .grid-btn:hover {
        background: #e9ecef;
        color: #495057;
    }

    .view-btn:hover,
    .play-btn:hover {
        color: #007bff !important;
        border-color: #007bff !important;
    }

    .download-btn:hover {
        color: #28a745 !important;
        border-color: #28a745 !important;
    }

    .edit-btn:hover {
        color: #007bff !important;
        border-color: #007bff !important;
    }

    .delete-btn:hover {
        color: #dc3545 !important;
        border-color: #dc3545 !important;
    }

    /* Remove custom admin button styles - use admin-btns.css instead */

    /* No Podcasts State */
    .no-podcasts {
        text-align: center;
        padding: 60px 20px;
        color: #6c757d;
        background: white;
        border-radius: 10px;
        box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
    }

    .no-podcasts i {
        font-size: 64px;
        margin-bottom: 20px;
        opacity: 0.5;
    }

    .no-podcasts p {
        font-size: 18px;
        margin: 0 0 10px 0;
        font-weight: 500;
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

    /* Audio Modal Styles */
    .audio-modal .modal-content {
        max-width: 90vw;
        max-height: 90vh;
    }

    .audio-modal-body {
        padding: 20px;
        text-align: center;
    }

    .modal-preview-audio {
        max-width: 100%;
        border-radius: 8px;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
        -webkit-user-select: none;
        -moz-user-select: none;
        -ms-user-select: none;
        user-select: none;
    }

    /* Additional protection against downloads for non-admins */
    <?php if (!isAdmin()): ?>.modal-preview-audio::-webkit-media-controls-download-button {
        display: none;
    }

    .modal-preview-audio::-webkit-media-controls-enclosure {
        overflow: hidden;
    }

    <?php endif; ?>

    /* Additional modal form styles */
    .form-group {
        margin-bottom: 15px;
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
        padding-top: 15px;
        border-top: 1px solid #e9ecef;
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

    /* Responsive adjustments */
    @media (max-width: 1200px) {
        .podcasts-grid-container {
            grid-template-columns: repeat(auto-fill, minmax(160px, 1fr));
            gap: 12px;
        }
    }

    @media (max-width: 768px) {
        .podcasts-grid-container {
            grid-template-columns: repeat(auto-fill, minmax(140px, 1fr));
            gap: 10px;
        }

        .podcast-info {
            padding: 12px;
        }

        .podcast-preview {
            height: 100px;
        }

        .file-preview-icon i {
            font-size: 36px !important;
        }
    }

    @media (max-width: 480px) {
        .podcasts-grid-container {
            grid-template-columns: repeat(auto-fill, minmax(120px, 1fr));
            gap: 8px;
        }

        .podcasts-list.open {
            padding: 15px;
        }
    }

    /* Drag and Drop Styles */
    .podcast-card.dragging {
        opacity: 0.5;
        transform: scale(0.95);
        cursor: grabbing;
    }

    .subfolder-document.dragging {
        opacity: 0.5;
        transform: scale(0.95);
        cursor: grabbing;
    }

    .podcast-subfolder-card.drag-over {
        background: #e3f2fd !important;
        border-color: #2196f3 !important;
        border: 2px solid #2196f3 !important;
        box-shadow: 0 0 15px rgba(33, 150, 243, 0.5) !important;
    }

    .podcast-category.drag-over {
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

    .podcast-subfolder-card.drag-over .drop-zone-indicator {
        display: flex;
    }

    .podcast-category.drag-over .drop-zone-indicator {
        display: flex;
    }

    .podcast-subfolder-card {
        position: relative;
    }

    .podcast-category {
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

    /* Fix podcast category toggle functionality to match edo.php */
    .podcast-category.expanded {
        grid-column: 1 / -1;
        max-width: none;
        transform: none !important;
        z-index: 10;
    }

    .podcast-category.expanded .podcasts-list {
        max-height: none !important;
        padding: 20px !important;
        display: block !important;
        opacity: 1 !important;
        overflow: visible !important;
    }

    .podcast-category.expanded .category-toggle i {
        transform: rotate(180deg);
    }

    .podcast-category:hover:not(.expanded) {
        box-shadow: 0 5px 15px rgba(0, 0, 0, 0.15);
    }

    .podcast-category:not(.expanded) .podcasts-list {
        max-height: 0 !important;
        padding: 0 20px !important;
        overflow: hidden !important;
        opacity: 0 !important;
        display: none !important;
    }

    .category-toggle i {
        transition: transform 0.3s ease;
    }

    .podcasts-list {
        transition: all 0.3s ease;
        background: white;
    }

    /* Podcast Subfolder Styling - Copied from edo.php */
    .podcast-subfolders-section {
        margin-bottom: 25px;
    }

    .podcast-subfolders-section h4 {
        color: #495057;
        font-size: 14px;
        font-weight: 600;
        margin: 0 0 12px 0;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    .podcast-subfolders-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
        gap: 12px;
    }

    .podcast-subfolder-card {
        background: #f8f9fa;
        border: 1px solid #e9ecef;
        border-radius: 8px;
        padding: 15px;
        cursor: pointer;
        transition: all 0.2s;
    }

    .podcast-subfolder-card:hover {
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
    .podcast-subfolder-card.open {
        grid-column: 1 / -1;
        width: 100%;
        max-width: none;
        background: #fff;
        border-color: #dee2e6;
    }

    .podcast-subfolder-card.open .subfolder-content {
        max-height: 1000px;
        /* Large enough value */
        margin-top: 12px;
        padding-top: 12px;
        border-top: 1px solid #dee2e6;
    }

    .podcast-subfolder-card.open .subfolder-toggle {
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

    .mini-btn {
        padding: 4px 6px;
        border: 1px solid #dee2e6;
        border-radius: 4px;
        background: #f8f9fa;
        color: #6c757d;
        font-size: 10px;
        cursor: pointer;
        transition: all 0.2s ease;
        display: flex;
        align-items: center;
        justify-content: center;
        text-decoration: none;
    }

    .mini-btn:hover {
        background: #e9ecef;
        color: #495057;
    }

    .mini-btn.play-btn:hover {
        color: #007bff !important;
        border-color: #007bff !important;
    }

    .mini-btn.download-btn:hover {
        color: #28a745 !important;
        border-color: #28a745 !important;
    }

    .mini-btn.edit-btn:hover {
        color: #007bff !important;
        border-color: #007bff !important;
    }

    .mini-btn.delete-btn:hover {
        color: #dc3545 !important;
        border-color: #dc3545 !important;
    }

    .add-podcast-subfolder-btn {
        background: linear-gradient(135deg, #17a2b8 0%, #138496 100%) !important;
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

    .add-podcast-subfolder-btn:hover {
        background: #138496;
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

    .category-actions {
        margin-bottom: 20px;
        padding-bottom: 15px;
        border-bottom: 1px solid #e9ecef;
    }

    .action-btn {
        background: #007bff;
        color: white;
        border: 1px solid #007bff;
        padding: 8px 16px;
        border-radius: 5px;
        font-size: 14px;
        cursor: pointer;
        transition: all 0.2s ease;
        display: inline-flex;
        align-items: center;
        gap: 8px;
        text-decoration: none;
    }

    .action-btn:hover {
        background: #0056b3;
        border-color: #0056b3;
        color: white;
    }

    .podcast-documents-section {
        margin-top: 20px;
    }

    .podcast-documents-section h4 {
        color: #495057;
        font-size: 16px;
        font-weight: 600;
        margin: 0 0 15px 0;
        padding-bottom: 8px;
        border-bottom: 2px solid #e9ecef;
    }

    /* Responsive adjustments for subfolders */
    @media (max-width: 768px) {
        .podcast-subfolders-grid {
            grid-template-columns: 1fr;
            gap: 12px;
        }

        .subfolder-header {
            padding: 12px 15px;
        }

        .subfolder-documents {
            padding: 12px 15px;
        }

        .doc-actions {
            gap: 2px;
        }

        .mini-btn {
            padding: 3px 5px;
            font-size: 9px;
        }
    }
</style>

<div class="page-content" id="podcasts">

    <div class="content-area">
        <!-- Status Messages -->
        <div id="status-messages"></div>

        <!-- Podcasts Grid Container -->
        <div class="podcasts-grid-container">
            <?php
            $podcastsDir = getRoot('podcast');
            $podcastsByCategory = [];

            // Scan for podcasts and subfolders like edo.php
            if (is_dir($podcastsDir)) {
                foreach ($podcastCategories as $categoryKey => $categoryInfo) {
                    $categoryDir = $podcastsDir . $categoryKey . '/';
                    if (is_dir($categoryDir)) {
                        $categoryData = scanPodcastsRecursive($categoryDir, $categoryKey, $categoryInfo);
                        if (!empty($categoryData['documents']) || !empty($categoryData['folders'])) {
                            $podcastsByCategory[$categoryKey] = [
                                    'info' => $categoryInfo,
                                    'data' => $categoryData
                            ];
                        }
                    }
                }
            }

            if (empty($podcastsByCategory)): ?>
                <div class="no-podcasts">
                    <i class="fas fa-podcast"></i>
                    <p>Zatím nejsou žádné podcasty v systému.</p>
                </div>
            <?php else: ?>
                <div class="categories-grid">
                    <?php foreach ($podcastsByCategory as $categoryKey => $category): ?>
                        <div class="category-card droppable-zone"
                             data-category="<?= htmlspecialchars($categoryKey) ?>"
                             data-subfolder=""
                             ondrop="handlePodcastDrop(event)"
                             ondragover="handlePodcastDragOver(event)"
                             ondragenter="handlePodcastDragEnter(event)"
                             ondragleave="handlePodcastDragLeave(event)"
                             style="border-top: 4px solid <?= $category['info']['color'] ?>">

                            <div class="drop-zone-indicator">
                                <i class="fas fa-file-import"></i> &nbsp; Přetáhněte soubor sem
                            </div>
                            <div class="category-header category-header-clickable"
                                 onclick="togglePodcastCategoryHeader('<?= $categoryKey ?>')">
                                <div class="category-info">
                                    <h3 style="color: <?= $category['info']['color'] ?>">
                                        <i class="fas fa-folder"></i>
                                        <?= htmlspecialchars(formatDisplayName($category['info']['name'])) ?>
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
                                        <button class="admin-btn edit-btn"
                                                onclick="editPodcastCategory('<?= htmlspecialchars($categoryKey, ENT_QUOTES, 'UTF-8') ?>')"
                                                title="Upravit kategorii">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <button class="admin-btn delete-btn"
                                                onclick="deletePodcastCategory('<?= htmlspecialchars($categoryKey, ENT_QUOTES, 'UTF-8') ?>')"
                                                title="Smazat kategorii">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </div>
                                <?php endif; ?>

                                <div class="category-toggle">
                                    <i class="fas fa-chevron-down"></i>
                                </div>
                            </div>

                            <div class="category-content" id="podcast-category-content-<?= $categoryKey ?>">
                                <?php if (canUpload()): ?>
                                    <div class="category-actions">
                                        <button class="action-btn add-subfolder-btn"
                                                onclick="showAddPodcastSubfolderModal('<?= $categoryKey ?>')">
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
                                                <?php renderPodcastSubfolder($folderName, $folderData, $categoryKey, $category['info']); ?>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                <?php endif; ?>

                                <!-- Podcasts in main category -->
                                <?php if (!empty($category['data']['documents'])): ?>
                                    <div class="documents-section">
                                        <h4>Podcasty</h4>
                                        <div class="documents-grid">
                                            <?php foreach ($category['data']['documents'] as $podcast): ?>
                                                <?php renderPodcastCard($podcast, $categoryKey); ?>
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

        <!-- Add Podcast Section -->
        <?php if (canUpload()): ?>

            <div class="add-podcast-section">
                <h2>Nahrát podcast</h2>
                <form action="upload-category-form-handler.php" method="post" enctype="multipart/form-data"
                      class="podcast-form">

                    <input type="hidden" name="type" value="podcast">
                    <input type="hidden" name="redirect" value="podcasts">

                    <div class="form-row">
                        <div class="form-group">
                            <label for="custom-podcast-folder-name">Vytvořit novou kategorii (volitelné)</label>
                            <input type="text" id="custom-podcast-folder-name" name="custom_category" class="full-width"
                                   placeholder="Zadejte název pro novou kategorii..."
                                   onchange="togglePodcastFolderInputs()"
                                   oninput="togglePodcastFolderInputs()">
                            <small class="file-help">Pokud zadáte název, vytvoří se nová kategorie s tímto
                                názvem</small>
                        </div>
                    </div>

                    <div class="form-row" id="existing-podcast-folder-row">
                        <div class="form-group">
                            <label for="podcast-category">Nebo vyberte existující kategorii</label>
                            <select id="podcast-category" name="category" class="full-width"
                                    onchange="updatePodcastSubfolderOptions()">
                                <option value="">-- Vyberte kategorii --</option>
                                <?php foreach ($podcastCategories as $key => $category): ?>
                                    <option value="<?= $key ?>"><?= htmlspecialchars($category['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="podcast-subfolder">Podsložka</label>
                            <select id="podcast-subfolder" name="subfolder" class="full-width">
                                <option value="">-- Hlavní složka --</option>
                            </select>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="podcast_file">Audio soubor *</label>
                            <div class="upload-area">
                                <label for="podcast_file" class="custom-file-button">Vyberte audio soubor</label>
                                <input type="file" name="file" id="podcast_file" accept=".mp3,.wav,.ogg,.m4a,.aac"
                                       class="hidden-file-input" required>
                                <div id="selected-file" class="selected-files-info"></div>
                            </div>
                            <small class="file-help">Podporované formáty: MP3, WAV, OGG, M4A, AAC (max 200MB)</small>
                        </div>
                    </div>

                    <div class="form-row">
                        <button type="submit" class="upload-btn" name="podcast_submit">
                            <i class="fas fa-upload"></i> Nahrát podcast
                        </button>
                    </div>
                </form>
            </div>

        <?php endif; ?>

    </div>

</div>


<?php

// Function to render podcast subfolder (like edo.php)

function renderPodcastSubfolder($folderName, $folderData, $categoryKey, $categoryInfo)
{
    ?>
    <div class="subfolder-card droppable-zone"
         data-category="<?= htmlspecialchars($categoryKey) ?>"
         data-subfolder="<?= htmlspecialchars($folderName) ?>"
         onclick="togglePodcastSubfolder('<?= $categoryKey ?>-<?= $folderName ?>')"
         ondrop="handlePodcastDrop(event)"
         ondragover="handlePodcastDragOver(event)"
         ondragenter="handlePodcastDragEnter(event)"
         ondragleave="handlePodcastDragLeave(event)">

        <div class="drop-zone-indicator">
            <i class="fas fa-file-import"></i> &nbsp; Přetáhněte soubor sem
        </div>

        <div class="subfolder-header">
            <div class="subfolder-info">
                <i class="fas fa-folder" style="color: <?= $categoryInfo['color'] ?>"></i>
                <h5><?= htmlspecialchars(formatDisplayName($folderName)) ?></h5>
            </div>
            <div class="subfolder-stats">
                <span class="doc-count"><?= count($folderData['data']['documents']) ?></span>
                <?php if (isAdmin()): ?>
                    <button class="mini-btn edit-btn"
                            onclick="event.stopPropagation(); editPodcastSubfolder('<?= $categoryKey ?>', '<?= $folderName ?>')"
                            title="Upravit podsložku">
                        <i class="fas fa-edit"></i>
                    </button>
                    <button class="mini-btn delete-btn"
                            onclick="event.stopPropagation(); deletePodcastSubfolder('<?= $categoryKey ?>', '<?= $folderName ?>')"
                            title="Smazat podsložku">
                        <i class="fas fa-trash"></i>
                    </button>
                <?php endif; ?>
                <i class="fas fa-chevron-right subfolder-toggle"></i>
            </div>
        </div>

        <div class="subfolder-content">
            <div class="subfolder-documents">
                <?php if (!empty($folderData['data']['documents'])): ?>
                    <?php foreach ($folderData['data']['documents'] as $podcast): ?>
                        <div class="subfolder-document <?= isAdmin() ? 'draggable-file' : '' ?>"
                             draggable="<?= isAdmin() ? 'true' : 'false' ?>"
                             data-file-name="<?= htmlspecialchars($podcast['original_name']) ?>"
                             data-current-category="<?= htmlspecialchars($categoryKey) ?>"
                             data-current-subfolder="<?= htmlspecialchars($folderName) ?>"
                                <?php if (isAdmin()): ?>
                                    ondragstart="handlePodcastDragStart(event)"
                                    ondragend="handlePodcastDragEnd(event)"
                                <?php endif; ?>>

                            <?php
                            $webUrl = str_replace($_SERVER['DOCUMENT_ROOT'], '', $podcast['file_path']);
                            $webUrl = str_replace('//', '/', $webUrl);
                            ?>

                            <?php if (isAdmin()): ?>
                                <i class="fas fa-arrows-alt drag-handle"></i>
                            <?php endif; ?>
                            <div class="doc-icon">
                                <i class="<?= $podcast['file_type_info']['icon'] ?>"
                                   style="color: <?= $podcast['file_type_info']['color'] ?>;"></i>
                            </div>
                            <div class="doc-info">
                                <span class="doc-name"><?= htmlspecialchars(formatDisplayName($podcast['name'])) ?></span>
                                <span class="doc-size"><?= formatBytes($podcast['file_size']) ?></span>
                            </div>
                            <div class="doc-actions">
                                <button class="mini-btn play-btn"
                                        onclick="event.stopPropagation(); togglePodcast(this, '<?= htmlspecialchars($webUrl) ?>')"
                                        title="Přehrát">
                                    <i class="fas fa-play"></i>
                                </button>
                                <a href="<?= htmlspecialchars($webUrl) ?>" download class="mini-btn download-btn"
                                   onclick="event.stopPropagation();" title="Stáhnout">
                                    <i class="fas fa-download"></i>
                                </a>
                                <?php if (isAdmin()): ?>
                                    <button class="mini-btn edit-btn"
                                            onclick="event.stopPropagation(); editPodcastFile('<?= addslashes($podcast['original_name']) ?>', '<?= $categoryKey ?>', '<?= addslashes($folderName) ?>', '<?= addslashes($podcast['name']) ?>')"
                                            title="Přejmenovat">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <button class="mini-btn delete-btn"
                                            onclick="event.stopPropagation(); deletePodcast('<?= addslashes($podcast['original_name']) ?>', '<?= $categoryKey ?>', '<?= addslashes($folderName) ?>')"
                                            title="Smazat">
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

// Function to render podcast card (like documents.php)
function renderPodcastCard($podcast, $categoryKey)
{
    ?>
    <div class="document-card-grid <?= isAdmin() ? 'draggable-file' : '' ?>"
         draggable="<?= isAdmin() ? 'true' : 'false' ?>"
         data-file-name="<?= htmlspecialchars($podcast['original_name']) ?>"
         data-current-category="<?= htmlspecialchars($categoryKey) ?>"
         data-current-subfolder=""
            <?php if (isAdmin()): ?>
                ondragstart="handlePodcastDragStart(event)"
                ondragend="handlePodcastDragEnd(event)"
            <?php endif; ?>>
        <div class="document-preview">
            <?php if (isAdmin()): ?>
                <i class="fas fa-arrows-alt drag-handle"></i>
            <?php endif; ?>
            <div class="document-icon">
                <i class="<?= $podcast['file_type_info']['icon'] ?>"
                   style="color: <?= $podcast['file_type_info']['color'] ?>; font-size: 32px;"></i>
            </div>
        </div>

        <div class="document-info-grid">
            <h5 class="document-title"><?= htmlspecialchars(formatDisplayName($podcast['name'])) ?></h5>

            <div class="document-meta-grid">
                <span class="document-date">
                    <i class="fas fa-calendar"></i>
                    <?= date('d.m.Y', $podcast['date_added']) ?>
                </span>
                <span class="document-size">
                    <i class="fas fa-file-audio"></i>
                    <?= formatBytes($podcast['file_size']) ?>
                </span>
            </div>

            <div class="document-actions-grid">
                <?php
                $webUrl = str_replace($_SERVER['DOCUMENT_ROOT'], '', $podcast['file_path']);
                $webUrl = str_replace('//', '/', $webUrl);
                ?>

                <button class="grid-btn play-btn"
                        onclick="event.stopPropagation(); togglePodcast(this, '<?= htmlspecialchars($webUrl) ?>')">
                    <i class="fas fa-play"></i>
                </button>

                <a href="<?= htmlspecialchars($webUrl) ?>" download class="grid-btn download-btn"
                   onclick="event.stopPropagation()">
                    <i class="fas fa-download"></i>
                </a>

                <?php if (isAdmin()): ?>
                    <button class="grid-btn edit-btn"
                            onclick="event.stopPropagation(); editPodcastFile('<?= addslashes($podcast['original_name']) ?>', '<?= $categoryKey ?>', '', '<?= addslashes($podcast['name']) ?>')">
                        <i class="fas fa-edit"></i>
                    </button>
                    <button class="grid-btn delete-btn"
                            onclick="event.stopPropagation(); deletePodcast('<?= addslashes($podcast['original_name']) ?>', '<?= $categoryKey ?>', '')">
                        <i class="fas fa-trash"></i>
                    </button>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php
}

?>

<!-- ...existing modals... -->
<div id="addPodcastSubfolderModal" class="modal" style="display: none;">
    <div class="modal-content">
        <div class="modal-header">
            <h2>Přidat podsložku podcastů</h2>
            <button class="modal-close" onclick="closeAddPodcastSubfolderModal()">&times;</button>
        </div>
        <form id="addPodcastSubfolderForm">
            <input type="hidden" id="podcastParentCategory" name="parent_category">

            <div class="form-group">
                <label for="podcastSubfolderName">Název podsložky *</label>
                <input type="text" id="podcastSubfolderName" name="subfolder_name" required
                       placeholder="Název nové podsložky" class="full-width">
            </div>

            <div class="modal-actions">
                <button type="button" class="btn btn-secondary" onclick="closeAddPodcastSubfolderModal()">Zrušit
                </button>
                <button type="submit" class="btn btn-primary">Vytvořit podsložku</button>
            </div>
        </form>
    </div>
</div>

<script>
    // ...existing code...

    let podcastSubfolderData = {};

    // Audio playback functionality
    let currentAudio = null;
    let currentButton = null;

    function togglePodcast(btn, filePath) {
        if (event) {
            event.stopPropagation();
            event.preventDefault();
        }

        const icon = btn.querySelector('i');

        // If clicking the same button that is currently playing
        if (currentButton === btn && currentAudio && !currentAudio.paused) {
            currentAudio.pause();
            icon.classList.remove('fa-pause');
            icon.classList.add('fa-play');
            return;
        }

        // If clicking a paused audio that is already loaded
        if (currentButton === btn && currentAudio && currentAudio.paused) {
            currentAudio.play();
            icon.classList.remove('fa-play');
            icon.classList.add('fa-pause');
            return;
        }

        // Stop any currently playing audio
        if (currentAudio) {
            currentAudio.pause();
            if (currentButton) {
                const oldIcon = currentButton.querySelector('i');
                if (oldIcon) {
                    oldIcon.classList.remove('fa-pause');
                    oldIcon.classList.add('fa-play');
                }
            }
        }

        // Setup new audio
        currentButton = btn;

        currentAudio = new Audio(filePath);

        // Show loading state
        icon.classList.remove('fa-play');
        icon.classList.add('fa-spinner', 'fa-spin');

        currentAudio.oncanplay = function () {
            icon.classList.remove('fa-spinner', 'fa-spin');
            icon.classList.add('fa-pause');
            currentAudio.play().catch(e => {
                console.error('Playback failed:', e);
                icon.classList.remove('fa-pause');
                icon.classList.add('fa-play');
                showStatusMessage('error', 'Nepodařilo se přehrát audio soubor.');
            });
        };

        currentAudio.onended = function () {
            icon.classList.remove('fa-pause');
            icon.classList.add('fa-play');
            currentButton = null;
            currentAudio = null;
        };

        currentAudio.onerror = function () {
            console.error('Error loading audio:', currentAudio.error);
            icon.classList.remove('fa-spinner', 'fa-spin');
            icon.classList.remove('fa-pause');
            icon.classList.add('fa-exclamation-circle');
            showStatusMessage('error', 'Chyba při načítání audio souboru.');
        };
    }

    // Load podcast subfolder data like edo.php
    function loadPodcastSubfolderData() {
        fetch('get-podcast-subfolders.php')
            .then(response => response.json())
            .then(data => {
                podcastSubfolderData = data || {};
                updatePodcastSubfolderOptions();
            })
            .catch(error => console.log('No podcast subfolder data available yet:', error.message));
    }

    // Update podcast subfolder dropdown like edo.php
    function updatePodcastSubfolderOptions() {
        const mainFolder = document.getElementById('podcast-category');
        const subfolderSelect = document.getElementById('podcast-subfolder');

        if (!mainFolder || !subfolderSelect) return;

        subfolderSelect.innerHTML = '<option value="">-- Hlavní složka --</option>';

        if (podcastSubfolderData[mainFolder.value]) {
            podcastSubfolderData[mainFolder.value].forEach(subfolder => {
                const option = document.createElement('option');
                option.value = subfolder;
                option.textContent = subfolder;
                subfolderSelect.appendChild(option);
            });
        }
    }

    // Add missing functions for podcast category management
    function editPodcastCategory(categoryKey) {
        if (event) {
            event.stopPropagation();
            event.preventDefault();
        }

        console.log('Opening edit category modal for:', categoryKey); // Debug log

        document.getElementById('editPodcastOriginalName').value = categoryKey;
        document.getElementById('editPodcastCategoryName').value = categoryKey;
        document.getElementById('editPodcastCategoryModal').style.display = 'flex';
        setTimeout(() => document.getElementById('editPodcastCategoryName').focus(), 100);
    }

    function closeEditPodcastCategoryModal() {
        document.getElementById('editPodcastCategoryModal').style.display = 'none';
        document.getElementById('editPodcastCategoryForm').reset();
    }

    function deletePodcastCategory(categoryKey) {
        if (event) {
            event.stopPropagation();
            event.preventDefault();
        }

        if (!categoryKey) return;

        if (confirm(`Opravdu chcete smazat kategorii "${categoryKey}" včetně všech podcastů?`)) {
            const formData = new FormData();
            formData.append('category', categoryKey);
            formData.append('type', 'podcast');

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
                    showStatusMessage('error', 'Chyba při mazání kategorie: ' + error.message);
                });
        }
    }

    function editPodcastSubfolder(categoryKey, subfolderName) {
        if (event) {
            event.stopPropagation();
            event.preventDefault();
        }
        document.getElementById('editPodcastSubfolderParentCategory').value = categoryKey;
        document.getElementById('editPodcastSubfolderOriginalName').value = subfolderName;
        document.getElementById('editPodcastSubfolderName').value = subfolderName;
        document.getElementById('editPodcastSubfolderModal').style.display = 'flex';
        setTimeout(() => document.getElementById('editPodcastSubfolderName').focus(), 100);
    }

    function closeEditPodcastSubfolderModal() {
        document.getElementById('editPodcastSubfolderModal').style.display = 'none';
        document.getElementById('editPodcastSubfolderForm').reset();
    }

    function deletePodcastSubfolder(categoryKey, subfolderName) {
        if (!confirm(`Opravdu chcete smazat podsložku "${subfolderName}" včetně všech podcastů?`)) return;

        const formData = new FormData();
        formData.append('category', categoryKey);
        formData.append('subfolder', subfolderName);
        formData.append('type', 'podcast');

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
                alert('Došlo k chybě na serveru. Zkontrolujte konzoli.');
            });
    }

    // Toggle podcast category like edo.php
    function togglePodcastCategoryHeader(categoryKey) {
        if (!document.getElementById('podcasts')) return;

        const categoryCard = document.querySelector(`#podcasts .category-card[data-category="${categoryKey}"]`);
        if (!categoryCard) return;

        if (categoryCard.classList.contains('expanded')) {
            categoryCard.classList.remove('expanded');
        } else {
            document.querySelectorAll('#podcasts .category-card.expanded').forEach(card => {
                card.classList.remove('expanded');
            });
            categoryCard.classList.add('expanded');
        }
    }

    // Toggle podcast subfolder like edo.php
    function togglePodcastSubfolder(subfolderId) {
        if (event) event.stopPropagation();
        if (!document.getElementById('podcasts')) return;

        const subfolderCard = document.querySelector(`[onclick="togglePodcastSubfolder('${subfolderId}')"]`);
        if (!subfolderCard) return;

        subfolderCard.classList.toggle('open');
    }

    // Show add podcast subfolder modal like edo.php
    function showAddPodcastSubfolderModal(categoryKey) {
        if (event) {
            event.stopPropagation();
            event.preventDefault();
        }

        document.getElementById('podcastParentCategory').value = categoryKey;
        document.getElementById('addPodcastSubfolderModal').style.display = 'flex';
        document.getElementById('addPodcastSubfolderForm').reset();
        setTimeout(() => document.getElementById('podcastSubfolderName').focus(), 100);
    }

    function closeAddPodcastSubfolderModal() {
        document.getElementById('addPodcastSubfolderModal').style.display = 'none';
        document.getElementById('addPodcastSubfolderForm').reset();
    }

    // Toggle folder inputs for custom category creation like edo.php
    function togglePodcastFolderInputs() {
        const customInput = document.getElementById('custom-podcast-folder-name');
        const existingRow = document.getElementById('existing-podcast-folder-row');
        const categorySelect = document.getElementById('podcast-category');
        const subfolderSelect = document.getElementById('podcast-subfolder');

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

    // --- DRAG AND DROP LOGIC ---
    var draggedPodcastElement = null;
    var draggedPodcastFileData = null;

    function handlePodcastDragStart(event) {
        draggedPodcastElement = event.currentTarget;
        draggedPodcastFileData = {
            fileName: draggedPodcastElement.dataset.fileName,
            sourceCategory: draggedPodcastElement.dataset.currentCategory,
            sourceSubfolder: draggedPodcastElement.dataset.currentSubfolder
        };
        draggedPodcastElement.classList.add('dragging');
        event.dataTransfer.effectAllowed = 'move';
    }

    function handlePodcastDragEnd(event) {
        draggedPodcastElement.classList.remove('dragging');
        document.querySelectorAll('#podcasts .droppable-zone').forEach(zone => {
            zone.classList.remove('drag-over');
        });
    }

    function handlePodcastDragOver(event) {
        event.preventDefault();
        event.dataTransfer.dropEffect = 'move';
    }

    function handlePodcastDragEnter(event) {
        if (event.currentTarget.classList.contains('droppable-zone')) {
            event.currentTarget.classList.add('drag-over');
        }
    }

    function handlePodcastDragLeave(event) {
        if (event.currentTarget.classList.contains('droppable-zone')) {
            event.currentTarget.classList.remove('drag-over');
        }
    }

    async function handlePodcastDrop(event) {
        event.preventDefault();
        event.stopPropagation();

        const dropZone = event.currentTarget;
        dropZone.classList.remove('drag-over');

        if (!draggedPodcastFileData) return;

        const targetCategory = dropZone.dataset.category;
        const targetSubfolder = dropZone.dataset.subfolder || '';

        // Check if same location
        if (draggedPodcastFileData.sourceCategory === targetCategory &&
            draggedPodcastFileData.sourceSubfolder === targetSubfolder) {
            return;
        }

        await movePodcastFile(draggedPodcastFileData, targetCategory, targetSubfolder);
    }

    async function movePodcastFile(fileData, targetCategory, targetSubfolder) {
        const formData = new FormData();
        formData.append('move_podcast_file', '1');
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
                showStatusMessage('success', 'Podcast byl úspěšně přesunut!');
                setTimeout(() => location.reload(), 1000);
            } else if (response.status === 400) {
                showStatusMessage('error', 'Neplatné zadání.');
            } else if (response.status === 404) {
                showStatusMessage('error', 'Podcast nebyl nalezen.');
            } else {
                showStatusMessage('error', 'Chyba při přesunu podcastu.');
            }
        } catch (error) {
            showStatusMessage('error', 'Chyba při komunikaci se serverem.');
        }
    }

    // Update delete function to handle subfolders like edo.php
    function deletePodcast(fileName, category, subfolder = '') {
        if (!fileName || !category) return;

        if (confirm(`Opravdu chcete smazat podcast "${fileName}"?`)) {
            const formData = new FormData();
            formData.append('type', 'podcast');
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
                        if (typeof showStatusMessage === 'function') {
                            showStatusMessage('success', data.message);
                        }

                        // --- BLESKOVÉ SMAZÁNÍ Z DOMU (VYLEPŠENÝ SELEKTOR) ---
                        // Hledáme prvek, který má přesné jméno, kategorii I PODSLOŽKU
                        const selector = `[data-file-name="${fileName}"][data-current-category="${category}"][data-current-subfolder="${subfolder}"]`;
                        const fileElement = document.querySelector(selector);

                        if (fileElement) {
                            // Sexy animace: prvek se smrskne a odletí doleva
                            fileElement.style.transition = 'all 0.4s cubic-bezier(0.4, 0, 0.2, 1)';
                            fileElement.style.opacity = '0';
                            fileElement.style.transform = 'scale(0.7) translateX(-30px)';
                            fileElement.style.pointerEvents = 'none';

                            setTimeout(() => {
                                fileElement.remove();

                                // Pokud je to v podsložce, zkontrolujeme, jestli nezůstala prázdná
                                if (subfolder) {
                                    const subfolderCard = document.querySelector(`[data-subfolder="${subfolder}"][data-category="${category}"]`);
                                    if (subfolderCard) {
                                        const countBadge = subfolderCard.querySelector('.doc-count');
                                        if (countBadge) {
                                            let count = parseInt(countBadge.textContent) - 1;
                                            countBadge.textContent = Math.max(0, count);
                                        }
                                    }
                                }
                            }, 400);
                        } else {
                            // Pokud selektor selže, aspoň to jistíme refreshem
                            console.warn("Prvek nebyl v DOMu nalezen přes selektor: " + selector);
                            setTimeout(() => location.reload(), 500);
                        }
                    } else {
                        alert(data.message);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Chyba při komunikaci se serverem.');
                });
        }
    }

    // Update edit podcast file function to match edo.php pattern
    function editPodcastFile(originalName, category, subfolder, currentName) {
        console.log('Opening edit modal for:', originalName, category, subfolder, currentName); // Debug log

        document.getElementById('editPodcastFileCategory').value = category;
        document.getElementById('editPodcastFileSubfolder').value = subfolder || '';
        document.getElementById('editPodcastFileOriginalName').value = originalName;

        // Remove extension from current name for editing
        document.getElementById('editPodcastFileName').value = currentName.replace(/\.[^/.]+$/, "");

        document.getElementById('editPodcastFileModal').style.display = 'flex';
        setTimeout(() => document.getElementById('editPodcastFileName').focus(), 100);
    }

    function closeEditPodcastFileModal() {
        document.getElementById('editPodcastFileModal').style.display = 'none';
        document.getElementById('editPodcastFileForm').reset();
    }

    // Initialize form handlers for podcast file management
    document.addEventListener('DOMContentLoaded', function () {
        // Only run if we're on the podcasts page
        if (!document.getElementById('podcasts')) {
            return;
        }

        // Load podcast subfolder data
        loadPodcastSubfolderData();

        // Close all categories by default
        document.querySelectorAll('#podcasts .category-content').forEach(content => {
            if (content) {
                content.style.maxHeight = '0px';
            }
        });

        // Handle add podcast subfolder form submission
        const addPodcastSubfolderForm = document.getElementById('addPodcastSubfolderForm');
        if (addPodcastSubfolderForm) {
            addPodcastSubfolderForm.addEventListener('submit', function (e) {
                e.preventDefault();

                const formData = new FormData(this);

                fetch('add-podcast-subfolder.php', {
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
                                closeAddPodcastSubfolderModal();
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

        // Handle edit podcast category form submission
        const editPodcastCategoryForm = document.getElementById('editPodcastCategoryForm');
        if (editPodcastCategoryForm) {
            editPodcastCategoryForm.addEventListener('submit', function (e) {
                e.preventDefault();

                const originalName = document.getElementById('editPodcastOriginalName').value;
                const newName = document.getElementById('editPodcastCategoryName').value;

                if (!originalName || !newName) {
                    alert('Chybí povinné údaje');
                    return;
                }

                const formData = new FormData(this);
                formData.set('original_name', originalName);
                formData.set('new_name', newName);

                // PŘIDÁNO: Řekneme univerzálnímu handleru, že jde o podcasty
                formData.append('type', 'podcast');

                const submitBtn = this.querySelector('button[type="submit"]');
                const originalText = submitBtn.innerHTML;
                submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Ukládá se...';
                submitBtn.disabled = true;

                // ZMĚNĚNO: Teď už nevoláme specifický soubor, ale ten univerzální
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
                                closeEditPodcastCategoryModal();
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

        // Handle edit podcast file form submission
        // Handler pro přejmenování PODCASTU
        const editPodFileForm = document.getElementById('editPodcastFileForm');
        if (editPodFileForm) {
            editPodFileForm.addEventListener('submit', function (e) {
                e.preventDefault();

                // 1. Sesbíráme data z hidden inputů v modalu
                const category = document.getElementById('editPodcastFileCategory').value;
                const subfolder = document.getElementById('editPodcastFileSubfolder').value;
                const originalName = document.getElementById('editPodcastFileOriginalName').value;
                const newName = document.getElementById('editPodcastFileName').value;

                if (!category || !originalName || !newName) {
                    alert('Chybí povinné údaje pro přejmenování.');
                    return;
                }

                const formData = new FormData();
                // 2. IDENTIFIKACE: 'podcast'
                formData.append('type', 'podcast');
                formData.append('category', category);
                formData.append('subfolder', subfolder);
                formData.append('original_name', originalName);
                formData.append('new_name', newName);

                const submitBtn = this.querySelector('button[type="submit"]');
                const originalBtnText = submitBtn.innerHTML;
                submitBtn.disabled = true;
                submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';

                // 3. PÁLÍME NA UNIVERZÁLNÍ HANDLER
                fetch('edit-file-handler.php', {
                    method: 'POST',
                    body: formData
                })
                    .then(res => res.json())
                    .then(data => {
                        if (data.success) {
                            closeEditPodcastFileModal(); // Zavřeme modal
                            showStatusMessage('success', data.message);
                            // Reload je u podcastů nutný, aby si přehrávač načetl novou cestu
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

        // Handle edit podcast subfolder form submission
        // Odeslání formuláře pro úpravu podsložky
        // Odeslání formuláře pro úpravu podsložky
        const editPodcastSubfolderForm = document.getElementById('editPodcastSubfolderForm');
        if (editPodcastSubfolderForm) {
            editPodcastSubfolderForm.addEventListener('submit', function (e) {
                e.preventDefault();

                const formData = new FormData(this);
                // Tady řekneme handleru, že pracujeme v podcastech
                formData.append('type', 'podcast');

                const submitBtn = this.querySelector('button[type="submit"]');
                submitBtn.disabled = true;
                submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';

                fetch('edit-subfolder-handler.php', {
                    method: 'POST',
                    body: formData,
                    headers: {'X-Requested-With': 'XMLHttpRequest'}
                })
                    .then(res => res.json())
                    .then(data => {
                        if (data.success) {
                            showStatusMessage('success', data.message);
                            if (window.closeEditPodcastSubfolderModal) closeEditPodcastSubfolderModal();
                            setTimeout(() => location.reload(), 600);
                        } else {
                            alert(data.message);
                            submitBtn.disabled = false;
                            submitBtn.innerHTML = 'Uložit změny';
                        }
                    })
                    .catch(err => {
                        console.error('Chyba:', err);
                        alert('Chyba komunikace se serverem.');
                        submitBtn.disabled = false;
                    });
            });
        }

        // ...existing code...
    });

    // ...existing code...
</script>

<!-- Add missing podcast category management modal -->
<div id="editPodcastCategoryModal" class="modal" style="display: none;">
    <div class="modal-content">
        <div class="modal-header">
            <h2>Upravit kategorii podcastů</h2>
            <button class="modal-close" onclick="closeEditPodcastCategoryModal()">&times;</button>
        </div>
        <form id="editPodcastCategoryForm" enctype="multipart/form-data">
            <input type="hidden" id="editPodcastOriginalName" name="original_name">

            <div class="form-group">
                <label for="editPodcastCategoryName">Název kategorie *</label>
                <input type="text" id="editPodcastCategoryName" name="new_name" required
                       placeholder="Nový název kategorie" class="full-width">
            </div>

            <div class="form-group">
                <label for="editPodcastCategoryFile">Nahrát podcast do kategorie (volitelné)</label>
                <div class="upload-area">
                    <label for="editPodcastCategoryFile" class="custom-file-button">Vyberte audio soubor</label>
                    <input type="file" name="category_file" id="editPodcastCategoryFile"
                           accept=".mp3,.wav,.ogg,.m4a,.aac" class="hidden-file-input">
                    <div id="edit-podcast-category-selected-file" class="selected-files-info"></div>
                </div>
                <small class="file-help">Podporované formáty: MP3, WAV, OGG, M4A, AAC (max 200MB)</small>
            </div>

            <div class="form-group">
                <label for="editPodcastCategoryCustomFilename">Přejmenovat soubor (volitelné)</label>
                <input type="text" id="editPodcastCategoryCustomFilename" name="custom_filename" class="full-width"
                       placeholder="Ponechte prázdné pro původní název">
                <small class="file-help">Zadejte pouze název bez přípony (přípona se doplní automaticky)</small>
            </div>

            <div class="modal-actions">
                <button type="button" class="btn btn-secondary" onclick="closeEditPodcastCategoryModal()">Zrušit
                </button>
                <button type="submit" class="btn btn-primary">Uložit změny</button>
            </div>
        </form>
    </div>
</div>

<!-- Add missing podcast file edit modal -->
<div id="editPodcastFileModal" class="modal" style="display: none;">
    <div class="modal-content">
        <div class="modal-header">
            <h2>Přejmenovat podcast</h2>
            <button class="modal-close" onclick="closeEditPodcastFileModal()">&times;</button>
        </div>
        <form id="editPodcastFileForm">
            <input type="hidden" id="editPodcastFileCategory" name="category">
            <input type="hidden" id="editPodcastFileSubfolder" name="subfolder">
            <input type="hidden" id="editPodcastFileOriginalName" name="original_name">

            <div class="form-group">
                <label for="editPodcastFileName">Název souboru *</label>
                <input type="text" id="editPodcastFileName" name="new_name" required
                       placeholder="Nový název souboru (bez přípony)" class="full-width">
                <small class="file-help">Zadejte pouze název bez přípony - přípona zůstane zachována</small>
            </div>

            <div class="modal-actions">
                <button type="button" class="btn btn-secondary" onclick="closeEditPodcastFileModal()">Zrušit</button>
                <button type="submit" class="btn btn-primary">Přejmenovat</button>
            </div>
        </form>
    </div>
</div>

<!-- Add missing podcast subfolder edit modal -->
<div id="editPodcastSubfolderModal" class="modal" style="display: none;">
    <div class="modal-content">
        <div class="modal-header">
            <h2>Upravit podsložku podcastů</h2>
            <button class="modal-close" onclick="closeEditPodcastSubfolderModal()">&times;</button>
        </div>
        <form id="editPodcastSubfolderForm" enctype="multipart/form-data">
            <input type="hidden" id="editPodcastSubfolderParentCategory" name="parent_category">
            <input type="hidden" id="editPodcastSubfolderOriginalName" name="original_name">

            <div class="form-group">
                <label for="editPodcastSubfolderName">Název podsložky *</label>
                <input type="text" id="editPodcastSubfolderName" name="new_name" required
                       placeholder="Nový název podsložky" class="full-width">
            </div>

            <div class="form-group">
                <label for="editPodcastSubfolderFile">Nahrát podcast do podsložky (volitelné)</label>
                <div class="upload-area">
                    <label for="editPodcastSubfolderFile" class="custom-file-button">Vyberte audio soubor</label>
                    <input type="file" name="subfolder_file" id="editPodcastSubfolderFile"
                           accept=".mp3,.wav,.ogg,.m4a,.aac" class="hidden-file-input">
                    <div id="edit-podcast-subfolder-selected-file" class="selected-files-info"></div>
                </div>
                <small class="file-help">Podporované formáty: MP3, WAV, OGG, M4A, AAC (max 200MB)</small>
            </div>

            <div class="form-group">
                <label for="editPodcastSubfolderCustomFilename">Přejmenovat soubor (volitelné)</label>
                <input type="text" id="editPodcastSubfolderCustomFilename" name="custom_filename" class="full-width"
                       placeholder="Ponechte prázdné pro původní název">
                <small class="file-help">Zadejte pouze název bez přípony (přípona se doplní automaticky)</small>
            </div>

            <div class="modal-actions">
                <button type="button" class="btn btn-secondary" onclick="closeEditPodcastSubfolderModal()">Zrušit
                </button>
                <button type="submit" class="btn btn-primary">Uložit změny</button>
            </div>
        </form>
    </div>
</div>

