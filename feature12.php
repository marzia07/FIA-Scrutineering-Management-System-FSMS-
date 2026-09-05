<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}
require_once 'db.php';

// Helper function to log audit events
function log_notification_audit($conn, $user_id, $action, $details_array = []) {
    $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
    if ($ip === '::1') $ip = '127.0.0.1';
    $details_json = !empty($details_array) ? json_encode($details_array, JSON_UNESCAPED_UNICODE) : null;
    
    $stmt = $conn->prepare("INSERT INTO AUDIT_LOGS (user_id, action, entity_type, entity_id, details, ip_address, created_at) VALUES (?, ?, 'NOTIFICATIONS', 0, ?, ?, NOW())");
    if ($stmt) {
        $stmt->bind_param("isss", $user_id, $action, $details_json, $ip);
        $stmt->execute();
    }
}

// 0. Ensure NOTIFICATIONS table exists and auto-seed if empty
$conn->query("CREATE TABLE IF NOT EXISTS NOTIFICATIONS (
    notification_id INT AUTO_INCREMENT PRIMARY KEY,
    recipient_role_id INT NULL,
    recipient_user_id INT NULL,
    title VARCHAR(255) NOT NULL,
    message TEXT NOT NULL,
    category ENUM('VIOLATION', 'PENALTY', 'REPAIR', 'APPEAL', 'INSPECTION', 'ADVISORY') DEFAULT 'ADVISORY',
    target_url VARCHAR(255) DEFAULT 'dashboard.php',
    is_read TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

$check_notif_count = $conn->query("SELECT COUNT(*) as cnt FROM NOTIFICATIONS");
if ($check_notif_count && $check_notif_count->fetch_assoc()['cnt'] == 0) {
    $u_curr = $_SESSION['user_id'] ?? 1;
    $seeds = [
        [
            'recipient_role_id' => NULL,
            'recipient_user_id' => $u_curr,
            'title' => 'PARC FERMÉ ALERT: Technical Violation Detected (Car #1)',
            'message' => 'Front Wing vertical load deflection exceeded 2.0mm statutory tolerance during post-qualifying load test. Referred to Technical Delegate.',
            'category' => 'VIOLATION',
            'target_url' => 'feature5.php',
            'is_read' => 0,
            'interval' => '8 MINUTE'
        ],
        [
            'recipient_role_id' => NULL,
            'recipient_user_id' => NULL,
            'title' => 'STEWARDS SUMMONS: Turn 4 Collision Incident Ratified',
            'message' => 'Car #16 (Charles Leclerc) and Car #55 (Carlos Sainz) summoned to Stewards Room at 18:45 regarding ISC Article 2(c) investigation.',
            'category' => 'PENALTY',
            'target_url' => 'feature6.php',
            'is_read' => 0,
            'interval' => '24 MINUTE'
        ],
        [
            'recipient_role_id' => NULL,
            'recipient_user_id' => $u_curr,
            'title' => 'RE-INSPECTION PASSED: DRS Actuator Seal Certified (Car #63)',
            'message' => 'Mercedes-AMG garage completed hydraulic actuator seal replacement. Re-inspection verified compliant under Technical Regulation 3.8.2.',
            'category' => 'REPAIR',
            'target_url' => 'feature8.php',
            'is_read' => 0,
            'interval' => '1 HOUR'
        ],
        [
            'recipient_role_id' => NULL,
            'recipient_user_id' => NULL,
            'title' => 'JUDICIAL APPEAL LODGED: McLaren Protest on Grid Penalty',
            'message' => 'McLaren Formula 1 Team lodged formal appeal with strain gauge telemetry traces contesting Stewards Decision Document #42.',
            'category' => 'APPEAL',
            'target_url' => 'feature9.php',
            'is_read' => 1,
            'interval' => '3 HOUR'
        ],
        [
            'recipient_role_id' => NULL,
            'recipient_user_id' => NULL,
            'title' => 'SCRUTINEERING CLEARANCE: Ferrari SF-24 Plank Skid Wear Passed',
            'message' => 'Post-session optical thickness check confirmed 9.15mm remaining plank depth against minimum 9.00mm legal limit.',
            'category' => 'INSPECTION',
            'target_url' => 'feature2.php',
            'is_read' => 1,
            'interval' => '5 HOUR'
        ],
        [
            'recipient_role_id' => NULL,
            'recipient_user_id' => NULL,
            'title' => 'RACE CONTROL DIRECTIVE: Track Limits Monitoring Turn 9 & 10',
            'message' => 'All competitors advised that optical kerb sensors on Turn 9 and 10 will trigger automatic steward lap-time deletion.',
            'category' => 'ADVISORY',
            'target_url' => 'feature10.php',
            'is_read' => 1,
            'interval' => '1 DAY'
        ]
    ];

    foreach ($seeds as $s) {
        $conn->query("INSERT INTO NOTIFICATIONS (recipient_role_id, recipient_user_id, title, message, category, target_url, is_read, created_at) 
                      VALUES (" . ($s['recipient_role_id'] === NULL ? "NULL" : intval($s['recipient_role_id'])) . ", 
                              " . ($s['recipient_user_id'] === NULL ? "NULL" : intval($s['recipient_user_id'])) . ", 
                              '" . addslashes($s['title']) . "', 
                              '" . addslashes($s['message']) . "', 
                              '" . $s['category'] . "', 
                              '" . $s['target_url'] . "', 
                              " . intval($s['is_read']) . ", 
                              NOW() - INTERVAL " . $s['interval'] . ")");
    }
}

$message = '';
$action_type = '';
$curr_uid = intval($_SESSION['user_id'] ?? 1);
$curr_role = intval($_SESSION['role_id'] ?? 1);

// 1. POST ACTION: MARK ALL AS READ
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'mark_all_read') {
    $stmt_m = $conn->prepare("UPDATE NOTIFICATIONS SET is_read = 1 WHERE (recipient_user_id = ? OR recipient_role_id = ? OR (recipient_user_id IS NULL AND recipient_role_id IS NULL)) AND is_read = 0");
    if ($stmt_m) {
        $stmt_m->bind_param("ii", $curr_uid, $curr_role);
        $stmt_m->execute();
        $affected = $stmt_m->affected_rows;
        
        log_notification_audit($conn, $curr_uid, "NOTIFICATIONS: Marked all active notifications as read ({$affected} items).");
        $message = "All active race control notifications marked as read ({$affected} items updated).";
        $action_type = "mark_all";
    }
}

// 2. POST ACTION: MARK SINGLE NOTIFICATION AS READ
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'mark_single_read') {
    $notif_id = intval($_POST['notification_id'] ?? 0);
    if ($notif_id > 0) {
        $stmt_s = $conn->prepare("UPDATE NOTIFICATIONS SET is_read = 1 WHERE notification_id = ?");
        if ($stmt_s) {
            $stmt_s->bind_param("i", $notif_id);
            $stmt_s->execute();
            $message = "Notification #{$notif_id} marked as acknowledged.";
            $action_type = "mark_single";
        }
    }
}

