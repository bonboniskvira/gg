<!--Page Contacts -->
<?php

require_once __DIR__ . '/../../access.php';

?>


<div class="page-content" id="contacts">



    <div class="content-area">



        <div class="contacts-folder-container">



            <div class="contacts-header">



                <h2>Kontakty</h2>



                <div class="contacts-filter">



                    <label for="sort-contacts">Filtrovat podle:</label>



                    <select id="sort-contacts" onchange="filterContacts()">



                        <option value="all">Všechny kategorie</option>



                        <option value="management">Management</option>



                        <option value="central">Centrála</option>



                        <option value="sales">Obchodní oddělení</option>



                        <option value="support">Technická podpora</option>



                        <option value="hr">Personální oddělení</option>



                        <option value="suppliers">Dodavatelé</option>



                        <option value="other">Ostatní</option>



                    </select>



                </div>



            </div>







            <div class="contacts-folder-grid">



                <?php



                $contactsDir = __DIR__ . '/../../contacts/';







                // Create contacts directory if it doesn't exist



                if (!is_dir($contactsDir)) {



                    mkdir($contactsDir, 0777, true);
                }







                $categories = ['management', 'central', 'sales', 'support', 'hr', 'suppliers', 'other'];



                $categoryNames = [



                    'management' => 'Management',



                    'central' => 'Centrála',



                    'sales' => 'Obchodní oddělení',



                    'support' => 'Technická podpora',



                    'hr' => 'Personální oddělení',



                    'suppliers' => 'Dodavatelé',



                    'other' => 'Ostatní'



                ];







                $hasContacts = false;







                foreach ($categories as $category) {



                    $categoryDir = $contactsDir . $category . '/';







                    if (!is_dir($categoryDir)) {



                        mkdir($categoryDir, 0777, true);
                    }







                    $contactFiles = glob($categoryDir . '*.json');







                    if (!empty($contactFiles)) {



                        $hasContacts = true;



                        $folderId = 'category-' . $category;



                        $contactCount = count($contactFiles);







                        echo '<div class="contacts-folder contact-category" id="' . $folderId . '">';



                        echo '<div class="contacts-folder-header">';



                        echo '<div class="contacts-folder-icon"><i class="fas fa-users"></i></div>';



                        echo '<div class="contacts-folder-info">';



                        echo '<h3>' . $categoryNames[$category] . '</h3>';



                        echo '<span class="contacts-file-count">' . $contactCount . ' ' . ($contactCount == 1 ? 'kontakt' : ($contactCount >= 2 && $contactCount <= 4 ? 'kontakty' : 'kontaktů')) . '</span>';



                        echo '</div>';



                        echo '<div class="contacts-folder-toggle"><i class="fas fa-chevron-down"></i></div>';



                        echo '</div>';







                        echo '<div class="contacts-folder-content">';



                        echo '<div class="contacts-grid">';



                        foreach ($contactFiles as $contactFile) {



                            $contactData = json_decode(file_get_contents($contactFile), true);



                            $contactId = basename($contactFile, '.json');







                            if ($contactData) {



                                echo '<div class="contact-item" data-contact-id="' . $contactId . '" data-category="' . $category . '">';



                                echo '<div class="contact-icon">' . getContactIcon($contactData['position'], $contactData['color_override'] ?? '') . '</div>';



                                echo '<div class="contact-info">';



                                echo '<div class="contact-name">' . htmlspecialchars($contactData['name']) . '</div>';







                                if (!empty($contactData['position'])) {



                                    echo '<div class="contact-position">' . htmlspecialchars($contactData['position']) . '</div>';
                                }







                                echo '<div class="contact-details">';



                                if (!empty($contactData['phone'])) {



                                    echo '<div class="contact-detail"><i class="fas fa-phone"></i> <a href="tel:' . htmlspecialchars($contactData['phone']) . '">' . htmlspecialchars($contactData['phone']) . '</a></div>';
                                }



                                if (!empty($contactData['email'])) {



                                    echo '<div class="contact-detail"><i class="fas fa-envelope"></i> <a href="mailto:' . htmlspecialchars($contactData['email']) . '">' . htmlspecialchars($contactData['email']) . '</a></div>';
                                }



                                echo '</div>';







                                if (!empty($contactData['notes'])) {



                                    echo '<div class="contact-notes">' . htmlspecialchars($contactData['notes']) . '</div>';
                                }







                                echo '</div>';







                                // Admin actions - Add debugging



                                if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin') {



                                    echo '<div class="admin-actions">';



                                    echo '<button class="admin-btn edit-btn" onclick="editContact(\'' . $contactId . '\', \'' . $category . '\')" title="Upravit kontakt"><i class="fas fa-edit"></i></button>';



                                    echo '<button class="admin-btn delete-btn" onclick="deleteContact(\'' . $contactId . '\', \'' . $category . '\')" title="Smazat kontakt"><i class="fas fa-trash"></i></button>';



                                    echo '</div>';
                                } else {



                                    // Debug: Show what role the user has



                                    echo '<!-- User role: ' . ($_SESSION['role'] ?? 'not set') . ' -->';
                                }







                                echo '</div>';
                            }
                        }



                        echo '</div>';



                        echo '</div>';



                        echo '</div>';
                    }
                }







                if (!$hasContacts) {



                    echo '<div class="contacts-no-folders">



                            <i class="fas fa-address-book"></i>



                            <p>Zatím nebyly přidány žádné kontakty.</p>



                          </div>';
                }







                // Helper function to get appropriate icon based on position with optional color override



                function getContactIcon($position, $colorOverride = '')

                {



                    $position = strtolower($position);







                    // Determine icon type and default color based on position

                    if (strpos($position, 'ředitel') !== false || strpos($position, 'manager') !== false || strpos($position, 'vedoucí') !== false) {

                        $icon = 'fas fa-user-tie';

                        $defaultColor = '#e74c3c';
                    } elseif (strpos($position, 'obchod') !== false || strpos($position, 'prodej') !== false || strpos($position, 'sales') !== false) {

                        $icon = 'fas fa-handshake';

                        $defaultColor = '#f39c12';
                    } elseif (strpos($position, 'technik') !== false || strpos($position, 'podpora') !== false || strpos($position, 'support') !== false) {

                        $icon = 'fas fa-tools';

                        $defaultColor = '#3498db';
                    } elseif (strpos($position, 'hr') !== false || strpos($position, 'personál') !== false) {

                        $icon = 'fas fa-users';

                        $defaultColor = '#9b59b6';
                    } else {

                        $icon = 'fas fa-user';

                        $defaultColor = '#2ecc71';
                    }



                    // Use override color if provided, otherwise use default

                    $color = !empty($colorOverride) ? $colorOverride : $defaultColor;



                    return '<i class="' . $icon . '" style="color: ' . $color . ';"></i>';
                }



                ?>



            </div>



        </div>


        <?php if ($isAdmin): ?>
            <div class="contacts-upload-container">



                <h2>Přidat nový kontakt</h2>



                <form action="publish-contacts.php" method="post" class="contacts-upload-form">



                    <div class="contacts-input-group">



                        <label for="contact_name">Jméno a příjmení</label>



                        <input type="text" name="contact_name" id="contact_name" placeholder="Zadejte jméno a příjmení" required>



                    </div>



                    <div class="contacts-input-group">



                        <label for="contact_phone">Telefonní číslo</label>



                        <input type="tel" name="contact_phone" id="contact_phone" placeholder="Zadejte telefonní číslo">



                    </div>



                    <div class="contacts-input-group">



                        <label for="contact_email">E-mail</label>



                        <input type="email" name="contact_email" id="contact_email" placeholder="Zadejte e-mailovou adresu">



                    </div>



                    <div class="contacts-input-group">



                        <label for="contact_position">Pozice</label>



                        <input type="text" name="contact_position" id="contact_position" placeholder="Zadejte pozici">



                    </div>



                    <div class="contacts-input-group">



                        <label for="contact_color_override">Vlastní barva ikony (volitelné)</label>



                        <select name="contact_color_override" id="contact_color_override">



                            <option value="">Automatická (podle pozice)</option>



                            <option value="#e74c3c">🔴 Červená</option>



                            <option value="#3498db">🔵 Modrá</option>



                            <option value="#f39c12">🟠 Oranžová</option>



                            <option value="#9b59b6">🟣 Fialová</option>



                            <option value="#2ecc71">🟢 Zelená</option>



                            <option value="#34495e">⚫ Tmavě šedá</option>



                            <option value="#16a085">🟢 Tyrkysová</option>



                            <option value="#e67e22">🟤 Hnědá</option>



                        </select>



                    </div>



                    <div class="contacts-input-group">



                        <label for="contact_category">Kategorie</label>



                        <select name="contact_category" id="contact_category">



                            <option value="management">Management</option>



                            <option value="central">Centrála</option>



                            <option value="sales">Obchodní oddělení</option>



                            <option value="support">Technická podpora</option>



                            <option value="hr">Personální oddělení</option>



                            <option value="suppliers">Dodavatelé</option>



                            <option value="other">Ostatní</option>



                        </select>



                    </div>



                    <div class="contacts-input-group">



                        <label for="contact_notes">Poznámky</label>



                        <textarea name="contact_notes" id="contact_notes" placeholder="Volitelné poznámky..."></textarea>



                    </div>



                    <button type="submit" class="contacts-upload-btn">



                        <i class="fas fa-user-plus"></i> Uložit kontakt



                    </button>



                </form>



            </div>
        <?php endif; ?>



    </div>



