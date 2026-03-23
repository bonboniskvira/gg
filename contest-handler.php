<?php
// Start output buffering to prevent any accidental output
ob_start();

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/access.php';

// Clear any previous output and set JSON header
ob_clean();
header('Content-Type: application/json; charset=UTF-8');

// Database connection using setup-database.php details
function getContestDbConnection() {
    $host = 'md396.wedos.net';
    $username = 'w394711_main';
    $password = 'mnpJtgaJ';
    $dbname = 'd394711_main';
    $port = 3306;
    
    try {
        $conn = new mysqli($host, $username, $password, $dbname, $port);
        
        if ($conn->connect_error) {
            throw new Exception("Connection failed: " . $conn->connect_error);
        }
        
        $conn->set_charset("utf8mb4");
        return $conn;
    } catch (Exception $e) {
        error_log("Contest handler database connection error: " . $e->getMessage());
        return null;
    }
}

// Create contest table if it doesn't exist
function createContestTable() {
    $conn = getContestDbConnection();
    if (!$conn) {
        throw new Exception("Database connection failed");
    }
    
    $sql = "CREATE TABLE IF NOT EXISTS contest_entries (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(255) NOT NULL,
        email VARCHAR(255) NOT NULL,
        phone VARCHAR(50) NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_email (email),
        INDEX idx_created_at (created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    try {
        $result = $conn->query($sql);
        if (!$result) {
            error_log("Failed to create contest_entries table: " . $conn->error);
            // Check if table exists anyway
            $checkSql = "SELECT 1 FROM contest_entries LIMIT 1";
            if (!$conn->query($checkSql)) {
                // Table doesn't exist and can't be created
                throw new Exception("Failed to create contest table");
            }
            // Table exists, so we're OK
        }
    } catch (Exception $e) {
        // If CREATE TABLE fails due to permissions, try to verify table exists
        $checkSql = "SELECT 1 FROM contest_entries LIMIT 1";
        if (!$conn->query($checkSql)) {
            $conn->close();
            throw new Exception("Contest table does not exist and cannot be created");
        }
    }
    
    $conn->close();
}

// Validate form data
function validateContestEntry($data) {
    $errors = [];
    
    // Validate name
    $name = trim($data['name'] ?? '');
    if (empty($name)) {
        $errors['name'] = 'Jméno je povinné';
    } elseif (mb_strlen($name) < 2) {
        $errors['name'] = 'Jméno musí mít alespoň 2 znaky';
    } elseif (mb_strlen($name) > 100) {
        $errors['name'] = 'Jméno je příliš dlouhé (max 100 znaků)';
    }
    
    // Validate email
    $email = trim($data['email'] ?? '');
    if (empty($email)) {
        $errors['email'] = 'E-mail je povinný';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'Neplatný formát e-mailu';
    } elseif (mb_strlen($email) > 255) {
        $errors['email'] = 'E-mail je příliš dlouhý';
    }
    
    // Validate phone
    $phone = trim($data['phone'] ?? '');
    if (empty($phone)) {
        $errors['phone'] = 'Telefon je povinný';
    } elseif (!preg_match('/^[\+]?[0-9\s\-\(\)]{9,20}$/', $phone)) {
        $errors['phone'] = 'Neplatný formát telefonního čísla';
    }
    
    return $errors;
}

// Check if email already exists
function emailExists($email) {
    $conn = getContestDbConnection();
    if (!$conn) {
        throw new Exception("Database connection failed");
    }
    
    $stmt = $conn->prepare("SELECT id FROM contest_entries WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();
    $exists = $result->num_rows > 0;
    $stmt->close();
    $conn->close();
    return $exists;
}

// Save contest entry
function saveContestEntry($name, $email, $phone) {
    $conn = getContestDbConnection();
    if (!$conn) {
        throw new Exception("Database connection failed");
    }
    
    $stmt = $conn->prepare("INSERT INTO contest_entries (name, email, phone) VALUES (?, ?, ?)");
    if (!$stmt) {
        error_log("Failed to prepare statement: " . $conn->error);
        $conn->close();
        throw new Exception("Database error: Failed to prepare statement");
    }
    
    $stmt->bind_param("sss", $name, $email, $phone);
    
    $success = $stmt->execute();
    if (!$success) {
        error_log("Failed to execute insert: " . $stmt->error);
        $stmt->close();
        $conn->close();
        throw new Exception("Database error: Failed to save entry");
    }
    
    $entryId = $conn->insert_id;
    error_log("Successfully saved contest entry with ID: " . $entryId);
    
    $stmt->close();
    $conn->close();
    
    return $entryId;
}

// Delete contest entry (admin only)
function deleteContestEntry($entryId) {
    // Only admins can delete - but check is done in main code above
    if (!isset($_SESSION['user_id']) || !isAdmin()) return false;
    
    $conn = getContestDbConnection();
    if (!$conn) {
        throw new Exception("Database connection failed");
    }
    
    // Start transaction to delete both entry and answers
    $conn->autocommit(false);
    
    try {
        // Delete answers first (due to foreign key constraint)
        $stmt1 = $conn->prepare("DELETE FROM contest_answers WHERE entry_id = ?");
        $stmt1->bind_param("i", $entryId);
        $stmt1->execute();
        $stmt1->close();
        
        // Delete entry
        $stmt2 = $conn->prepare("DELETE FROM contest_entries WHERE id = ?");
        $stmt2->bind_param("i", $entryId);
        $success = $stmt2->execute();
        $stmt2->close();
        
        if ($success) {
            $conn->commit();
            error_log("Successfully deleted contest entry ID: " . $entryId);
        } else {
            $conn->rollback();
            error_log("Failed to delete contest entry ID: " . $entryId);
        }
        
        $conn->close();
        return $success;
        
    } catch (Exception $e) {
        $conn->rollback();
        $conn->close();
        error_log("Error deleting contest entry: " . $e->getMessage());
        throw $e;
    }
}

try {
    // Ensure table exists - try to create, but don't fail if permission denied
    try {
        createContestTable();
    } catch (Exception $tableError) {
        error_log("Table creation failed, but continuing: " . $tableError->getMessage());
        // Continue anyway - table might exist
    }
    
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Neplatná metoda požadavku');
    }
    
    // Handle delete action (admin only - requires login)
    if (isset($_POST['action']) && $_POST['action'] === 'delete') {
        // Check if user is logged in for admin actions
        if (!isset($_SESSION['user_id']) || !isLoggedIn()) {
            echo json_encode([
                'success' => false,
                'message' => 'Nejste přihlášeni - admin akce vyžaduje přihlášení'
            ]);
            exit;
        }
        
        if (!isAdmin()) {
            echo json_encode([
                'success' => false,
                'message' => 'Nemáte oprávnění pro tuto akci'
            ]);
            exit;
        }
        
        $entryId = intval($_POST['entry_id'] ?? 0);
        if ($entryId <= 0) {
            throw new Exception('Neplatné ID přihlášky');
        }
        
        if (deleteContestEntry($entryId)) {
            ob_clean();
            echo json_encode([
                'success' => true,
                'message' => 'Přihláška byla úspěšně smazána'
            ]);
        } else {
            throw new Exception('Chyba při mazání přihlášky');
        }
        exit;
    }
    
    // Public contest entry submission - NO login required
    // Handle normal form submission (this is the public contest entry)
    error_log("Processing public contest entry submission");
    
    $errors = validateContestEntry($_POST);
    
    if (!empty($errors)) {
        ob_clean();
        echo json_encode([
            'success' => false,
            'message' => 'Opravte chyby ve formuláři',
            'field_errors' => $errors
        ]);
        exit;
    }
    
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone']);
    
    error_log("Attempting to save contest entry: " . $email);
    
    // Check for duplicate email
    if (emailExists($email)) {
        error_log("Duplicate email attempt: " . $email);
        ob_clean();
        echo json_encode([
            'success' => false,
            'message' => 'Tento e-mail je již registrován v soutěži',
            'field_errors' => ['email' => 'E-mail je již použit']
        ]);
        exit;
    }
    
    // Save entry
    $entryId = saveContestEntry($name, $email, $phone);
    
    if ($entryId) {
        error_log("Contest entry saved successfully with ID: " . $entryId);
        
        // Set session variable for access to questions page (even for non-logged users)
        $_SESSION['contest_entry_id'] = $entryId;
        
        ob_clean();
        echo json_encode([
            'success' => true,
            'message' => 'Registrace byla úspěšná! Přesměrováváme vás na otázky soutěže...',
            'entry_id' => $entryId,
            'redirect' => 'contest-questions.php?entry_id=' . $entryId
        ]);
    } else {
        throw new Exception('Chyba při ukládání přihlášky do databáze');
    }
    
} catch (Exception $e) {
    error_log("Contest handler error: " . $e->getMessage());
    ob_clean();
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

// Ensure we exit cleanly
exit;
?>
        'message' => $e->getMessage()
    ]);
}

// Ensure we exit cleanly
exit;
?>
