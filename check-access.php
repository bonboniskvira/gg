<?php
// Universal access check for all component pages
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Prevent browser caching for all protected pages
if (!headers_sent()) {
    header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
    header("Cache-Control: post-check=0, pre-check=0", false);
    header("Pragma: no-cache");
}

require_once __DIR__ . '/access.php';

// Check if this is a direct access attempt
$script_name = basename($_SERVER['SCRIPT_NAME']);
$request_uri = $_SERVER['REQUEST_URI'];

// If someone tries to access component files directly, block them
if (strpos($request_uri, '/components/') !== false || 
    strpos(__FILE__, '/components/') !== false) {
    
    // This is a direct access attempt to a component file
    if (!isLoggedIn()) {
        header("Location: login.php?message=" . urlencode("Direct access denied. Please log in."));
        exit;
    }
}

// For all other cases, just enforce login
requireLogin();
?>
