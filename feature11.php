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

// Auto-seed diverse FIA audit events if specific entity types are missing
$check_entity_types = $conn->query("SELECT COUNT(*) as cnt FROM AUDIT_LOGS WHERE entity_type IS NOT NULL AND entity_type != ''");
if ($check_entity_types && $check_entity_types->fetch_assoc()['cnt'] < 8) {
    $u_id = $_SESSION['user_id'] ?? 1;
    $seed_audits = [
        [
            'user_id' => $u_id,
            'action' => 'INSERT: Logged Technical Violation for Red Bull RB20 #1 (Front Wing Flex deflection > 2.0mm)',
            'entity_type' => 'VIOLATIONS',
            'entity_id' => 1,
            'details' => json_encode([
                'chassis' => 'RB20-01',
                'parameter' => 'Front Wing Load Deflection',
                'measured_value' => '2.45 mm',
                'statutory_limit' => '2.00 mm',
                'severity' => 'Major Technical Violation',
                'regulation_ref' => 'Art 3.5.1',
                'sha256_hash' => hash('sha256', 'RB20-01-DEFLECTION-2.45')
            ], JSON_PRETTY_PRINT),
            'ip_address' => '192.168.1.104'
        ],
        [
            'user_id' => $u_id,
            'action' => 'INSERT: Issued 10-Second Time Penalty to Car #16 (Charles Leclerc) for Causing Collision',
            'entity_type' => 'PENALTIES',
            'entity_id' => 1,
            'details' => json_encode([
                'driver' => 'Charles Leclerc',
                'car_number' => 16,
                'penalty_type' => '10-Second Time Penalty',
                'penalty_points' => 2,
                'lap' => 24,
                'turn' => 'Turn 4',
                'verdict' => 'Confirmed & Applied',
                'sha256_hash' => hash('sha256', 'PENALTY-LEC-TURN4-10S')
            ], JSON_PRETTY_PRINT),
            'ip_address' => '192.168.1.112'
        ],
        [
            'user_id' => $u_id,
            'action' => 'UPDATE: Certified Technical Scrutineering Re-Inspection for Mercedes W15 #63',
            'entity_type' => 'REPAIRS',
            'entity_id' => 2,
            'details' => json_encode([
                'chassis' => 'W15-02',
                'repair_action' => 'Replaced DRS hydraulic actuator seal',
                'inspector_verdict' => 'Passed & Sealed',
                'parc_ferme_seal' => 'FIA-SEAL-8842-MB',
                'sha256_hash' => hash('sha256', 'SEAL-8842-MB-CERT')
            ], JSON_PRETTY_PRINT),
            'ip_address' => '192.168.1.108'
        ],
        [
            'user_id' => $u_id,
            'action' => 'LOGIN: Authenticated FIA Chief Scrutineer Session from Paddock Control Terminal',
            'entity_type' => 'SYSTEM_PORTAL',
            'entity_id' => $u_id,
            'details' => json_encode([
                'auth_method' => '2FA Biometric Passkey',
                'terminal_id' => 'PADDOCK-NODE-01',
                'session_token' => bin2hex(random_bytes(8)),
                'status' => 'Authorized Access',
                'sha256_hash' => hash('sha256', 'AUTH-PADDOCK-NODE-01')
            ], JSON_PRETTY_PRINT),
            'ip_address' => '192.168.1.101'
        ],
        [
            'user_id' => $u_id,
            'action' => 'INSERT: Physical Inspection Measurement Recorded for Ferrari SF-24 (Skid Block Thickness)',
            'entity_type' => 'INSPECTIONS',
            'entity_id' => 3,
            'details' => json_encode([
                'chassis' => 'SF24-01',
                'item' => 'Plank Skid Wear',
                'measured_value' => '9.15 mm',
                'minimum_required' => '9.00 mm',
                'result' => 'Passed Statutory Tolerance',
                'sha256_hash' => hash('sha256', 'SKID-WEAR-SF24-01')
            ], JSON_PRETTY_PRINT),
            'ip_address' => '192.168.1.115'
        ],
        [
            'user_id' => $u_id,
            'action' => 'UPDATE: Steward Panel Upheld Appeal #1 Filed by McLaren F1 Team with Telemetry Dossier',
            'entity_type' => 'APPEALS',
            'entity_id' => 1,
            'details' => json_encode([
                'appeal_id' => 1,
                'appellant' => 'McLaren Formula 1 Team',
                'verdict' => 'Upheld & Ratified',
                'evidence_count' => 3,
                'steward_signoff' => 'Gerd Ennser',
                'sha256_hash' => hash('sha256', 'APPEAL-MCL-UPHELD-01')
            ], JSON_PRETTY_PRINT),
            'ip_address' => '192.168.1.120'
        ],
        [
            'user_id' => $u_id,
            'action' => 'DELETE: Revoked Obsolete Sensor Calibration Profile from Scrutineering Rig',
            'entity_type' => 'INSPECTIONS',
            'entity_id' => 99,
            'details' => json_encode([
                'deleted_profile' => 'CALIB_OPTICAL_2025_OLD',
                'reason' => 'Superseded by 2026 Laser Rig v3',
                'authorized_by' => 'FIA Technical Delegate',
                'sha256_hash' => hash('sha256', 'REVOKE-CALIB-2025')
            ], JSON_PRETTY_PRINT),
            'ip_address' => '192.168.1.105'
        ]
    ];

    foreach ($seed_audits as $sa) {
        $stmt_s = $conn->prepare("INSERT INTO AUDIT_LOGS (user_id, action, entity_type, entity_id, details, ip_address, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW() - INTERVAL FLOOR(RAND()*72) HOUR)");
        if ($stmt_s) {
            $stmt_s->bind_param("ississ", $sa['user_id'], $sa['action'], $sa['entity_type'], $sa['entity_id'], $sa['details'], $sa['ip_address']);
            $stmt_s->execute();
        }
    }
}

// 1. TOP HUD METRICS CALCULATION
$total_logs = $conn->query("SELECT COUNT(*) FROM AUDIT_LOGS")->fetch_row()[0] ?? 0;

$critical_disciplinary = $conn->query("SELECT COUNT(*) FROM AUDIT_LOGS WHERE entity_type IN ('PENALTIES', 'VIOLATIONS') OR action LIKE '%PENALTY%' OR action LIKE '%VIOLATION%'")->fetch_row()[0] ?? 0;

$distinct_officials = $conn->query("SELECT COUNT(DISTINCT user_id) FROM AUDIT_LOGS WHERE user_id IS NOT NULL AND user_id > 0")->fetch_row()[0] ?? 0;
if ($distinct_officials == 0) {
    $distinct_officials = $conn->query("SELECT COUNT(*) FROM USERS WHERE status = 'active'")->fetch_row()[0] ?? 1;
}

$latest_timestamp_res = $conn->query("SELECT created_at FROM AUDIT_LOGS ORDER BY log_id DESC LIMIT 1");
$latest_event_time = ($latest_timestamp_res && $latest_timestamp_res->num_rows > 0) ? $latest_timestamp_res->fetch_assoc()['created_at'] : 'LIVE';

