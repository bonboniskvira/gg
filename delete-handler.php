<?php
require_once 'access.php';
header('Content-Type: application/json');

if (!isAdmin()) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

// Získání parametrů (např. z URL: delete-handler.php?type=doc&category=test)
$type = $_POST['type'] ?? $_GET['type'] ?? '';
$category = basename($_POST['category'] ?? $_GET['category'] ?? '');

if (empty($type) || empty($category)) {
    echo json_encode(['success' => false, 'message' => 'Missing parameters']);
    exit;
}

// Mapa složek - tady definuješ, co se kam ukládá
$paths = [
    'doc'       => getRoot('doc'),
    'podcasts'     => getRoot('podcasts'),
    'agreement' => getRoot('agreement'),
    // ... sem dopíšeš zbytek z těch 216 typů
];

if (!isset($paths[$type])) {
    echo json_encode(['success' => false, 'message' => 'Invalid type']);
    exit;
}

$rootPath = realpath($paths[$type]);
$targetPath = realpath($rootPath . DIRECTORY_SEPARATOR . $category);

// Tvůj neprůstřelný security check
if (!$targetPath || !is_dir($targetPath) || strpos($targetPath, $rootPath) !== 0) {
    echo json_encode(['success' => false, 'message' => 'Security violation or not found']);
    exit;
}

// Tady zavoláš tu svou rekurzivní funkci deleteDocDirectory($targetPath)
if (deleteDocDirectory($targetPath)) {
    echo json_encode(['success' => true, 'message' => 'Smazáno']);
} else {
    echo json_encode(['success' => false, 'message' => 'Chyba při mazání']);
}