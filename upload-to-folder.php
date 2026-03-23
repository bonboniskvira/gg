<?php



// Start output buffering to prevent any accidental output

ob_start();



// Set error reporting

error_reporting(E_ALL);

ini_set('display_errors', 0); // Don't display errors in JSON response



session_start();



// Clear any previous output

ob_clean();



require_once __DIR__ . '/access.php';



header('Content-Type: application/json; charset=UTF-8');

mb_internal_encoding('UTF-8');



// Check if user has permission to upload

if (!isAdmin() && !isManager()) {

    http_response_code(403);

    echo json_encode(['success' => false, 'message' => 'Nemáte oprávnění k nahrávání souborů']);

    exit;

}



// Check for upload errors first

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Check if POST data was truncated due to size limits

    if (empty($_POST) && empty($_FILES) && $_SERVER['CONTENT_LENGTH'] > 0) {

        $displayMaxSize = ini_get('post_max_size');

        echo json_encode(['success' => false, 'message' => "Soubor je příliš velký. Maximální velikost: $displayMaxSize"]);

        exit;

    }

    

    $targetFolder = trim($_POST['target_folder'] ?? '');

    

    if (empty($targetFolder)) {

        echo json_encode(['success' => false, 'message' => 'Název složky je povinný']);

        exit;

    }

    

    // Sanitize folder name to prevent directory traversal

    $targetFolder = basename($targetFolder);

    

    $baseDir = getRoot('videos');

    $targetDir = $baseDir . $targetFolder . '/';

    

    // Check if target folder exists

    if (!is_dir($targetDir)) {

        echo json_encode(['success' => false, 'message' => 'Cílová složka neexistuje']);

        exit;

    }

    

    // Handle file uploads

    if (isset($_FILES['additional_files'])) {

        $files = $_FILES['additional_files'];

        $allowedExtensions = ['jpg', 'mp4', 'jpeg', 'png', 'mp3', 'wav', 'avi', 'wmw', 'mkv', 'mov', 'aac'];

        

        // Get PHP upload limits

        $maxFileSize = min(

            parseSize(ini_get('upload_max_filesize')),

            parseSize(ini_get('post_max_size')),

            250000000 // 250MB application limit (updated from 1.2GB to match JS)

        );

        

        $uploadedCount = 0;

        $errorCount = 0;

        $errors = [];

        

        for ($i = 0; $i < count($files['name']); $i++) {

            // Check for upload errors

            switch ($files['error'][$i]) {

                case UPLOAD_ERR_OK:

                    // No error, process file

                    break;

                case UPLOAD_ERR_INI_SIZE:

                case UPLOAD_ERR_FORM_SIZE:

                    $errorCount++;

                    $errors[] = "Soubor {$files['name'][$i]} je příliš velký";

                    continue 2;

                case UPLOAD_ERR_PARTIAL:

                    $errorCount++;

                    $errors[] = "Soubor {$files['name'][$i]} byl nahrán pouze částečně";

                    continue 2;

                case UPLOAD_ERR_NO_FILE:

                    continue 2; // Skip empty file slots

                default:

                    $errorCount++;

                    $errors[] = "Chyba při nahrávání souboru {$files['name'][$i]}";

                    continue 2;

            }

            

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

                    $maxSizeMB = round($maxFileSize / 1024 / 1024);

                    $errors[] = "Soubor $fileName je příliš velký (max {$maxSizeMB}MB)";

                }

            } else {

                $errorCount++;

                $errors[] = "Nepodporovaný formát souboru: $fileName";

            }

        }

        

        if ($uploadedCount > 0) {

            $message = "Úspěšně nahráno $uploadedCount souborů do složky '$targetFolder'";

            if ($errorCount > 0) {

                $message .= ". Chyby: " . implode(', ', array_slice($errors, 0, 3)); // Limit error messages

                if (count($errors) > 3) {

                    $message .= " a další...";

                }

            }

            echo json_encode(['success' => true, 'message' => $message, 'uploaded' => $uploadedCount, 'errors' => $errorCount]);

        } else {

            $errorMessage = 'Žádné soubory nebyly nahrány';

            if (!empty($errors)) {

                $errorMessage .= '. ' . implode(', ', array_slice($errors, 0, 2));

                if (count($errors) > 2) {

                    $errorMessage .= ' a další...';

                }

            }

            echo json_encode(['success' => false, 'message' => $errorMessage]);

        }

    } else {

        echo json_encode(['success' => false, 'message' => 'Žádné soubory nebyly vybrány']);

    }

} else {

    echo json_encode(['success' => false, 'message' => 'Neplatná metoda požadavku']);

}



// Helper function to parse size strings like "250M" to bytes

function parseSize($size) {

    $unit = preg_replace('/[^bkmgtpezy]/i', '', $size);

    $size = preg_replace('/[^0-9\.]/', '', $size);

    if ($unit) {

        return round($size * pow(1024, stripos('bkmgtpezy', $unit[0])));

    } else {

        return round($size);

    }

}



exit;

?>

