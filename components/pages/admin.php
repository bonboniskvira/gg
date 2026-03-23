<?php
// Block direct access and enforce login
require_once __DIR__ . '/../../check-access.php';

// Check if user is admin, if not redirect
if (!isAdmin()) {
    echo '<div class="access-denied">
            <h2>Přístup odepřen</h2>
            <p>Nemáte oprávnění k přístupu na tuto stránku.</p>
          </div>';
    return;
}

// --- LOG ACCESS ---
try {
    $logPdo = new PDO("mysql:host=md396.wedos.net;port=3306;dbname=d394711_main", "w394711_main", "mnpJtgaJ");
    $logPdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Get IP address (Handle potential proxies)
    $ip = $_SERVER['REMOTE_ADDR'];
    if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
        $ip = $_SERVER['HTTP_CLIENT_IP'];
    } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
    }

    $stmtLog = $logPdo->prepare("INSERT INTO admin_logs (user_id, username, role, ip_address) VALUES (?, ?, ?, ?)");
    $stmtLog->execute([
        $_SESSION['user_id'] ?? 0,
        $_SESSION['username'] ?? 'Unknown',
        $_SESSION['role'] ?? 'unknown',
        $ip
    ]);
} catch (Exception $e) {
    // Silent fail
}
// --- END LOG ACCESS ---

// Handle user creation
$createUserMessage = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_user'])) {

    $username = trim($_POST['username']);

    $password = $_POST['password'];

    $email = trim($_POST['email']);

    $role = $_POST['role'];

    // Validate inputs

    $errors = [];

    if (empty($username)) {
        $errors[] = "Uživatelské jméno je povinné.";
    }

    if (empty($password)) {
        $errors[] = "Heslo je povinné.";
    } elseif (strlen($password) < 6) {
        $errors[] = "Heslo musí mít alespoň 6 znaků.";
    }

    if (empty($email)) {
        $errors[] = "Email je povinný.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Neplatný formát emailu.";
    }

    if (empty($errors)) {
        // Connect to database - Updated for Wedos hosting
        $pdo = new PDO("mysql:host=md396.wedos.net;port=3306;dbname=d394711_main", "w394711_main", "mnpJtgaJ");
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // Check if username already exists
        $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ?");
        $stmt->execute([$username]);

        if ($stmt->rowCount() > 0) {
            $createUserMessage = '<div class="error-message">Toto uživatelské jméno již existuje.</div>';
        } else {
            // Create new user
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("INSERT INTO users (username, password, email, role) VALUES (?, ?, ?, ?)");
            $result = $stmt->execute([$username, $hashedPassword, $email, $role]);

            if ($result) {
                $createUserMessage = '<div class="success-message">Uživatel byl úspěšně vytvořen.</div>';
            } else {
                $createUserMessage = '<div class="error-message">Nepodařilo se vytvořit uživatele.</div>';
            }
        }
    } else {
        $createUserMessage = '<div class="error-message">' . implode('<br>', $errors) . '</div>';
    }
}

// Handle role change
$updateRoleMessage = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_role'])) {

    $userId = (int)$_POST['user_id'];
    $newRole = $_POST['new_role'];

    // Connect to database - Updated for Wedos hosting
    $pdo = new PDO("mysql:host=md396.wedos.net;port=3306;dbname=d394711_main", "w394711_main", "mnpJtgaJ");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $stmt = $pdo->prepare("UPDATE users SET role = ? WHERE id = ?");
    $result = $stmt->execute([$newRole, $userId]);

    if ($result) {
        $updateRoleMessage = '<div class="success-message">Role uživatele byla aktualizována.</div>';
    } else {
        $updateRoleMessage = '<div class="error-message">Nepodařilo se aktualizovat roli.</div>';
    }
}

// Handle user deletion
$deleteUserMessage = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_user'])) {

    $userId = (int)$_POST['user_id'];

    // Make sure admin cannot delete themselves
    if ($userId == $_SESSION['user_id']) {
        $deleteUserMessage = '<div class="error-message">Nemůžete smazat svůj vlastní účet.</div>';
    } else {
        // Connect to database - Updated for Wedos hosting
        $pdo = new PDO("mysql:host=md396.wedos.net;port=3306;dbname=d394711_main", "w394711_main", "mnpJtgaJ");
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
        $result = $stmt->execute([$userId]);

        if ($result) {
            $deleteUserMessage = '<div class="success-message">Uživatel byl smazán.</div>';
        } else {
            $deleteUserMessage = '<div class="error-message">Nepodařilo se smazat uživatele.</div>';
        }
    }
}

