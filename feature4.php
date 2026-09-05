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

// Auto-create and seed tables if not exist
$conn->query("CREATE TABLE IF NOT EXISTS component_allocations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    car_id INT NOT NULL,
    component_type VARCHAR(60) NOT NULL,
    units_used INT DEFAULT 1,
    max_limit INT DEFAULT 4,
    penalty_threshold_reached TINYINT(1) DEFAULT 0,
    last_replaced_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (car_id) REFERENCES CARS(car_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

$conn->query("CREATE TABLE IF NOT EXISTS scrutineering_bulletins (
    bulletin_id INT AUTO_INCREMENT PRIMARY KEY,
    document_no VARCHAR(50) NOT NULL UNIQUE,
    grand_prix_name VARCHAR(100) NOT NULL,
    session_type VARCHAR(50) NOT NULL DEFAULT 'Qualifying',
    status VARCHAR(50) NOT NULL DEFAULT 'Official',
    published_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    summary_notes TEXT,
    issued_by INT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

// Seed initial component allocations if empty
$chk_alloc = $conn->query("SELECT COUNT(*) as cnt FROM component_allocations");
if ($chk_alloc && $chk_alloc->fetch_assoc()['cnt'] == 0) {
    $cars = $conn->query("SELECT car_id FROM CARS LIMIT 10");
    $comp_limits = [
        'Internal Combustion Engine (ICE)' => 4,
        'Turbocharger (TC)' => 4,
        'MGU-H' => 4,
        'MGU-K' => 4,
        'Energy Store (ES)' => 2,
        'Control Electronics (CE)' => 2,
        'Gearbox (GB)' => 4
    ];
    if ($cars) {
        while ($c = $cars->fetch_assoc()) {
            $cid = $c['car_id'];
            foreach ($comp_limits as $ctype => $mlim) {
                $used = 1;
                if ($cid == 1 && $ctype == 'Internal Combustion Engine (ICE)') $used = 4;
                if ($cid == 3 && $ctype == 'Internal Combustion Engine (ICE)') $used = 5;
                if ($cid == 5 && $ctype == 'Energy Store (ES)') $used = 3;
                if ($cid == 7 && $ctype == 'Turbocharger (TC)') $used = 3;
                if ($cid == 2 && $ctype == 'Control Electronics (CE)') $used = 2;
                $penalty = ($used > $mlim) ? 1 : 0;
                $conn->query("INSERT INTO component_allocations (car_id, component_type, units_used, max_limit, penalty_threshold_reached) VALUES ($cid, '$ctype', $used, $mlim, $penalty)");
            }
        }
    }
}

// Seed initial bulletins if empty
$chk_bul = $conn->query("SELECT COUNT(*) as cnt FROM scrutineering_bulletins");
if ($chk_bul && $chk_bul->fetch_assoc()['cnt'] == 0) {
    $conn->query("INSERT INTO scrutineering_bulletins (document_no, grand_prix_name, session_type, status, summary_notes, issued_by) VALUES
    ('DOC-04-MCO', 'Monaco Grand Prix 2026', 'FP1', 'Official', 'Initial technical checks and weight verifications for all 20 chassis completed without anomaly.', 1),
    ('DOC-12-MCO', 'Monaco Grand Prix 2026', 'FP3', 'Official', 'PU Component replacements logged: Car 3 (Mercedes W15) 5th ICE installed. Grid penalty notice issued.', 1),
    ('DOC-28-MCO', 'Monaco Grand Prix 2026', 'Qualifying', 'Official', 'Post-Qualifying scrutineering: Front wing flex and fuel temperature tests passed. Car 11 rear wing under further FIA review.', 1),
    ('DOC-39-MCO', 'Monaco Grand Prix 2026', 'Race', 'Pending Sign-off', 'Pre-Race parc fermé technical delegating in progress. Tyre pressures, cooling fans, and seal locks verified.', 1)");
}

$msg = "";
$msg_type = "success";

// 1. LOG / REPLACE COMPONENT HANDLER
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action']) && $_POST['action'] === 'log_component') {
    $car_id = intval($_POST['car_id']);
    $component_type = trim($_POST['component_type']);
    $units_used = intval($_POST['units_used']);
    $max_limit = intval($_POST['max_limit'] ?? 4);

    // Default standard limits
    if ($component_type === 'Energy Store (ES)' || $component_type === 'Control Electronics (CE)') {
        $max_limit = 2;
    }

    $penalty = ($units_used > $max_limit) ? 1 : 0;

    // Check if record exists
    $chk_stmt = $conn->prepare("SELECT id FROM component_allocations WHERE car_id = ? AND component_type = ?");
    $chk_stmt->bind_param("is", $car_id, $component_type);
    $chk_stmt->execute();
    $res = $chk_stmt->get_result();

    if ($res && $row = $res->fetch_assoc()) {
        $alloc_id = $row['id'];
        $upd_stmt = $conn->prepare("UPDATE component_allocations SET units_used = ?, max_limit = ?, penalty_threshold_reached = ?, last_replaced_date = NOW() WHERE id = ?");
        $upd_stmt->bind_param("iiii", $units_used, $max_limit, $penalty, $alloc_id);
        $upd_stmt->execute();
    } else {
        $ins_stmt = $conn->prepare("INSERT INTO component_allocations (car_id, component_type, units_used, max_limit, penalty_threshold_reached) VALUES (?, ?, ?, ?, ?)");
        $ins_stmt->bind_param("isiii", $car_id, $component_type, $units_used, $max_limit, $penalty);
        $ins_stmt->execute();
    }

    if ($penalty) {
        $msg = "CRITICAL GRID PENALTY WARNING: Unit {$units_used}/{$max_limit} exceeds allowable FIA quota for {$component_type}! 10-Place Grid Drop automatically triggered.";
        $msg_type = "error";
    } else {
        $msg = "COMPONENT ALLOCATION LOGGED: Chassis #{$car_id} {$component_type} updated to Unit {$units_used}/{$max_limit} (Compliant with FIA regulations).";
        $msg_type = "success";
    }
}

// 2. ISSUE OFFICIAL BULLETIN HANDLER
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action']) && $_POST['action'] === 'publish_bulletin') {
    $document_no = trim($_POST['document_no']);
    $grand_prix_name = trim($_POST['grand_prix_name']);
    $session_type = trim($_POST['session_type']);
    $status = trim($_POST['status'] ?? 'Official');
    $summary_notes = trim($_POST['summary_notes']);
    $issued_by = $_SESSION['user_id'] ?? 1;

    $bul_stmt = $conn->prepare("INSERT INTO scrutineering_bulletins (document_no, grand_prix_name, session_type, status, summary_notes, issued_by) VALUES (?, ?, ?, ?, ?, ?)");
    $bul_stmt->bind_param("sssssi", $document_no, $grand_prix_name, $session_type, $status, $summary_notes, $issued_by);

    if ($bul_stmt->execute()) {
        $msg = "OFFICIAL TECHNICAL BULLETIN PUBLISHED: Document {$document_no} issued to all competitors and media.";
        $msg_type = "success";
    } else {
        $msg = "PUBLICATION ERROR: " . $conn->error;
        $msg_type = "error";
    }
}

// 3. DELETE BULLETIN HANDLER
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action']) && $_POST['action'] === 'delete_bulletin') {
    $bulletin_id = intval($_POST['bulletin_id']);
    $del_stmt = $conn->prepare("DELETE FROM scrutineering_bulletins WHERE bulletin_id = ?");
    $del_stmt->bind_param("i", $bulletin_id);
    if ($del_stmt->execute()) {
        $msg = "BULLETIN EXPUNGED: Document removed from active official registry.";
        $msg_type = "success";
    } else {
        $msg = "DELETION ERROR: " . $conn->error;
        $msg_type = "error";
    }
}

// Dropdown Cars
$cars_dropdown = $conn->query("SELECT c.car_id, c.car_name, c.chassis_number, t.team_name, d.full_name as driver_name 
                               FROM CARS c 
                               JOIN TEAMS t ON c.team_id = t.team_id 
                               LEFT JOIN DRIVERS d ON c.driver_id = d.driver_id 
                               ORDER BY t.team_name ASC, c.car_name ASC");

// Component matrix data query
$alloc_query = "SELECT a.*, c.car_name, c.chassis_number, t.team_name, d.full_name as driver_name 
                FROM component_allocations a 
                JOIN CARS c ON a.car_id = c.car_id 
                JOIN TEAMS t ON c.team_id = t.team_id 
                LEFT JOIN DRIVERS d ON c.driver_id = d.driver_id 
                ORDER BY t.team_name ASC, c.car_id ASC, a.component_type ASC";
$alloc_res = $conn->query($alloc_query);

// Group allocations by Car
$grid_matrix = [];
$cars_at_limit_count = 0;
$cars_penalty_count = 0;
$car_penalty_set = [];
$car_limit_set = [];

if ($alloc_res) {
    while ($row = $alloc_res->fetch_assoc()) {
        $cid = $row['car_id'];
        if (!isset($grid_matrix[$cid])) {
            $grid_matrix[$cid] = [
                'car_name' => $row['car_name'],
                'chassis_number' => $row['chassis_number'],
                'team_name' => $row['team_name'],
                'driver_name' => $row['driver_name'],
                'components' => []
            ];
        }
        $grid_matrix[$cid]['components'][$row['component_type']] = [
            'units_used' => intval($row['units_used']),
            'max_limit' => intval($row['max_limit']),
            'penalty' => intval($row['penalty_threshold_reached'])
        ];

        if (intval($row['units_used']) >= intval($row['max_limit']) && intval($row['units_used']) <= intval($row['max_limit'])) {
            $car_limit_set[$cid] = true;
        }
        if (intval($row['units_used']) > intval($row['max_limit'])) {
            $car_penalty_set[$cid] = true;
        }
    }
}
$cars_at_limit_count = count($car_limit_set);
$cars_penalty_count = count($car_penalty_set);

// Bulletins Query
$bulletins_query = "SELECT b.*, u.full_name as delegate_name 
                    FROM scrutineering_bulletins b 
                    LEFT JOIN USERS u ON b.issued_by = u.user_id 
                    ORDER BY b.bulletin_id DESC";
$bulletins_res = $conn->query($bulletins_query);
$all_bulletins = [];
$total_bulletins = 0;
if ($bulletins_res) {
    while ($b = $bulletins_res->fetch_assoc()) {
        $all_bulletins[] = $b;
        $total_bulletins++;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>FSMS | Official Technical Bulletins & PU Allocations</title>
    <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@500;700;800;900&family=Share+Tech+Mono&family=Titillium+Web:wght@300;400;600;700;900&display=swap" rel="stylesheet">
    <style>
        :root {
            --f1-red: #ff1801;
            --f1-red-glow: rgba(255, 24, 1, 0.4);
            --f1-cyan: #00d2be;
            --f1-cyan-glow: rgba(0, 210, 190, 0.4);
            --f1-amber: #ffb703;
            --f1-amber-glow: rgba(255, 183, 3, 0.4);
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
            background: linear-gradient(rgba(18, 16, 16, 0) 50%, rgba(0, 0, 0, 0.25) 50%), linear-gradient(90deg, rgba(255, 0, 0, 0.03), rgba(0, 255, 0, 0.01), rgba(0, 0, 255, 0.03));
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
            background: radial-gradient(circle, rgba(255, 24, 1, 0.14) 0%, transparent 65%);
            bottom: -100px;
            left: -100px;
            pointer-events: none;
            z-index: 0;
            filter: blur(50px);
        }

        /* Telemetry Ribbon */
        .telemetry-ribbon {
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: rgba(14, 14, 24, 0.95);
            border: 1px solid var(--f1-border);
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
            border: 1px solid var(--f1-border);
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
        .hud-cell.amber::after { background: linear-gradient(90deg, var(--f1-amber), transparent); }
        .hud-cell.red::after { background: linear-gradient(90deg, var(--f1-red), transparent); }
        .hud-cell:hover {
            transform: translateY(-3px);
            border-color: rgba(255, 255, 255, 0.2);
            box-shadow: 0 12px 35px rgba(0,0,0,0.8);
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
            grid-template-columns: 1fr 1fr;
            gap: 24px;
            margin-bottom: 28px;
            position: relative;
            z-index: 10;
        }
        .telemetry-card {
            background: linear-gradient(135deg, rgba(20, 20, 32, 0.9) 0%, rgba(12, 12, 20, 0.95) 100%);
            border: 1px solid var(--f1-border);
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
        .form-unit { position: relative; }
        .form-unit.full { grid-column: 1 / -1; }
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
        .btn-action-cyan:hover { transform: translateY(-2px); box-shadow: 0 6px 25px rgba(0, 210, 190, 0.7); }
        .btn-action-red {
            background: linear-gradient(135deg, #ff1801 0%, #a80000 100%);
            color: #ffffff;
            box-shadow: 0 4px 15px rgba(255, 24, 1, 0.4);
        }
        .btn-action-red:hover { transform: translateY(-2px); box-shadow: 0 6px 25px rgba(255, 24, 1, 0.7); }

        /* Power Unit & Gearbox Quota Matrix Table */
        .table-console {
            background: linear-gradient(135deg, rgba(18, 18, 28, 0.95) 0%, rgba(10, 10, 18, 0.98) 100%);
            border: 1px solid var(--f1-border);
            border-radius: 12px;
            padding: 26px;
            margin-bottom: 28px;
            position: relative;
            z-index: 10;
            box-shadow: 0 15px 45px rgba(0,0,0,0.7);
        }

        .quota-card-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(340px, 1fr));
            gap: 16px;
            margin-top: 15px;
        }
        .quota-car-card {
            background: #090912;
            border: 1px solid #202030;
            border-radius: 10px;
            padding: 16px 18px;
            position: relative;
            transition: all 0.25s;
        }
        .quota-car-card:hover {
            border-color: rgba(0, 210, 190, 0.4);
            transform: translateY(-2px);
        }
        .quota-car-card.has-penalty {
            border-color: rgba(255, 24, 1, 0.5);
            background: linear-gradient(135deg, rgba(255, 24, 1, 0.05) 0%, #090912 100%);
        }
        .car-card-head {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 12px;
            padding-bottom: 8px;
            border-bottom: 1px solid #1c1c2b;
        }
        .chassis-tag {
            font-family: 'Share Tech Mono', monospace;
            font-size: 11px;
            background: rgba(0, 210, 190, 0.08);
            border: 1px solid rgba(0, 210, 190, 0.3);
            color: #00d2be;
            padding: 2px 6px;
            border-radius: 4px;
        }
        .penalty-warning-tag {
            font-family: 'Share Tech Mono', monospace;
            font-size: 10px;
            font-weight: 700;
            background: rgba(255, 24, 1, 0.2);
            border: 1px solid #ff1801;
            color: #ff5252;
            padding: 2px 6px;
            border-radius: 4px;
            animation: pulseRed 1s infinite alternate;
        }
        @keyframes pulseRed { 0% { opacity: 0.6; } 100% { opacity: 1; box-shadow: 0 0 8px #ff1801; } }

        .comp-bar-list {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }
        .comp-bar-item {
            display: flex;
            flex-direction: column;
            gap: 3px;
        }
        .comp-meta {
            display: flex;
            justify-content: space-between;
            font-family: 'Share Tech Mono', monospace;
            font-size: 10px;
            color: #8c8c9e;
        }
        .progress-track {
            height: 6px;
            background: #141422;
            border-radius: 3px;
            overflow: hidden;
        }
        .progress-fill {
            height: 100%;
            border-radius: 3px;
            transition: width 0.6s ease;
        }
        .progress-fill.green { background: #2ecc71; }
        .progress-fill.amber { background: #ffb703; box-shadow: 0 0 6px rgba(255, 183, 3, 0.5); }
        .progress-fill.red { background: #ff1801; box-shadow: 0 0 8px rgba(255, 24, 1, 0.8); }

        /* Bulletins Table */
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

        .table-responsive {
            overflow-x: auto;
            border-radius: 8px;
            border: 1px solid #1f1f2e;
        }
        table { width: 100%; border-collapse: collapse; text-align: left; }
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
        tr:hover td { background: rgba(255, 255, 255, 0.02); }

        .doc-badge {
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
        .status-chip {
            font-family: 'Share Tech Mono', monospace;
            font-size: 10px;
            font-weight: 700;
            padding: 3px 8px;
            border-radius: 4px;
            text-transform: uppercase;
        }
        .status-chip.official {
            background: rgba(46, 204, 113, 0.15);
            border: 1px solid rgba(46, 204, 113, 0.4);
            color: #2ecc71;
        }
        .status-chip.pending {
            background: rgba(255, 183, 3, 0.15);
            border: 1px solid rgba(255, 183, 3, 0.4);
            color: #ffb703;
        }

        .btn-view-doc {
            background: rgba(0, 210, 190, 0.1);
            border: 1px solid rgba(0, 210, 190, 0.4);
            color: #00d2be;
            padding: 6px 12px;
            border-radius: 4px;
            font-family: 'Orbitron', sans-serif;
            font-size: 10px;
            font-weight: 800;
            letter-spacing: 1px;
            cursor: pointer;
            transition: all 0.2s;
            text-transform: uppercase;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
        .btn-view-doc:hover {
            background: #00d2be;
            color: #07070d;
            box-shadow: 0 0 12px rgba(0, 210, 190, 0.6);
        }

        /* Printable Official FIA Document Modal */
        .doc-modal-overlay {
            display: none;
            position: fixed;
            top: 0; left: 0; width: 100%; height: 100%;
            background: rgba(4, 4, 8, 0.92);
            backdrop-filter: blur(12px);
            z-index: 3000;
            justify-content: center;
            align-items: center;
            padding: 20px;
        }
        .doc-modal-overlay.active { display: flex; }
        .official-fia-paper {
            background: #ffffff;
            color: #111111;
            width: 100%;
            max-width: 750px;
            max-height: 90vh;
            overflow-y: auto;
            border-radius: 4px;
            padding: 40px 48px;
            box-shadow: 0 25px 60px rgba(0,0,0,0.95);
            font-family: 'Titillium Web', Arial, sans-serif;
            position: relative;
        }
        .fia-paper-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 2px solid #e10600;
            padding-bottom: 16px;
            margin-bottom: 24px;
        }
        .fia-logo-box {
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .fia-crest {
            font-size: 32px;
        }
        .fia-header-title {
            font-size: 18px;
            font-weight: 900;
            text-transform: uppercase;
            letter-spacing: 1px;
            color: #111111;
        }
        .fia-header-sub {
            font-size: 12px;
            color: #555555;
            text-transform: uppercase;
        }
        .doc-meta-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 24px;
            font-size: 13px;
        }
        .doc-meta-table td {
            padding: 6px 10px;
            border: 1px solid #dddddd;
            color: #222222;
        }
        .doc-meta-label {
            font-weight: 700;
            background: #f4f4f4;
            width: 28%;
            text-transform: uppercase;
            font-size: 11px;
        }
        .doc-body-content {
            font-size: 14px;
            line-height: 1.6;
            color: #222222;
            margin-bottom: 30px;
            white-space: pre-line;
            border-left: 3px solid #e10600;
            padding-left: 16px;
        }
        .delegate-sign-box {
            display: flex;
            justify-content: flex-end;
            margin-top: 30px;
            padding-top: 16px;
            border-top: 1px solid #dddddd;
        }
        .signature-block {
            text-align: right;
            font-size: 13px;
        }
        .sign-title { font-weight: 700; color: #111111; text-transform: uppercase; }

        .modal-action-bar {
            display: flex;
            justify-content: flex-end;
            gap: 12px;
            margin-top: 24px;
            padding-top: 16px;
            border-top: 1px solid #eeeeee;
        }
        .btn-print-doc {
            background: #e10600;
            color: #ffffff;
            border: none;
            padding: 10px 20px;
            border-radius: 4px;
            font-weight: 700;
            font-size: 13px;
            cursor: pointer;
            text-transform: uppercase;
        }
        .btn-print-doc:hover { background: #b80500; }
        .btn-close-paper {
            background: #f0f0f0;
            color: #333333;
            border: 1px solid #cccccc;
            padding: 10px 18px;
            border-radius: 4px;
            font-weight: 700;
            font-size: 13px;
            cursor: pointer;
        }

        @media print {
            body * { visibility: hidden; }
            .official-fia-paper, .official-fia-paper * { visibility: visible; }
            .official-fia-paper { position: absolute; left: 0; top: 0; width: 100%; box-shadow: none; padding: 20px; }
            .modal-action-bar { display: none; }
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

<!-- Top Telemetry Ribbon -->
<div class="telemetry-ribbon">
    <div class="telemetry-tag">
        <div class="pulse-core-cyan"></div>
        <span>FIA TECHNICAL DELEGATE BULLETINS // PU ALLOCATION RIG ACTIVE</span>
    </div>
    <div style="color: #717188;">
        FIA HOMOLOGATION STANDARD: <span style="color: var(--f1-cyan); font-weight:700;">2026 POWER UNIT & GEARBOX CAP</span>
    </div>
</div>

<?php if($msg): ?>
    <div class="alert-banner <?php echo $msg_type; ?>"><?php echo htmlspecialchars($msg); ?></div>
<?php endif; ?>

<!-- Summary Metric HUD Matrix -->
<div class="hud-matrix">
    <div class="hud-cell">
        <div class="hud-label"><span>Official Bulletins</span> <span>📑</span></div>
        <div class="hud-value"><?php echo $total_bulletins; ?></div>
    </div>
    <div class="hud-cell amber">
        <div class="hud-label"><span>At Component Limit</span> <span>⚠️</span></div>
        <div class="hud-value" style="color: var(--f1-amber);"><?php echo $cars_at_limit_count; ?> <span style="font-size: 13px; color: #717188;">CARS</span></div>
    </div>
    <div class="hud-cell red">
        <div class="hud-label"><span>Penalty Incurred Cars</span> <span>🚩</span></div>
        <div class="hud-value" style="color: #ff4757;"><?php echo $cars_penalty_count; ?> <span style="font-size: 13px; color: #717188;">DROPS</span></div>
    </div>
    <div class="hud-cell">
        <div class="hud-label"><span>Session Scrutineering</span> <span>⚡</span></div>
        <div class="hud-value" style="color: var(--f1-cyan); font-size: 22px; line-height: 1.2;">ACTIVE MONACO GP</div>
    </div>
</div>

<!-- INTERACTIVE 1.6L V6 TURBO HYBRID PU ARCHITECTURE SCHEMATIC -->
<?php include 'pu_architecture_diagram.php'; ?>

<!-- Forms Command Deck -->
<div class="forms-deck">
    <!-- Form 1: Log Component Replacement -->
    <div class="telemetry-card cyan-trim">
        <div class="card-header-bar">
            <div class="card-title-text" style="color: #00d2be;">
                <span>⚙️</span> 01. Log PU / Gearbox Replacement
            </div>
            <span class="tag-pill">FIA COMPONENT CAP</span>
        </div>
        <form method="POST">
            <input type="hidden" name="action" value="log_component">
            <div class="input-matrix">
                <div class="form-unit full">
                    <label>Target Grid Chassis & Pilot <span class="req">*</span></label>
                    <select name="car_id" required>
                        <option value="">-- Select Chassis --</option>
                        <?php 
                        if ($cars_dropdown) {
                            $cars_dropdown->data_seek(0);
                            while($c = $cars_dropdown->fetch_assoc()): ?>
                                <option value="<?php echo $c['car_id']; ?>">
                                    <?php echo htmlspecialchars($c['team_name'] . ' — ' . $c['car_name'] . ' (' . ($c['driver_name'] ?: 'Unassigned') . ') [' . $c['chassis_number'] . ']'); ?>
                                </option>
                            <?php endwhile; 
                        } ?>
                    </select>
                </div>

                <div class="form-unit">
                    <label>Power Unit / Transmission Element <span class="req">*</span></label>
                    <select name="component_type" id="compTypeSelect" onchange="adjustCompLimit(this.value)" required>
                        <option value="Internal Combustion Engine (ICE)">Internal Combustion Engine (ICE)</option>
                        <option value="Turbocharger (TC)">Turbocharger (TC)</option>
                        <option value="MGU-H">MGU-H (Motor Gen Unit - Heat)</option>
                        <option value="MGU-K">MGU-K (Motor Gen Unit - Kinetic)</option>
                        <option value="Energy Store (ES)">Energy Store (ES)</option>
                        <option value="Control Electronics (CE)">Control Electronics (CE)</option>
                        <option value="Gearbox (GB)">Gearbox Assembly (GB)</option>
                    </select>
                </div>

                <div class="form-unit">
                    <label>Assigned Unit Number <span class="req">*</span></label>
                    <input type="number" name="units_used" id="unitNumInput" min="1" max="10" value="1" required>
                </div>

                <div class="form-unit">
                    <label>Allowable Season Quota</label>
                    <input type="number" name="max_limit" id="maxLimitInput" value="4" readonly>
                </div>

                <div class="form-unit">
                    <label>Calculated Grid Penalty</label>
                    <div id="penaltyWarningDisplay" style="font-family: 'Share Tech Mono', monospace; font-size: 12px; color: #2ecc71; padding: 10px 12px; background: #0a0a14; border: 1px solid #242436; border-radius: 6px;">
                        ● WITHIN QUOTA (No Penalty)
                    </div>
                </div>

                <button type="submit" class="btn-action-trigger btn-action-cyan">
                    <span>⚡</span> LOG COMPONENT REPLACEMENT
                </button>
            </div>
        </form>
    </div>

    <!-- Form 2: Issue Official Scrutineering Bulletin -->
    <div class="telemetry-card red-trim">
        <div class="card-header-bar">
            <div class="card-title-text" style="color: #ff4757;">
                <span>📢</span> 02. Publish Official Scrutineering Bulletin
            </div>
            <span class="tag-pill">FIA DELEGATE RELEASE</span>
        </div>
        <form method="POST">
            <input type="hidden" name="action" value="publish_bulletin">
            <div class="input-matrix">
                <div class="form-unit">
                    <label>Document Reference <span class="req">*</span></label>
                    <input type="text" name="document_no" value="DOC-<?php echo rand(40, 99); ?>-FIA" required>
                </div>
                <div class="form-unit">
                    <label>Grand Prix Event <span class="req">*</span></label>
                    <input type="text" name="grand_prix_name" value="Monaco Grand Prix 2026" required>
                </div>
                <div class="form-unit">
                    <label>Session Phase <span class="req">*</span></label>
                    <select name="session_type" required>
                        <option value="FP1">Free Practice 1</option>
                        <option value="FP2">Free Practice 2</option>
                        <option value="FP3">Free Practice 3</option>
                        <option value="Qualifying">Qualifying</option>
                        <option value="Sprint">Sprint Race</option>
                        <option value="Race">Grand Prix Race</option>
                        <option value="Post-Race Scrutineering">Post-Race Scrutineering</option>
                    </select>
                </div>
                <div class="form-unit">
                    <label>Publication Status <span class="req">*</span></label>
                    <select name="status" required>
                        <option value="Official">Official Bulletin</option>
                        <option value="Pending Sign-off">Pending Sign-off</option>
                        <option value="Amended">Amended Notice</option>
                    </select>
                </div>
                <div class="form-unit full">
                    <label>Official Bulletin Summary & Findings <span class="req">*</span></label>
                    <textarea name="summary_notes" rows="3" placeholder="Technical Delegate findings, physical seals, aerodynamic tests, component allocations, or parc fermé observations..." required></textarea>
                </div>
                <button type="submit" class="btn-action-trigger btn-action-red">
                    <span>📑</span> ISSUE & DISTRIBUTE OFFICIAL BULLETIN
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Power Unit & Gearbox Quota Matrix -->
<div class="table-console">
    <div class="card-header-bar">
        <div class="card-title-text" style="color: #ffffff;">
            <span>📊</span> Power Unit & Transmission Season Quota Matrix
        </div>
        <span class="tag-pill">2026 FIA CAP LIMITS</span>
    </div>

    <div class="quota-card-grid">
        <?php if (empty($grid_matrix)): ?>
            <div style="grid-column: 1 / -1; text-align: center; color: #717188; padding: 30px;">
                No component allocations registered yet. Log an allocation above to populate the matrix.
            </div>
        <?php else: ?>
            <?php foreach ($grid_matrix as $cid => $car): 
                $has_car_penalty = false;
                foreach ($car['components'] as $cmp) {
                    if ($cmp['penalty']) $has_car_penalty = true;
                }
            ?>
            <div class="quota-car-card <?php echo $has_car_penalty ? 'has-penalty' : ''; ?>">
                <div class="car-card-head">
                    <div>
                        <div style="font-weight: 700; color: #ffffff; font-size: 14px;"><?php echo htmlspecialchars($car['driver_name'] ?: 'Pilot Unassigned'); ?></div>
                        <div style="font-size: 11px; color: #8c8c9e; margin-top: 1px;"><?php echo htmlspecialchars($car['team_name']); ?></div>
                    </div>
                    <div>
                        <span class="chassis-tag"><?php echo htmlspecialchars($car['chassis_number']); ?></span>
                        <?php if ($has_car_penalty): ?>
                            <span class="penalty-warning-tag">GRID PENALTY</span>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="comp-bar-list">
                    <?php 
                    $std_comps = [
                        'Internal Combustion Engine (ICE)' => 'ICE',
                        'Turbocharger (TC)' => 'TC',
                        'MGU-H' => 'MGU-H',
                        'MGU-K' => 'MGU-K',
                        'Energy Store (ES)' => 'ES',
                        'Control Electronics (CE)' => 'CE',
                        'Gearbox (GB)' => 'GB'
                    ];
                    foreach ($std_comps as $full_name => $short_name): 
                        $cdata = $car['components'][$full_name] ?? ['units_used' => 1, 'max_limit' => ($short_name === 'ES' || $short_name === 'CE' ? 2 : 4), 'penalty' => 0];
                        $used = $cdata['units_used'];
                        $limit = $cdata['max_limit'];
                        $pct = min(100, round(($used / $limit) * 100));
                        $fill_cls = 'green';
                        if ($used == $limit) $fill_cls = 'amber';
                        if ($used > $limit) $fill_cls = 'red';
                    ?>
                    <div class="comp-bar-item">
                        <div class="comp-meta">
                            <span><?php echo $short_name; ?></span>
                            <span style="font-weight: 700; color: <?php echo ($used > $limit) ? '#ff5252' : ($used == $limit ? '#ffb703' : '#d1d1e0'); ?>;">
                                <?php echo $used; ?> / <?php echo $limit; ?> <?php echo ($used > $limit) ? '(!)' : ''; ?>
                            </span>
                        </div>
                        <div class="progress-track">
                            <div class="progress-fill <?php echo $fill_cls; ?>" style="width: <?php echo $pct; ?>%;"></div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<!-- Official Scrutineering Bulletins Archive -->
<div class="table-console">
    <div class="card-header-bar">
        <div class="card-title-text" style="color: #ffffff;">
            <span>📋</span> Official Technical Bulletins Archive
        </div>
        <span class="tag-pill"><?php echo $total_bulletins; ?> PUBLISHED BULLETINS</span>
    </div>

    <!-- Filter Ribbon -->
    <div class="filter-ribbon">
        <div class="filter-cluster">
            <button type="button" class="filter-tab-btn active" data-filter="all">All Documents (<?php echo $total_bulletins; ?>)</button>
            <button type="button" class="filter-tab-btn" data-filter="Official">Official</button>
            <button type="button" class="filter-tab-btn" data-filter="Pending Sign-off">Pending Sign-off</button>
            <button type="button" class="filter-tab-btn" data-filter="Qualifying">Qualifying</button>
            <button type="button" class="filter-tab-btn" data-filter="Race">Race</button>
        </div>
        <div class="search-field-wrap">
            <span style="color: #717188; font-size: 12px; margin-right: 4px;">🔎</span>
            <input type="text" id="filterSearch" placeholder="Search doc #, Grand Prix, findings...">
        </div>
    </div>

    <!-- Table -->
    <div class="table-responsive">
        <table>
            <thead>
                <tr>
                    <th>Document No</th>
                    <th>Grand Prix Event</th>
                    <th>Session Phase</th>
                    <th>Status</th>
                    <th>Summary Findings</th>
                    <th>Published Date</th>
                    <th>Delegate</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($all_bulletins)): ?>
                    <tr>
                        <td colspan="8" style="text-align: center; padding: 40px; color: #717188;">
                            No technical bulletins published yet.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($all_bulletins as $b): 
                        $status_cls = (strcasecmp($b['status'], 'Official') === 0) ? 'official' : 'pending';
                    ?>
                    <tr class="bulletin-row" data-status="<?php echo htmlspecialchars($b['status']); ?>" data-session="<?php echo htmlspecialchars($b['session_type']); ?>">
                        <td>
                            <span class="doc-badge"><?php echo htmlspecialchars($b['document_no']); ?></span>
                        </td>
                        <td>
                            <div style="font-weight: 700; color: #ffffff;"><?php echo htmlspecialchars($b['grand_prix_name']); ?></div>
                        </td>
                        <td>
                            <span style="font-family: 'Share Tech Mono', monospace; font-size: 11px; color: #00d2be;">
                                <?php echo htmlspecialchars($b['session_type']); ?>
                            </span>
                        </td>
                        <td>
                            <span class="status-chip <?php echo $status_cls; ?>">
                                ● <?php echo htmlspecialchars($b['status']); ?>
                            </span>
                        </td>
                        <td>
                            <div style="font-size: 12px; color: #d1d1e0; max-width: 260px; line-height: 1.4;">
                                <?php echo htmlspecialchars($b['summary_notes']); ?>
                            </div>
                        </td>
                        <td>
                            <div style="font-family: 'Share Tech Mono', monospace; font-size: 11px; color: #8c8c9e;">
                                <?php echo date('M d, Y H:i', strtotime($b['published_at'])); ?>
                            </div>
                        </td>
                        <td>
                            <span style="font-size: 12px; color: #a0a0b8;">
                                <?php echo htmlspecialchars($b['delegate_name'] ?? 'Jo Bauer (FIA)'); ?>
                            </span>
                        </td>
                        <td>
                            <div style="display: flex; gap: 8px;">
                                <button type="button" class="btn-view-doc" onclick='openOfficialDocModal(<?php echo json_encode($b, JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_HEX_AMP); ?>)'>
                                    📄 Print / PDF View
                                </button>
                                <form method="POST" onsubmit="return confirm('Delete technical bulletin <?php echo $b['document_no']; ?>?');" style="display:inline;">
                                    <input type="hidden" name="action" value="delete_bulletin">
                                    <input type="hidden" name="bulletin_id" value="<?php echo $b['bulletin_id']; ?>">
                                    <button type="submit" style="background: rgba(255,24,1,0.1); border: 1px solid rgba(255,24,1,0.4); color: #ff4757; padding: 6px 10px; border-radius: 4px; font-family: 'Orbitron', sans-serif; font-size: 10px; font-weight: 800; cursor: pointer;">✕</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- PRINTABLE OFFICIAL FIA TECHNICAL DELEGATE DOCUMENT MODAL -->
<div class="doc-modal-overlay" id="docModal">
    <div class="official-fia-paper">
        <div class="fia-paper-header">
            <div class="fia-logo-box">
                <span class="fia-crest">🏎️</span>
                <div>
                    <div class="fia-header-title">FEDERATION INTERNATIONALE DE L'AUTOMOBILE</div>
                    <div class="fia-header-sub">FIA Formula One World Championship — Technical Delegate Report</div>
                </div>
            </div>
            <div style="text-align: right;">
                <div style="font-family: monospace; font-size: 14px; font-weight: 700; color: #e10600;" id="modalDocRef">DOC-00-FIA</div>
                <div style="font-size: 11px; color: #666666;" id="modalDocDate">DATE: 2026</div>
            </div>
        </div>

        <table class="doc-meta-table">
            <tr>
                <td class="doc-meta-label">From:</td>
                <td>The FIA Formula One Technical Delegate</td>
                <td class="doc-meta-label">Document:</td>
                <td id="tableDocNo">DOC-00</td>
            </tr>
            <tr>
                <td class="doc-meta-label">To:</td>
                <td>All Teams, All Officials</td>
                <td class="doc-meta-label">Date / Time:</td>
                <td id="tableDocTime">Live Session</td>
            </tr>
            <tr>
                <td class="doc-meta-label">Grand Prix:</td>
                <td id="tableDocGP" style="font-weight: 700;">Monaco Grand Prix</td>
                <td class="doc-meta-label">Session:</td>
                <td id="tableDocSession" style="font-weight: 700; color: #e10600;">Qualifying</td>
            </tr>
            <tr>
                <td class="doc-meta-label">Status:</td>
                <td id="tableDocStatus" colspan="3" style="font-weight: 700; text-transform: uppercase;">OFFICIAL FIA SCRUTINEERING BULLETIN</td>
            </tr>
        </table>

        <div style="font-weight: 700; text-transform: uppercase; font-size: 12px; color: #444444; margin-bottom: 8px;">
            TECHNICAL DELEGATE REPORT & VERIFICATION FINDINGS:
        </div>

        <div class="doc-body-content" id="modalDocBody">
            Report content loading...
        </div>

        <div class="delegate-sign-box">
            <div class="signature-block">
                <div style="font-family: 'Brush Script MT', cursive, sans-serif; font-size: 26px; color: #111111; margin-bottom: 4px;" id="modalSignDelegate">
                    Jo Bauer
                </div>
                <div class="sign-title">FIA Formula One Technical Delegate</div>
                <div style="font-size: 11px; color: #666666;">Fédération Internationale de l'Automobile</div>
            </div>
        </div>

        <div class="modal-action-bar">
            <button type="button" class="btn-print-doc" onclick="window.print()">
                🖨️ Print / Save as PDF
            </button>
            <button type="button" class="btn-close-paper" onclick="closeDocModal()">
                Close
            </button>
        </div>
    </div>
</div>

<script>
// Filter Ribbon & Realtime Search
document.addEventListener('DOMContentLoaded', () => {
    const filterTabs = document.querySelectorAll('.filter-tab-btn');
    const searchField = document.getElementById('filterSearch');
    const rows = document.querySelectorAll('.bulletin-row');

    let currentFilter = 'all';

    function filterBulletins() {
        const query = (searchField.value || '').toLowerCase().trim();

        rows.forEach(row => {
            const status = row.getAttribute('data-status');
            const session = row.getAttribute('data-session');
            const text = row.textContent.toLowerCase();

            let matchesTab = (currentFilter === 'all') || 
                             (status === currentFilter) || 
                             (session === currentFilter);

            let matchesSearch = text.includes(query);

            if (matchesTab && matchesSearch) {
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
            filterBulletins();
        });
    });

    searchField.addEventListener('input', filterBulletins);

    // Initial check for component limit calculation
    document.getElementById('unitNumInput').addEventListener('input', calculatePenaltyWarning);
});

function adjustCompLimit(type) {
    const limitInput = document.getElementById('maxLimitInput');
    if (type === 'Energy Store (ES)' || type === 'Control Electronics (CE)') {
        limitInput.value = 2;
    } else {
        limitInput.value = 4;
    }
    calculatePenaltyWarning();
}

function calculatePenaltyWarning() {
    const units = parseInt(document.getElementById('unitNumInput').value || 1);
    const limit = parseInt(document.getElementById('maxLimitInput').value || 4);
    const display = document.getElementById('penaltyWarningDisplay');

    if (units > limit) {
        display.innerHTML = '⚠️ GRID DROP PENALTY: ' + units + '/' + limit + ' (10-Place Drop)';
        display.style.color = '#ff4757';
        display.style.borderColor = '#ff1801';
        display.style.background = 'rgba(255,24,1,0.1)';
    } else if (units === limit) {
        display.innerHTML = '● AT ALLOCATION LIMIT: ' + units + '/' + limit + ' (Next triggers penalty)';
        display.style.color = '#ffb703';
        display.style.borderColor = '#ffb703';
        display.style.background = 'rgba(255,183,3,0.1)';
    } else {
        display.innerHTML = '● WITHIN QUOTA: ' + units + '/' + limit + ' (Compliant)';
        display.style.color = '#2ecc71';
        display.style.borderColor = '#2ecc71';
        display.style.background = '#0a0a14';
    }
}

// Modal handling for official FIA paper view
function openOfficialDocModal(data) {
    document.getElementById('modalDocRef').textContent = data.document_no;
    document.getElementById('tableDocNo').textContent = data.document_no;
    document.getElementById('modalDocDate').textContent = 'PUBLISHED: ' + data.published_at;
    document.getElementById('tableDocTime').textContent = data.published_at;
    document.getElementById('tableDocGP').textContent = data.grand_prix_name;
    document.getElementById('tableDocSession').textContent = data.session_type;
    document.getElementById('tableDocStatus').textContent = data.status + ' TECHNICAL BULLETIN';
    document.getElementById('modalDocBody').textContent = data.summary_notes;
    document.getElementById('modalSignDelegate').textContent = data.delegate_name || 'Jo Bauer';

    document.getElementById('docModal').classList.add('active');
}

function closeDocModal() {
    document.getElementById('docModal').classList.remove('active');
}

document.getElementById('docModal').addEventListener('click', (e) => {
    if (e.target.id === 'docModal') {
        closeDocModal();
    }
});
</script>

</body>
</html>
