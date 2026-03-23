<?php
require_once 'access.php';
header('Content-Type: application/json');

// 1. Kontrola práv (canUpload je tvoje funkce v access.php)
if (!isAdmin() && (!function_exists('canUpload') || !canUpload())) {
    echo json_encode(['success' => false, 'message' => 'Nedostatečná oprávnění']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['file'])) {
    // Zachytíme data z formuláře
    $type = $_POST['type'] ?? ''; // 'doc', 'edo', 'videos' atd.
    $customCategory = trim($_POST['custom_category'] ?? ''); // Z toho prvního inputu
    $existingCategory = $_POST['category'] ?? ''; // Z toho prvního selectu
    $subfolder = $_POST['subfolder'] ?? ''; // Z toho druhého selectu

    // 2. Ověření modulu přes access.php
    $allowedModules = ['doc', 'videos', 'marketing', 'pdf', 'other', 'links', 'podcast', 'agreements', 'courses', 'edo', 'seznam', 'test'];
    if (!in_array($type, $allowedModules)) {
        echo json_encode(['success' => false, 'message' => 'Neplatný typ modulu']);
        exit;
    }

    $baseDir = getRoot($type);
    $targetDirName = '';

    // 3. Logika složky (přesně podle tvého formátu)
    if (!empty($customCategory)) {
        // Chce vytvořit novou kategorii
        if (preg_match('/[<>:"\/\\|?*]/', $customCategory) || strpos($customCategory, '..') !== false) {
            echo json_encode(['success' => false, 'message' => 'Název složky obsahuje nepovolené znaky.']);
            exit;
        }
        $targetDirName = $customCategory;
        $subfolder = ''; // U nové kategorie většinu podsložky zatím neřešíme
    } elseif (!empty($existingCategory)) {
        // Nahrává do existující
        $targetDirName = $existingCategory;
    } else {
        echo json_encode(['success' => false, 'message' => 'Vyberte složku nebo zadejte novou.']);
        exit;
    }

    // Sestavení cesty na Wedosu
    $uploadPath = $baseDir . $targetDirName . '/';
    if (!empty($subfolder)) {
        $uploadPath .= $subfolder . '/';
    }

    // 4. Vytvoření složky, pokud neexistuje (0755 pro Linux/Wedos)
    if (!is_dir($uploadPath)) {
        if (!mkdir($uploadPath, 0755, true)) {
            echo json_encode(['success' => false, 'message' => 'Chyba při vytváření složky na serveru.']);
            exit;
        }
    }

    // 5. Zpracování souboru
    $file = $_FILES['file'];
    $fileName = $file['name'];
    $finalPath = $uploadPath . $fileName;

    // Ošetření duplicit (stejně jako jsi to měl)
    if (file_exists($finalPath)) {
        $info = pathinfo($fileName);
        $counter = 1;
        while (file_exists($uploadPath . $info['filename'] . " ({$counter})." . $info['extension'])) {
            $counter++;
        }
        $finalPath = $uploadPath . $info['filename'] . " ({$counter})." . $info['extension'];
    }

    // 6. Přesun na Wedos disk
    if (move_uploaded_file($file['tmp_name'], $finalPath)) {
        echo json_encode([
            'success' => true,
            'message' => "Soubor byl nahrán do: " . ($customCategory ?: $targetDirName),
            'fileName' => basename($finalPath)
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Chyba při ukládání souboru na server.']);
    }
    exit;
}