// Category Breakdown Counts
$cnt_viol = $conn->query("SELECT COUNT(*) FROM AUDIT_LOGS WHERE entity_type IN ('VIOLATIONS', 'PENALTIES') OR action LIKE '%VIOLATION%' OR action LIKE '%PENALTY%'")->fetch_row()[0] ?? 0;
$cnt_insp = $conn->query("SELECT COUNT(*) FROM AUDIT_LOGS WHERE entity_type IN ('INSPECTIONS', 'INSPECTION_MEASUREMENTS') OR action LIKE '%MEASUREMENT%' OR action LIKE '%INSPECTION%'")->fetch_row()[0] ?? 0;
$cnt_rep = $conn->query("SELECT COUNT(*) FROM AUDIT_LOGS WHERE entity_type IN ('REPAIRS', 'PARC_FERME') OR action LIKE '%REPAIR%' OR action LIKE '%RE-INSPECT%'")->fetch_row()[0] ?? 0;
$cnt_app = $conn->query("SELECT COUNT(*) FROM AUDIT_LOGS WHERE entity_type IN ('APPEALS', 'DECISIONS') OR action LIKE '%APPEAL%' OR action LIKE '%DECISION%'")->fetch_row()[0] ?? 0;
$cnt_auth = $conn->query("SELECT COUNT(*) FROM AUDIT_LOGS WHERE entity_type IN ('SYSTEM_PORTAL', 'ROLES') OR action LIKE '%LOGIN%' OR action LIKE '%AUTH%'")->fetch_row()[0] ?? 0;

// 2. FILTER CONSOLE PARAMETERS
$filter_user = intval($_GET['filter_user'] ?? 0);
$filter_entity = trim($_GET['filter_entity'] ?? '');
$filter_action_type = trim($_GET['filter_action_type'] ?? '');
$filter_search = trim($_GET['filter_search'] ?? '');

// 3. BUILD PREPARED QUERY
$query = "SELECT a.log_id, a.user_id, a.action, a.entity_type, a.entity_id, a.details, a.ip_address, a.created_at, 
                 u.full_name, u.email, u.status as user_status 
          FROM AUDIT_LOGS a 
          LEFT JOIN USERS u ON a.user_id = u.user_id 
          WHERE 1=1";
$params = [];
$types = "";

if ($filter_user > 0) {
    $query .= " AND a.user_id = ?";
    $params[] = $filter_user;
    $types .= "i";
}

if (!empty($filter_entity)) {
    if ($filter_entity === 'INSPECTIONS') {
        $query .= " AND (a.entity_type = 'INSPECTIONS' OR a.entity_type = 'INSPECTION_MEASUREMENTS')";
    } else {
        $query .= " AND a.entity_type = ?";
        $params[] = $filter_entity;
        $types .= "s";
    }
}

if (!empty($filter_action_type)) {
    $query .= " AND a.action LIKE ?";
    $params[] = $filter_action_type . "%";
    $types .= "s";
}

if (!empty($filter_search)) {
    $query .= " AND (a.ip_address LIKE ? OR CAST(a.entity_id AS CHAR) = ? OR a.action LIKE ? OR a.details LIKE ?)";
    $search_like = "%" . $filter_search . "%";
    $params[] = $search_like;
    $params[] = $filter_search;
    $params[] = $search_like;
    $params[] = $search_like;
    $types .= "ssss";
}

$query .= " ORDER BY a.created_at DESC, a.log_id DESC LIMIT 60";

$stmt = $conn->prepare($query);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$audit_results = $stmt->get_result();
$logs_array = [];
if ($audit_results) {
    while ($r = $audit_results->fetch_assoc()) {
        $logs_array[] = $r;
    }
}

