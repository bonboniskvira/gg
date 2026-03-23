<?php


echo "<h1>Test - Website is working!</h1>";


echo "<p>Server: " . $_SERVER['HTTP_HOST'] . "</p>";


echo "<p>Document Root: " . $_SERVER['DOCUMENT_ROOT'] . "</p>";


echo "<p>Current Directory: " . __DIR__ . "</p>";





// Test database connection


try {


    include 'config.php';


    $pdo = getDatabaseConnection();


    echo "<p style='color: green;'>✅ Database connection successful!</p>";


    


    // Test if tables exist


    $stmt = $pdo->query("SHOW TABLES");


    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);


    echo "<p>Tables in database: " . implode(', ', $tables) . "</p>";


    


} catch (Exception $e) {


    echo "<p style='color: red;'>❌ Database error: " . $e->getMessage() . "</p>";


}





echo "<hr>";


echo "<p><a href='setup-database.php'>Run Database Setup</a></p>";


echo "<p><a href='login.php'>Go to Login</a></p>";


?>


