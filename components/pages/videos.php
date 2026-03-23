<?php

require_once __DIR__ . '/../../access.php';

$isAdmin = isAdmin() || isManager();

?>



<!--Page Videos -->

<div class="page-content" id="videos">

    <div class="content-area">

        <!-- Status Messages -->

        <div id="status-messages"></div>



        <!-- Videos Grid -->

        <div class="videos-grid">

            <?php

            $baseDir = getRoot('videos');

            $videosByFolder = [];



            if (is_dir($baseDir)) {

                $folders = array_filter(scandir($baseDir), function ($f) use ($baseDir) {

                    return $f !== '.' && $f !== '..' && is_dir($baseDir . $f);
                });



                foreach ($folders as $folder) {

                    $folderPath = $baseDir . $folder . '/';

                    $files = array_diff(scandir($folderPath), ['.', '..']);



                    foreach ($files as $file) {

                        $filePath = $folderPath . $file;

                        if (is_file($filePath)) {

                            $fileExt = strtolower(pathinfo($file, PATHINFO_EXTENSION));

                            if (in_array($fileExt, ['mp4', 'avi', 'mov', 'mkv', 'webm', 'mp3', 'wav', 'aac'])) {

                                $videoData = [

                                    'name' => pathinfo($file, PATHINFO_FILENAME),

                                    'file_name' => $file,

                                    'file_path' => getRoot('videos') . $folder . '/' . $file,

                                    'file_extension' => $fileExt,

                                    'file_size' => filesize($filePath),

                                    'date_added' => filemtime($filePath),

                                    'folder' => $folder

                                ];



                                if (!isset($videosByFolder[$folder])) {

                                    $videosByFolder[$folder] = [];
                                }

                                $videosByFolder[$folder][] = $videoData;
                            }
                        }
                    }
                }
            }



            // Helper function to format file size (local to avoid conflicts)

            function formatVideoFileSize($size, $precision = 2)

            {

                $units = array('B', 'KB', 'MB', 'GB', 'TB');

                for ($i = 0; $size > 1024 && $i < count($units) - 1; $i++) {

                    $size /= 1024;
                }

                return round($size, $precision) . ' ' . $units[$i];
            }



            if (empty($videosByFolder)): ?>

                <div class="no-videos">

                    <i class="fas fa-video"></i>

                    <p>Zatím nejsou žádná videa v systému.</p>

                </div>

            <?php else: ?>

                <?php foreach ($videosByFolder as $folderName => $videos): ?>

                    <div class="video-category">

                        <div class="category-header" onclick="toggleCategory('<?= $folderName ?>')">

                            <div class="category-info">

                                <div class="folder-icon-wrapper">

                                    <i class="fas fa-folder folder-icon"></i>

                                    <h3><?= htmlspecialchars($folderName) ?></h3>

                                </div>

                                <span class="video-count"><?= count($videos) ?> videí</span>

                            </div>

                            <?php if ($isAdmin): ?>

                                <div class="category-admin-actions" onclick="event.stopPropagation()">

                                    <button class="admin-btn upload-btn" onclick="showUploadModal('<?= htmlspecialchars($folderName) ?>')" title="Přidat videa">

                                        <i class="fas fa-plus"></i>

                                    </button>

                                    <button class="admin-btn edit-btn" onclick="showEditFolderModal('<?= htmlspecialchars($folderName) ?>')" title="Přejmenovat složku">

                                        <i class="fas fa-edit"></i>

                                    </button>

                                    <button class="admin-btn delete-btn" onclick="deleteFolderConfirm('<?= htmlspecialchars($folderName) ?>')" title="Smazat složku">

                                        <i class="fas fa-trash"></i>

                                    </button>

                                </div>

                            <?php endif; ?>

                            <div class="category-toggle">

                                <i class="fas fa-chevron-down"></i>

                            </div>

                        </div>



                        <div class="videos-list" id="category-<?= $folderName ?>">

                            <div class="video-blocks-grid" data-folder="<?= htmlspecialchars($folderName) ?>">

                                <?php

                                // Load custom order if exists

                                $orderFile = $baseDir . $folderName . '/.order.json';

                                $customOrder = [];

                                if (file_exists($orderFile)) {

                                    $orderData = json_decode(file_get_contents($orderFile), true);

                                    $customOrder = $orderData['order'] ?? [];
                                }



                                // Sort videos by custom order if available

                                if (!empty($customOrder)) {

                                    usort($videos, function ($a, $b) use ($customOrder) {

                                        $posA = array_search($a['file_name'], $customOrder);

                                        $posB = array_search($b['file_name'], $customOrder);

                                        if ($posA === false) $posA = 999;

                                        if ($posB === false) $posB = 999;

                                        return $posA - $posB;
                                    });
                                }



                                foreach ($videos as $video):

                                    // Load description from metadata

                                    $metadataFile = $baseDir . $folderName . '/.metadata.json';

                                    $descriptions = [];
                                    $webUrl = str_replace($_SERVER['DOCUMENT_ROOT'], '', $video['file_path']);
                                    $webUrl = str_replace('//', '/', $webUrl); // Pojistka proti zdvojeným lomítkům

                                    if (file_exists($metadataFile)) {

                                        $metadataData = json_decode(file_get_contents($metadataFile), true);

                                        $descriptions = $metadataData['descriptions'] ?? [];
                                    }

                                    $description = $descriptions[$video['file_name']] ?? '';

                                ?>

                                    <div class="video-block"

                                        data-filename="<?= htmlspecialchars($video['file_name']) ?>"

                                        <?php if ($isAdmin): ?>draggable="true" <?php endif; ?>>

                                        <div class="video-block-header">

                                            <?php if ($isAdmin): ?>

                                                <div class="video-block-admin">

                                                    <button class="admin-btn edit-btn"

                                                        onclick="showEditVideoModal('<?= htmlspecialchars($video['folder']) ?>', '<?= htmlspecialchars($video['file_name']) ?>', '<?= htmlspecialchars($video['name']) ?>', '<?= htmlspecialchars($description) ?>')"

                                                        title="Upravit video">

                                                        <i class="fas fa-edit"></i>

                                                    </button>

                                                    <button class="admin-btn delete-btn"

                                                        onclick="deleteVideoConfirm('<?= htmlspecialchars($video['folder']) ?>', '<?= htmlspecialchars($video['file_name']) ?>')"

                                                        title="Smazat video">

                                                        <i class="fas fa-trash"></i>

                                                    </button>

                                                </div>

                                            <?php endif; ?>

                                        </div>



                                        <div class="video-block-preview">

                                            <?php if (in_array($video['file_extension'], ['mp4', 'avi', 'mov', 'mkv', 'webm'])): ?>

                                                <div class="video-thumbnail"

                                                    onclick="openVideoModal('<?= htmlspecialchars($webUrl) ?>', '<?= htmlspecialchars($video['name']) ?>', 'video')">

                                                    <i class="fas fa-play-circle video-play-icon"></i>

                                                    <div class="video-overlay">

                                                        <span class="video-type">VIDEO</span>

                                                    </div>

                                                </div>

                                            <?php else: ?>

                                                <div class="audio-thumbnail"

                                                    onclick="openVideoModal('<?= htmlspecialchars($webUrl) ?>', '<?= htmlspecialchars($video['name']) ?>', 'audio')">

                                                    <i class="fas fa-music audio-play-icon"></i>

                                                    <div class="audio-overlay">

                                                        <span class="audio-type">AUDIO</span>

                                                    </div>

                                                </div>

                                            <?php endif; ?>

                                        </div>



                                        <div class="video-block-content">

                                            <h4 class="video-block-title">

                                                <?= htmlspecialchars($video['name']) ?>

                                            </h4>



                                            <?php if (!empty($description)): ?>

                                                <div class="video-description">

                                                    <?= htmlspecialchars($description) ?>

                                                </div>

                                            <?php endif; ?>



                                            <div class="video-block-meta">

                                                <div class="video-meta-item">

                                                    <i class="fas fa-calendar"></i>

                                                    <?= date('d.m.Y', $video['date_added']) ?>

                                                </div>

                                                <div class="video-meta-item">

                                                    <i class="fas fa-file"></i>

                                                    <?= strtoupper($video['file_extension']) ?>

                                                </div>

                                                <div class="video-meta-item">

                                                    <i class="fas fa-hdd"></i>

                                                    <?= formatVideoFileSize($video['file_size']) ?>

                                                </div>

                                            </div>



                                            <div class="video-block-actions">

                                                <?php if ($isAdmin): ?>

                                                    <a href="<?= htmlspecialchars($webUrl) ?>" download class="action-btn download-btn">

                                                        <i class="fas fa-download"></i>

                                                        Stáhnout

                                                    </a>

                                                <?php endif; ?>

                                            </div>

                                        </div>

                                    </div>

                                <?php endforeach; ?>

                            </div>

                        </div>

                    </div>

                <?php endforeach; ?>

            <?php endif; ?>

        </div>



        <!-- Add Videos Section -->

        <?php if ($isAdmin): ?>

            <div class="add-video-section">

                <h2>Nahrát video</h2>

                <form action="publish-video-folder.php" method="post" enctype="multipart/form-data" class="video-form">

                    <div class="form-row">

                        <div class="form-group">

                            <label for="custom_folder_name">Vytvořit novou složku (volitelné)</label>

                            <input type="text" id="custom_folder_name" name="custom_folder_name" 

                                   placeholder="Zadejte název pro novou složku..."

                                   onchange="toggleVideoFolderInputs()"

                                   oninput="toggleVideoFolderInputs()" class="full-width">

                            <small class="file-help">Pokud zadáte název, vytvoří se nová složka s tímto názvem</small>

                        </div>

                    </div>

                    <div class="form-row" id="existing-video-folder-row">

                        <div class="form-group">

                            <label for="existing_folder_name">Nebo vyberte existující složku</label>

                            <select id="existing_folder_name" name="folder_name" class="full-width">

                                <option value="">-- Vyberte existující složku --</option>

                                <?php foreach ($videosByFolder as $folderName => $videos): ?>

                                    <option value="<?= htmlspecialchars($folderName) ?>"><?= htmlspecialchars($folderName) ?></option>

                                <?php endforeach; ?>

                            </select>

                        </div>

                    </div>

                    <div class="form-row">

                        <div class="form-group">

                            <label for="video_files">Soubory</label>

                            <div class="upload-area">

                                <label for="video_files" class="custom-file-button">Vyberte soubory</label>

                                <input type="file" name="video_files[]" id="video_files" multiple required

                                    class="hidden-file-input" accept=".mp4,.avi,.mov,.mkv,.webm,.mp3,.wav,.aac">

                                <div id="selected-files" class="selected-files-info"></div>

                            </div>

                        </div>

                    </div>

                    <div class="form-row">

                        <button type="submit" class="upload-btn">

                            <i class="fas fa-folder-plus"></i> <span id="upload-btn-text">Vytvořit složku a nahrát soubory</span>

                        </button>

                    </div>

                </form>

            </div>

        <?php endif; ?>

    </div>