// 3. POST ACTION: BROADCAST NEW OFFICIAL ADVISORY (DEMO DISPATCH CONSOLE)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'dispatch_alert') {
    $title = trim($_POST['title'] ?? '');
    $msg_content = trim($_POST['message'] ?? '');
    $category = trim($_POST['category'] ?? 'ADVISORY');
    $target_url = trim($_POST['target_url'] ?? 'dashboard.php');
    $recipient_type = trim($_POST['recipient_type'] ?? 'broadcast');

    $target_user_id = null;
    $target_role_id = null;

    if ($recipient_type === 'me') {
        $target_user_id = $curr_uid;
    } elseif ($recipient_type === 'officials') {
        $target_role_id = 1; // Stewards/Delegates
    }

    if (!empty($title) && !empty($msg_content)) {
        $stmt_d = $conn->prepare("INSERT INTO NOTIFICATIONS (recipient_role_id, recipient_user_id, title, message, category, target_url, is_read, created_at) VALUES (?, ?, ?, ?, ?, ?, 0, NOW())");
        if ($stmt_d) {
            $stmt_d->bind_param("iissss", $target_role_id, $target_user_id, $title, $msg_content, $category, $target_url);
            $stmt_d->execute();
            $new_nid = $conn->insert_id;

            log_notification_audit($conn, $curr_uid, "BROADCAST_ALERT: Promulgated Race Control alert '{$title}' (#{$new_nid})", [
                'category' => $category,
                'target_url' => $target_url,
                'recipient_type' => $recipient_type
            ]);

            $message = "Official Race Control Alert #{$new_nid} successfully dispatched and broadcast to paddock telemetry stream.";
            $action_type = "dispatched";
        }
    }
}

// 4. POST ACTION: TRIGGER SIMULATED RED ALERT (DEMO / AUDIT SIMULATION)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'trigger_simulated_red_alert') {
    $stmt_sim = $conn->prepare("INSERT INTO NOTIFICATIONS (recipient_role_id, recipient_user_id, title, message, category, target_url, is_read, created_at) VALUES (NULL, NULL, 'PARC FERMÉ BREACH', 'Car #44 rear wing deflection exceeded 85mm tolerance during post-session scrutineering verification.', 'VIOLATION', 'feature5.php', 0, NOW())");
    if ($stmt_sim) {
        $stmt_sim->execute();
        $sim_id = $conn->insert_id;
        log_notification_audit($conn, $curr_uid, "RED_ALERT_SIMULATION: Triggered critical Parc Fermé breach (#{$sim_id}).");
        $message = "🚨 RED ALERT TAKEOVER ACTIVATED: Emergency disco strobe & klaxon siren broadcast live across telemetry network!";
        $action_type = "red_alert_simulated";
    }
}

// 4. TOP HUD METRICS CALCULATION
$stmt_unread = $conn->prepare("SELECT COUNT(*) FROM NOTIFICATIONS WHERE is_read = 0 AND (recipient_user_id = ? OR recipient_role_id = ? OR (recipient_user_id IS NULL AND recipient_role_id IS NULL))");
$stmt_unread->bind_param("ii", $curr_uid, $curr_role);
$stmt_unread->execute();
$unread_count = $stmt_unread->get_result()->fetch_row()[0] ?? 0;

