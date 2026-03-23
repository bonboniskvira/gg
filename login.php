<?php
// Start session
session_start();

// Check if user is already logged in
if (isset($_SESSION['user_id']) && 
    isset($_SESSION['username']) && 
    isset($_SESSION['role']) &&
    !empty($_SESSION['user_id'])) {
    header("Location: index.php");
    exit;
}

// Display any messages
$message = $_GET['message'] ?? '';

// Test database connection first
$db_connection_ok = false;
$db_error = '';

try {
    $conn = new mysqli("md392.wedos.net", "w394711_main", "mnpJtgaJ", "d394711_main");
    if ($conn->connect_error) {
        $db_error = "Connection failed: " . $conn->connect_error;
    } else {
        $db_connection_ok = true;
    }
} catch (Exception $e) {
    $db_error = "Database exception: " . $e->getMessage() . " (User: w394711_main, Host: md392.wedos.net)";
}

// Process login attempt
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (!$db_connection_ok) {
        $error = "Database connection failed: " . $db_error;
    } else {
        // Get and sanitize inputs
        $username = $conn->real_escape_string($_POST['username']);
        $password = $_POST['password'];

        // Get user from database
        $sql = "SELECT id, username, password, role FROM users WHERE username = '$username'";
        $result = $conn->query($sql);

        if ($result->num_rows == 1) {
            $user = $result->fetch_assoc();

            // Verify password
            if (password_verify($password, $user['password'])) {
                // Password correct, create secure session
                session_regenerate_id(true);
                
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['role'] = $user['role'];
                $_SESSION['login_time'] = time();
                $_SESSION['last_activity'] = time();
                $_SESSION['ip_address'] = $_SERVER['REMOTE_ADDR'] ?? 'unknown';

                // Redirect to main page
                header("Location: index.php");
                exit;
            } else {
                $error = "Nesprávné heslo";
            }
        } else {
            $error = "Uživatelské jméno nenalezeno";
        }
    }
    
    if (isset($conn)) {
        $conn->close();
    }
}
?>