</div>



<!-- Video/Audio Preview Modal -->

<div id="videoPreviewModal" class="modal" style="display: none;">

    <div class="modal-content video-modal">

        <div class="modal-header">

            <h2 id="videoModalTitle">Přehrát video</h2>

            <button class="modal-close" onclick="closeVideoModal()">&times;</button>

        </div>

        <div class="video-modal-body">

            <?php if ($isAdmin): ?>

                <video id="modalVideo" controls style="display: none;" class="modal-preview-video">

                    <source id="modalVideoSource" src="" type="">

                    Váš prohlížeč nepodporuje video tag.

                </video>

                <audio id="modalAudio" controls style="display: none;" class="modal-preview-audio">

                    <source id="modalAudioSource" src="" type="">

                    Váš prohlížeč nepodporuje audio tag.

                </audio>

            <?php else: ?>

                <video id="modalVideo" controls controlsList="nodownload" style="display: none;" class="modal-preview-video" oncontextmenu="return false;">

                    <source id="modalVideoSource" src="" type="">

                    Váš prohlížeč nepodporuje video tag.

                </video>

                <audio id="modalAudio" controls controlsList="nodownload" style="display: none;" class="modal-preview-audio" oncontextmenu="return false;">

                    <source id="modalAudioSource" src="" type="">

                    Váš prohlížeč nepodporuje audio tag.

                </audio>

            <?php endif; ?>

        </div>

    </div>

</div>



<!-- Upload to Folder Modal -->

