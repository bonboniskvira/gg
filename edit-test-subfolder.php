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
    
    // Remove hardcoded category validation - allow any category
    // Validate category name to prevent directory traversal
    if (strpos($category, '..') !== false || strpos($category, '/') !== false || strpos($category, '\\') !== false) {
        echo json_encode(['success' => false, 'message' => 'Invalid category name']);
        exit;
    }
    
    // Validate names to prevent directory traversal
    if (strpos($originalName, '..') !== false || strpos($originalName, '/') !== false || strpos($originalName, '\\') !== false ||
        strpos($newName, '..') !== false || strpos($newName, '/') !== false || strpos($newName, '\\') !== false) {
        echo json_encode(['success' => false, 'message' => 'Invalid folder name']);
        exit;
    }
    
    // Use new name directly without sanitization
    $safeName = trim($newName);
    
    if (empty($safeName)) {
        echo json_encode(['success' => false, 'message' => 'Invalid subfolder name']);
        exit;
    }
    
    // Create paths
    $categoryPath = __DIR__ . "/test/{$category}/";
    $folderRenamed = false;
    $targetPath = $categoryPath; // Default to category path
    
    // Check if we're renaming a category (when originalName equals category)
    if ($originalName === $category) {
        // This is a category rename, not a subfolder rename
        $originalPath = $categoryPath;
        $newPath = __DIR__ . "/test/{$safeName}/";
        
        // Check if original category exists
        if (!is_dir($originalPath)) {
            echo json_encode(['success' => false, 'message' => 'Original category does not exist']);
            exit;
        }
        
        // Only rename if the names are different
        if ($originalName !== $safeName) {
            // Check if new name already exists
            if (is_dir($newPath)) {
                echo json_encode(['success' => false, 'message' => 'A category with this name already exists']);
                exit;
            }
            
            // Rename the category directory
            if (rename($originalPath, $newPath)) {
                $folderRenamed = true;
                $targetPath = $newPath; // Use the new path for file upload
            } else {
                echo json_encode(['success' => false, 'message' => 'Failed to rename category']);
                exit;
            }
        } else {
            // Names are the same, no rename needed, but set target path for file upload
            $targetPath = $originalPath;
        }
    } else {
        // Original subfolder logic for subfolders within categories
        $originalPath = $categoryPath . $originalName . '/';
        $newPath = $categoryPath . $safeName . '/';
        
        // Check if original subfolder exists
        if (!is_dir($originalPath)) {
            echo json_encode(['success' => false, 'message' => 'Original subfolder does not exist']);
            exit;
        }
        
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
                $targetPath = $newPath;
            } else {
                echo json_encode(['success' => false, 'message' => 'Failed to rename subfolder']);
                exit;
            }
        } else {
            // Names are the same, no rename needed, but set target path for file upload
            $targetPath = $originalPath;
        }
    }
    
    $fileUploaded = false;
    
    // Handle file upload - check for both 'subfolder_file' and 'category_file'
    $uploadedFile = null;
    if (isset($_FILES['subfolder_file']) && $_FILES['subfolder_file']['error'] === UPLOAD_ERR_OK) {
        $uploadedFile = $_FILES['subfolder_file'];
    } elseif (isset($_FILES['category_file']) && $_FILES['category_file']['error'] === UPLOAD_ERR_OK) {
        $uploadedFile = $_FILES['category_file'];
    }
    
    if ($uploadedFile) {
        $originalFileName = $uploadedFile['name'];
        $fileExtension = strtolower(pathinfo($originalFileName, PATHINFO_EXTENSION));
        $allowedExtensions = ['mp3', 'wav', 'ogg', 'm4a', 'aac', 'mp4', 'avi', 'mov', 'wmv', 'flv', 'webm', 'mkv', 'pdf', 'txt', 'doc', 'docx', 'rtf'];
        
        if (in_array($fileExtension, $allowedExtensions) && $uploadedFile['size'] <= 500 * 1024 * 1024) {
            // Use filename directly without sanitization
            if (!empty($customFilename)) {
                $fileName = $customFilename . '.' . $fileExtension;
            } else {
                $fileName = $originalFileName;
            }
            
            $uploadPath = $targetPath . $fileName;
            
            // Handle duplicate filenames with incremental numbering
            $counter = 1;
            while (file_exists($uploadPath)) {
                $baseName = pathinfo($fileName, PATHINFO_FILENAME);
                $extension = pathinfo($fileName, PATHINFO_EXTENSION);
                $newFileName = $baseName . " ({$counter})." . $extension;
                $uploadPath = $targetPath . $newFileName;
                $counter++;
            }
            
            if (move_uploaded_file($uploadedFile['tmp_name'], $uploadPath)) {
                $fileUploaded = true;
            }
        }
    }
    
    // Build success message
    $messages = [];
    if ($folderRenamed) {
        if ($originalName === $category) {
            $messages[] = "kategorie '{$originalName}' byla přejmenována na '{$safeName}'";
        } else {
            $messages[] = "podsložka '{$originalName}' byla přejmenována na '{$safeName}'";
        }
    }
    if ($fileUploaded) {
        $uploadedFileName = basename($uploadPath);
        $messages[] = "soubor '{$uploadedFileName}' byl nahrán";
    }
    
    if (!empty($messages)) {
        $message = "Úspěšně: " . implode(' a ', $messages) . ".";
        echo json_encode(['success' => true, 'message' => $message, 'refresh' => true], JSON_UNESCAPED_UNICODE);
    } else if ($originalName === $safeName && !$fileUploaded) {
        // No changes were made
        echo json_encode(['success' => true, 'message' => 'Žádné změny nebyly provedeny', 'refresh' => false], JSON_UNESCAPED_UNICODE);
    } else {
        echo json_encode(['success' => true, 'message' => 'Operace dokončena', 'refresh' => true], JSON_UNESCAPED_UNICODE);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
}
exit;
?>