<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>eDO Sys - Přihlášení</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 50%, #667eea 100%);
            background-size: 120% 120%; /* Much more subtle scaling */
            background-attachment: fixed;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
            overflow: hidden;
            animation: gradientShift 30s ease infinite; /* Much slower animation */
        }

        @keyframes gradientShift {
            0% { background-position: 0% 50%; }
            50% { background-position: 100% 50%; }
            100% { background-position: 0% 50%; }
        }

        /* Background pattern - minimal scaling */
        body::before {
            content: '';
            position: absolute;
            top: -15%; /* Much smaller extension */
            left: -15%; /* Much smaller extension */
            right: -15%; /* Much smaller extension */
            bottom: -15%; /* Much smaller extension */
            background-image: 
                radial-gradient(circle at 25% 25%, rgba(255, 255, 255, 0.02) 0%, transparent 50%),
                radial-gradient(circle at 75% 75%, rgba(255, 255, 255, 0.02) 0%, transparent 50%),
                radial-gradient(circle at 50% 50%, rgba(255, 255, 255, 0.01) 0%, transparent 70%);
            background-size: 120px 120px, 160px 160px, 200px 200px; /* Much smaller patterns */
            animation: float 35s ease-in-out infinite; /* Much slower animation */
            transform: scale(1.05); /* Very minimal scaling */
        }

        @keyframes float {
            0%, 100% { transform: scale(1.05) translateY(0px) rotate(0deg); }
            50% { transform: scale(1.05) translateY(-3px) rotate(0.5deg); } /* Minimal movement */
        }

        .login-container {
            width: 100%;
            max-width: 420px;
            margin: 0 20px;
            position: relative;
            z-index: 1;
        }

        .login-card {
            background: rgba(255, 255, 255, 0.98);
            backdrop-filter: blur(25px);
            border-radius: 20px;
            padding: 40px;
            box-shadow: 
                0 25px 50px rgba(0, 0, 0, 0.15),
                0 15px 25px rgba(0, 0, 0, 0.08),
                inset 0 1px 0 rgba(255, 255, 255, 0.8);
            border: 1px solid rgba(255, 255, 255, 0.3);
            transition: all 0.3s ease;
        }

        .login-card:hover {
            transform: translateY(-5px);
            box-shadow: 
                0 35px 70px rgba(0, 0, 0, 0.2),
                0 20px 35px rgba(0, 0, 0, 0.12),
                inset 0 1px 0 rgba(255, 255, 255, 0.8);
        }

        .login-header {
            text-align: center;
            margin-bottom: 40px;
        }

        .logo-container {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 80px;
            height: 80px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 50%, #667eea 100%);
            background-size: 120% 120%; /* Minimal scaling on logo */
            border-radius: 20px;
            margin-bottom: 20px;
            box-shadow: 0 15px 35px rgba(102, 126, 234, 0.4);
            animation: logoGradient 25s ease infinite; /* Much slower animation */
        }

        @keyframes logoGradient {
            0% { background-position: 0% 50%; }
            50% { background-position: 100% 50%; }
            100% { background-position: 0% 50%; }
        }

        .logo-container i {
            font-size: 36px;
            color: white;
            filter: drop-shadow(0 2px 4px rgba(0,0,0,0.2));
        }

        .login-title {
            color: #2d3748;
            font-size: 28px;
            font-weight: 700;
            margin-bottom: 8px;
            letter-spacing: -0.5px;
        }

        .login-subtitle {
            color: #718096;
            font-size: 16px;
            font-weight: 400;
        }

        .status-alert {
            padding: 16px 20px;
            border-radius: 12px;
            margin-bottom: 24px;
            font-size: 14px;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .status-success {
            background: rgba(72, 187, 120, 0.1);
            color: #2f855a;
            border: 1px solid rgba(72, 187, 120, 0.2);
        }

        .status-error {
            background: rgba(245, 101, 101, 0.1);
            color: #c53030;
            border: 1px solid rgba(245, 101, 101, 0.2);
        }

        .status-info {
            background: rgba(66, 153, 225, 0.1);
            color: #2b6cb0;
            border: 1px solid rgba(66, 153, 225, 0.2);
        }

        .form-group {
            margin-bottom: 24px;
        }

        .form-label {
            display: block;
            margin-bottom: 8px;
            color: #2d3748;
            font-size: 14px;
            font-weight: 600;
            letter-spacing: 0.025em;
        }

        .form-input {
            width: 100%;
            padding: 16px 20px;
            border: 2px solid #e2e8f0;
            border-radius: 12px;
            font-size: 16px;
            font-weight: 400;
            color: #2d3748;
            background: #ffffff;
            transition: all 0.2s ease;
            font-family: inherit;
        }

        .form-input:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
            transform: translateY(-1px);
        }

        .form-input:hover {
            border-color: #cbd5e0;
        }

        .login-button {
            width: 100%;
            padding: 18px 24px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 50%, #667eea 100%);
            background-size: 120% 120%; /* Minimal scaling on button */
            color: white;
            border: none;
            border-radius: 12px;
            cursor: pointer;
            font-size: 16px;
            font-weight: 600;
            letter-spacing: 0.025em;
            transition: all 0.2s ease;
            font-family: inherit;
            position: relative;
            overflow: hidden;
            animation: buttonGradient 20s ease infinite; /* Much slower animation */
        }

        @keyframes buttonGradient {
            0% { background-position: 0% 50%; }
            50% { background-position: 100% 50%; }
            100% { background-position: 0% 50%; }
        }

        .login-button::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.3), transparent);
            transition: left 0.5s ease;
        }

        .login-button:hover::before {
            left: 100%;
        }

        .login-button:hover {
            transform: translateY(-2px);
            box-shadow: 0 20px 40px rgba(102, 126, 234, 0.5);
        }

        .login-button:active {
            transform: translateY(0);
        }

        .login-button:disabled {
            background: #a0aec0;
            cursor: not-allowed;
            transform: none;
            box-shadow: none;
        }

        .login-button:disabled::before {
            display: none;
        }

        .button-text {
            position: relative;
            z-index: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }

        .footer-text {
            text-align: center;
            margin-top: 32px;
            color: #718096;
            font-size: 14px;
        }

        .footer-text a {
            color: #667eea;
            text-decoration: none;
            font-weight: 500;
        }

        .footer-text a:hover {
            text-decoration: underline;
        }

        /* Responsive design */
        @media (max-width: 480px) {
            body::before {
                top: -20%; /* Slightly more on mobile but still minimal */
                left: -20%;
                right: -20%;
                bottom: -20%;
                transform: scale(1.1); /* Even less scaling on mobile */
            }

            .login-card {
                padding: 30px;
                margin: 20px;
                border-radius: 16px;
                background: rgba(255, 255, 255, 0.99);
            }

            .login-title {
                font-size: 24px;
            }

            .logo-container {
                width: 70px;
                height: 70px;
            }

            .logo-container i {
                font-size: 32px;
            }
        }

        /* Loading animation */
        .spinner {
            width: 20px;
            height: 20px;
            border: 2px solid rgba(255, 255, 255, 0.3);
            border-radius: 50%;
            border-top-color: white;
            animation: spin 1s ease-in-out infinite;
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }
    </style>
