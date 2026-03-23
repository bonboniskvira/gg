<?php
// 1. Zabezpečení a přístup
require_once 'access.php'; // Tady máme isAdmin() a getRoot()
header('Content-Type: application/json');

if (!isAdmin()) {
    echo json_encode(['success' => false, 'message' => 'Nedostatečná oprávnění']);
    exit;
}

// 2. Parametry
$type = $_POST['type'] ?? $_GET['type'] ?? '';
$target = basename($_POST['category'] ?? $_GET['category'] ?? '');

if (empty($type) || empty($target)) {
    echo json_encode(['success' => false, 'message' => 'Chybí parametry']);
    exit;
}

/**
 * REKURZIVNÍ MAZÁNÍ - Definujeme přímo tady, aby skript byl nezávislý
 */
function universalDeleteFolder($dir) {
    if (!is_dir($dir)) return false;

    $files = array_diff(scandir($dir), ['.', '..']);
    foreach ($files as $file) {
        $filePath = $dir . DIRECTORY_SEPARATOR . $file;
        // Pokud je to složka, zavolej se znovu (rekurze), jinak smaž soubor
        is_dir($filePath) ? universalDeleteFolder($filePath) : unlink($filePath);
    }
    return rmdir($dir); // Nakonec smaž prázdnou složku
}

// 3. Mapa modulů - Tady definujeme, kam skript smí sáhnout
// getRoot($type) v access.php nám vyřeší i ty verze pro poradce!
$allowedModules = ['doc', 'videos', 'marketing', 'pdf', 'other', 'links', 'podcast', 'agreements', 'courses', 'edo', 'seznam', 'test'];

if (!in_array($type, $allowedModules)) {
    echo json_encode(['success' => false, 'message' => 'Tento modul není povolen k mazání']);
    exit;
}

// 4. Sestavení a ověření cesty (Bezpečnostní firewall)
$basePath = getRoot($type); // Cesta z access.php
$rootPath = realpath($basePath);
$targetPath = realpath($rootPath . DIRECTORY_SEPARATOR . $target);

// Kontrola, jestli složka existuje a jestli se nepokoušíme "vyskočit" z rootu
if (!$targetPath || !is_dir($targetPath) || strpos($targetPath, $rootPath) !== 0) {
    echo json_encode(['success' => false, 'message' => 'Složka nenalezena nebo porušení zabezpečení']);
    exit;
}

// 5. Samotná akce
if (universalDeleteFolder($targetPath)) {
    echo json_encode(['success' => true, 'message' => "Kategorie '{$target}' byla úspěšně odstraněna."]);
} else {
    echo json_encode(['success' => false, 'message' => 'Chyba při mazání složky z disku.']);
}