<?php if ($isAdmin): ?>

    <div id="uploadToFolderModal" class="modal" style="display: none;">

        <div class="modal-content">

            <div class="modal-header">

                <h2>Nahrát soubory do složky</h2>

                <button class="modal-close" onclick="closeModal('uploadToFolderModal')">&times;</button>

            </div>

            <form id="uploadToFolderForm" enctype="multipart/form-data">

                <input type="hidden" id="targetFolderName" name="target_folder" value="">

                <div class="form-group">

                    <label for="additional_files">Vyberte soubory</label>

                    <div class="upload-area">

                        <label for="additional_files" class="custom-file-button">Vyberte soubory</label>

                        <input type="file" name="additional_files[]" id="additional_files" multiple required

                            class="hidden-file-input" accept=".mp4,.avi,.mov,.mkv,.webm,.mp3,.wav,.aac">

                        <div id="additional-selected-files" class="selected-files-info"></div>

                    </div>

                </div>



                <!-- Upload Progress Bar -->

                <div id="uploadProgress" class="upload-progress" style="display: none;">

                    <div class="progress-bar">

                        <div class="progress-fill" id="progressFill"></div>

                    </div>

                    <div class="progress-text" id="progressText">Nahrávání...</div>

                </div>



                <div class="modal-actions">

                    <button type="button" class="btn btn-secondary" onclick="closeModal('uploadToFolderModal')" id="cancelUploadBtn">Zrušit</button>

                    <button type="submit" class="btn btn-primary" id="uploadSubmitBtn">

                        <span class="btn-text">Nahrát soubory</span>

                        <span class="btn-loading" style="display: none;">

                            <i class="fas fa-spinner fa-spin"></i> Nahrávání...

                        </span>

                    </button>

                </div>

            </form>

        </div>

    </div>



    <!-- Edit Folder Modal -->

    <div id="editFolderModal" class="modal" style="display: none;">

        <div class="modal-content">

            <div class="modal-header">

                <h2>Upravit název složky</h2>

                <button class="modal-close" onclick="closeModal('editFolderModal')">&times;</button>

            </div>

            <form id="editFolderForm">

                <input type="hidden" id="oldFolderName" name="old_folder_name">

                <div class="form-group">

                    <label for="newFolderName">Nový název:</label>

                    <input type="text" id="newFolderName" name="new_folder_name" required class="full-width">

                </div>

                <div class="modal-actions">

                    <button type="button" class="btn btn-secondary" onclick="closeModal('editFolderModal')">Zrušit</button>

                    <button type="submit" class="btn btn-primary">Uložit</button>

                </div>

            </form>

        </div>

    </div>



    <!-- Edit Video Modal -->

    <div id="editVideoModal" class="modal" style="display: none;">

        <div class="modal-content">

            <div class="modal-header">

                <h2>Upravit video</h2>

                <button class="modal-close" onclick="closeModal('editVideoModal')">&times;</button>

            </div>

            <form id="editVideoForm">

                <input type="hidden" id="editVideoFolder" name="folder_name">

                <input type="hidden" id="editVideoOldName" name="old_file_name">

                <div class="form-group">

                    <label for="editVideoName">Název videa:</label>

                    <input type="text" id="editVideoName" name="new_video_name" required class="full-width">

                </div>

                <div class="form-group">

                    <label for="editVideoDescription">Popis:</label>

                    <textarea id="editVideoDescription" name="video_description" class="full-width" rows="3" placeholder="Volitelný popis videa"></textarea>

                </div>

                <div class="modal-actions">

                    <button type="button" class="btn btn-secondary" onclick="closeModal('editVideoModal')">Zrušit</button>

                    <button type="submit" class="btn btn-primary">Uložit změny</button>

                </div>

            </form>

        </div>

    </div>

<?php endif; ?>



<style>
    /* Videos page styling - Clean layout without gradients */

    #videos .content-area {

        display: flex;

        flex-direction: column;

        gap: 30px;

    }



    .add-video-section {

        background: white;

        border-radius: 10px;

        padding: 25px;

        margin-bottom: 25px;

        box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);

    }



    .add-video-section h2 {

        color: #2c3e50;

        margin-top: 0;

        margin-bottom: 20px;

        font-size: 20px;

    }



    .video-form {

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



    .form-group input {

        width: 100%;

        padding: 10px;

        border: 1px solid #ddd;

        border-radius: 5px;

        font-size: 14px;

        box-sizing: border-box;

    }



    .form-group input:focus {

        outline: none;

        border-color: #3498db;

        box-shadow: 0 0 0 2px rgba(52, 152, 219, 0.2);

    }



    /* Form styling for textareas */

    textarea.full-width {

        width: 100%;

        padding: 10px;

        border: 1px solid #ddd;

        border-radius: 5px;

        font-size: 14px;

        box-sizing: border-box;

        font-family: inherit;

        resize: vertical;

        min-height: 80px;

    }



    textarea.full-width:focus {

        outline: none;

        border-color: #3498db;

        box-shadow: 0 0 0 2px rgba(52, 152, 219, 0.2);

    }



    /* Modal actions styling */

    .modal-actions {

        padding: 25px 30px;

        border-top: 1px solid #eee;

        display: flex;

        justify-content: flex-end;

        gap: 15px;

        background: #f8f9fa;

    }



    .btn {

        padding: 12px 24px;

        border: none;

        border-radius: 5px;

        font-size: 14px;

        font-weight: 500;

        cursor: pointer;

        transition: all 0.2s;

    }





    /* Videos Grid */

    .videos-grid {

        display: flex;

        flex-direction: column;

        gap: 20px;

    }



    /* Video Category - Folder Style */

    .video-category {

        background: white;

        border-radius: 10px;

        box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);

        overflow: hidden;

        transition: all 0.3s ease;

        border-left: 4px solid #3498db;

    }



    .video-category:hover {

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



    .category-info {

        display: flex;

        flex-direction: column;

        gap: 5px;

    }



    .folder-icon-wrapper {

        display: flex;

        align-items: center;

        gap: 15px;

    }



    .folder-icon {

        font-size: 24px;

        color: #3498db;

        transition: all 0.3s ease;

    }



    .category-header:hover .folder-icon {

        color: #2980b9;

        transform: scale(1.1);

    }



    .category-info h3 {

        margin: 0;

        font-size: 18px;

        font-weight: 600;

        color: #2c3e50;

    }



    .video-count {

        font-size: 14px;

        color: #6c757d;

        font-weight: 500;

        margin-left: 39px;

    }



    .category-admin-actions {

        display: flex;

        gap: 8px;

        z-index: 2;

        flex-shrink: 0;

    }



    .category-toggle {

        color: #6c757d;

        transition: transform 0.3s ease;

        font-size: 16px;

        flex-shrink: 0;

    }



    .category-toggle.open {

        transform: rotate(180deg);

    }



    /* Videos List */

    .videos-list {

        padding: 0;

        max-height: 0;

        overflow: hidden;

        transition: max-height 0.3s ease-in-out;

        background: #fafbfc;

    }



    .videos-list.open {

        max-height: 2000px;

    }



    /* Video Blocks Grid */

    .video-blocks-grid {

        display: grid;

        grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));

        gap: 20px;

        padding: 25px;

    }



    /* Video Block */

    .video-block {

        background: white;

        border-radius: 8px;

        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);

        overflow: hidden;

        transition: all 0.3s ease;

        border: 1px solid #e9ecef;

    }



    .video-block:hover {

        transform: translateY(-4px);

        box-shadow: 0 8px 20px rgba(0, 0, 0, 0.12);

    }



    .video-block-header {

        padding: 10px 15px;

        background: #f8f9fa;

        display: flex;

        justify-content: flex-end;

        align-items: center;

        min-height: 20px;

    }



    .video-block-admin {

        display: flex;

        gap: 5px;

    }



    .video-block-preview {

        position: relative;

        height: 160px;

        background: #3498db;

        display: flex;

        align-items: center;

        justify-content: center;

        cursor: pointer;

        overflow: hidden;

    }



    .video-thumbnail {

        width: 100%;

        height: 100%;

        display: flex;

        align-items: center;

        justify-content: center;

        position: relative;

        transition: all 0.3s ease;

        background: #3498db;

    }

