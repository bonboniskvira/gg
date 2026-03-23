<!--Page Links -->

<?php

require_once __DIR__ . '/../../access.php';

?>

<div class="page-content" id="links">
    <div class="content-area">
        <!-- Status Messages -->
        <div id="status-messages"></div>

        <div class="links-container">
            <div class="links-header">
                <h2>Odkazy a stránky</h2>
                <div class="links-filter">
                    <label for="sort-links">Filtrovat podle:</label>
                    <select id="sort-links" onchange="filterLinks()">
                        <option value="all">Všechny kategorie</option>
                        <option value="general">Obecné</option>
                        <option value="tools">Nástroje</option>
                        <option value="courses">Kurzy</option>
                        <option value="resources">Zdroje</option>
                        <option value="partners">Partneři</option>
                    </select>
                </div>
            </div>

            <div class="links-grid">
                <?php
                $linksDir = __DIR__ . '/../../links/';

                // Create links directory if it doesn't exist
                if (!is_dir($linksDir)) {
                    mkdir($linksDir, 0777, true);
                }

                $categories = ['general', 'tools', 'courses', 'resources', 'partners'];
                $categoryNames = [
                    'general' => 'Obecné',
                    'tools' => 'Nástroje',
                    'courses' => 'Kurzy',
                    'resources' => 'Zdroje',
                    'partners' => 'Partneři'
                ];

                $hasLinks = false;

                foreach ($categories as $category) {
                    $categoryDir = $linksDir . $category . '/';

                    if (!is_dir($categoryDir)) {
                        mkdir($categoryDir, 0777, true);
                    }

                    $linkFiles = glob($categoryDir . '*.json');

                    if (!empty($linkFiles)) {
                        $hasLinks = true;
                        echo '<div class="links-category" id="links-category-' . $category . '">';
                        echo '<div class="links-category-header">';
                        echo '<div class="links-category-icon"><i class="fas fa-folder"></i></div>';
                        echo '<div class="links-category-info">';
                        echo '<h3>' . $categoryNames[$category] . '</h3>';
                        echo '<span class="links-count">' . count($linkFiles) . ' ' . (count($linkFiles) == 1 ? 'odkaz' : (count($linkFiles) >= 2 && count($linkFiles) <= 4 ? 'odkazy' : 'odkazů')) . '</span>';
                        echo '</div>';

                        // Add admin actions using existing styles
                        if (isAdmin()) {
                            echo '<div class="category-admin-actions" onclick="event.stopPropagation()">';
                            echo '<button class="admin-btn edit-btn" onclick="editLinkCategory(\'' . $category . '\')" title="Upravit kategorii">';
                            echo '<i class="fas fa-edit"></i>';
                            echo '</button>';
                            echo '<button class="admin-btn delete-btn" onclick="deleteLinkCategory(\'' . $category . '\')" title="Smazat kategorii">';
                            echo '<i class="fas fa-trash"></i>';
                            echo '</button>';
                            echo '</div>';
                        }

                        echo '<div class="links-category-toggle"><i class="fas fa-chevron-down"></i></div>';
                        echo '</div>';

                        echo '<div class="links-category-content" style="display: none;">';
                        foreach ($linkFiles as $linkFile) {
                            $linkData = json_decode(file_get_contents($linkFile), true);

                            if ($linkData) {
                                echo '<div class="links-item">';
                                echo '<div class="links-item-content">';
                                echo '<div class="links-icon">' . getIconForUrl($linkData['url']) . '</div>';
                                echo '<div class="links-info">';
                                echo '<div class="links-name"><a href="' . htmlspecialchars($linkData['url']) . '" target="_blank">' . htmlspecialchars($linkData['title']) . '</a></div>';

                                if (!empty($linkData['description'])) {
                                    echo '<div class="links-description">' . htmlspecialchars($linkData['description']) . '</div>';
                                }

                                echo '<div class="links-meta">Přidáno: ' . date("d.m.Y", $linkData['date_added']) . '</div>';
                                echo '</div>';
                                echo '</div>';

                                // Add link actions using existing styles
                                if (isAdmin()) {
                                    echo '<div class="doc-actions">';
                                    echo '<button class="mini-btn edit-btn" onclick="editLink(\'' . basename($linkFile) . '\', \'' . $category . '\')" title="Upravit odkaz">';
                                    echo '<i class="fas fa-edit"></i>';
                                    echo '</button>';
                                    echo '<button class="mini-btn delete-btn" onclick="deleteLink(\'' . basename($linkFile) . '\', \'' . $category . '\')" title="Smazat odkaz">';
                                    echo '<i class="fas fa-trash"></i>';
                                    echo '</button>';
                                    echo '</div>';
                                }

                                echo '</div>';
                            }
                        }
                        echo '</div>';
                        echo '</div>';
                    }
                }

                if (!$hasLinks) {
                    echo '<div class="no-links">
                            <i class="fas fa-link"></i>
                            <p>Zatím nebyly přidány žádné odkazy.</p>
                          </div>';
                }

                // Helper function to get appropriate icon based on URL
                function getIconForUrl($url)
                {
                    $domain = parse_url($url, PHP_URL_HOST);

                    if (strpos($domain, 'youtube.com') !== false || strpos($domain, 'youtu.be') !== false) {
                        return '<i class="fab fa-youtube" style="color: #ff0000;"></i>';
                    } elseif (strpos($domain, 'facebook.com') !== false || strpos($domain, 'fb.com') !== false) {
                        return '<i class="fab fa-facebook" style="color: #1877f2;"></i>';
                    } elseif (strpos($domain, 'twitter.com') !== false || strpos($domain, 'x.com') !== false) {
                        return '<i class="fab fa-twitter" style="color: #1da1f2;"></i>';
                    } elseif (strpos($domain, 'linkedin.com') !== false) {
                        return '<i class="fab fa-linkedin" style="color: #0077b5;"></i>';
                    } elseif (strpos($domain, 'github.com') !== false) {
                        return '<i class="fab fa-github" style="color: #333;"></i>';
                    } elseif (strpos($domain, 'google.com') !== false) {
                        return '<i class="fab fa-google" style="color: #4285f4;"></i>';
                    } else {
                        return '<i class="fas fa-external-link-alt" style="color: #3498db;"></i>';
                    }
                }
                ?>
            </div>
        </div>

        <?php if (isAdmin()): ?>
            <div class="links-upload-container">
                <h2>Přidat nový odkaz</h2>
                <form action="publish-link.php" method="post" class="links-upload-form">
                    <div class="input-group">
                        <label for="link_title">Název odkazu</label>
                        <input type="text" name="link_title" id="link_title" placeholder="Zadejte název odkazu" required>
                    </div>
                    <div class="input-group">
                        <label for="link_url">URL adresa</label>
                        <input type="url" name="link_url" id="link_url" placeholder="https://..." required>
                    </div>
                    <div class="input-group">
                        <label for="link_category">Kategorie</label>
                        <select name="link_category" id="link_category">
                            <option value="general">Obecné</option>
                            <option value="tools">Nástroje</option>
                            <option value="courses">Kurzy</option>
                            <option value="resources">Zdroje</option>
                            <option value="partners">Partneři</option>
                        </select>
                    </div>
                    <div class="input-group">
                        <label for="link_description">Popis</label>
                        <textarea name="link_description" id="link_description" placeholder="Volitelný popis odkazu..."></textarea>
                    </div>
                    <button type="submit" class="links-upload-btn">
                        <i class="fas fa-link"></i> Uložit odkaz
                    </button>
                </form>
            </div>
        <?php endif; ?>
    </div>
