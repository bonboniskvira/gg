<?php



// Check if we're on a live server (Wedos) or local development



$isLiveServer = !in_array($_SERVER['HTTP_HOST'], ['127.0.0.1', '127.0.0.1']);







// Start session
session_start();

// Prevent browser caching
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

// Include switch logic
require_once 'switch_logic.php';







// Check if user is logged in



if (!isset($_SESSION['user_id'])) {



    header("Location: login.php");



    exit;
}







// Include header



require_once 'components/header.php';







// Include sidebar



require_once 'components/pages/admin.php';



require_once 'components/pages/homepage.php';



require_once 'components/sidebar.php';







// Include menu



require_once 'components/menu-top.php';







// Include all pages



require_once 'components/pages/dashboard.php';



require_once 'components/pages/videos.php';



require_once 'components/pages/documents.php';



require_once 'components/pages/podcasts.php';



require_once 'components/pages/chatbot.php';



require_once 'components/pages/test.php';



require_once 'components/pages/list.php';



require_once 'components/pages/agreements.php';



require_once 'components/pages/search.php';



require_once 'components/pages/marketing.php';



require_once 'components/pages/contest.php';



require_once 'components/pages/archives.php';



require_once 'components/pages/pdfs.php';



require_once 'components/pages/other.php';



require_once 'components/pages/links.php';



require_once 'components/pages/edo.php';



require_once 'components/pages/courses.php';



require_once 'components/pages/contacts.php';



require_once 'components/pages/bulletin.php';



require_once 'components/pages/programs.php';







// Include footer



require_once 'components/footer.php';



?>



    <head>

    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title><?= $currentPageTitle ?></title>

    <link rel="stylesheet" href="admin-btns.css">

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">

    <link rel="icon" type="image/svg+xml" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><text y='.9em' font-size='90'>📚</text></svg>">



