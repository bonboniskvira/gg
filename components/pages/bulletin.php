<?php
// Block direct access and enforce login
require_once __DIR__ . '/../../check-access.php';

// Database connection - Updated for Wedos hosting
$host = 'md396.wedos.net';
$dbname = 'd394711_main';
$username = 'w394711_main';
$password = 'mnpJtgaJ';

$error = '';
$success = '';

try {
    $pdo = new PDO("mysql:host=$host;port=3306;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Get all bulletin posts
    $stmt = $pdo->query("
        SELECT 
            bulletin_posts.*, 
            users.username 
        FROM 
            bulletin_posts 
        LEFT JOIN 
            users ON bulletin_posts.user_id = users.id 
        ORDER BY 
            pinned DESC, 
            created_at DESC
    ");
    $posts = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error = "Database error: " . $e->getMessage();
}

// Function to convert formatting tags to HTML
function formatBulletinText($text) {
    $text = preg_replace('/\[b\](.*?)\[\/b\]/s', '<strong>$1</strong>', $text);
    $text = preg_replace('/\[i\](.*?)\[\/i\]/s', '<em>$1</em>', $text);
    $text = preg_replace('/\[u\](.*?)\[\/u\]/s', '<u>$1</u>', $text);
    $text = preg_replace('/\[url=(.*?)\](.*?)\[\/url\]/s', '<a href="$1" target="_blank" rel="noopener noreferrer">$2</a>', $text);
    return $text;
}
?>

<div class="page-content" id="bulletin">
    <div class="content-area">
        <?php if ($error): ?>
            <div class="error-message"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="success-message"><?= htmlspecialchars($success) ?></div>
        <?php endif; ?>

        <div class="bulletin-posts">
            <h2>Příspěvky na nástěnce</h2>
            <?php if (empty($posts)): ?>
                <p class="no-posts">Zatím nejsou žádné příspěvky.</p>
            <?php else: ?>
                <?php foreach ($posts as $post): ?>
                    <div class="bulletin-post <?= $post['pinned'] ? 'pinned' : '' ?>">
                        <?php if ($post['pinned']): ?>
                            <div class="pinned-badge" title="Připnutý příspěvek">📌</div>
                        <?php endif; ?>

                        <h3 class="post-title"><?= htmlspecialchars($post['title']) ?></h3>

                        <div class="post-meta">
                            <span class="post-author">Přidal: <?= htmlspecialchars($post['username']) ?></span>
                            <span class="post-date"><?= date('d.m.Y H:i', strtotime($post['created_at'])) ?></span>
                        </div>

                        <div class="post-content"><?= formatBulletinText(htmlspecialchars(trim($post['content']))) ?></div>



                        <?php if (!empty($post['image'])): ?>
                            <div class="post-image">
                                <img src="serve_image.php?img=<?= urlencode($post['image']) ?>"
                                     alt="<?= htmlspecialchars($post['title']) ?>"
                                     onclick="openImageModal('serve_image.php?img=<?= urlencode($post['image']) ?>')"
                                     onerror="this.style.display='none'; this.nextElementSibling.style.display='block';"
                                     loading="lazy">
                                <div class="image-error" style="display: none; padding: 10px; background: #f8d7da; color: #721c24; border-radius: 5px; margin: 10px 0;">
                                    <p>⚠️ Obrázek se nepodařilo načíst</p>
                                </div>
                            </div>
                        <?php endif; ?>

                        <?php if (isAdmin() || $_SESSION['user_id'] == $post['user_id']): ?>
                            <div class="post-actions">
                                <?php if (isAdmin()): ?>
                                    <button type="button" class="edit-btn"
                                            data-post-id="<?= $post['id'] ?>"
                                            data-post-title="<?= htmlspecialchars($post['title'], ENT_QUOTES) ?>"
                                            data-post-content="<?= htmlspecialchars($post['content'], ENT_QUOTES) ?>"
                                            data-post-pinned="<?= $post['pinned'] ?>"
                                            onclick="editPost(this)">
                                        Upravit
                                    </button>
                                <?php endif; ?>

                                <form action="delete-bulletin.php" method="post" class="inline-form">
                                    <input type="hidden" name="post_id" value="<?= $post['id'] ?>">
                                    <button type="submit" class="delete-btn" onclick="return confirm('Opravdu chcete smazat tento příspěvek?')">
                                        Smazat
                                    </button>
                                </form>

                                <?php if (isAdmin()): ?>
                                    <form action="toggle-pin.php" method="post" class="inline-form">
                                        <input type="hidden" name="post_id" value="<?= $post['id'] ?>">
                                        <input type="hidden" name="pinned" value="<?= $post['pinned'] ? '0' : '1' ?>">
                                        <button type="submit" class="pin-btn">
                                            <?= $post['pinned'] ? 'Odepnout' : 'Připnout' ?>
                                        </button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <?php if (canUpload()): ?>
            <div class="bulletin-form">
                <h2>Přidat nový příspěvek</h2>
                <form action="publish-bulletin.php" method="post" enctype="multipart/form-data">
                    <div class="form-group">
                        <label for="post-title">Titulek</label>
                        <input type="text" id="post-title" name="title" required maxlength="100" placeholder="Titulek příspěvku" class="full-width">
                    </div>

                    <div class="form-group">
                        <label>Obrázek (volitelný)</label>
                        <div class="upload-area" onclick="document.getElementById('post-image').click()">
                            <label for="post-image" class="custom-file-button">Vyberte obrázek</label>
                            <input type="file" id="post-image" name="image" accept="image/*" class="hidden-file-input">
                            <div id="selected-image-info" class="selected-files-info"></div>
                        </div>

                        <div class="file-help">
                            <small>Podporované formáty: JPG, PNG, GIF. Maximální velikost: 5MB</small>
                        </div>

                        <div id="image-preview" class="image-preview" style="display: none; margin-top: 15px; text-align: center;">
                            <img id="preview-img" src="" alt="Náhled obrázku" style="max-width: 100%; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1);">
                            <br>
                            <button type="button" class="btn-secondary" style="margin-top: 10px;" onclick="removeImagePreview()">Odebrat obrázek</button>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="post-content">Obsah</label>
                        <div class="formatting-toolbar">
                            <button type="button" onclick="insertFormat('[b]', '[/b]')" title="Tučný text"><strong>B</strong></button>
                            <button type="button" onclick="insertFormat('[i]', '[/i]')" title="Kurzíva"><em>I</em></button>
                            <button type="button" onclick="insertFormat('[u]', '[/u]')" title="Podtržený text"><u>U</u></button>
                            <button type="button" onclick="insertLink()" title="Odkaz">🔗</button>
                        </div>
                        <textarea id="post-content" name="content" required rows="5" placeholder="Text příspěvku..." class="full-width" oninput="updatePreview()"></textarea>
                        <div class="formatting-help">
                            <small>Formátování: [b]tučný[/b], [i]kurzíva[/i], [u]podtržený[/u], [url=odkaz]text[/url]</small>
                        </div>
                        <div class="preview-container">
                            <label>Náhled:</label>
                            <div id="post-preview" class="post-preview"></div>
                        </div>
                    </div>

                    <?php if (isAdmin()): ?>
                        <div class="form-group checkbox">
                            <input type="checkbox" id="post-pinned" name="pinned" value="1">
                            <label for="post-pinned">Připnout na začátek</label>
                        </div>
                    <?php endif; ?>

                    <div class="form-group">
                        <button type="submit" class="upload-btn">Publikovat příspěvek</button>
                    </div>
                </form>
            </div>
        <?php endif; ?>
    </div>
</div>

<div id="imageModal" class="modal" style="display: none;" onclick="closeImageModal()">
    <div class="modal-content image-modal-content">
        <img id="modalImage" src="" alt="Zvětšený obrázek">
        <button class="modal-close" onclick="closeImageModal()">&times;</button>
    </div>
</div>

<div id="editPostModal" class="modal" style="display: none;">
    <div class="modal-content">
        <div class="modal-header">
            <h2>Upravit příspěvek</h2>
            <button class="modal-close" onclick="closeEditModal()">&times;</button>
        </div>
        <form id="editPostForm" action="edit-bulletin.php" method="post" enctype="multipart/form-data">
            <input type="hidden" id="editPostId" name="post_id">
            <div class="form-group">
                <label for="editPostTitle">Titulek</label>
                <input type="text" id="editPostTitle" name="title" required maxlength="100" class="full-width">
            </div>

            <div class="form-group">
                <label for="editPostImage">Změnit obrázek (volitelný)</label>
                <input type="file" id="editPostImage" name="image" accept="image/*" class="full-width">
                <div class="file-help">
                    <small>Ponechte prázdné pro zachování současného obrázku</small>
                </div>
            </div>

            <div class="form-group">
                <label for="editPostContent">Obsah</label>
                <div class="formatting-toolbar">
                    <button type="button" onclick="insertFormatEdit('[b]', '[/b]')" title="Tučný text"><strong>B</strong></button>
                    <button type="button" onclick="insertFormatEdit('[i]', '[/i]')" title="Kurzíva"><em>I</em></button>
                    <button type="button" onclick="insertFormatEdit('[u]', '[/u]')" title="Podtržený text"><u>U</u></button>
                    <button type="button" onclick="insertLinkEdit()" title="Odkaz">🔗</button>
                </div>
                <textarea id="editPostContent" name="content" required rows="5" class="full-width"></textarea>
                <div class="formatting-help">
                    <small>Formátování: [b]tučný[/b], [i]kurzíva[/i], [u]podtržený[/u], [url=odkaz]text[/url]</small>
                </div>
            </div>

            <div class="form-group checkbox">
                <input type="checkbox" id="editPostPinned" name="pinned" value="1">
                <label for="editPostPinned">Připnout na začátek</label>
            </div>

            <div class="modal-actions">
                <button type="button" class="btn-secondary" onclick="closeEditModal()">Zrušit</button>
                <button type="submit" class="btn-primary">Uložit změny</button>
            </div>
        </form>
    </div>
</div>

<style>
    .modal { position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(0, 0, 0, 0.5); display: flex; justify-content: center; align-items: center; }
    .modal-content { background: white; border-radius: 10px; width: 90%; max-width: 600px; max-height: 90vh; overflow-y: auto; }
    .modal-header { display: flex; justify-content: space-between; align-items: center; padding: 20px 25px 15px; border-bottom: 1px solid #eee; }
    .modal-header h2 { margin: 0; color: #2c3e50; }
    .modal-close { background: none; border: none; font-size: 24px; cursor: pointer; color: #95a5a6; padding: 0; width: 30px; height: 30px; display: flex; align-items: center; justify-content: center; }
    .modal-close:hover { color: #e74c3c; }
    #editPostForm { padding: 20px 25px; }
    #editPostForm .form-group { margin-bottom: 15px; }
    #editPostForm .full-width, #editPostForm textarea, #editPostForm input[type="text"] { width: 100%; box-sizing: border-box; }
    #editPostContent { width: 100%; min-height: 120px; resize: vertical; box-sizing: border-box; }
    .modal-actions { display: flex; gap: 10px; justify-content: flex-end; margin-top: 20px; padding-top: 15px; border-top: 1px solid #eee; }
    .btn-primary, .btn-secondary { padding: 10px 20px; border: none; border-radius: 5px; cursor: pointer; font-size: 14px; font-weight: 600; transition: background 0.2s; }
    .btn-primary { background: #3498db; color: white; }
    .btn-primary:hover { background: #2980b9; }
    .btn-secondary { background: #95a5a6; color: white; }
    .btn-secondary:hover { background: #7f8c8d; }
    .formatting-toolbar { margin-bottom: 5px; display: flex; gap: 5px; }
    .formatting-toolbar button { background: #f8f9fa; border: 1px solid #ddd; border-radius: 3px; padding: 5px 8px; cursor: pointer; font-size: 14px; transition: background 0.2s; }
    .formatting-toolbar button:hover { background: #e9ecef; }
    .formatting-help { margin-top: 5px; }
    .formatting-help small { color: #6c757d; font-style: italic; }
    .bulletin-form { width: 100%; max-width: 100%; overflow: hidden; box-sizing: border-box; }
    .bulletin-form textarea, .bulletin-form input[type="text"] { width: 100%; max-width: 100%; box-sizing: border-box; word-wrap: break-word; word-break: break-word; overflow-wrap: break-word; white-space: pre-wrap; resize: vertical; }
    #post-content { min-height: 120px; line-height: 1.5; padding: 12px; border: 2px solid #ddd; border-radius: 8px; font-family: inherit; font-size: 14px; transition: border-color 0.3s ease; }
    #post-content:focus { border-color: #3498db; outline: none; box-shadow: 0 0 0 3px rgba(52, 152, 219, 0.1); }
    .preview-container { margin-top: 15px; padding: 15px; background: #f8f9fa; border-radius: 8px; border: 1px solid #e9ecef; }
    .preview-container label { font-weight: 600; color: #495057; margin-bottom: 8px; display: block; }
    .post-preview { background: white; padding: 15px; border-radius: 6px; border: 1px solid #dee2e6; min-height: 50px; word-wrap: break-word; word-break: break-word; overflow-wrap: break-word; white-space: pre-wrap; line-height: 1.6; font-size: 14px; color: #495057; }
    .form-group { width: 100%; max-width: 100%; margin-bottom: 20px; overflow: hidden; box-sizing: border-box; }
    .form-group label { display: block; margin-bottom: 8px; font-weight: 600; color: #495057; word-wrap: break-word; }
    .full-width { width: 100% !important; max-width: 100% !important; box-sizing: border-box !important; }
    .post-content { word-wrap: break-word; word-break: break-word; overflow-wrap: break-word; white-space: normal; line-height: 1.6; margin: 15px 0; max-width: 100%; overflow: hidden; }
    #editPostContent { width: 100%; min-height: 120px; resize: vertical; box-sizing: border-box; word-wrap: break-word; word-break: break-word; overflow-wrap: break-word; white-space: pre-wrap; line-height: 1.5; padding: 12px; border: 2px solid #ddd; border-radius: 8px; font-family: inherit; font-size: 14px; }
    #editPostContent:focus { border-color: #3498db; outline: none; box-shadow: 0 0 0 3px rgba(52, 152, 219, 0.1); }
    #editPostTitle { word-wrap: break-word; word-break: break-word; overflow-wrap: break-word; }
    #bulletin, #bulletin .content-area { width: 100%; max-width: 100%; overflow-x: hidden; box-sizing: border-box; }
    .bulletin-post { width: 100%; max-width: 100%; overflow: hidden; box-sizing: border-box; word-wrap: break-word; }
    .post-title { word-wrap: break-word; word-break: break-word; overflow-wrap: break-word; line-height: 1.4; }
    .image-modal-content { background: transparent; border: none; max-width: 95vw; max-height: 95vh; position: relative; display: flex; align-items: center; justify-content: center; }
    .image-modal-content img { max-width: 100%; max-height: 95vh; width: auto; height: auto; object-fit: contain; border-radius: 8px; box-shadow: 0 8px 32px rgba(0, 0, 0, 0.3); }
    .image-modal-content .modal-close { position: absolute; top: 15px; right: 15px; background: rgba(0, 0, 0, 0.7); color: white; border: none; width: 45px; height: 45px; border-radius: 50%; font-size: 22px; cursor: pointer; display: flex; align-items: center; justify-content: center; transition: background 0.2s; z-index: 1001; }
    .image-modal-content .modal-close:hover { background: rgba(0, 0, 0, 0.9); }
    .post-image::after { content: "🔍"; position: absolute; top: 10px; right: 10px; background: rgba(0, 0, 0, 0.7); color: white; padding: 5px 8px; border-radius: 15px; font-size: 12px; opacity: 0; transition: opacity 0.2s; pointer-events: none; }
    .post-image { position: relative; display: inline-block; }
    .post-image:hover::after { opacity: 1; }

    @media (max-width: 768px) {
        .bulletin-form { padding: 15px; }
        #post-content, #editPostContent { font-size: 16px; min-height: 100px; }
        .formatting-toolbar { gap: 3px; }
        .formatting-toolbar button { padding: 8px 6px; font-size: 12px; min-width: 28px; }
        .preview-container { padding: 10px; }
        .post-preview { padding: 10px; font-size: 13px; }
        .post-image img { max-width: 250px; max-height: 150px; }
        .image-modal-content img { max-width: 90vw; max-height: 80vh; }
        .image-modal-content .modal-close { top: 10px; right: 10px; width: 35px; height: 35px; font-size: 18px; }
    }
</style>

<script>
    function editPost(button) {
        const postId = button.getAttribute('data-post-id');
        const title = button.getAttribute('data-post-title');
        const content = button.getAttribute('data-post-content');
        const pinned = button.getAttribute('data-post-pinned');

        document.getElementById('editPostId').value = postId;
        document.getElementById('editPostTitle').value = title;
        document.getElementById('editPostContent').value = content;
        document.getElementById('editPostPinned').checked = (pinned == 1);
        document.getElementById('editPostModal').style.display = 'flex';
    }

    function closeEditModal() {
        document.getElementById('editPostModal').style.display = 'none';
    }

    document.addEventListener('click', function (event) {
        const modal = document.getElementById('editPostModal');
        if (event.target === modal) closeEditModal();
    });

    function insertFormat(openTag, closeTag) {
        const textarea = document.getElementById('post-content');
        const start = textarea.selectionStart;
        const end = textarea.selectionEnd;
        const selectedText = textarea.value.substring(start, end);

        if (selectedText.length === 0) {
            textarea.value = textarea.value.substring(0, start) + openTag + closeTag + textarea.value.substring(end);
            const newCursorPos = start + openTag.length;
            textarea.setSelectionRange(newCursorPos, newCursorPos);
        } else {
            textarea.value = textarea.value.substring(0, start) + openTag + selectedText + closeTag + textarea.value.substring(end);
            textarea.setSelectionRange(start + openTag.length, start + openTag.length + selectedText.length);
        }
        textarea.focus();
    }

    function insertFormatEdit(openTag, closeTag) {
        const textarea = document.getElementById('editPostContent');
        const start = textarea.selectionStart;
        const end = textarea.selectionEnd;
        const selectedText = textarea.value.substring(start, end);

        if (selectedText.length === 0) {
            textarea.value = textarea.value.substring(0, start) + openTag + closeTag + textarea.value.substring(end);
            textarea.setSelectionRange(start + openTag.length, start + openTag.length);
        } else {
            textarea.value = textarea.value.substring(0, start) + openTag + selectedText + closeTag + textarea.value.substring(end);
            textarea.setSelectionRange(start + openTag.length, start + openTag.length + selectedText.length);
        }
        textarea.focus();
    }

    function insertLink() {
        const textarea = document.getElementById('post-content');
        let url = prompt('Zadejte URL odkazu:', 'https://');
        if (url === null) return;
        if (!url.startsWith('http')) url = 'https://' + url;

        const start = textarea.selectionStart;
        const end = textarea.selectionEnd;
        let linkText = textarea.value.substring(start, end) || prompt('Zadejte text odkazu:', url);
        if (linkText === null) linkText = url;

        const linkTag = `[url=${url}]${linkText}[/url]`;
        textarea.value = textarea.value.substring(0, start) + linkTag + textarea.value.substring(end);
        updatePreview();
    }

    function insertLinkEdit() {
        const textarea = document.getElementById('editPostContent');
        let url = prompt('Zadejte URL odkazu:', 'https://');
        if (url === null) return;
        if (!url.startsWith('http')) url = 'https://' + url;

        const start = textarea.selectionStart;
        const end = textarea.selectionEnd;
        let linkText = textarea.value.substring(start, end) || prompt('Zadejte text odkazu:', url);
        if (linkText === null) linkText = url;

        textarea.value = textarea.value.substring(0, start) + `[url=${url}]${linkText}[/url]` + textarea.value.substring(end);
    }

    document.addEventListener('keydown', function (e) {
        if (!e.ctrlKey) return;
        const target = (document.activeElement.id === 'post-content') ? 'post-content' : (document.activeElement.id === 'editPostContent' ? 'editPostContent' : null);
        if (!target) return;

        const tags = { 'b': ['[b]', '[/b]'], 'i': ['[i]', '[/i]'], 'u': ['[u]', '[/u]'] };
        if (tags[e.key]) {
            e.preventDefault();
            target === 'post-content' ? insertFormat(tags[e.key][0], tags[e.key][1]) : insertFormatEdit(tags[e.key][0], tags[e.key][1]);
        }
    });

    function updatePreview() {
        const text = document.getElementById('post-content').value;
        document.getElementById('post-preview').innerHTML = text
            .replace(/\[b\](.*?)\[\/b\]/g, '<strong>$1</strong>')
            .replace(/\[i\](.*?)\[\/i\]/g, '<em>$1</em>')
            .replace(/\[u\](.*?)\[\/u\]/g, '<u>$1</u>')
            .replace(/\[url=(.*?)\](.*?)\[\/url\]/g, '<a href="$1" target="_blank">$2</a>');
    }

    document.getElementById('post-image')?.addEventListener('change', function (e) {
        const file = e.target.files[0];
        if (file) {
            const reader = new FileReader();
            reader.onload = (ex) => {
                document.getElementById('preview-img').src = ex.target.result;
                document.getElementById('image-preview').style.display = 'block';
            };
            reader.readAsDataURL(file);
        } else removeImagePreview();
    });

    function removeImagePreview() {
        document.getElementById('image-preview').style.display = 'none';
        document.getElementById('post-image').value = '';
    }

    function openImageModal(imageSrc) {
        document.getElementById('modalImage').src = imageSrc;
        document.getElementById('imageModal').style.display = 'flex';
        document.body.style.overflow = 'hidden';
    }

    function closeImageModal() {
        document.getElementById('imageModal').style.display = 'none';
        document.body.style.overflow = 'auto';
    }

    document.addEventListener('keydown', (e) => { if (e.key === 'Escape') closeImageModal(); });
</script>