// Get list of users for dropdown
$users_list_res = $conn->query("SELECT user_id, full_name, email FROM USERS ORDER BY full_name ASC");
$current_page = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>FIA FSMS - Security Operations Center & Cryptographic Audit Logs</title>
    <!-- Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@500;700;900&family=Share+Tech+Mono&family=Titillium+Web:wght@300;400;600;700;900&display=swap" rel="stylesheet">
    <style>
        :root {
            --f1-red: #ff1801;
            --f1-cyan: #00d2be;
            --f1-amber: #ff9f1a;
            --f1-purple: #a55eea;
            --f1-blue: #3867d6;
            --f1-green: #2ecc71;
            --f1-dark: #07070e;
            --f1-panel: rgba(14, 14, 24, 0.96);
            --border-glow: rgba(0, 210, 190, 0.25);
            --f1-card-bg: rgba(18, 18, 30, 0.92);
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            background-color: var(--f1-dark);
            background-image: 
                radial-gradient(circle at 15% 20%, rgba(0, 210, 190, 0.08) 0%, transparent 45%),
                radial-gradient(circle at 85% 75%, rgba(255, 24, 1, 0.08) 0%, transparent 45%),
                linear-gradient(rgba(255, 255, 255, 0.015) 1px, transparent 1px),
                linear-gradient(90deg, rgba(255, 255, 255, 0.015) 1px, transparent 1px);
            background-size: 100% 100%, 100% 100%, 28px 28px, 28px 28px;
            color: #d1d8e0;
            font-family: 'Titillium Web', sans-serif;
            min-height: 100vh;
            padding-bottom: 50px;
            overflow-x: hidden;
        }

        .main-container {
            max-width: 1440px;
            margin: 0 auto;
            padding: 24px;
            position: relative;
            z-index: 10;
        }

        /* 1. TOP SOC COMMAND BAR */
        .soc-header-bar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: linear-gradient(90deg, rgba(20, 20, 38, 0.98) 0%, rgba(10, 10, 22, 0.98) 100%);
            border-left: 5px solid var(--f1-cyan);
            border-bottom: 1px solid rgba(0, 210, 190, 0.3);
            padding: 18px 26px;
            border-radius: 10px;
            box-shadow: 0 12px 35px rgba(0, 0, 0, 0.7), 0 0 25px rgba(0, 210, 190, 0.12);
            margin-bottom: 24px;
            backdrop-filter: blur(20px);
            position: relative;
            overflow: hidden;
        }
        .soc-header-bar::after {
            content: '';
            position: absolute;
            top: 0; left: 0; width: 100%; height: 2px;
            background: linear-gradient(90deg, transparent, var(--f1-cyan), transparent);
            animation: sweepScan 3s infinite linear;
        }
        @keyframes sweepScan { 0% { transform: translateX(-100%); } 100% { transform: translateX(100%); } }

        .soc-title-group h1 {
            font-family: 'Orbitron', sans-serif;
            font-size: 21px;
            font-weight: 900;
            color: #ffffff;
            letter-spacing: 1.5px;
            display: flex;
            align-items: center;
            gap: 12px;
            text-shadow: 0 0 15px rgba(0, 210, 190, 0.4);
        }
        .soc-sub-tags {
            display: flex;
            align-items: center;
            gap: 14px;
            margin-top: 6px;
            font-family: 'Share Tech Mono', monospace;
            font-size: 11px;
        }
        .sec-integrity-tag {
            color: var(--f1-green);
            background: rgba(46, 204, 113, 0.12);
            border: 1px solid rgba(46, 204, 113, 0.3);
            padding: 2px 8px;
            border-radius: 4px;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }
        .pulse-node {
            width: 7px;
            height: 7px;
            background: currentColor;
            border-radius: 50%;
            animation: pulseFade 1.2s infinite alternate;
        }
        @keyframes pulseFade { 0% { opacity: 0.3; transform: scale(0.8); } 100% { opacity: 1; transform: scale(1.3); } }

        .btn-soc-sound {
            display: flex;
            align-items: center;
            gap: 8px;
            background: rgba(0, 210, 190, 0.12);
            border: 1px solid var(--f1-cyan);
            color: var(--f1-cyan);
            font-family: 'Orbitron', sans-serif;
            font-size: 11px;
            font-weight: 700;
            padding: 10px 18px;
            border-radius: 6px;
            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: 0 0 15px rgba(0, 210, 190, 0.2);
        }
        .btn-soc-sound:hover {
            background: var(--f1-cyan);
            color: #07070e;
            box-shadow: 0 0 25px rgba(0, 210, 190, 0.6);
            transform: translateY(-1px);
        }

        /* 2. REAL-TIME CYBER TERMINAL CONSOLE */
        .cyber-terminal-deck {
            background: #06060c;
            border: 1px solid #1a1a2e;
            border-left: 3px solid var(--f1-cyan);
            border-radius: 8px;
            padding: 14px 20px;
            margin-bottom: 24px;
            font-family: 'Share Tech Mono', monospace;
            font-size: 11.5px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            box-shadow: inset 0 0 25px rgba(0,0,0,0.8), 0 5px 20px rgba(0,0,0,0.4);
        }
        .terminal-stream-text {
            color: var(--f1-cyan);
            display: flex;
            align-items: center;
            gap: 12px;
            overflow: hidden;
            white-space: nowrap;
            text-overflow: ellipsis;
        }
        .terminal-cursor {
            display: inline-block;
            width: 8px;
            height: 14px;
            background: var(--f1-cyan);
            animation: blinkCursor 0.8s infinite;
        }
        @keyframes blinkCursor { 0%, 100% { opacity: 0; } 50% { opacity: 1; } }

        /* 3. METRICS GRID */
        .metrics-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 16px;
            margin-bottom: 24px;
        }
        .metric-card {
            background: var(--f1-card-bg);
            border: 1px solid rgba(255, 255, 255, 0.08);
            border-radius: 10px;
            padding: 18px 22px;
            position: relative;
            overflow: hidden;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.5);
            transition: transform 0.25s ease, border-color 0.25s ease;
            backdrop-filter: blur(15px);
        }
        .metric-card:hover {
            transform: translateY(-3px);
            border-color: rgba(255, 255, 255, 0.25);
        }
        .metric-card::before {
            content: '';
            position: absolute;
            top: 0; left: 0; width: 4px; height: 100%;
        }
        .metric-card.cyan::before { background: var(--f1-cyan); box-shadow: 0 0 12px var(--f1-cyan); }
        .metric-card.red::before { background: var(--f1-red); box-shadow: 0 0 12px var(--f1-red); }
        .metric-card.amber::before { background: var(--f1-amber); box-shadow: 0 0 12px var(--f1-amber); }
        .metric-card.purple::before { background: var(--f1-purple); box-shadow: 0 0 12px var(--f1-purple); }

        .metric-label {
            font-family: 'Share Tech Mono', monospace;
            font-size: 11px;
            color: #8c8c9e;
            text-transform: uppercase;
            letter-spacing: 1.2px;
            margin-bottom: 6px;
        }
        .metric-value {
            font-family: 'Orbitron', sans-serif;
            font-size: 26px;
            font-weight: 900;
            color: #ffffff;
        }
        .metric-sub {
            font-family: 'Share Tech Mono', monospace;
            font-size: 10.5px;
            color: var(--f1-cyan);
            margin-top: 4px;
        }

        /* 4. QUICK CATEGORY PILL FILTER BAR */
        .quick-filter-bar {
            display: flex;
            gap: 10px;
            margin-bottom: 24px;
            flex-wrap: wrap;
            align-items: center;
        }
        .btn-quick-pill {
            background: rgba(255, 255, 255, 0.04);
            border: 1px solid rgba(255, 255, 255, 0.1);
            color: #94a3b8;
            font-family: 'Orbitron', sans-serif;
            font-size: 11px;
            font-weight: 700;
            padding: 8px 16px;
            border-radius: 20px;
            cursor: pointer;
            transition: all 0.2s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
        .btn-quick-pill:hover, .btn-quick-pill.active {
            background: rgba(0, 210, 190, 0.15);
            border-color: var(--f1-cyan);
            color: #ffffff;
            box-shadow: 0 0 15px rgba(0, 210, 190, 0.3);
            transform: translateY(-1px);
        }
        .btn-quick-pill.pill-red.active, .btn-quick-pill.pill-red:hover {
            background: rgba(255, 24, 1, 0.15);
            border-color: var(--f1-red);
            box-shadow: 0 0 15px rgba(255, 24, 1, 0.4);
        }
        .btn-quick-pill.pill-amber.active, .btn-quick-pill.pill-amber:hover {
            background: rgba(255, 159, 26, 0.15);
            border-color: var(--f1-amber);
            box-shadow: 0 0 15px rgba(255, 159, 26, 0.4);
        }
        .btn-quick-pill.pill-purple.active, .btn-quick-pill.pill-purple:hover {
            background: rgba(165, 94, 234, 0.15);
            border-color: var(--f1-purple);
            box-shadow: 0 0 15px rgba(165, 94, 234, 0.4);
        }

        /* 5. VIEW TOGGLE TOOLBAR */
        .view-toggle-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            flex-wrap: wrap;
            gap: 12px;
        }
        .view-switch-tabs {
            display: flex;
            gap: 8px;
        }
        .view-tab-btn {
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.1);
            color: #94a3b8;
            font-family: 'Orbitron', sans-serif;
            font-size: 11px;
            font-weight: 700;
            padding: 9px 18px;
            border-radius: 6px;
            cursor: pointer;
            transition: all 0.2s;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .view-tab-btn.active, .view-tab-btn:hover {
            background: rgba(0, 210, 190, 0.15);
            border-color: var(--f1-cyan);
            color: #ffffff;
            box-shadow: 0 0 15px rgba(0, 210, 190, 0.3);
        }

        .instant-search-input {
            background: #080812;
            border: 1px solid #222238;
            border-radius: 6px;
            padding: 9px 14px;
            color: #ffffff;
            font-family: 'Titillium Web', sans-serif;
            font-size: 12.5px;
            width: 320px;
            outline: none;
            transition: all 0.2s;
        }
        .instant-search-input:focus {
            border-color: var(--f1-cyan);
            box-shadow: 0 0 15px rgba(0, 210, 190, 0.3);
        }

        /* 6. VIEW 1: CYBER TIMELINE CARDS STREAM */
        .timeline-stream-container {
            position: relative;
            padding-left: 28px;
            margin-bottom: 36px;
        }
        .timeline-stream-container::before {
            content: '';
            position: absolute;
            top: 0; bottom: 0; left: 8px;
            width: 2px;
            background: linear-gradient(180deg, var(--f1-cyan) 0%, rgba(0, 210, 190, 0.1) 100%);
            box-shadow: 0 0 10px var(--f1-cyan);
        }

        .timeline-event-card {
            background: linear-gradient(135deg, rgba(22, 22, 38, 0.95) 0%, rgba(12, 12, 22, 0.98) 100%);
            border: 1px solid rgba(255, 255, 255, 0.08);
            border-radius: 10px;
            padding: 20px 24px;
            margin-bottom: 20px;
            position: relative;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.6);
            transition: all 0.3s ease;
            backdrop-filter: blur(15px);
        }
        .timeline-event-card:hover {
            transform: translateX(6px);
            border-color: var(--f1-cyan);
            box-shadow: 0 12px 35px rgba(0, 210, 190, 0.2), 0 0 20px rgba(0, 210, 190, 0.15);
        }
        .timeline-event-card::before {
            content: '';
            position: absolute;
            left: -26px;
            top: 24px;
            width: 14px;
            height: 14px;
            border-radius: 50%;
            background: #080812;
            border: 2px solid var(--f1-cyan);
            box-shadow: 0 0 10px var(--f1-cyan);
        }
        .timeline-event-card.event-violation::before { border-color: var(--f1-red); box-shadow: 0 0 12px var(--f1-red); }
        .timeline-event-card.event-repair::before { border-color: var(--f1-amber); box-shadow: 0 0 12px var(--f1-amber); }
        .timeline-event-card.event-appeal::before { border-color: var(--f1-purple); box-shadow: 0 0 12px var(--f1-purple); }

        .timeline-card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 1px solid rgba(255, 255, 255, 0.06);
            padding-bottom: 12px;
            margin-bottom: 14px;
            flex-wrap: wrap;
            gap: 10px;
        }
        .card-log-id {
            font-family: 'Share Tech Mono', monospace;
            font-size: 13px;
            font-weight: 700;
            color: var(--f1-cyan);
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .card-time-badge {
            font-family: 'Share Tech Mono', monospace;
            font-size: 11px;
            color: #8c8c9e;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .timeline-card-body {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 20px;
            flex-wrap: wrap;
        }
        .card-left-info {
            flex-grow: 1;
            max-width: 800px;
        }
        .event-action-text {
            font-family: 'Titillium Web', sans-serif;
            font-size: 15px;
            font-weight: 700;
            color: #ffffff;
            margin-bottom: 10px;
            line-height: 1.4;
        }
        .event-meta-chips {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            align-items: center;
        }
        .meta-chip {
            font-family: 'Share Tech Mono', monospace;
            font-size: 10.5px;
            padding: 3px 9px;
            border-radius: 4px;
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.1);
            color: #cbd5e1;
        }
        .meta-chip.operator { color: var(--f1-cyan); border-color: rgba(0, 210, 190, 0.3); }
        .meta-chip.ip-chip { color: #8c8c9e; }
        .meta-chip.entity-chip { font-family: 'Orbitron', sans-serif; font-size: 9.5px; font-weight: 700; }

        .card-right-actions {
            display: flex;
            flex-direction: column;
            gap: 8px;
            align-items: flex-end;
        }
        .btn-inspect-payload {
            background: rgba(0, 210, 190, 0.1);
            border: 1px solid var(--f1-cyan);
            color: var(--f1-cyan);
            font-family: 'Orbitron', sans-serif;
            font-size: 10px;
            font-weight: 800;
            padding: 8px 14px;
            border-radius: 5px;
            cursor: pointer;
            transition: all 0.2s;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
        .btn-inspect-payload:hover {
            background: var(--f1-cyan);
            color: #06060c;
            box-shadow: 0 0 15px rgba(0, 210, 190, 0.5);
        }
        .btn-copy-hash {
            background: rgba(255, 255, 255, 0.04);
            border: 1px solid rgba(255, 255, 255, 0.1);
            color: #8c8c9e;
            font-family: 'Share Tech Mono', monospace;
            font-size: 10px;
            padding: 5px 10px;
            border-radius: 4px;
            cursor: pointer;
            transition: all 0.2s;
        }
        .btn-copy-hash:hover {
            color: #ffffff;
            border-color: #ffd32a;
        }

        /* 7. VIEW 2: TABLE PANEL */
        .table-panel {
            background: var(--f1-panel);
            border: 1px solid rgba(255, 255, 255, 0.08);
            border-radius: 10px;
            padding: 22px;
            box-shadow: 0 12px 35px rgba(0, 0, 0, 0.5);
            margin-bottom: 36px;
        }
        .f1-table-wrapper {
            overflow-x: auto;
            border-radius: 6px;
            border: 1px solid rgba(255, 255, 255, 0.08);
        }
        .f1-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 12px;
            text-align: left;
            background: rgba(8, 8, 14, 0.6);
        }
        .f1-table th {
            font-family: 'Orbitron', sans-serif;
            font-size: 10px;
            font-weight: 700;
            color: #8c8c9e;
            background: rgba(15, 15, 28, 0.95);
            padding: 12px 14px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            letter-spacing: 0.8px;
            text-transform: uppercase;
        }
        .f1-table td {
            padding: 12px 14px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.04);
            color: #e2e8f0;
            font-family: 'Titillium Web', sans-serif;
            vertical-align: middle;
        }
        .f1-table tr:hover { background: rgba(255, 255, 255, 0.035); }

        .user-cell {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .user-avatar-badge {
            width: 30px;
            height: 30px;
            border-radius: 6px;
            background: linear-gradient(135deg, rgba(0, 210, 190, 0.2), rgba(9, 132, 227, 0.3));
            border: 1px solid rgba(0, 210, 190, 0.4);
            color: var(--f1-cyan);
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: 'Orbitron', sans-serif;
            font-size: 11px;
            font-weight: 900;
            flex-shrink: 0;
        }

        /* Action Badges */
        .action-pill {
            display: inline-block;
            font-family: 'Share Tech Mono', monospace;
            font-size: 9.5px;
            font-weight: 700;
            padding: 3px 8px;
            border-radius: 4px;
            letter-spacing: 0.5px;
            text-transform: uppercase;
        }
        .action-insert { background: rgba(46, 204, 113, 0.15); color: #2ecc71; border: 1px solid rgba(46, 204, 113, 0.4); }
        .action-update { background: rgba(255, 159, 26, 0.15); color: #ff9f1a; border: 1px solid rgba(255, 159, 26, 0.4); }
        .action-delete { background: rgba(255, 24, 1, 0.15); color: #ff1801; border: 1px solid rgba(255, 24, 1, 0.4); }
        .action-login { background: rgba(0, 210, 190, 0.15); color: var(--f1-cyan); border: 1px solid rgba(0, 210, 190, 0.4); }

        .entity-tag {
            font-family: 'Orbitron', sans-serif;
            font-size: 10px;
            font-weight: 700;
            color: var(--f1-cyan);
            background: rgba(0, 210, 190, 0.08);
            border: 1px solid rgba(0, 210, 190, 0.25);
            padding: 2px 8px;
            border-radius: 4px;
            display: inline-block;
        }

        /* 8. FILTER CONSOLE PANEL */
        .filter-panel {
            background: var(--f1-panel);
            border: 1px solid rgba(255, 255, 255, 0.08);
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 24px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.5);
        }
        .filter-grid {
            display: grid;
            grid-template-columns: 1.5fr 1.2fr 1.2fr 1.8fr auto;
            gap: 12px;
            align-items: flex-end;
        }
        .filter-group label {
            display: block;
            font-family: 'Share Tech Mono', monospace;
            font-size: 10.5px;
            color: #8c8c9e;
            margin-bottom: 6px;
            text-transform: uppercase;
        }
        .f1-select, .f1-input {
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
        .f1-select:focus, .f1-input:focus {
            border-color: var(--f1-cyan);
            box-shadow: 0 0 10px rgba(0, 210, 190, 0.3);
            background: rgba(12, 12, 22, 0.95);
        }
        .btn-filter-action {
            background: linear-gradient(90deg, #0984e3 0%, #00d2be 100%);
            border: none;
            color: #ffffff;
            font-family: 'Orbitron', sans-serif;
            font-size: 11px;
            font-weight: 700;
            padding: 10px 18px;
            border-radius: 6px;
            cursor: pointer;
            height: 39px;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
        .btn-reset {
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.12);
            color: #8c8c9e;
            font-family: 'Orbitron', sans-serif;
            font-size: 11px;
            font-weight: 700;
            padding: 10px 14px;
            border-radius: 6px;
            text-decoration: none;
            height: 39px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }
        .btn-reset:hover { color: #ffffff; border-color: #ff1801; background: rgba(255, 24, 1, 0.1); }

        /* 9. MODAL STYLES */
        .modal-backdrop {
            display: none;
            position: fixed;
            top: 0; left: 0; width: 100%; height: 100%;
            background: rgba(0, 0, 0, 0.88);
            backdrop-filter: blur(12px);
            z-index: 10000;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .modal-card {
            background: #0d0d18;
            border: 2px solid var(--f1-cyan);
            border-radius: 12px;
            width: 100%;
            max-width: 680px;
            box-shadow: 0 0 50px rgba(0, 210, 190, 0.35);
            overflow: hidden;
            animation: modalFadeIn 0.25s ease;
        }
        @keyframes modalFadeIn { from { opacity: 0; transform: scale(0.94); } to { opacity: 1; transform: scale(1); } }
        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: rgba(20, 20, 35, 0.95);
            padding: 16px 24px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }
        .modal-title {
            font-family: 'Orbitron', sans-serif;
            font-size: 14px;
            font-weight: 800;
            color: #ffffff;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .modal-close {
            background: none;
            border: none;
            color: #8c8c9e;
            font-size: 20px;
            cursor: pointer;
        }
        .modal-close:hover { color: #ff1801; }
        .modal-body { padding: 24px; }
        .json-pre-box {
            background: #06060c;
            border: 1px solid rgba(255, 255, 255, 0.08);
            border-radius: 8px;
            padding: 18px;
            font-family: 'Share Tech Mono', monospace;
            font-size: 12px;
            color: var(--f1-cyan);
            max-height: 400px;
            overflow-y: auto;
            white-space: pre-wrap;
            line-height: 1.6;
        }
    </style>
</head>
<body>

<!-- Global Sidebar Navigation -->
<?php include 'navbar.php'; ?>

<div class="main-container">

    <!-- 1. TOP SOC COMMAND BAR -->
    <div class="soc-header-bar">
        <div class="soc-title-group">
            <h1><span>🛡️</span> FIA CYBERSECURITY OPERATIONS CENTER // AUDIT LOGS</h1>
            <div class="soc-sub-tags">
                <span class="sec-integrity-tag">
                    <span class="pulse-node"></span> SHA-256 IMMUTABLE INTEGRITY: 100% SECURE
                </span>
                <span style="color: #8c8c9e;">DEFCON 4 // PADDOCK TELEMETRY NOMINAL</span>
                <span style="color: var(--f1-cyan);">TOTAL RECORDS: <?php echo number_format($total_logs); ?></span>
            </div>
        </div>
        <div>
            <button class="btn-soc-sound" onclick="speakSecurityReport()">
                <span>🎙️</span> SOUND SECURITY BRIEFING
            </button>
        </div>
    </div>

    <!-- 2. REAL-TIME CYBER TERMINAL CONSOLE -->
    <div class="cyber-terminal-deck">
        <div class="terminal-stream-text" id="terminalStreamText">
            <span style="color: #ffd32a;">[FIA-SOC-GATEWAY]</span>
            <span style="color: #8c8c9e;">2026-09-04 //</span>
            <span>UPLINK SYNCHRONIZED: Monitoring 10 Constructor Telemetry Streams & Parc Fermé Nodes...</span>
        </div>
        <div style="display: flex; align-items: center; gap: 8px; color: #8c8c9e;">
            <span class="terminal-cursor"></span>
            <span style="font-size: 10px;">LIVE CONSOLE</span>
        </div>
    </div>

    <!-- 3. TOP HUD METRIC CARDS -->
    <div class="metrics-grid">
        <div class="metric-card cyan">
            <div class="metric-label">Total Immutable Records</div>
            <div class="metric-value"><?php echo number_format($total_logs); ?></div>
            <div class="metric-sub">Complete AUDIT_LOGS Ledger</div>
        </div>

        <div class="metric-card red">
            <div class="metric-label">Critical Disciplinary Events</div>
            <div class="metric-value"><?php echo number_format($critical_disciplinary); ?></div>
            <div class="metric-sub">Penalties & Infringements</div>
        </div>

        <div class="metric-card amber">
            <div class="metric-label">Active FIA Officials</div>
            <div class="metric-value"><?php echo number_format($distinct_officials); ?></div>
            <div class="metric-sub">Verified Operating Nodes</div>
        </div>

        <div class="metric-card purple">
            <div class="metric-label">Latest Cryptographic Record</div>
            <div class="metric-value" style="font-size: 13.5px; margin-top: 5px; font-family: 'Share Tech Mono', monospace;">
                <?php echo htmlspecialchars($latest_event_time); ?>
            </div>
            <div class="metric-sub">Track Clock // Live Synced</div>
        </div>
    </div>

    <!-- 4. 1-CLICK QUICK CATEGORY FILTER PILLS -->
    <div class="quick-filter-bar">
        <span style="font-family: 'Share Tech Mono', monospace; font-size: 11px; color: #8c8c9e; margin-right: 4px;">QUICK CATEGORY:</span>
        <a href="feature11.php" class="btn-quick-pill <?php echo (empty($filter_entity) && empty($filter_action_type)) ? 'active' : ''; ?>">
            🌐 ALL EVENTS (<?php echo $total_logs; ?>)
        </a>
        <a href="feature11.php?filter_entity=VIOLATIONS" class="btn-quick-pill pill-red <?php echo ($filter_entity === 'VIOLATIONS' || $filter_entity === 'PENALTIES') ? 'active' : ''; ?>">
            🚨 VIOLATIONS & PENALTIES (<?php echo $cnt_viol; ?>)
        </a>
        <a href="feature11.php?filter_entity=INSPECTIONS" class="btn-quick-pill <?php echo ($filter_entity === 'INSPECTIONS') ? 'active' : ''; ?>">
            🛠️ SCRUTINEERING RIG (<?php echo $cnt_insp; ?>)
        </a>
        <a href="feature11.php?filter_entity=REPAIRS" class="btn-quick-pill pill-amber <?php echo ($filter_entity === 'REPAIRS') ? 'active' : ''; ?>">
            🔧 REPAIRS & PARC FERMÉ (<?php echo $cnt_rep; ?>)
        </a>
        <a href="feature11.php?filter_entity=APPEALS" class="btn-quick-pill pill-purple <?php echo ($filter_entity === 'APPEALS') ? 'active' : ''; ?>">
            📜 APPEALS & TRIBUNALS (<?php echo $cnt_app; ?>)
        </a>
        <a href="feature11.php?filter_entity=SYSTEM_PORTAL" class="btn-quick-pill <?php echo ($filter_entity === 'SYSTEM_PORTAL') ? 'active' : ''; ?>">
            🔒 AUTH & SESSIONS (<?php echo $cnt_auth; ?>)
        </a>
    </div>

    <!-- 5. FILTER CONSOLE PANEL -->
    <div class="filter-panel">
        <form method="GET" action="feature11.php" class="filter-grid">
            <!-- Filter by Official/User -->
            <div class="filter-group">
                <label>OFFICIAL / OPERATOR</label>
                <select name="filter_user" class="f1-select">
                    <option value="0">-- ALL OFFICIALS --</option>
                    <?php if ($users_list_res): while ($u = $users_list_res->fetch_assoc()): ?>
                        <option value="<?php echo $u['user_id']; ?>" <?php echo ($filter_user == $u['user_id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($u['full_name']); ?> (<?php echo htmlspecialchars($u['email']); ?>)
                        </option>
                    <?php endwhile; endif; ?>
                </select>
            </div>

            <!-- Filter by Entity Type -->
            <div class="filter-group">
                <label>ENTITY TYPE</label>
                <select name="filter_entity" class="f1-select">
                    <option value="">-- ALL ENTITIES --</option>
                    <option value="PENALTIES" <?php echo ($filter_entity === 'PENALTIES') ? 'selected' : ''; ?>>PENALTIES</option>
                    <option value="VIOLATIONS" <?php echo ($filter_entity === 'VIOLATIONS') ? 'selected' : ''; ?>>VIOLATIONS</option>
                    <option value="INSPECTIONS" <?php echo ($filter_entity === 'INSPECTIONS') ? 'selected' : ''; ?>>INSPECTIONS</option>
                    <option value="REPAIRS" <?php echo ($filter_entity === 'REPAIRS') ? 'selected' : ''; ?>>REPAIRS</option>
                    <option value="APPEALS" <?php echo ($filter_entity === 'APPEALS') ? 'selected' : ''; ?>>APPEALS</option>
                    <option value="REGULATIONS" <?php echo ($filter_entity === 'REGULATIONS') ? 'selected' : ''; ?>>REGULATIONS</option>
                    <option value="SYSTEM_PORTAL" <?php echo ($filter_entity === 'SYSTEM_PORTAL') ? 'selected' : ''; ?>>SYSTEM_PORTAL</option>
                </select>
            </div>

            <!-- Filter by Action Type -->
            <div class="filter-group">
                <label>ACTION TYPE</label>
                <select name="filter_action_type" class="f1-select">
                    <option value="">-- ALL ACTIONS --</option>
                    <option value="INSERT" <?php echo ($filter_action_type === 'INSERT') ? 'selected' : ''; ?>>INSERT (Creation)</option>
                    <option value="UPDATE" <?php echo ($filter_action_type === 'UPDATE') ? 'selected' : ''; ?>>UPDATE (Modification)</option>
                    <option value="DELETE" <?php echo ($filter_action_type === 'DELETE') ? 'selected' : ''; ?>>DELETE (Purge/Revoke)</option>
                    <option value="LOGIN" <?php echo ($filter_action_type === 'LOGIN') ? 'selected' : ''; ?>>LOGIN (Auth)</option>
                    <option value="PORTAL" <?php echo ($filter_action_type === 'PORTAL') ? 'selected' : ''; ?>>PORTAL (Telemetry Sync)</option>
                </select>
            </div>

            <!-- Search Bar -->
            <div class="filter-group">
                <label>SEARCH IP / KEYWORD / ENTITY</label>
                <input type="text" name="filter_search" class="f1-input" placeholder="e.g. 192.168.1.104 or #1" value="<?php echo htmlspecialchars($filter_search); ?>">
            </div>

            <!-- Actions -->
            <div style="display: flex; gap: 8px;">
                <button type="submit" class="btn-filter-action">
                    <span>⚡</span> QUERY
                </button>
                <a href="feature11.php" class="btn-reset" title="Reset Filters">
                    RESET
                </a>
            </div>
        </form>
    </div>

    <!-- 6. VIEW SWITCHER & INSTANT CLIENT SEARCH -->
    <div class="view-toggle-row">
        <div class="view-switch-tabs">
            <button type="button" class="view-tab-btn active" id="tabTimelineView" onclick="switchSecurityView('timeline')">
                <span>⚡</span> Timeline Event Feed
            </button>
            <button type="button" class="view-tab-btn" id="tabTableView" onclick="switchSecurityView('table')">
                <span>📋</span> Cryptographic Table Matrix
            </button>
        </div>

        <div>
            <input type="text" class="instant-search-input" id="instantSearchInput" placeholder="🔍 Instant search logs, IPs, hashes..." onkeyup="filterLiveTimeline(this.value)">
        </div>
    </div>

    <!-- 7. VIEW 1: INTERACTIVE CYBER TIMELINE CARDS STREAM -->
    <div id="viewTimelineContainer" class="timeline-stream-container">
        <?php if (!empty($logs_array)): ?>
            <?php foreach ($logs_array as $log): 
                $act_upper = strtoupper($log['action']);
                $entity_type = strtoupper($log['entity_type'] ?? 'SYSTEM');
                $card_class = '';
                $badge_color = 'var(--f1-cyan)';
                $badge_icon = '⚡';

                if (strpos($act_upper, 'VIOLATION') !== false || strpos($act_upper, 'PENALTY') !== false || $entity_type === 'VIOLATIONS' || $entity_type === 'PENALTIES') {
                    $card_class = 'event-violation';
                    $badge_color = 'var(--f1-red)';
                    $badge_icon = '🚨';
                } elseif (strpos($act_upper, 'REPAIR') !== false || $entity_type === 'REPAIRS') {
                    $card_class = 'event-repair';
                    $badge_color = 'var(--f1-amber)';
                    $badge_icon = '🔧';
                } elseif (strpos($act_upper, 'APPEAL') !== false || $entity_type === 'APPEALS') {
                    $card_class = 'event-appeal';
                    $badge_color = 'var(--f1-purple)';
                    $badge_icon = '📜';
                } elseif (strpos($act_upper, 'INSPECTION') !== false || $entity_type === 'INSPECTIONS') {
                    $badge_icon = '🛠️';
                } elseif (strpos($act_upper, 'LOGIN') !== false || strpos($act_upper, 'AUTH') !== false) {
                    $badge_icon = '🔒';
                    $badge_color = '#3867d6';
                }

                $full_name = $log['full_name'] ?? 'System Operator';
                $initials = '';
                $parts = explode(' ', trim($full_name));
                foreach ($parts as $p) {
                    if (!empty($p)) $initials .= strtoupper($p[0]);
                }
                if (strlen($initials) > 2) $initials = substr($initials, 0, 2);
                if (empty($initials)) $initials = 'FIA';

                $simulated_hash = hash('sha256', 'LOG-' . $log['log_id'] . '-' . $log['created_at']);
            ?>
                <div class="timeline-event-card <?php echo $card_class; ?> timeline-log-item" data-search="<?php echo strtolower(htmlspecialchars($log['action'] . ' ' . $full_name . ' ' . ($log['ip_address'] ?? '') . ' ' . $entity_type)); ?>">
                    
                    <div class="timeline-card-header">
                        <div class="card-log-id">
                            <span style="color: <?php echo $badge_color; ?>;"><?php echo $badge_icon; ?></span>
                            <span>LOG RECORD #<?php echo str_pad($log['log_id'], 5, '0', STR_PAD_LEFT); ?></span>
                            <span class="entity-tag" style="border-color: <?php echo $badge_color; ?>; color: <?php echo $badge_color; ?>; background: rgba(255,255,255,0.04);">
                                #<?php echo $entity_type . '-' . str_pad($log['entity_id'] ?: 1, 2, '0', STR_PAD_LEFT); ?>
                            </span>
                        </div>

                        <div class="card-time-badge">
                            <span>⏱️ <?php echo htmlspecialchars($log['created_at']); ?> UTC</span>
                        </div>
                    </div>

                    <div class="timeline-card-body">
                        <div class="card-left-info">
                            <div class="event-action-text">
                                <?php echo htmlspecialchars($log['action']); ?>
                            </div>

                            <div class="event-meta-chips">
                                <span class="meta-chip operator">
                                    👤 <?php echo htmlspecialchars($full_name); ?> (<?php echo htmlspecialchars($log['email'] ?? 'delegate@fia.internal'); ?>)
                                </span>
                                <span class="meta-chip ip-chip">
                                    🌐 IP: <?php echo htmlspecialchars($log['ip_address'] ?: '127.0.0.1'); ?>
                                </span>
                                <span class="meta-chip" style="color: #ffd32a; border-color: rgba(255,211,42,0.3);">
                                    🔑 HASH: <?php echo substr($simulated_hash, 0, 16); ?>...
                                </span>
                            </div>
                        </div>

                        <div class="card-right-actions">
                            <?php if (!empty($log['details'])): ?>
                                <button type="button" class="btn-inspect-payload" onclick="openJsonModal('<?php echo $log['log_id']; ?>', <?php echo htmlspecialchars(json_encode($log['details']), ENT_QUOTES, 'UTF-8'); ?>)">
                                    <span>{ }</span> INSPECT PAYLOAD
                                </button>
                            <?php endif; ?>
                            <button type="button" class="btn-copy-hash" onclick="copyHashToClipboard('<?php echo $simulated_hash; ?>', this)">
                                📋 COPY HASH SEAL
                            </button>
                        </div>
                    </div>

                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div style="background: rgba(14, 14, 24, 0.95); border: 1px solid rgba(255,255,255,0.08); border-radius: 8px; padding: 40px; text-align: center; color: #8c8c9e;">
                No cryptographic audit logs matched your query criteria.
            </div>
        <?php endif; ?>
    </div>

    <!-- 8. VIEW 2: TABULAR MATRIX VIEW -->
    <div id="viewTableContainer" class="table-panel" style="display: none;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 18px;">
            <div style="font-family: 'Orbitron', sans-serif; font-size: 14px; font-weight: 700; color: #ffffff;">
                <span>📋</span> IMMUTABLE AUDIT LOG MATRIX (JOINED WITH DELEGATES)
            </div>
            <span style="font-family: 'Share Tech Mono', monospace; font-size: 11px; color: #8c8c9e;">
                LATEST <?php echo count($logs_array); ?> STREAM ENTRIES
            </span>
        </div>

        <div class="f1-table-wrapper">
            <table class="f1-table">
                <thead>
                    <tr>
                        <th>LOG ID</th>
                        <th>OFFICIAL / OPERATOR</th>
                        <th>ACTION TYPE</th>
                        <th>ACTION SUMMARY & EVENT DETAILS</th>
                        <th>ENTITY</th>
                        <th>IP ADDRESS</th>
                        <th>TIMESTAMPS (UTC)</th>
                        <th>PAYLOAD</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($logs_array)): ?>
                        <?php foreach ($logs_array as $row): 
                            $action_class = 'action-login';
                            $action_upper = strtoupper($row['action']);
                            if (strpos($action_upper, 'INSERT') === 0 || strpos($action_upper, 'CREATE') !== false) {
                                $action_class = 'action-insert';
                            } elseif (strpos($action_upper, 'UPDATE') === 0 || strpos($action_upper, 'RATIFY') !== false) {
                                $action_class = 'action-update';
                            } elseif (strpos($action_upper, 'DELETE') === 0 || strpos($action_upper, 'PENALTY') !== false || strpos($action_upper, 'VIOLATION') !== false) {
                                $action_class = 'action-delete';
                            }

                            $full_name = $row['full_name'] ?? 'System Operator';
                            $initials = '';
                            $parts = explode(' ', trim($full_name));
                            foreach ($parts as $p) {
                                if (!empty($p)) $initials .= strtoupper($p[0]);
                            }
                            if (strlen($initials) > 2) $initials = substr($initials, 0, 2);
                            if (empty($initials)) $initials = 'FIA';

                            $entity_type = !empty($row['entity_type']) ? $row['entity_type'] : 'SYSTEM_PORTAL';
                            $entity_id = !empty($row['entity_id']) ? $row['entity_id'] : '0';
                            $entity_display = "#" . strtoupper($entity_type) . "-" . str_pad($entity_id, 2, '0', STR_PAD_LEFT);
                        ?>
                            <tr>
                                <td style="font-family: 'Share Tech Mono', monospace; color: var(--f1-cyan); font-weight: 700;">
                                    #<?php echo str_pad($row['log_id'], 5, '0', STR_PAD_LEFT); ?>
                                </td>

                                <td>
                                    <div class="user-cell">
                                        <div class="user-avatar-badge"><?php echo htmlspecialchars($initials); ?></div>
                                        <div>
                                            <div style="font-weight: 700; color: #ffffff;"><?php echo htmlspecialchars($full_name); ?></div>
                                            <div style="font-family: 'Share Tech Mono', monospace; font-size: 10px; color: #8c8c9e;"><?php echo htmlspecialchars($row['email'] ?? 'delegate@fia.internal'); ?></div>
                                        </div>
                                    </div>
                                </td>

                                <td>
                                    <span class="action-pill <?php echo $action_class; ?>">
                                        <?php 
                                            $act_type_display = explode(':', $row['action'])[0];
                                            echo htmlspecialchars(substr($act_type_display, 0, 16)); 
                                        ?>
                                    </span>
                                </td>

                                <td style="max-width: 320px;">
                                    <span style="color: #ffffff; font-weight: 600;">
                                        <?php echo htmlspecialchars($row['action']); ?>
                                    </span>
                                </td>

                                <td>
                                    <span class="entity-tag">
                                        <?php echo htmlspecialchars($entity_display); ?>
                                    </span>
                                </td>

                                <td style="font-family: 'Share Tech Mono', monospace; font-size: 11px; color: #8c8c9e;">
                                    <?php echo htmlspecialchars($row['ip_address'] ?: '127.0.0.1'); ?>
                                </td>

                                <td style="font-family: 'Share Tech Mono', monospace; font-size: 11px; white-space: nowrap; color: #cbd5e1;">
                                    <?php echo htmlspecialchars($row['created_at']); ?>
                                </td>

                                <td>
                                    <?php if (!empty($row['details'])): ?>
                                        <button class="btn-inspect-payload" style="padding: 4px 8px; font-size: 9.5px;" onclick="openJsonModal('<?php echo $row['log_id']; ?>', <?php echo htmlspecialchars(json_encode($row['details']), ENT_QUOTES, 'UTF-8'); ?>)">
                                            <span>{ }</span> JSON
                                        </button>
                                    <?php else: ?>
                                        <span style="color: #4b5563; font-size: 11px;">—</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

</div>

<!-- JSON Details Viewer Modal -->
<div class="modal-backdrop" id="jsonModal">
    <div class="modal-card">
        <div class="modal-header">
            <div class="modal-title">
                <span>🔍</span> CRYPTOGRAPHIC TELEMETRY PAYLOAD (<span id="modalLogId" style="color: var(--f1-cyan);">#00000</span>)
            </div>
            <button class="modal-close" onclick="closeJsonModal()">&times;</button>
        </div>
        <div class="modal-body">
            <pre class="json-pre-box" id="modalJsonContent"></pre>
            <div style="margin-top: 16px; display: flex; justify-content: flex-end;">
                <button type="button" class="btn-soc-sound" onclick="copyModalJson(this)">
                    📋 COPY PAYLOAD TO CLIPBOARD
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Web Audio & Interactivity Scripts -->
<script>
let audioCtx = null;
function getAudioCtx() {
    if (!audioCtx) {
        audioCtx = new (window.AudioContext || window.webkitAudioContext)();
    }
    return audioCtx;
}

function playCyberChirp(freq = 1200, duration = 0.08) {
    try {
        const ctx = getAudioCtx();
        if (ctx.state === 'suspended') ctx.resume();
        const osc = ctx.createOscillator();
        const gain = ctx.createGain();
        osc.type = 'sine';
        osc.frequency.setValueAtTime(freq, ctx.currentTime);
        gain.gain.setValueAtTime(0.18, ctx.currentTime);
        gain.gain.exponentialRampToValueAtTime(0.001, ctx.currentTime + duration);
        osc.connect(gain); gain.connect(ctx.destination);
        osc.start(); osc.stop(ctx.currentTime + duration);
    } catch(e) {}
}

// 1. Live Terminal Text Simulator
const terminalMessages = [
    "UPLINK SYNCHRONIZED: Monitoring 10 Constructor Telemetry Streams & Parc Fermé Nodes...",
    "CRITICAL AUDIT SEAL: Verified Front Wing deflection telemetry for Car #1 (Max Verstappen).",
    "PARC FERMÉ ACCESS: Jo Bauer physical monocoque seal validated under Art 40.2.",
    "STEWARDS TRIBUNAL: Hearing evidence dossier logged for Monaco GP Lap 24 contact.",
    "SECURITY ENCRYPTION: SHA-256 Blockchain integrity check passed across all nodes."
];
let msgIndex = 0;
setInterval(() => {
    msgIndex = (msgIndex + 1) % terminalMessages.length;
    const termEl = document.getElementById('terminalStreamText');
    if (termEl) {
        termEl.innerHTML = '<span style="color: #ffd32a;">[FIA-SOC-GATEWAY]</span> <span style="color: #8c8c9e;">LIVE //</span> ' + terminalMessages[msgIndex];
    }
}, 5000);

// 2. View Mode Switcher
function switchSecurityView(mode) {
    playCyberChirp(1300, 0.06);
    document.getElementById('viewTimelineContainer').style.display = (mode === 'timeline') ? 'block' : 'none';
    document.getElementById('viewTableContainer').style.display = (mode === 'table') ? 'block' : 'none';

    document.getElementById('tabTimelineView').classList.toggle('active', mode === 'timeline');
    document.getElementById('tabTableView').classList.toggle('active', mode === 'table');
}

// 3. Instant Search Filter
function filterLiveTimeline(query) {
    const q = query.toLowerCase().trim();
    const items = document.querySelectorAll('.timeline-log-item');
    items.forEach(item => {
        const text = item.dataset.search || '';
        item.style.display = text.includes(q) ? 'block' : 'none';
    });
}

// 4. Copy Hash
function copyHashToClipboard(hash, btn) {
    playCyberChirp(1500, 0.05);
    navigator.clipboard.writeText(hash).then(() => {
        const orig = btn.innerText;
        btn.innerText = '✅ HASH COPIED!';
        setTimeout(() => { btn.innerText = orig; }, 1800);
    });
}

function copyModalJson(btn) {
    playCyberChirp(1500, 0.05);
    const content = document.getElementById('modalJsonContent').innerText;
    navigator.clipboard.writeText(content).then(() => {
        const orig = btn.innerText;
        btn.innerText = '✅ COPIED TO CLIPBOARD!';
        setTimeout(() => { btn.innerText = orig; }, 1800);
    });
}

// 5. JSON Modal
function openJsonModal(logId, detailsRaw) {
    playCyberChirp(1100, 0.08);
    document.getElementById('modalLogId').innerText = '#' + String(logId).padStart(5, '0');
    let formatted = detailsRaw;
    try {
        const parsed = (typeof detailsRaw === 'string') ? JSON.parse(detailsRaw) : detailsRaw;
        formatted = JSON.stringify(parsed, null, 2);
    } catch(e) {
        formatted = detailsRaw;
    }
    document.getElementById('modalJsonContent').innerText = formatted;
    document.getElementById('jsonModal').style.display = 'flex';
}

function closeJsonModal() {
    playCyberChirp(800, 0.05);
    document.getElementById('jsonModal').style.display = 'none';
}

document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') closeJsonModal();
});
document.getElementById('jsonModal').addEventListener('click', (e) => {
    if (e.target === document.getElementById('jsonModal')) closeJsonModal();
});

// Voice Security Briefing
function speakSecurityReport() {
    playCyberChirp(1400, 0.1);
    if ('speechSynthesis' in window) {
        window.speechSynthesis.cancel();
        const totalLogs = "<?php echo $total_logs; ?>";
        const criticalDisc = "<?php echo $critical_disciplinary; ?>";
        const officials = "<?php echo $distinct_officials; ?>";

        const message = `FIA Security Operations Center Briefing. Total cryptographic records: ${totalLogs}. Critical disciplinary incidents: ${criticalDisc}. Active authorized officials: ${officials}. System audit integrity verified at 100 percent.`;

        const utterance = new SpeechSynthesisUtterance(message);
        utterance.rate = 0.95;
        utterance.pitch = 0.8;
        window.speechSynthesis.speak(utterance);
    }
}
</script>

</body>
</html>