</div>

<style>
    /* Import admin button styles */
    @import url('admin-btns.css');
    
    /* Links Page Styling with links-specific class names */
    #links .content-area {
        display: flex;
        flex-direction: column;
        gap: 30px;
    }

    /* Upload Container */
    .links-upload-container {
        background: white;
        border-radius: 10px;
        padding: 25px;
        box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
    }

    .links-upload-container h2 {
        color: #2c3e50;
        margin-top: 0;
        margin-bottom: 20px;
        font-size: 20px;
    }

    .links-upload-form {
        display: flex;
        flex-direction: column;
        gap: 15px;
    }

    .input-group {
        display: flex;
        flex-direction: column;
        gap: 5px;
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
        height: 80px;
        resize: vertical;
    }

    .links-upload-btn {
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
    }

    .links-upload-btn:hover {
        background: #2980b9;
    }

    /* Links Container */
    .links-container {
        background: white;
        border-radius: 10px;
        padding: 25px;
        box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
    }

    .links-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 20px;
        flex-wrap: wrap;
        gap: 15px;
    }

    .links-header h2 {
        color: #2c3e50;
        margin: 0;
        font-size: 20px;
    }

    .links-filter {
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .links-filter label {
        font-weight: 600;
        color: #34495e;
    }

    .links-filter select {
        padding: 8px 12px;
        border: 1px solid #ddd;
        border-radius: 5px;
        font-size: 14px;
    }

    .links-grid {
        display: flex;
        flex-direction: column;
        gap: 15px;
    }

    /* Links Category Styling */
    .links-category {
        border: 1px solid #e0e0e0;
        border-radius: 8px;
        overflow: hidden;
        transition: all 0.3s ease;
    }

    .links-category:hover {
        box-shadow: 0 5px 15px rgba(0, 0, 0, 0.08);
    }

    .links-category-header {
        display: flex;
        align-items: center;
        padding: 15px 20px;
        background: #f8f9fa;
        cursor: pointer;
        transition: background 0.2s;
        gap: 15px;
    }

    .links-category-header:hover {
        background: #f1f3f4;
    }

    .links-category-icon {
        font-size: 24px;
        color: #f39c12;
    }

    .links-category-info {
        flex: 1;
    }

    .links-category-info h3 {
        margin: 0 0 3px 0;
        font-size: 18px;
        color: #2c3e50;
    }

    .links-count {
        font-size: 14px;
        color: #7f8c8d;
    }

    /* Admin actions positioned correctly */
    .category-admin-actions {
        margin-left: auto;
        margin-right: 10px;
    }

    .links-category-toggle {
        color: #95a5a6;
        transform: rotate(0deg);
        transition: transform 0.3s;
    }

    .links-category.links-open .links-category-toggle {
        transform: rotate(180deg);
    }

    /* Links Category Content - Simple show/hide */
    .links-category-content {
        padding: 15px 20px;
        border-top: 1px solid #e0e0e0;
        background: white;
    }

    /* Links Item */
    .links-item {
        display: flex;
        align-items: flex-start;
        padding: 15px;
        margin-bottom: 10px;
        border-radius: 6px;
        transition: background 0.2s;
        border: 1px solid #f0f0f0;
        justify-content: space-between;
    }

    .links-item:hover {
        background: #f8f9fa;
        border-color: #e0e0e0;
    }

    .links-item-content {
        display: flex;
        align-items: flex-start;
        flex: 1;
    }

    .links-icon {
        margin-right: 15px;
        font-size: 24px;
        margin-top: 2px;
    }

    .links-info {
        flex: 1;
    }

    .links-name {
        margin-bottom: 5px;
    }

    .links-name a {
        color: #2980b9;
        text-decoration: none;
        font-weight: 600;
        font-size: 16px;
    }

    .links-name a:hover {
        text-decoration: underline;
    }

    .links-description {
        color: #555;
        margin-bottom: 8px;
        line-height: 1.4;
        font-size: 14px;
    }

    .links-meta {
        font-size: 12px;
        color: #95a5a6;
    }

    /* File actions positioned on the right */
    .doc-actions {
        display: flex;
        gap: 4px;
        flex-shrink: 0;
    }

    /* No links message */
    .no-links {
        display: flex;
        flex-direction: column;
        align-items: center;
        padding: 40px 0;
        color: #95a5a6;
    }

    .no-links i {
        font-size: 48px;
        margin-bottom: 15px;
        opacity: 0.5;
    }

    /* Edit form styles */
    .link-edit-form {
        display: none;
        background: #f8f9fa;
        border: 1px solid #e0e0e0;
        border-radius: 6px;
        padding: 15px;
        margin-top: 10px;
        gap: 10px;
    }

    .link-edit-form.active {
        display: flex;
        flex-direction: column;
    }

    .link-edit-form .input-group {
        margin-bottom: 10px;
    }

    .link-edit-form .input-group label {
        font-size: 14px;
        font-weight: 600;
        margin-bottom: 5px;
    }

    .link-edit-form .input-group input,
    .link-edit-form .input-group select,
    .link-edit_form .input-group textarea {
        padding: 8px 10px;
        font-size: 14px;
    }

    .link-edit-form .form-actions {
        display: flex;
        gap: 10px;
        justify-content: flex-end;
        margin-top: 10px;
    }

    .link-edit-form .form-actions button {
        padding: 8px 16px;
        border: none;
        border-radius: 4px;
        cursor: pointer;
        font-size: 14px;
        font-weight: 600;
    }

    .link-edit-form .save-btn {
        background: #27ae60;
        color: white;
    }

    .link-edit-form .save-btn:hover {
        background: #229954;
    }

    .link-edit-form .cancel-btn {
        background: #95a5a6;
        color: white;
    }

    .link-edit-form .cancel-btn:hover {
        background: #7f8c8d;
    }

    /* Responsive adjustments */
    @media (max-width: 768px) {
        .links-header {
            flex-direction: column;
            align-items: flex-start;
        }

        .links-filter {
            width: 100%;
        }

        .links-filter select {
            flex: 1;
        }

        .links-item {
            flex-direction: column;
            text-align: center;
        }

        .links-icon {
            margin-right: 0;
            margin-bottom: 10px;
        }
    }
</style>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Only run if we're on the links page
        if (!document.getElementById('links')) {
            return;
        }

        // Simple category toggle functionality - no setTimeout
        const linksHeaders = document.querySelectorAll('.links-category-header');

        linksHeaders.forEach(header => {
            header.addEventListener('click', function(e) {
                // Don't toggle if clicking on admin actions
                if (e.target.closest('.category-admin-actions')) {
                    return;
                }
                
                const category = this.parentElement;
                const content = category.querySelector('.links-category-content');
                const folderIcon = header.querySelector('.links-category-icon i');
                const toggleIcon = header.querySelector('.links-category-toggle i');

                if (content.style.display === 'none' || content.style.display === '') {
                    // Show content
                    content.style.display = 'block';
                    folderIcon.className = 'fas fa-folder-open';
                    toggleIcon.style.transform = 'rotate(180deg)';
                    category.classList.add('links-open');
                } else {
                    // Hide content
                    content.style.display = 'none';
                    folderIcon.className = 'fas fa-folder';
                    toggleIcon.style.transform = 'rotate(0deg)';
                    category.classList.remove('links-open');
                }
            });
        });

        // Initialize all categories as closed - no setTimeout
        document.querySelectorAll('.links-category-content').forEach(content => {
            content.style.display = 'none';
        });
    });

    // Filter functionality
    function filterLinks() {
        const selectedCategory = document.getElementById('sort-links').value;
        const categories = document.querySelectorAll('.links-category');

        categories.forEach(category => {
            if (selectedCategory === 'all' || category.id === 'links-category-' + selectedCategory) {
                category.style.display = 'block';
            } else {
                category.style.display = 'none';
            }
        });
    }

    // Link management functions
    function editLink(filename, category) {
        if (event) event.stopPropagation();
        
        // Find the link item
        const linkItems = document.querySelectorAll('.links-item');
        let targetItem = null;
        
        linkItems.forEach(item => {
            const editBtn = item.querySelector('.edit-btn');
            if (editBtn && editBtn.getAttribute('onclick').includes(filename)) {
                targetItem = item;
            }
        });
        
        if (!targetItem) {
            console.error('Link item not found');
            return;
        }
        
        // Check if edit form already exists
        let editForm = targetItem.querySelector('.link-edit-form');
        if (editForm) {
            editForm.classList.toggle('active');
            return;
        }
        
        // Get current link data
        const linkInfo = targetItem.querySelector('.links-info');
        const currentTitle = linkInfo.querySelector('.links-name a').textContent;
        const currentUrl = linkInfo.querySelector('.links-name a').href;
        const currentDescription = linkInfo.querySelector('.links-description')?.textContent || '';
        
        // Create edit form
        editForm = document.createElement('div');
        editForm.className = 'link-edit-form active';
        editForm.innerHTML = `
            <div class="input-group">
                <label>Název odkazu</label>
                <input type="text" class="edit-title" value="${currentTitle}" required>
            </div>
            <div class="input-group">
                <label>URL adresa</label>
                <input type="url" class="edit-url" value="${currentUrl}" required>
            </div>
            <div class="input-group">
                <label>Kategorie</label>
                <select class="edit-category">
                    <option value="general" ${category === 'general' ? 'selected' : ''}>Obecné</option>
                    <option value="tools" ${category === 'tools' ? 'selected' : ''}>Nástroje</option>
                    <option value="courses" ${category === 'courses' ? 'selected' : ''}>Kurzy</option>
                    <option value="resources" ${category === 'resources' ? 'selected' : ''}>Zdroje</option>
                    <option value="partners" ${category === 'partners' ? 'selected' : ''}>Partneři</option>
                </select>
            </div>
            <div class="input-group">
                <label>Popis</label>
                <textarea class="edit-description" placeholder="Volitelný popis odkazu...">${currentDescription}</textarea>
            </div>
            <div class="form-actions">
                <button type="button" class="cancel-btn" onclick="cancelEditLink(this)">Zrušit</button>
                <button type="button" class="save-btn" onclick="saveEditLink('${filename}', '${category}', this)">Uložit</button>
            </div>
        `;
        
        targetItem.appendChild(editForm);
    }
    
    function cancelEditLink(button) {
        const editForm = button.closest('.link-edit-form');
        if (editForm) {
            editForm.remove();
        }
    }
    
    function saveEditLink(filename, originalCategory, button) {
        const editForm = button.closest('.link-edit-form');
        
        const title = editForm.querySelector('.edit-title').value.trim();
        const url = editForm.querySelector('.edit-url').value.trim();
        const category = editForm.querySelector('.edit-category').value;
        const description = editForm.querySelector('.edit-description').value.trim();
        
        if (!title || !url) {
            alert('Název a URL jsou povinné položky.');
            return;
        }
        
        // Validate URL
        try {
            new URL(url);
        } catch {
            alert('Neplatná URL adresa.');
            return;
        }
        
        // Create form data
        const formData = new FormData();
        formData.append('action', 'edit_link');
        formData.append('filename', filename);
        formData.append('original_category', originalCategory);
        formData.append('link_title', title);
        formData.append('link_url', url);
        formData.append('link_category', category);
        formData.append('link_description', description);
        
        // Send update request
        fetch('edit-link.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showStatusMessage('success', 'Odkaz byl úspěšně upraven.');
                // Reload the page to show changes
                setTimeout(() => {
                    window.location.reload();
                }, 1000);
            } else {
                showStatusMessage('error', data.message || 'Chyba při úpravě odkazu.');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showStatusMessage('error', 'Chyba při úpravě odkazu.');
        });
    }

    function editLinkCategory(category) {
        if (event) event.stopPropagation();
        console.log('Edit link category:', category);
        // Implement edit functionality similar to marketing/courses
    }

    function deleteLinkCategory(category) {
        if (event) event.stopPropagation();
        
        if (confirm(`Opravdu chcete smazat kategorii "${category}" a všechny odkazy v ní?`)) {
            console.log('Delete link category:', category);
            // Implement delete functionality
        }
    }

    function deleteLink(filename, category) {
        if (event) event.stopPropagation();
        
        if (confirm('Opravdu chcete smazat tento odkaz?')) {
            // Create form data
            const formData = new FormData();
            formData.append('action', 'delete_link');
            formData.append('filename', filename);
            formData.append('category', category);
            
            // Send delete request
            fetch('delete-link.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showStatusMessage('success', 'Odkaz byl úspěšně smazán.');
                    // Reload the page to show changes
                    setTimeout(() => {
                        window.location.reload();
                    }, 1000);
                } else {
                    showStatusMessage('error', data.message || 'Chyba při mazání odkazu.');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showStatusMessage('error', 'Chyba při mazání odkazu.');
            });
        }
    }

    function showStatusMessage(type, message) {
        const container = document.getElementById('status-messages');
        if (!container) {
            alert(message);
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
        `;
        div.innerHTML = `<i class="fas ${type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle'}"></i> ${message}`;

        container.innerHTML = '';
        container.appendChild(div);
        
        // Simple timeout without complex animations
        setTimeout(function() {
            if (div && div.parentNode) {
                div.remove();
            }
        }, 5000);
    }

    // Expose functions globally for links page
    if (document.getElementById('links')) {
        window.filterLinks = filterLinks;
        window.editLinkCategory = editLinkCategory;
        window.deleteLinkCategory = deleteLinkCategory;
        window.deleteLink = deleteLink;
        window.editLink = editLink;
        window.cancelEditLink = cancelEditLink;
        window.saveEditLink = saveEditLink;
        window.showStatusMessage = showStatusMessage;
    }
</script>