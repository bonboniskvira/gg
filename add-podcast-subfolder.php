<?php
// Prevent any output before headers
ob_start();

session_start();
require_once 'access.php';

// Clean any previous output
ob_clean();

header('Content-Type: application/json; charset=utf-8');

if (!canUpload()) {
    echo json_encode(['success' => false, 'message' => 'Insufficient permissions']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $parentCategory = trim($_POST['parent_category'] ?? '');
    $subfolderName = trim($_POST['subfolder_name'] ?? '');
    
    if (empty($parentCategory) || empty($subfolderName)) {
        echo json_encode(['success' => false, 'message' => 'Chybí povinné údaje']);
        exit;
    }
    
    // Fix: Allow Czech characters - only remove dangerous characters
    $subfolderName = preg_replace('/[<>:"|*?\\\\\/]/', '', $subfolderName);
    $subfolderName = str_replace(' ', '_', $subfolderName);
    $subfolderName = trim($subfolderName);
    
    if (empty($subfolderName)) {
        echo json_encode(['success' => false, 'message' => 'Neplatný název podsložky']);
        exit;
    }
    
    $categoryPath = getRoot('podcast') . $parentCategory;
    $subfolderPath = $categoryPath . '/' . $subfolderName;
    
    if (!is_dir($categoryPath)) {
        echo json_encode(['success' => false, 'message' => 'Rodičovská kategorie neexistuje']);
        exit;
    }
    
    if (is_dir($subfolderPath)) {
        echo json_encode(['success' => false, 'message' => 'Podsložka již existuje']);
        exit;
    }
    
    if (mkdir($subfolderPath, 0755, true)) {
        chmod($subfolderPath, 0755);
        echo json_encode(['success' => true, 'message' => "Podsložka '{$subfolderName}' byla úspěšně vytvořena"]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Nepodařilo se vytvořit podsložku']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
}

// End output buffering and send response
ob_end_flush();
exit;
?>
