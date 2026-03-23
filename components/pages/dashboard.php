<?php
// Block direct access and enforce login
require_once __DIR__ . '/../../check-access.php';

// Include database configuration for chatbot
require_once __DIR__ . '/../../config.php';

// Get current user ID from session
$user_id = $_SESSION['user_id'] ?? 0;

// Define isAdmin function if not already defined
if (!function_exists('isAdmin')) {
    function isAdmin() {
        return isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
    }
}

// Gemini API key
$apiKey = 'AIzaSyD4VWCt8o3t3J1FTETn2W0BBYlYHTCT0SI';

// Database connection using config
try {
    $pdo = getDatabaseConnection();
} catch (Exception $e) {
    $pdo = null;
}

// Get user's latest chat session for dashboard widget
$dashboardChatId = 0;
$dashboardMessages = [];

if ($pdo && $user_id) {
    try {
        // Get or create a dashboard chat session
        $stmt = $pdo->prepare("SELECT id FROM chat_sessions WHERE user_id = ? AND title = ? ORDER BY updated_at DESC LIMIT 1");
        $stmt->execute([$user_id, 'Dashboard Chat']);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($result) {
            $dashboardChatId = $result['id'];
        } else {
            // Create a new dashboard chat session
            $stmt = $pdo->prepare("INSERT INTO chat_sessions (user_id, title) VALUES (?, ?)");
            $stmt->execute([$user_id, 'Dashboard Chat']);
            $dashboardChatId = $pdo->lastInsertId();
        }
        
        // Get recent messages (last 5) for dashboard widget
        if ($dashboardChatId) {
            $stmt = $pdo->prepare("SELECT role, content, timestamp FROM chat_messages WHERE session_id = ? ORDER BY timestamp DESC LIMIT 5");
            $stmt->execute([$dashboardChatId]);
            $dashboardMessages = array_reverse($stmt->fetchAll(PDO::FETCH_ASSOC));
        }
    } catch (PDOException $e) {
        // Handle error silently
    }
}

// Handle dashboard chat message
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['dashboard_message']) && !empty($_POST['dashboard_message']) && $dashboardChatId && $pdo) {
    $userMessage = trim($_POST['dashboard_message']);
    
    try {
        // Save user message
        $stmt = $pdo->prepare("INSERT INTO chat_messages (session_id, role, content) VALUES (?, 'user', ?)");
        $stmt->execute([$dashboardChatId, $userMessage]);
        
        // Update chat session
        $stmt = $pdo->prepare("UPDATE chat_sessions SET updated_at = CURRENT_TIMESTAMP WHERE id = ?");
        $stmt->execute([$dashboardChatId]);
        
        // Get conversation history for context (last 10 messages)
        $stmt = $pdo->prepare("SELECT role, content FROM chat_messages WHERE session_id = ? ORDER BY timestamp DESC LIMIT 10");
        $stmt->execute([$dashboardChatId]);
        $history = array_reverse($stmt->fetchAll(PDO::FETCH_ASSOC));
        
        $conversationHistory = [];
        foreach ($history as $msg) {
            // Map assistant role to model for Gemini API
            $apiRole = ($msg['role'] === 'assistant') ? 'model' : $msg['role'];
            
            $conversationHistory[] = [
                "role" => $apiRole,
                "parts" => [["text" => $msg['content']]]
            ];
        }
        
        // Call Gemini API
        $url = "https://generativelanguage.googleapis.com/v1/models/gemini-2.5-flash:generateContent?key=" . $apiKey;
        
        $postData = [
            "contents" => $conversationHistory,
            "generationConfig" => [
                "temperature" => 0.7,
                "maxOutputTokens" => 12000
            ]
        ];
        
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($postData));
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 30);
        curl_setopt($ch, CURLOPT_TIMEOUT, 60);
        
        $result = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        
        // Add logging for debugging
        error_log("Dashboard Chatbot API Response - HTTP Code: " . $httpCode);
        error_log("Dashboard Chatbot API Response - Raw Result: " . substr($result, 0, 500));
        
        if (curl_errno($ch) || $httpCode !== 200) {
            $curlError = curl_error($ch);
            error_log("Dashboard Chatbot Error - cURL: " . $curlError . ", HTTP: " . $httpCode . ", Response: " . $result);
            $aiResponse = "Omlouváme se, v tuto chvíli nelze vygenerovat odpověď. Zkuste to prosím později.";
            
            // Add JavaScript console logging for debugging (admin only)
            if (isAdmin()) {
                $aiResponse .= "<script>console.error('Dashboard Chatbot API Error:', " . json_encode([
                    'httpCode' => $httpCode,
                    'curlError' => $curlError,
                    'response' => substr($result ?? '', 0, 200)
                ]) . ");</script>";
            }
        } else {
            $responseData = json_decode($result, true);
            error_log("Dashboard Chatbot JSON Response: " . print_r($responseData, true));
            
            if (json_last_error() !== JSON_ERROR_NONE) {
                error_log("Dashboard Chatbot JSON Parse Error: " . json_last_error_msg());
                $aiResponse = "Chyba při zpracování odpovědi API.";
            } else {
                $aiResponse = $responseData['candidates'][0]['content']['parts'][0]['text'] ?? "Nepodařilo se vygenerovat odpověď.";
                
                if ($aiResponse === "Nepodařilo se vygenerovat odpověď." && isAdmin()) {
                    $aiResponse .= "<script>console.error('Dashboard Chatbot Response Structure Error:', " . json_encode($responseData) . ");</script>";
                }
            }
        }
        
        curl_close($ch);
        
        // Save AI response
        $stmt = $pdo->prepare("INSERT INTO chat_messages (session_id, role, content) VALUES (?, 'assistant', ?)");
        $stmt->execute([$dashboardChatId, $aiResponse]);
        
        // Reload messages
        $stmt = $pdo->prepare("SELECT role, content, timestamp FROM chat_messages WHERE session_id = ? ORDER BY timestamp DESC LIMIT 5");
        $stmt->execute([$dashboardChatId]);
        $dashboardMessages = array_reverse($stmt->fetchAll(PDO::FETCH_ASSOC));
        
    } catch (Exception $e) {
        // Handle error silently
    }
}

