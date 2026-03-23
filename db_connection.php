<?php
// db_connection.php

// Include the database configuration
require_once 'config.php';

$pdo = null;
$conn = null;

try {
    // Create a PDO instance for database operations
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4", DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);

    // Create a mysqli connection for legacy parts of the application
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME, DB_PORT);
    if ($conn->connect_error) {
        throw new Exception("mysqli connection failed: " . $conn->connect_error);
    }
    $conn->set_charset("utf8mb4");

} catch (PDOException $e) {
    // Handle PDO connection error
    die("PDO Connection Error: " . $e->getMessage());
} catch (Exception $e) {
    // Handle mysqli connection error
    die("mysqli Connection Error: " . $e->getMessage());
}
?>

