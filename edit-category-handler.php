<?php
session_start();
require_once 'access.php';

if (!isAdmin()) {
    echo json_encode(['success' => false, 'message' => 'Nedostatečná oprávnění']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 1. OCHRANA: Whitelist povolených složek
    $allowedTypes = ['doc', 'videos', 'marketing', 'pdf', 'other', 'links', 'podcast', 'agreements', 'courses', 'edo', 'seznam', 'test'];
    $type = $_POST['type'] ?? 'doc';

    if (!in_array($type, $allowedTypes)) {
        echo json_encode(['success' => false, 'message' => 'Neplatný typ úložiště']);
        exit;
    }

    $originalName = trim($_POST['original_name'] ?? '');
    $newName = trim($_POST['new_name'] ?? '');

    // Čištění názvu, aby se nedalo vyskočit ze složky přes ../
    $dirName = basename(preg_replace('/[<>:"|*?\\\\\/]/', '', $newName));
    $dirName = str_replace(' ', '_', $dirName);

    $basePath = getRoot($type);
    $oldPath = realpath($basePath . $originalName);
    $newPath = $basePath . $dirName;

    // 2. OCHRANA: Kontrola, jestli realpath pořád končí v naší složce
    if (!$oldPath || strpos($oldPath, realpath($basePath)) !== 0) {
        echo json_encode(['success' => false, 'message' => 'Nepovolená manipulace s cestou']);
        exit;
    }

    // Handle file upload
    if (isset($_FILES['category_file']) && $_FILES['category_file']['error'] === UPLOAD_ERR_OK) {
        $uploadedFile = $_FILES['category_file'];
        $ext = strtolower(pathinfo($uploadedFile['name'], PATHINFO_EXTENSION));

        // 3. OCHRANA: Blacklist nebezpečných spustitelných souborů
        $forbidden = ['php', 'phtml', 'php5', 'php7', 'phps', 'php8', 'pht', 'phar', 'htaccess', 'cgi'];
        if (in_array($ext, $forbidden)) {
            echo json_encode(['success' => false, 'message' => 'Tento typ souboru je z bezpečnostních důvodů zakázán!']);
            exit;
        }

        $fileName = !empty($_POST['custom_filename']) ? $_POST['custom_filename'] . '.' . $ext : $uploadedFile['name'];
        $targetPath = $oldPath . '/' . basename($fileName);

        if (move_uploaded_file($uploadedFile['tmp_name'], $targetPath)) {
            chmod($targetPath, 0644);
        }
    }

    // Přejmenování složky
    if ($originalName !== $dirName) {
        if (rename($oldPath, $newPath)) {
            echo json_encode(['success' => true, 'message' => "Změněno na $newName"]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Chyba při přejmenování']);
        }
    } else {
        echo json_encode(['success' => true, 'message' => 'Aktualizováno']);
    }
}