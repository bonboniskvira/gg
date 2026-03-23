<?php
// Don't require login for contest - it's public
// Only include access.php for helper functions
require_once __DIR__ . '/../../access.php';

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
        error_log("Contest database connection error: " . $e->getMessage());
        return null;
    }
}

// Create contest table if it doesn't exist
function ensureContestTable() {
    $conn = getContestDbConnection();
    if (!$conn) {
        return false;
    }
    
    $sql = "CREATE TABLE IF NOT EXISTS contest_entries (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(255) NOT NULL,
        email VARCHAR(255) NOT NULL,
        phone VARCHAR(50) NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_email (email),
        INDEX idx_created_at (created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    try {
        $result = $conn->query($sql);
        $conn->close();
        return $result !== false;
    } catch (Exception $e) {
        // If CREATE TABLE fails due to permissions, try to verify table exists
        // by querying it instead
        $checkSql = "SELECT 1 FROM contest_entries LIMIT 1";
        try {
            $conn->query($checkSql);
            $conn->close();
            return true; // Table already exists
        } catch (Exception $e2) {
            $conn->close();
            return false; // Table doesn't exist and can't be created
        }
    }
}

// Helper function to get contest answers for an entry
function getContestAnswers($entryId) {
    $conn = getContestDbConnection();
    if (!$conn) {
        return null;
    }
    
    $stmt = $conn->prepare("SELECT question1, question2, question3, question4, question5, question6, question7, question8, question9, question10, score FROM contest_answers WHERE entry_id = ?");
    $stmt->bind_param("i", $entryId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $answers = null;
    if ($result && $result->num_rows > 0) {
        $answers = $result->fetch_assoc();
    }
    
    $stmt->close();
    $conn->close();
    
    return $answers;
}

// Helper function to get all contest entries (admin only)
function getContestEntries() {
    // Only show entries if user is logged in AND admin
    if (!isset($_SESSION['user_id']) || !isAdmin()) {
        return [];
    }
    
    // Ensure table exists first
    if (!ensureContestTable()) {
        error_log("Contest page: Failed to create contest table");
        return [];
    }
    
    $conn = getContestDbConnection();
    if (!$conn) {
        error_log("Contest page: Database connection failed");
        return [];
    }
    
    // Check if table exists
    $checkTable = $conn->query("SHOW TABLES LIKE 'contest_entries'");
    if (!$checkTable || $checkTable->num_rows == 0) {
        error_log("Contest page: Table contest_entries does not exist");
        $conn->close();
        return [];
    }
    
    $sql = "SELECT * FROM contest_entries ORDER BY created_at DESC";
    $result = $conn->query($sql);
    
    $entries = [];
    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            // Get answers for this entry
            $row['answers'] = getContestAnswers($row['id']);
            $entries[] = $row;
        }
        error_log("Contest page: Found " . count($entries) . " entries");
    } else {
        error_log("Contest page: No entries found or query failed");
    }
    $conn->close();
    
    return $entries;
}

$contestEntries = getContestEntries();
$debugInfo = '';
// Only show debug info to logged-in admins
if (isset($_SESSION['user_id']) && isAdmin()) {
    $debugInfo = "Database connection test: " . (getContestDbConnection() ? "OK" : "FAILED");
    $debugInfo .= " | Entries count: " . count($contestEntries);
}
?>