// Function to format bulletin text for preview
function formatBulletinTextPreview($text) {
    $text = preg_replace('/\[b\](.*?)\[\/b\]/s', '<strong>$1</strong>', $text);
    $text = preg_replace('/\[i\](.*?)\[\/i\]/s', '<em>$1</em>', $text);
    $text = preg_replace('/\[u\](.*?)\[\/u\]/s', '<u>$1</u>', $text);
    $text = preg_replace('/\[url=(.*?)\](.*?)\[\/url\]/s', '<a href="$1" target="_blank" rel="noopener noreferrer">$2</a>', $text);
    return $text;
}
?>

<!--Page Dashboard-->
<div class="page-content" id="dashboard">
    <div class="content-area">
        <div class="dashboard-grid">
            <!-- Chatbot Widget -->
            <div class="dashboard-section dashboard-chat-widget">
                <div class="dashboard-chat-header">
                    <h4><i class="fas fa-robot"></i> Marvin AI</h4>
                    <a href="index.php#chatbot" class="full-chat-link">Plný chat</a>
                </div>
                
                <div class="dashboard-chat-messages" id="dashboard-chat-messages">
                    <?php if (empty($dashboardMessages)): ?>
                        <div class="dashboard-welcome-message">
                            <i class="fas fa-comment-dots"></i>
                            <p>Zeptejte se na cokoliv!</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($dashboardMessages as $message): ?>
                            <div class="dashboard-message <?php echo $message['role']; ?>">
                                <div class="dashboard-message-content">
                                    <?php echo nl2br(htmlspecialchars($message['content'])); ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
                
                <div class="dashboard-chat-input">
                    <form method="post" id="dashboard-chat-form">
                        <input type="text" name="dashboard_message" placeholder="Zeptejte se..." required>
                        <button type="submit">
                            <i class="fas fa-paper-plane"></i>
                        </button>
                    </form>
                </div>
            </div>

            <!-- Latest bulletin posts -->
            <div class="dashboard-section dashboard-posts">
                <h4>Poslední příspěvky na nástěnce</h4>

                <?php
                // Get the latest bulletin posts to display on dashboard - Updated for Wedos hosting
                try {
                    $pdo_bulletin = new PDO("mysql:host=md396.wedos.net;port=3306;dbname=d394711_main", "w394711_main", "mnpJtgaJ");
                    $pdo_bulletin->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                    
                    // Get latest 3 posts with pinned posts first
                    $stmt = $pdo_bulletin->query("
                        SELECT 
                            bulletin_posts.*, 
                            users.username 
                        FROM 
                            bulletin_posts 
                        LEFT JOIN 
                            users ON bulletin_posts.user_id = users.id 
                        ORDER BY 
                            pinned DESC, 
                            created_at DESC
                        LIMIT 3
                    ");
                    
                    $dashboardPosts = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    
                    if (empty($dashboardPosts)) {
                        echo '<p class="no-posts">Zatím nejsou žádné příspěvky.</p>';
                    } else {
                        foreach ($dashboardPosts as $post) {
                            ?>
                            <div class="dashboard-post <?= $post['pinned'] ? 'pinned' : '' ?>">
                                <?php if ($post['pinned']): ?>
                                    <div class="pinned-badge-small" title="Připnutý příspěvek">📌</div>
                                <?php endif; ?>
                                
                                <h5 class="post-title"><?= htmlspecialchars($post['title']) ?></h5>
                                
                                <div class="post-meta">
                                    <span class="post-author">
                                        <?= htmlspecialchars($post['username']) ?>
                                    </span>
                                    <span class="post-date">
                                        <?= date('d.m.Y', strtotime($post['created_at'])) ?>
                                    </span>
                                </div>
                                
                                <div class="post-content-preview">
                                    <?php 
                                    $preview = substr($post['content'], 0, 100) . (strlen($post['content']) > 100 ? '...' : '');
                                    echo formatBulletinTextPreview(htmlspecialchars($preview));
                                    ?>
                                </div>

                                <a href="#bulletin" class="read-more">Číst více</a>
                            </div>
                            <?php
                        }
                        
                        // Show "View all" link if there are posts
                        echo '<a href="#bulletin" class="view-all-posts">Zobrazit všechny příspěvky</a>';
                    }
                } catch (PDOException $e) {
                    echo '<p class="error-message">Nepodařilo se načíst příspěvky.</p>';
                }
                ?>
            </div>
        </div>
    </div>
</div>

<style>
.dashboard-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 30px;
    margin: 32px 0;
}

