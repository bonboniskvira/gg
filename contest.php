<?php
// Start session for contest functionality (but don't require login)
session_start();

// Include necessary files
require_once __DIR__ . '/access.php';

// Update last activity if user is logged in (but don't require it)
if (isset($_SESSION['user_id'])) {
    $_SESSION['last_activity'] = time();
}

// Include the contest page content
require_once __DIR__ . '/components/pages/contest.php';
?>

<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>eDO Soutěž</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="icon" type="image/svg+xml" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><text y='.9em' font-size='90'>🏆</text></svg>">
    
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: #f8f9fa;
            line-height: 1.6;
            color: #495057;
        }

        .page-content {
            min-height: 100vh;
            padding: 20px;
        }

        .back-link {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            color: #3498db;
            text-decoration: none;
            font-weight: 500;
            margin-bottom: 20px;
            padding: 10px 15px;
            border-radius: 8px;
            transition: all 0.3s ease;
            background: white;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }

        .back-link:hover {
            background: #f0f3ff;
            transform: translateX(-5px);
            color: #2980b9;
        }

        /* Include admin button styles */
        .mini-btn, .grid-btn, .admin-btn {
            background: #f8f9fa;
            border: 1px solid #dee2e6;
            color: #495057;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 12px;
            cursor: pointer;
            transition: all 0.2s;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }

        .mini-btn:hover, .grid-btn:hover, .admin-btn:hover {
            background: #e9ecef;
            border-color: #adb5bd;
        }

        .delete-btn {
            background: #dc3545 !important;
            color: white !important;
            border-color: #dc3545 !important;
        }

        .delete-btn:hover {
            background: #c82333 !important;
            border-color: #c82333 !important;
        }

        .edit-btn {
            background: #ffc107 !important;
            color: #212529 !important;
            border-color: #ffc107 !important;
        }

        .edit-btn:hover {
            background: #e0a800 !important;
            border-color: #e0a800 !important;
        }

        .view-btn {
            background: #17a2b8 !important;
            color: white !important;
            border-color: #17a2b8 !important;
        }

        .view-btn:hover {
            background: #138496 !important;
            border-color: #138496 !important;
        }

        .download-btn {
            background: #28a745 !important;
            color: white !important;
            border-color: #28a745 !important;
        }

        .download-btn:hover {
            background: #218838 !important;
            border-color: #218838 !important;
        }

        .login-prompt {
            background: #e3f2fd;
            color: #1565c0;
            padding: 15px 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
            border: 1px solid #bbdefb;
        }
        
        .login-prompt a {
            color: #1565c0;
            font-weight: 600;
            text-decoration: none;
        }
        
        .login-prompt a:hover {
            text-decoration: underline;
        }

        /* Responsive design */
        @media (max-width: 768px) {
            .page-content {
                padding: 10px;
            }
        }
    </style>
</head>
<body>
    <div class="page-content">
        <?php if (!isset($_SESSION['user_id'])): ?>
            <div class="login-prompt">
                <i class="fas fa-info-circle"></i>
                Jste zaměstnanec? <a href="login.php">Přihlaste se zde</a> pro přístup k hlavnímu systému.
            </div>
        <?php else: ?>
            <a href="index.php" class="back-link">
                <i class="fas fa-arrow-left"></i>
                Zpět na hlavní stránku
            </a>
        <?php endif; ?>
        
        <!-- Contest content will be inserted here -->
    </div>
</body>
</html>
