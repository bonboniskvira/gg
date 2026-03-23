<?php
// edit-file-handler.php - Univerzální přejmenovávač souborů
ob_start();
session_start();
require_once 'access.php';

// Vyčistíme buffer a nastavíme JSON
if (ob_get_length()) ob_clean();
header('Content-Type: application/json; charset=utf-8');

if (!isAdmin()) {
    echo json_encode(['success' => false, 'message' => 'Nedostatečná oprávnění']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Povolené sekce
    $allowedTypes = ['agreements', 'doc', 'podcast', 'edo', 'seznam', 'test', 'courses', 'marketing', 'pdf'];

    $type         = $_POST['type'] ?? '';
    $category     = basename($_POST['category'] ?? '');
    $subfolder    = basename($_POST['subfolder'] ?? '');
    $originalName = basename($_POST['original_name'] ?? ''); // Celé jméno s příponou
    $newNameRaw   = trim($_POST['new_name'] ?? '');        // Nové jméno bez přípony

    if (!in_array($type, $allowedTypes) || empty($originalName) || empty($newNameRaw)) {
        echo json_encode(['success' => false, 'message' => 'Neplatné parametry požadavku']);
        exit;
    }

    try {
        $baseDir = getRoot($type);
        if (!$baseDir) throw new Exception("Cesta pro typ '{$type}' nenalezena.");

        // Sestavení cesty ke složce
        $dirPath = $baseDir . $category . '/';
        if (!empty($subfolder) && $subfolder !== '.') {
            $dirPath .= $subfolder . '/';
        }

        $oldPath = $dirPath . $originalName;

        if (!file_exists($oldPath)) {
            echo json_encode(['success' => false, 'message' => 'Původní soubor neexistuje']);
            exit;
        }

        // Získání přípony z původního souboru
        $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));

        // Vyčištění nového jména (odstranění divných znaků, ale zachování češtiny)
        $cleanNewName = preg_replace('/[<>:"\/\\|?*]/', '', $newNameRaw);
        $newFileName = $cleanNewName . '.' . $extension;
        $newPath = $dirPath . $newFileName;

        // Pokud se jméno nemění, nebudeme nic dělat
        if ($originalName === $newFileName) {
            echo json_encode(['success' => true, 'message' => 'Jméno je stejné, není co měnit.']);
            exit;
        }

        // Kontrola, jestli už soubor s novým jménem neexistuje
        if (file_exists($newPath)) {
            echo json_encode(['success' => false, 'message' => 'Soubor s tímto názvem již v této složce existuje']);
            exit;
        }

        // Realný přejmenovávací proces
        if (rename($oldPath, $newPath)) {
            echo json_encode([
                'success' => true,
                'message' => "Soubor byl úspěšně přejmenován na '{$newFileName}'",
                'new_name' => $newFileName
            ]);
        } else {
            throw new Exception("Chyba při zápisu na disk (práva?)");
        }

    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Chyba: ' . $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Metoda POST nebyla nalezena']);
}

ob_end_flush();