<?php
header('Content-Type: application/json');

require_once 'access.php';

if (!isAdmin()) {
    echo json_encode(['success' => false, 'message' => 'Nemáte oprávnění']);
    exit;
}

$documentId = $_POST['document_id'] ?? '';
$category = $_POST['category'] ?? '';
$oldName = $_POST['old_name'] ?? '';
$newName = trim($_POST['new_name'] ?? '');

if (empty($oldName) || empty($newName)) {
    echo json_encode(['success' => false, 'message' => 'Chybí údaje']);
    exit;
}

$docDir = __DIR__ . '/../../doc/' . $category . '/';
$extension = pathinfo($oldName, PATHINFO_EXTENSION);
$newFileName = $newName . '.' . $extension;

$oldPath = $docDir . $oldName;
$newPath = $docDir . $newFileName;

// Check in subfolders if not found in main directory
if (!file_exists($oldPath)) {
    $folders = glob($docDir . '*', GLOB_ONLYDIR);
    foreach ($folders as $folder) {
        $subPath = $folder . '/' . $oldName;
        if (file_exists($subPath)) {
            $oldPath = $subPath;
            $newPath = $folder . '/' . $newFileName;
            break;
        }
    }
}

if (!file_exists($oldPath)) {
    echo json_encode(['success' => false, 'message' => 'Soubor nenalezen']);
    exit;
}

if (file_exists($newPath)) {
    echo json_encode(['success' => false, 'message' => 'Soubor s tímto názvem již existuje']);
    exit;
}

if (rename($oldPath, $newPath)) {
    echo json_encode(['success' => true, 'message' => 'Soubor přejmenován']);
} else {
    echo json_encode(['success' => false, 'message' => 'Chyba při přejmenování']);
}
?>