.dashboard-section {
    background: white;
    border-radius: 10px;
    padding: 20px;
    box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
    display: flex;
    flex-direction: column;
}

/* Dashboard Chat Widget */
.dashboard-chat-widget {
    border-left: 4px solid #3498db;
    height: 500px;
    
}

.dashboard-chat-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 15px;
    padding-bottom: 10px;
    border-bottom: 1px solid #eee;
}

.dashboard-chat-header h4 {
    margin: 0;
    color: #2c3e50;
    font-size: 16px;
}

.full-chat-link {
    color: #3498db;
    text-decoration: none;
    font-size: 12px;
    padding: 5px 10px;
    border: 1px solid #3498db;
    border-radius: 15px;
    transition: all 0.2s;
}

.full-chat-link:hover {
    background: #3498db;
    color: white;
}

.dashboard-chat-messages {
    flex: 1;
    overflow-y: auto;
    padding: 10px 0;
    display: flex;
    flex-direction: column;
    gap: 10px;
    max-height: 350px;
}

.dashboard-welcome-message {
    text-align: center;
    color: #7f8c8d;
    margin: auto;
    padding: 20px;
}

.dashboard-welcome-message i {
    font-size: 24px;
    margin-bottom: 10px;
    display: block;
}

.dashboard-message {
    max-width: 85%;
}

.dashboard-message.user {
    align-self: flex-end;
}

.dashboard-message.assistant {
    align-self: flex-start;
}

.dashboard-message-content {
    padding: 8px 12px;
    border-radius: 12px;
    font-size: 13px;
    line-height: 1.4;
}

.dashboard-message.user .dashboard-message-content {
    background: #3498db;
    color: white;
    border-bottom-right-radius: 4px;
}

