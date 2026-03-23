<?php



// Database configuration



// Better detection for live server



$isLiveServer = (



    isset($_SERVER['HTTP_HOST']) && 



    (



        $_SERVER['HTTP_HOST'] === 'edosys.cz' || 



        $_SERVER['HTTP_HOST'] === 'www.edosys.cz' ||



        strpos($_SERVER['HTTP_HOST'], 'wedos') !== false



    )



);







if ($isLiveServer) {



    // Live server (Wedos) settings



    define('DB_HOST', 'md396.wedos.net');



    define('DB_NAME', 'd394711_main');



    define('DB_USER', 'w394711_main');



    define('DB_PASS', 'mnpJtgaJ');



    define('DB_PORT', 3306);



} else {



    // Local development settings



    define('DB_HOST', 'md392.wedos.net');



    define('DB_NAME', 'd394711_main');



    define('DB_USER', 'w394711_main');



    define('DB_PASS', 'mnpJtgaJ');



    define('DB_PORT', 3306);



}







// Create PDO connection function



function getDatabaseConnection() {



    try {



        $dsn = "mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME . ";charset=utf8mb4";



        $pdo = new PDO($dsn, DB_USER, DB_PASS);



        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);



        $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);



        return $pdo;



    } catch (PDOException $e) {



        throw new Exception("Database connection failed: " . $e->getMessage());



    }



}



?>