// Handle password reset
$resetPasswordMessage = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reset_password'])) {

    $userId = (int)$_POST['user_id'];
    $newPassword = $_POST['new_password'];

    // Validate password
    if (empty($newPassword)) {
        $resetPasswordMessage = '<div class="error-message">Heslo je povinné.</div>';
    } elseif (strlen($newPassword) < 6) {
        $resetPasswordMessage = '<div class="error-message">Heslo musí mít alespoň 6 znaků.</div>';
    } else {
        // Connect to database - Updated for Wedos hosting
        $pdo = new PDO("mysql:host=md396.wedos.net;port=3306;dbname=d394711_main", "w394711_main", "mnpJtgaJ");
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // Hash the new password
        $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
        $result = $stmt->execute([$hashedPassword, $userId]);

        if ($result) {
            $resetPasswordMessage = '<div class="success-message">Heslo bylo úspěšně změněno.</div>';
        } else {
            $resetPasswordMessage = '<div class="error-message">Nepodařilo se změnit heslo.</div>';
        }
    }
}

// Handle email update
$updateEmailMessage = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_email'])) {
    $userId = (int)$_POST['user_id'];
    $newEmail = trim($_POST['new_email']);

    // Validate email
    if (empty($newEmail)) {
        $updateEmailMessage = '<div class="error-message">Email je povinný.</div>';
    } elseif (!filter_var($newEmail, FILTER_VALIDATE_EMAIL)) {
        $updateEmailMessage = '<div class="error-message">Neplatný formát emailu.</div>';
    } else {
        // Connect to database - Updated for Wedos hosting
        $pdo = new PDO("mysql:host=md396.wedos.net;port=3306;dbname=d394711_main", "w394711_main", "mnpJtgaJ");
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // Check if email already exists for another user
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
        $stmt->execute([$newEmail, $userId]);

        if ($stmt->rowCount() > 0) {
            $updateEmailMessage = '<div class="error-message">Tento email již používá jiný uživatel.</div>';
        } else {
            $stmt = $pdo->prepare("UPDATE users SET email = ? WHERE id = ?");
            $result = $stmt->execute([$newEmail, $userId]);

            if ($result) {
                $updateEmailMessage = '<div class="success-message">Email byl úspěšně aktualizován.</div>';
            } else {
                $updateEmailMessage = '<div class="error-message">Nepodařilo se aktualizovat email.</div>';
            }
        }
    }
}

// Get all users - Updated for Wedos hosting
try {
    $pdo = new PDO("mysql:host=md396.wedos.net;port=3306;dbname=d394711_main", "w394711_main", "mnpJtgaJ");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $stmt = $pdo->query("SELECT id, username, email, role, created_at FROM users ORDER BY created_at DESC");
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Fetch logs (nested try-catch to protect user list)
    $accessLogs = [];
    try {
        $stmtLogs = $pdo->query("SELECT * FROM admin_logs ORDER BY accessed_at DESC LIMIT 50");
        $accessLogs = $stmtLogs->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $logEx) {
        // Table might not exist yet or other error, just ignore
    }

} catch (PDOException $e) {
    $users = [];
    $accessLogs = [];
    echo '<div class="error-message">Nepodařilo se načíst uživatele: ' . $e->getMessage() . '</div>';
}
?>







<!--Page Admin User Management-->



