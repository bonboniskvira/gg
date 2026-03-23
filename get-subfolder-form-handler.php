<?php
ini_set('display_errors', 0);
error_reporting(E_ALL);

ob_start();
session_start();
require_once 'access.php';

if (ob_get_length()) ob_clean();
header('Content-Type: application/json; charset=utf-8');

try {
    if (!isset($_SESSION['user_id'])) {
        throw new Exception('Uživatel není přihlášen.');
    }

    // BEZPEČNOST: Whitelist povolených modulů
    $allowedTypes = ['doc', 'edo', 'videos', 'marketing', 'pdf', 'courses', 'agreements', 'seznam', 'test'];
    $type = $_GET['type'] ?? 'edo';

    if (!in_array($type, $allowedTypes)) {
        throw new Exception("Nepovolený typ modulu.");
    }

    $baseDir = getRoot($type);

    if (!$baseDir || !is_dir($baseDir)) {
        throw new Exception("Adresář pro typ '{$type}' neexistuje.");
    }

    $subfolderData = [];
    $categories = array_diff(scandir($baseDir), ['.', '..']);

    foreach ($categories as $categoryKey) {
        // Cestu ošetříme pomocí rtrim a lomítka, aby to fungovalo na Windows i Linuxu
        $categoryPath = rtrim($baseDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $categoryKey;

        if (is_dir($categoryPath) && strpos($categoryKey, '.') !== 0) {
            $subfolders = [];
            $items = array_diff(scandir($categoryPath), ['.', '..']);
            foreach ($items as $item) {
                if (is_dir($categoryPath . DIRECTORY_SEPARATOR . $item)) {
                    $subfolders[] = $item;
                }
            }
            $subfolderData[$categoryKey] = $subfolders;
        }
    }

    echo json_encode($subfolderData, JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'error' => true,
        'message' => $e->getMessage()
    ]);
}

ob_end_flush();
exit;