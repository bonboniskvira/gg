<?php



// Include access control



require_once 'access.php';







// Include database configuration



require_once 'config.php';







// Get current user ID from session



$user_id = $_SESSION['user_id'] ?? 0;







// Updated Gemini API key - use the working one from home.php



$apiKey = 'AIzaSyD4VWCt8o3t3J1FTETn2W0BBYlYHTCT0SI';







// Database connection using config



try {



    $pdo = getDatabaseConnection();
} catch (Exception $e) {


    echo '<div class="error-message">' . $e->getMessage() . '</div>';



    return;
}







// Handle creating a new chat session



if (isset($_POST['new_chat'])) {



    try {



        $stmt = $pdo->prepare("INSERT INTO chat_sessions (user_id, title) VALUES (?, ?)");


        $stmt->execute([$user_id, 'Nový rozhovor']);


        $newChatId = $pdo->lastInsertId();


        echo "<script>window.location.href = 'index.php?chat_id=$newChatId#chatbot';</script>";


        return;
    } catch (PDOException $e) {


        $error = "Nelze vytvořit nový rozhovor: " . $e->getMessage();
    }
}







// Handle deleting a chat session



if (isset($_POST['delete_chat']) && isset($_POST['chat_id'])) {



    try {



        $chatId = (int)$_POST['chat_id'];



        // First verify this chat belongs to the current user



        $stmt = $pdo->prepare("SELECT id FROM chat_sessions WHERE id = ? AND user_id = ?");


        $stmt->execute([$chatId, $user_id]);







        if ($stmt->rowCount() > 0) {



            $stmt = $pdo->prepare("DELETE FROM chat_sessions WHERE id = ?");


            $stmt->execute([$chatId]);


            echo "<script>window.location.href = 'index.php#chatbot';</script>";


            return;
        }
    } catch (PDOException $e) {


        $error = "Nelze smazat rozhovor: " . $e->getMessage();
    }
}







// Handle renaming a chat session



if (isset($_POST['rename_chat']) && isset($_POST['chat_id']) && isset($_POST['new_title'])) {



    try {



        $chatId = (int)$_POST['chat_id'];



        $newTitle = trim($_POST['new_title']);







        if (empty($newTitle)) {


            $newTitle = "Nepojmenovaný rozhovor";
        }







        // First verify this chat belongs to the current user



        $stmt = $pdo->prepare("SELECT id FROM chat_sessions WHERE id = ? AND user_id = ?");


        $stmt->execute([$chatId, $user_id]);







        if ($stmt->rowCount() > 0) {



            $stmt = $pdo->prepare("UPDATE chat_sessions SET title = ? WHERE id = ?");


            $stmt->execute([$newTitle, $chatId]);
        }
    } catch (PDOException $e) {


        $error = "Nelze přejmenovat rozhovor: " . $e->getMessage();
    }
}







// Get all chat sessions for the current user



