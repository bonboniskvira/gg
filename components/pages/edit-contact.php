<?php
session_start();

// Check if user is admin
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Přístup odepřen']);
    exit;
}

$action = $_GET['action'] ?? '';
$contactsDir = __DIR__ . '/contacts/';

switch ($action) {
    case 'get':
        $contactId = $_GET['id'] ?? '';
        $category = $_GET['category'] ?? '';
        
        if (empty($contactId) || empty($category)) {
            echo json_encode(['success' => false, 'message' => 'Chybí ID kontaktu nebo kategorie']);
            exit;
        }
        
        $filePath = $contactsDir . $category . '/' . $contactId . '.json';
        
        if (!file_exists($filePath)) {
            echo json_encode(['success' => false, 'message' => 'Kontakt nenalezen']);
            exit;
        }
        
        $contactData = json_decode(file_get_contents($filePath), true);
        if ($contactData) {
            $contactData['category'] = $category;
            echo json_encode(['success' => true, 'contact' => $contactData]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Chyba při čtení kontaktu']);
        }
        break;
        
    case 'save':
        $contactId = $_POST['contact_id'] ?? '';
        $originalCategory = $_POST['original_category'] ?? '';
        $newCategory = $_POST['contact_category'] ?? '';
        
        if (empty($contactId) || empty($originalCategory)) {
            echo json_encode(['success' => false, 'message' => 'Chybí ID kontaktu nebo kategorie']);
            exit;
        }
        
        $oldFilePath = $contactsDir . $originalCategory . '/' . $contactId . '.json';
        
        if (!file_exists($oldFilePath)) {
            echo json_encode(['success' => false, 'message' => 'Kontakt nenalezen']);
            exit;
        }
        
        // Prepare new contact data
        $contactData = [
            'name' => $_POST['contact_name'] ?? '',
            'phone' => $_POST['contact_phone'] ?? '',
            'email' => $_POST['contact_email'] ?? '',
            'position' => $_POST['contact_position'] ?? '',
            'category' => $newCategory,
            'notes' => $_POST['contact_notes'] ?? '',
            'created_at' => date('Y-m-d H:i:s')
        ];
        
        // Create new category directory if needed
        $newCategoryDir = $contactsDir . $newCategory . '/';
        if (!is_dir($newCategoryDir)) {
            mkdir($newCategoryDir, 0777, true);
        }
        
        $newFilePath = $newCategoryDir . $contactId . '.json';
        
        // Save the contact
        if (file_put_contents($newFilePath, json_encode($contactData, JSON_PRETTY_PRINT))) {
            // If category changed, delete old file
            if ($originalCategory !== $newCategory) {
                unlink($oldFilePath);
            }
            echo json_encode(['success' => true, 'message' => 'Kontakt upraven']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Chyba při ukládání']);
        }
        break;
        
    case 'delete':
        $contactId = $_POST['contact_id'] ?? '';
        $category = $_POST['category'] ?? '';
        
        if (empty($contactId) || empty($category)) {
            echo json_encode(['success' => false, 'message' => 'Chybí ID kontaktu nebo kategorie']);
            exit;
        }
        
        $filePath = $contactsDir . $category . '/' . $contactId . '.json';
        
        if (!file_exists($filePath)) {
            echo json_encode(['success' => false, 'message' => 'Kontakt nenalezen']);
            exit;
        }
        
        if (unlink($filePath)) {
            echo json_encode(['success' => true, 'message' => 'Kontakt smazán']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Chyba při mazání']);
        }
        break;
        
    default:
        echo json_encode(['success' => false, 'message' => 'Neznámá akce']);
}
?>
