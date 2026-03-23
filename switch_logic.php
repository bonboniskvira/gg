<?php
// Include in index.php after session_start()

if (isset($_GET['action']) && $_GET['action'] === 'switch_view' && isset($_GET['target'])) {
    
    require_once 'access.php'; // Ensure functions are available
    
    $target = $_GET['target'];
    $allowed_targets = ['user', 'poradce', 'admin'];
    
    if (in_array($target, $allowed_targets)) {
        
        // Determine if current user has rights to switch
        // 1. Is actual admin
        // 2. Was admin originally (masquerading)
        
        $can_switch = false;
        
        // Check current role directly
        if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin') {
            $can_switch = true;
        }
        
        // Check original role
        if (isset($_SESSION['original_role']) && $_SESSION['original_role'] === 'admin') {
            $can_switch = true;
        }
        
        if ($can_switch) {
            if ($target === 'admin') {
                // Restore
                $_SESSION['role'] = 'admin';
                unset($_SESSION['original_role']);
            } else {
                // Switch to user/poradce
                if (!isset($_SESSION['original_role'])) {
                    // Save original role if not already saved
                     if (isset($_SESSION['role'])) {
                        $_SESSION['original_role'] = $_SESSION['role'];
                     }
                }
                $_SESSION['role'] = $target;
            }
            
            // Redirect to remove query params
            // Ensure no output before header
            if (headers_sent()) {
                echo '<script>window.location.href="index.php";</script>';
                exit;
            } else {
                header("Location: index.php");
                exit;
            }
        }
    }
}
?>