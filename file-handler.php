<?php
session_start();
require_once 'access.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isAdmin()) {
    echo json_encode(['success' => false, 'message' => 'Nedostatečná oprávnění']);
    exit;
}

$action = $_POST['action'] ?? ''; // 'rename' nebo 'delete'
$type = $_POST['type'] ?? '';
$category = basename($_POST['category'] ?? '');
$subfolder = basename($_POST['subfolder'] ?? '');
$fileName = basename($_POST['original_name'] ?? $_POST['file_name'] ?? '');

$basePath = getRoot($type) . $category . '/' . ($subfolder ? $subfolder . '/' : '');
$targetFile = $basePath . $fileName;

if ($action === 'delete') {
    if (file_exists($targetFile) && unlink($targetFile)) {
        if (function_exists('clearWebCache')) clearWebCache($type);
        echo json_encode(['success' => true, 'message' => 'Soubor smazán']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Chyba při mazání']);
    }
}
elseif ($action === 'rename') {
    $newName = basename($_POST['new_name'] ?? '');
    $ext = pathinfo($fileName, PATHINFO_EXTENSION);
    $newFullName = $newName . '.' . $ext;
    $newPath = $basePath . $newFullName;

    if (file_exists($newPath)) {
        echo json_encode(['success' => false, 'message' => 'Soubor s tímto jménem již existuje']);
    } elseif (rename($targetFile, $newPath)) {
        if (function_exists('clearWebCache')) clearWebCache($type);
        echo json_encode(['success' => true, 'message' => 'Soubor přejmenován']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Chyba při přejmenování']);
    }
}