<?php
// test_db_connection.php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

echo "Attempting to connect to the database...<br>";

require_once 'config.php';

try {
    $dsn = "mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME . ";charset=utf8mb4";
    $pdo = new PDO($dsn, DB_USER, DB_PASS);

    // If we get this far, the connection was successful
    echo "Database connection successful!<br>";

    // Optional: Check if the 'users' table exists
    $stmt = $pdo->query("SHOW TABLES LIKE 'users'");
    if ($stmt->rowCount() > 0) {
        echo "Table 'users' found.<br>";
    } else {
        echo "<strong>Warning:</strong> Table 'users' not found.<br>";
    }

} catch (PDOException $e) {
    echo "<strong>Database connection failed:</strong> " . $e->getMessage() . "<br>";
} catch (Exception $e) {
    echo "<strong>An error occurred:</strong> " . $e->getMessage() . "<br>";
}

?>

