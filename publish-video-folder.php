<?php

// Start output buffering to prevent header issues

ob_start();



// Set error reporting

error_reporting(E_ALL);

ini_set('display_errors', 0);



session_start();



// Clear any previous output

ob_clean();



// Check if POST data was truncated due to size limits

if (empty($_POST) && empty($_FILES) && $_SERVER['CONTENT_LENGTH'] > 0) {

    $displayMaxSize = ini_get('post_max_size');

    ob_clean();

    header('Location: index.php?page=videos&status=error&message=' . urlencode("Soubor je příliš velký. Maximální velikost: $displayMaxSize. Použijte menší soubory nebo nahrajte po částech."));

    exit;

}



// Set proper headers

header('Content-Type: text/html; charset=UTF-8');

mb_internal_encoding('UTF-8');



// Include access control

require_once __DIR__ . '/access.php';



// Check if user has permission to upload

if (!isAdmin() && !isManager()) {

    ob_clean();

    header('Location: index.php?page=videos&status=error&message=' . urlencode('Nemáte oprávnění k nahrávání souborů'));

    exit;

}



if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Check if we're using custom folder name or existing folder
    $customFolderName = trim($_POST['custom_folder_name'] ?? '');
    $existingFolderName = trim($_POST['folder_name'] ?? '');
    
    // Determine which folder name to use
    if (!empty($customFolderName)) {
        $folderName = $customFolderName;
        $isNewFolder = true;
    } elseif (!empty($existingFolderName)) {
        $folderName = $existingFolderName;
        $isNewFolder = false;
    } else {
        ob_clean();
        header('Location: index.php?page=videos&status=error&message=' . urlencode('Musíte buď vytvořit novou složku nebo vybrat existující.'));
        exit;
    }
    
    if (empty($folderName)) {
        ob_clean();
        header('Location: index.php?page=videos&status=error&message=' . urlencode('Název složky je povinný.'));
        exit;
    }
    
    // Sanitize folder name for filesystem (only if it's a new folder)
    if ($isNewFolder) {
        $folderName = preg_replace('/[^a-zA-Z0-9\s\-_áčďéěíňóřšťúůýžÁČĎÉĚÍŇÓŘŠŤÚŮÝŽ]/u', '', $folderName);
        $folderName = trim($folderName);
        
        if (empty($folderName)) {
            ob_clean();
            header('Location: index.php?page=videos&status=error&message=' . urlencode('Název složky obsahuje nepovolené znaky.'));
            exit;
        }
    }
    
    $baseDir = getRoot('videos');

    $targetDir = $baseDir . $folderName . '/';

    

    // Create base directory if it doesn't exist

    if (!is_dir($baseDir)) {

        if (!mkdir($baseDir, 0755, true)) {

            ob_clean();

            header('Location: index.php?page=videos&status=error&message=' . urlencode('Nepodařilo se vytvořit základní adresář.'));

            exit;

        }

    }

    

    // Create target folder only if it's a new folder or if it doesn't exist

    if ($isNewFolder || !is_dir($targetDir)) {

        if (!mkdir($targetDir, 0755, true)) {

            ob_clean();

            header('Location: index.php?page=videos&status=error&message=' . urlencode('Nepodařilo se vytvořit složku.'));

            exit;

        }

    }

    

    // Handle file uploads

    if (isset($_FILES['video_files'])) {

        $files = $_FILES['video_files'];

        $allowedExtensions = ['mp4', 'avi', 'mov', 'mkv', 'webm', 'mp3', 'wav', 'aac'];

        $maxFileSize = 250000000; // 250MB limit for regular upload (larger files should use chunked upload)

        

        $uploadedCount = 0;

        $errorCount = 0;

        $errors = [];

        

        for ($i = 0; $i < count($files['name']); $i++) {

            if ($files['error'][$i] === UPLOAD_ERR_OK) {

                $fileName = $files['name'][$i];

                $tmpName = $files['tmp_name'][$i];

                $fileSize = $files['size'][$i];

                

                $fileExtension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

                

                if (in_array($fileExtension, $allowedExtensions)) {

                    if ($fileSize <= $maxFileSize) {

                        // Keep original filename with proper encoding

                        $targetFile = $targetDir . $fileName;

                        

                        // Handle duplicate filenames

                        $counter = 1;

                        $originalName = pathinfo($fileName, PATHINFO_FILENAME);

                        while (file_exists($targetFile)) {

                            $fileName = $originalName . '_' . $counter . '.' . $fileExtension;

                            $targetFile = $targetDir . $fileName;

                            $counter++;

                        }

                        

                        if (move_uploaded_file($tmpName, $targetFile)) {

                            // Set proper permissions

                            chmod($targetFile, 0644);

                            $uploadedCount++;

                        } else {

                            $errorCount++;

                            $errors[] = "Nepodařilo se nahrát soubor: $fileName";

                        }

                    } else {

                        $errorCount++;

                        $errors[] = "Soubor $fileName je příliš velký (max 250MB pro tuto metodu). Použijte funkci 'Přidat videa' pro větší soubory.";

                    }

                } else {

                    $errorCount++;

                    $errors[] = "Nepodporovaný formát souboru: $fileName";

                }

            } else {

                $errorCount++;

                switch ($files['error'][$i]) {

                    case UPLOAD_ERR_INI_SIZE:

                    case UPLOAD_ERR_FORM_SIZE:

                        $errors[] = "Soubor " . $files['name'][$i] . " je příliš velký";

                        break;

                    case UPLOAD_ERR_PARTIAL:

                        $errors[] = "Soubor " . $files['name'][$i] . " byl nahrán pouze částečně";

                        break;

                    default:

                        $errors[] = "Chyba při nahrávání souboru: " . $files['name'][$i];

                        break;

                }

            }

        }

        

        ob_clean();

        if ($uploadedCount > 0) {

            $actionText = $isNewFolder ? "vytvořena složka a nahráno" : "nahráno";

            $message = "Úspěšně $actionText $uploadedCount souborů do složky '$folderName'";

            if ($errorCount > 0) {

                $message .= ". Chyby: " . implode(', ', array_slice($errors, 0, 2));

                if (count($errors) > 2) {

                    $message .= " a další...";

                }

            }

            header('Location: index.php?page=videos&status=success&message=' . urlencode($message));

        } else {

            $errorMessage = 'Žádné soubory nebyly nahrány';

            if (!empty($errors)) {

                $errorMessage .= '. ' . implode(', ', array_slice($errors, 0, 2));

                if (count($errors) > 2) {

                    $errorMessage .= ' a další...';

                }

            }

            header('Location: index.php?page=videos&status=error&message=' . urlencode($errorMessage));

        }

        exit;

    }

}



ob_clean();

header('Location: index.php?page=videos');

exit;

?>

