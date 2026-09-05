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
check_role_access(['steward']);

// Ensure tables exist and have initial schema and data
$conn->query("CREATE TABLE IF NOT EXISTS fia_regulations (
    regulation_id INT AUTO_INCREMENT PRIMARY KEY,
    article_code VARCHAR(50) NOT NULL,
    title VARCHAR(255) NOT NULL,
    category VARCHAR(50) DEFAULT 'Sporting',
    description TEXT,
    penalty_guideline VARCHAR(150) DEFAULT 'Time Penalty / Grid Drop',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

$conn->query("CREATE TABLE IF NOT EXISTS steward_infringements (
    infringement_id INT AUTO_INCREMENT PRIMARY KEY,
    case_number VARCHAR(50) NOT NULL UNIQUE,
    driver_id INT NULL,
    car_id INT NULL,
    regulation_id INT NOT NULL,
    session_name VARCHAR(100) DEFAULT 'Race',
    lap_or_turn VARCHAR(100) DEFAULT NULL,
    incident_description TEXT NOT NULL,
    penalty_type VARCHAR(100) DEFAULT 'Under Investigation',
    penalty_points INT DEFAULT 0,
    fine_amount DECIMAL(10,2) DEFAULT 0.00,
    status VARCHAR(50) DEFAULT 'Under Investigation',
    steward_notes TEXT NULL,
    steward_id INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (regulation_id) REFERENCES fia_regulations(regulation_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

// Seed regulations if empty
$reg_check = $conn->query("SELECT COUNT(*) as cnt FROM fia_regulations");
if ($reg_check && $reg_check->fetch_assoc()['cnt'] == 0) {
    $conn->query("INSERT INTO fia_regulations (article_code, title, category, description, penalty_guideline) VALUES
    ('ISC App. L, Ch. IV, Art. 2(c)', 'Causing an Avoidable Collision', 'Driving Conduct', 'Causing an avoidable collision or forcing another competitor off the racing surface.', '10-Second Time Penalty + 2 Penalty Points'),
    ('Art 33.4 Sporting Regs', 'Impeding Competitor in Qualifying', 'Sporting', 'Unnecessarily impeding another driver on a hot lap during qualifying session.', '3-Place Grid Drop / Official Reprimand'),
    ('Art 3.5.1 Technical Regs', 'Aerodynamic Deflection Over Limit', 'Technical', 'Rear wing slot gap or aerodynamic element flexing beyond 2.0mm threshold.', 'Disqualification from Session / Parc Fermé Start'),
    ('Art 34.14 Sporting Regs', 'Pit Lane Speeding Violation', 'Pit Lane', 'Exceeding the designated 80.0 km/h pit lane speed limit.', '€1,000 Fine + 5-Second Time Penalty'),
    ('Art 55.7 Sporting Regs', 'Safety Car Delta Infringement', 'Safety Car', 'Failing to maintain minimum delta time during Virtual / Full Safety Car period.', '5-Second Time Penalty + 1 Penalty Point'),
    ('Art 12.2.1.i ISC', 'Failure to Respect Double Yellow Flags', 'Safety', 'Not significantly reducing speed and being prepared to stop under double yellow flags.', '10-Place Grid Drop + 3 Penalty Points'),
    ('Art 4.1 Technical Regs', 'Under Minimum Weight Limit', 'Technical', 'Car and driver combined mass below 798.0 kg at post-race scrutineering.', 'Disqualification from Race Results'),
    ('Art 33.3 Sporting Regs', 'Persistent Track Limits Abuse', 'Sporting', 'Exceeding track limits on 4 or more occasions without justifiable reason.', '5-Second Time Penalty')");
}

$msg = "";
$msg_type = "success";

// 1. CREATE / ASSIGN NEW INFRINGEMENT & PENALTY (Steward & Admin only)
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action']) && $_POST['action'] === 'create_infringement') {
    check_role_access(['steward']);
    $case_number = trim($_POST['case_number'] ?? '');
    if (empty($case_number)) {
        $case_number = "DOC-" . rand(40, 99) . "-FIA";
    }
    
    $car_id = !empty($_POST['car_id']) ? intval($_POST['car_id']) : null;
    $driver_id = !empty($_POST['driver_id']) ? intval($_POST['driver_id']) : null;
    $regulation_id = intval($_POST['regulation_id']);
    $session_name = trim($_POST['session_name'] ?? 'Race');
    $lap_or_turn = trim($_POST['lap_or_turn'] ?? '');
    $incident_description = trim($_POST['incident_description'] ?? '');
    $penalty_type = trim($_POST['penalty_type'] ?? 'Under Investigation');
    $penalty_points = intval($_POST['penalty_points'] ?? 0);
    $fine_amount = floatval($_POST['fine_amount'] ?? 0.00);
    $status = trim($_POST['status'] ?? 'Under Investigation');
    $steward_notes = trim($_POST['steward_notes'] ?? '');
    $steward_id = $_SESSION['user_id'] ?? null;

    if ($car_id && !$driver_id) {
        $d_lookup = $conn->query("SELECT driver_id FROM CARS WHERE car_id = $car_id");
        if ($d_lookup && $d_row = $d_lookup->fetch_assoc()) {
            $driver_id = $d_row['driver_id'];
        }
    }

    $stmt = $conn->prepare("INSERT INTO steward_infringements 
        (case_number, driver_id, car_id, regulation_id, session_name, lap_or_turn, incident_description, penalty_type, penalty_points, fine_amount, status, steward_notes, steward_id) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("siiissssidssi", 
        $case_number, $driver_id, $car_id, $regulation_id, $session_name, $lap_or_turn, 
        $incident_description, $penalty_type, $penalty_points, $fine_amount, $status, $steward_notes, $steward_id);

    if ($stmt->execute()) {
        $msg = "STEWARD RULING LODGED: Document " . htmlspecialchars($case_number) . " officially registered in FIA records.";
        $msg_type = "success";
    } else {
        $msg = "RULING SUBMISSION ERROR: " . $conn->error;
        $msg_type = "error";
    }
}

// 2. UPDATE PENALTY STATUS & VERDICT (Steward & Admin only)
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action']) && $_POST['action'] === 'update_penalty_status') {
    check_role_access(['steward']);
    $infringement_id = intval($_POST['infringement_id']);
    $status = trim($_POST['status']);
    $penalty_type = trim($_POST['penalty_type']);
    $penalty_points = intval($_POST['penalty_points'] ?? 0);
    $fine_amount = floatval($_POST['fine_amount'] ?? 0.00);
    $steward_notes = trim($_POST['steward_notes'] ?? '');

    $upd_stmt = $conn->prepare("UPDATE steward_infringements 
        SET status = ?, penalty_type = ?, penalty_points = ?, fine_amount = ?, steward_notes = ? 
        WHERE infringement_id = ?");
    $upd_stmt->bind_param("ssidsi", $status, $penalty_type, $penalty_points, $fine_amount, $steward_notes, $infringement_id);

    if ($upd_stmt->execute()) {
        $msg = "CASE VERDICT MODIFIED: Case #{$infringement_id} status updated to '{$status}'.";
        $msg_type = "success";
    } else {
        $msg = "VERDICT UPDATE ERROR: " . $conn->error;
        $msg_type = "error";
    }
}

// Query dropdown options
$cars_dropdown = $conn->query("SELECT c.car_id, c.car_name, c.chassis_number, t.team_name, d.driver_id, d.full_name 
                               FROM CARS c 
                               JOIN TEAMS t ON c.team_id = t.team_id 
                               LEFT JOIN DRIVERS d ON c.driver_id = d.driver_id 
                               ORDER BY t.team_name ASC, c.car_name ASC");

$drivers_dropdown = $conn->query("SELECT driver_id, full_name, license_number FROM DRIVERS ORDER BY full_name ASC");
$regulations_dropdown = $conn->query("SELECT regulation_id, article_code, title, category, penalty_guideline FROM fia_regulations ORDER BY category ASC, article_code ASC");

// Query all cases with comprehensive joins
$cases_query = "SELECT i.*, 
                       r.article_code, r.title as regulation_title, r.category as regulation_category, r.description as regulation_desc,
                       c.car_name, c.chassis_number,
                       t.team_name,
                       d.full_name as driver_name, d.license_number,
                       u.full_name as steward_name
                FROM steward_infringements i
                JOIN fia_regulations r ON i.regulation_id = r.regulation_id
                LEFT JOIN CARS c ON i.car_id = c.car_id
                LEFT JOIN TEAMS t ON c.team_id = t.team_id
                LEFT JOIN DRIVERS d ON i.driver_id = d.driver_id
                LEFT JOIN USERS u ON i.steward_id = u.user_id
                ORDER BY i.infringement_id DESC";
$cases_res = $conn->query($cases_query);

// Summary metrics
$total_cases = 0;
$active_investigations = 0;
$hearings_count = 0;
$penalties_applied = 0;
$total_points_issued = 0;

$all_cases_data = [];
if ($cases_res) {
    while ($row = $cases_res->fetch_assoc()) {
        $all_cases_data[] = $row;
        $total_cases++;
        if ($row['status'] === 'Under Investigation') $active_investigations++;
        if ($row['status'] === 'Steward Hearing') $hearings_count++;
        if ($row['status'] === 'Penalty Applied') $penalties_applied++;
        $total_points_issued += intval($row['penalty_points']);
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>FSMS | FIA Stewards Room & Penalties</title>
    <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@500;700;800;900&family=Share+Tech+Mono&family=Titillium+Web:wght@300;400;600;700;900&display=swap" rel="stylesheet">
    <style>
        :root {
            --f1-red: #ff1801;
            --f1-red-glow: rgba(255, 24, 1, 0.4);
            --f1-cyan: #00d2be;
            --f1-cyan-glow: rgba(0, 210, 190, 0.4);
            --f1-amber: #ffb703;
            --f1-amber-glow: rgba(255, 183, 3, 0.4);
            --f1-purple: #9d4edd;
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

        /* Scanlines Overlay */
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
        .ambient-glow-red {
            position: absolute;
            width: 700px;
            height: 700px;
            background: radial-gradient(circle, rgba(255, 24, 1, 0.16) 0%, transparent 65%);
            top: -150px;
            right: -100px;
            pointer-events: none;
            z-index: 0;
            filter: blur(50px);
        }
        .ambient-glow-amber {
            position: absolute;
            width: 600px;
            height: 600px;
            background: radial-gradient(circle, rgba(255, 183, 3, 0.12) 0%, transparent 65%);
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
            border-left: 4px solid var(--f1-red);
            border-radius: 8px;
            padding: 12px 20px;
            margin-bottom: 24px;
            font-family: 'Share Tech Mono', monospace;
            font-size: 12px;
            box-shadow: 0 0 25px rgba(255, 24, 1, 0.12);
            position: relative;
            z-index: 10;
        }
        .telemetry-tag {
            display: flex;
            align-items: center;
            gap: 10px;
            color: #ff4757;
            font-weight: 700;
            letter-spacing: 1.5px;
        }
        .pulse-core {
            width: 8px;
            height: 8px;
            background: var(--f1-red);
            border-radius: 50%;
            box-shadow: 0 0 10px var(--f1-red);
            animation: pulseDot 1s infinite alternate;
        }
        @keyframes pulseDot { 0% { opacity: 0.4; } 100% { opacity: 1; transform: scale(1.2); } }

        /* HUD Metric Cards */
        .hud-matrix {
            display: grid;
            grid-template-columns: repeat(5, 1fr);
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
            background: linear-gradient(90deg, var(--f1-red), transparent);
        }
        .hud-cell.amber::after {
            background: linear-gradient(90deg, var(--f1-amber), transparent);
        }
        .hud-cell.cyan::after {
            background: linear-gradient(90deg, var(--f1-cyan), transparent);
        }
        .hud-cell:hover {
            transform: translateY(-3px);
            border-color: rgba(255, 24, 1, 0.4);
            box-shadow: 0 12px 35px rgba(255, 24, 1, 0.2);
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
            grid-template-columns: 1.2fr 0.8fr;
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
        .telemetry-card.red-trim { border-top: 3px solid #ff1801; }
        .telemetry-card.amber-trim { border-top: 3px solid var(--f1-amber); }

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
        .form-unit label span.req { color: #ff4757; }
        
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
            border-color: #ff1801;
            box-shadow: 0 0 12px rgba(255, 24, 1, 0.35);
            background: #0e0e1a;
        }
        .telemetry-card.amber-trim input:focus, 
        .telemetry-card.amber-trim select:focus,
        .telemetry-card.amber-trim textarea:focus {
            border-color: var(--f1-amber);
            box-shadow: 0 0 12px rgba(255, 183, 3, 0.35);
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
        .btn-action-red {
            background: linear-gradient(135deg, #ff1801 0%, #a80000 100%);
            color: #ffffff;
            box-shadow: 0 4px 15px rgba(255, 24, 1, 0.4);
        }
        .btn-action-red:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 25px rgba(255, 24, 1, 0.7);
        }
        .btn-action-amber {
            background: linear-gradient(135deg, #ffb703 0%, #b87b00 100%);
            color: #07070d;
            box-shadow: 0 4px 15px rgba(255, 183, 3, 0.4);
        }
        .btn-action-amber:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 25px rgba(255, 183, 3, 0.7);
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

        /* Filter Controls */
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
            background: rgba(255, 24, 1, 0.15);
            border-color: var(--f1-red);
            color: #ffffff;
            box-shadow: 0 0 10px rgba(255, 24, 1, 0.3);
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
        .phase-badge {
            font-family: 'Share Tech Mono', monospace;
            font-size: 10px;
            color: #8c8c9e;
            display: block;
            margin-top: 4px;
        }

        .chassis-badge {
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

        .verdict-pill {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 4px;
            font-family: 'Share Tech Mono', monospace;
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            background: rgba(255, 24, 1, 0.15);
            color: #ff4757;
            border: 1px solid rgba(255, 24, 1, 0.4);
        }
        .verdict-pill.passive {
            background: rgba(140, 140, 158, 0.12);
            color: #8c8c9e;
            border-color: rgba(140, 140, 158, 0.3);
        }

        .status-pill {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 4px 10px;
            border-radius: 4px;
            font-family: 'Share Tech Mono', monospace;
            font-size: 10px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 1px;
            white-space: nowrap;
        }
        .status-investigating {
            background: rgba(255, 183, 3, 0.12);
            border: 1px solid rgba(255, 183, 3, 0.4);
            color: #ffb703;
        }
        .status-hearing {
            background: rgba(0, 210, 190, 0.12);
            border: 1px solid rgba(0, 210, 190, 0.4);
            color: #00d2be;
        }
        .status-applied {
            background: rgba(255, 24, 1, 0.12);
            border: 1px solid rgba(255, 24, 1, 0.4);
            color: #ff4757;
        }
        .status-closed {
            background: rgba(46, 204, 113, 0.12);
            border: 1px solid rgba(46, 204, 113, 0.4);
            color: #2ecc71;
        }

        .btn-telemetry-edit {
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
        }
        .btn-telemetry-edit:hover {
            background: #00d2be;
            color: #07070d;
            box-shadow: 0 0 12px rgba(0, 210, 190, 0.6);
        }

        /* Edit Modal Window */
        .modal-telemetry-overlay {
            display: none;
            position: fixed;
            top: 0; left: 0; width: 100%; height: 100%;
            background: rgba(4, 4, 8, 0.88);
            backdrop-filter: blur(10px);
            z-index: 2000;
            justify-content: center;
            align-items: center;
            padding: 20px;
        }
        .modal-telemetry-overlay.active { display: flex; }
        .modal-telemetry-box {
            background: linear-gradient(135deg, rgba(22, 22, 34, 0.98) 0%, rgba(12, 12, 20, 0.99) 100%);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-top: 3px solid var(--f1-cyan);
            border-radius: 12px;
            width: 100%;
            max-width: 650px;
            max-height: 90vh;
            overflow-y: auto;
            padding: 28px;
            box-shadow: 0 20px 50px rgba(0,0,0,0.9), 0 0 30px rgba(0, 210, 190, 0.15);
            animation: modalFade 0.25s ease-out;
        }
        @keyframes modalFade {
            from { transform: translateY(-10px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }
        .modal-head {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding-bottom: 14px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.08);
            margin-bottom: 20px;
        }
        .modal-title {
            font-family: 'Orbitron', sans-serif;
            font-size: 15px;
            font-weight: 800;
            color: #ffffff;
            letter-spacing: 1.5px;
            text-transform: uppercase;
        }
        .btn-modal-close {
            background: transparent;
            border: none;
            color: #8c8c9e;
            font-size: 22px;
            cursor: pointer;
        }
        .btn-modal-close:hover { color: #ffffff; }

        @media (max-width: 1100px) {
            .hud-matrix { grid-template-columns: repeat(3, 1fr); }
            .forms-deck { grid-template-columns: 1fr; }
        }
        @media (max-width: 768px) {
            .hud-matrix { grid-template-columns: 1fr 1fr; }
            .input-matrix { grid-template-columns: 1fr; }
            .filter-ribbon { flex-direction: column; align-items: stretch; }
        }
    </style>
</head>
<body>

<div class="ambient-glow-red"></div>
<div class="ambient-glow-amber"></div>

<?php include 'navbar.php'; ?>

<!-- Top Live Telemetry Ribbon -->
<div class="telemetry-ribbon">
    <div class="telemetry-tag">
        <div class="pulse-core"></div>
        <span>FIA STEWARDS RACE CONTROL // HEARING CHAMBER ACTIVE</span>
    </div>
    <div style="color: #717188;">
        STEWARD PROTOCOL: <span style="color: var(--f1-amber); font-weight:700;">FIA 2026 SPORTING & TECHNICAL REGS</span>
    </div>
</div>

<?php if($msg): ?>
    <div class="alert-banner <?php echo $msg_type; ?>"><?php echo htmlspecialchars($msg); ?></div>
<?php endif; ?>

<!-- Telemetry Metric HUD Matrix -->
<div class="hud-matrix">
    <div class="hud-cell">
        <div class="hud-label"><span>Total Cases</span> <span>📑</span></div>
        <div class="hud-value"><?php echo $total_cases; ?></div>
    </div>
    <div class="hud-cell amber">
        <div class="hud-label"><span>Under Investigation</span> <span>🔍</span></div>
        <div class="hud-value" style="color: var(--f1-amber);"><?php echo $active_investigations; ?></div>
    </div>
    <div class="hud-cell cyan">
        <div class="hud-label"><span>Steward Hearings</span> <span>⚖️</span></div>
        <div class="hud-value" style="color: var(--f1-cyan);"><?php echo $hearings_count; ?></div>
    </div>
    <div class="hud-cell">
        <div class="hud-label"><span>Penalties Applied</span> <span>🚩</span></div>
        <div class="hud-value" style="color: #ff4757;"><?php echo $penalties_applied; ?></div>
    </div>
    <div class="hud-cell">
        <div class="hud-label"><span>Penalty Points</span> <span>⚠️</span></div>
        <div class="hud-value" style="color: #ff5252;"><?php echo $total_points_issued; ?> <span style="font-size: 13px; color: #717188;">PTS</span></div>
    </div>
</div>

<!-- DRIVER SUPERLICENSE PENALTY POINTS RADAR -->
<?php include 'driver_superlicense_tracker.php'; ?>

<!-- Forms Command Deck -->
<div class="forms-deck">
    <!-- Form 1: Lodge Infringement & Assign Penalty -->
    <div class="telemetry-card red-trim" id="lodgeCard">
        <div class="card-header-bar">
            <div class="card-title-text" style="color: #ff4757;">
                <span>⚖️</span> 01. Lodge Infringement & Ruling
            </div>
            <span class="tag-pill">FIA STEWARDS RIG</span>
        </div>
        <form method="POST">
            <input type="hidden" name="action" value="create_infringement">
            <div class="input-matrix">
                <div class="form-unit">
                    <label>Document Ref <span class="req">*</span></label>
                    <input type="text" name="case_number" value="DOC-<?php echo rand(45, 99); ?>-FIA" required>
                </div>
                <div class="form-unit">
                    <label>Target Chassis <span class="req">*</span></label>
                    <select name="car_id" required>
                        <option value="">-- Select Grid Vehicle --</option>
                        <?php 
                        if ($cars_dropdown) {
                            $cars_dropdown->data_seek(0);
                            while($c = $cars_dropdown->fetch_assoc()): ?>
                                <option value="<?php echo $c['car_id']; ?>">
                                    <?php echo htmlspecialchars($c['team_name'] . ' - ' . $c['car_name'] . ' [' . $c['chassis_number'] . ']'); ?>
                                </option>
                            <?php endwhile; 
                        } ?>
                    </select>
                </div>
                <div class="form-unit">
                    <label>Super License Pilot</label>
                    <select name="driver_id">
                        <option value="">-- Auto-assign or Select Pilot --</option>
                        <?php 
                        if ($drivers_dropdown) {
                            $drivers_dropdown->data_seek(0);
                            while($d = $drivers_dropdown->fetch_assoc()): ?>
                                <option value="<?php echo $d['driver_id']; ?>">
                                    <?php echo htmlspecialchars($d['full_name'] . ' [' . $d['license_number'] . ']'); ?>
                                </option>
                            <?php endwhile; 
                        } ?>
                    </select>
                </div>
                <div class="form-unit">
                    <label>FIA Regulation Article <span class="req">*</span></label>
                    <select name="regulation_id" required>
                        <?php 
                        if ($regulations_dropdown) {
                            $regulations_dropdown->data_seek(0);
                            while($r = $regulations_dropdown->fetch_assoc()): ?>
                                <option value="<?php echo $r['regulation_id']; ?>">
                                    <?php echo htmlspecialchars('[' . $r['category'] . '] ' . $r['article_code'] . ' - ' . $r['title']); ?>
                                </option>
                            <?php endwhile; 
                        } ?>
                    </select>
                </div>
                <div class="form-unit">
                    <label>Session Phase <span class="req">*</span></label>
                    <select name="session_name" required>
                        <option value="Race">Race</option>
                        <option value="Qualifying Q3">Qualifying Q3</option>
                        <option value="Qualifying Q2">Qualifying Q2</option>
                        <option value="Qualifying Q1">Qualifying Q1</option>
                        <option value="Sprint Race">Sprint Race</option>
                        <option value="Sprint Shootout">Sprint Shootout</option>
                        <option value="Free Practice 1">Free Practice 1</option>
                        <option value="Free Practice 2">Free Practice 2</option>
                        <option value="Free Practice 3">Free Practice 3</option>
                        <option value="Post-Race Scrutineering">Post-Race Scrutineering</option>
                    </select>
                </div>
                <div class="form-unit">
                    <label>Track Location / Lap <span class="req">*</span></label>
                    <input type="text" name="lap_or_turn" placeholder="e.g. Lap 42 - Turn 1 (Sainte Devote)" required>
                </div>
                <div class="form-unit">
                    <label>Assigned Penalty Verdict <span class="req">*</span></label>
                    <select name="penalty_type" required>
                        <option value="Under Investigation">Under Investigation</option>
                        <option value="5-Second Time Penalty">5-Second Time Penalty</option>
                        <option value="10-Second Time Penalty">10-Second Time Penalty</option>
                        <option value="Drive-Through Penalty">Drive-Through Penalty</option>
                        <option value="10-Second Stop and Go Penalty">10-Second Stop and Go Penalty</option>
                        <option value="3-Place Grid Drop">3-Place Grid Drop</option>
                        <option value="5-Place Grid Drop">5-Place Grid Drop</option>
                        <option value="10-Place Grid Drop">10-Place Grid Drop</option>
                        <option value="Back of Grid Penalty">Back of Grid Penalty</option>
                        <option value="Pit Lane Start">Pit Lane Start</option>
                        <option value="Disqualification (DSQ)">Disqualification (DSQ)</option>
                        <option value="Official Reprimand">Official Reprimand</option>
                        <option value="€1,000 Fine">€1,000 Fine</option>
                        <option value="€5,000 Fine">€5,000 Fine</option>
                        <option value="€10,000 Fine">€10,000 Fine</option>
                        <option value="No Further Action">No Further Action</option>
                    </select>
                </div>
                <div class="form-unit">
                    <label>Penalty Points</label>
                    <select name="penalty_points">
                        <option value="0">0 Points</option>
                        <option value="1">1 Penalty Point</option>
                        <option value="2">2 Penalty Points</option>
                        <option value="3">3 Penalty Points</option>
                        <option value="4">4 Penalty Points</option>
                    </select>
                </div>
                <div class="form-unit">
                    <label>Monetary Fine (€ EUR)</label>
                    <input type="number" step="100" name="fine_amount" value="0.00">
                </div>
                <div class="form-unit">
                    <label>Initial Status <span class="req">*</span></label>
                    <select name="status" required>
                        <option value="Under Investigation">Under Investigation</option>
                        <option value="Steward Hearing">Steward Hearing</option>
                        <option value="Penalty Applied">Penalty Applied</option>
                        <option value="Closed">Closed</option>
                        <option value="Dismissed">Dismissed (No Action)</option>
                    </select>
                </div>
                <div class="form-unit full">
                    <label>Incident Evidence & Observations <span class="req">*</span></label>
                    <textarea name="incident_description" rows="2" placeholder="Telemetry analysis, track limits, or collision details..." required></textarea>
                </div>
                <div class="form-unit full">
                    <label>Official Steward Reason for Decision</label>
                    <textarea name="steward_notes" rows="2" placeholder="Judicial reasoning according to FIA International Sporting Code..."></textarea>
                </div>
                <button type="submit" class="btn-action-trigger btn-action-red">
                    <span>⚡</span> ENFORCE STEWARD RULING
                </button>
            </div>
        </form>
    </div>

    <!-- Form 2: Quick Case Status Update Console -->
    <div class="telemetry-card amber-trim">
        <div class="card-header-bar">
            <div class="card-title-text" style="color: var(--f1-amber);">
                <span>🛠️</span> 02. Quick Status & Verdict Update
            </div>
            <span class="tag-pill">LIVE DISPATCH</span>
        </div>
        <form method="POST">
            <input type="hidden" name="action" value="update_penalty_status">
            <div class="input-matrix">
                <div class="form-unit full">
                    <label>Select Registered Case <span class="req">*</span></label>
                    <select name="infringement_id" id="quickCaseSelect" required onchange="populateQuickUpdate(this.value)">
                        <option value="">-- Choose Active Case --</option>
                        <?php foreach ($all_cases_data as $c): ?>
                            <option value="<?php echo $c['infringement_id']; ?>" 
                                    data-status="<?php echo htmlspecialchars($c['status']); ?>"
                                    data-penalty="<?php echo htmlspecialchars($c['penalty_type']); ?>"
                                    data-points="<?php echo $c['penalty_points']; ?>"
                                    data-fine="<?php echo $c['fine_amount']; ?>"
                                    data-notes="<?php echo htmlspecialchars($c['steward_notes'] ?? ''); ?>">
                                <?php echo htmlspecialchars($c['case_number'] . ' | ' . ($c['driver_name'] ?: 'Unassigned') . ' (' . ($c['team_name'] ?: 'Grid') . ') - ' . $c['status']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-unit">
                    <label>Updated Case Status <span class="req">*</span></label>
                    <select name="status" id="quickStatus" required>
                        <option value="Under Investigation">Under Investigation</option>
                        <option value="Steward Hearing">Steward Hearing</option>
                        <option value="Penalty Applied">Penalty Applied</option>
                        <option value="Appealed">Appealed to FIA Tribunal</option>
                        <option value="Closed">Closed</option>
                        <option value="Dismissed">Dismissed (No Action)</option>
                    </select>
                </div>

                <div class="form-unit">
                    <label>Assigned Penalty Verdict <span class="req">*</span></label>
                    <select name="penalty_type" id="quickPenalty" required>
                        <option value="Under Investigation">Under Investigation</option>
                        <option value="5-Second Time Penalty">5-Second Time Penalty</option>
                        <option value="10-Second Time Penalty">10-Second Time Penalty</option>
                        <option value="Drive-Through Penalty">Drive-Through Penalty</option>
                        <option value="10-Second Stop and Go Penalty">10-Second Stop and Go Penalty</option>
                        <option value="3-Place Grid Drop">3-Place Grid Drop</option>
                        <option value="5-Place Grid Drop">5-Place Grid Drop</option>
                        <option value="10-Place Grid Drop">10-Place Grid Drop</option>
                        <option value="Back of Grid Penalty">Back of Grid Penalty</option>
                        <option value="Pit Lane Start">Pit Lane Start</option>
                        <option value="Disqualification (DSQ)">Disqualification (DSQ)</option>
                        <option value="Official Reprimand">Official Reprimand</option>
                        <option value="€1,000 Fine">€1,000 Fine</option>
                        <option value="€5,000 Fine">€5,000 Fine</option>
                        <option value="€10,000 Fine">€10,000 Fine</option>
                        <option value="No Further Action">No Further Action</option>
                    </select>
                </div>

                <div class="form-unit">
                    <label>Penalty Points</label>
                    <select name="penalty_points" id="quickPoints">
                        <option value="0">0 Points</option>
                        <option value="1">1 Penalty Point</option>
                        <option value="2">2 Penalty Points</option>
                        <option value="3">3 Penalty Points</option>
                        <option value="4">4 Penalty Points</option>
                    </select>
                </div>

                <div class="form-unit">
                    <label>Fine Amount (€)</label>
                    <input type="number" step="100" name="fine_amount" id="quickFine" value="0.00">
                </div>

                <div class="form-unit full">
                    <label>Decision Notes / Deliberations</label>
                    <textarea name="steward_notes" id="quickNotes" rows="3" placeholder="Hearing findings, telemetry review, and decision summary..."></textarea>
                </div>

                <button type="submit" class="btn-action-trigger btn-action-amber">
                    <span>🔄</span> UPDATE CASE VERDICT
                </button>
            </div>
        </form>
    </div>
</div>

<!-- INTERACTIVE GRAND PRIX TRACK INCIDENT & CORNER RADAR -->
<?php include 'track_incident_radar.php'; ?>

<!-- Active Cases Telemetry Table Console -->
<div class="table-console">
    <div class="card-header-bar">
        <div class="card-title-text" style="color: #ffffff;">
            <span>📋</span> Active Stewards Infringements & Judicial Registry
        </div>
        <span class="tag-pill"><?php echo $total_cases; ?> CASES FILED</span>
    </div>

    <!-- Filter Ribbon -->
    <div class="filter-ribbon">
        <div class="filter-cluster">
            <button type="button" class="filter-tab-btn active" data-filter="all">All Cases (<?php echo $total_cases; ?>)</button>
            <button type="button" class="filter-tab-btn" data-filter="Under Investigation">Investigations (<?php echo $active_investigations; ?>)</button>
            <button type="button" class="filter-tab-btn" data-filter="Steward Hearing">Hearings (<?php echo $hearings_count; ?>)</button>
            <button type="button" class="filter-tab-btn" data-filter="Penalty Applied">Penalties (<?php echo $penalties_applied; ?>)</button>
            <button type="button" class="filter-tab-btn" data-filter="Closed">Closed / Dismissed</button>
        </div>
        <div class="search-field-wrap">
            <span style="color: #717188; font-size: 12px; margin-right: 4px;">🔎</span>
            <input type="text" id="filterSearch" placeholder="Search case #, pilot, team, article...">
        </div>
    </div>

    <!-- FIA Styled Dark Table -->
    <div class="table-responsive">
        <table>
            <thead>
                <tr>
                    <th>Doc Ref</th>
                    <th>Competitor & Chassis</th>
                    <th>FIA Regulation</th>
                    <th>Incident Details</th>
                    <th>Assigned Penalty</th>
                    <th>Status</th>
                    <th>Steward Decision</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($all_cases_data)): ?>
                    <tr>
                        <td colspan="8" style="text-align: center; padding: 40px; color: #717188;">
                            No steward infringements currently registered in database.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($all_cases_data as $case): 
                        $status_cls = 'status-investigating';
                        if ($case['status'] === 'Steward Hearing') $status_cls = 'status-hearing';
                        elseif ($case['status'] === 'Penalty Applied') $status_cls = 'status-applied';
                        elseif ($case['status'] === 'Closed' || $case['status'] === 'Dismissed') $status_cls = 'status-closed';
                    ?>
                    <tr class="steward-row" data-status="<?php echo htmlspecialchars($case['status']); ?>">
                        <td>
                            <span class="doc-badge"><?php echo htmlspecialchars($case['case_number']); ?></span>
                            <span class="phase-badge"><?php echo htmlspecialchars($case['session_name']); ?></span>
                        </td>
                        <td>
                            <div style="font-weight: 700; color: #ffffff;"><?php echo htmlspecialchars($case['driver_name'] ?: 'Pilot Unassigned'); ?></div>
                            <div style="margin-top: 2px;">
                                <span class="chassis-badge"><?php echo htmlspecialchars($case['chassis_number'] ?: 'CHASSIS-N/A'); ?></span>
                                <span style="font-size: 11px; color: #717188; margin-left: 4px;"><?php echo htmlspecialchars($case['team_name'] ?: 'FIA Grid'); ?></span>
                            </div>
                        </td>
                        <td>
                            <span class="reg-chip"><?php echo htmlspecialchars($case['article_code']); ?></span>
                            <div style="font-size: 12px; color: #d1d1e0; max-width: 190px; line-height: 1.3;" title="<?php echo htmlspecialchars($case['regulation_desc']); ?>">
                                <?php echo htmlspecialchars($case['regulation_title']); ?>
                            </div>
                        </td>
                        <td>
                            <div style="max-width: 230px; font-size: 12px; line-height: 1.4; color: #b8b8c8;">
                                <?php echo htmlspecialchars($case['incident_description']); ?>
                            </div>
                            <?php if(!empty($case['lap_or_turn'])): ?>
                                <div style="font-size: 11px; color: var(--f1-amber); font-family: 'Share Tech Mono', monospace; margin-top: 3px;">
                                    📍 <?php echo htmlspecialchars($case['lap_or_turn']); ?>
                                </div>
                            <?php endif; ?>
                        </td>
                        <td>
                            <span class="verdict-pill <?php echo ($case['penalty_type'] === 'No Further Action' || $case['penalty_type'] === 'Under Investigation') ? 'passive' : ''; ?>">
                                <?php echo htmlspecialchars($case['penalty_type']); ?>
                            </span>
                            <div style="display: flex; gap: 6px; margin-top: 5px; font-family: 'Share Tech Mono', monospace; font-size: 10px;">
                                <?php if ($case['penalty_points'] > 0): ?>
                                    <span style="background: rgba(255,183,3,0.15); color: #ffb703; border: 1px solid rgba(255,183,3,0.3); padding: 1px 5px; border-radius: 3px;">
                                        +<?php echo $case['penalty_points']; ?> PTS
                                    </span>
                                <?php endif; ?>
                                <?php if ($case['fine_amount'] > 0): ?>
                                    <span style="background: rgba(0,210,190,0.15); color: #00d2be; border: 1px solid rgba(0,210,190,0.3); padding: 1px 5px; border-radius: 3px;">
                                        €<?php echo number_format($case['fine_amount']); ?>
                                    </span>
                                <?php endif; ?>
                            </div>
                        </td>
                        <td>
                            <span class="status-pill <?php echo $status_cls; ?>">
                                ● <?php echo htmlspecialchars($case['status']); ?>
                            </span>
                        </td>
                        <td>
                            <div style="font-size: 12px; color: #8c8c9e; max-width: 170px; line-height: 1.3;">
                                <?php echo htmlspecialchars($case['steward_notes'] ?: 'Awaiting steward verdict...'); ?>
                            </div>
                            <div style="font-size: 10px; color: #5a5a70; font-family: 'Share Tech Mono', monospace; margin-top: 3px;">
                                BY: <?php echo htmlspecialchars($case['steward_name'] ?: 'FIA PANEL'); ?>
                            </div>
                        </td>
                        <td>
                            <div style="display: flex; flex-direction: column; gap: 4px;">
                                <button type="button" class="btn-telemetry-edit" onclick='launchEditModal(<?php echo json_encode($case, JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_HEX_AMP); ?>)'>
                                    ✏️ EDIT
                                </button>
                                <a href="export_document.php?doc_type=decision&id=<?php echo $case['infringement_id']; ?>" target="_blank" class="btn-telemetry-edit" style="text-decoration:none; display:inline-flex; align-items:center; justify-content:center; gap:4px; border-color:#00d2be; color:#00d2be; background:rgba(0,210,190,0.08);">
                                    📄 FIA DOC
                                </a>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal Telemetry Dialog -->
<div class="modal-telemetry-overlay" id="telemetryModal">
    <div class="modal-telemetry-box">
        <div class="modal-head">
            <div class="modal-title" id="modalCaseHeading">EDIT STEWARD VERDICT</div>
            <button type="button" class="btn-modal-close" onclick="dismissEditModal()">&times;</button>
        </div>
        <form method="POST">
            <input type="hidden" name="action" value="update_penalty_status">
            <input type="hidden" name="infringement_id" id="modalCaseId">

            <div class="input-matrix">
                <div class="form-unit full" style="background: rgba(255,255,255,0.03); padding: 12px; border-radius: 6px; border: 1px solid rgba(255,255,255,0.06);">
                    <div style="font-family: 'Share Tech Mono', monospace; font-size: 10px; color: #717188;">TARGET INCIDENT:</div>
                    <div id="modalIncidentSummary" style="font-size: 13px; font-weight: 700; color: #00d2be; margin-top: 3px;"></div>
                </div>

                <div class="form-unit">
                    <label>Ruling Status <span class="req">*</span></label>
                    <select name="status" id="modalStatusSelect" required>
                        <option value="Under Investigation">Under Investigation</option>
                        <option value="Steward Hearing">Steward Hearing</option>
                        <option value="Penalty Applied">Penalty Applied</option>
                        <option value="Appealed">Appealed to FIA Tribunal</option>
                        <option value="Closed">Closed</option>
                        <option value="Dismissed">Dismissed (No Action)</option>
                    </select>
                </div>

                <div class="form-unit">
                    <label>Assigned Penalty Verdict <span class="req">*</span></label>
                    <select name="penalty_type" id="modalPenaltySelect" required>
                        <option value="Under Investigation">Under Investigation</option>
                        <option value="5-Second Time Penalty">5-Second Time Penalty</option>
                        <option value="10-Second Time Penalty">10-Second Time Penalty</option>
                        <option value="Drive-Through Penalty">Drive-Through Penalty</option>
                        <option value="10-Second Stop and Go Penalty">10-Second Stop and Go Penalty</option>
                        <option value="3-Place Grid Drop">3-Place Grid Drop</option>
                        <option value="5-Place Grid Drop">5-Place Grid Drop</option>
                        <option value="10-Place Grid Drop">10-Place Grid Drop</option>
                        <option value="Back of Grid Penalty">Back of Grid Penalty</option>
                        <option value="Pit Lane Start">Pit Lane Start</option>
                        <option value="Disqualification (DSQ)">Disqualification (DSQ)</option>
                        <option value="Official Reprimand">Official Reprimand</option>
                        <option value="€1,000 Fine">€1,000 Fine</option>
                        <option value="€5,000 Fine">€5,000 Fine</option>
                        <option value="€10,000 Fine">€10,000 Fine</option>
                        <option value="No Further Action">No Further Action</option>
                    </select>
                </div>

                <div class="form-unit">
                    <label>Penalty Points</label>
                    <select name="penalty_points" id="modalPointsSelect">
                        <option value="0">0 Points</option>
                        <option value="1">1 Penalty Point</option>
                        <option value="2">2 Penalty Points</option>
                        <option value="3">3 Penalty Points</option>
                        <option value="4">4 Penalty Points</option>
                    </select>
                </div>

                <div class="form-unit">
                    <label>Fine Amount (€)</label>
                    <input type="number" step="100" name="fine_amount" id="modalFineInput" value="0.00">
                </div>

                <div class="form-unit full">
                    <label>Official Reason for Decision</label>
                    <textarea name="steward_notes" id="modalNotesTextarea" rows="3" placeholder="Reasoning based on telemetry, video review, and driver hearing..."></textarea>
                </div>

                <button type="submit" class="btn-action-trigger btn-action-cyan" style="grid-column: 1 / -1; margin-top: 8px;">
                    <span>💾</span> COMMIT VERDICT MODIFICATIONS
                </button>
            </div>
        </form>
    </div>
</div>

<script>
// Filter Ribbon & Realtime Search
document.addEventListener('DOMContentLoaded', () => {
    const filterTabs = document.querySelectorAll('.filter-tab-btn');
    const searchField = document.getElementById('filterSearch');
    const rows = document.querySelectorAll('.steward-row');

    let currentFilter = 'all';

    function runFiltering() {
        const query = (searchField.value || '').toLowerCase().trim();

        rows.forEach(row => {
            const status = row.getAttribute('data-status');
            const text = row.textContent.toLowerCase();

            let matchesTab = (currentFilter === 'all') || (status === currentFilter);
            if (currentFilter === 'Closed' && (status === 'Closed' || status === 'Dismissed')) {
                matchesTab = true;
            }

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
            runFiltering();
        });
    });

    searchField.addEventListener('input', runFiltering);
});

// Quick Dispatch Autofill
function populateQuickUpdate(val) {
    const select = document.getElementById('quickCaseSelect');
    const opt = select.options[select.selectedIndex];
    if (!opt || !val) return;

    document.getElementById('quickStatus').value = opt.getAttribute('data-status') || 'Under Investigation';
    document.getElementById('quickPenalty').value = opt.getAttribute('data-penalty') || 'Under Investigation';
    document.getElementById('quickPoints').value = opt.getAttribute('data-points') || 0;
    document.getElementById('quickFine').value = parseFloat(opt.getAttribute('data-fine') || 0).toFixed(2);
    document.getElementById('quickNotes').value = opt.getAttribute('data-notes') || '';
}

// Modal Operations
function launchEditModal(data) {
    document.getElementById('modalCaseId').value = data.infringement_id;
    document.getElementById('modalCaseHeading').textContent = 'VERDICT RIG: ' + data.case_number;

    const pilot = data.driver_name || 'Pilot Unassigned';
    const chassis = data.car_name || 'Vehicle N/A';
    const team = data.team_name || '';
    document.getElementById('modalIncidentSummary').textContent = `${data.case_number} | ${pilot} (${team}) — ${data.article_code}`;

    document.getElementById('modalStatusSelect').value = data.status;
    document.getElementById('modalPenaltySelect').value = data.penalty_type;
    document.getElementById('modalPointsSelect').value = data.penalty_points || 0;
    document.getElementById('modalFineInput').value = parseFloat(data.fine_amount || 0).toFixed(2);
    document.getElementById('modalNotesTextarea').value = data.steward_notes || '';

    document.getElementById('telemetryModal').classList.add('active');
}

function dismissEditModal() {
    document.getElementById('telemetryModal').classList.remove('active');
}

document.getElementById('telemetryModal').addEventListener('click', (e) => {
    if (e.target.id === 'telemetryModal') {
        dismissEditModal();
    }
});
</script>

</body>
</html>