try {


    $stmt = $pdo->prepare("SELECT id, title, created_at FROM chat_sessions 



                         WHERE user_id = ? 



                         ORDER BY updated_at DESC");


    $stmt->execute([$user_id]);


    $chatSessions = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {


    $error = "Nelze načíst rozhovory: " . $e->getMessage();



    $chatSessions = [];
}







// If no chat sessions exist, create one



if (empty($chatSessions)) {



    try {



        $stmt = $pdo->prepare("INSERT INTO chat_sessions (user_id, title) VALUES (?, ?)");


        $stmt->execute([$user_id, 'Nový rozhovor']);


        $newChatId = $pdo->lastInsertId();







        // Reload the chat sessions



        $stmt = $pdo->prepare("SELECT id, title, created_at FROM chat_sessions 



                             WHERE user_id = ? 



                             ORDER BY updated_at DESC");


        $stmt->execute([$user_id]);


        $chatSessions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {


        $error = "Nelze vytvořit výchozí rozhovor: " . $e->getMessage();
    }
}







// Determine which chat is active



$activeChatId = isset($_GET['chat_id']) ? (int)$_GET['chat_id'] : ($chatSessions[0]['id'] ?? 0);







// Verify the active chat belongs to the current user



if ($activeChatId) {



    $stmt = $pdo->prepare("SELECT id, title FROM chat_sessions WHERE id = ? AND user_id = ?");


    $stmt->execute([$activeChatId, $user_id]);


    $activeChat = $stmt->fetch(PDO::FETCH_ASSOC);







    if (!$activeChat) {



        // If the chat doesn't belong to the user, default to their first chat



        $activeChatId = $chatSessions[0]['id'] ?? 0;



        $activeChat = $chatSessions[0] ?? null;
    }
} else {


    $activeChat = $chatSessions[0] ?? null;



    $activeChatId = $activeChat['id'] ?? 0;
}







// Get messages for the active chat



$chatMessages = [];



if ($activeChatId) {



    try {



        $stmt = $pdo->prepare("SELECT id, role, content, timestamp 



                             FROM chat_messages 



                             WHERE session_id = ? 



                             ORDER BY timestamp ASC");


        $stmt->execute([$activeChatId]);


        $chatMessages = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {


        $error = "Nelze načíst zprávy: " . $e->getMessage();
    }
}







// Handle new message submission



if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['message']) && !empty($_POST['message']) && $activeChatId) {



    $userMessage = trim($_POST['message']);



    $apiError = null;







    try {



        // 1. Save user message to database



        $stmt = $pdo->prepare("INSERT INTO chat_messages (session_id, role, content) VALUES (?, 'user', ?)");


        $stmt->execute([$activeChatId, $userMessage]);







        // 2. Update the chat session's updated_at timestamp



        $stmt = $pdo->prepare("UPDATE chat_sessions SET updated_at = CURRENT_TIMESTAMP WHERE id = ?");


        $stmt->execute([$activeChatId]);







        // 3. If this is the first message, update the chat title



        if (count($chatMessages) === 0) {


            $chatTitle = strlen($userMessage) > 30



                ? substr($userMessage, 0, 27) . '...'



                : $userMessage;







            $stmt = $pdo->prepare("UPDATE chat_sessions SET title = ? WHERE id = ?");


            $stmt->execute([$chatTitle, $activeChatId]);
        }







        // 4. Prepare conversation history for context



        $conversationHistory = [];



        foreach ($chatMessages as $msg) {


            // Map assistant role to model for Gemini API



            $apiRole = ($msg['role'] === 'assistant') ? 'model' : $msg['role'];



            $conversationHistory[] = [



                "role" => $apiRole,



                "parts" => [["text" => $msg['content']]]



            ];
        }







        // Add the new user message



        $conversationHistory[] = [



            "role" => "user",



            "parts" => [["text" => $userMessage]]



        ];







        // 5. Call Gemini API with better error handling
        $url = "https://generativelanguage.googleapis.com/v1/models/gemini-2.5-flash:generateContent?key=" . $apiKey;

        // Request data
        $postData = [
            "contents" => $conversationHistory,
            "generationConfig" => [
                "temperature" => 0.7,
                "topK" => 40,
                "topP" => 0.95,
                "maxOutputTokens" => 35000
            ]
        ];







        // Initialize cURL session with better error handling



        $ch = curl_init($url);



        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);



        curl_setopt($ch, CURLOPT_POST, true);



        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($postData));



        curl_setopt($ch, CURLOPT_HTTPHEADER, [



            'Content-Type: application/json'



        ]);







        // Set longer timeouts for AI responses



        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 30);



        curl_setopt($ch, CURLOPT_TIMEOUT, 120);







        // Execute request and get response



        $result = curl_exec($ch);



        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);



        $curlError = curl_error($ch);



        curl_close($ch);







        // Log the actual response for debugging
        error_log("Chatbot API Response - HTTP Code: " . $httpCode);
        error_log("Chatbot API Response - Raw Result: " . substr($result, 0, 500));

        if ($curlError) {
            $apiError = "Chyba připojení k API: " . $curlError;
            error_log("Chatbot cURL Error: " . $curlError);
        } else if ($httpCode !== 200) {
            $apiError = "Chyba API: HTTP kód " . $httpCode;
            error_log("Chatbot HTTP Error: " . $httpCode . " - Response: " . $result);
            if ($result) {
                $responseData = json_decode($result, true);
                if (isset($responseData['error']['message'])) {
                    $apiError .= " - " . $responseData['error']['message'];
                }
            }
        } else {
            $responseData = json_decode($result, true);
            error_log("Chatbot JSON Response: " . print_r($responseData, true));

            if (json_last_error() !== JSON_ERROR_NONE) {
                $apiError = "JSON Error: " . json_last_error_msg();
                error_log("Chatbot JSON Parse Error: " . json_last_error_msg());
            } else if (isset($responseData['candidates'][0]['content']['parts'][0]['text'])) {
                $aiResponse = $responseData['candidates'][0]['content']['parts'][0]['text'];
                error_log("Chatbot Success - AI Response length: " . strlen($aiResponse));



                // Save AI response to database



                $stmt = $pdo->prepare("INSERT INTO chat_messages (session_id, role, content) VALUES (?, 'assistant', ?)");


                $stmt->execute([$activeChatId, $aiResponse]);







                // Reload messages to include new ones



                $stmt = $pdo->prepare("SELECT id, role, content, timestamp 



                                     FROM chat_messages 



                                     WHERE session_id = ? 



                                     ORDER BY timestamp ASC");


                $stmt->execute([$activeChatId]);



                $chatMessages = $stmt->fetchAll(PDO::FETCH_ASSOC);
            } else {


                $apiError = "API nevrátilo očekávanou odpověď";


                error_log("Chatbot Response Structure Error. Response: " . print_r($responseData, true));



                // Check for specific error cases



                if (isset($responseData['candidates'][0]['finishReason'])) {



                    $finishReason = $responseData['candidates'][0]['finishReason'];


                    error_log("Chatbot Finish Reason: " . $finishReason);



                    switch ($finishReason) {

                        case 'SAFETY':



                            $apiError = "Vaše zpráva byla zablokována z bezpečnostních důvodů.";

                            break;

                        case 'RECITATION':



                            $apiError = "Odpověď byla zablokována kvůli možnému porušení autorských práv.";

                            break;

                        case 'MAX_TOKENS':



                            $apiError = "Odpověď je příliš dlouhá. Zkuste položit konkrétnější otázku.";

                            break;

                        default:

                            $apiError = "API ukončilo generování s důvodem: " . $finishReason;
                    }
                }
            }
        }







        // If there was an API error, save it to the chat with detailed info for debugging



        if ($apiError) {



            $debugInfo = "";



            if (isAdmin()) {


                $debugInfo = " (Debug - HTTP: $httpCode, Error: $apiError)";
            }



            // Also add JavaScript console logging for admin debugging



            $jsDebugInfo = "";

            if (isAdmin()) {

                $jsDebugInfo = "<script>console.error('Chatbot API Error:', " . json_encode([

                    'httpCode' => $httpCode,

                    'error' => $apiError,

                    'response' => substr($result ?? '', 0, 200)

                ]) . ");</script>";
            }



            $errorMessage = "Omlouváme se, došlo k chybě při generování odpovědi. Zkuste to prosím znovu." . $debugInfo . $jsDebugInfo;



            $stmt = $pdo->prepare("INSERT INTO chat_messages (session_id, role, content) VALUES (?, 'assistant', ?)");


            $stmt->execute([$activeChatId, $errorMessage]);



            // Log the detailed error for admin debugging



            error_log("Chatbot Error for user {$user_id}: " . $apiError);
        }
    } catch (PDOException $e) {


        $error = "Chyba databáze: " . $e->getMessage();
    }
}



?>







<!--Page Chatbot -->



<div class="page-content" id="chatbot">



    <div class="page-header">



        <h1 class="page-title">Marvin AI</h1>



        <p class="page-subtitle">Zeptej se na cokoliv</p>



    </div>







    <?php if (isset($error)): ?>



        <div class="error-message"><?php echo htmlspecialchars($error); ?></div>



    <?php endif; ?>







    <div class="chatbot-container">



        <!-- Sidebar with chat history -->



        <div class="chat-sidebar">



            <div class="sidebar-header">



                <h2>Vaše konverzace</h2>



                <form method="post" class="new-chat-form">



                    <button type="submit" name="new_chat" class="new-chat-btn">



                        <i class="fas fa-plus"></i> Nový rozhovor



                    </button>



                </form>



            </div>







            <div class="chat-list">



                <?php if (empty($chatSessions)): ?>



                    <div class="no-chats">Zatím žádná historie rozhovorů.</div>



                <?php else: ?>



                    <?php foreach ($chatSessions as $chat): ?>



                        <div class="chat-item <?php echo ($chat['id'] == $activeChatId) ? 'active' : ''; ?>">



                            <a href="index.php?chat_id=<?php echo $chat['id']; ?>#chatbot" class="chat-link">



                                <i class="fas fa-comments"></i>



                                <span class="chat-title"><?php echo htmlspecialchars($chat['title']); ?></span>



                            </a>







                            <?php if ($chat['id'] == $activeChatId): ?>



                                <div class="chat-actions">



                                    <button class="chat-action-btn rename-btn" onclick="showRenameForm(<?php echo $chat['id']; ?>)">



                                        <i class="fas fa-edit"></i>



                                    </button>



                                    <button class="chat-action-btn delete-btn" onclick="confirmDelete(<?php echo $chat['id']; ?>)">



                                        <i class="fas fa-trash"></i>



                                    </button>



                                </div>







                                <div id="rename-form-<?php echo $chat['id']; ?>" class="rename-form" style="display: none;">



                                    <form method="post">



                                        <input type="hidden" name="chat_id" value="<?php echo $chat['id']; ?>">



                                        <input type="text" name="new_title" value="<?php echo htmlspecialchars($chat['title']); ?>" required>



                                        <button type="submit" name="rename_chat">Uložit</button>



                                        <button type="button" onclick="hideRenameForm(<?php echo $chat['id']; ?>)">Zrušit</button>



                                    </form>



                                </div>







                                <div id="delete-confirm-<?php echo $chat['id']; ?>" class="delete-confirm" style="display: none;">



                                    <form method="post">



                                        <input type="hidden" name="chat_id" value="<?php echo $chat['id']; ?>">



                                        <p>Smazat tento rozhovor?</p>



                                        <button type="submit" name="delete_chat" class="confirm-btn">Ano</button>



                                        <button type="button" onclick="hideDeleteConfirm(<?php echo $chat['id']; ?>)" class="cancel-btn">Ne</button>



                                    </form>



                                </div>



                            <?php endif; ?>



                        </div>



                    <?php endforeach; ?>



                <?php endif; ?>



            </div>



        </div>







        <!-- Main chat area -->



        <div class="chat-main">



            <!-- Chat messages -->



            <div class="chat-messages" id="chat-messages">



                <?php if (empty($chatMessages)): ?>



                    <div class="welcome-message">



                        <h2>Vítejte v Marvin AI</h2>



                        <p>Jak vám mohu dnes pomoci?</p>



                    </div>



                <?php else: ?>



                    <?php foreach ($chatMessages as $message): ?>



                        <div class="message <?php echo $message['role']; ?>">



                            <div class="message-content">



                                <?php



                                // Format message content with line breaks and links



                                $content = nl2br(htmlspecialchars($message['content']));



                                $content = preg_replace('/(https?:\/\/[^\s<>"\']+)/', '<a href="$1" target="_blank">$1</a>', $content);



                                echo $content;



                                ?>



                            </div>



                        </div>



                    <?php endforeach; ?>



                <?php endif; ?>



            </div>







            <!-- Chat input form -->



            <div class="chat-input">



                <form method="post" id="message-form">



                    <textarea name="message" id="message-input" placeholder="Napište svoji zprávu..." rows="1" required></textarea>



                    <button type="submit">



                        <i class="fas fa-paper-plane"></i>



                    </button>



                </form>



            </div>



        </div>



    </div>



</div>







<style>
    /* Chatbot Layout */



    .chatbot-container {



        display: flex;



        height: calc(100vh - 200px);



        min-height: 500px;



        border-radius: 10px;



        overflow: hidden;



        background: white;



        box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);



        margin: 20px 0;



    }







    /* Sidebar Styles */



    .chat-sidebar {



        width: 280px;



        background: #f0f2f5;



        border-right: 1px solid #ddd;



        display: flex;



        flex-direction: column;



    }







    .sidebar-header {



        padding: 15px;



        border-bottom: 1px solid #ddd;



    }







    .sidebar-header h2 {


        color: white;



        font-size: 18px;



        margin: 0 0 10px 0;



    }







    .new-chat-btn {


        width: 100%;


        background: #3498db;


        color: white;


        border: none;


        padding: 10px;


        border-radius: 5px;


        cursor: pointer;


        display: flex;


        align-items: center;


        justify-content: center;


        gap: 5px;


    }







    .new-chat-btn:hover {


        background: #2980b9;


    }







    .chat-list {



        flex: 1;



        overflow-y: auto;



        padding: 10px;



    }







    .no-chats {



        color: #888;



        text-align: center;



        padding: 20px;



    }







    .chat-item {



        margin-bottom: 5px;



        border-radius: 5px;



        position: relative;



    }







    .chat-item.active {



        background: rgba(52, 152, 219, 0.1);



    }







    .chat-link {



        display: flex;



        align-items: center;



        padding: 10px;



        color: #333;



        text-decoration: none;



        gap: 10px;



        overflow: hidden;



    }







    .chat-link:hover {



        background: rgba(0, 0, 0, 0.05);



        border-radius: 5px;



    }







    .chat-title {



        flex: 1;



        white-space: nowrap;



        overflow: hidden;



        text-overflow: ellipsis;



    }







    .chat-actions {



        position: absolute;



        right: 10px;



        top: 10px;



        display: none;



    }







    .chat-item.active .chat-actions {



        display: flex;



    }







    .chat-action-btn {



        background: none;



        border: none;



        color: #888;



        cursor: pointer;



        padding: 2px 5px;



    }







    .chat-action-btn:hover {



        color: #333;



    }







    .rename-form,



    .delete-confirm {



        padding: 10px;



        background: white;



        border: 1px solid #ddd;



        border-radius: 5px;



        margin-top: 5px;



        box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);



    }







    .rename-form input {



        width: 100%;



        padding: 5px;



        margin-bottom: 5px;



    }







    .rename-form button,



    .delete-confirm button {



        padding: 5px 10px;



        border: none;



        border-radius: 3px;



        cursor: pointer;



    }







    .rename-form button[type="submit"],



    .confirm-btn {



        background: #3498db;



        color: white;



        margin-right: 5px;



    }







    .rename-form button[type="button"],



    .cancel-btn {



        background: #f0f0f0;



        color: #333;



    }







    .delete-confirm p {



        margin: 0 0 10px 0;



    }







    /* Main Chat Area Styles */



    .chat-main {



        flex: 1;



        display: flex;



        flex-direction: column;



    }







    .chat-messages {



        flex: 1;



        overflow-y: auto;



        padding: 20px;



        display: flex;



        flex-direction: column;



    }







    .welcome-message {



        text-align: center;



        margin: auto;



        max-width: 600px;



    }







    .welcome-message h2 {



        font-size: 24px;



        margin-bottom: 10px;



        color: #2c3e50;



    }







    .welcome-message p {



        color: #7f8c8d;



    }







    .message {



        max-width: 80%;



        margin-bottom: 15px;



        clear: both;



    }







    .message.user {


        align-self: flex-end;



    }







    .message.assistant {


        align-self: flex-start;



    }







    .message-content {



        padding: 12px 16px;



        border-radius: 18px;



        position: relative;



        line-height: 1.5;



    }







    .user .message-content {



        background-color: #3498db;



        color: white;



        border-bottom-right-radius: 4px;



    }







    .user .message-content a {


        color: #e0f0ff;



        text-decoration: underline;



    }







    .assistant .message-content {



        background-color: #f0f2f5;



        color: #333;



        border-bottom-left-radius: 4px;



    }







    .assistant .message-content a {



        color: #2980b9;



    }







    .chat-input {



        padding: 15px;



        border-top: 1px solid #eee;



    }







    .chat-input form {



        display: flex;



        gap: 10px;



    }







    .chat-input textarea {



        flex: 1;



        padding: 12px 15px;



        border: 1px solid #ddd;



        border-radius: 20px;



        outline: none;



        resize: none;



        max-height: 120px;



    }







    .chat-input button {



        background: #3498db;



        color: white;



        border: none;



        width: 40px;



        height: 40px;



        border-radius: 50%;



        display: flex;



        align-items: center;



        justify-content: center;



        cursor: pointer;



    }







    .chat-input button:hover {


        background: #2980b9;



    }







    /* Error message */



    .error-message {



        background-color: #f8d7da;



        color: #721c24;



        padding: 10px;



        margin-bottom: 15px;



        border-radius: 5px;



        border: 1px solid #f5c6cb;



    }







    /* Responsive adjustments */



    @media (max-width: 768px) {



        .chatbot-container {



            flex-direction: column;



            height: calc(100vh - 150px);



        }







        .chat-sidebar {



            width: 100%;



            height: 200px;



            border-right: none;



            border-bottom: 1px solid #ddd;



        }



    }
