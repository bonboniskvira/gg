<?php
session_start();

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$action = $_GET['action'] ?? '';

if ($action === 'get') {
    // Get contact data
    $contactId = $_GET['id'] ?? '';
    $category = $_GET['category'] ?? '';
    
    if (empty($contactId) || empty($category)) {
        echo json_encode(['success' => false, 'message' => 'Missing parameters']);
        exit;
    }
    
    $contactsDir = __DIR__ . '/contacts/';
    $categoryDir = $contactsDir . $category . '/';
    $contactFile = $categoryDir . $contactId . '.json';
    
    if (!file_exists($contactFile)) {
        echo json_encode(['success' => false, 'message' => 'Contact not found']);
        exit;
    }
    
    $contactData = json_decode(file_get_contents($contactFile), true);
    
    if ($contactData) {
        echo json_encode(['success' => true, 'contact' => $contactData]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Invalid contact data']);
    }
    
} elseif ($action === 'save') {
    // Save contact data
    $contactId = $_POST['contact_id'] ?? '';
    $originalCategory = $_POST['original_category'] ?? '';
    $name = trim($_POST['contact_name'] ?? '');
    $phone = trim($_POST['contact_phone'] ?? '');
    $email = trim($_POST['contact_email'] ?? '');
    $position = trim($_POST['contact_position'] ?? '');
    $colorOverride = trim($_POST['contact_color_override'] ?? '');
    $category = trim($_POST['contact_category'] ?? 'other');
    $notes = trim($_POST['contact_notes'] ?? '');
    
    // Validation
    if (empty($contactId) || empty($originalCategory) || empty($name)) {
        echo json_encode(['success' => false, 'message' => 'Missing required fields']);
        exit;
    }
    
    if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo json_encode(['success' => false, 'message' => 'Invalid email']);
        exit;
    }
    
    // Valid categories and colors
    $validCategories = ['management', 'central', 'sales', 'support', 'hr', 'suppliers', 'other'];
    $validColors = ['', '#e74c3c', '#3498db', '#f39c12', '#9b59b6', '#2ecc71', '#34495e', '#16a085', '#e67e22'];
    
    if (!in_array($category, $validCategories)) {
        $category = 'other';
    }
    
    if (!in_array($colorOverride, $validColors)) {
        $colorOverride = '';
    }
    
    $contactsDir = __DIR__ . '/contacts/';
    $originalCategoryDir = $contactsDir . $originalCategory . '/';
    $newCategoryDir = $contactsDir . $category . '/';
    $originalFile = $originalCategoryDir . $contactId . '.json';
    
    // Create new category directory if needed
    if (!is_dir($newCategoryDir)) {
        mkdir($newCategoryDir, 0777, true);
    }
    
    // Create updated contact data
    $contactData = [
        'name' => $name,
        'phone' => $phone,
        'email' => $email,
        'position' => $position,
        'color_override' => $colorOverride,
        'notes' => $notes,
        'date_added' => time(),
        'category' => $category
    ];
    
    // If category changed, move the file
    if ($originalCategory !== $category) {
        $newFile = $newCategoryDir . $contactId . '.json';
        file_put_contents($newFile, json_encode($contactData, JSON_PRETTY_PRINT));
        
        // Delete old file
        if (file_exists($originalFile)) {
            unlink($originalFile);
        }
    } else {
        // Same category, just update the file
        file_put_contents($originalFile, json_encode($contactData, JSON_PRETTY_PRINT));
    }
    
    echo json_encode(['success' => true, 'message' => 'Contact updated successfully']);
    
} elseif ($action === 'delete') {
    // Delete contact
    $contactId = $_POST['contact_id'] ?? '';
    $category = $_POST['category'] ?? '';
    
    if (empty($contactId) || empty($category)) {
        echo json_encode(['success' => false, 'message' => 'Missing parameters']);
        exit;
    }
    
    $contactsDir = __DIR__ . '/contacts/';
    $categoryDir = $contactsDir . $category . '/';
    $contactFile = $categoryDir . $contactId . '.json';
    
    if (!file_exists($contactFile)) {
        echo json_encode(['success' => false, 'message' => 'Contact not found']);
        exit;
    }
    
    if (unlink($contactFile)) {
        echo json_encode(['success' => true, 'message' => 'Contact deleted successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to delete contact']);
    }
    
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid action']);
}
?>
