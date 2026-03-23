<?php

// Fix for missing timestamp column in chat_messages table

$host = 'md396.wedos.net';

$username = 'w394711_main';

$password = 'mnpJtgaJ';

$dbname = 'd394711_main';

try {

    $conn = new mysqli($host, $username, $password, $dbname);



    if ($conn->connect_error) {

        die("Connection failed: " . $conn->connect_error);

    }



    // Check if column exists

    $result = $conn->query("SHOW COLUMNS FROM chat_messages LIKE 'timestamp'");



    if ($result->num_rows == 0) {

        // Add the missing column

        $sql = "ALTER TABLE chat_messages 

                ADD COLUMN timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP";



        if ($conn->query($sql) === TRUE) {

            echo "Column 'timestamp' added successfully to chat_messages table";

        } else {

            echo "Error adding column: " . $conn->error;

        }

    } else {

        echo "Column 'timestamp' already exists in chat_messages table";

    }



    $conn->close();



    echo "<br><a href='index.php?page=chatbot'>Return to chatbot</a>";

} catch (Exception $e) {

    echo "Error: " . $e->getMessage();

}

