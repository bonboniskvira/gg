<?php
ob_start();
session_start();
require_once 'access.php';

if (ob_get_level()) ob_clean();
header('Content-Type: application/json; charset=utf-8');

if (!isAdmin()) {
    echo json_encode(['success' => false, 'message' => 'Nedostatečná oprávnění.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // TADY JE TA ZMĚNA: Bereme typ z POSTu, nebo defaultně doc
    $type = $_POST['type'] ?? 'doc';
    $category = basename($_POST['category'] ?? '');
    $subfolder = basename($_POST['subfolder'] ?? '');

    if (empty($category) || empty($subfolder)) {
        echo json_encode(['success' => false, 'message' => 'Chybí parametry pro smazání.']);
        exit;
    }

    // Použijeme dynamický typ v getRoot
    $targetPath = getRoot($type) . $category . '/' . $subfolder;

    if (!is_dir($targetPath)) {
        echo json_encode(['success' => false, 'message' => 'Složka neexistuje na cestě: ' . $targetPath]);
        exit;
    }

    function deleteDir($dir) {
        if (!is_dir($dir)) return false;
        $items = array_diff(scandir($dir), ['.', '..']);
        foreach ($items as $item) {
            $path = $dir . DIRECTORY_SEPARATOR . $item;
            is_dir($path) ? deleteDir($path) : unlink($path);
        }
        return rmdir($dir);
    }

    if (deleteDir($targetPath)) {
        // Vyčistíme cache pro daný modul, pokud existuje funkce
        if (function_exists('clearWebCache')) {
            clearWebCache($type);
        }
        echo json_encode(['success' => true, 'message' => "Složka '$subfolder' byla smazána."]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Složku se nepodařilo smazat.']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Neplatná metoda.']);
}

ob_end_flush();
exit;