$stmt_disc = $conn->prepare("SELECT COUNT(*) FROM NOTIFICATIONS WHERE category IN ('VIOLATION', 'PENALTY') AND (recipient_user_id = ? OR recipient_role_id = ? OR (recipient_user_id IS NULL AND recipient_role_id IS NULL))");
$stmt_disc->bind_param("ii", $curr_uid, $curr_role);
$stmt_disc->execute();
$disciplinary_count = $stmt_disc->get_result()->fetch_row()[0] ?? 0;

$stmt_tech = $conn->prepare("SELECT COUNT(*) FROM NOTIFICATIONS WHERE category IN ('REPAIR', 'INSPECTION') AND (recipient_user_id = ? OR recipient_role_id = ? OR (recipient_user_id IS NULL AND recipient_role_id IS NULL))");
$stmt_tech->bind_param("ii", $curr_uid, $curr_role);
$stmt_tech->execute();
$technical_count = $stmt_tech->get_result()->fetch_row()[0] ?? 0;

$stmt_total = $conn->prepare("SELECT COUNT(*) FROM NOTIFICATIONS WHERE (recipient_user_id = ? OR recipient_role_id = ? OR (recipient_user_id IS NULL AND recipient_role_id IS NULL))");
$stmt_total->bind_param("ii", $curr_uid, $curr_role);
$stmt_total->execute();
$total_history_count = $stmt_total->get_result()->fetch_row()[0] ?? 0;

// 5. FILTER TABS QUERY
$active_tab = $_GET['tab'] ?? 'all';

$query = "SELECT * FROM NOTIFICATIONS WHERE (recipient_user_id = ? OR recipient_role_id = ? OR (recipient_user_id IS NULL AND recipient_role_id IS NULL))";
$types = "ii";
$params = [$curr_uid, $curr_role];

if ($active_tab === 'disciplinary') {
    $query .= " AND category IN ('VIOLATION', 'PENALTY')";
} elseif ($active_tab === 'technical') {
    $query .= " AND category IN ('REPAIR', 'INSPECTION')";
} elseif ($active_tab === 'unread') {
    $query .= " AND is_read = 0";
}

$query .= " ORDER BY is_read ASC, created_at DESC LIMIT 50";

$stmt_list = $conn->prepare($query);
$stmt_list->bind_param($types, ...$params);
$stmt_list->execute();
$notifications_res = $stmt_list->get_result();

// Relative time helper
function format_relative_time($datetime) {
    $time = strtotime($datetime);
    $diff = time() - $time;
    if ($diff < 60) return 'Just now';
    if ($diff < 3600) return floor($diff / 60) . 'm ago';
    if ($diff < 86400) return floor($diff / 3600) . 'h ago';
    if ($diff < 604800) return floor($diff / 86400) . 'd ago';
    return date('M j, Y', $time);
}

