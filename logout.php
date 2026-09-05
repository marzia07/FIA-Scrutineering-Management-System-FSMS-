<?php
session_start();

// Unset all session variables
$_SESSION = array();

// Destroy session cookie if present
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// Clear Remember Me persistent cookie
if (isset($_COOKIE['f1_remember_user'])) {
    setcookie('f1_remember_user', '', time() - 3600, '/');
}

// Destroy session on server
session_destroy();

// Redirect directly to login portal
header("Location: login.php");
exit;