</style>







<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">







<script>
    // Auto-resize textarea as user types



    document.addEventListener('DOMContentLoaded', function() {



        const messageInput = document.getElementById('message-input');



        const chatMessages = document.getElementById('chat-messages');



        const messageForm = document.getElementById('message-form');







        // Auto-scroll to bottom



        function scrollToBottom() {


            chatMessages.scrollTop = chatMessages.scrollHeight;



        }







        // Resize textarea



        function autoResizeTextarea() {



            messageInput.style.height = 'auto';



            messageInput.style.height = (messageInput.scrollHeight) + 'px';



        }







        // Initial scroll to bottom



        scrollToBottom();







        // Add event listeners



        messageInput.addEventListener('input', autoResizeTextarea);







        messageForm.addEventListener('submit', function() {



            // Show loading state



            const submitBtn = this.querySelector('button[type="submit"]');



            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';



            submitBtn.disabled = true;



        });



    });







    // Chat session management functions



    function showRenameForm(chatId) {



        document.getElementById('rename-form-' + chatId).style.display = 'block';



    }







    function hideRenameForm(chatId) {



        document.getElementById('rename-form-' + chatId).style.display = 'none';



    }







    function confirmDelete(chatId) {



        document.getElementById('delete-confirm-' + chatId).style.display = 'block';



    }







    function hideDeleteConfirm(chatId) {



        document.getElementById('delete-confirm-' + chatId).style.display = 'none';



    }
</script>