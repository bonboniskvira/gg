<?php
session_start();
require_once 'access.php';

// Nastavení kódování pro Wedos/Linux
header('Content-Type: text/html; charset=UTF-8');
mb_internal_encoding('UTF-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    die("Neplatný požadavek.");
}

// 1. Parametry z formuláře
$type = $_POST['type'] ?? 'doc';
$redirect = $_POST['redirect'] ?? 'documents'; // Např. documents, edo, videos
$customCategory = trim($_POST['custom_category'] ?? '');
$category = $_POST['category'] ?? '';
$subfolder = $_POST['subfolder'] ?? '';

// 2. Kontrola modulu
$allowedModules = ['doc', 'videos', 'marketing', 'pdf', 'other', 'links', 'podcast', 'agreements', 'courses', 'edo', 'seznam', 'test'];
if (!in_array($type, $allowedModules)) {
    header("Location: index.php#{$redirect}"); // Čistý návrat při chybě
    exit;
}

$baseDir = getRoot($type);
$finalCategory = '';

// 3. Logika složky (přesně podle tebe, bez tabulek)
if (!empty($customCategory)) {
    // Tvoje původní validace znaků
    if (preg_match('/[<>:"\/\\|?*]/', $customCategory) || strpos($customCategory, '..') !== false) {
        header("Location: index.php#{$redirect}");
        exit;
    }
    $finalCategory = $customCategory;
    $subfolder = '';
} elseif (!empty($category)) {
    $finalCategory = $category;
} else {
    header("Location: index.php#{$redirect}");
    exit;
}

// 4. Cesta pro upload (pro Wedos)
$uploadDir = $baseDir . $finalCategory . '/';
if (!empty($subfolder)) {
    $uploadDir .= $subfolder . '/';
}

// Vytvoření složky, pokud neexistuje (0755 pro Linux)
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

// 5. Zpracování souboru
if (isset($_FILES['file']) && $_FILES['file']['error'] === UPLOAD_ERR_OK) {
    $originalName = $_FILES['file']['name'];
    $tmpName = $_FILES['file']['tmp_name'];

    // Ošetření duplicit (přidávání (1), (2) atd.)
    $targetPath = $uploadDir . $originalName;
    if (file_exists($targetPath)) {
        $info = pathinfo($originalName);
        $counter = 1;
        while (file_exists($uploadDir . $info['filename'] . " ({$counter})." . $info['extension'])) {
            $counter++;
        }
        $targetPath = $uploadDir . $info['filename'] . " ({$counter})." . $info['extension'];
    }

    // Přesun souboru
    move_uploaded_file($tmpName, $targetPath);
}

// 6. FINÁLNÍ REDIRECT (Čistý, bez zpráv v URL)
header("Location: index.php#{$redirect}");
exit;