<div class="page-content" id="admin">







    <div class="content-area">



        <div class="admin-panel">



            <!-- Create new user form -->



            <div class="admin-section">



                <h2>Vytvořit nového uživatele</h2>



                <?php echo $createUserMessage; ?>



                <form method="post" class="admin-form">



                    <div class="form-group">



                        <label for="username">Uživatelské jméno:</label>



                        <input type="text" id="username" name="username" required>



                    </div>






                    <div class="form-group">



                        <label for="password">Heslo:</label>



                        <input type="password" id="password" name="password" required>



                    </div>






                    <div class="form-group">



                        <label for="email">Email:</label>



                        <input type="email" id="email" name="email" required>



                    </div>






                    <div class="form-group">



                        <label for="role">Role:</label>



                        <select id="role" name="role">



                            <option value="user">Uživatel (Makléř)</option>



                            <option value="poradce">Poradce</option>



                            <option value="manager">Manažer</option>



                            <option value="admin">Administrátor</option>



                        </select>



                    </div>






                    <button type="submit" name="create_user" class="btn btn-primary">Vytvořit uživatele</button>



                </form>



            </div>






            <!-- User management table -->



            <div class="admin-section">



                <h2>Seznam uživatelů</h2>



                <?php



                echo $updateRoleMessage;



                echo $deleteUserMessage;



                echo $updateEmailMessage;



                ?>







                <?php if (count($users) > 0): ?>



                    <div class="user-table-container">



                        <table class="user-table">



                            <thead>



                                <tr>



                                    <th>ID</th>



                                    <th>Uživatelské jméno</th>



                                    <th>Email</th>



                                    <th>Role</th>



                                    <th>Vytvořeno</th>



                                    <th>Akce</th>



                                </tr>



                            </thead>



                            <tbody>



                                <?php foreach ($users as $user): ?>



                                    <tr>



                                        <td><?php echo $user['id']; ?></td>



                                        <td><?php echo htmlspecialchars($user['username']); ?></td>



                                        <td><?php echo htmlspecialchars($user['email']); ?></td>



                                        <td>



                                            <form method="post" class="inline-form role-form">



                                                <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">



                                                <select name="new_role" onchange="this.form.submit()" <?php echo ($user['id'] == $_SESSION['user_id']) ? 'disabled' : ''; ?>>



                                                    <option value="user" <?php echo ($user['role'] === 'user') ? 'selected' : ''; ?>>Uživatel (Makléř)</option>



                                                    <option value="poradce" <?php echo ($user['role'] === 'poradce') ? 'selected' : ''; ?>>Poradce</option>



                                                    <option value="manager" <?php echo ($user['role'] === 'manager') ? 'selected' : ''; ?>>Manažer</option>



                                                    <option value="admin" <?php echo ($user['role'] === 'admin') ? 'selected' : ''; ?>>Administrátor</option>



                                                </select>



                                                <input type="hidden" name="update_role" value="1">



                                            </form>



                                        </td>



                                        <td><?php echo date('d.m.Y H:i', strtotime($user['created_at'])); ?></td>



                                        <td>



                                            <div class="action-buttons">



                                                <button type="button" class="btn btn-info btn-small" onclick="showEmailForm(<?php echo $user['id']; ?>, '<?php echo htmlspecialchars($user['email'], ENT_QUOTES); ?>')">Změnit email</button>



                                                <?php if ($user['id'] != $_SESSION['user_id']): ?>



                                                    <button type="button" class="btn btn-warning btn-small" onclick="showPasswordForm(<?php echo $user['id']; ?>)">Změnit heslo</button>



                                                    <form method="post" class="inline-form delete-form" onsubmit="return confirm('Opravdu chcete smazat tohoto uživatele?');">



                                                        <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">



                                                        <button type="submit" name="delete_user" class="btn btn-danger btn-small">Smazat</button>



                                                    </form>



                                                <?php else: ?>



                                                    <span class="current-user-badge">Aktuální uživatel</span>



                                                <?php endif; ?>



                                            </div>



                                        </td>



                                    </tr>



                                <?php endforeach; ?>



                            </tbody>



                        </table>



                    </div>



                <?php else: ?>



                    <p>Žádní uživatelé nebyli nalezeni.</p>



                <?php endif; ?>



            </div>


            <!-- Acces Logs Table -->
            <div class="admin-section">
                <h2>Protokol přístupů</h2>
                <?php if (!empty($accessLogs)): ?>
                    <div class="user-table-container">
                        <table class="user-table">
                            <thead>
                                <tr>
                                    <th>Čas</th>
                                    <th>Uživatel</th>
                                    <th>Role</th>
                                    <th>IP Adresa</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($accessLogs as $log): ?>
                                    <tr>
                                        <td><?php echo date('d.m.Y H:i:s', strtotime($log['accessed_at'])); ?></td>
                                        <td><?php echo htmlspecialchars($log['username']); ?></td>
                                        <td><?php echo htmlspecialchars($log['role']); ?></td>
                                        <td><?php echo htmlspecialchars($log['ip_address']); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <p>Zatím žádné záznamy.</p>
                <?php endif; ?>
            </div>



            <!-- Password reset modal -->
            <div id="passwordModal" class="modal" style="display: none;">
                <div class="modal-content">
                    <div class="modal-header">
                        <h3>Změnit heslo uživatele</h3>
                        <span class="close" onclick="hidePasswordForm()">&times;</span>
                    </div>
                    <div class="modal-body">
                        <div id="password-message-container">
                            <?php echo $resetPasswordMessage; ?>
                        </div>
                        <form method="post" class="admin-form">
                            <input type="hidden" id="modal_user_id" name="user_id" value="">
                            <div class="form-group">
                                <label for="new_password">Nové heslo:</label>
                                <input type="password" id="new_password" name="new_password" required minlength="6">
                                <small>Minimálně 6 znaků</small>
                            </div>
                            <div class="form-actions">
                                <button type="submit" name="reset_password" class="btn btn-primary">Změnit heslo</button>
                                <button type="button" class="btn btn-secondary" onclick="hidePasswordForm()">Zrušit</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Email update modal -->
            <div id="emailModal" class="modal" style="display: none;">
                <div class="modal-content">
                    <div class="modal-header">
                        <h3>Změnit email uživatele</h3>
                        <span class="close" onclick="hideEmailForm()">&times;</span>
                    </div>
                    <div class="modal-body">
                        <div id="email-message-container">
                            <?php echo $updateEmailMessage; ?>
                        </div>
                        <form method="post" class="admin-form">
                            <input type="hidden" id="modal_email_user_id" name="user_id" value="">
                            <div class="form-group">
                                <label for="new_email">Nový email:</label>
                                <input type="email" id="new_email" name="new_email" required>
                                <small>Zadejte platnou emailovou adresu</small>
                            </div>
                            <div class="form-actions">
                                <button type="submit" name="update_email" class="btn btn-primary">Změnit email</button>
                                <button type="button" class="btn btn-secondary" onclick="hideEmailForm()">Zrušit</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>




            <!-- Role explanation -->



            <div class="admin-section">



                <h2>Vysvětlení rolí</h2>



                <div class="role-explanation">



                    <div class="role-card">



                        <h3>Uživatel</h3>



                        <p>Základní role s omezeným přístupem. Může prohlížet obsah, ale nemůže upravovat nebo nahrávat nové soubory.</p>



                        <ul>



                            <li>Prohlížení dokumentů</li>



                            <li>Prohlížení kontaktů</li>



                            <li>Přístup k chatbotu</li>



                        </ul>



                    </div>






                    <div class="role-card">



                        <h3>Manažer</h3>



                        <p>Střední úroveň přístupu. Může nahrávat a spravovat obsah, ale nemá přístup k nastavení systému.</p>



                        <ul>



                            <li>Všechny možnosti uživatele</li>



                            <li>Nahrávání souborů a dokumentů</li>



                            <li>Správa kontaktů</li>



                            <li>Správa nástěnky</li>



                        </ul>



                    </div>






                    <div class="role-card">



                        <h3>Administrátor</h3>



                        <p>Nejvyšší úroveň přístupu. Může spravovat uživatele a má plný přístup ke všem funkcím systému.</p>



                        <ul>



                            <li>Všechny možnosti manažera</li>



                            <li>Správa uživatelů</li>



                            <li>Přístup ke všem sekcím</li>



                        </ul>



                    </div>



                </div>



            </div>



        </div>



    </div>