.upload-btn {
    margin-bottom: 10px!important;
    }
    .audio-thumbnail {

        width: 100%;

        height: 100%;

        display: flex;

        align-items: center;

        justify-content: center;

        position: relative;

        transition: all 0.3s ease;

        background: #2ecc71;

    }



    .video-play-icon,

    .audio-play-icon {

        font-size: 28px;

        color: white;

        filter: drop-shadow(0 2px 4px rgba(0, 0, 0, 0.3));

        transition: all 0.3s ease;

        z-index: 2;

    }



    .video-thumbnail:hover .video-play-icon,

    .audio-thumbnail:hover .audio-play-icon {

        font-size: 32px;

        transform: scale(1.1);

    }



    .video-overlay,

    .audio-overlay {

        position: absolute;

        bottom: 8px;

        right: 8px;

        background: rgba(0, 0, 0, 0.7);

        color: white;

        padding: 3px 6px;

        border-radius: 3px;

        font-size: 10px;

        font-weight: 600;

        letter-spacing: 0.5px;

    }



    .video-block-content {

        padding: 15px;

    }



    .video-block-title {

        margin: 0 0 12px 0;

        font-size: 14px;

        font-weight: 600;

        color: #2c3e50;

        line-height: 1.4;

        display: -webkit-box;

        -webkit-line-clamp: 2;

        line-clamp: 2;

        -webkit-box-orient: vertical;

        overflow: hidden;

        text-overflow: ellipsis;

    }



    .video-description {

        font-size: 12px;

        color: #6c757d;

        margin-bottom: 8px;

        line-height: 1.4;

        display: -webkit-box;

        -webkit-line-clamp: 2;

        line-clamp: 2;

        -webkit-box-orient: vertical;

        overflow: hidden;

        text-overflow: ellipsis;

    }



    .video-block-meta {

        display: flex;

        flex-direction: row;

        gap: 12px;

        margin-bottom: 12px;

        flex-wrap: wrap;

    }



    .video-meta-item {

        display: flex;

        align-items: center;

        gap: 6px;

        font-size: 11px;

        color: #6c757d;

        font-weight: 500;

    }



    .video-meta-item i {

        font-size: 10px;

        width: 10px;

        opacity: 0.7;

    }



    .video-block-actions {

        display: flex;

        gap: 8px;

    }



    .action-btn {

        flex: 1;

        padding: 8px 12px;

        border: none;

        border-radius: 4px;

        text-decoration: none;

        font-size: 11px;

        font-weight: 600;

        cursor: pointer;

        transition: all 0.3s ease;

        display: inline-flex;

        align-items: center;

        justify-content: center;

        gap: 4px;

        text-transform: uppercase;

        letter-spacing: 0.5px;

    }



    .download-btn {

        background: #2ecc71;

        color: white;

    }



    .download-btn:hover {

        background: #27ae60;

        text-decoration: none;

        color: white;

        transform: translateY(-1px);

    }



    /* Admin buttons */




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



    .no-videos {

        text-align: center;

        padding: 40px;

        color: #6c757d;

        background: #f8f9fa;

        border-radius: 10px;

        border: 1px solid #dee2e6;

    }



    .no-videos i {

        font-size: 48px;

        margin-bottom: 15px;

        color: #adb5bd;

    }



    /* Status messages */

    .status-message {

        padding: 15px;

        border-radius: 5px;

        margin-bottom: 20px;

        font-weight: 500;

    }



    .status-success {

        background: #d4edda;

        color: #155724;

        border: 1px solid #c3e6cb;

    }



    .status-error {

        background: #f8d7da;

        color: #721c24;

        border: 1px solid #f5c6cb;

    }



    /* Upload Progress Styles */

    .upload-progress {

        margin: 20px 0;

        padding: 15px;

        background: #f8f9fa;

        border-radius: 8px;

        border: 1px solid #e9ecef;

    }



    .progress-bar {

        width: 100%;

        height: 20px;

        background: #e9ecef;

        border-radius: 10px;

        overflow: hidden;

        margin-bottom: 10px;

        position: relative;

    }



    .progress-fill {

        height: 100%;

        background: #3498db;

        border-radius: 10px;

        width: 0%;

        transition: width 0.3s ease;

        position: relative;

    }



    .progress-fill::after {

        content: '';

        position: absolute;

        top: 0;

        left: 0;

        bottom: 0;

        right: 0;

        background-image: linear-gradient(-45deg,

                rgba(255, 255, 255, .2) 25%,

                transparent 25%,

                transparent 50%,

                rgba(255, 255, 255, .2) 50%,

                rgba(255, 255, 255, .2) 75%,

                transparent 75%,

                transparent);

        background-size: 50px 50px;

        animation: move 2s linear infinite;

    }



    @keyframes move {

        0% {

            background-position: 0 0;

        }



        100% {

            background-position: 50px 50px;

        }

    }



    .progress-text {

        text-align: center;

        font-size: 14px;

        color: #495057;

        font-weight: 500;

    }



    /* Button loading states */

    .btn:disabled {

        opacity: 0.6;

        cursor: not-allowed;

        pointer-events: none;

    }



    .btn-loading {

        display: flex;

        align-items: center;

        gap: 8px;

    }



    .fa-spinner {

        animation: spin 1s linear infinite;

    }



    @keyframes spin {

        0% {

            transform: rotate(0deg);

        }



        100% {

            transform: rotate(360deg);

        }

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



    /* Video Modal Styles */

    .video-modal .modal-content {

        max-width: 90vw;

        max-height: 90vh;

    }



    .video-modal_body {

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

    <?php if (!$isAdmin): ?>.modal-preview-video::-webkit-media-controls-download-button {

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
    /* Responsive adjustments */

    @media (max-width: 768px) {

        .video-blocks-grid {

            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));

            gap: 15px;

            padding: 20px;

        }



        .form-row {

            flex-direction: column;

            gap: 15px;

        }



        .form-group {

            min-width: auto;

        }



        .category-header {

            padding: 15px 20px;

            flex-wrap: wrap;

            gap: 10px;

        }



        .folder-icon {

            font-size: 20px;

        }



        .folder-icon-wrapper {

            gap: 10px;

        }



        .category-info h3 {

            font-size: 16px;

        }



        .video-count {

            margin-left: 30px;

            font-size: 13px;

        }



        .category-admin-actions {

            order: 3;

            width: 100%;

            justify-content: center;

            margin-top: 10px;

        }



        .video-block-preview {

            height: 140px;

        }



        .video-play-icon,

        .audio-play-icon {

            font-size: 24px;

        }



        .modal-content {

            width: 95%;

            margin: 5% auto;

        }

    }



    @media (max-width: 480px) {

        .video-blocks-grid {

            grid-template-columns: 1fr;

            gap: 15px;

            padding: 15px;

        }



        #videos .content-area {

            padding: 15px;

        }



        .add-video-section {

            padding: 20px;

        }



        .category-header {

            padding: 15px;

        }



        .video-block-content {

            padding: 12px;

        }

    }



    /* Padding for modal forms */

    #uploadToFolderForm,

    #editFolderForm,

    #editVideoForm {

        padding: 25px 30px;

    }



    #uploadToFolderForm .form-group,

    #editFolderForm .form-group,

    #editVideoForm .form-group {

        margin-bottom: 25px;

    }



    /* Modal header padding */

    .modal-header {

        display: flex;

        justify-content: space-between;

        align-items: center;

        padding: 25px 30px 20px;

        border-bottom: 1px solid #eee;

    }



    /* Responsive modal padding */

    @media (max-width: 768px) {

        #uploadToFolderForm,

        #editFolderForm,

        #editVideoForm {

            padding: 20px 25px;

        }



        .modal-actions {

            padding: 20px 25px;

        }



        .modal-header {

            padding: 20px 25px 15px;

        }

    }



    /* Drag and drop styles */

    .video-block.dragging {

        opacity: 0.5;

        transform: rotate(5deg);

        z-index: 1000;

    }



    .video-blocks-grid.drag-over {

        background-color: rgba(52, 152, 219, 0.1);

        border: 2px dashed #3498db;

        border-radius: 8px;

    }



    .video-block.drag-placeholder {

        background: rgba(52, 152, 219, 0.2);

        border: 2px dashed #3498db;

        opacity: 0.6;

    }



    .video-block.drag-placeholder * {

        visibility: hidden;

    }



    <?php if ($isAdmin): ?>.video-block[draggable="true"] {

        cursor: move;

    }



    .video-block[draggable="true"]:hover {

        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);

    }

    <?php endif; ?>