.dashboard-message.assistant .dashboard-message-content {
    background: #f0f2f5;
    color: #333;
    border-bottom-left-radius: 4px;
}

.dashboard-chat-input {
    margin-top: 15px;
    padding-top: 15px;
    border-top: 1px solid #eee;
}

.dashboard-chat-input form {
    display: flex;
    gap: 10px;
}

.dashboard-chat-input input {
    flex: 1;
    padding: 8px 12px;
    border: 1px solid #ddd;
    border-radius: 15px;
    outline: none;
    font-size: 13px;
}

.dashboard-chat-input input:focus {
    border-color: #3498db;
}

.dashboard-chat-input button {
    background: #3498db;
    color: white;
    border: none;
    width: 32px;
    height: 32px;
    border-radius: 50%;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 12px;
}

.dashboard-chat-input button:hover {
    background: #2980b9;
}

/* Dashboard Posts */
.dashboard-posts {
    border-left: 4px solid #e74c3c;
}

.dashboard-posts h4 {
    margin: 0 0 15px 0;
    color: #2c3e50;
    font-size: 16px;
}

.dashboard-post {
    background: #f8f9fa;
    border-radius: 8px;

    margin-bottom: 15px;
    border-left: 4px solid #e74c3c;
    transition: transform 0.2s;
    position: relative;
}

.dashboard-post:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
}

.dashboard-post.pinned {
    border-left-color: #f39c12;
    background: #fef9e7;
}

.pinned-badge-small {
    position: absolute;
    top: 10px;
    right: 10px;
    font-size: 12px;
}

.post-title {
    margin: 0 0 8px 0;
    color: #2c3e50;
    font-size: 14px;
    font-weight: 600;
}

.post-meta {
    display: flex;
    justify-content: space-between;
    margin-bottom: 8px;
    font-size: 11px;
    color: #7f8c8d;
}

.post-content-preview {
    color: #555;
    font-size: 12px;
    line-height: 1.4;
    margin-bottom: 8px;
}

.read-more {
    color: #3498db;
    text-decoration: none;
    font-size: 12px;
    font-weight: 500;
}

.read-more:hover {
    text-decoration: underline;
}

.view-all-posts {
    display: block;
    text-align: center;
    color: #3498db;
    text-decoration: none;
    font-weight: 500;
    padding: 10px;
    border: 1px solid #3498db;
    border-radius: 5px;
    margin-top: 10px;
    transition: all 0.2s;
}

.view-all-posts:hover {
    background: #3498db;
    color: white;
}

.no-posts {
    text-align: center;
    color: #7f8c8d;
    padding: 20px;
    font-style: italic;
}

.error-message {
    color: #e74c3c;
    text-align: center;
    padding: 10px;
    background: #ffeaea;
    border-radius: 5px;
}

/* Responsive design */
@media (max-width: 1024px) {
    .dashboard-grid {
        grid-template-columns: 1fr;
        gap: 20px;
    }
    
    .dashboard-chat-widget {
        height: 400px;
    }
    
    .dashboard-chat-messages {
        max-height: 250px;
    }
}

@media (max-width: 768px) {
    .dashboard-grid {
        margin: 10px 0;
    }
    
    .dashboard-section {
        padding: 15px;
    }
    
    .dashboard-chat-widget {
        height: 350px;
    }
    
    .dashboard-chat-messages {
        max-height: 200px;
    }
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Auto-scroll dashboard chat to bottom
    const dashboardChatMessages = document.getElementById('dashboard-chat-messages');
    if (dashboardChatMessages) {
        dashboardChatMessages.scrollTop = dashboardChatMessages.scrollHeight;
    }

    // Handle dashboard chat form submission
    const dashboardChatForm = document.getElementById('dashboard-chat-form');
    if (dashboardChatForm) {
        dashboardChatForm.addEventListener('submit', function() {
            const submitBtn = this.querySelector('button[type="submit"]');
            const originalContent = submitBtn.innerHTML;
            
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
            submitBtn.disabled = true;
            
            // Re-enable after a delay in case of errors
            setTimeout(() => {
                submitBtn.innerHTML = originalContent;
                submitBtn.disabled = false;
            }, 10000);
        });
    }
});
</script>