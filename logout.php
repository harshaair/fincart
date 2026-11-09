<?php
require_once "includes/security.php";
init_secure_session();

// Clear all session variables
$_SESSION = array();

// Expire the session cookie in browser
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// Destroy session on server
session_destroy();

// Redirect to login
header('Location: login.php');
exit();
