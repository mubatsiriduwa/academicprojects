<?php
// pages/logout.php
session_start();

if (isset($_SESSION['user_id'])) {
    require_once '../config/database.php';
    require_once '../includes/functions.php';

    $database = new Database();
    $db = $database->getConnection();

    logActivity($_SESSION['user_id'], 'LOGOUT', 'User logged out');
}

$_SESSION = array();

if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

session_destroy();
header("Location: ../index.php");
exit();
?>
