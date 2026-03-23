<?php
// Start output buffering to prevent any accidental output
ob_start();

// Start session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/access.php';

// Clean any previous output and set JSON header
ob_clean();
header('Content-Type: application/json; charset=UTF-8');

// Database connection using setup-database.php details
function getContestDbConnection() {
    $host = 'md396.wedos.net';
    $username = 'w394711_main';
    $password = 'mnpJtgaJ';
    $dbname = 'd394711_main';
    $port = 3306;
    
    try {
        $conn = new mysqli($host, $username, $password, $dbname, $port);
        
        if ($conn->connect_error) {
            throw new Exception("Connection failed: " . $conn->connect_error);
        }
        
        $conn->set_charset("utf8mb4");
        return $conn;
    } catch (Exception $e) {
        error_log("Contest answers database connection error: " . $e->getMessage());
        return null;
    }
}

// Create contest_answers table if it doesn't exist
function createContestAnswersTable() {
    $conn = getContestDbConnection();
    if (!$conn) {
        throw new Exception("Database connection failed");
    }
    
    // First, create table with original structure if it doesn't exist
    $sql = "CREATE TABLE IF NOT EXISTS contest_answers (
        id INT AUTO_INCREMENT PRIMARY KEY,
        entry_id INT NOT NULL,
        question1 CHAR(1) NOT NULL,
        question2 CHAR(1) NOT NULL,
        question3 CHAR(1) NOT NULL,
        question4 CHAR(1) NOT NULL,
        question5 CHAR(1) NOT NULL,
        question6 CHAR(1) NOT NULL DEFAULT 'a',
        question7 CHAR(1) NOT NULL DEFAULT 'a',
        question8 CHAR(1) NOT NULL DEFAULT 'a',
        question9 CHAR(1) NOT NULL DEFAULT 'a',
        question10 CHAR(1) NOT NULL DEFAULT 'a',
        score INT DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (entry_id) REFERENCES contest_entries(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    try {
        $result = $conn->query($sql);
        if (!$result) {
            error_log("Failed to create contest_answers table: " . $conn->error);
            // Check if table exists anyway
            $checkSql = "SELECT 1 FROM contest_answers LIMIT 1";
            if (!$conn->query($checkSql)) {
                $conn->close();
                throw new Exception("Failed to create contest answers table");
            }
        }
    } catch (Exception $e) {
        // If CREATE TABLE fails due to permissions, try to verify table exists
        $checkSql = "SELECT 1 FROM contest_answers LIMIT 1";
        if (!$conn->query($checkSql)) {
            $conn->close();
            throw new Exception("Contest answers table does not exist and cannot be created");
        }
    }
    
    // Check and add missing columns for existing tables
    $columnsToAdd = [
        'question6' => 'CHAR(1) NOT NULL DEFAULT "a"',
        'question7' => 'CHAR(1) NOT NULL DEFAULT "a"',
        'question8' => 'CHAR(1) NOT NULL DEFAULT "a"',
        'question9' => 'CHAR(1) NOT NULL DEFAULT "a"',
        'question10' => 'CHAR(1) NOT NULL DEFAULT "a"'
    ];
    
    foreach ($columnsToAdd as $column => $definition) {
        // Check if column exists
        $checkSql = "SHOW COLUMNS FROM contest_answers LIKE '$column'";
        $checkResult = $conn->query($checkSql);
        
        if ($checkResult && $checkResult->num_rows == 0) {
            // Column doesn't exist, add it
            $addSql = "ALTER TABLE contest_answers ADD COLUMN $column $definition";
            $addResult = $conn->query($addSql);
            
            if (!$addResult) {
                error_log("Failed to add column $column: " . $conn->error);
            } else {
                error_log("Successfully added column $column to contest_answers table");
            }
        }
    }
    
    $conn->close();
}

// Calculate score based on correct answers
function calculateScore($answers) {
    $correctAnswers = [
        'question1' => 'a', // LTV = Poměr mezi výší hypotéčního úvěru a aktuální tržní hodnotou zastavené nemovitosti
        'question2' => 'd', // Hypotéka = Úvěr zajištěný nemovitostí, účelově vázán na bydlení
        'question3' => 'b', // Riziko fixace = Zvýšení měsíční splátky v důsledku změny úrokových sazeb
        'question4' => 'a', // Neúčelová hypotéka = Americká hypotéka
        'question5' => 'a', // PPI = Krýt splátky v případě nemoci, ztráty zaměstnání nebo úmrtí
        'question6' => 'd', // Centrální banka = Stanovení základních úrokových sazeb (REPO sazby)
        'question7' => 'd', // Variabilní sazba = Tržní úrokové sazby a marže banky
        'question8' => 'b', // Hodnota nemovitosti = Odhad tržní ceny (znalecký posudek)
        'question9' => 'a', // Refinancování = Nahrazení stávajícího úvěru novým s lepšími podmínkami
        'question10' => 'a' // RPSN neobsahuje = Poplatek za zpracování úvěru
    ];
    
    $score = 0;
    foreach ($correctAnswers as $question => $correctAnswer) {
        if (isset($answers[$question]) && $answers[$question] === $correctAnswer) {
            $score++;
        }
    }
    
    return $score;
}

// Save contest answers
function saveContestAnswers($entryId, $answers, $score) {
    $conn = getContestDbConnection();
    if (!$conn) {
        throw new Exception("Database connection failed");
    }
    
    // Check which columns exist in the table
    $columnsResult = $conn->query("SHOW COLUMNS FROM contest_answers");
    $existingColumns = [];
    while ($row = $columnsResult->fetch_assoc()) {
        $existingColumns[] = $row['Field'];
    }
    
    // Build dynamic SQL based on existing columns
    $questionColumns = [];
    $questionValues = [];
    $bindTypes = 'i'; // Start with integer for entry_id
    $bindParams = [$entryId];
    
    for ($i = 1; $i <= 10; $i++) {
        $column = "question$i";
        if (in_array($column, $existingColumns)) {
            $questionColumns[] = $column;
            $questionValues[] = '?';
            $bindTypes .= 's';
            $bindParams[] = $answers[$column] ?? 'a'; // Default to 'a' if not provided
        }
    }
    
    // Add score
    $questionColumns[] = 'score';
    $questionValues[] = '?';
    $bindTypes .= 'i';
    $bindParams[] = $score;
    
    $columnsStr = 'entry_id, ' . implode(', ', $questionColumns);
    $valuesStr = '?, ' . implode(', ', $questionValues);
    
    $stmt = $conn->prepare("INSERT INTO contest_answers ($columnsStr) VALUES ($valuesStr)");
    if (!$stmt) {
        error_log("Failed to prepare statement: " . $conn->error);
        $conn->close();
        throw new Exception("Database error: Failed to prepare statement");
    }
    
    $stmt->bind_param($bindTypes, ...$bindParams);
    
    $success = $stmt->execute();
    if (!$success) {
        error_log("Failed to execute insert: " . $stmt->error);
        $stmt->close();
        $conn->close();
        throw new Exception("Database error: Failed to save answers");
    }
    
    $answerId = $conn->insert_id;
    error_log("Successfully saved contest answers with ID: " . $answerId);
    
    $stmt->close();
    $conn->close();
    
    return $answerId;
}

try {
    // Ensure table exists - try to create, but don't fail if permission denied
    try {
        createContestAnswersTable();
    } catch (Exception $tableError) {
        error_log("Contest answers table creation failed, but continuing: " . $tableError->getMessage());
        // Continue anyway - table might exist
    }
    
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Neplatná metoda požadavku');
    }
    
    // Validate entry ID
    $entryId = intval($_POST['entry_id'] ?? 0);
    if ($entryId <= 0) {
        throw new Exception('Neplatné ID přihlášky');
    }
    
    // Validate all questions are answered
    $requiredQuestions = ['question1', 'question2', 'question3', 'question4', 'question5', 'question6', 'question7', 'question8', 'question9', 'question10'];
    foreach ($requiredQuestions as $question) {
        if (!isset($_POST[$question]) || empty($_POST[$question])) {
            throw new Exception('Všechny otázky musí být zodpovězeny');
        }
    }
    
    $answers = [];
    foreach ($requiredQuestions as $question) {
        $answers[$question] = trim($_POST[$question]);
        if (!in_array($answers[$question], ['a', 'b', 'c', 'd'])) {
            throw new Exception('Neplatná odpověď na otázku');
        }
    }
    
    // Calculate score
    $score = calculateScore($answers);
    
    // Save answers
    $answerId = saveContestAnswers($entryId, $answers, $score);
    
    if ($answerId) {
        ob_clean();
        echo json_encode([
            'success' => true,
            'message' => 'Odpovědi byly úspěšně odeslány! Vaše skóre: ' . $score . '/10',
            'score' => $score,
            'total' => 10,
            'answer_id' => $answerId
        ]);
    } else {
        throw new Exception('Chyba při ukládání odpovědí do databáze');
    }
    
} catch (Exception $e) {
    error_log("Contest answers handler error: " . $e->getMessage());
    ob_clean();
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

// Ensure we exit cleanly
exit;
?>
