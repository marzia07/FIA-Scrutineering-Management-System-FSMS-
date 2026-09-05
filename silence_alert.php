<?php
error_reporting(0);
ini_set('display_errors', 0);
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

require_once 'db.php';

$notification_id = intval($_POST['notification_id'] ?? 0);

if ($notification_id > 0) {
    $stmt = $conn->prepare("UPDATE NOTIFICATIONS SET is_read = 1 WHERE notification_id = ?");
    if ($stmt) {
        $stmt->bind_param("i", $notification_id);
        $stmt->execute();
        echo json_encode(['success' => true, 'notification_id' => $notification_id]);
        exit;
    }
}

// Fallback if no specific ID, silence all critical active alerts
$stmt_all = $conn->prepare("UPDATE NOTIFICATIONS SET is_read = 1 WHERE category IN ('VIOLATION', 'PENALTY') AND is_read = 0");
if ($stmt_all) {
    $stmt_all->execute();
}

echo json_encode(['success' => true, 'silenced_all' => true]);