</div>






<style>



    /* Admin page styles */



    #admin {
        margin-top: 50px;
        padding: 50px;
        margin-left: 250px; /* Account for sidebar width */


    }







    #admin .content-area {


        flex-direction: column;


    }







    .admin-panel {


        width: 100%;


    }







    .admin-section {


        background-color: white;


        border-radius: 10px;


        box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);


        padding: 25px;


        margin-bottom: 30px;


    }







    .admin-section h2 {


        color: #2c3e50;


        margin-bottom: 20px;


        padding-bottom: 10px;


        border-bottom: 1px solid #ecf0f1;


    }







    .admin-form .form-group {


        margin-bottom: 15px;


    }







    .admin-form label {


        display: block;


        margin-bottom: 5px;


        font-weight: bold;


    }







    .admin-form input,



    .admin-form select {


        width: 100%;


        padding: 10px;


        border: 1px solid #ddd;


        border-radius: 4px;


        font-size: 16px;


    }







    .btn {


        padding: 10px 20px;


        border: none;


        border-radius: 4px;


        cursor: pointer;


        font-size: 16px;


        font-weight: bold;


        transition: background-color 0.3s ease;


    }







    .btn-primary {


        background-color: #3498db;


        color: white;


    }







    .btn-primary:hover {


        background-color: #2980b9;


    }







    .btn-danger {


        background-color: #e74c3c;


        color: white;


    }







    .btn-danger:hover {


        background-color: #c0392b;


    }







    .btn-warning {


        background-color: #f39c12;


        color: white;


    }







    .btn-warning:hover {


        background-color: #e67e22;


    }







    .btn-secondary {


        background-color: #95a5a6;


        color: white;


    }







    .btn-secondary:hover {


        background-color: #7f8c8d;


    }







    .btn-info {
        background-color: #17a2b8;
        color: white;
    }
    
    .btn-info:hover {
        background-color: #138496;
    }
    
    /* Ensure buttons are clickable */
    .action-buttons button {
        pointer-events: auto;
        cursor: pointer;
    }
    
    .action-buttons button:hover {
        opacity: 0.9;
    }







    .btn-small {


        padding: 5px 10px;


        font-size: 14px;


        margin-right: 5px;


    }







    .action-buttons {


        display: flex;


        gap: 5px;


        align-items: center;


    }







    .modal {


        position: fixed !important;


        z-index: 1000 !important;


        left: 0 !important;


        top: 0 !important;


        width: 100% !important;


        height: 100% !important;


        background-color: rgba(0,0,0,0.5) !important;


        display: none !important;


    }







    .modal[style*="block"] {


        display: block !important;


    }







    .modal-content {


        background-color: white;


        margin: 10% auto;


        padding: 0;


        border-radius: 8px;


        width: 90%;


        max-width: 500px;


        box-shadow: 0 4px 20px rgba(0,0,0,0.3);


    }







    .modal-header {


        padding: 20px;


        border-bottom: 1px solid #ecf0f1;


        display: flex;


        justify-content: space-between;


        align-items: center;


    }







    .modal-header h3 {


        margin: 0;


        color: #2c3e50;


    }







    .close {


        font-size: 28px;


        font-weight: bold;


        cursor: pointer;


        color: #aaa;


    }







    .close:hover {


        color: #000;


    }







    .modal-body {


        padding: 20px;


    }







    .form-actions {


        display: flex;


        gap: 10px;


        margin-top: 20px;


    }







    .form-group small {


        color: #666;


        font-size: 12px;


        margin-top: 5px;


        display: block;


    }







    .success-message {


        padding: 15px;


        margin-bottom: 20px;


        border-radius: 4px;


        background-color: #d4edda;


        color: #155724;


        border: 1px solid #c3e6cb;


    }







    .error-message {


        padding: 15px;


        margin-bottom: 20px;


        border-radius: 4px;


        background-color: #f8d7da;


        color: #721c24;


        border: 1px solid #f5c6cb;


    }







    .user-table-container {


        overflow-x: auto;


    }







    .user-table {


        width: 100%;


        border-collapse: collapse;


    }







    .user-table th,



    .user-table td {


        padding: 12px 15px;


        text-align: left;


        border-bottom: 1px solid #ddd;


    }







    .user-table th {


        background-color: #f8f9fa;


        font-weight: bold;


        color: #2c3e50;


    }







    .user-table tbody tr:hover {


        background-color: #f8f9fa;


    }







    .inline-form {


        display: inline;


    }







    .role-form select {


        padding: 5px;


        border: 1px solid #ddd;


        border-radius: 4px;


    }







    .current-user-badge {


        display: inline-block;


        padding: 5px 10px;


        border-radius: 4px;


        background-color: #3498db;


        color: white;


        font-size: 14px;


    }







    .role-explanation {


        display: flex;


        flex-wrap: wrap;


        gap: 20px;


    }







    .role-card {


        flex: 1;


        min-width: 250px;


        padding: 20px;


        border-radius: 8px;


        background-color: #f8f9fa;


        border-left: 4px solid #3498db;


    }







    .role-card h3 {


        color: #2c3e50;


        margin-bottom: 10px;


    }







    .role-card p {


        margin-bottom: 15px;


        color: #34495e;


    }







    .role-card ul {


        margin-left: 20px;


        color: #34495e;


    }







    .role-card ul li {


        margin-bottom: 5px;


    }







    .access-denied {


        background-color: #f8d7da;


        color: #721c24;


        padding: 30px;


        text-align: center;


        border-radius: 8px;


        margin: 30px 0;


    }







    .access-denied h2 {


        margin-bottom: 10px;


    }







    @media (max-width: 768px) {


        #admin {


            margin-left: 0; /* Remove margin on mobile when sidebar collapses */


        }







        .role-explanation {


            flex-direction: column;


        }



    }



