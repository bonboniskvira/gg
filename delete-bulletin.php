<?php
// Start output buffering to prevent header issues
ob_start();

session_start();

require_once 'access.php';



if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['post_id'])) {

    $post_id = $_POST['post_id'];



    // Database connection

    $host = 'md396.wedos.net';

    $dbname = 'd394711_main';

    $username = 'w394711_main';

    $password = 'mnpJtgaJ';



    try {

        $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);

        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);



        // First check if user has permission to delete this post

        $stmt = $pdo->prepare("SELECT user_id, image FROM bulletin_posts WHERE id = :post_id");

        $stmt->bindParam(':post_id', $post_id);

        $stmt->execute();

        $post = $stmt->fetch(PDO::FETCH_ASSOC);



        // Only allow deletion if admin or post owner

        if ($post && (isAdmin() || $_SESSION['user_id'] == $post['user_id'])) {

            // Delete associated image if it exists

            if ($post['image'] && file_exists('img_bulletin/' . $post['image'])) {

                unlink('img_bulletin/' . $post['image']);

            }



            $stmt = $pdo->prepare("DELETE FROM bulletin_posts WHERE id = :post_id");

            $stmt->bindParam(':post_id', $post_id);

            $stmt->execute();

        }

    } catch (PDOException $e) {

        // Just log the error and continue

        error_log("Error deleting bulletin post: " . $e->getMessage());

    }

}

// Clear output buffer and redirect back to bulletin page

ob_clean();

header("Location: index.php#bulletin");

exit;
?>

