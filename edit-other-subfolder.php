<?php
// Prevent any output before headers
ob_start();

session_start();
require_once 'access.php';

// Clean any previous output
ob_clean();

// Set headers
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-cache, must-revalidate');

// Only admins can edit subfolders
if (!isAdmin()) {
    echo json_encode(['success' => false, 'message' => 'Insufficient permissions']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $category = $_POST['parent_category'] ?? '';
    $originalName = trim($_POST['original_name'] ?? '');
    $newName = trim($_POST['new_name'] ?? '');
    $customFilename = trim($_POST['custom_filename'] ?? '');
    
    if (empty($category) || empty($originalName) || empty($newName)) {
        echo json_encode(['success' => false, 'message' => 'Missing required parameters']);
        exit;
    }
    
    // Use new subfolder name as-is - no sanitization to preserve Czech characters
    $safeName = trim($newName);
    
    if (empty($safeName)) {
        echo json_encode(['success' => false, 'message' => 'Invalid subfolder name']);
        exit;
    }
    
    // Create paths
    $categoryPath = __DIR__ . "/other/{$category}/";
    $originalPath = $categoryPath . $originalName . '/';
    $newPath = $categoryPath . $safeName . '/';
    
    // Check if original subfolder exists
    if (!is_dir($originalPath)) {
        echo json_encode(['success' => false, 'message' => 'Original subfolder does not exist']);
        exit;
    }
    
    $folderRenamed = false;
    $fileUploaded = false;
    
    // Handle folder renaming if names are different
    if ($originalName !== $safeName) {
        // Check if new name already exists
        if (is_dir($newPath)) {
            echo json_encode(['success' => false, 'message' => 'A subfolder with this name already exists']);
            exit;
        }
        
        // Rename the directory
        if (rename($originalPath, $newPath)) {
            $folderRenamed = true;
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to rename subfolder']);
            exit;
        }
    }
    
    // Use the correct path (either renamed or original)
    $targetPath = $folderRenamed ? $newPath : $originalPath;
    
    // Handle file upload if provided
    if (isset($_FILES['subfolder_file']) && $_FILES['subfolder_file']['error'] === UPLOAD_ERR_OK) {
        $originalFileName = $_FILES['subfolder_file']['name'];
        $fileExtension = strtolower(pathinfo($originalFileName, PATHINFO_EXTENSION));
        
        if ($_FILES['subfolder_file']['size'] <= 100 * 1024 * 1024) { // 100MB limit
            // Determine final filename - no sanitization to preserve Czech characters
            if (!empty($customFilename)) {
                // Use custom filename if provided
                $fileName = $customFilename . '.' . $fileExtension;
            } else {
                // Use original filename
                $fileName = $originalFileName;
            }
            
            $uploadPath = $targetPath . $fileName;
            
            // Handle duplicate filenames
            $counter = 1;
            while (file_exists($uploadPath)) {
                $nameWithoutExt = pathinfo($fileName, PATHINFO_FILENAME);
                $uploadPath = $targetPath . $nameWithoutExt . '_' . $counter . '.' . $fileExtension;
                $counter++;
            }
            
            if (move_uploaded_file($_FILES['subfolder_file']['tmp_name'], $uploadPath)) {
                $fileUploaded = true;
            }
        }
    }
    
    // Build success message
    $messages = [];
    if ($folderRenamed) {
        $messages[] = "podsložka '{$originalName}' byla přejmenována na '{$safeName}'";
    }
    if ($fileUploaded) {
        $uploadedFileName = basename($uploadPath);
        $messages[] = "soubor '{$uploadedFileName}' byl nahrán";
    }
    
    if (!empty($messages)) {
        $message = "Úspěšně: " . implode(' a ', $messages) . ".";
        echo json_encode(['success' => true, 'message' => $message]);
    } else {
        echo json_encode(['success' => true, 'message' => 'No changes made']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
}
exit;
?>
