<!DOCTYPE html>
<html lang="cs" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Marvin Intranet - Internal company portal">
    <title>Marvin Intranet</title>
    
    <!-- Preload critical resources -->
    <link rel="preload" href="css/critical.css" as="style">
    <link rel="preload" href="script.js" as="script">
    
    <!-- Critical CSS - Inline for fastest loading -->
    <link rel="stylesheet" href="css/critical.css">
    
    <!-- Non-critical CSS - Load asynchronously -->
    <link rel="preload" href="styles.css" as="style" onload="this.onload=null;this.rel='stylesheet'">
    <noscript><link rel="stylesheet" href="styles.css"></noscript>
    
    <!-- Favicon and icons -->
    <link rel="icon" type="image/svg+xml" href="img/favicon.svg">
    <link rel="icon" type="image/png" href="img/favicon.png">
    
    <!-- Meta tags for better SEO and performance -->
    <meta name="robots" content="noindex, nofollow">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    
    <!-- Performance optimization -->
    <meta name="format-detection" content="telephone=no">
    <meta name="msapplication-tap-highlight" content="no">
</head>
<body>
    <!-- Skip to main content for accessibility -->
    <a href="#main-content" class="skip-link">Skip to main content</a>
    
    <div class="container"><?php
// Start output buffering to enable compression
if (!ob_get_level()) {
    ob_start('ob_gzhandler');
}

// Set security headers
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('X-XSS-Protection: 1; mode=block');
header('Referrer-Policy: strict-origin-when-cross-origin');

// Performance headers
header('Cache-Control: public, max-age=3600'); // Cache for 1 hour
header('ETag: "' . md5(filemtime(__FILE__)) . '"');
?>
