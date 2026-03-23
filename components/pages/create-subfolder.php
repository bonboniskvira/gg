<?php
// Set proper encoding for Czech characters
mb_internal_encoding('UTF-8');
mb_http_output('UTF-8');

require_once 'access.php';

// Check if user can upload (only they can create subfolders)
if (!canUpload()) {
    header('Location: documents.php?error=' . urlencode('Nemáte oprávnění k vytváření podsložek.'));
    exit;
}

// Check if POST request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: documents.php?error=' . urlencode('Neplatný požadavek.'));
    exit;
}

// Get and validate input
$parentCategory = trim($_POST['parent_category'] ?? '');
$subfolderName = trim($_POST['subfolder_name'] ?? '');

// Validate parent category
$documentCategories = [
    'general' => ['name' => 'Obecné', 'color' => '#3498db'],
    'contracts' => ['name' => 'Smlouvy', 'color' => '#e74c3c'],
    'reports' => ['name' => 'Zprávy', 'color' => '#2ecc71'],
    'presentations' => ['name' => 'Prezentace', 'color' => '#f39c12'],
    'manuals' => ['name' => 'Manuály', 'color' => '#9b59b6'],
    'forms' => ['name' => 'Formuláře', 'color' => '#16a085'],
    'training' => ['name' => 'Školení', 'color' => '#e67e22'],
    'ebooks' => ['name' => 'Ebooky', 'color' => '#e64e72'],
    'other' => ['name' => 'Ostatní', 'color' => '#95a5a6']
];

if (empty($parentCategory) || !isset($documentCategories[$parentCategory])) {
    header('Location: documents.php?error=' . urlencode('Neplatná kategorie.'));
    exit;
}

if (empty($subfolderName)) {
    header('Location: documents.php?error=' . urlencode('Název podsložky je povinný.'));
    exit;
}

// Sanitize subfolder name (remove dangerous characters)
$subfolderName = preg_replace('/[^a-zA-Z0-9\s\-_áčďéěíňóřšťúůýžÁČĎÉĚÍŇÓŘŠŤÚŮÝŽ]/', '', $subfolderName);
$subfolderName = trim($subfolderName);

if (empty($subfolderName)) {
    header('Location: documents.php?error=' . urlencode('Název podsložky obsahuje nepovolené znaky.'));
    exit;
}

// Check length
if (strlen($subfolderName) > 50) {
    header('Location: documents.php?error=' . urlencode('Název podsložky je příliš dlouhý (max 50 znaků).'));
    exit;
}

// Create the subfolder path
$documentsDir = __DIR__ . '/../../doc/';
$categoryDir = $documentsDir . $parentCategory . '/';
$subfolderPath = $categoryDir . $subfolderName . '/';

// Check if category directory exists
if (!is_dir($categoryDir)) {
    // Create category directory if it doesn't exist
    if (!mkdir($categoryDir, 0755, true)) {
        header('Location: documents.php?error=' . urlencode('Nepodařilo se vytvořit hlavní složku.'));
        exit;
    }
}

// Check if subfolder already exists
if (is_dir($subfolderPath)) {
    header('Location: documents.php?error=' . urlencode('Podsložka s tímto názvem již existuje.'));
    exit;
}

// Create the subfolder
if (mkdir($subfolderPath, 0755, true)) {
    // Success - redirect back with success message
    $categoryName = $documentCategories[$parentCategory]['name'];
    $successMessage = "Podsložka '{$subfolderName}' byla úspěšně vytvořena v kategorii '{$categoryName}'.";
    header('Location: documents.php?success=' . urlencode($successMessage));
} else {
    // Error creating folder
    header('Location: documents.php?error=' . urlencode('Nepodařilo se vytvořit podsložku. Zkontrolujte oprávnění.'));
}
exit;
?>