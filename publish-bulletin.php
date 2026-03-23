<?php
// Start output buffering to prevent header issues
ob_start();

session_start();
require_once 'access.php';

// Only users with upload permissions can post
if (!canUpload()) {
    ob_clean(); // Clear any output
    header("Location: index.php");
    exit;
}

// Check if form was submitted
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['title']) && isset($_POST['content'])) {
    // Database connection
    $host = 'md396.wedos.net';
    $dbname = 'd394711_main';
    $username = 'w394711_main';
    $password = 'mnpJtgaJ';

    try {
        $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // Get and sanitize form data
        $title = trim($_POST['title']);
        $content = trim($_POST['content']);
        $imageName = null;

        // Check if post should be pinned (admins only)
        $pinned = 0;
        if (isAdmin() && isset($_POST['pinned']) && $_POST['pinned'] == 1) {
            $pinned = 1;
        }

        // Handle image upload
        if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
            $uploadDir = __DIR__ . '/img_bulletin/';
            $webPath = 'img_bulletin/';

            // Create directory if it doesn't exist with proper permissions
            if (!is_dir($uploadDir)) {
                if (!mkdir($uploadDir, 0755, true)) {
                    throw new Exception("Nepodařilo se vytvořit složku pro obrázky.");
                }
            }

            // Ensure directory has correct permissions
            chmod($uploadDir, 0755);

            // Create index.php to prevent directory listing
            $indexContent = "<?php\n// Directory access denied\nheader('HTTP/1.1 403 Forbidden');\nexit;\n?>";
            file_put_contents($uploadDir . 'index.php', $indexContent);

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
            $imageName = uniqid('bulletin_') . '.' . strtolower($extension);
            $uploadPath = $uploadDir . $imageName;

            if (!move_uploaded_file($_FILES['image']['tmp_name'], $uploadPath)) {
                throw new Exception("Chyba při nahrávání obrázku.");
            }

            // Set proper file permissions
            chmod($uploadPath, 0644);

            // Verify file was created and is readable
            if (!file_exists($uploadPath) || !is_readable($uploadPath)) {
                throw new Exception("Obrázek byl nahrán, ale není dostupný.");
            }
        }

        // Insert the post
        $stmt = $pdo->prepare("
            INSERT INTO bulletin_posts (user_id, username, title, content, image, pinned) 
            VALUES (:user_id, :username, :title, :content, :image, :pinned)
        ");

        $stmt->bindParam(':user_id', $_SESSION['user_id']);
        $stmt->bindParam(':username', $_SESSION['username']);
        $stmt->bindParam(':title', $title);
        $stmt->bindParam(':content', $content);
        $stmt->bindParam(':image', $imageName);
        $stmt->bindParam(':pinned', $pinned);
        $stmt->execute();

        // Clear output buffer and redirect
        ob_clean();
        header("Location: index.php#bulletin");
        exit;
    } catch (Exception $e) {
        // If there was an error and image was uploaded, delete it
        if (isset($uploadPath) && file_exists($uploadPath)) {
            unlink($uploadPath);
        }

        ob_clean();
        die("Error: " . $e->getMessage());
    }
}

// If we get here, something went wrong
ob_clean();
header("Location: index.php#bulletin");
exit;
?>


