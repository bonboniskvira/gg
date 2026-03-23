<?php
// delete-file-handler.php - Univerzální zabiják souborů
ob_start();
session_start();

require_once 'access.php';

// Vyčistíme buffer a nastavíme JSON headers
ob_clean();
header('Content-Type: application/json; charset=utf-8');

// Jen admini můžou mazat, to dá rozum
if (!isAdmin()) {
    echo json_encode(['success' => false, 'message' => 'Nedostatečná oprávnění pro mazání']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Co mažeme (agreements, contacts, doc, marketing, atd.)
    $type = $_POST['type'] ?? '';
    $category = $_POST['category'] ?? '';
    $subfolder = $_POST['subfolder'] ?? '';
    $fileName = $_POST['file_name'] ?? $_POST['agreement_file'] ?? ''; // Chytáme oba názvy pro kompatibilitu

    // Základní validace
    if (empty($type) || empty($category) || empty($fileName)) {
        echo json_encode(['success' => false, 'message' => 'Chybí parametry pro smazání']);
        exit;
    }

    // Bezpečnostní kontrola proti "path traversal" (aby nám někdo nesmazal index.php přes ../)
    $checkInputs = [$type, $category, $subfolder, $fileName];
    foreach ($checkInputs as $input) {
        if (strpos($input, '..') !== false || strpos($input, '/') !== false || strpos($input, '\\') !== false) {
            echo json_encode(['success' => false, 'message' => 'Neplatný název souboru nebo cesty']);
            exit;
        }
    }

    try {
        // Použijeme tvůj getRoot pro získání základní cesty (např. C:/xampp/htdocs/edosys/agreements/)
        $baseDir = getRoot($type);

        // Sestavíme cestu k souboru
        $filePath = $baseDir . $category . '/';
        if (!empty($subfolder)) {
            $filePath .= $subfolder . '/';
        }
        $fullPath = $filePath . $fileName;

        // Existuje to vůbec?
        if (!file_exists($fullPath)) {
            echo json_encode(['success' => false, 'message' => 'Soubor nenalezen: ' . $fileName]);
            exit;
        }

        // Tady to reálně umírá
        if (unlink($fullPath)) {
            echo json_encode([
                'success' => true,
                'message' => "Soubor '{$fileName}' byl úspěšně smazán z '{$type}/{$category}" . ($subfolder ? "/{$subfolder}" : "") . "'."
            ]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Chyba při mazání souboru ze serveru']);
        }

    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Systémová chyba: ' . $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Neplatná metoda požadavku']);
}

ob_end_flush();
exit;