</style>






<script>
function showPasswordForm(userId) {
    console.log('showPasswordForm called with userId:', userId);
    
    const modal = document.getElementById('passwordModal');
    const userIdInput = document.getElementById('modal_user_id');
    const passwordInput = document.getElementById('new_password');
    
    console.log('Modal element:', modal);
    
    if (!modal) {
        console.error('Password modal not found!');
        return;
    }
    
    if (userIdInput) userIdInput.value = userId;
    if (passwordInput) passwordInput.value = '';
    
    // Force display with !important
    modal.style.setProperty('display', 'block', 'important');
    modal.style.setProperty('position', 'fixed', 'important');
    modal.style.setProperty('z-index', '9999', 'important');
    
    console.log('Modal style after setting:', modal.style.display);
    
    if (passwordInput) {
        setTimeout(() => passwordInput.focus(), 100);
    }
}

function hidePasswordForm() {
    const modal = document.getElementById('passwordModal');
    const passwordInput = document.getElementById('new_password');
    
    if (modal) {
        modal.style.setProperty('display', 'none', 'important');
    }
    if (passwordInput) passwordInput.value = '';
}

function showEmailForm(userId, currentEmail) {
    console.log('showEmailForm called with userId:', userId, 'email:', currentEmail);
    
    const modal = document.getElementById('emailModal');
    const userIdInput = document.getElementById('modal_email_user_id');
    const emailInput = document.getElementById('new_email');
    
    console.log('Email modal element:', modal);
    console.log('Modal computed style:', modal ? window.getComputedStyle(modal).display : 'modal not found');
    
    if (!modal) {
        console.error('Email modal not found!');
        return;
    }
    
    if (userIdInput) {
        userIdInput.value = userId;
        console.log('Set user ID:', userIdInput.value);
    }
    if (emailInput) {
        emailInput.value = currentEmail;
        console.log('Set email:', emailInput.value);
    }
    
    // Force display with !important
    modal.style.setProperty('display', 'block', 'important');
    modal.style.setProperty('position', 'fixed', 'important');
    modal.style.setProperty('z-index', '9999', 'important');
    modal.style.setProperty('top', '0', 'important');
    modal.style.setProperty('left', '0', 'important');
    modal.style.setProperty('width', '100%', 'important');
    modal.style.setProperty('height', '100%', 'important');
    
    console.log('Modal style after setting:', modal.style.display);
    console.log('Modal visibility:', modal.offsetWidth, modal.offsetHeight);
    
    if (emailInput) {
        setTimeout(() => emailInput.focus(), 100);
    }
}

