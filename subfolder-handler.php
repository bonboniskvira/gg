<?php
// Prevence výstupu před JSONem
ob_start();
session_start();
require_once 'access.php';

// Vyčištění bufferu a nastavení JSON headeru
ob_clean();
header('Content-Type: application/json; charset=utf-8');

// 1. OCHRANA: Pouze admin
if (!isAdmin()) {
    echo json_encode(['success' => false, 'message' => 'Nedostatečná oprávnění']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 2. VSTUPY A WHITELIST
    $allowedTypes = ['doc', 'videos', 'marketing', 'pdf', 'other', 'links', 'podcast', 'agreements', 'courses', 'edo', 'seznam', 'test'];

    $type = $_POST['type'] ?? '';
    $action = $_POST['action'] ?? ''; // 'edit' nebo 'delete'
    $parentCategory = basename($_POST['parent_category'] ?? $_POST['category'] ?? '');
    $originalName = basename($_POST['original_name'] ?? $_POST['subfolder'] ?? '');

    if (!in_array($type, $allowedTypes) || empty($parentCategory) || empty($originalName)) {
        echo json_encode(['success' => false, 'message' => 'Neplatné parametry požadavku']);
        exit;
    }

    $basePath = getRoot($type) . $parentCategory . '/';
    $targetPath = $basePath . $originalName;

    // --- AKCE: SMAZAT ---
    if ($action === 'delete') {
        if (!is_dir($targetPath)) {
            echo json_encode(['success' => false, 'message' => 'Podsložka neexistuje']);
            exit;
        }

        // Rekurzivní funkce pro smazání složky i s obsahem
        function deleteDirectoryRecursive($dir) {
            if (!is_dir($dir)) return false;
            $items = array_diff(scandir($dir), ['.', '..']);
            foreach ($items as $item) {
                $path = $dir . DIRECTORY_SEPARATOR . $item;
                is_dir($path) ? deleteDirectoryRecursive($path) : unlink($path);
            }
            return rmdir($dir);
        }

        if (deleteDirectoryRecursive($targetPath)) {
            if (function_exists('clearWebCache')) clearWebCache($type);
            echo json_encode(['success' => true, 'message' => "Podsložka byla smazána"]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Chyba při mazání z disku']);
        }
    }

    // --- AKCE: UPRAVIT (Rename + Upload) ---
    elseif ($action === 'edit') {
        $newNameRaw = trim($_POST['new_name'] ?? $originalName);
        // Očištění názvu pro souborový systém
        $newName = basename(preg_replace('/[<>:"|*?\\\\\/]/', '', $newNameRaw));
        $newName = str_replace(' ', '_', $newName);
        $newPath = $basePath . $newName;

        if (!is_dir($targetPath)) {
            echo json_encode(['success' => false, 'message' => 'Původní podsložka neexistuje']);
            exit;
        }

        // 1. Handle file upload (pokud je přiložen soubor v modalu)
        if (isset($_FILES['subfolder_file']) && $_FILES['subfolder_file']['error'] === UPLOAD_ERR_OK) {
            $uploadedFile = $_FILES['subfolder_file'];
            $ext = strtolower(pathinfo($uploadedFile['name'], PATHINFO_EXTENSION));

            // Bezpečnostní stopka pro spustitelné soubory
            $forbidden = ['php', 'phtml', 'php5', 'php7', 'php8', 'phar', 'htaccess', 'cgi'];
            if (in_array($ext, $forbidden)) {
                echo json_encode(['success' => false, 'message' => 'Tento typ souboru je zakázán!']);
                exit;
            }

            $customFilename = trim($_POST['custom_filename'] ?? '');
            $finalFilename = !empty($customFilename) ? basename($customFilename) . '.' . $ext : basename($uploadedFile['name']);

            // Soubor nahráváme do STÁVAJÍCÍ složky (před případným přejmenováním)
            if (move_uploaded_file($uploadedFile['tmp_name'], $targetPath . '/' . $finalFilename)) {
                chmod($targetPath . '/' . $finalFilename, 0644);
            }
        }

        // 2. Handle Rename
        if ($originalName !== $newName) {
            if (is_dir($newPath)) {
                echo json_encode(['success' => false, 'message' => 'Složka s tímto názvem už existuje']);
                exit;
            }
            if (rename($targetPath, $newPath)) {
                chmod($newPath, 0755);
                if (function_exists('clearWebCache')) clearWebCache($type);
                echo json_encode(['success' => true, 'message' => "Podsložka byla upravena a přejmenována"]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Přejmenování se nezdařilo']);
            }
        } else {
            if (function_exists('clearWebCache')) clearWebCache($type);
            echo json_encode(['success' => true, 'message' => "Podsložka byla aktualizována"]);
        }
    }

    else {
        echo json_encode(['success' => false, 'message' => 'Neznámá akce']);
    }

} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
}

ob_end_flush();