<?php
// FIA FSMS Root Gateway Controller
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

require_once 'db.php';
$dest = get_role_home_page();
header("Location: " . $dest);
exit;