<style>
    /* Contest page styling */
    #contest .content-area {
        display: flex;
        flex-direction: column;
        gap: 30px;
        max-width: 800px;
        margin: 0 auto;
    }

    .contest-header {
        text-align: center;
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        padding: 40px 20px;
        border-radius: 15px;
        margin-bottom: 30px;
    }

    .contest-header h1 {
        margin: 0 0 10px 0;
        font-size: 2.5em;
        font-weight: 700;
    }

    .contest-header p {
        margin: 0;
        font-size: 1.1em;
        opacity: 0.9;
    }

    .contest-form-container {
        background: white;
        border-radius: 15px;
        padding: 30px;
        box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
        border-top: 4px solid #667eea;
    }

    .contest-form h2 {
        color: #495057;
        margin: 0 0 20px 0;
        font-size: 1.8em;
        text-align: center;
    }

    .form-group {
        margin-bottom: 20px;
    }

    .form-group label {
        display: block;
        margin-bottom: 8px;
        font-weight: 600;
        color: #495057;
    }

    .form-group input[type="text"],
    .form-group input[type="email"],
    .form-group input[type="tel"] {
        width: 100%;
        padding: 12px 15px;
        border: 2px solid #e9ecef;
        border-radius: 8px;
        font-size: 16px;
        transition: border-color 0.3s ease;
        box-sizing: border-box;
    }

    .form-group input:focus {
        outline: none;
        border-color: #667eea;
        box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
    }

    .form-group input.error {
        border-color: #e74c3c;
    }

    .form-group .field-error {
        color: #e74c3c;
        font-size: 14px;
        margin-top: 5px;
        display: block;
    }

    .contest-submit-btn {
        width: 100%;
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        border: none;
        padding: 15px 30px;
        border-radius: 8px;
        font-size: 16px;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.3s ease;
        margin-top: 10px;
    }

    .contest-submit-btn:hover {
        transform: translateY(-2px);
        box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
    }

    .contest-submit-btn:disabled {
        background: #6c757d;
        cursor: not-allowed;
        transform: none;
        box-shadow: none;
    }

    /* Admin entries section */
    .contest-entries-section {
        background: white;
        border-radius: 15px;
        padding: 30px;
        box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
        border-top: 4px solid #28a745;
    }

    .contest-entries-section h2 {
        color: #495057;
        margin: 0 0 20px 0;
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .entries-table {
        width: 100%;
        border-collapse: collapse;
        margin-top: 15px;
    }

    .entries-table th,
    .entries-table td {
        padding: 12px;
        text-align: left;
        border-bottom: 1px solid #dee2e6;
    }

    .entries-table th {
        background-color: #f8f9fa;
        font-weight: 600;
        color: #495057;
    }

    .entries-table tr:hover {
        background-color: #f8f9fa;
    }

    .entry-actions {
        display: flex;
        gap: 5px;
    }

    .entry-delete-btn {
        background: #dc3545;
        color: white;
        border: none;
        padding: 5px 10px;
        border-radius: 4px;
        cursor: pointer;
        font-size: 12px;
    }

    .entry-delete-btn:hover {
        background: #c82333;
    }

    .no-entries {
        text-align: center;
        padding: 40px;
        color: #6c757d;
    }

    .status-message {
        padding: 12px 16px;
        margin: 15px 0;
        border-radius: 8px;
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .status-success {
        background: #d4edda;
        color: #155724;
        border: 1px solid #c3e6cb;
    }

    .status-error {
        background: #f8d7da;
        color: #721c24;
        border: 1px solid #f5c6cb;
    }

    .entry-answers {
        font-size: 12px;
        color: #6c757d;
        margin: 0;
        padding: 0;
        background: transparent;
        border: none;
        display: flex;
        flex-direction: column;
        justify-content: center;
        height: 100%;
    }
    
    .entry-answers.has-answers {
        border-left-color: transparent;
    }
    
    .entry-answers.no-answers {
        border-left-color: transparent;
        background: transparent;
        color: #856404;
    }
    
    .answers-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
        gap: 8px;
        margin-top: 6px;
    }
    
    .answer-item {
        display: flex;
        align-items: center;
        gap: 4px;
        font-size: 11px;
    }
    
    .answer-correct {
        color: #28a745;
    }
    
    .answer-incorrect {
        color: #dc3545;
    }
    
    .score-badge {
        display: inline-block;
        padding: 8px 16px;
        border-radius: 20px;
        font-weight: 600;
        font-size: 14px;
        color: white;
    }

    .score-badge.high-score {
        background: linear-gradient(135deg, #27ae60, #2ecc71);
    }

    .score-badge.medium-score {
        background: linear-gradient(135deg, #f39c12, #e67e22);
    }

    .score-badge.low-score {
        background: linear-gradient(135deg, #e74c3c, #c0392b);
    }

    .answers-breakdown {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
        gap: 8px;
        margin-top: 15px;
        padding: 15px;
        background: #f8f9fa;
        border-radius: 8px;
    }

    .answer-item {
        display: flex;
        align-items: center;
        gap: 5px;
        padding: 6px 8px;
        border-radius: 4px;
        font-size: 12px;
        background: white;
        border: 1px solid #e9ecef;
    }

    .answer-item.correct {
        background: #d4edda;
        border-color: #c3e6cb;
        color: #155724;
    }

    .answer-item.incorrect {
        background: #f8d7da;
        border-color: #f5c6cb;
        color: #721c24;
    }

    .question-num {
        font-weight: 600;
        min-width: 24px;
    }

    .user-answer {
        font-weight: 600;
    }

    .correct-answer {
        font-size: 11px;
        opacity: 0.8;
    }

    .answer-item i {
        margin-left: auto;
        font-size: 14px;
    }

    .answer-item.correct i {
        color: #28a745;
    }

    .answer-item.incorrect i {
        color: #dc3545;
    }

    @media (max-width: 768px) {
        .contest-header h1 {
            font-size: 2em;
        }
        
        .contest-form-container,
        .contest-entries-section {
            padding: 20px;
            margin: 0 15px;
        }
        
        .entries-table {
            font-size: 14px;
        }
        
        .entries-table th,
        .entries-table td {
            padding: 8px;
        }
    }
    
    .answers-row td {
        border-top: none !important;
        border-bottom: 3px solid #007bff !important;
        padding: 12px !important;
        vertical-align: middle;
        height: 60px;
    }
    
    .answers-row:hover {
        background-color: transparent !important;
    }
    
    .entries-table tr:last-child td,
    .entries-table tr:last-child + .answers-row td {
        border-bottom: 1px solid #dee2e6 !important;
    }
</style>

<div class="page-content" id="contest">
    <div class="content-area">
        <!-- Contest Header -->
        <div class="contest-header">
            <h1>eDO Soutěž</h1>
            <p>Vyplňte formulář a zapojte se do soutěže!</p>
        </div>

        <!-- Status Messages -->
        <div id="status-messages"></div>

        <!-- Contest Form -->
        <div class="contest-form-container">
            <form id="contestForm" class="contest-form">
                <h2>Registrace do soutěže</h2>
                
                <div class="form-group">
                    <label for="contest_name">Jméno a příjmení *</label>
                    <input type="text" id="contest_name" name="name" required 
                           placeholder="Zadejte vaše celé jméno">
                    <span class="field-error" id="name-error"></span>
                </div>

                <div class="form-group">
                    <label for="contest_email">E-mailová adresa *</label>
                    <input type="email" id="contest_email" name="email" required 
                           placeholder="vas-email@example.com">
                    <span class="field-error" id="email-error"></span>
                </div>

                <div class="form-group">
                    <label for="contest_phone">Telefonní číslo *</label>
                    <input type="tel" id="contest_phone" name="phone" required 
                           placeholder="+420 123 456 789">
                    <span class="field-error" id="phone-error"></span>
                </div>

                <button type="submit" class="contest-submit-btn">
                    <i class="fas fa-trophy"></i> Přihlásit se do soutěže
                </button>
            </form>
        </div>

        <!-- Admin Entries Section - only show if logged in as admin -->
        <?php if (isset($_SESSION['user_id']) && isAdmin()): ?>
            <div class="contest-entries-section">
                <h2>
                    <i class="fas fa-list"></i>
                    Přihlášky do soutěže
                    <span style="font-size: 0.8em; color: #6c757d;">(<?= count($contestEntries) ?>)</span>
                </h2>
                
                <?php if (!empty($debugInfo)): ?>
                    <div style="background: #f8f9fa; padding: 10px; border-radius: 4px; margin-bottom: 15px; font-size: 12px; color: #6c757d;">
                        Debug: <?= htmlspecialchars($debugInfo) ?>
                    </div>
                <?php endif; ?>
                
                <?php if (!empty($contestEntries)): ?>
                    <div style="overflow-x: auto;">
                        <table class="entries-table">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Jméno</th>
                                    <th>E-mail</th>
                                    <th>Telefon</th>
                                    <th>Skóre</th>
                                    <th>Datum přihlášení</th>
                                    <th>Akce</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($contestEntries as $entry): ?>
                                    <tr>
                                        <td style="font-size: 14px; font-weight: 600; color: #6c757d;">
                                            <?= htmlspecialchars($entry['id']) ?>
                                        </td>
                                        <td>
                                            <strong><?= htmlspecialchars($entry['name']) ?></strong>
                                        </td>
                                        <td><?= htmlspecialchars($entry['email']) ?></td>
                                        <td><?= htmlspecialchars($entry['phone']) ?></td>
                                        <td>
                                            <?php if ($entry['answers']): ?>
                                                <?php
                                                $score = $entry['answers']['score'];
                                                $scoreClass = '';
                                                $scoreIcon = '';
                                                if ($score >= 5) {
                                                    $scoreClass = 'score-excellent';
                                                    $scoreIcon = 'fa-trophy';
                                                } elseif ($score >= 4) {
                                                    $scoreClass = 'score-good';
                                                    $scoreIcon = 'fa-star';
                                                } elseif ($score >= 2) {
                                                    $scoreClass = 'score-average';
                                                    $scoreIcon = 'fa-medal';
                                                } else {
                                                    $scoreClass = 'score-poor';
                                                    $scoreIcon = 'fa-times-circle';
                                                }
                                                ?>
                                                <span class="score-badge <?= $scoreClass ?>">
                                                    <i class="fas <?= $scoreIcon ?>"></i>
                                                    <?= $score ?>/5
                                                </span>
                                            <?php else: ?>
                                                <span class="score-badge score-poor">
                                                    <i class="fas fa-exclamation-triangle"></i>
                                                    Nevyplněno
                                                </span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?= date('d.m.Y H:i', strtotime($entry['created_at'])) ?></td>
                                        <td>
                                            <div class="entry-actions">
                                                <button class="entry-delete-btn" 
                                                        onclick="deleteContestEntry(<?= $entry['id'] ?>)"
                                                        title="Smazat přihlášku">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php if ($entry['answers']): ?>
                                        <tr class="answers-row">
                                            <td colspan="7" style="padding: 12px; background: #f8f9fa;">
                                                <div class="entry-answers has-answers">
                                                    <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 6px;">
                                                        <span style="font-weight: 600; color: #495057;">
                                                            <i class="fas fa-question-circle"></i> Odpovědi:
                                                        </span>
                                                    </div>
                                                    <div class="answers-grid">
                                                        <?php
                                                        // Correct answers for comparison - Updated for 10 questions
                                                        $correctAnswers = [
                                                            'question1' => 'a', // LTV definition
                                                            'question2' => 'd', // Hypotéka definition 
                                                            'question3' => 'b', // Fixace risk
                                                            'question4' => 'a', // Americká hypotéka
                                                            'question5' => 'a', // PPI purpose
                                                            'question6' => 'd', // Centrální banka REPO sazby
                                                            'question7' => 'd', // Variabilní sazba
                                                            'question8' => 'b', // Znalecký posudek
                                                            'question9' => 'a', // Refinancování
                                                            'question10' => 'a' // RPSN neobsahuje
                                                        ];
                                                        
                                                        // Count total correct answers for this entry
                                                        $totalCorrect = 0;
                                                        for ($i = 1; $i <= 10; $i++): // Changed from 5 to 10
                                                            $questionKey = "question$i";
                                                            if (isset($entry['answers'][$questionKey])) {
                                                                $userAnswer = $entry['answers'][$questionKey];
                                                                $correctAnswer = $correctAnswers[$questionKey] ?? '';
                                                                $isCorrect = $userAnswer === $correctAnswer;
                                                                if ($isCorrect) {
                                                                    $totalCorrect++;
                                                                }
                                                            }
                                                        endfor;
                                                        ?>
                                                        
                                                        <div class="score-display">
                                                            <span class="score-badge <?= $totalCorrect >= 8 ? 'high-score' : ($totalCorrect >= 6 ? 'medium-score' : 'low-score') ?>">
                                                                <?= $totalCorrect ?>/10 správně
                                                            </span>
                                                        </div>

                                                        <div class="answers-breakdown">
                                                            <?php for ($i = 1; $i <= 10; $i++): // Changed from 5 to 10
                                                                $questionKey = "question$i";
                                                                if (isset($entry['answers'][$questionKey])):
                                                                    $userAnswer = $entry['answers'][$questionKey];
                                                                    $correctAnswer = $correctAnswers[$questionKey] ?? '';
                                                                    $isCorrect = $userAnswer === $correctAnswer;
                                                            ?>
                                                                <div class="answer-item <?= $isCorrect ? 'correct' : 'incorrect' ?>">
                                                                    <span class="question-num">Q<?= $i ?>:</span>
                                                                    <span class="user-answer"><?= strtoupper($userAnswer) ?></span>
                                                                    <?php if (!$isCorrect): ?>
                                                                        <span class="correct-answer">(správně: <?= strtoupper($correctAnswer) ?>)</span>
                                                                    <?php endif; ?>
                                                                    <i class="fas <?= $isCorrect ? 'fa-check' : 'fa-times' ?>"></i>
                                                                </div>
                                                            <?php 
                                                                endif;
                                                            endfor; 
                                                            ?>
                                                        </div>
                                                    </div>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php else: ?>
                                        <tr class="answers-row">
                                            <td colspan="7" style="padding: 12px; background: #fff3cd;">
                                                <div class="entry-answers no-answers">
                                                    <i class="fas fa-exclamation-triangle"></i>
                                                    Otázky nebyly zodpovězeny
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="no-entries">
                        <i class="fas fa-inbox" style="font-size: 48px; margin-bottom: 15px; color: #dee2e6;"></i>
                        <p>Zatím se nikdo nepřihlásil do soutěže.</p>
                        <?php if (isAdmin()): ?>
                            <small style="color: #6c757d; margin-top: 10px; display: block;">
                                Pokud byly odeslány přihlášky, zkontrolujte databázi nebo logy pro chyby.
                            </small>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const contestForm = document.getElementById('contestForm');
        
        if (contestForm) {
            contestForm.addEventListener('submit', function(e) {
                e.preventDefault();
                
                // Clear previous errors
                clearFieldErrors();
                
                const formData = new FormData(this);
                const submitBtn = this.querySelector('.contest-submit-btn');
                const originalText = submitBtn.innerHTML;
                
                // Disable form during submission
                submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Odesílá se...';
                submitBtn.disabled = true;
                
                fetch('contest-handler.php', {
                    method: 'POST',
                    body: formData,
                    headers: {
                        'Accept': 'application/json'
                    }
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        showStatusMessage('success', data.message || 'Přihláška byla úspěšně odeslána!');
                        contestForm.reset();
                        
                        // Redirect to questions page if provided
                        if (data.redirect) {
                            setTimeout(() => {
                                window.location.href = data.redirect;
                            }, 1500);
                        } else {
                            // Fallback: reload page after success to show updated entries (if admin)
                            setTimeout(() => {
                                window.location.reload();
                            }, 2000);
                        }
                    } else {
                        if (data.field_errors) {
                            showFieldErrors(data.field_errors);
                        }
                        showStatusMessage('error', data.message || 'Chyba při odesílání přihlášky');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    showStatusMessage('error', 'Chyba při komunikaci se serverem');
                })
                .finally(() => {
                    submitBtn.innerHTML = originalText;
                    submitBtn.disabled = false;
                });
            });
        }
    });
    
    function clearFieldErrors() {
        document.querySelectorAll('.field-error').forEach(error => {
            error.textContent = '';
        });
        document.querySelectorAll('.form-group input').forEach(input => {
            input.classList.remove('error');
        });
    }
    
    function showFieldErrors(errors) {
        Object.keys(errors).forEach(field => {
            const errorElement = document.getElementById(field + '-error');
            const inputElement = document.getElementById('contest_' + field);
            
            if (errorElement) {
                errorElement.textContent = errors[field];
            }
            if (inputElement) {
                inputElement.classList.add('error');
            }
        });
    }
    
    function showStatusMessage(type, message) {
        const container = document.getElementById('status-messages');
        if (!container) return;
        
        const div = document.createElement('div');
        div.className = `status-message status-${type}`;
        div.innerHTML = `<i class="fas ${type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle'}"></i> ${message}`;
        
        container.innerHTML = '';
        container.appendChild(div);
        
        setTimeout(() => {
            if (div.parentNode) {
                div.remove();
            }
        }, 5000);
    }
    
    function deleteContestEntry(entryId) {
        if (!confirm('Opravdu chcete smazat tuto přihlášku?\n\nTato akce smaže jak registraci, tak i odpovědi na otázky.')) {
            return;
        }
        
        // Show loading state
        const deleteBtn = event.target.closest('button');
        const originalContent = deleteBtn.innerHTML;
        deleteBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
        deleteBtn.disabled = true;
        
        fetch('contest-handler.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
                'Accept': 'application/json'
            },
            body: 'action=delete&entry_id=' + encodeURIComponent(entryId)
        })
        .then(response => {
            if (!response.ok) {
                throw new Error(`HTTP ${response.status}: ${response.statusText}`);
            }
            return response.text().then(text => {
                console.log('Delete response:', text);
                try {
                    return JSON.parse(text);
                } catch (e) {
                    console.error('Failed to parse JSON:', e);
                    console.error('Raw response text:', text);
                    throw new Error('Invalid JSON response from server');
                }
            });
        })
        .then(data => {
            console.log('Delete result:', data);
            if (data.success) {
                showStatusMessage('success', data.message || 'Přihláška byla smazána');
                
                // Remove both the main row and the answers row
                const mainRow = deleteBtn.closest('tr');
                if (mainRow) {
                    // Find the next row (answers row) if it exists
                    const answersRow = mainRow.nextElementSibling;
                    
                    // Animate both rows
                    mainRow.style.transition = 'opacity 0.3s ease';
                    mainRow.style.opacity = '0';
                    
                    if (answersRow && answersRow.classList.contains('answers-row')) {
                        answersRow.style.transition = 'opacity 0.3s ease';
                        answersRow.style.opacity = '0';
                    }
                    
                    setTimeout(() => {
                        // Remove main row
                        mainRow.remove();
                        
                        // Remove answers row if it exists
                        if (answersRow && answersRow.classList.contains('answers-row')) {
                            answersRow.remove();
                        }
                        
                        // Update the count in the header
                        const countSpan = document.querySelector('.contest-entries-section h2 span');
                        if (countSpan) {
                            const currentCount = parseInt(countSpan.textContent.match(/\d+/)[0]);
                            countSpan.textContent = `(${currentCount - 1})`;
                        }
                    }, 300);
                }
            } else {
                showStatusMessage('error', data.message || 'Chyba při mazání přihlášky');
                // Restore button state on error
                deleteBtn.innerHTML = originalContent;
                deleteBtn.disabled = false;
            }
        })
        .catch(error => {
            console.error('Error deleting entry:', error);
            showStatusMessage('error', 'Chyba při komunikaci se serverem: ' + error.message);
            // Restore button state on error
            deleteBtn.innerHTML = originalContent;
            deleteBtn.disabled = false;
        });
    }
</script>
