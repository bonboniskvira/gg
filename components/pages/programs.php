<!--Page Programs -->

<?php

require_once __DIR__ . '/../../access.php';

?>

<div class="page-content" id="programs">
    <div class="content-area">
        <!-- Status Messages -->
        <div id="status-messages"></div>

        <div class="programs-container">
            <div class="programs-header">
                <h2>Placené programy a nástroje</h2>
                <div class="programs-filter">
                    <label for="sort-programs">Filtrovat podle:</label>
                    <select id="sort-programs" onchange="filterPrograms()">
                        <option value="all">Všechny kategorie</option>
                        <?php foreach ($allCategories as $categoryKey => $categoryInfo): ?>
                            <option value="<?= htmlspecialchars($categoryKey) ?>"><?= htmlspecialchars($categoryInfo['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <?php if (isAdmin()): ?>
                        <button class="add-category-btn" onclick="showAddCategoryModal()">
                            <i class="fas fa-plus"></i> Přidat kategorii
                        </button>
                    <?php endif; ?>
                </div>
            </div>

            <div class="programs-grid">
                <?php
                $programsDir = __DIR__ . '/../../programs/';

                // Create programs directory if it doesn't exist
                if (!is_dir($programsDir)) {
                    mkdir($programsDir, 0777, true);
                }

                // Default categories
                $defaultCategories = ['design', 'development', 'office', 'media', 'security', 'productivity'];
                $defaultCategoryNames = [
                    'design' => 'Design a grafika',
                    'development' => 'Vývoj a programování',
                    'office' => 'Kancelářské aplikace',
                    'media' => 'Multimédia',
                    'security' => 'Bezpečnost',
                    'productivity' => 'Produktivita'
                ];

                $defaultCategoryColors = [
                    'design' => '#e74c3c',
                    'development' => '#3498db',
                    'office' => '#2ecc71',
                    'media' => '#9b59b6',
                    'security' => '#e67e22',
                    'productivity' => '#f39c12'
                ];

                // Load all categories (default + custom)
                $allCategories = [];
                
                // Add default categories
                foreach ($defaultCategories as $category) {
                    $categoryDir = $programsDir . $category . '/';
                    $infoFile = $categoryDir . '.category-info.json';
                    
                    if (file_exists($infoFile)) {
                        $categoryInfo = json_decode(file_get_contents($infoFile), true);
                        if ($categoryInfo) {
                            $allCategories[$category] = [
                                'name' => $categoryInfo['name'] ?? $defaultCategoryNames[$category],
                                'color' => $categoryInfo['color'] ?? $defaultCategoryColors[$category],
                                'custom' => $categoryInfo['custom'] ?? true // If info file exists, treat as editable
                            ];
                        }
                    } else {
                        $allCategories[$category] = [
                            'name' => $defaultCategoryNames[$category],
                            'color' => $defaultCategoryColors[$category],
                            'custom' => false
                        ];
                    }
                }

                // Scan for custom categories
                if (is_dir($programsDir)) {
                    $items = array_diff(scandir($programsDir), ['.', '..']);
                    foreach ($items as $item) {
                        $itemPath = $programsDir . $item;
                        if (is_dir($itemPath) && !in_array($item, $defaultCategories)) {
                            // Check for category info file
                            $infoFile = $itemPath . '/.category-info.json';
                            if (file_exists($infoFile)) {
                                $categoryInfo = json_decode(file_get_contents($infoFile), true);
                                if ($categoryInfo) {
                                    $allCategories[$item] = [
                                        'name' => $categoryInfo['name'] ?? $item,
                                        'color' => $categoryInfo['color'] ?? '#3498db',
                                        'custom' => true
                                    ];
                                }
                            } else {
                                // Custom category without info file
                                $allCategories[$item] = [
                                    'name' => ucfirst($item),
                                    'color' => '#3498db',
                                    'custom' => true
                                ];
                            }
                        }
                    }
                }

                $hasPrograms = false;

                foreach ($allCategories as $categoryKey => $categoryInfo) {
                    $categoryDir = $programsDir . $categoryKey . '/';

                    if (!is_dir($categoryDir)) {
                        mkdir($categoryDir, 0777, true);
                    }

                    $programFiles = glob($categoryDir . '*.json');

                    // Show category even if it has no programs yet (for newly created categories)
                    echo '<div class="programs-category" id="programs-category-' . $categoryKey . '" data-category-key="' . $categoryKey . '" data-category-name="' . htmlspecialchars($categoryInfo['name']) . '" data-category-color="' . htmlspecialchars($categoryInfo['color']) . '">';
                    echo '<div class="programs-category-header">';
                    echo '<div class="programs-category-icon"><i class="fas fa-folder" style="color: ' . $categoryInfo['color'] . ';"></i></div>';
                    echo '<div class="programs-category-info">';
                    echo '<h3 style="color: ' . $categoryInfo['color'] . ';">' . htmlspecialchars($categoryInfo['name']) . '</h3>';
                    echo '<span class="programs-count">' . count($programFiles) . ' ' . (count($programFiles) == 1 ? 'program' : 'programů') . '</span>';
                    echo '</div>';

                    // Add admin actions
                    if (isAdmin()) {
                        echo '<div class="category-admin-actions" onclick="event.stopPropagation()">';
                        echo '<button class="admin-btn edit-btn" onclick="editProgramCategory(\'' . $categoryKey . '\')" title="Upravit kategorii">';
                        echo '<i class="fas fa-edit"></i>';
                        echo '</button>';
                        // Only allow deletion of custom categories
                        echo '<button class="admin-btn delete-btn" onclick="deleteProgramCategory(\'' . $categoryKey . '\')" title="Smazat kategorii">';
                        echo '<i class="fas fa-trash"></i>';
                        echo '</button>';
                        echo '</div>';
                    }

                    echo '<div class="programs-category-toggle"><i class="fas fa-chevron-down"></i></div>';
                    echo '</div>';

                    echo '<div class="programs-category-content" style="display: none;">';
                    
                    if (!empty($programFiles)) {
                        $hasPrograms = true;
                        foreach ($programFiles as $programFile) {
                            $programData = json_decode(file_get_contents($programFile), true);

                            if ($programData) {
                                echo '<div class="programs-item">';
                                echo '<div class="programs-item-content">';
                                
                                // Show program image if available, otherwise show icon
                                if (!empty($programData['image'])) {
                                    echo '<div class="programs-image"><img src="' . htmlspecialchars($programData['image']) . '" alt="' . htmlspecialchars($programData['title']) . '"></div>';
                                } else {
                                    echo '<div class="programs-icon">' . getIconForProgram($programData['category'], $programData['title']) . '</div>';
                                }
                                
                                echo '<div class="programs-info">';
                                echo '<div class="programs-name">';
                                echo '<a href="' . htmlspecialchars($programData['url']) . '" target="_blank">' . htmlspecialchars($programData['title']) . '</a>';
                                if (!empty($programData['price'])) {
                                    echo ' <span class="programs-price">(' . htmlspecialchars($programData['price']) . ')</span>';
                                }
                                echo '</div>';

                                if (!empty($programData['description'])) {
                                    echo '<div class="programs-description">' . htmlspecialchars($programData['description']) . '</div>';
                                }

                                echo '<div class="programs-meta">';
                                echo 'Přidáno: ' . date("d.m.Y", $programData['date_added']);
                                if (!empty($programData['license_type'])) {
                                    echo ' • Licence: ' . htmlspecialchars($programData['license_type']);
                                }
                                echo '</div>';
                                echo '</div>';
                                echo '</div>';

                                // Add program actions
                                if (isAdmin()) {
                                    echo '<div class="doc-actions">';
                                    echo '<button class="mini-btn edit-btn" onclick="editProgram(\'' . basename($programFile) . '\', \'' . $categoryKey . '\')" title="Upravit program">';
                                    echo '<i class="fas fa-edit"></i>';
                                    echo '</button>';
                                    echo '<button class="mini-btn delete-btn" onclick="deleteProgram(\'' . basename($programFile) . '\', \'' . $categoryKey . '\')" title="Smazat program">';
                                    echo '<i class="fas fa-trash"></i>';
                                    echo '</button>';
                                    echo '</div>';
                                }

                                echo '</div>';
                            }
                        }
                    } else {
                        // Show message for empty categories
                        echo '<div class="empty-category-message">';
                        echo '<i class="fas fa-inbox" style="color: #bdc3c7; font-size: 24px; margin-bottom: 10px;"></i>';
                        echo '<p style="color: #7f8c8d; margin: 0;">Tato kategorie zatím neobsahuje žádné programy.</p>';
                        echo '</div>';
                    }
                    
                    echo '</div>';
                    echo '</div>';
                }

                if (!$hasPrograms) {
                    echo '<div class="no-programs">
                            <i class="fas fa-desktop"></i>
                            <p>Zatím nebyly přidány žádné placené programy.</p>
                          </div>';
                }

                // Helper function to get appropriate icon based on program category and title
                function getIconForProgram($category, $title)
                {
                    $title = strtolower($title);
                    
                    // Specific program icons
                    if (strpos($title, 'adobe') !== false) {
                        if (strpos($title, 'photoshop') !== false) return '<i class="fas fa-palette" style="color: #001e36;"></i>';
                        if (strpos($title, 'illustrator') !== false) return '<i class="fas fa-vector-square" style="color: #ff9a00;"></i>';
                        if (strpos($title, 'premiere') !== false) return '<i class="fas fa-film" style="color: #9999ff;"></i>';
                        return '<i class="fab fa-adobe" style="color: #ff0000;"></i>';
                    }
                    
                    if (strpos($title, 'microsoft') !== false || strpos($title, 'office') !== false) {
                        return '<i class="fab fa-microsoft" style="color: #0078d4;"></i>';
                    }
                    
                    if (strpos($title, 'sketch') !== false) return '<i class="fas fa-pen-nib" style="color: #fdad00;"></i>';
                    if (strpos($title, 'figma') !== false) return '<i class="fab fa-figma" style="color: #f24e1e;"></i>';
                    if (strpos($title, 'canva') !== false) return '<i class="fas fa-paint-brush" style="color: #00c4cc;"></i>';
                    
                    // Category-based icons
                    switch ($category) {
                        case 'design':
                            return '<i class="fas fa-palette" style="color: #e74c3c;"></i>';
                        case 'development':
                            return '<i class="fas fa-code" style="color: #3498db;"></i>';
                        case 'office':
                            return '<i class="fas fa-file-alt" style="color: #2ecc71;"></i>';
                        case 'media':
                            return '<i class="fas fa-play-circle" style="color: #9b59b6;"></i>';
                        case 'security':
                            return '<i class="fas fa-shield-alt" style="color: #e67e22;"></i>';
                        case 'productivity':
                            return '<i class="fas fa-chart-line" style="color: #f39c12;"></i>';
                        default:
                            return '<i class="fas fa-desktop" style="color: #34495e;"></i>';
                    }
                }
                ?>
            </div>
        </div>

        <?php if (isAdmin()): ?>
            <div class="programs-upload-container">
                <h2>Přidat nový program</h2>
                <form action="publish-program.php" method="post" class="programs-upload-form" enctype="multipart/form-data">
                    <div class="form-row">
                        <div class="input-group">
                            <label for="program_title">Název programu *</label>
                            <input type="text" name="program_title" id="program_title" placeholder="Zadejte název programu" required>
                        </div>
                        <div class="input-group">
                            <label for="program_url">URL adresa *</label>
                            <input type="url" name="program_url" id="program_url" placeholder="https://..." required>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="input-group">
                            <label for="program_category">Kategorie *</label>
                            <select name="program_category" id="program_category" required>
                                <option value="">Vyberte kategorii</option>
                                <?php foreach ($allCategories as $categoryKey => $categoryInfo): ?>
                                    <option value="<?= htmlspecialchars($categoryKey) ?>"><?= htmlspecialchars($categoryInfo['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="input-group">
                            <label for="program_price">Cena</label>
                            <input type="text" name="program_price" id="program_price" placeholder="např. 29 USD/měsíc, 199 USD jednorázově">
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="input-group">
                            <label for="program_license">Typ licence</label>
                            <select name="program_license" id="program_license">
                                <option value="">Vyberte typ licence</option>
                                <option value="subscription">Předplatné</option>
                                <option value="one-time">Jednorázová</option>
                                <option value="freemium">Freemium</option>
                                <option value="trial">Zkušební verze</option>
                            </select>
                        </div>
                        <div class="input-group">
                            <label>Obrázek programu</label>
                            <div class="upload-area-programs" onclick="document.getElementById('program_image').click()">
                                <label for="program_image" class="custom-file-button-programs">Vybrat obrázek</label>
                                <input type="file" name="program_image" id="program_image" accept="image/*" class="hidden-file-input" style="display:none !important;">
                                <div id="selected-program-image-info" class="selected-files-info-programs"></div>
                            </div>
                            <small class="input-help">Podporované formáty: JPG, PNG, GIF (max. 5MB)</small>
                        </div>
                    </div>
                    
                    <div class="input-group">
                        <label for="program_description">Popis</label>
                        <textarea name="program_description" id="program_description" placeholder="Popis programu a jeho funkcí..."></textarea>
                    </div>
                    
                    <button type="submit" class="programs-upload-btn">
                        <i class="fas fa-plus"></i> Přidat program
                    </button>
                </form>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Edit Program Modal -->
<div id="editProgramModal" class="modal" style="display: none;">
    <div class="modal-content">
        <div class="modal-header">
            <h2>Upravit program</h2>
            <span class="close" onclick="closeEditModal()">&times;</span>
        </div>
        <form id="editProgramForm" enctype="multipart/form-data">
            <input type="hidden" id="edit_filename" name="filename">
            <input type="hidden" id="edit_original_category" name="original_category">
            
            <div class="form-row">
                <div class="input-group">
                    <label for="edit_program_title">Název programu *</label>
                    <input type="text" name="program_title" id="edit_program_title" required>
                </div>
                <div class="input-group">
                    <label for="edit_program_url">URL adresa *</label>
                    <input type="url" name="program_url" id="edit_program_url" required>
                </div>
            </div>
            
            <div class="form-row">
                <div class="input-group">
                    <label for="edit_program_category">Kategorie *</label>
                    <select name="program_category" id="edit_program_category" required>
                        <?php foreach ($allCategories as $categoryKey => $categoryInfo): ?>
                            <option value="<?= htmlspecialchars($categoryKey) ?>"><?= htmlspecialchars($categoryInfo['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="input-group">
                    <label for="edit_program_price">Cena</label>
                    <input type="text" name="program_price" id="edit_program_price" placeholder="např. 29 USD/měsíc">
                </div>
            </div>
            
            <div class="form-row">
                <div class="input-group">
                    <label for="edit_program_license">Typ licence</label>
                    <select name="program_license" id="edit_program_license">
                        <option value="">Vyberte typ licence</option>
                        <option value="subscription">Předplatné</option>
                        <option value="one-time">Jednorázová</option>
                        <option value="freemium">Freemium</option>
                        <option value="trial">Zkušební verze</option>
                    </select>
                </div>
                <div class="input-group">
                    <label>Změnit obrázek programu (volitelné)</label>
                    <div class="upload-area-programs" onclick="document.getElementById('edit_program_image').click()">
                        <label for="edit_program_image" class="custom-file-button-programs">Vybrat nový</label>
                        <input type="file" name="program_image" id="edit_program_image" accept="image/*" class="hidden-file-input" style="display:none !important;">
                        <div id="selected-edit-image-info" class="selected-files-info-programs"></div>
                    </div>
                    <small class="input-help">Ponechte prázdné pro zachování současného obrázku</small>
                </div>
            </div>
            
            <div class="input-group">
                <label for="edit_program_description">Popis</label>
                <textarea name="program_description" id="edit_program_description" placeholder="Popis programu a jeho funkcí..."></textarea>
            </div>
            
            <div class="current-image-container" id="currentImageContainer" style="display: none;">
                <label>Současný obrázek:</label>
                <img id="currentImage" src="" alt="Current program image" style="max-width: 100px; height: auto; border-radius: 5px; border: 1px solid #ddd;">
            </div>
            
            <div class="modal-actions">
                <button type="button" class="cancel-btn" onclick="closeEditModal()">Zrušit</button>
                <button type="submit" class="save-btn">Uložit změny</button>
            </div>
        </form>
    </div>
</div>

<!-- Edit Category Modal -->
<div id="editProgramCategoryModal" class="modal" style="display: none;">
    <div class="modal-content">
        <div class="modal-header">
            <h2>Upravit kategorii</h2>
            <span class="close" onclick="closeEditProgramCategoryModal()">&times;</span>
        </div>
        <form id="editProgramCategoryForm">
            <input type="hidden" id="edit_program_category_original" name="original_category">
            
            <div class="input-group">
                <label for="edit_program_category_name">Název kategorie *</label>
                <input type="text" name="category_name" id="edit_program_category_name" required placeholder="např. Upravená kategorie">
            </div>
            
            <div class="input-group">
                <label for="edit_program_category_color">Barva kategorie</label>
                <input type="color" name="category_color" id="edit_program_category_color" value="#3498db">
            </div>
            
            <div class="modal-actions">
                <button type="button" class="cancel-btn" onclick="closeEditProgramCategoryModal()">Zrušit</button>
                <button type="submit" class="save-btn">Uložit změny</button>
            </div>
        </form>
    </div>
</div>

<!-- Add Custom Category Modal -->
<div id="addProgramCategoryModal" class="modal" style="display: none;">
    <div class="modal-content">
        <div class="modal-header">
            <h2>Přidat novou kategorii</h2>
            <span class="close" onclick="closeAddProgramCategoryModal()">&times;</span>
        </div>
        <form id="addProgramCategoryForm">
            <div class="input-group">
                <label for="new_program_category_name">Název kategorie *</label>
                <input type="text" name="category_name" id="new_program_category_name" required placeholder="např. Moje kategorie">
            </div>
            
            <div class="input-group">
                <label for="new_program_category_color">Barva kategorie</label>
                <input type="color" name="category_color" id="new_program_category_color" value="#3498db">
            </div>
            
            <div class="modal-actions">
                <button type="button" class="cancel-btn" onclick="closeAddProgramCategoryModal()">Zrušit</button>
                <button type="submit" class="save-btn">Vytvořit kategorii</button>
            </div>
        </form>
    </div>
</div>

<style>
    /* Programs Page Styling - based on links.php */
    #programs .content-area {
        display: flex;
        flex-direction: column;
        gap: 30px;
    }
    .upload-area-programs {
        display: flex !important;
        flex-direction: row !important; /* Vše v jedné lince */
        align-items: center !important;
        justify-content: flex-start !important;
        gap: 12px !important;
        padding: 6px 15px !important;
        min-height: 48px !important; /* Výška přesně k ostatním inputům */
        border: 1px dashed #b5c1d1 !important;
        border-radius: 8px !important;
        background-color: #ffffff !important;
        transition: all 0.2s ease !important;
        cursor: pointer !important;
        width: 100% !important;
        box-sizing: border-box !important;
    }

    .upload-area-programs:hover {
        border-color: #007bff !important;
        background-color: #f5f9ff !important;
    }

    /* Transformace labelu na ikonu + text */
    .custom-file-button-programs {
        font-size: 0 !important; /* Schováme "Vybrat obrázek" */
        display: flex !important;
        align-items: center !important;
        margin: 0 !important;
        cursor: pointer !important;
    }

    /* Ikona mráčku/uploadu */
    .custom-file-button-programs::before {
        content: '\f0ee' !important; /* Cloud Upload ikona */
        font-family: "Font Awesome 5 Free" !important;
        font-weight: 900 !important;
        font-size: 18px !important;
        color: #007bff !important;
        margin-right: 10px !important;
    }

    /* Nový text vedle ikony */
    .custom-file-button-programs::after {
        content: 'Nahrát obrázek...' !important;
        font-size: 14px !important;
        color: #4a5568 !important;
        font-weight: 500 !important;
    }

    /* Název vybraného souboru - nenápadně vpravo */
    .selected-files-info-programs {
        margin-left: auto !important;
        font-size: 12px !important;
        color: #2ecc71 !important;
        font-weight: 600 !important;
        white-space: nowrap !important;
        overflow: hidden !important;
        text-overflow: ellipsis !important;
        max-width: 120px !important;
    }
    /* Upload Container */
    .programs-upload-container {
        background: white;
        border-radius: 10px;
        padding: 25px;
        box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
    }

    .programs-upload-container h2 {
        color: #2c3e50;
        margin-top: 0;
        margin-bottom: 20px;
        font-size: 20px;
    }

    .programs-upload-form {
        display: flex;
        flex-direction: column;
        gap: 15px;
    }

    .form-row {
        display: flex;
        gap: 20px;
        flex-wrap: wrap;
    }

    .input-group {
        display: flex;
        flex-direction: column;
        gap: 5px;
        flex: 1;
        min-width: 200px;
    }

    .input-group label {
        font-weight: 600;
        color: #34495e;
    }

    .input-group input[type="text"],
    .input-group input[type="url"],
    .input-group select,
    .input-group textarea {
        width: 100%;
        padding: 12px;
        border: 1px solid #ddd;
        border-radius: 5px;
        font-size: 16px;
        box-sizing: border-box;
    }

    .input-group textarea {
        height: 100px;
        resize: vertical;
    }

    .programs-upload-btn {
        background: #3498db;
        color: white;
        border: none;
        padding: 12px 20px;
        border-radius: 5px;
        font-size: 16px;
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
        transition: background 0.2s;
        margin-top: 10px;
        font-weight: 600;
        width: fit-content;
    }

    .programs-upload-btn:hover {
        background: #2980b9;
    }

    /* Programs Container */
    .programs-container {
        background: white;
        border-radius: 10px;
        padding: 25px;
        box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
    }

    .programs-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 20px;
        flex-wrap: wrap;
        gap: 15px;
    }

    .programs-header h2 {
        color: #2c3e50;
        margin: 0;
        font-size: 20px;
    }

    .programs-filter {
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .programs-filter label {
        font-weight: 600;
        color: #34495e;
    }

    .programs-filter select {
        padding: 8px 12px;
        border: 1px solid #ddd;
        border-radius: 5px;
        font-size: 14px;
    }

    .add-category-btn {
        background: #27ae60;
        color: white;
        border: none;
        padding: 8px 15px;
        border-radius: 5px;
        font-size: 14px;
        cursor: pointer;
        display: flex;
        align-items: center;
        gap: 5px;
        transition: background 0.2s;
    }

    .add-category-btn:hover {
        background: #219150;
    }

    .programs-grid {
        display: flex;
        flex-direction: column;
        gap: 15px;
    }

    /* Programs Category Styling */
    .programs-category {
        border: 1px solid #e0e0e0;
        border-radius: 8px;
        overflow: hidden;
        transition: all 0.3s ease;
    }

    .programs-category:hover {
        box-shadow: 0 5px 15px rgba(0, 0, 0, 0.08);
    }

    .programs-category-header {
        display: flex;
        align-items: center;
        padding: 15px 20px;
        background: #f8f9fa;
        cursor: pointer;
        transition: background 0.2s;
        gap: 15px;
    }

    .programs-category-header:hover {
        background: #f1f3f4;
    }

    .programs-category-icon {
        font-size: 24px;
    }

    .programs-category-info {
        flex: 1;
    }

    .programs-category-info h3 {
        margin: 0 0 3px 0;
        font-size: 18px;
    }

    .programs-count {
        font-size: 14px;
        color: #7f8c8d;
    }

    .category-admin-actions {
        margin-left: auto;
        margin-right: 10px;
        display: flex;
        gap: 5px;
    }

    .programs-category-toggle {
        color: #95a5a6;
        transform: rotate(0deg);
        transition: transform 0.3s;
    }

    .programs-category.programs-open .programs-category-toggle {
        transform: rotate(180deg);
    }

    .programs-category-content {
        padding: 15px 20px;
        border-top: 1px solid #e0e0e0;
        background: white;
    }

    /* Programs Item */
    .programs-item {
        display: flex;
        align-items: flex-start;
        padding: 15px;
        margin-bottom: 10px;
        border-radius: 6px;
        transition: background 0.2s;
        border: 1px solid #f0f0f0;
        justify-content: space-between;
    }

    .programs-item:hover {
        background: #f8f9fa;
        border-color: #e0e0e0;
    }

    .programs-item-content {
        display: flex;
        align-items: flex-start;
        flex: 1;
    }

    .programs-image {
        margin-right: 15px;
        margin-top: 2px;
        width: 64px;
        height: 64px;
        flex-shrink: 0;
    }

    .programs-image img {
        width: 100%;
        height: 100%;
        object-fit: cover;
        border-radius: 8px;
        border: 2px solid #e9ecef;
        transition: border-color 0.3s ease;
    }

    .programs-image img:hover {
        border-color: #dee2e6;
    }

    /* Adjust icon size to match image */
    .programs-icon {
        margin-right: 15px;
        font-size: 32px;
        margin-top: 2px;
        width: 64px;
        height: 64px;
        display: flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
    }

    .programs-info {
        flex: 1;
    }

    .programs-name {
        margin-bottom: 5px;
        display: flex;
        align-items: center;
        gap: 10px;
        flex-wrap: wrap;
    }

    .programs-name a {
        color: #2980b9;
        text-decoration: none;
        font-weight: 600;
        font-size: 16px;
    }

    .programs-name a:hover {
        text-decoration: underline;
    }

    .programs-price {
        background: #e74c3c;
        color: white;
        padding: 2px 8px;
        border-radius: 12px;
        font-size: 12px;
        font-weight: 500;
    }

    .programs-description {
        color: #555;
        margin-bottom: 8px;
        line-height: 1.4;
        font-size: 14px;
    }

    .programs-meta {
        font-size: 12px;
        color: #95a5a6;
    }

    .doc-actions {
        display: flex;
        gap: 4px;
        flex-shrink: 0;
    }

    /* No programs message */
    .no-programs {
        display: flex;
        flex-direction: column;
        align-items: center;
        padding: 40px 0;
        color: #95a5a6;
    }

    .no-programs i {
        font-size: 48px;
        margin-bottom: 15px;
        opacity: 0.5;
    }

    /* Empty category message */
    .empty-category-message {
        display: flex;
        flex-direction: column;
        align-items: center;
        padding: 30px;
        text-align: center;
    }

    .empty-category-message p {
        font-size: 14px;
        line-height: 1.4;
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
        animation: fadeIn 0.3s ease;
        display: none !important; /* Force initial hidden state */
    }

    .modal.show {
        display: flex !important;
        align-items: center;
        justify-content: center;
    }

    .modal-content {
        background-color: white;
        border-radius: 10px;
        box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
        max-width: 800px;
        width: 90%;
        max-height: 90vh;
        overflow-y: auto;
        animation: slideIn 0.3s ease;
    }

    .modal-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 20px 25px;
        border-bottom: 1px solid #e0e0e0;
        background: #f8f9fa;
        border-radius: 10px 10px 0 0;
    }

    .modal-header h2 {
        margin: 0;
        color: #2c3e50;
        font-size: 20px;
    }

    .close {
        color: #aaa;
        font-size: 28px;
        font-weight: bold;
        cursor: pointer;
        line-height: 1;
    }

    .close:hover {
        color: #e74c3c;
    }

    #editProgramForm,
    #editProgramCategoryForm,
    #addProgramCategoryForm {
        padding: 25px;
        display: flex;
        flex-direction: column;
        gap: 15px;
    }

    .current-image-container {
        margin: 15px 0;
        padding: 15px;
        background: #f8f9fa;
        border-radius: 5px;
    }

    .current-image-container label {
        display: block;
        margin-bottom: 10px;
        font-weight: 600;
        color: #34495e;
    }

    .modal-actions {
        display: flex;
        gap: 10px;
        justify-content: flex-end;
        margin-top: 20px;
        padding-top: 20px;
        border-top: 1px solid #e0e0e0;
    }

    .modal-actions button {
        padding: 10px 20px;
        border: none;
        border-radius: 5px;
        cursor: pointer;
        font-size: 14px;
        font-weight: 600;
        transition: all 0.3s ease;
    }

    .save-btn {
        background: #27ae60;
        color: white;
    }

    .save-btn:hover {
        background: #229954;
    }

    .cancel-btn {
        background: #95a5a6;
        color: white;
    }

    .cancel-btn:hover {
        background: #7f8c8d;
    }

    @keyframes fadeIn {
        from { opacity: 0; }
        to { opacity: 1; }
    }

    @keyframes slideIn {
        from { 
            opacity: 0; 
            transform: translateY(-50px);
        }
        to { 
            opacity: 1; 
            transform: translateY(0);
        }
    }

    /* Responsive modal */
    @media (max-width: 768px) {
        .modal-content {
            width: 95%;
            margin: 10px;
        }
        
        .modal-header {
            padding: 15px 20px;
        }
        
        #editProgramForm {
            padding: 20px;
        }
        
        .form-row {
            flex-direction: column;
        }
    }

    /* Status Messages */
    #status-messages {
        position: absolute;
        top: 20px;
        right: 20px;
        width: auto;
        max-width: 300px;
        z-index: 1050;
    }

    .status-message {
        display: flex;
        align-items: center;
        padding: 12px 15px;
        border-radius: 5px;
        margin-bottom: 15px;
        font-weight: 500;
        animation: slideDown 0.3s ease;
    }

    .status-message i {
        margin-right: 10px;
        font-size: 16px;
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

    @keyframes slideDown {
        from { transform: translateY(-20px); opacity: 0; }
        to { transform: translateY(0); opacity: 1; }
    }

    .input-help {
        font-size: 12px;
        color: #7f8c8d;
        margin-top: 5px;
        display: block;
    }

    /* Missing admin button styles */
    .admin-btn {
        background: transparent;
        border: none;
        padding: 5px;
        border-radius: 3px;
        cursor: pointer;
        color: #6c757d;
        transition: all 0.2s ease;
    }

    .admin-btn:hover {
        color: #495057;
        background: rgba(0, 0, 0, 0.05);
    }

    .edit-btn:hover {
        color: #007bff !important;
    }

    .delete-btn:hover {
        color: #dc3545 !important;
    }

    .mini-btn {
        background: transparent;
        border: none;
        padding: 5px 8px;
        border-radius: 3px;
        cursor: pointer;
        color: #6c757d;
        font-size: 14px;
        transition: all 0.2s ease;
    }

    .mini-btn:hover {
        background: rgba(0, 0, 0, 0.05);
    }

    .mini-btn.edit-btn:hover {
        color: #007bff;
    }

    .mini-btn.delete-btn:hover {
        color: #dc3545;
    }
</style>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Only run if we're on the programs page
        if (!document.getElementById('programs')) {
            return;
        }

        // Force hide all modals on page load to prevent auto-showing
        const modals = ['editProgramModal', 'editProgramCategoryModal', 'addProgramCategoryModal'];
        modals.forEach(modalId => {
            const modal = document.getElementById(modalId);
            if (modal) {
                modal.style.display = 'none';
                modal.classList.remove('show');
                console.log(`Modal ${modalId} forced to hidden state`);
            }
        });

        // Category toggle functionality
        const programsHeaders = document.querySelectorAll('.programs-category-header');

        programsHeaders.forEach(header => {
            header.addEventListener('click', function(e) {
                // Don't toggle if clicking on admin actions
                if (e.target.closest('.category-admin-actions')) {
                    return;
                }
                
                const category = this.parentElement;
                const content = category.querySelector('.programs-category-content');
                const folderIcon = header.querySelector('.programs-category-icon i');
                const toggleIcon = header.querySelector('.programs-category-toggle i');

                if (content.style.display === 'none' || content.style.display === '') {
                    // Show content
                    content.style.display = 'block';
                    folderIcon.className = 'fas fa-folder-open';
                    toggleIcon.style.transform = 'rotate(180deg)';
                    category.classList.add('programs-open');
                } else {
                    // Hide content
                    content.style.display = 'none';
                    folderIcon.className = 'fas fa-folder';
                    toggleIcon.style.transform = 'rotate(0deg)';
                    category.classList.remove('programs-open');
                }
            });
        });

        // Initialize all categories as closed
        document.querySelectorAll('.programs-category-content').forEach(content => {
            content.style.display = 'none';
        });

        // Add event listeners for forms
        setupFormHandlers();
    });

    // Setup form handlers
    function setupFormHandlers() {
        // Edit category form
        const editCategoryForm = document.getElementById('editProgramCategoryForm');
        if (editCategoryForm) {
            editCategoryForm.addEventListener('submit', function(e) {
                e.preventDefault();
                console.log('Edit program category form submitted');
                
                const formData = new FormData(this);
                
                fetch('../../edit-program-category.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        showStatusMessage('success', 'Kategorie byla úspěšně upravena.');
                        closeEditProgramCategoryModal();
                        (() => window.location.reload(), 500);
                    } else {
                        showStatusMessage('error', data.message || 'Chyba při úpravě kategorie.');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    showStatusMessage('error', 'Chyba při úpravě kategorie.');
                });
        })}

        // Add category form
        const addCategoryForm = document.getElementById('addProgramCategoryForm');
        if (addCategoryForm) {
            addCategoryForm.addEventListener('submit', function(e) {
                e.preventDefault();
                console.log('Add program category form submitted');
                
                const categoryName = document.getElementById('new_program_category_name').value.trim();
                const categoryColor = document.getElementById('new_program_category_color').value;
                
                if (!categoryName) {
                    showStatusMessage('error', 'Název kategorie je povinný.');
                    return;
                }
                
                const submitBtn = this.querySelector('.save-btn');
                const originalText = submitBtn.textContent;
                submitBtn.textContent = 'Vytváří se...';
                submitBtn.disabled = true;
                
                const formData = new FormData();
                formData.append('category_name', categoryName);
                formData.append('category_color', categoryColor);
                
                fetch('../../add-program-category.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => {
                    console.log('Response received:', response.status);
                    return response.text();
                })
                .then(text => {
                    console.log('Raw response:', text);
                    try {
                        const data = JSON.parse(text);
                        console.log('Response data:', data);
                        if (data.success) {
                            showStatusMessage('success', 'Kategorie byla úspěšně vytvořena.');
                            closeAddProgramCategoryModal();
                            (() => window.location.reload(), 500);
                        } else {
                            showStatusMessage('error', data.message || 'Chyba při vytváření kategorie.');
                            if (data.debug) {
                                console.log('Debug info:', data.debug);
                            }
                        }
                    } catch (e) {
                        console.error('Failed to parse JSON:', e);
                        showStatusMessage('error', 'Server returned invalid response: ' + text.substring(0, 100));
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    showStatusMessage('error', 'Chyba při vytváření kategorie.');
                })
                .finally(() => {
                    submitBtn.textContent = originalText;
                    submitBtn.disabled = false;
                });
            });
        }

        // Handle edit program form submission
        const editProgramForm = document.getElementById('editProgramForm');
        if (editProgramForm) {
            editProgramForm.addEventListener('submit', function(e) {
                e.preventDefault();
                
                const formData = new FormData(this);
                formData.append('action', 'edit_program');
                
                const submitBtn = this.querySelector('.save-btn');
                const originalText = submitBtn.textContent;
                submitBtn.textContent = 'Ukládá se...';
                submitBtn.disabled = true;
                
                fetch('../../edit-program.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => {
                    if (!response.ok) {
                        throw new Error(`HTTP error! status: ${response.status}`);
                    }
                    return response.json();
                })
                .then(data => {
                    if (data.success) {
                        showStatusMessage('success', 'Program byl úspěšně upraven.');
                        closeEditModal();
                        (() => window.location.reload(), 500);
                    } else {
                        showStatusMessage('error', data.message || 'Chyba při úpravě programu.');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    showStatusMessage('error', 'Chyba při komunikaci se serverem.');
                })
                .finally(() => {
                    submitBtn.textContent = originalText;
                    submitBtn.disabled = false;
                });
            });
        }
    }

    // Filter functionality
    function filterPrograms() {
        const selectedCategory = document.getElementById('sort-programs').value;
        const categories = document.querySelectorAll('.programs-category');

        categories.forEach(category => {
            if (selectedCategory === 'all' || category.id === 'programs-category-' + selectedCategory) {
                category.style.display = 'block';
            } else {
                category.style.display = 'none';
            }
        });
    }

    // Program management functions
    function editProgram(filename, category) {
        if (event) event.stopPropagation();
        
        fetch('../../get-program.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: 'filename=' + encodeURIComponent(filename) + '&category=' + encodeURIComponent(category)
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                document.getElementById('edit_filename').value = filename;
                document.getElementById('edit_original_category').value = category;
                document.getElementById('edit_program_title').value = data.program.title || '';
                document.getElementById('edit_program_url').value = data.program.url || '';
                document.getElementById('edit_program_category').value = data.program.category || category;
                document.getElementById('edit_program_price').value = data.program.price || '';
                document.getElementById('edit_program_license').value = data.program.license_type || '';
                document.getElementById('edit_program_description').value = data.program.description || '';
                
                const imageContainer = document.getElementById('currentImageContainer');
                const currentImage = document.getElementById('currentImage');
                if (data.program.image) {
                    currentImage.src = data.program.image;
                    imageContainer.style.display = 'block';
                } else {
                    imageContainer.style.display = 'none';
                }
                
                document.getElementById('editProgramModal').classList.add('show');
            } else {
                showStatusMessage('error', data.message || 'Chyba při načítání dat programu');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showStatusMessage('error', 'Chyba při načítání dat programu');
        });
    }

    function deleteProgram(filename, category) {
        if (event) event.stopPropagation();
        
        if (!confirm('Opravdu chcete smazat tento program?')) {
            return;
        }
        
        fetch('../../delete-program.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: 'filename=' + encodeURIComponent(filename) + '&category=' + encodeURIComponent(category)
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showStatusMessage('success', 'Program byl úspěšně smazán.');
                (() => window.location.reload(), 500);
            } else {
                showStatusMessage('error', data.message || 'Chyba při mazání programu');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showStatusMessage('error', 'Chyba při mazání programu');
        });
    }

    function editProgramCategory(categoryKey) {
        console.log('editProgramCategory called with:', categoryKey);
        
        if (event) {
            event.stopPropagation();
            event.preventDefault();
        }
        
        // Get current category data from the DOM element
        const categoryElement = document.getElementById('programs-category-' + categoryKey);
        if (!categoryElement) {
            console.error('Category element not found:', 'programs-category-' + categoryKey);
            showStatusMessage('error', 'Kategorie nebyla nalezena');
            return;
        }
        
        const currentName = categoryElement.getAttribute('data-category-name') || '';
        const currentColor = categoryElement.getAttribute('data-category-color') || '#3498db';
        
        console.log('Current name:', currentName);
        console.log('Current color:', currentColor);
        
        // Check if modal exists
        const modal = document.getElementById('editProgramCategoryModal');
        if (!modal) {
            console.error('Edit program category modal not found');
            showStatusMessage('error', 'Modal nebyl nalezen');
            return;
        }
        
        // Populate modal fields using the specific IDs
        const originalField = document.getElementById('edit_program_category_original');
        const nameField = document.getElementById('edit_program_category_name');
        const colorField = document.getElementById('edit_program_category_color');
        
        if (!originalField || !nameField || !colorField) {
            console.error('Modal fields not found');
            console.error('Original field:', originalField);
            console.error('Name field:', nameField);
            console.error('Color field:', colorField);
            showStatusMessage('error', 'Formulářové pole nebyla nalezena');
            return;
        }
        
        originalField.value = categoryKey;
        nameField.value = currentName;
        colorField.value = currentColor;
        
        console.log('Modal populated, showing...');
        
        // Show the modal
        modal.style.display = 'flex';
        modal.classList.add('show');
        
        console.log('Modal display set to:', modal.style.display);
        console.log('Modal classes:', modal.className);
        
        // Focus on name field
        setTimeout(() => {
            if (nameField && nameField.focus) {
                nameField.focus();
                nameField.select();
            }
        }, 100);
    }

    function showAddCategoryModal() {
        const modal = document.getElementById('addProgramCategoryModal');
        if (modal) {
            modal.style.display = 'flex';
            modal.classList.add('show');
            
            setTimeout(() => {
                const nameInput = document.getElementById('new_program_category_name');
                if (nameInput) {
                    nameInput.focus();
                    nameInput.select();
                }
            }, 100);
        }
    }

    function closeEditModal() {
        document.getElementById('editProgramModal').classList.remove('show');
    }

    function closeEditProgramCategoryModal() {
        const modal = document.getElementById('editProgramCategoryModal');
        if (modal) {
            modal.style.display = 'none';
            modal.classList.remove('show');
            
            // Reset form
            const form = document.getElementById('editProgramCategoryForm');
            if (form) {
                form.reset();
            }
        }
    }

    function closeAddProgramCategoryModal() {
        const modal = document.getElementById('addProgramCategoryModal');
        if (modal) {
            modal.style.display = 'none';
            modal.classList.remove('show');
            
            // Reset form
            const form = document.getElementById('addProgramCategoryForm');
            if (form) {
                form.reset();
            }
        }
    }

    function showStatusMessage(type, message) {
        let container = document.getElementById('status-messages');
        if (!container) {
            container = document.createElement('div');
            container.id = 'status-messages';
            document.querySelector('#programs .content-area').insertBefore(container, document.querySelector('#programs .content-area').firstChild);
        }

        const messageDiv = document.createElement('div');
        messageDiv.className = `status-message status-${type}`;
        messageDiv.innerHTML = `
            <i class="fas ${type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle'}"></i>
            ${message}
        `;

        container.appendChild(messageDiv);

        setTimeout(() => {
            if (messageDiv.parentNode) {
                messageDiv.remove();
            }
        }, 5000);
    }

    // Close modals when clicking outside
    document.addEventListener('click', function(e) {
        if (e.target.classList.contains('modal')) {
            if (e.target.id === 'editProgramModal') closeEditModal();
            if (e.target.id === 'editProgramCategoryModal') closeEditProgramCategoryModal();
            if (e.target.id === 'addProgramCategoryModal') closeAddProgramCategoryModal();
        }
    });

    // Expose functions globally for programs page
    if (document.getElementById('programs')) {
        window.filterPrograms = filterPrograms;
        window.editProgramCategory = editProgramCategory;
        window.deleteProgramCategory = deleteProgramCategory;
        window.deleteProgram = deleteProgram;
        window.editProgram = editProgram;
        window.closeEditModal = closeEditModal;
        window.closeEditProgramCategoryModal = closeEditProgramCategoryModal;
        window.closeAddProgramCategoryModal = closeAddProgramCategoryModal;
        window.showAddCategoryModal = showAddCategoryModal;
        window.showStatusMessage = showStatusMessage;
    }

    function deleteProgramCategory(categoryKey) {
        if (event) {
            event.stopPropagation();
            event.preventDefault();
        }

        if (!confirm(`Opravdu chcete smazat kategorii "${categoryKey}" a všechny programy v ní?\n\nTato akce je nevratná!`)) {
            return;
        }

        const formData = new FormData();
        formData.append('category', categoryKey);

        fetch('../../delete-program-category.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showStatusMessage('success', data.message);
                (() => window.location.reload(), 500);
            } else {
                showStatusMessage('error', data.message || 'Neznámá chyba');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showStatusMessage('error', 'Došlo k chybě při mazání kategorie.');
        });
    }
</script>