</style>



<script>
    document.addEventListener('DOMContentLoaded', function() {

        // Show status messages on page load

        const urlParams = new URLSearchParams(window.location.search);

        const status = urlParams.get('status');

        const message = urlParams.get('message');



        if (status && message) {

            const statusDiv = document.getElementById('status-messages');

            const messageDiv = document.createElement('div');

            messageDiv.className = `status-message status-${status}`;

            messageDiv.textContent = decodeURIComponent(message);

            statusDiv.appendChild(messageDiv);



            // Remove message from URL

            const newUrl = window.location.pathname + window.location.hash;

            window.history.replaceState({}, '', newUrl);



            // Auto-hide after 5 seconds

            setTimeout(() => {

                messageDiv.remove();

            }, 5000);

        }



        // File input handlers

        const fileInput = document.getElementById('video_files');

        const selectedFiles = document.getElementById('selected-files');



        if (fileInput && selectedFiles) {

            fileInput.addEventListener('change', function() {

                if (this.files.length > 0) {

                    if (this.files.length === 1) {

                        selectedFiles.textContent = `Vybraný soubor: ${this.files[0].name}`;

                    } else {

                        selectedFiles.textContent = `Vybrané soubory (${this.files.length}): `;

                        const fileNames = Array.from(this.files).map(file => file.name);

                        selectedFiles.textContent += fileNames.join(', ');

                    }

                } else {

                    selectedFiles.textContent = 'Žádné soubory nebyly vybrány';

                }

            });

        }



        // Additional files selection

        const additionalFilesInput = document.getElementById('additional_files');

        const additionalSelectedFiles = document.getElementById('additional-selected-files');



        if (additionalFilesInput && additionalSelectedFiles) {

            additionalFilesInput.addEventListener('change', function() {

                if (this.files.length > 0) {

                    if (this.files.length === 1) {

                        additionalSelectedFiles.textContent = `Vybraný soubor: ${this.files[0].name}`;

                    } else {

                        additionalSelectedFiles.textContent = `Vybrané soubory (${this.files.length}): `;

                        const fileNames = Array.from(this.files).map(file => file.name);

                        additionalSelectedFiles.textContent += fileNames.join(', ');

                    }

                } else {

                    additionalSelectedFiles.textContent = 'Žádné soubory nebyly vybrány';

                }

            });

        }



        // Upload to folder form submission with chunked upload

        const uploadToFolderForm = document.getElementById('uploadToFolderForm');

        if (uploadToFolderForm) {

            uploadToFolderForm.addEventListener('submit', function(e) {

                e.preventDefault();



                const files = document.getElementById('additional_files').files;

                const targetFolder = document.getElementById('targetFolderName').value;



                if (files.length === 0) {

                    alert('Vyberte soubory k nahrání');

                    return;

                }



                // Show loading state

                showUploadProgress();



                // Process files one by one with chunked upload

                uploadFilesSequentially(files, targetFolder, 0);

            });

        }



        // Edit folder form submission

        const editForm = document.getElementById('editFolderForm');

        if (editForm) {

            editForm.addEventListener('submit', function(e) {

                e.preventDefault();



                const oldName = document.getElementById('oldFolderName').value;

                const newName = document.getElementById('newFolderName').value;



                if (oldName && newName && newName.trim() !== '') {

                    fetch('manage-videos.php', {

                            method: 'POST',

                            headers: {

                                'Content-Type': 'application/x-www-form-urlencoded'

                            },

                            body: `action=rename_folder&old_name=${encodeURIComponent(oldName)}&new_name=${encodeURIComponent(newName.trim())}`

                        })

                        .then(response => response.json())

                        .then(data => {

                            closeModal('editFolderModal');

                            if (data.success) {

                                location.reload();

                            } else {

                                alert('Chyba: ' + (data.message || 'Neznámá chyba'));

                            }

                        })

                        .catch(error => {

                            closeModal('editFolderModal');

                            console.error('Error:', error);

                            alert('Došlo k chybě: ' + error.message);

                        });

                } else {

                    alert('Zadejte platný název složky');

                    closeModal('editFolderModal');

                }

            });

        }



        // Edit video form submission

        const editVideoForm = document.getElementById('editVideoForm');

        if (editVideoForm) {

            editVideoForm.addEventListener('submit', function(e) {

                e.preventDefault();



                const folderName = document.getElementById('editVideoFolder').value;

                const oldFileName = document.getElementById('editVideoOldName').value;

                const newVideoName = document.getElementById('editVideoName').value;

                const videoDescription = document.getElementById('editVideoDescription').value;



                if (folderName && oldFileName && newVideoName.trim() !== '') {

                    fetch('manage-videos.php', {

                            method: 'POST',

                            headers: {

                                'Content-Type': 'application/x-www-form-urlencoded'

                            },

                            body: `action=edit_video&folder_name=${encodeURIComponent(folderName)}&old_file_name=${encodeURIComponent(oldFileName)}&new_video_name=${encodeURIComponent(newVideoName.trim())}&video_description=${encodeURIComponent(videoDescription.trim())}`

                        })

                        .then(response => response.json())

                        .then(data => {

                            closeModal('editVideoModal');

                            if (data.success) {

                                location.reload();

                            } else {

                                alert('Chyba: ' + (data.message || 'Neznámá chyba'));

                            }

                        })

                        .catch(error => {

                            closeModal('editVideoModal');

                            console.error('Error:', error);

                            alert('Došlo k chybě: ' + error.message);

                        });

                } else {

                    alert('Zadejte platný název videa');

                }

            });

        }



        // Initialize all categories as closed

        document.querySelectorAll('.videos-list').forEach(list => {

            list.style.maxHeight = '0px';

        });



        // Initialize drag and drop for video reordering (admin only)

        <?php if ($isAdmin): ?>

            initializeDragAndDrop();

        <?php endif; ?>

    });



    // Category toggle functionality

    function toggleCategory(categoryKey) {

        const categoryList = document.getElementById('category-' + categoryKey);

        const toggleIcon = document.querySelector(`[onclick="toggleCategory('${categoryKey}')"] .category-toggle`);



        if (categoryList && toggleIcon) {

            if (categoryList.classList.contains('open')) {

                categoryList.classList.remove('open');

                toggleIcon.classList.remove('open');

                categoryList.style.maxHeight = '0px';

            } else {

                categoryList.classList.add('open');

                toggleIcon.classList.add('open');

                categoryList.style.maxHeight = categoryList.scrollHeight + 'px';

            }

        }

    }



    // Video preview functionality

    function openVideoModal(videoSrc, videoName, mediaType) {

        const modal = document.getElementById('videoPreviewModal');

        const modalVideo = document.getElementById('modalVideo');

        const modalAudio = document.getElementById('modalAudio');

        const modalTitle = document.getElementById('videoModalTitle');

        const videoSource = document.getElementById('modalVideoSource');

        const audioSource = document.getElementById('modalAudioSource');



        modalTitle.textContent = videoName || 'Přehrát média';



        console.log('Opening video modal for:', videoSrc, 'Type:', mediaType);



        if (mediaType === 'video') {

            modalVideo.style.display = 'block';

            modalAudio.style.display = 'none';



            // Use direct file access - simpler and more reliable

            videoSource.src = videoSrc;

            const extension = videoSrc.split('.').pop().toLowerCase();

            videoSource.type = `video/${extension}`;

            modalVideo.load();



            <?php if (!$isAdmin): ?>

                // Additional protection for non-admins

                modalVideo.addEventListener('contextmenu', function(e) {

                    e.preventDefault();

                    return false;

                });



                // Disable keyboard shortcuts that might allow downloading

                modalVideo.addEventListener('keydown', function(e) {

                    // Disable common download shortcuts

                    if ((e.ctrlKey || e.metaKey) && (e.key === 's' || e.key === 'S')) {

                        e.preventDefault();

                        return false;

                    }

                });

            <?php endif; ?>

        } else {

            modalVideo.style.display = 'none';

            modalAudio.style.display = 'block';



            // Use direct file access - simpler and more reliable

            audioSource.src = videoSrc;

            const extension = videoSrc.split('.').pop().toLowerCase();

            audioSource.type = `audio/${extension}`;

            modalAudio.load();



            <?php if (!$isAdmin): ?>

                // Additional protection for non-admins

                modalAudio.addEventListener('contextmenu', function(e) {

                    e.preventDefault();

                    return false;

                });



                // Disable keyboard shortcuts that might allow downloading

                modalAudio.addEventListener('keydown', function(e) {

                    // Disable common download shortcuts

                    if ((e.ctrlKey || e.metaKey) && (e.key === 's' || e.key === 'S')) {

                        e.preventDefault();

                        return false;

                    }

                });

            <?php endif; ?>

        }



        modal.style.display = 'flex';

    }



    <?php if (!$isAdmin): ?>

        // Additional protection: disable right-click on the entire modal for non-admins

        document.addEventListener('DOMContentLoaded', function() {

            const videoModal = document.getElementById('videoPreviewModal');

            if (videoModal) {

                videoModal.addEventListener('contextmenu', function(e) {

                    e.preventDefault();

                    return false;

                });



                // Disable drag and drop

                videoModal.addEventListener('dragstart', function(e) {

                    e.preventDefault();

                    return false;

                });

            }

        });

    <?php endif; ?>



    // Modal functions

    function showModal(modalId) {

        const modal = document.getElementById(modalId);

        if (modal) {

            modal.style.display = 'flex';

        }

    }



    function closeModal(modalId) {

        const modal = document.getElementById(modalId);

        if (modal) {

            // Check if upload is in progress

            const submitBtn = document.getElementById('uploadSubmitBtn');

            if (submitBtn && submitBtn.disabled && modalId === 'uploadToFolderModal') {

                if (!confirm('Nahrávání probíhá. Opravdu chcete zrušit?')) {

                    return;

                }

                // Reset upload state if user confirms cancellation

                hideUploadProgress();

            }



            modal.style.display = 'none';

        }

    }



    function closeVideoModal() {

        const modal = document.getElementById('videoPreviewModal');

        const modalVideo = document.getElementById('modalVideo');

        const modalAudio = document.getElementById('modalAudio');



        // Pause and reset video/audio

        modalVideo.pause();

        modalAudio.pause();

        modalVideo.currentTime = 0;

        modalAudio.currentTime = 0;



        // Clear sources to fully stop playback

        const videoSource = document.getElementById('modalVideoSource');

        const audioSource = document.getElementById('modalAudioSource');

        videoSource.src = '';

        audioSource.src = '';



        // Hide modal

        modal.style.display = 'none';

    }



    function showUploadModal(folderName) {

        // Reset any previous upload state

        hideUploadProgress();



        const targetFolderInput = document.getElementById('targetFolderName');

        if (targetFolderInput) {

            targetFolderInput.value = folderName;

            showModal('uploadToFolderModal');

        }

    }



    function showEditFolderModal(folderName) {

        const oldNameInput = document.getElementById('oldFolderName');

        const newNameInput = document.getElementById('newFolderName');



        if (oldNameInput && newNameInput) {

            oldNameInput.value = folderName;

            newNameInput.value = folderName;

            showModal('editFolderModal');

        }

    }



    function showEditVideoModal(folderName, fileName, videoName, description) {

        const folderInput = document.getElementById('editVideoFolder');

        const oldNameInput = document.getElementById('editVideoOldName');

        const newNameInput = document.getElementById('editVideoName');

        const descriptionInput = document.getElementById('editVideoDescription');



        if (folderInput && oldNameInput && newNameInput && descriptionInput) {

            folderInput.value = folderName;

            oldNameInput.value = fileName;

            newNameInput.value = videoName;

            descriptionInput.value = description;

            showModal('editVideoModal');

        }

    }



    function deleteFolderConfirm(folderName) {

        if (confirm(`Opravdu chcete smazat složku "${folderName}" a všechny její soubory?`)) {

            fetch('manage-videos.php', {

                    method: 'POST',

                    headers: {

                        'Content-Type': 'application/x-www-form-urlencoded'

                    },

                    body: `action=delete_folder&folder_name=${encodeURIComponent(folderName)}`

                })

                .then(response => response.json())

                .then(data => {

                    if (data.success) {

                        location.reload();

                    } else {

                        alert('Chyba: ' + (data.message || 'Neznámá chyba'));

                    }

                })

                .catch(error => {

                    console.error('Error:', error);

                    alert('Došlo k chybě: ' + error.message);

                });

        }

    }



    function deleteVideoConfirm(folderName, fileName) {

        if (confirm(`Opravdu chcete smazat soubor "${fileName}"?`)) {

            fetch('manage-videos.php', {

                    method: 'POST',

                    headers: {

                        'Content-Type': 'application/x-www-form-urlencoded'

                    },

                    body: `action=delete_video&folder_name=${encodeURIComponent(folderName)}&file_name=${encodeURIComponent(fileName)}`

                })

                .then(response => response.json())

                .then(data => {

                    if (data.success) {

                        location.reload();

                    } else {

                        alert('Chyba: ' + (data.message || 'Neznámá chyba'));

                    }

                })

                .catch(error => {

                    console.error('Error:', error);

                    alert('Došlo k chybě: ' + error.message);

                });

        }

    }



    // Close modal with Escape key

    document.addEventListener('keydown', function(event) {

        if (event.key === 'Escape') {

            closeVideoModal();

            closeModal('uploadToFolderModal');

            closeModal('editFolderModal');

            closeModal('editVideoModal');

        }

    });



    // Close modals on outside click

    document.addEventListener('click', function(event) {

        const videoModal = document.getElementById('videoPreviewModal');

        const uploadModal = document.getElementById('uploadToFolderModal');

        const editModal = document.getElementById('editFolderModal');

        const editVideoModal = document.getElementById('editVideoModal');



        if (event.target === videoModal) {

            closeVideoModal();

        }

        if (event.target === uploadModal) {

            closeModal('uploadToFolderModal');

        }

        if (event.target === editModal) {

            closeModal('editFolderModal');

        }

        if (event.target === editVideoModal) {

            closeModal('editVideoModal');

        }

    });



    // Upload progress functions

    function showUploadProgress() {

        const progressContainer = document.getElementById('uploadProgress');

        const submitBtn = document.getElementById('uploadSubmitBtn');

        const cancelBtn = document.getElementById('cancelUploadBtn');

        const btnText = submitBtn.querySelector('.btn-text');

        const btnLoading = submitBtn.querySelector('.btn-loading');



        // Show progress bar

        progressContainer.style.display = 'block';



        // Update button state

        submitBtn.disabled = true;

        btnText.style.display = 'none';

        btnLoading.style.display = 'flex';



        // Disable cancel button during upload

        cancelBtn.disabled = true;

        cancelBtn.textContent = 'Nahrávání...';



        // Reset progress

        updateUploadProgress(0);

    }



    function hideUploadProgress() {

        const progressContainer = document.getElementById('uploadProgress');

        const submitBtn = document.getElementById('uploadSubmitBtn');

        const cancelBtn = document.getElementById('cancelUploadBtn');

        const btnText = submitBtn.querySelector('.btn-text');

        const btnLoading = submitBtn.querySelector('.btn-loading');



        // Hide progress bar

        progressContainer.style.display = 'none';



        // Reset button state

        submitBtn.disabled = false;

        btnText.style.display = 'inline';

        btnLoading.style.display = 'none';



        // Reset cancel button

        cancelBtn.disabled = false;

        cancelBtn.textContent = 'Zrušit';



        // Reset form

        document.getElementById('uploadToFolderForm').reset();

        document.getElementById('additional-selected-files').textContent = '';

    }



    function updateUploadProgress(percent, text) {

        const progressFill = document.getElementById('progressFill');

        const progressText = document.getElementById('progressText');



        if (progressFill && progressText) {

            progressFill.style.width = Math.min(100, Math.max(0, percent)) + '%';

            progressText.textContent = text || `Nahrávání... ${Math.round(percent)}%`;

        }

    }



    // Chunked upload functions

    function uploadFilesSequentially(files, targetFolder, index) {

        if (index >= files.length) {

            // All files uploaded

            hideUploadProgress();

            closeModal('uploadToFolderModal');

            alert('Všechny soubory byly úspěšně nahrány');

            location.reload();

            return;

        }



        const file = files[index];

        const fileName = file.name;



        // Check if file is larger than 250MB

        const maxRegularSize = 250 * 1024 * 1024; // 250MB



        if (file.size > maxRegularSize) {

            // Use chunked upload

            uploadFileInChunks(file, targetFolder, fileName, () => {

                // Success callback - upload next file

                uploadFilesSequentially(files, targetFolder, index + 1);

            }, (error) => {

                // Error callback

                hideUploadProgress();

                closeModal('uploadToFolderModal');

                alert('Chyba při nahrávání souboru ' + fileName + ': ' + error);

            });

        } else {

            // Use regular upload for smaller files

            uploadFileRegular(file, targetFolder, () => {

                // Success callback - upload next file

                uploadFilesSequentially(files, targetFolder, index + 1);

            }, (error) => {

                // Error callback

                hideUploadProgress();

                closeModal('uploadToFolderModal');

                alert('Chyba při nahrávání souboru ' + fileName + ': ' + error);

            });

        }

    }



    function uploadFileInChunks(file, targetFolder, fileName, successCallback, errorCallback) {

        const chunkSize = 50 * 1024 * 1024; // 50MB chunks (smaller to avoid POST limits)

        const totalChunks = Math.ceil(file.size / chunkSize);

        const fileId = generateUniqueId();

        let currentChunk = 0;



        function uploadNextChunk() {

            if (currentChunk >= totalChunks) {

                successCallback();

                return;

            }



            const start = currentChunk * chunkSize;

            const end = Math.min(start + chunkSize, file.size);

            const chunk = file.slice(start, end);



            const formData = new FormData();

            formData.append('chunk', chunk);

            formData.append('chunkIndex', currentChunk);

            formData.append('totalChunks', totalChunks);

            formData.append('fileName', fileName);

            formData.append('targetFolder', targetFolder);

            formData.append('fileId', fileId);



            const xhr = new XMLHttpRequest();



            xhr.upload.addEventListener('progress', function(e) {

                if (e.lengthComputable) {

                    const chunkProgress = (e.loaded / e.total) * 100;

                    const overallProgress = ((currentChunk + chunkProgress / 100) / totalChunks) * 100;

                    updateUploadProgress(overallProgress, `Nahrávání ${fileName} (část ${currentChunk + 1}/${totalChunks})`);

                }

            });



            xhr.addEventListener('load', function() {

                if (xhr.status === 200) {

                    try {

                        const response = JSON.parse(xhr.responseText);

                        if (response.success) {

                            currentChunk++;

                            if (response.isComplete) {

                                updateUploadProgress(100, `Soubor ${fileName} byl úspěšně nahrán`);

                                setTimeout(successCallback, 500); // Small delay to show completion

                            } else {

                                uploadNextChunk();

                            }

                        } else {

                            errorCallback(response.message || 'Neznámá chyba');

                        }

                    } catch (error) {

                        errorCallback('Chyba při zpracování odpovědi serveru');

                    }

                } else {

                    errorCallback('Chyba serveru: ' + xhr.status);

                }

            });



            xhr.addEventListener('error', function() {

                errorCallback('Chyba při nahrávání části souboru');

            });



            xhr.open('POST', 'chunked-upload.php');

            xhr.send(formData);

        }



        uploadNextChunk();

    }



    function uploadFileRegular(file, targetFolder, successCallback, errorCallback) {

        const formData = new FormData();

        formData.append('additional_files[]', file);

        formData.append('target_folder', targetFolder);



        const xhr = new XMLHttpRequest();



        xhr.upload.addEventListener('progress', function(e) {

            if (e.lengthComputable) {

                const percentComplete = (e.loaded / e.total) * 100;

                updateUploadProgress(percentComplete, `Nahrávání ${file.name}`);

            }

        });



        xhr.addEventListener('load', function() {

            if (xhr.status === 200) {

                try {

                    const responseText = xhr.responseText.trim();

                    if (responseText.startsWith('<') || responseText.includes('<br />')) {

                        throw new Error('Server vrátil HTML chybu místo JSON odpovědi');

                    }



                    const data = JSON.parse(responseText);

                    if (data.success) {

                        successCallback();

                    } else {

                        errorCallback(data.message || 'Neznámá chyba');

                    }

                } catch (error) {

                    errorCallback('Chyba při zpracování odpovědi serveru');

                }

            } else if (xhr.status === 413) {

                errorCallback('Soubor je příliš velký pro server');

            } else {

                errorCallback('Chyba serveru: ' + xhr.status);

            }

        });



        xhr.addEventListener('error', function() {

            errorCallback('Chyba při nahrávání');

        });



        xhr.open('POST', 'upload-to-folder.php');

        xhr.send(formData);

    }



    function generateUniqueId() {

        return Date.now().toString(36) + Math.random().toString(36).substr(2);

    }



    <?php if ($isAdmin): ?>

        // Drag and drop functionality for reordering videos

        function initializeDragAndDrop() {

            let draggedElement = null;

            let draggedFromFolder = null;



            document.querySelectorAll('.video-block[draggable="true"]').forEach(block => {

                block.addEventListener('dragstart', function(e) {

                    draggedElement = this;

                    draggedFromFolder = this.closest('.video-blocks-grid').dataset.folder;

                    this.classList.add('dragging');



                    // Set drag data

                    e.dataTransfer.effectAllowed = 'move';

                    e.dataTransfer.setData('text/html', this.outerHTML);

                });



                block.addEventListener('dragend', function(e) {

                    this.classList.remove('dragging');



                    // Remove any drag placeholders

                    document.querySelectorAll('.drag-placeholder').forEach(placeholder => {

                        placeholder.remove();

                    });



                    // Remove drag-over class from grids

                    document.querySelectorAll('.video-blocks-grid').forEach(grid => {

                        grid.classList.remove('drag-over');

                    });



                    draggedElement = null;

                    draggedFromFolder = null;

                });

            });



            document.querySelectorAll('.video-blocks-grid').forEach(grid => {

                grid.addEventListener('dragover', function(e) {

                    e.preventDefault();

                    e.dataTransfer.dropEffect = 'move';



                    if (draggedElement) {

                        this.classList.add('drag-over');

                    }

                });



                grid.addEventListener('dragleave', function(e) {

                    // Only remove drag-over if we're leaving the grid entirely

                    if (!this.contains(e.relatedTarget)) {

                        this.classList.remove('drag-over');

                    }

                });



                grid.addEventListener('drop', function(e) {

                    e.preventDefault();

                    this.classList.remove('drag-over');



                    if (!draggedElement) return;



                    const targetFolder = this.dataset.folder;



                    // Only allow reordering within the same folder

                    if (draggedFromFolder !== targetFolder) {

                        alert('Videa lze přesouvat pouze v rámci stejné složky');

                        return;

                    }



                    // Find the element we're dropping onto

                    const afterElement = getDragAfterElement(this, e.clientY);



                    if (afterElement == null) {

                        this.appendChild(draggedElement);

                    } else {

                        this.insertBefore(draggedElement, afterElement);

                    }



                    // Save the new order

                    saveVideoOrder(targetFolder);

                });

            });

        }



        function getDragAfterElement(container, y) {

            const draggableElements = [...container.querySelectorAll('.video-block:not(.dragging)')];



            return draggableElements.reduce((closest, child) => {

                const box = child.getBoundingClientRect();

                const offset = y - box.top - box.height / 2;



                if (offset < 0 && offset > closest.offset) {

                    return {
                        offset: offset,
                        element: child
                    };

                } else {

                    return closest;

                }

            }, {
                offset: Number.NEGATIVE_INFINITY
            }).element;

        }



        function saveVideoOrder(folderName) {

            const grid = document.querySelector(`[data-folder="${folderName}"]`);

            const videoBlocks = grid.querySelectorAll('.video-block');

            const order = Array.from(videoBlocks).map(block => block.dataset.filename);



            fetch('save-video-order.php', {

                    method: 'POST',

                    headers: {

                        'Content-Type': 'application/json',

                    },

                    body: JSON.stringify({

                        folder: folderName,

                        order: order

                    })

                })

                .then(response => response.json())

                .then(data => {

                    if (data.success) {

                        console.log('Order saved successfully');

                        // Optionally show a success message

                        showTemporaryMessage('Pořadí bylo uloženo', 'success');

                    } else {

                        console.error('Failed to save order:', data.message);

                        alert('Nepodařilo se uložit pořadí: ' + data.message);

                    }

                })

                .catch(error => {

                    console.error('Error saving order:', error);

                    alert('Chyba při ukládání pořadí');

                });

        }



        function showTemporaryMessage(message, type) {

            const statusDiv = document.getElementById('status-messages');

            const messageDiv = document.createElement('div');

            messageDiv.className = `status-message status-${type}`;

            messageDiv.textContent = message;

            statusDiv.appendChild(messageDiv);



            // Auto-hide after 3 seconds

            setTimeout(() => {

                messageDiv.remove();

            }, 3000);

        }

    <?php endif; ?>
</script>