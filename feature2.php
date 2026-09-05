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
check_role_access(['inspector']);

$msg = "";
$msg_type = "success";

// 1. DELETE LOG HANDLER (Inspector & Admin only)
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action']) && $_POST['action'] === 'delete_measurement') {
    check_role_access(['inspector']);
    $measurement_id = intval($_POST['measurement_id']);
    $del_stmt = $conn->prepare("DELETE FROM INSPECTION_MEASUREMENTS WHERE measurement_id = ?");
    $del_stmt->bind_param("i", $measurement_id);
    if ($del_stmt->execute()) {
        $msg = "TELEMETRY PURGED: Measurement Record #{$measurement_id} removed from Scrutineering logs.";
        $msg_type = "success";
    } else {
        $msg = "PURGE ERROR: " . $conn->error;
        $msg_type = "error";
    }
}

// 2. LOG NEW SCRUTINEERING MEASUREMENT (Inspector & Admin only)
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['log_inspection'])) {
    check_role_access(['inspector']);
    $car_id = intval($_POST['car_id']);
    $regulation_id = intval($_POST['regulation_id']);
    $session_type = trim($_POST['session_type']);
    $location = trim($_POST['location']);
    $measurement_name = trim($_POST['measurement_name']);
    $expected_value = trim($_POST['expected_value']);
    $actual_value = trim($_POST['actual_value']);
    $unit = trim($_POST['unit']);
    $result = trim($_POST['result']);
    $notes = trim($_POST['notes']);
    $inspector_id = $_SESSION['user_id'];

    $session_stmt = $conn->prepare("INSERT INTO INSPECTION_SESSIONS (car_id, session_type, scheduled_at, started_at, status, location, inspector_id) VALUES (?, ?, NOW(), NOW(), 'completed', ?, ?)");
    $session_stmt->bind_param("issi", $car_id, $session_type, $location, $inspector_id);
    
    if ($session_stmt->execute()) {
        $session_id = $session_stmt->insert_id;
        
        $meas_stmt = $conn->prepare("INSERT INTO INSPECTION_MEASUREMENTS (session_id, regulation_id, measurement_name, expected_value, actual_value, unit, result, notes) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $meas_stmt->bind_param("iissssss", $session_id, $regulation_id, $measurement_name, $expected_value, $actual_value, $unit, $result, $notes);
        
        if ($meas_stmt->execute()) {
            $meas_id = $meas_stmt->insert_id;
            $msg = "SCRUTINEERING TELEMETRY LOGGED: Test #{$meas_id} recorded for " . htmlspecialchars($measurement_name) . " [Verdict: {$result}].";
            $msg_type = ($result === 'Passed' || $result === 'PASSED') ? 'success' : 'error';
        } else {
            $msg = "MEASUREMENT ERROR: " . $conn->error;
            $msg_type = "error";
        }
    } else {
        $msg = "INSPECTION SESSION ERROR: " . $conn->error;
        $msg_type = "error";
    }
}

// Fetch Dropdown Choices
$cars_dropdown = $conn->query("SELECT c.car_id, c.car_name, c.chassis_number, t.team_name, d.full_name as driver_name 
                               FROM CARS c 
                               JOIN TEAMS t ON c.team_id = t.team_id 
                               LEFT JOIN DRIVERS d ON c.driver_id = d.driver_id 
                               ORDER BY t.team_name ASC, c.car_name ASC");

$regulations_dropdown = $conn->query("SELECT regulation_id, title, description, content FROM REGULATIONS ORDER BY regulation_id ASC");

// Query Inspection Measurements with complete joins
$inspections_query = "SELECT m.measurement_id, m.measurement_name, m.expected_value, m.actual_value, m.unit, m.result, m.notes, m.created_at,
                             s.session_type, s.location, s.session_id,
                             c.car_name, c.chassis_number,
                             t.team_name,
                             d.full_name as driver_name,
                             r.title as regulation_title, r.description as regulation_desc,
                             u.full_name as inspector_name
                      FROM INSPECTION_MEASUREMENTS m
                      JOIN INSPECTION_SESSIONS s ON m.session_id = s.session_id
                      JOIN CARS c ON s.car_id = c.car_id
                      LEFT JOIN TEAMS t ON c.team_id = t.team_id
                      LEFT JOIN DRIVERS d ON c.driver_id = d.driver_id
                      JOIN REGULATIONS r ON m.regulation_id = r.regulation_id
                      LEFT JOIN USERS u ON s.inspector_id = u.user_id
                      ORDER BY m.measurement_id DESC";
$inspections_res = $conn->query($inspections_query);

// Summary Metrics
$total_tests = 0;
$passed_tests = 0;
$failed_tests = 0;
$all_measurements = [];

if ($inspections_res) {
    while ($row = $inspections_res->fetch_assoc()) {
        $all_measurements[] = $row;
        $total_tests++;
        if (strcasecmp($row['result'], 'Passed') === 0) {
            $passed_tests++;
        } else {
            $failed_tests++;
        }
    }
}
$compliance_rate = ($total_tests > 0) ? round(($passed_tests / $total_tests) * 100) : 100;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>FSMS | FIA Technical Scrutineering Rig</title>
    <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@500;700;800;900&family=Share+Tech+Mono&family=Titillium+Web:wght@300;400;600;700;900&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        :root {
            --f1-red: #ff1801;
            --f1-red-glow: rgba(255, 24, 1, 0.4);
            --f1-cyan: #00d2be;
            --f1-cyan-glow: rgba(0, 210, 190, 0.4);
            --f1-amber: #ffb703;
            --f1-green: #2ecc71;
            --f1-dark: #06060c;
            --f1-panel: rgba(18, 18, 28, 0.95);
            --f1-border: rgba(255, 255, 255, 0.08);
        }

        * { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            background-color: var(--f1-dark);
            color: #ffffff;
            min-height: 100vh;
            padding: 24px;
            position: relative;
            font-family: 'Titillium Web', sans-serif;
            overflow-x: hidden;
        }

        /* CRT Scanlines Overlay */
        body::before {
            content: " ";
            display: block;
            position: fixed;
            top: 0; left: 0; bottom: 0; right: 0;
            background: linear-gradient(rgba(18, 16, 16, 0) 50%, rgba(0, 0, 0, 0.25) 50%), linear-gradient(90deg, rgba(255, 0, 0, 0.03), rgba(0, 255, 0, 0.01), rgba(0, 255, 0, 0.03));
            z-index: 999;
            background-size: 100% 3px, 6px 100%;
            pointer-events: none;
            opacity: 0.6;
        }

        /* Ambient Glow Orbs */
        .ambient-glow-cyan {
            position: absolute;
            width: 700px;
            height: 700px;
            background: radial-gradient(circle, rgba(0, 210, 190, 0.16) 0%, transparent 65%);
            top: -150px;
            right: -100px;
            pointer-events: none;
            z-index: 0;
            filter: blur(50px);
        }
        .ambient-glow-red {
            position: absolute;
            width: 600px;
            height: 600px;
            background: radial-gradient(circle, rgba(255, 24, 1, 0.12) 0%, transparent 65%);
            bottom: -100px;
            left: -100px;
            pointer-events: none;
            z-index: 0;
            filter: blur(50px);
        }

        /* Telemetry Status Ribbon */
        .telemetry-ribbon {
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: rgba(14, 14, 24, 0.95);
            border: 1px solid rgba(255, 255, 255, 0.08);
            border-left: 4px solid var(--f1-cyan);
            border-radius: 8px;
            padding: 12px 20px;
            margin-bottom: 24px;
            font-family: 'Share Tech Mono', monospace;
            font-size: 12px;
            box-shadow: 0 0 25px rgba(0, 210, 190, 0.12);
            position: relative;
            z-index: 10;
        }
        .telemetry-tag {
            display: flex;
            align-items: center;
            gap: 10px;
            color: var(--f1-cyan);
            font-weight: 700;
            letter-spacing: 1.5px;
        }
        .pulse-core-cyan {
            width: 8px;
            height: 8px;
            background: var(--f1-cyan);
            border-radius: 50%;
            box-shadow: 0 0 10px var(--f1-cyan);
            animation: pulseDotCyan 1s infinite alternate;
        }
        @keyframes pulseDotCyan { 0% { opacity: 0.4; } 100% { opacity: 1; transform: scale(1.2); } }

        /* Metric HUD Matrix */
        .hud-matrix {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 18px;
            margin-bottom: 28px;
            position: relative;
            z-index: 10;
        }
        .hud-cell {
            background: linear-gradient(135deg, rgba(20, 20, 32, 0.9) 0%, rgba(12, 12, 20, 0.95) 100%);
            border: 1px solid rgba(255, 255, 255, 0.08);
            border-radius: 10px;
            padding: 20px;
            position: relative;
            overflow: hidden;
            box-shadow: 0 10px 30px rgba(0,0,0,0.5);
            transition: all 0.3s ease;
        }
        .hud-cell::after {
            content: '';
            position: absolute;
            top: 0; left: 0; width: 100%; height: 3px;
            background: linear-gradient(90deg, var(--f1-cyan), transparent);
        }
        .hud-cell.green::after { background: linear-gradient(90deg, #2ecc71, transparent); }
        .hud-cell.red::after { background: linear-gradient(90deg, #ff1801, transparent); }
        .hud-cell:hover {
            transform: translateY(-3px);
            border-color: rgba(0, 210, 190, 0.4);
            box-shadow: 0 12px 35px rgba(0, 210, 190, 0.2);
        }
        .hud-label {
            font-family: 'Share Tech Mono', monospace;
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            color: #8c8c9e;
            letter-spacing: 1.2px;
            margin-bottom: 8px;
            display: flex;
            justify-content: space-between;
        }
        .hud-value {
            font-family: 'Orbitron', sans-serif;
            font-size: 34px;
            font-weight: 900;
            color: #ffffff;
            line-height: 1;
        }

        /* Alert Banners */
        .alert-banner {
            padding: 14px 20px;
            border-radius: 8px;
            margin-bottom: 24px;
            font-family: 'Share Tech Mono', monospace;
            font-size: 13px;
            position: relative;
            z-index: 10;
        }
        .alert-banner.success {
            background: rgba(0, 210, 190, 0.15);
            border-left: 4px solid #00d2be;
            color: #00d2be;
            box-shadow: 0 0 20px rgba(0, 210, 190, 0.2);
        }
        .alert-banner.error {
            background: rgba(255, 24, 1, 0.15);
            border-left: 4px solid #ff1801;
            color: #ff6b6b;
            box-shadow: 0 0 20px rgba(255, 24, 1, 0.2);
        }

        /* Forms Layout & Cards */
        .forms-deck {
            display: grid;
            grid-template-columns: 1.25fr 0.75fr;
            gap: 24px;
            margin-bottom: 28px;
            position: relative;
            z-index: 10;
        }
        .telemetry-card {
            background: linear-gradient(135deg, rgba(20, 20, 32, 0.9) 0%, rgba(12, 12, 20, 0.95) 100%);
            border: 1px solid rgba(255, 255, 255, 0.08);
            border-radius: 12px;
            padding: 26px;
            position: relative;
            overflow: hidden;
            box-shadow: 0 15px 40px rgba(0, 0, 0, 0.6);
            backdrop-filter: blur(20px);
        }
        .telemetry-card.cyan-trim { border-top: 3px solid #00d2be; }
        .telemetry-card.red-trim { border-top: 3px solid #ff1801; }

        .card-header-bar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 1px solid rgba(255, 255, 255, 0.06);
            padding-bottom: 14px;
            margin-bottom: 20px;
        }
        .card-title-text {
            font-family: 'Orbitron', sans-serif;
            font-size: 15px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 1.5px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .tag-pill {
            font-family: 'Share Tech Mono', monospace;
            font-size: 10px;
            padding: 3px 8px;
            border-radius: 4px;
            background: rgba(255,255,255,0.05);
            border: 1px solid rgba(255,255,255,0.1);
            color: #8c8c9e;
        }

        .input-matrix {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px;
        }
        .form-unit {
            position: relative;
        }
        .form-unit.full {
            grid-column: 1 / -1;
        }
        .form-unit label {
            display: flex;
            justify-content: space-between;
            font-family: 'Share Tech Mono', monospace;
            font-size: 10px;
            font-weight: 700;
            color: #8c8c9e;
            letter-spacing: 1px;
            margin-bottom: 6px;
            text-transform: uppercase;
        }
        .form-unit label span.req { color: #00d2be; }

        input, select, textarea {
            width: 100%;
            padding: 11px 14px;
            background: #090912;
            border: 1px solid #242436;
            border-radius: 6px;
            color: #ffffff;
            font-family: 'Titillium Web', sans-serif;
            font-size: 13px;
            transition: all 0.25s;
        }
        input:focus, select:focus, textarea:focus {
            outline: none;
            border-color: #00d2be;
            box-shadow: 0 0 12px rgba(0, 210, 190, 0.35);
            background: #0e0e1a;
        }

        /* Preset Chips */
        .preset-grid {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }
        .preset-btn {
            background: rgba(255, 255, 255, 0.03);
            border: 1px solid #28283c;
            border-radius: 8px;
            padding: 12px 14px;
            text-align: left;
            cursor: pointer;
            transition: all 0.25s;
            display: flex;
            flex-direction: column;
            gap: 4px;
        }
        .preset-btn:hover {
            background: rgba(0, 210, 190, 0.1);
            border-color: #00d2be;
            transform: translateX(4px);
            box-shadow: 0 0 15px rgba(0, 210, 190, 0.2);
        }
        .preset-title {
            font-family: 'Orbitron', sans-serif;
            font-size: 12px;
            font-weight: 700;
            color: #ffffff;
            display: flex;
            justify-content: space-between;
        }
        .preset-desc {
            font-family: 'Share Tech Mono', monospace;
            font-size: 11px;
            color: #8c8c9e;
        }

        .btn-action-trigger {
            grid-column: 1 / -1;
            padding: 13px;
            border-radius: 6px;
            font-family: 'Orbitron', sans-serif;
            font-size: 12px;
            font-weight: 900;
            letter-spacing: 1.5px;
            text-transform: uppercase;
            border: none;
            cursor: pointer;
            transition: all 0.3s ease;
            margin-top: 6px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }
        .btn-action-cyan {
            background: linear-gradient(135deg, #00d2be 0%, #008f82 100%);
            color: #07070d;
            box-shadow: 0 4px 15px rgba(0, 210, 190, 0.4);
        }
        .btn-action-cyan:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 25px rgba(0, 210, 190, 0.7);
        }

        /* Table Console */
        .table-console {
            background: linear-gradient(135deg, rgba(18, 18, 28, 0.95) 0%, rgba(10, 10, 18, 0.98) 100%);
            border: 1px solid rgba(255, 255, 255, 0.08);
            border-radius: 12px;
            padding: 26px;
            margin-bottom: 28px;
            position: relative;
            z-index: 10;
            box-shadow: 0 15px 45px rgba(0,0,0,0.7);
        }

        .filter-ribbon {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 16px;
            margin-bottom: 20px;
            flex-wrap: wrap;
        }
        .filter-cluster {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }
        .filter-tab-btn {
            background: rgba(255, 255, 255, 0.03);
            border: 1px solid #242436;
            color: #8c8c9e;
            padding: 8px 14px;
            border-radius: 6px;
            font-family: 'Share Tech Mono', monospace;
            font-size: 11px;
            font-weight: 700;
            letter-spacing: 1px;
            cursor: pointer;
            transition: all 0.25s;
        }
        .filter-tab-btn:hover, .filter-tab-btn.active {
            background: rgba(0, 210, 190, 0.15);
            border-color: var(--f1-cyan);
            color: #ffffff;
            box-shadow: 0 0 10px rgba(0, 210, 190, 0.3);
        }
        .search-field-wrap {
            display: flex;
            align-items: center;
            background: #090912;
            border: 1px solid #242436;
            border-radius: 6px;
            padding: 6px 14px;
            min-width: 280px;
        }
        .search-field-wrap input {
            background: transparent;
            border: none;
            padding: 4px 8px;
            color: #ffffff;
            font-size: 13px;
        }
        .search-field-wrap input:focus { box-shadow: none; border-color: transparent; }

        /* F1 Dark Table */
        .table-responsive {
            overflow-x: auto;
            border-radius: 8px;
            border: 1px solid #1f1f2e;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            text-align: left;
        }
        th {
            background: #0b0b14;
            color: #717188;
            font-family: 'Share Tech Mono', monospace;
            font-size: 11px;
            font-weight: 700;
            letter-spacing: 1.5px;
            text-transform: uppercase;
            padding: 14px 16px;
            border-bottom: 2px solid #232336;
            white-space: nowrap;
        }
        td {
            padding: 14px 16px;
            font-size: 13px;
            border-bottom: 1px solid #1a1a27;
            color: #d1d1e0;
            vertical-align: middle;
        }
        tr:hover td {
            background: rgba(255, 255, 255, 0.02);
        }

        .log-badge {
            font-family: 'Share Tech Mono', monospace;
            font-size: 11px;
            font-weight: 700;
            background: rgba(255, 255, 255, 0.04);
            border: 1px solid rgba(255, 255, 255, 0.15);
            color: #ffffff;
            padding: 3px 8px;
            border-radius: 4px;
            display: inline-block;
        }
        .chassis-tag {
            font-family: 'Share Tech Mono', monospace;
            font-size: 11px;
            background: rgba(0, 210, 190, 0.08);
            border: 1px solid rgba(0, 210, 190, 0.3);
            color: #00d2be;
            padding: 3px 6px;
            border-radius: 4px;
        }
        .reg-chip {
            display: inline-block;
            background: rgba(255, 183, 3, 0.08);
            color: #ffb703;
            border: 1px solid rgba(255, 183, 3, 0.3);
            border-radius: 4px;
            padding: 2px 6px;
            font-size: 10px;
            font-weight: 700;
            font-family: 'Share Tech Mono', monospace;
            margin-bottom: 4px;
        }

        .badge-passed {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 4px 10px;
            border-radius: 4px;
            font-family: 'Share Tech Mono', monospace;
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 1px;
            background: rgba(46, 204, 113, 0.12);
            border: 1px solid rgba(46, 204, 113, 0.4);
            color: #2ecc71;
            box-shadow: 0 0 10px rgba(46, 204, 113, 0.2);
        }
        .badge-failed {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 4px 10px;
            border-radius: 4px;
            font-family: 'Share Tech Mono', monospace;
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 1px;
            background: rgba(255, 24, 1, 0.15);
            border: 1px solid rgba(255, 24, 1, 0.4);
            color: #ff4757;
            box-shadow: 0 0 10px rgba(255, 24, 1, 0.2);
        }

        .btn-kill-switch {
            background: rgba(255, 24, 1, 0.1);
            border: 1px solid rgba(255, 24, 1, 0.4);
            color: #ff4757;
            padding: 6px 12px;
            border-radius: 4px;
            font-family: 'Orbitron', sans-serif;
            font-size: 10px;
            font-weight: 800;
            letter-spacing: 1px;
            cursor: pointer;
            transition: all 0.2s;
        }
        .btn-kill-switch:hover {
            background: #ff1801;
            color: #ffffff;
            box-shadow: 0 0 12px rgba(255, 24, 1, 0.6);
        }

        @media (max-width: 1100px) {
            .hud-matrix { grid-template-columns: repeat(2, 1fr); }
            .forms-deck { grid-template-columns: 1fr; }
        }
        @media (max-width: 768px) {
            .hud-matrix { grid-template-columns: 1fr; }
            .input-matrix { grid-template-columns: 1fr; }
            .filter-ribbon { flex-direction: column; align-items: stretch; }
        }
    </style>
</head>
<body>

<div class="ambient-glow-cyan"></div>
<div class="ambient-glow-red"></div>

<?php include 'navbar.php'; ?>

<!-- Top Live Telemetry Ribbon -->
<div class="telemetry-ribbon">
    <div class="telemetry-tag">
        <div class="pulse-core-cyan"></div>
        <span>FIA TECHNICAL SCRUTINEERING RIG // ACTIVE TELEMETRY CONSOLE</span>
    </div>
    <div style="color: #717188;">
        SCRUTINEERING PROTOCOL: <span style="color: var(--f1-cyan); font-weight:700;">FIA 2026 TECHNICAL COMPLIANCE</span>
    </div>
</div>

<?php if($msg): ?>
    <div class="alert-banner <?php echo $msg_type; ?>"><?php echo htmlspecialchars($msg); ?></div>
<?php endif; ?>

<!-- Summary Metric HUD Matrix -->
<div class="hud-matrix">
    <div class="hud-cell">
        <div class="hud-label"><span>Total Tests Run</span> <span>📋</span></div>
        <div class="hud-value"><?php echo $total_tests; ?></div>
    </div>
    <div class="hud-cell green">
        <div class="hud-label"><span>Compliant Tests</span> <span>✅</span></div>
        <div class="hud-value" style="color: #2ecc71;"><?php echo $passed_tests; ?></div>
    </div>
    <div class="hud-cell red">
        <div class="hud-label"><span>Violations Detected</span> <span>⚠️</span></div>
        <div class="hud-value" style="color: #ff4757;"><?php echo $failed_tests; ?></div>
    </div>
    <div class="hud-cell">
        <div class="hud-label"><span>Grid Compliance Rate</span> <span>⚖️</span></div>
        <div class="hud-value" style="color: var(--f1-cyan);"><?php echo $compliance_rate; ?>%</div>
    </div>
</div>

<!-- INTERACTIVE 2D F1 CAR INSPECTION BLUEPRINT -->
<?php include 'car_blueprint_component.php'; ?>

<!-- LIVE TELEMETRY GAUGES & TOLERANCE VISUALIZERS -->
<div class="telemetry-charts-deck" style="display: grid; grid-template-columns: 1fr 1fr; gap: 24px; margin-bottom: 28px;">
    <!-- Chart 1: Front Wing Load vs Deflection Curve -->
    <div class="telemetry-card cyan-trim" style="background: rgba(14, 14, 24, 0.95); border: 1px solid rgba(255, 255, 255, 0.08); border-radius: 12px; padding: 20px; box-shadow: 0 10px 30px rgba(0,0,0,0.5);">
        <div class="card-header-bar" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px;">
            <div class="card-title-text" style="color: #00d2be; font-family: 'Orbitron', sans-serif; font-size: 13px; font-weight: 700;">
                <span>📐</span> ART 3.5.1 // FRONT WING LOAD DEFLECTION CURVE
            </div>
            <span class="tag-pill" style="font-family: 'Share Tech Mono', monospace; font-size: 10px; color: #ff4757; border: 1px solid #ff4757; padding: 2px 6px; border-radius: 4px;">MAX 2.0mm LIMIT</span>
        </div>
        <div style="height: 240px; position: relative;">
            <canvas id="wingDeflectionChart"></canvas>
        </div>
    </div>

    <!-- Chart 2: Championship Grid Plank Wear Ultrasonic Scanner -->
    <div class="telemetry-card" style="background: rgba(14, 14, 24, 0.95); border: 1px solid rgba(255, 255, 255, 0.08); border-top: 3px solid #f1c40f; border-radius: 12px; padding: 20px; box-shadow: 0 10px 30px rgba(0,0,0,0.5);">
        <div class="card-header-bar" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px;">
            <div class="card-title-text" style="color: #f1c40f; font-family: 'Orbitron', sans-serif; font-size: 13px; font-weight: 700;">
                <span>📏</span> ART 3.12.1 // SKID BLOCK PLANK WEAR (MM)
            </div>
            <span class="tag-pill" style="font-family: 'Share Tech Mono', monospace; font-size: 10px; color: #00d2be; border: 1px solid #00d2be; padding: 2px 6px; border-radius: 4px;">MIN 9.0mm THRESHOLD</span>
        </div>
        <div style="height: 240px; position: relative;">
            <canvas id="plankWearChart"></canvas>
        </div>
    </div>
</div>

<!-- Forms Command Deck -->
<div class="forms-deck">
    <!-- Form 1: Conduct Scrutineering Diagnostic -->
    <div class="telemetry-card cyan-trim" id="diagnosticFormCard">
        <div class="card-header-bar">
            <div class="card-title-text" style="color: #00d2be;">
                <span>🛠️</span> 01. Execute Scrutineering Test
            </div>
            <span class="tag-pill">FIA TECHNICAL RIG</span>
        </div>
        <form method="POST">
            <input type="hidden" name="log_inspection" value="1">
            <div class="input-matrix">
                <div class="form-unit">
                    <label>Target Vehicle & Chassis <span class="req">*</span></label>
                    <select name="car_id" id="carSelect" required>
                        <option value="">-- Select Grid Chassis --</option>
                        <?php 
                        if ($cars_dropdown) {
                            $cars_dropdown->data_seek(0);
                            while($c = $cars_dropdown->fetch_assoc()): ?>
                                <option value="<?php echo $c['car_id']; ?>">
                                    <?php echo htmlspecialchars($c['team_name'] . ' — ' . $c['car_name'] . ' [' . $c['chassis_number'] . ']'); ?>
                                </option>
                            <?php endwhile; 
                        } ?>
                    </select>
                </div>
                <div class="form-unit">
                    <label>FIA Technical Regulation <span class="req">*</span></label>
                    <select name="regulation_id" id="regulationSelect" required>
                        <?php 
                        if ($regulations_dropdown) {
                            $regulations_dropdown->data_seek(0);
                            while($r = $regulations_dropdown->fetch_assoc()): ?>
                                <option value="<?php echo $r['regulation_id']; ?>" data-desc="<?php echo htmlspecialchars($r['description'] ?? ''); ?>">
                                    <?php echo htmlspecialchars($r['title']); ?>
                                </option>
                            <?php endwhile; 
                        } ?>
                    </select>
                </div>
                <div class="form-unit">
                    <label>Session Phase <span class="req">*</span></label>
                    <select name="session_type" id="sessionType" required>
                        <option value="Pre-Event Scrutineering">Pre-Event Scrutineering</option>
                        <option value="Post-Practice Check">Post-Practice Check</option>
                        <option value="Post-Qualifying Check">Post-Qualifying Check</option>
                        <option value="Post-Race Parc Ferme">Post-Race Parc Ferme</option>
                    </select>
                </div>
                <div class="form-unit">
                    <label>Inspection Location <span class="req">*</span></label>
                    <input type="text" name="location" id="testLocation" value="FIA Technical Garage / Bay 1" required>
                </div>
                <div class="form-unit">
                    <label>Diagnostic Test Name <span class="req">*</span></label>
                    <input type="text" name="measurement_name" id="testName" placeholder="e.g. Rear Wing Load Deflection" required>
                </div>
                <div class="form-unit">
                    <label>Unit of Measure <span class="req">*</span></label>
                    <input type="text" name="unit" id="testUnit" placeholder="e.g. mm, kg, °C, bar" required>
                </div>
                <div class="form-unit">
                    <label>Expected / Max Threshold <span class="req">*</span></label>
                    <input type="text" name="expected_value" id="expectedVal" placeholder="e.g. <= 2.00" required>
                </div>
                <div class="form-unit">
                    <label>Actual Measured Value <span class="req">*</span></label>
                    <input type="text" name="actual_value" id="actualVal" placeholder="e.g. 1.82" required>
                </div>
                <div class="form-unit full">
                    <label>Compliance Verdict <span class="req">*</span></label>
                    <select name="result" id="testVerdict" required>
                        <option value="Passed">Passed (Fully Compliant)</option>
                        <option value="Failed">Failed (Technical Violation Detected)</option>
                    </select>
                </div>
                <div class="form-unit full">
                    <label>Delegate Observation Notes</label>
                    <input type="text" name="notes" id="testNotes" placeholder="e.g. 1000N vertical load applied. Deflection within 2.0mm tolerance limit.">
                </div>
                <button type="submit" class="btn-action-trigger btn-action-cyan">
                    <span>⚡</span> RECORD TELEMETRY DIAGNOSTIC
                </button>
            </div>
        </form>
    </div>

    <!-- Form 2: Quick Diagnostic Presets -->
    <div class="telemetry-card red-trim">
        <div class="card-header-bar">
            <div class="card-title-text" style="color: #ff4757;">
                <span>⚡</span> 02. Quick Diagnostic Presets
            </div>
            <span class="tag-pill">1-CLICK RIG</span>
        </div>
        <div class="preset-grid">
            <div class="preset-btn" onclick="applyPreset('Rear Wing Load Deflection', '<= 2.00', '1.74', 'mm', 'Passed', 1, 'Verified with 1000N vertical rig load.')">
                <div class="preset-title">
                    <span>Rear Wing Deflection</span>
                    <span style="color: #00d2be;">Art 3.5.1</span>
                </div>
                <div class="preset-desc">Threshold: &le; 2.00 mm | Load test under 1000N</div>
            </div>

            <div class="preset-btn" onclick="applyPreset('Vehicle Total Mass (Post-Race)', '>= 798.0', '801.5', 'kg', 'Passed', 2, 'Weighbridge calibrated: Car + Driver combined weight.')">
                <div class="preset-title">
                    <span>Minimum Car Weight</span>
                    <span style="color: #00d2be;">Art 4.1</span>
                </div>
                <div class="preset-desc">Threshold: &ge; 798.0 kg | Post-session weighbridge</div>
            </div>

            <div class="preset-btn" onclick="applyPreset('Fuel Temperature Check', '>= -10.0', '-6.5', '°C', 'Passed', 3, 'Sample taken 1 hour prior to session.')">
                <div class="preset-title">
                    <span>Fuel Temperature Delta</span>
                    <span style="color: #00d2be;">Art 5.2</span>
                </div>
                <div class="preset-desc">Threshold: &ge; -10.0 °C vs ambient reading</div>
            </div>

            <div class="preset-btn" onclick="applyPreset('Plank Skid Block Thickness', '>= 9.0', '9.35', 'mm', 'Passed', 1, 'Titanium skid wear checked across 4 reference holes.')">
                <div class="preset-title">
                    <span>Plank & Skid Block Wear</span>
                    <span style="color: #00d2be;">Art 3.5.9</span>
                </div>
                <div class="preset-desc">Threshold: &ge; 9.0 mm | Ultrasonic thickness scan</div>
            </div>

            <div class="preset-btn" onclick="applyPreset('Rear Wing Flexibility Defect', '<= 2.00', '2.45', 'mm', 'Failed', 1, 'Top flap slot deflection exceeded allowable tolerance.')">
                <div class="preset-title" style="color: #ff6b6b;">
                    <span>Deflection Violation Flag</span>
                    <span style="color: #ff4757;">VIOLATION</span>
                </div>
                <div class="preset-desc">Simulate flex fail: 2.45 mm (Ref: Stewards DOC)</div>
            </div>
        </div>
    </div>
</div>

<!-- Scrutineering Logs Table Console -->
<div class="table-console">
    <div class="card-header-bar">
        <div class="card-title-text" style="color: #ffffff;">
            <span>📋</span> Live Scrutineering Telemetry Logs
        </div>
        <span class="tag-pill"><?php echo $total_tests; ?> TELEMETRY LOGS</span>
    </div>

    <!-- Filter Ribbon -->
    <div class="filter-ribbon">
        <div class="filter-cluster">
            <button type="button" class="filter-tab-btn active" data-filter="all">All Logs (<?php echo $total_tests; ?>)</button>
            <button type="button" class="filter-tab-btn" data-filter="Passed">Compliant (<?php echo $passed_tests; ?>)</button>
            <button type="button" class="filter-tab-btn" data-filter="Failed">Violations (<?php echo $failed_tests; ?>)</button>
        </div>
        <div class="search-field-wrap">
            <span style="color: #717188; font-size: 12px; margin-right: 4px;">🔎</span>
            <input type="text" id="filterSearch" placeholder="Search test name, chassis, team, regulation...">
        </div>
    </div>

    <!-- FIA Styled Dark Table -->
    <div class="table-responsive">
        <table>
            <thead>
                <tr>
                    <th>Log #</th>
                    <th>Vehicle & Competitor</th>
                    <th>Technical Regulation</th>
                    <th>Diagnostic Test</th>
                    <th>Threshold</th>
                    <th>Actual Reading</th>
                    <th>Compliance Verdict</th>
                    <th>Location / Delegate</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($all_measurements)): ?>
                    <tr>
                        <td colspan="9" style="text-align: center; padding: 40px; color: #717188;">
                            No scrutineering measurements recorded yet.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($all_measurements as $row): 
                        $is_pass = (strcasecmp($row['result'], 'Passed') === 0);
                    ?>
                    <tr class="scrutineering-row" data-result="<?php echo htmlspecialchars($row['result']); ?>">
                        <td>
                            <span class="log-badge">#LOG-<?php echo str_pad($row['measurement_id'], 3, '0', STR_PAD_LEFT); ?></span>
                            <div style="font-family: 'Share Tech Mono', monospace; font-size: 10px; color: #717188; margin-top: 3px;">
                                <?php echo date('H:i:s', strtotime($row['created_at'])); ?>
                            </div>
                        </td>
                        <td>
                            <div style="font-weight: 700; color: #ffffff;"><?php echo htmlspecialchars($row['car_name']); ?></div>
                            <div style="margin-top: 2px;">
                                <span class="chassis-tag"><?php echo htmlspecialchars($row['chassis_number']); ?></span>
                                <span style="font-size: 11px; color: #717188; margin-left: 4px;"><?php echo htmlspecialchars($row['team_name'] ?: 'Grid Chassis'); ?></span>
                            </div>
                        </td>
                        <td>
                            <span class="reg-chip">REGULATION</span>
                            <div style="font-size: 12px; color: #d1d1e0; max-width: 180px; line-height: 1.3;" title="<?php echo htmlspecialchars($row['regulation_desc'] ?? ''); ?>">
                                <?php echo htmlspecialchars($row['regulation_title']); ?>
                            </div>
                        </td>
                        <td>
                            <div style="font-weight: 600; color: #ffffff; font-size: 13px;">
                                <?php echo htmlspecialchars($row['measurement_name']); ?>
                            </div>
                            <div style="font-size: 11px; color: #8c8c9e; margin-top: 2px;">
                                Phase: <?php echo htmlspecialchars($row['session_type']); ?>
                            </div>
                        </td>
                        <td>
                            <span style="font-family: 'Share Tech Mono', monospace; font-size: 12px; color: #a4a4b8;">
                                <?php echo htmlspecialchars($row['expected_value'] . ' ' . $row['unit']); ?>
                            </span>
                        </td>
                        <td>
                            <span style="font-family: 'Share Tech Mono', monospace; font-size: 13px; font-weight: 700; color: <?php echo $is_pass ? '#00d2be' : '#ff4757'; ?>;">
                                <?php echo htmlspecialchars($row['actual_value'] . ' ' . $row['unit']); ?>
                            </span>
                        </td>
                        <td>
                            <span class="<?php echo $is_pass ? 'badge-passed' : 'badge-failed'; ?>">
                                <?php echo $is_pass ? '● PASSED' : '▲ FAILED'; ?>
                            </span>
                        </td>
                        <td>
                            <div style="font-size: 12px; color: #c0c0d0;"><?php echo htmlspecialchars($row['location']); ?></div>
                            <div style="font-size: 10px; color: #6e6e84; font-family: 'Share Tech Mono', monospace; margin-top: 2px;">
                                BY: <?php echo htmlspecialchars($row['inspector_name'] ?? 'FIA DELEGATE'); ?>
                            </div>
                        </td>
                        <td>
                            <form method="POST" onsubmit="return confirm('Purge measurement telemetry log #<?php echo $row['measurement_id']; ?>?');">
                                <input type="hidden" name="action" value="delete_measurement">
                                <input type="hidden" name="measurement_id" value="<?php echo $row['measurement_id']; ?>">
                                <button type="submit" class="btn-kill-switch">PURGE</button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
// Filter Tabs and Realtime Search
document.addEventListener('DOMContentLoaded', () => {
    const filterTabs = document.querySelectorAll('.filter-tab-btn');
    const searchField = document.getElementById('filterSearch');
    const rows = document.querySelectorAll('.scrutineering-row');

    let currentFilter = 'all';

    function runFilter() {
        const query = (searchField.value || '').toLowerCase().trim();

        rows.forEach(row => {
            const result = (row.getAttribute('data-result') || '').toLowerCase();
            const text = row.textContent.toLowerCase();

            let matchesFilter = (currentFilter === 'all') || (result === currentFilter.toLowerCase());
            let matchesSearch = text.includes(query);

            if (matchesFilter && matchesSearch) {
                row.style.display = '';
            } else {
                row.style.display = 'none';
            }
        });
    }

    filterTabs.forEach(tab => {
        tab.addEventListener('click', () => {
            filterTabs.forEach(t => t.classList.remove('active'));
            tab.classList.add('active');
            currentFilter = tab.getAttribute('data-filter');
            runFilter();
        });
    });

    searchField.addEventListener('input', runFilter);
});

// Apply Quick Preset into Form
function applyPreset(name, expected, actual, unit, result, regId, notes) {
    document.getElementById('testName').value = name;
    document.getElementById('expectedVal').value = expected;
    document.getElementById('actualVal').value = actual;
    document.getElementById('testUnit').value = unit;
    document.getElementById('testVerdict').value = result;
    document.getElementById('testNotes').value = notes;
    if (regId) {
        document.getElementById('regulationSelect').value = regId;
    }
    document.getElementById('diagnosticFormCard').scrollIntoView({ behavior: 'smooth' });
}

// --- LIVE SCRUTINEERING CHARTS INITIALIZER ---
document.addEventListener('DOMContentLoaded', () => {
    // 1. Wing Deflection Chart
    const ctxWing = document.getElementById('wingDeflectionChart');
    if (ctxWing && typeof Chart !== 'undefined') {
        new Chart(ctxWing, {
            type: 'line',
            data: {
                labels: ['0N', '200N', '400N', '600N', '800N', '1000N'],
                datasets: [
                    {
                        label: 'Statutory Tolerance Ceiling (2.0mm)',
                        data: [2.0, 2.0, 2.0, 2.0, 2.0, 2.0],
                        borderColor: '#ff1801',
                        borderWidth: 2,
                        borderDash: [6, 4],
                        pointRadius: 0,
                        fill: false
                    },
                    {
                        label: 'Red Bull RB20 #1 (Actual Load)',
                        data: [0.0, 0.32, 0.71, 1.12, 1.48, 1.84],
                        borderColor: '#00d2be',
                        backgroundColor: 'rgba(0, 210, 190, 0.1)',
                        borderWidth: 2.5,
                        pointBackgroundColor: '#00d2be',
                        pointRadius: 4,
                        tension: 0.3,
                        fill: true
                    },
                    {
                        label: 'Failed Spec (Non-Compliant Flap)',
                        data: [0.0, 0.55, 1.15, 1.78, 2.22, 2.65],
                        borderColor: '#ff4757',
                        borderWidth: 2,
                        pointBackgroundColor: '#ff4757',
                        pointRadius: 3,
                        tension: 0.3,
                        fill: false
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        labels: { color: '#cbd5e1', font: { family: "'Share Tech Mono', monospace", size: 10 } }
                    }
                },
                scales: {
                    x: {
                        grid: { color: 'rgba(255, 255, 255, 0.05)' },
                        ticks: { color: '#8c8c9e', font: { family: "'Share Tech Mono', monospace" } }
                    },
                    y: {
                        title: { display: true, text: 'Deflection (mm)', color: '#8c8c9e' },
                        grid: { color: 'rgba(255, 255, 255, 0.05)' },
                        ticks: { color: '#8c8c9e', font: { family: "'Share Tech Mono', monospace" } },
                        min: 0,
                        max: 3.0
                    }
                }
            }
        });
    }

    // 2. Plank Wear Thickness Chart
    const ctxPlank = document.getElementById('plankWearChart');
    if (ctxPlank && typeof Chart !== 'undefined') {
        new Chart(ctxPlank, {
            type: 'bar',
            data: {
                labels: ['RBR #1', 'RBR #2', 'FER #16', 'FER #55', 'MER #63', 'MER #44', 'MCL #4', 'MCL #81', 'AST #14', 'ALP #10'],
                datasets: [
                    {
                        label: 'Post-Session Plank Thickness (mm)',
                        data: [9.45, 9.20, 8.85, 9.35, 9.50, 9.15, 9.40, 9.30, 9.60, 9.10],
                        backgroundColor: function(context) {
                            const val = context.raw;
                            return (val < 9.0) ? '#ff1801' : (val < 9.2 ? '#f1c40f' : '#00d2be');
                        },
                        borderRadius: 4
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        labels: { color: '#cbd5e1', font: { family: "'Share Tech Mono', monospace", size: 10 } }
                    },
                    tooltip: {
                        callbacks: {
                            afterLabel: function(context) {
                                return context.raw < 9.0 ? '⚠️ VIOLATION: Below 9.0mm limit' : '✅ COMPLIANT';
                            }
                        }
                    }
                },
                scales: {
                    x: {
                        grid: { color: 'rgba(255, 255, 255, 0.05)' },
                        ticks: { color: '#8c8c9e', font: { family: "'Share Tech Mono', monospace", size: 10 } }
                    },
                    y: {
                        title: { display: true, text: 'Thickness (mm)', color: '#8c8c9e' },
                        grid: { color: 'rgba(255, 255, 255, 0.05)' },
                        ticks: { color: '#8c8c9e', font: { family: "'Share Tech Mono', monospace" } },
                        min: 8.0,
                        max: 10.0
                    }
                }
            }
        });
    }
});
</script>

</body>
</html>
