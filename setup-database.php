<?php


// Database connection details - Updated for Wedos hosting


$host = 'md396.wedos.net';


$username = 'w394711_main';


$password = 'mnpJtgaJ';


$dbname = 'd394711_main';


$port = 3306;





try {


    // Connect to MySQL server with new credentials


    $conn = new mysqli($host, $username, $password, $dbname, $port);





    // Check connection


    if ($conn->connect_error) {


        die("Connection failed: " . $conn->connect_error);


    }





    // Database already exists on Wedos, no need to create it


    echo "Connected to database: $dbname<br>";





    // Select the database


    $conn->select_db("login");





    // Create users table if it doesn't exist


    $sql = "CREATE TABLE IF NOT EXISTS users (


        id INT(11) AUTO_INCREMENT PRIMARY KEY,


        username VARCHAR(50) NOT NULL UNIQUE,


        password VARCHAR(255) NOT NULL,


        email VARCHAR(100) NOT NULL,


        role VARCHAR(20) NOT NULL DEFAULT 'user',


        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP


    )";





    if ($conn->query($sql) === TRUE) {


        echo "Users table created or already exists<br>";


    } else {


        die("Error creating table: " . $conn->error);


    }


    $sql = "CREATE TABLE IF NOT EXISTS bulletin_posts (


        id INT(11) AUTO_INCREMENT PRIMARY KEY,


        user_id INT(11) NOT NULL,


        username VARCHAR(50) NOT NULL,


        title VARCHAR(100) NOT NULL,


        content TEXT NOT NULL,


        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,


        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,


        pinned TINYINT(1) DEFAULT 0,


        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE


    )";


    


    if ($conn->query($sql) === TRUE) {


        echo "Bulletin posts table created or already exists<br>";


    } else {


        echo "Error creating bulletin table: " . $conn->error . "<br>";


    }


    // Create chat_sessions table for storing user chat history


    $sql = "CREATE TABLE IF NOT EXISTS chat_sessions (


    id INT(11) AUTO_INCREMENT PRIMARY KEY,


    user_id INT(11) NOT NULL,


    title VARCHAR(100) NOT NULL,


    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,


    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,


    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE


)";





    if ($conn->query($sql) === TRUE) {


        echo "Chat sessions table created or already exists<br>";


    } else {


        echo "Error creating chat sessions table: " . $conn->error . "<br>";


    }





    // Create chat_messages table for storing individual messages


    $sql = "CREATE TABLE IF NOT EXISTS chat_messages (


    id INT(11) AUTO_INCREMENT PRIMARY KEY,


    session_id INT(11) NOT NULL,


    role ENUM('user', 'assistant') NOT NULL,


    content TEXT NOT NULL,


    timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,


    FOREIGN KEY (session_id) REFERENCES chat_sessions(id) ON DELETE CASCADE


)";





    if ($conn->query($sql) === TRUE) {


        echo "Chat messages table created or already exists<br>";


    } else {


        echo "Error creating chat messages table: " . $conn->error . "<br>";


    }


    // Create contest_entries table for storing contest submissions


    $sql = "CREATE TABLE IF NOT EXISTS contest_entries (


        id INT(11) AUTO_INCREMENT PRIMARY KEY,


        name VARCHAR(255) NOT NULL,


        email VARCHAR(255) NOT NULL,


        phone VARCHAR(50) NOT NULL,


        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,


        INDEX idx_email (email),


        INDEX idx_created_at (created_at)


    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";





    if ($conn->query($sql) === TRUE) {


        echo "Contest entries table created or already exists<br>";


    } else {


        echo "Error creating contest entries table: " . $conn->error . "<br>";


    }


    // Check if admin user exists


    $sql = "SELECT * FROM users WHERE username='admin'";


    $result = $conn->query($sql);





    if ($result->num_rows == 0) {


        // Create default admin user if none exists


        $hashedPassword = password_hash('admin123', PASSWORD_DEFAULT);


        $sql = "INSERT INTO users (username, password, email, role) 


                VALUES ('admin', '$hashedPassword', 'admin@example.com', 'admin')";





        if ($conn->query($sql) === TRUE) {


            echo "Default admin user created:<br>";


            echo "Username: admin<br>";


            echo "Password: admin123<br>";


        } else {


            echo "Error creating admin user: " . $conn->error;


        }


    } else {


        echo "Admin user already exists<br>";


    }


    





    $conn->close();


    echo "<p>Database setup complete.</p>";


    echo "<a href='login.php'>Go to login page</a>";


} catch (Exception $e) {


    echo "Error: " . $e->getMessage();


}
?>