$current_page = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>FIA FSMS - Feature 12: Race Control Real-Time Notification & Alert Center</title>
    <!-- Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@500;700;900&family=Share+Tech+Mono&family=Titillium+Web:wght@400;600;700;900&display=swap" rel="stylesheet">
    <style>
        :root {
            --f1-red: #ff1801;
            --f1-cyan: #00d2be;
            --f1-amber: #ff9f1a;
            --f1-purple: #9b59b6;
            --f1-blue: #0984e3;
            --f1-green: #2ecc71;
            --f1-dark: #07070e;
            --f1-panel: rgba(14, 14, 24, 0.96);
            --border-glow: rgba(0, 210, 190, 0.25);
            --f1-card-bg: rgba(20, 20, 35, 0.85);
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            background-color: var(--f1-dark);
            background-image: 
                radial-gradient(circle at 10% 20%, rgba(255, 24, 1, 0.05) 0%, transparent 40%),
                radial-gradient(circle at 90% 80%, rgba(0, 210, 190, 0.05) 0%, transparent 40%),
                linear-gradient(rgba(255, 255, 255, 0.015) 1px, transparent 1px),
                linear-gradient(90deg, rgba(255, 255, 255, 0.015) 1px, transparent 1px);
            background-size: 100% 100%, 100% 100%, 30px 30px, 30px 30px;
            color: #d1d8e0;
            font-family: 'Titillium Web', sans-serif;
            min-height: 100vh;
            padding-bottom: 40px;
        }

        .main-container {
            max-width: 1440px;
            margin: 0 auto;
            padding: 24px;
        }

        /* Top HUD Header */
        .top-hud-bar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: linear-gradient(90deg, rgba(20, 20, 35, 0.98) 0%, rgba(10, 10, 20, 0.98) 100%);
            border-left: 4px solid var(--f1-red);
            border-bottom: 1px solid rgba(255, 24, 1, 0.2);
            padding: 16px 24px;
            border-radius: 8px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.6);
            margin-bottom: 24px;
        }
        .hud-title-group h1 {
            font-family: 'Orbitron', sans-serif;
            font-size: 20px;
            font-weight: 900;
            color: #ffffff;
            letter-spacing: 1.5px;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .hud-title-group p {
            font-family: 'Share Tech Mono', monospace;
            font-size: 11px;
            color: var(--f1-cyan);
            margin-top: 4px;
            letter-spacing: 1px;
        }

        /* Audio HUD Action */
        .btn-telemetry-sound {
            display: flex;
            align-items: center;
            gap: 8px;
            background: rgba(255, 24, 1, 0.1);
            border: 1px solid var(--f1-red);
            color: var(--f1-red);
            font-family: 'Orbitron', sans-serif;
            font-size: 11px;
            font-weight: 700;
            padding: 9px 16px;
            border-radius: 6px;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        .btn-telemetry-sound:hover {
            background: var(--f1-red);
            color: #ffffff;
            box-shadow: 0 0 15px rgba(255, 24, 1, 0.6);
        }

        /* Metrics Deck */
        .metrics-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 16px;
            margin-bottom: 24px;
        }
        .metric-card {
            background: var(--f1-card-bg);
            border: 1px solid rgba(255, 255, 255, 0.08);
            border-radius: 8px;
            padding: 16px 20px;
            position: relative;
            overflow: hidden;
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.4);
            transition: transform 0.2s ease, border-color 0.2s ease;
        }
        .metric-card:hover {
            transform: translateY(-2px);
            border-color: rgba(255, 255, 255, 0.2);
        }
        .metric-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 4px;
            height: 100%;
        }
        .metric-card.red::before { background: var(--f1-red); box-shadow: 0 0 10px var(--f1-red); }
        .metric-card.amber::before { background: var(--f1-amber); box-shadow: 0 0 10px var(--f1-amber); }
        .metric-card.cyan::before { background: var(--f1-cyan); box-shadow: 0 0 10px var(--f1-cyan); }
        .metric-card.purple::before { background: var(--f1-purple); box-shadow: 0 0 10px var(--f1-purple); }

        .metric-label {
            font-family: 'Share Tech Mono', monospace;
            font-size: 11px;
            color: #8c8c9e;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 6px;
        }
        .metric-value {
            font-family: 'Orbitron', sans-serif;
            font-size: 24px;
            font-weight: 900;
            color: #ffffff;
            display: flex;
            align-items: baseline;
            gap: 6px;
        }
        .metric-sub {
            font-family: 'Share Tech Mono', monospace;
            font-size: 10px;
            color: var(--f1-cyan);
            margin-top: 4px;
        }

        /* Beacon Pulse Animation */
        .beacon-pulse {
            display: inline-block;
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: #ff1801;
            box-shadow: 0 0 10px #ff1801;
            animation: beaconBlink 1s infinite alternate;
        }
        @keyframes beaconBlink {
            from { transform: scale(0.8); opacity: 0.5; }
            to { transform: scale(1.4); opacity: 1; filter: drop-shadow(0 0 6px #ff1801); }
        }

        /* Banner Alerts */
        .f1-alert {
            background: rgba(0, 210, 190, 0.12);
            border: 1px solid var(--f1-cyan);
            border-radius: 6px;
            padding: 12px 18px;
            font-family: 'Share Tech Mono', monospace;
            font-size: 12px;
            color: #ffffff;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
            box-shadow: 0 0 15px rgba(0, 210, 190, 0.25);
            animation: slideDown 0.3s ease;
        }
        @keyframes slideDown {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        /* Quick Action Bar & Tabs */
        .action-bar-container {
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: var(--f1-panel);
            border: 1px solid rgba(255, 255, 255, 0.08);
            border-radius: 8px;
            padding: 14px 20px;
            margin-bottom: 24px;
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.4);
        }
        .filter-tabs {
            display: flex;
            gap: 8px;
        }
        .tab-btn {
            font-family: 'Orbitron', sans-serif;
            font-size: 11px;
            font-weight: 700;
            padding: 8px 16px;
            border-radius: 6px;
            text-decoration: none;
            color: #8c8c9e;
            background: rgba(255, 255, 255, 0.03);
            border: 1px solid rgba(255, 255, 255, 0.08);
            transition: all 0.2s ease;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
        .tab-btn:hover {
            color: #ffffff;
            background: rgba(255, 255, 255, 0.08);
            border-color: rgba(255, 255, 255, 0.2);
        }
        .tab-btn.active {
            color: #ffffff;
            background: rgba(255, 24, 1, 0.15);
            border-color: #ff1801;
            box-shadow: 0 0 12px rgba(255, 24, 1, 0.4);
        }

        .btn-mark-all {
            background: rgba(46, 204, 113, 0.12);
            border: 1px solid #2ecc71;
            color: #2ecc71;
            font-family: 'Orbitron', sans-serif;
            font-size: 11px;
            font-weight: 700;
            padding: 8px 16px;
            border-radius: 6px;
            cursor: pointer;
            transition: all 0.2s ease;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
        .btn-mark-all:hover {
            background: #2ecc71;
            color: #07070e;
            box-shadow: 0 0 15px rgba(46, 204, 113, 0.6);
        }

        /* Main Feed & Dispatch Deck Grid */
        .content-deck-grid {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 24px;
        }

        /* Notification Stream Card */
        .feed-container {
            display: flex;
            flex-direction: column;
            gap: 14px;
        }
        .notif-card {
            background: var(--f1-panel);
            border: 1px solid rgba(255, 255, 255, 0.08);
            border-radius: 8px;
            padding: 18px 22px;
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.4);
            position: relative;
            transition: all 0.2s ease;
            display: flex;
            gap: 18px;
            align-items: flex-start;
        }
        .notif-card:hover {
            border-color: rgba(255, 255, 255, 0.2);
            transform: translateX(4px);
        }
        .notif-card.unread {
            background: linear-gradient(90deg, rgba(25, 25, 45, 0.98) 0%, rgba(14, 14, 24, 0.98) 100%);
            border-left: 4px solid var(--f1-red);
            box-shadow: 0 8px 25px rgba(255, 24, 1, 0.15);
        }
        .notif-card.read {
            opacity: 0.85;
            border-left: 4px solid rgba(255, 255, 255, 0.15);
        }

        /* Category Icons */
        .notif-icon-box {
            width: 42px;
            height: 42px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
            flex-shrink: 0;
        }
        .icon-violation {
            background: rgba(255, 24, 1, 0.15);
            border: 1px solid #ff1801;
            color: #ff1801;
            box-shadow: 0 0 10px rgba(255, 24, 1, 0.3);
        }
        .icon-penalty {
            background: rgba(255, 159, 26, 0.15);
            border: 1px solid #ff9f1a;
            color: #ff9f1a;
            box-shadow: 0 0 10px rgba(255, 159, 26, 0.3);
        }
        .icon-repair {
            background: rgba(0, 210, 190, 0.15);
            border: 1px solid #00d2be;
            color: #00d2be;
            box-shadow: 0 0 10px rgba(0, 210, 190, 0.3);
        }
        .icon-appeal {
            background: rgba(155, 89, 182, 0.15);
            border: 1px solid #9b59b6;
            color: #9b59b6;
            box-shadow: 0 0 10px rgba(155, 89, 182, 0.3);
        }
        .icon-inspection {
            background: rgba(46, 204, 113, 0.15);
            border: 1px solid #2ecc71;
            color: #2ecc71;
            box-shadow: 0 0 10px rgba(46, 204, 113, 0.3);
        }
        .icon-advisory {
            background: rgba(9, 132, 227, 0.15);
            border: 1px solid #0984e3;
            color: #0984e3;
            box-shadow: 0 0 10px rgba(9, 132, 227, 0.3);
        }

        .notif-body {
            flex: 1;
        }
        .notif-top-meta {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 6px;
        }
        .notif-category-pill {
            font-family: 'Share Tech Mono', monospace;
            font-size: 9.5px;
            font-weight: 700;
            padding: 2px 8px;
            border-radius: 4px;
            letter-spacing: 0.8px;
            text-transform: uppercase;
        }
        .notif-time {
            font-family: 'Share Tech Mono', monospace;
            font-size: 10.5px;
            color: #8c8c9e;
        }
        .notif-title {
            font-family: 'Orbitron', sans-serif;
            font-size: 13.5px;
            font-weight: 700;
            color: #ffffff;
            margin-bottom: 6px;
            letter-spacing: 0.5px;
        }
        .notif-message {
            font-size: 12.5px;
            line-height: 1.5;
            color: #cbd5e1;
            margin-bottom: 12px;
        }
        .notif-actions {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .btn-action-jump {
            background: rgba(0, 210, 190, 0.1);
            border: 1px solid var(--f1-cyan);
            color: var(--f1-cyan);
            font-family: 'Orbitron', sans-serif;
            font-size: 10px;
            font-weight: 700;
            padding: 6px 12px;
            border-radius: 4px;
            text-decoration: none;
            transition: all 0.2s ease;
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }
        .btn-action-jump:hover {
            background: var(--f1-cyan);
            color: #07070e;
            box-shadow: 0 0 10px rgba(0, 210, 190, 0.5);
        }
        .btn-ack-single {
            background: none;
            border: 1px solid rgba(255, 255, 255, 0.12);
            color: #8c8c9e;
            font-family: 'Share Tech Mono', monospace;
            font-size: 10px;
            padding: 5px 10px;
            border-radius: 4px;
            cursor: pointer;
            transition: all 0.2s ease;
        }
        .btn-ack-single:hover {
            color: #2ecc71;
            border-color: #2ecc71;
            background: rgba(46, 204, 113, 0.1);
        }

        /* Dispatch Form Card */
        .dispatch-card {
            background: var(--f1-panel);
            border: 1px solid rgba(255, 255, 255, 0.08);
            border-radius: 8px;
            padding: 22px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.5);
            position: sticky;
            top: 20px;
        }
        .panel-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 1px solid rgba(255, 255, 255, 0.08);
            padding-bottom: 12px;
            margin-bottom: 16px;
        }
        .panel-title {
            font-family: 'Orbitron', sans-serif;
            font-size: 13.5px;
            font-weight: 700;
            color: #ffffff;
            letter-spacing: 1px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .f1-form-group {
            margin-bottom: 14px;
        }
        .f1-form-group label {
            display: block;
            font-family: 'Share Tech Mono', monospace;
            font-size: 10.5px;
            color: #8c8c9e;
            margin-bottom: 6px;
            letter-spacing: 0.5px;
            text-transform: uppercase;
        }
        .f1-input, .f1-select, .f1-textarea {
            width: 100%;
            background: rgba(8, 8, 14, 0.85);
            border: 1px solid rgba(255, 255, 255, 0.12);
            border-radius: 6px;
            padding: 9px 12px;
            font-family: 'Titillium Web', sans-serif;
            font-size: 12.5px;
            color: #ffffff;
            outline: none;
            transition: all 0.2s ease;
        }
        .f1-input:focus, .f1-select:focus, .f1-textarea:focus {
            border-color: var(--f1-red);
            box-shadow: 0 0 10px rgba(255, 24, 1, 0.3);
            background: rgba(12, 12, 22, 0.95);
        }
        .btn-f1-submit {
            width: 100%;
            background: linear-gradient(90deg, #ff1801 0%, #d63031 100%);
            border: none;
            color: #ffffff;
            font-family: 'Orbitron', sans-serif;
            font-size: 11.5px;
            font-weight: 700;
            letter-spacing: 1.2px;
            padding: 11px;
            border-radius: 6px;
            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(255, 24, 1, 0.4);
            margin-top: 6px;
        }
        .btn-f1-submit:hover {
            box-shadow: 0 0 20px rgba(255, 24, 1, 0.7);
            transform: translateY(-1px);
        }
    </style>
</head>
<body>

<!-- Global Navigation Sidebar -->
<?php include 'navbar.php'; ?>

<div class="main-container">

    <!-- Top Telemetry Header -->
    <div class="top-hud-bar">
        <div class="hud-title-group">
            <h1><span style="color: var(--f1-red);">🚨</span> FEATURE 12: RACE CONTROL NOTIFICATION CENTER</h1>
            <p>REAL-TIME DIRECTIVES // TELEMETRY BROADCAST // PARC FERMÉ & DISCIPLINARY INCIDENTS</p>
        </div>
        <div class="hud-actions">
            <button class="btn-telemetry-sound" onclick="speakAlertComms()">
                <span>🎙️</span> SOUND ALERT COMMS
            </button>
        </div>
    </div>

    <?php if (!empty($message)): ?>
        <div class="f1-alert">
            <span>⚡ [RACE CONTROL]</span> <?php echo htmlspecialchars($message); ?>
        </div>
    <?php endif; ?>

    <!-- Top HUD Metric Cards -->
    <div class="metrics-grid">
        <div class="metric-card red">
            <div class="metric-label">
                Unread Active Alerts <?php if ($unread_count > 0): ?><span class="beacon-pulse" style="margin-left: 6px;"></span><?php endif; ?>
            </div>
            <div class="metric-value" style="color: #ff4757;"><?php echo number_format($unread_count); ?></div>
            <div class="metric-sub">Action Required by Officials</div>
        </div>

        <div class="metric-card amber">
            <div class="metric-label">Disciplinary Notices</div>
            <div class="metric-value"><?php echo number_format($disciplinary_count); ?></div>
            <div class="metric-sub">Violations & Steward Summons</div>
        </div>

        <div class="metric-card cyan">
            <div class="metric-label">Technical Clearances</div>
            <div class="metric-value"><?php echo number_format($technical_count); ?></div>
            <div class="metric-sub">Repairs & Re-Inspections</div>
        </div>

        <div class="metric-card purple">
            <div class="metric-label">Total Dispatch Logs</div>
            <div class="metric-value"><?php echo number_format($total_history_count); ?></div>
            <div class="metric-sub">Official Event Broadcasts</div>
        </div>
    </div>

    <!-- Quick Action Bar & Filter Tabs -->
    <div class="action-bar-container">
        <div class="filter-tabs">
            <a href="feature12.php?tab=all" class="tab-btn <?php echo ($active_tab === 'all') ? 'active' : ''; ?>">
                <span>🌐</span> ALL ALERTS (<?php echo $total_history_count; ?>)
            </a>
            <a href="feature12.php?tab=disciplinary" class="tab-btn <?php echo ($active_tab === 'disciplinary') ? 'active' : ''; ?>">
                <span>🚨</span> VIOLATIONS & PENALTIES (<?php echo $disciplinary_count; ?>)
            </a>
            <a href="feature12.php?tab=technical" class="tab-btn <?php echo ($active_tab === 'technical') ? 'active' : ''; ?>">
                <span>🛠️</span> TECHNICAL & REPAIRS (<?php echo $technical_count; ?>)
            </a>
            <a href="feature12.php?tab=unread" class="tab-btn <?php echo ($active_tab === 'unread') ? 'active' : ''; ?>">
                <span>⚡</span> UNREAD ONLY (<?php echo $unread_count; ?>)
            </a>
        </div>

        <?php if ($unread_count > 0): ?>
            <form method="POST" action="feature12.php<?php echo !empty($_GET['tab']) ? '?tab='.htmlspecialchars($_GET['tab']) : ''; ?>" style="margin: 0;">
                <input type="hidden" name="action" value="mark_all_read">
                <button type="submit" class="btn-mark-all">
                    <span>✓</span> MARK ALL AS READ
                </button>
            </form>
        <?php endif; ?>
    </div>

    <!-- Content Deck: Feed & Quick Dispatch Form -->
    <div class="content-deck-grid">
        
        <!-- Left Column: Interactive Notification Feed -->
        <div class="feed-container">
            <?php if ($notifications_res && $notifications_res->num_rows > 0): ?>
                <?php while ($notif = $notifications_res->fetch_assoc()): ?>
                    <?php
                        $cat = strtoupper($notif['category']);
                        $icon_class = 'icon-advisory';
                        $icon_char = '📡';
                        $pill_class = 'pill-info';

                        if ($cat === 'VIOLATION') {
                            $icon_class = 'icon-violation';
                            $icon_char = '🚨';
                        } elseif ($cat === 'PENALTY') {
                            $icon_class = 'icon-penalty';
                            $icon_char = '⚖️';
                        } elseif ($cat === 'REPAIR') {
                            $icon_class = 'icon-repair';
                            $icon_char = '🔧';
                        } elseif ($cat === 'APPEAL') {
                            $icon_class = 'icon-appeal';
                            $icon_char = '📜';
                        } elseif ($cat === 'INSPECTION') {
                            $icon_class = 'icon-inspection';
                            $icon_char = '📐';
                        }
                    ?>
                    <div class="notif-card <?php echo ($notif['is_read'] == 0) ? 'unread' : 'read'; ?>">
                        <div class="notif-icon-box <?php echo $icon_class; ?>">
                            <?php echo $icon_char; ?>
                        </div>

                        <div class="notif-body">
                            <div class="notif-top-meta">
                                <span class="notif-category-pill <?php echo $icon_class; ?>">
                                    <?php echo htmlspecialchars($notif['category']); ?>
                                </span>
                                <span class="notif-time">
                                    <?php echo format_relative_time($notif['created_at']); ?>
                                </span>
                            </div>

                            <div class="notif-title">
                                <?php echo htmlspecialchars($notif['title']); ?>
                            </div>

                            <div class="notif-message">
                                <?php echo htmlspecialchars($notif['message']); ?>
                            </div>

                            <div class="notif-actions">
                                <?php if (!empty($notif['target_url'])): ?>
                                    <a href="<?php echo htmlspecialchars($notif['target_url']); ?>" class="btn-action-jump">
                                        Review Incident <span>→</span>
                                    </a>
                                <?php endif; ?>

                                <?php if ($notif['is_read'] == 0): ?>
                                    <form method="POST" action="feature12.php<?php echo !empty($_GET['tab']) ? '?tab='.htmlspecialchars($_GET['tab']) : ''; ?>" style="margin: 0;">
                                        <input type="hidden" name="action" value="mark_single_read">
                                        <input type="hidden" name="notification_id" value="<?php echo $notif['notification_id']; ?>">
                                        <button type="submit" class="btn-ack-single">
                                            ✓ Mark Read
                                        </button>
                                    </form>
                                <?php else: ?>
                                    <span style="font-family: 'Share Tech Mono', monospace; font-size: 10px; color: #64748b;">
                                        ✓ Acknowledged
                                    </span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endwhile; ?>
            <?php else: ?>
                <div style="text-align: center; padding: 48px 20px; background: var(--f1-panel); border-radius: 8px; border: 1px dashed rgba(255, 255, 255, 0.1);">
                    <div style="font-size: 32px; margin-bottom: 12px;">📡</div>
                    <h3 style="font-family: 'Orbitron', sans-serif; font-size: 14px; color: #ffffff; margin-bottom: 6px;">
                        NO ACTIVE ALERTS IN FEED
                    </h3>
                    <p style="font-family: 'Share Tech Mono', monospace; font-size: 11px; color: #8c8c9e;">
                        All notifications under this category have been acknowledged or no events have been dispatched.
                    </p>
                </div>
            <?php endif; ?>
        </div>

        <!-- Right Column: Quick Dispatch Console (Admin/Race Control Demo Mode) -->
        <div>
            <div class="dispatch-card">
                <div class="panel-header">
                    <div class="panel-title">
                        <span>📢</span> RACE CONTROL DISPATCH CONSOLE
                    </div>
                    <span style="font-family: 'Share Tech Mono', monospace; font-size: 10px; color: var(--f1-cyan);">
                        LIVE BROADCAST
                    </span>
                </div>

                <form method="POST" action="feature12.php<?php echo !empty($_GET['tab']) ? '?tab='.htmlspecialchars($_GET['tab']) : ''; ?>">
                    <input type="hidden" name="action" value="dispatch_alert">

                    <div class="f1-form-group">
                        <label>INCIDENT CATEGORY</label>
                        <select name="category" class="f1-select">
                            <option value="VIOLATION">VIOLATION (Technical Infringement)</option>
                            <option value="PENALTY">PENALTY (Steward Sanction)</option>
                            <option value="REPAIR">REPAIR (Parc Fermé Rectification)</option>
                            <option value="APPEAL">APPEAL (Judicial Arbitration)</option>
                            <option value="INSPECTION">INSPECTION (Physical Measurement)</option>
                            <option value="ADVISORY" selected>ADVISORY (Race Director Directive)</option>
                        </select>
                    </div>

                    <div class="f1-form-group">
                        <label>ALERT HEADLINE</label>
                        <input type="text" name="title" class="f1-input" placeholder="e.g. Red Flag: Session Suspended Turn 7" required>
                    </div>

                    <div class="f1-form-group">
                        <label>TARGET MODULE LINK</label>
                        <select name="target_url" class="f1-select">
                            <option value="feature5.php">Feature 5: Technical Violations</option>
                            <option value="feature6.php">Feature 6: Steward Decisions</option>
                            <option value="feature8.php">Feature 8: Repairs & Re-Inspection</option>
                            <option value="feature9.php">Feature 9: Appeals & Evidence</option>
                            <option value="feature10.php">Feature 10: Technical Master Dossier</option>
                            <option value="feature11.php">Feature 11: Security Logs</option>
                            <option value="dashboard.php" selected>Dashboard Mission Control</option>
                        </select>
                    </div>

                    <div class="f1-form-group">
                        <label>TARGET AUDIENCE</label>
                        <select name="recipient_type" class="f1-select">
                            <option value="broadcast" selected>Paddock Wide (All Officials & Teams)</option>
                            <option value="officials">Technical Stewards & Scrutineers</option>
                            <option value="me">Private Alert (Current User Only)</option>
                        </select>
                    </div>

                    <div class="f1-form-group">
                        <label>ALERT BODY & TELEMETRY NOTES</label>
                        <textarea name="message" class="f1-textarea" rows="4" placeholder="Detailed race control instruction or technical summons description..." required></textarea>
                    </div>

                    <button type="submit" class="btn-f1-submit">
                        ⚡ TRANSMIT OFFICIAL ALERT
                    </button>
                </form>

                <!-- Quick Red Alert Disco Strobe Simulation Card -->
                <div style="margin-top: 20px; padding-top: 16px; border-top: 1px dashed rgba(255, 24, 1, 0.3);">
                    <div style="font-family: 'Orbitron', sans-serif; font-size: 11px; font-weight: 700; color: #ff4757; margin-bottom: 8px; display: flex; align-items: center; gap: 6px;">
                        <span>🚨</span> EMERGENCY SIMULATION CONTROL
                    </div>
                    <p style="font-family: 'Share Tech Mono', monospace; font-size: 10px; color: #8c8c9e; margin-bottom: 10px; line-height: 1.4;">
                        Instantly trigger a live critical Parc Fermé infraction to demonstrate the global full-screen red alert disco strobe, horizontal laser scanner, and klaxon siren.
                    </p>
                    <form method="POST" action="feature12.php" style="margin: 0;">
                        <input type="hidden" name="action" value="trigger_simulated_red_alert">
                        <button type="submit" class="btn-f1-submit" style="background: linear-gradient(90deg, #ff1801 0%, #ff4757 50%, #d63031 100%); box-shadow: 0 0 15px rgba(255, 24, 1, 0.6);">
                            🚨 SOUND RED ALERT (SIMULATION)
                        </button>
                    </form>
                </div>
            </div>
        </div>

    </div>

</div>

<!-- Audio Synthesizer Script -->
<script>
function speakAlertComms() {
    if ('speechSynthesis' in window) {
        window.speechSynthesis.cancel();
        const unread = "<?php echo $unread_count; ?>";
        const disc = "<?php echo $disciplinary_count; ?>";
        const tech = "<?php echo $technical_count; ?>";

        const message = `Race Control Notification Center updated. You have ${unread} active unread alerts. ${disc} disciplinary notices logged, and ${tech} technical clearances certified.`;

        const utterance = new SpeechSynthesisUtterance(message);
        utterance.rate = 0.95;
        utterance.pitch = 0.78; // Deep masculine baritone tone
        utterance.volume = 1.0;

        const voices = window.speechSynthesis.getVoices();
        let selectedVoice = voices.find(v => (v.name.includes('David') || v.name.includes('George') || v.name.includes('Male') || v.name.includes('UK English Male')) && !v.name.includes('Female'));
        if (!selectedVoice) {
            selectedVoice = voices.find(v => v.lang.startsWith('en') && !v.name.toLowerCase().includes('female') && !v.name.toLowerCase().includes('samantha') && !v.name.toLowerCase().includes('zira'));
        }
        if (selectedVoice) utterance.voice = selectedVoice;

        if (typeof playGlobalF1Radio === 'function') {
            playGlobalF1Radio();
            setTimeout(() => { window.speechSynthesis.speak(utterance); }, 150);
        } else {
            window.speechSynthesis.speak(utterance);
        }
    }
}
</script>

</body>
</html>
