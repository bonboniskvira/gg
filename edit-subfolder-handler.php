<?php
// Prevence jakéhokoliv výstupu před hlavičkami
ob_start();
session_start();
require_once 'access.php';

// Vyčištění bufferu
if (ob_get_level()) ob_clean();

// Nastavení JSON hlavičky
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-cache, must-revalidate');

// Pouze admini mohou upravovat podsložky
if (!isAdmin()) {
    echo json_encode(['success' => false, 'message' => 'Nedostatečná oprávnění']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // UNIVERZÁLNÍ TYP (podcast, edo, doc, marketing...)
    $type = $_POST['type'] ?? 'doc';
    $category = trim($_POST['parent_category'] ?? '');
    $originalName = trim($_POST['original_name'] ?? '');
    $newName = trim($_POST['new_name'] ?? '');
    $customFilename = trim($_POST['custom_filename'] ?? '');

    if (empty($category) || empty($originalName) || empty($newName)) {
        echo json_encode(['success' => false, 'message' => "Chybí povinné údaje (Cat: $category, Orig: $originalName, New: $newName)"]);
        exit;
    }

    // Získání cesty pomocí getRoot(type)
    $baseDir = getRoot($type);
    $categoryPath = $baseDir . $category . '/';
    $originalPath = $categoryPath . $originalName;

    // Čištění nového názvu - zachováme CZ znaky
    $safeName = preg_replace('/[<>:"|*?\\\\\/]/', '', $newName);
    $safeName = trim($safeName);


    if (empty($safeName)) {
        echo json_encode(['success' => false, 'message' => 'Neplatný název podsložky']);
        exit;
    }

    $newPath = $categoryPath . $safeName;

    // Kontrola, zda původní podsložka existuje
    if (!is_dir($originalPath)) {
        echo json_encode(['success' => false, 'message' => 'Původní podsložka neexistuje']);
        exit;
    }

    $folderRenamed = false;
    $fileUploaded = false;

    // 1. LOGIKA PŘEJMENOVÁNÍ
    if ($originalName !== $safeName) {
        if (is_dir($newPath)) {
            echo json_encode(['success' => false, 'message' => 'Podsložka s tímto názvem již existuje']);
            exit;
        }

        if (rename($originalPath, $newPath)) {
            $folderRenamed = true;
        } else {
            echo json_encode(['success' => false, 'message' => 'Nepodařilo se přejmenovat podsložku']);
            exit;
        }
    }

    // Použijeme správnou cestu (buď přejmenovanou nebo původní)
    $targetPath = $folderRenamed ? $newPath : $originalPath;
    if (!str_ends_with($targetPath, '/')) {
        $targetPath .= '/';
    }

    // 2. LOGIKA UPLOADU SOUBORU
    if (isset($_FILES['subfolder_file']) && $_FILES['subfolder_file']['error'] === UPLOAD_ERR_OK) {
        $originalFileName = $_FILES['subfolder_file']['name'];
        $fileExtension = strtolower(pathinfo($originalFileName, PATHINFO_EXTENSION));

        // Rozšířený seznam povolených přípon pro všechny typy (audio, video, dokumenty)
        $allowedExtensions = ['mp3', 'wav', 'ogg', 'm4a', 'aac', 'mp4', 'avi', 'mov', 'wmv', 'pdf', 'doc', 'docx', 'xls', 'xlsx', 'txt'];

        if (in_array($fileExtension, $allowedExtensions)) {
            // Určení finálního názvu souboru
            if (!empty($customFilename)) {
                $fileName = $customFilename . '.' . $fileExtension;
            } else {
                $fileName = $originalFileName;
            }

            $uploadPath = $targetPath . $fileName;

            // Ošetření duplicitních názvů souborů (přidání _1, _2...)
            $counter = 1;
            while (file_exists($uploadPath)) {
                $nameWithoutExt = pathinfo($fileName, PATHINFO_FILENAME);
                $uploadPath = $targetPath . $nameWithoutExt . '_' . $counter . '.' . $fileExtension;
                $counter++;
            }

            if (move_uploaded_file($_FILES['subfolder_file']['tmp_name'], $uploadPath)) {
                chmod($uploadPath, 0644);
                $fileUploaded = true;
            }
        }
    }

    // Sestavení zprávy o úspěchu
    $messages = [];
    if ($folderRenamed) $messages[] = "podsložka byla přejmenována na '{$safeName}'";
    if ($fileUploaded) $messages[] = "soubor byl nahrán";

    if (!empty($messages)) {
        echo json_encode(['success' => true, 'message' => "Úspěšně: " . implode(' a ', $messages) . "."]);
    } else {
        echo json_encode(['success' => true, 'message' => 'Změny byly uloženy (beze změn v souborech)']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Neplatná metoda požadavku']);
}

ob_end_flush();
exit;