function hideEmailForm() {
    const modal = document.getElementById('emailModal');
    const emailInput = document.getElementById('new_email');
    
    if (modal) {
        modal.style.setProperty('display', 'none', 'important');
    }
    if (emailInput) emailInput.value = '';
}

// Close modal when clicking outside of it
window.onclick = function(event) {
    const passwordModal = document.getElementById('passwordModal');
    const emailModal = document.getElementById('emailModal');
    
    if (event.target == passwordModal) {
        hidePasswordForm();
    } else if (event.target == emailModal) {
        hideEmailForm();
    }
}

// Show modals if there are form submission messages
document.addEventListener('DOMContentLoaded', function() {
    console.log('DOM loaded, checking for messages...');
    
    // Log all modal elements
    console.log('Password modal:', document.getElementById('passwordModal'));
    console.log('Email modal:', document.getElementById('emailModal'));
    
    <?php if (!empty($resetPasswordMessage)): ?>
        console.log('Found password reset message, showing modal');
        const lastUserIdForPassword = '<?php echo isset($_POST['user_id']) && isset($_POST['reset_password']) ? $_POST['user_id'] : ''; ?>';
        if (lastUserIdForPassword) {
            showPasswordForm(lastUserIdForPassword);
        }
    <?php endif; ?>
    
    <?php if (!empty($updateEmailMessage)): ?>
        console.log('Found email update message, showing modal');
        const lastUserIdForEmail = '<?php echo isset($_POST['user_id']) && isset($_POST['update_email']) ? $_POST['user_id'] : ''; ?>';
        const lastEmail = '<?php echo isset($_POST['new_email']) ? htmlspecialchars($_POST['new_email'], ENT_QUOTES) : ''; ?>';
        if (lastUserIdForEmail) {
            showEmailForm(lastUserIdForEmail, lastEmail);
        }
    <?php endif; ?>
});
</script>