</head>

<body>
    <div class="login-container">
        <div class="login-card">
            <div class="login-header">
                <div class="logo-container">
                    <i class="fas fa-building"></i>
                </div>
                <h1 class="login-title">eDO Sys</h1>
                <p class="login-subtitle">Interní systém společnosti</p>
            </div>

            <!-- Database Status -->
            <div class="status-alert <?= $db_connection_ok ? 'status-success' : 'status-error' ?>">
                <i class="fas <?= $db_connection_ok ? 'fa-check-circle' : 'fa-exclamation-triangle' ?>"></i>
                <?php if ($db_connection_ok): ?>
                    Připojení k databázi je aktivní
                <?php else: ?>
                    Chyba databáze: <?= htmlspecialchars($db_error) ?>
                <?php endif; ?>
            </div>

            <?php if (!empty($message)): ?>
                <div class="status-alert status-info">
                    <i class="fas fa-info-circle"></i>
                    <?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>

            <?php if (isset($error)): ?>
                <div class="status-alert status-error">
                    <i class="fas fa-exclamation-circle"></i>
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <form method="POST" action="" id="loginForm" autocomplete="on">
                <div class="form-group">
                    <label for="username" class="form-label">Uživatelské jméno</label>
                    <input type="text" 
                           id="username" 
                           name="username" 
                           class="form-input" 
                           required 
                           autocomplete="username"
                           autofill="username">
                </div>

                <div class="form-group">
                    <label for="password" class="form-label">Heslo</label>
                    <input type="password" 
                           id="password" 
                           name="password" 
                           class="form-input" 
                           required 
                           autocomplete="current-password"
                           autofill="current-password">
                </div>

                <button type="submit" 
                        class="login-button" 
                        <?= !$db_connection_ok ? 'disabled' : '' ?> 
                        id="loginBtn"
                        name="login_submit">
                    <span class="button-text">
                        <i class="fas fa-sign-in-alt"></i>
                        <span id="btnText">Přihlásit se</span>
                    </span>
                </button>
            </form>

            <div class="footer-text">
                <p>&copy; <?= date('Y') ?> Ing. Michal Kučera. Všechna práva vyhrazena.</p>
            </div>
        </div>
    </div>

    <script>
        document.getElementById('loginForm').addEventListener('submit', function() {
            const btn = document.getElementById('loginBtn');
            const btnText = document.getElementById('btnText');
            const spinner = document.createElement('div');
            spinner.className = 'spinner';
            
            btn.disabled = true;
            btnText.textContent = 'Přihlašuji...';
            btnText.appendChild(spinner);
        });

        // Focus on username field when page loads
        document.getElementById('username').focus();

        // Add enter key support
        document.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                document.getElementById('loginForm').submit();
            }
        });
    </script>
</body>
</html>

