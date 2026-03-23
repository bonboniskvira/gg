<?php
// Chatbot configuration - keep this file secure and out of version control

// Gemini API configuration
define('GEMINI_API_KEY', 'AIzaSyBBwXnWNIviOOD9Kk7YTr4rq4t0zrHlRkk');
define('GEMINI_API_URL', 'https://generativelanguage.googleapis.com/v1/models/gemini-1.5-flash:generateContent');

// API settings
define('CHATBOT_MAX_TOKENS', 800);
define('CHATBOT_TEMPERATURE', 0.7);
define('CHATBOT_TIMEOUT', 30);
define('CHATBOT_CONNECT_TIMEOUT', 10);

// Rate limiting (requests per minute)
define('CHATBOT_RATE_LIMIT', 60);

return [
    'api_key' => GEMINI_API_KEY,
    'api_url' => GEMINI_API_URL,
    'max_tokens' => CHATBOT_MAX_TOKENS,
    'temperature' => CHATBOT_TEMPERATURE,
    'timeout' => CHATBOT_TIMEOUT,
    'connect_timeout' => CHATBOT_CONNECT_TIMEOUT,
    'rate_limit' => CHATBOT_RATE_LIMIT
];
?>