</div>







<!-- Edit Contact Modal -->



<div id="editContactModal" class="modal" style="display: none;">



    <div class="modal-content">



        <div class="modal-header">



            <h2>Upravit kontakt</h2>



            <button class="modal-close" onclick="closeEditModal()">&times;</button>



        </div>



        <form id="editContactForm" onsubmit="saveContact(event)">



            <input type="hidden" id="editContactId" name="contact_id">



            <input type="hidden" id="editContactCategory" name="original_category">







            <div class="contacts-input-group">



                <label for="editContactName">Jméno a příjmení</label>



                <input type="text" id="editContactName" name="contact_name" required>



            </div>







            <div class="contacts-input-group">



                <label for="editContactPhone">Telefonní číslo</label>



                <input type="tel" id="editContactPhone" name="contact_phone">



            </div>







            <div class="contacts-input-group">



                <label for="editContactEmail">E-mail</label>



                <input type="email" id="editContactEmail" name="contact_email">



            </div>







            <div class="contacts-input-group">



                <label for="editContactPosition">Pozice</label>



                <input type="text" id="editContactPosition" name="contact_position">



            </div>



            <div class="contacts-input-group">



                <label for="editContactColorOverride">Vlastní barva ikony (volitelné)</label>



                <select id="editContactColorOverride" name="contact_color_override">



                    <option value="">Automatická (podle pozice)</option>



                    <option value="#e74c3c">🔴 Červená</option>



                    <option value="#3498db">🔵 Modrá</option>



                    <option value="#f39c12">🟠 Oranžová</option>



                    <option value="#9b59b6">🟣 Fialová</option>



                    <option value="#2ecc71">🟢 Zelená</option>



                    <option value="#34495e">⚫ Tmavě šedá</option>



                    <option value="#16a085">🟢 Tyrkysová</option>



                    <option value="#e67e22">🟤 Hnědá</option>



                </select>



            </div>







            <div class="contacts-input-group">



                <label for="editContactCategory">Kategorie</label>



                <select id="editContactCategorySelect" name="contact_category">



                    <option value="management">Management</option>



                    <option value="central">Centrála</option>



                    <option value="sales">Obchodní oddělení</option>



                    <option value="support">Technická podpora</option>



                    <option value="hr">Personální oddělení</option>



                    <option value="suppliers">Dodavatelé</option>



                    <option value="other">Ostatní</option>



                </select>



            </div>







            <div class="contacts-input-group">



                <label for="editContactNotes">Poznámky</label>



                <textarea id="editContactNotes" name="contact_notes"></textarea>



            </div>







            <div class="modal-actions">



                <button type="button" class="btn-secondary" onclick="closeEditModal()">Zrušit</button>



                <button type="submit" class="btn-primary">Uložit změny</button>



            </div>



        </form>



    </div>



