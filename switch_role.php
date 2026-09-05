<?php
// FIA FSMS Demo Role Switcher Endpoint
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once 'db.php';

// Only Admin / Master Session can switch roles
$current_role = get_current_user_role();
$can_switch = ($current_role === 'admin') || (!empty($_SESSION['is_admin_root']));

if (!$can_switch) {
    check_role_access(['admin']);
    exit;
}

$role_id = intval($_GET['role_id'] ?? ($_POST['role_id'] ?? 1));
$return_url = $_GET['return_url'] ?? ($_POST['return_url'] ?? '');

$dest = switch_active_role($role_id, $conn);

// If request is JSON / AJAX
if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
    header('Content-Type: application/json');
    echo json_encode([
        'status' => 'success',
        'role_id' => $_SESSION['role_id'],
        'role_name' => $_SESSION['role_name'],
        'username' => $_SESSION['username'],
        'redirect' => !empty($return_url) ? $return_url : $dest
    ]);
    exit;
}

// Otherwise standard redirect
if (!empty($return_url) && strpos($return_url, 'http') === false) {
    header("Location: " . $return_url);
} else {
    header("Location: " . $dest);
}
exit;
