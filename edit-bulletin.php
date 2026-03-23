<?php
// Start output buffering to prevent header issues
ob_start();

session_start();
require_once 'access.php';

// Only admins can edit posts
if (!isAdmin()) {
    ob_clean();
    header("Location: index.php");
    exit;
}

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['post_id']) && isset($_POST['title']) && isset($_POST['content'])) {
    $post_id = $_POST['post_id'];
    $title = trim($_POST['title']);
    $content = trim($_POST['content']);
    $pinned = isset($_POST['pinned']) && $_POST['pinned'] == 1 ? 1 : 0;
    $newImageName = null;

    // Basic validation
    if (empty($title) || empty($content)) {
        ob_clean();
        header("Location: index.php#bulletin");
        exit;
    }

    // Database connection
    $host = 'md396.wedos.net';
    $dbname = 'd394711_main';
    $username = 'w394711_main';
    $password = 'mnpJtgaJ';

    try {
        $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // Get current image name
        $stmt = $pdo->prepare("SELECT image FROM bulletin_posts WHERE id = :post_id");
        $stmt->bindParam(':post_id', $post_id);
        $stmt->execute();
        $currentPost = $stmt->fetch(PDO::FETCH_ASSOC);
        $currentImage = $currentPost['image'];

        // Handle image upload if new image is provided
        if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
            $uploadDir = 'img_bulletin/';

            // Create directory if it doesn't exist with proper permissions
            if (!is_dir($uploadDir)) {
                if (!mkdir($uploadDir, 0755, true)) {
                    throw new Exception("Nepodařilo se vytvořit složku pro obrázky.");
                }

                // Create .htaccess file to ensure images are accessible
                $htaccessContent = "Options +Indexes\nOrder allow,deny\nAllow from all\n";
                file_put_contents($uploadDir . '.htaccess', $htaccessContent);
            }

            $allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif'];
            $maxFileSize = 5 * 1024 * 1024; // 5MB

            $fileType = $_FILES['image']['type'];
            $fileSize = $_FILES['image']['size'];

            if (!in_array($fileType, $allowedTypes)) {
                throw new Exception("Nepodporovaný formát obrázku. Použijte JPG, PNG nebo GIF.");
            }

            if ($fileSize > $maxFileSize) {
                throw new Exception("Obrázek je příliš velký. Maximální velikost je 5MB.");
            }

            // Generate unique filename
            $extension = pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION);
            $newImageName = uniqid('bulletin_') . '.' . strtolower($extension);
            $uploadPath = $uploadDir . $newImageName;

            if (!move_uploaded_file($_FILES['image']['tmp_name'], $uploadPath)) {
                throw new Exception("Chyba při nahrávání obrázku.");
            }

            // Set proper file permissions
            chmod($uploadPath, 0644);

            // Delete old image if it exists
            if ($currentImage && file_exists($uploadDir . $currentImage)) {
                unlink($uploadDir . $currentImage);
            }
        } else {
            // Keep current image
            $newImageName = $currentImage;
        }

        $stmt = $pdo->prepare("UPDATE bulletin_posts SET title = :title, content = :content, image = :image, pinned = :pinned WHERE id = :post_id");
        $stmt->bindParam(':title', $title);
        $stmt->bindParam(':content', $content);
        $stmt->bindParam(':image', $newImageName);
        $stmt->bindParam(':pinned', $pinned);
        $stmt->bindParam(':post_id', $post_id);
        $stmt->execute();

        // Clear output buffer and redirect
        ob_clean();
        header("Location: index.php#bulletin");
        exit;
    } catch (Exception $e) {
        // If there was an error and new image was uploaded, delete it
        if (isset($uploadPath) && file_exists($uploadPath)) {
            unlink($uploadPath);
        }

        error_log("Error editing bulletin post: " . $e->getMessage());
        ob_clean();
        die("Error: " . $e->getMessage());
    }
}

// If we get here, something went wrong
ob_clean();
header("Location: index.php#bulletin");
exit;
?>
