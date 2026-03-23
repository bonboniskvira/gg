<?php
echo "<h2>MySQL Connection Test - Wedos Database</h2>";

// Test connection with new Wedos credentials
$host = 'md396.wedos.net';
$username = 'w394711_main';
$password = 'mnpJtgaJ';
$dbname = 'd394711_main';
$port = 3306;

echo "<h3>Testing Wedos database connection:</h3>";

try {
    $conn = new mysqli($host, $username, $password, $dbname, $port);
    
    if ($conn->connect_error) {
        echo "❌ Connection failed: " . $conn->connect_error . "<br>";
    } else {
        echo "✅ Connection successful to database: $dbname<br>";
        echo "MySQL version: " . $conn->server_info . "<br>";
        
        // Test if tables exist
        $result = $conn->query("SHOW TABLES");
        if ($result) {
            echo "Tables in database:<br>";
            while ($row = $result->fetch_array()) {
                echo "- " . $row[0] . "<br>";
            }
        }
        
        $conn->close();
    }
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "<br>";
}

echo "<hr>";
echo "<p><a href='setup-database.php'>Run Database Setup</a></p>";
echo "<p><a href='index.php'>Go to Application</a></p>";
?>