</div>







<style>
    /* Contacts Page Styling */



    #contacts .content-area {



        display: flex;



        flex-direction: column;



        gap: 30px;



    }







    /* Upload Container */



    .contacts-upload-container {



        background: white;



        border-radius: 10px;



        padding: 25px;



        box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);



    }







    .contacts-upload-container h2 {



        color: #2c3e50;



        margin-top: 0;



        margin-bottom: 20px;



        font-size: 20px;



    }







    .contacts-upload-form {



        display: flex;



        flex-direction: column;



        gap: 15px;



    }







    .contacts-input-group {



        display: flex;



        flex-direction: column;



        gap: 5px;



    }







    .contacts-input-group label {



        font-weight: 600;



        color: #34495e;



    }







    .contacts-input-group input[type="text"],



    .contacts-input-group input[type="tel"],



    .contacts-input-group input[type="email"],



    .contacts-input-group select,



    .contacts-input-group textarea {



        width: 100%;



        padding: 12px;



        border: 1px solid #ddd;



        border-radius: 5px;



        font-size: 16px;



        box-sizing: border-box;



    }







    .contacts-input-group textarea {



        height: 80px;



        resize: vertical;



    }







    .contacts-upload-btn {



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







    .contacts-upload-btn:hover {



        background: #2980b9;



    }







    /* Folders Container */



    .contacts-folder-container {



        background: white;



        border-radius: 10px;



        padding: 25px;



        box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);



    }







    .contacts-header {



        display: flex;



        justify-content: space-between;



        align-items: center;



        margin-bottom: 20px;



        flex-wrap: wrap;



        gap: 15px;



    }







    .contacts-header h2 {



        color: #2c3e50;



        margin: 0;



        font-size: 20px;



    }







    .contacts-filter {



        display: flex;



        align-items: center;



        gap: 10px;



    }







    .contacts-filter label {



        font-weight: 600;



        color: #34495e;



    }







    .contacts-filter select {



        padding: 8px 12px;



        border: 1px solid #ddd;



        border-radius: 5px;



        font-size: 14px;



    }







    .contacts-folder-grid {



        display: flex;



        flex-direction: column;



        gap: 15px;



    }







    /* Folder Styling */



    .contacts-folder {



        border: 1px solid #e0e0e0;



        border-radius: 8px;



        overflow: hidden;



        transition: all 0.3s ease;



    }







    .contacts-folder:hover {



        box-shadow: 0 5px 15px rgba(0, 0, 0, 0.08);



    }







    .contacts-folder-header {



        display: flex;



        align-items: center;



        padding: 15px 20px;



        background: #f8f9fa;



        cursor: pointer;



        transition: background 0.2s;



    }







    .contacts-folder-header:hover {



        background: #f1f3f4;



    }







    .contacts-folder-icon {



        font-size: 24px;



        color: #3498db;



        margin-right: 15px;



    }







    .contacts-folder-info {



        flex: 1;



    }







    .contacts-folder-info h3 {



        margin: 0 0 3px 0;



        font-size: 18px;



        color: #2c3e50;



    }







    .contacts-file-count {



        font-size: 14px;



        color: #7f8c8d;



    }







    .contacts-folder-toggle {



        color: #95a5a6;



        transform: rotate(0deg);



        transition: transform 0.3s;



    }







    .contacts-folder.open .contacts-folder-toggle {



        transform: rotate(180deg);



    }







    /* Folder Content */



    .contacts-folder-content {



        max-height: 0;



        overflow: hidden;



        transition: max-height 0.4s ease-in-out, padding 0.4s ease-in-out;



        background: white;



        padding: 0 20px;



        box-sizing: border-box;



    }







    .contacts-folder.open .contacts-folder-content {



        max-height: 2000px;



        padding: 15px 20px;



        border-top: 1px solid #e0e0e0;



    }







    /* Contacts Grid */



    .contacts-grid {



        display: grid;



        grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));



        gap: 15px;



        padding: 10px 0;



    }







    /* Contact Item */



    .contact-item {



        display: flex;



        align-items: flex-start;



        padding: 15px;



        border-radius: 6px;



        transition: all 0.2s;



        border: 1px solid #f0f0f0;



        background: #fafafa;



        position: relative;



    }







    .contact-item:hover {



        background: white;



        border-color: #e0e0e0;



        transform: translateY(-2px);



        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);



    }







    .contact-icon {



        margin-right: 15px;



        font-size: 32px;



        margin-top: 2px;



    }







    .contact-info {



        flex: 1;



    }







    .contact-name {



        font-weight: 600;



        font-size: 16px;



        color: #2c3e50;



        margin-bottom: 5px;



    }







    .contact-position {



        color: #7f8c8d;



        font-size: 14px;



        margin-bottom: 10px;



        font-style: italic;



    }







    .contact-details {



        margin-bottom: 10px;



    }







    .contact-detail {



        display: flex;



        align-items: center;



        margin-bottom: 5px;



        font-size: 14px;



    }







    .contact-detail i {



        margin-right: 8px;



        width: 16px;



        color: #95a5a6;



    }







    .contact-detail a {



        color: #3498db;



        text-decoration: none;



    }







    .contact-detail a:hover {



        text-decoration: underline;



    }







    .contact-notes {



        background: #ecf0f1;



        padding: 8px 10px;



        border-radius: 4px;



        font-size: 13px;



        color: #555;



        line-height: 1.4;



        border-left: 3px solid #3498db;



    }







    /* No contacts message */



    .contacts-no-folders {



        display: flex;



        flex-direction: column;



        align-items: center;



        padding: 40px 0;



        color: #95a5a6;



    }







    .contacts-no-folders i {



        font-size: 48px;



        margin-bottom: 15px;



        opacity: 0.5;



    }







    /* Admin Actions */



    .contact-item {



        position: relative;



    }







    .admin-actions {



        position: absolute;



        top: 10px;



        right: 10px;



        display: flex;



        gap: 5px;



        opacity: 0;



        transition: opacity 0.2s;



    }







    .contact-item:hover .admin-actions {



        opacity: 1;



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



        max-width: 500px;



        max-height: 90vh;



        overflow-y: auto;



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



    }







    .modal-close:hover {



        color: #e74c3c;



    }







    #editContactForm {



        padding: 20px 25px;



    }







    .modal-actions {



        display: flex;



        gap: 10px;



        justify-content: flex-end;



        margin-top: 20px;



        padding-top: 15px;



        border-top: 1px solid #eee;



    }







    .btn-primary,

    .btn-secondary {



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







    /* Responsive adjustments */



    @media (max-width: 768px) {



        .contacts-header {



            flex-direction: column;



            align-items: flex-start;



        }







        .contacts-filter {



            width: 100%;



        }







        .contacts-filter select {



            flex: 1;



        }







        .contacts-grid {



            grid-template-columns: 1fr;



        }







        .contact-item {



            flex-direction: column;



            text-align: center;



        }







        .contact-icon {



            margin-right: 0;



            margin-bottom: 10px;



        }



    }
</style>







<script>
    document.addEventListener('DOMContentLoaded', function() {



        // Folder toggle functionality with contacts-specific selectors



        const folderHeaders = document.querySelectorAll('.contacts-folder-header');







        folderHeaders.forEach(header => {



            header.addEventListener('click', function() {



                const folder = this.parentElement;



                const content = folder.querySelector('.contacts-folder-content');



                const folderIcon = header.querySelector('.contacts-folder-icon i');



                const toggleIcon = header.querySelector('.contacts-folder-toggle i');







                console.log('Contact folder clicked!');







                if (content.style.maxHeight && content.style.maxHeight !== '0px') {



                    // Close folder



                    content.style.maxHeight = '0px';



                    content.style.padding = '0 20px';



                    content.style.borderTop = 'none';



                    folderIcon.className = 'fas fa-users';



                    toggleIcon.style.transform = 'rotate(0deg)';



                    folder.classList.remove('open');



                    console.log('Contact folder closed');



                } else {



                    // Open folder



                    content.style.maxHeight = content.scrollHeight + 50 + 'px';



                    content.style.padding = '15px 20px';



                    content.style.borderTop = '1px solid #e0e0e0';



                    folderIcon.className = 'fas fa-users';



                    toggleIcon.style.transform = 'rotate(180deg)';



                    folder.classList.add('open');



                    console.log('Contact folder opened');



                }



            });



        });







        // Initialize all folders as closed



        document.querySelectorAll('.contacts-folder-content').forEach(content => {



            content.style.maxHeight = '0px';



            content.style.padding = '0 20px';



            content.style.borderTop = 'none';



        });







        console.log('Contact folder system initialized with', folderHeaders.length, 'folders');



    });







    function filterContacts() {



        const selectedCategory = document.getElementById('sort-contacts').value;



        const categories = document.querySelectorAll('.contact-category');







        categories.forEach(category => {



            if (selectedCategory === 'all' || category.id === 'category-' + selectedCategory) {



                category.style.display = 'block';



            } else {



                category.style.display = 'none';



            }



        });



    }







    // Admin functions



    function editContact(contactId, category) {



        console.log('Edit contact called:', contactId, category);



        fetch(`edit-contact.php?action=get&id=${contactId}&category=${category}`)

            .then(response => {

                console.log('Response status:', response.status);

                return response.json();

            })

            .then(data => {

                console.log('Response data:', data);

                if (data.success) {



                    document.getElementById('editContactId').value = contactId;



                    document.getElementById('editContactCategory').value = category;



                    document.getElementById('editContactName').value = data.contact.name || '';



                    document.getElementById('editContactPhone').value = data.contact.phone || '';



                    document.getElementById('editContactEmail').value = data.contact.email || '';



                    document.getElementById('editContactPosition').value = data.contact.position || '';



                    document.getElementById('editContactColorOverride').value = data.contact.color_override || '';



                    document.getElementById('editContactCategorySelect').value = data.contact.category || category;



                    document.getElementById('editContactNotes').value = data.contact.notes || '';







                    document.getElementById('editContactModal').style.display = 'flex';



                } else {



                    alert('Chyba při načítání kontaktu: ' + data.message);



                }



            })



            .catch(error => {



                console.error('Fetch error:', error);



                alert('Chyba při načítání kontaktu');



            });



    }







    function closeEditModal() {



        document.getElementById('editContactModal').style.display = 'none';



    }







    function saveContact(event) {



        event.preventDefault();



        const formData = new FormData(event.target);



        fetch('edit-contact.php?action=save', {

                method: 'POST',

                body: formData

            })

            .then(response => response.json())

            .then(data => {

                if (data.success) {

                    alert('Kontakt byl úspěšně upraven');

                    closeEditModal();

                    location.reload(); // Refresh to show changes

                } else {

                    alert('Chyba při ukládání: ' + data.message);

                }

            })

            .catch(error => {

                console.error('Error:', error);

                alert('Chyba při ukládání kontaktu');

            });

    }







    function deleteContact(contactId, category) {



        if (confirm('Opravdu chcete smazat tento kontakt? Tato akce je nevratná.')) {



            fetch('edit-contact.php?action=delete', {



                    method: 'POST',



                    headers: {



                        'Content-Type': 'application/x-www-form-urlencoded',



                    },



                    body: `contact_id=${contactId}&category=${category}`



                })



                .then(response => response.json())



                .then(data => {



                    if (data.success) {



                        alert('Kontakt byl úspěšně smazán');



                        location.reload(); // Refresh to show changes



                    } else {



                        alert('Chyba při mazání: ' + data.message);



                    }



                })



                .catch(error => {



                    console.error('Error:', error);



                    alert('Chyba při mazání kontaktu');



                });



        }



    }







    // Close modal on outside click



    document.addEventListener('click', function(event) {



        const modal = document.getElementById('editContactModal');



        if (event.target === modal) {



            closeEditModal();



        }



    });
</script>