<?php
// Start output buffering to prevent header issues
ob_start();

session_start();

require_once 'access.php';



// Only admins can pin/unpin posts

if (!isAdmin()) {

    ob_clean(); // Clear any output

    header("Location: index.php");

    exit;

}



if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['post_id']) && isset($_POST['pinned'])) {

    $post_id = $_POST['post_id'];

    $pinned = $_POST['pinned'];



    // Database connection

    $host = 'md396.wedos.net';

    $dbname = 'd394711_main';

    $username = 'w394711_main';

    $password = 'mnpJtgaJ';



    try {

        $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);

        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);



        $stmt = $pdo->prepare("UPDATE bulletin_posts SET pinned = :pinned WHERE id = :post_id");

        $stmt->bindParam(':pinned', $pinned);

        $stmt->bindParam(':post_id', $post_id);

        $stmt->execute();

    } catch (PDOException $e) {

        // Just log the error and continue

        error_log("Error toggling pin status: " . $e->getMessage());

    }

}



// Clear output buffer and redirect back to bulletin page

ob_clean();

header("Location: index.php#bulletin");

exit;
?>

