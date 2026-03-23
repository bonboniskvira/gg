<?php
session_start();

// Check if user has a valid entry_id
if (!isset($_GET['entry_id'])) {
    header("Location: index.php#contest");
    exit;
}

$entry_id = intval($_GET['entry_id']);
if ($entry_id <= 0) {
    header("Location: index.php#contest");
    exit;
}

// Database connection to get the score
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
        error_log("Contest results database connection error: " . $e->getMessage());
        return null;
    }
}

// Get the user's score
$userScore = null;
$userName = '';

try {
    $conn = getContestDbConnection();
    if ($conn) {
        // Get entry details and score
        $stmt = $conn->prepare("
            SELECT e.name, a.score 
            FROM contest_entries e 
            LEFT JOIN contest_answers a ON e.id = a.entry_id 
            WHERE e.id = ?
        ");
        $stmt->bind_param("i", $entry_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result && $result->num_rows > 0) {
            $row = $result->fetch_assoc();
            $userName = $row['name'];
            $userScore = $row['score'];
        }
        
        $stmt->close();
        $conn->close();
    }
} catch (Exception $e) {
    error_log("Error getting contest results: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>eDO Soutěž - Hotovo!</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="icon" type="image/svg+xml" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><text y='.9em' font-size='90'>🏆</text></svg>">
    
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .results-container {
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
            max-width: 600px;
            width: 100%;
            overflow: hidden;
            text-align: center;
        }

        .results-header {
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
            color: white;
            padding: 40px 30px 20px 30px;
        }

        .success-circle {
            width: 120px;
            height: 120px;
            background: rgba(255, 255, 255, 0.2);
            border: 4px solid rgba(255, 255, 255, 0.3);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px auto;
            animation: successPulse 2s ease-in-out infinite;
        }

        .success-circle i {
            font-size: 48px;
            color: white;
            animation: checkMark 1s ease-in-out;
        }

        .success-title {
            font-size: 3em;
            font-weight: 700;
            margin: 0 0 10px 0;
            animation: fadeInUp 1s ease-out 0.5s both;
        }

        .success-subtitle {
            font-size: 1.2em;
            opacity: 0.9;
            animation: fadeInUp 1s ease-out 1s both;
        }

        .results-content {
            padding: 40px 30px;
        }

        .user-info {
            background: #f8f9fa;
            border-radius: 15px;
            padding: 25px;
            margin-bottom: 30px;
            animation: fadeInUp 1s ease-out 1.5s both;
        }

        .user-name {
            font-size: 1.5em;
            font-weight: 600;
            color: #495057;
            margin-bottom: 15px;
        }

        .score-display {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 15px;
            margin-bottom: 20px;
        }

        .score-badge {
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
            color: white;
            padding: 10px 20px;
            border-radius: 25px;
            font-size: 1.2em;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .score-message {
            color: #6c757d;
            font-size: 1.1em;
            line-height: 1.5;
        }

        .action-buttons {
            display: flex;
            flex-direction: column;
            gap: 15px;
            animation: fadeInUp 1s ease-out 2s both;
        }

        .home-btn {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            padding: 15px 30px;
            border-radius: 10px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            text-decoration: none;
        }

        .home-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(102, 126, 234, 0.3);
            color: white;
            text-decoration: none;
        }

        .secondary-btn {
            background: #f8f9fa;
            color: #6c757d;
            border: 2px solid #e9ecef;
            padding: 12px 25px;
            border-radius: 10px;
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }

        .secondary-btn:hover {
            background: #e9ecef;
            border-color: #dee2e6;
            color: #495057;
            text-decoration: none;
        }

        .celebration-emoji {
            font-size: 2em;
            margin: 20px 0;
            animation: bounce 2s ease-in-out infinite;
        }

        @keyframes successPulse {
            0%, 100% {
                transform: scale(1);
                box-shadow: 0 0 0 0 rgba(255, 255, 255, 0.4);
            }
            50% {
                transform: scale(1.05);
                box-shadow: 0 0 0 20px rgba(255, 255, 255, 0);
            }
        }

        @keyframes checkMark {
            0% {
                transform: scale(0) rotate(-45deg);
                opacity: 0;
            }
            50% {
                transform: scale(1.2) rotate(-45deg);
                opacity: 1;
            }
            100% {
                transform: scale(1) rotate(0deg);
                opacity: 1;
            }
        }

        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        @keyframes bounce {
            0%, 20%, 50%, 80%, 100% {
                transform: translateY(0);
            }
            40% {
                transform: translateY(-10px);
            }
            60% {
                transform: translateY(-5px);
            }
        }

        @media (max-width: 768px) {
            .results-container {
                margin: 10px;
            }
            
            .results-header {
                padding: 30px 20px 15px 20px;
            }
            
            .success-title {
                font-size: 2.5em;
            }
            
            .results-content {
                padding: 30px 20px;
            }
            
            .success-circle {
                width: 100px;
                height: 100px;
            }
            
            .success-circle i {
                font-size: 40px;
            }
        }
    </style>
</head>
<body>
    <div class="results-container">
        <div class="results-header">
            <div class="success-circle">
                <i class="fas fa-check"></i>
            </div>
            <h1 class="success-title">Hotovo!</h1>
            <p class="success-subtitle">Vaše odpovědi byly úspěšně odeslány</p>
        </div>

        <div class="results-content">
            <?php if ($userName): ?>
                <div class="user-info">
                    <div class="user-name">
                        <i class="fas fa-user"></i>
                        <?= htmlspecialchars($userName) ?>
                    </div>
                    
                    <?php if ($userScore !== null): ?>
                        <div class="score-display">
                            <div class="score-badge">
                                <i class="fas fa-trophy"></i>
                                Skóre: <?= $userScore ?>/10
                            </div>
                        </div>
                        
                        <div class="score-message">
                            <?php if ($userScore >= 9): ?>
                                🎉 Výborně! Téměř perfektní nebo perfektní skóre!
                            <?php elseif ($userScore >= 7): ?>
                                🌟 Skvělá práce! Velmi dobrý výsledek!
                            <?php elseif ($userScore >= 5): ?>
                                👍 Dobrá práce! Solidní výsledek!
                            <?php elseif ($userScore >= 3): ?>
                                📚 Není to špatné! Příště to určitě půjde lépe!
                            <?php else: ?>
                                💪 Nezáleží na skóre, hlavně že jste se zapojili!
                            <?php endif; ?>
                        </div>
                    <?php else: ?>
                        <div class="score-message">
                            Děkujeme za účast v soutěži!
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <div class="celebration-emoji">🎊</div>

            <div class="action-buttons">
                <a href="index.php" class="home-btn">
                    <i class="fas fa-home"></i>
                    Zpět na hlavní stránku
                </a>
                
                <a href="index.php#contest" class="secondary-btn">
                    <i class="fas fa-trophy"></i>
                    Zpět na stránku soutěže
                </a>
            </div>
        </div>
    </div>

    <script>
        // Add some confetti effect on page load
        document.addEventListener('DOMContentLoaded', function() {
            // Simple confetti effect using CSS animations
            const container = document.querySelector('.results-container');
            
            // Create confetti elements
            for (let i = 0; i < 20; i++) {
                setTimeout(() => {
                    createConfetti();
                }, i * 100);
            }
        });

        function createConfetti() {
            const confetti = document.createElement('div');
            confetti.style.cssText = `
                position: fixed;
                width: 10px;
                height: 10px;
                background: ${['#ff6b6b', '#4ecdc4', '#45b7d1', '#96ceb4', '#ffeaa7'][Math.floor(Math.random() * 5)]};
                top: -10px;
                left: ${Math.random() * 100}vw;
                border-radius: 50%;
                pointer-events: none;
                z-index: 1000;
                animation: confettiFall ${Math.random() * 3 + 2}s linear forwards;
            `;
            
            document.body.appendChild(confetti);
            
            setTimeout(() => {
                confetti.remove();
            }, 5000);
        }

        // Add confetti animation CSS
        const style = document.createElement('style');
        style.textContent = `
            @keyframes confettiFall {
                to {
                    transform: translateY(100vh) rotate(360deg);
                }
            }
        `;
        document.head.appendChild(style);
    </script>
</body>
</html>
