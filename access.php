<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// AUTO-FIX: Pokud uživatel už je přihlášený, ale ještě nemá original_role
if (isset($_SESSION['role']) && !isset($_SESSION['original_role'])) {
    $_SESSION['original_role'] = $_SESSION['role'];
}

/**
 * NOVÁ FUNKCE: Checkne, jestli je uživatel ve skutečnosti admin (v peněžence má admin občanku)
 */
function isActualAdmin()
{
    return (isset($_SESSION['original_role']) && $_SESSION['original_role'] === 'admin');
}

/**
 * UPRAVENÁ FUNKCE: Teď povolí nahrávání, pokud:
 * 1. Aktuální role je admin/manager
 * 2. NEBO pokud je to admin přepnutý na kohokoliv jiného
 */
function canUpload()
{
    $uploadRoles = ['admin', 'manager'];

    // Aktuální role v session
    $currentRole = $_SESSION['role'] ?? '';

    return in_array($currentRole, $uploadRoles) || isActualAdmin();
}

/**
 * UPRAVENÁ FUNKCE: Admin práva (pro mazání, editaci atd.)
 */
function isAdmin()
{
    // Vrátí true, pokud je role admin NEBO pokud je to náš přepnutý admin
    return (isset($_SESSION['role']) && $_SESSION['role'] === 'admin') || isActualAdmin();
}

/**
 * Check if the current user is a manager or above
 * return boolean True if user is manager or admin, false otherwise
 */

function isManager()

{


    $managerRoles = ['admin', 'manager'];

    return isset($_SESSION['role']) && in_array($_SESSION['role'], $managerRoles);


}

function getRoot($cesta)
{
    $basePath = $_SERVER['DOCUMENT_ROOT'];
    $role = $_SESSION['role'] ?? '';

    // Seznam složek, které mají i verzi "-poradce"
    // Pokud přidáš novou sekci (třeba 'navody'), stačí ji připsat sem
    $separatedModules = ['doc', 'videos', 'marketing', 'pdf', 'other', 'links', 'podcast', 'edo', 'seznam', 'test', 'agreements', 'courses'];

    $folder = $cesta;

    // Pokud je role poradce a složka je v seznamu oddělených, přidáme suffix
    if ($role === 'poradce' && in_array($cesta, $separatedModules)) {
        $folder .= '-poradce';
    }

    // Vrátíme čistou absolutní cestu
    return $basePath . '/' . $folder . '/';
}

function getDocRoot()
{
    $basePath = $_SERVER['DOCUMENT_ROOT'];
    // Vytáhneme roli (tady už session existuje, protože requireLogin ji nastartuje)
    $role = $_SESSION['role'] ?? '';

    if ($role === 'poradce') {
        return $basePath . '/doc-poradce/';
    }

    return $basePath . '/doc/';
}

/**
 * Check if the current user has access to a specific page
 * @param string $page Page identifier
 * @return boolean True if user has access, false otherwise
 */

function hasPageAccess($page)

{


    // Pages that require admin role

    $adminPages = ['admin'];

    // Pages that require manager or admin role

    $managerPages = ['bulletin-manage'];

    if (in_array($page, $adminPages)) {


        return isAdmin();


    } else if (in_array($page, $managerPages)) {


        return isManager();


    }

    // Default to true for regular pages

    return true;


}

/**
 * Show or hide elements based on upload permission
 * return string CSS display property value ('block' or 'none')
 */

function showIfCanUpload()

{


    return canUpload() ? 'block' : 'none';


}

/**
 * Show or hide elements based on admin status
 * return string CSS display property value ('block' or 'none')
 */

function showIfAdmin()

{


    return isAdmin() ? 'block' : 'none';


}

/**
 * Check if user is properly logged in with valid session
 */

function isLoggedIn()
{

    return isset($_SESSION['user_id']) &&

        isset($_SESSION['username']) &&

        isset($_SESSION['role']) &&

        !empty($_SESSION['user_id']) &&

        !empty($_SESSION['username']) &&

        !empty($_SESSION['role']);

}

/**
 * Enforce login requirement - call this in protected pages
 */

function requireLogin()
{

    // Start session if not already started

    if (session_status() === PHP_SESSION_NONE) {

        session_start();

    }

    if (!isLoggedIn()) {

        // Clear any potentially corrupted session data

        session_unset();

        session_destroy();

        // Check if this is an AJAX request

        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) &&

            strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {

            // Clean any output buffer

            if (ob_get_level()) {

                ob_clean();

            }

            header('Content-Type: application/json');

            echo json_encode(['error' => 'Not authenticated', 'redirect' => '/login.php']);

            exit;

        }

        // Regular request - redirect to login with absolute path

        header("Location: /login.php");

        exit;

    }

    // Check session timeout (2 hours)

    $session_timeout = 2 * 60 * 60;

    if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity']) > $session_timeout) {

        session_unset();

        session_destroy();

        header("Location: /login.php");

        exit;

    }

    // Update last activity

    $_SESSION['last_activity'] = time();

}

// Remove any echo, print, or output statements at the end of the file

// Make sure there are no trailing spaces or newlines after the closing PHP tag

?>