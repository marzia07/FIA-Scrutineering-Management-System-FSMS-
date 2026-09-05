<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

$host = "127.0.0.1";
$user = "root";
$pass = "";
$dbname = "F1_Inspection";

$conn = @new mysqli($host, $user, $pass, $dbname);

if ($conn->connect_error) {
    die("<div style='background:#1e1e27;color:#ff4757;padding:20px;font-family:sans-serif;border-radius:8px;'>
        <h3>Database Connection Error</h3>
        <p>" . $conn->connect_error . "</p>
        <p>Make sure <strong>MySQL Database</strong> is started in your XAMPP Control Panel.</p>
    </div>");
}

require_once __DIR__ . '/auth_guard.php';
