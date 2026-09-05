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
// Restrict Dashboard strictly to Admin (Race Director)
check_role_access(['admin']);

// Fetch Core Metrics
$teams_count = 0;
$t_res = $conn->query("SELECT COUNT(*) as total FROM TEAMS");
if ($t_res) $teams_count = $t_res->fetch_assoc()['total'] ?? 0;

$cars_count = 0;
$c_res = $conn->query("SELECT COUNT(*) as total FROM CARS");
if ($c_res) $cars_count = $c_res->fetch_assoc()['total'] ?? 0;

$drivers_count = 0;
$d_res = $conn->query("SELECT COUNT(*) as total FROM DRIVERS");
if ($d_res) $drivers_count = $d_res->fetch_assoc()['total'] ?? 0;

$logs_count = 0;
$pass_count = 0;
$meas_res = $conn->query("SELECT COUNT(*) as total, SUM(CASE WHEN LOWER(result) = 'passed' THEN 1 ELSE 0 END) as passed FROM INSPECTION_MEASUREMENTS");
if ($meas_res && $m_row = $meas_res->fetch_assoc()) {
    $logs_count = intval($m_row['total'] ?? 0);
    $pass_count = intval($m_row['passed'] ?? 0);
}

// Fetch Steward Cases count
$stewards_count = 0;
$active_penalties = 0;
$st_table_check = $conn->query("SHOW TABLES LIKE 'steward_infringements'");
if ($st_table_check && $st_table_check->num_rows > 0) {
    $st_res = $conn->query("SELECT COUNT(*) as total, SUM(CASE WHEN status = 'Penalty Applied' THEN 1 ELSE 0 END) as penalties FROM steward_infringements");
    if ($st_res && $s_row = $st_res->fetch_assoc()) {
        $stewards_count = intval($s_row['total'] ?? 0);
        $active_penalties = intval($s_row['penalties'] ?? 0);
    }
}

// Fetch Feature 5 Technical Violations Count
$violations_count = 0;
$v_table_check = $conn->query("SHOW TABLES LIKE 'technical_violations'");
if ($v_table_check && $v_table_check->num_rows > 0) {
    $v_res = $conn->query("SELECT COUNT(*) as total FROM technical_violations");
    if ($v_res && $v_row = $v_res->fetch_assoc()) {
        $violations_count = intval($v_row['total'] ?? 0);
    }
}

// Fetch Feature 6 Steward Decisions Count
$decisions_count = 0;
$d_table_check = $conn->query("SHOW TABLES LIKE 'steward_decisions'");
if ($d_table_check && $d_table_check->num_rows > 0) {
    $d_res = $conn->query("SELECT COUNT(*) as total FROM steward_decisions");
    if ($d_res && $d_row = $d_res->fetch_assoc()) {
        $decisions_count = intval($d_row['total'] ?? 0);
    }
}

// Fetch Feature 7 Vehicle Repairs & Re-Inspections Count
$repairs_count = 0;
$reinspections_count = 0;
$rep_table_check = $conn->query("SHOW TABLES LIKE 'vehicle_repairs'");
if ($rep_table_check && $rep_table_check->num_rows > 0) {
    $rep_res = $conn->query("SELECT COUNT(*) as total FROM vehicle_repairs");
    if ($rep_res && $r_row = $rep_res->fetch_assoc()) {
        $repairs_count = intval($r_row['total'] ?? 0);
    }
}
$reins_table_check = $conn->query("SHOW TABLES LIKE 'vehicle_reinspections'");
if ($reins_table_check && $reins_table_check->num_rows > 0) {
    $reins_res = $conn->query("SELECT COUNT(*) as total FROM vehicle_reinspections WHERE LOWER(result) = 'passed'");
    if ($reins_res && $re_row = $reins_res->fetch_assoc()) {
        $reinspections_count = intval($re_row['total'] ?? 0);
    }
}

// Fetch Latest Feed Events (Combined Scrutineering, Stewards, Violations, Decisions & Repairs)
$recent_events = [];

// 1. Scrutineering latest
$latest_meas = $conn->query("SELECT m.measurement_id, m.measurement_name, m.result, m.created_at, c.car_name, c.chassis_number 
                             FROM INSPECTION_MEASUREMENTS m 
                             JOIN INSPECTION_SESSIONS s ON m.session_id = s.session_id 
                             JOIN CARS c ON s.car_id = c.car_id 
                             ORDER BY m.measurement_id DESC LIMIT 2");
if ($latest_meas) {
    while ($m = $latest_meas->fetch_assoc()) {
        $recent_events[] = [
            'type' => 'scrutineering',
            'badge' => 'TECH SCRUTINEERING',
            'title' => $m['measurement_name'] . ' — ' . $m['car_name'],
            'status' => $m['result'],
            'time' => $m['created_at'],
            'is_violation' => (strcasecmp($m['result'], 'Passed') !== 0)
        ];
    }
}

// 2. Steward latest
if ($st_table_check && $st_table_check->num_rows > 0) {
    $latest_stew = $conn->query("SELECT i.case_number, i.penalty_type, i.status, i.created_at, c.car_name, d.full_name as driver_name 
                                 FROM steward_infringements i 
                                 LEFT JOIN CARS c ON i.car_id = c.car_id 
                                 LEFT JOIN DRIVERS d ON i.driver_id = d.driver_id 
                                 ORDER BY i.infringement_id DESC LIMIT 2");
    if ($latest_stew) {
        while ($s = $latest_stew->fetch_assoc()) {
            $recent_events[] = [
                'type' => 'steward',
                'badge' => 'STEWARDS RULING',
                'title' => $s['case_number'] . ' — ' . ($s['driver_name'] ?: ($s['car_name'] ?: 'Grid Competitor')),
                'status' => $s['penalty_type'] . ' (' . $s['status'] . ')',
                'time' => $s['created_at'],
                'is_violation' => ($s['status'] === 'Penalty Applied')
            ];
        }
    }
}

// 3. Technical Violations latest (Feature 5)
if ($v_table_check && $v_table_check->num_rows > 0) {
    $latest_viol = $conn->query("SELECT id, vehicle_name, parameter, actual_value, severity, detected_at FROM technical_violations ORDER BY id DESC LIMIT 2");
    if ($latest_viol) {
        while ($v = $latest_viol->fetch_assoc()) {
            $recent_events[] = [
                'type' => 'violation',
                'badge' => 'VIOLATION DETECTED',
                'title' => '#' . $v['id'] . ' ' . $v['parameter'] . ' (' . $v['actual_value'] . ')',
                'status' => $v['severity'],
                'time' => $v['detected_at'],
                'is_violation' => true
            ];
        }
    }
}

// 4. Official Steward Decisions latest (Feature 6)
if ($d_table_check && $d_table_check->num_rows > 0) {
    $latest_dec = $conn->query("SELECT decision_code, driver_name, penalty_type, decision_status, created_at FROM steward_decisions ORDER BY id DESC LIMIT 2");
    if ($latest_dec) {
        while ($d = $latest_dec->fetch_assoc()) {
            $recent_events[] = [
                'type' => 'decision',
                'badge' => 'JUDICIAL DECISION',
                'title' => '#' . $d['decision_code'] . ' ' . $d['penalty_type'] . ' — ' . $d['driver_name'],
                'status' => $d['decision_status'],
                'time' => $d['created_at'],
                'is_violation' => true
            ];
        }
    }
}

// 5. Vehicle Repairs latest (Feature 7)
if ($rep_table_check && $rep_table_check->num_rows > 0) {
    $latest_rep = $conn->query("SELECT id, vehicle_name, performed_by, status, created_at FROM vehicle_repairs ORDER BY id DESC LIMIT 2");
    if ($latest_rep) {
        while ($r = $latest_rep->fetch_assoc()) {
            $recent_events[] = [
                'type' => 'repair',
                'badge' => 'REPAIR COMPLETED',
                'title' => '#' . $r['id'] . ' ' . $r['vehicle_name'] . ' (' . $r['performed_by'] . ')',
                'status' => $r['status'],
                'time' => $r['created_at'],
                'is_violation' => false
            ];
        }
    }
}

// 6. Steward Appeals latest (Feature 9)
$apl_check = $conn->query("SHOW TABLES LIKE 'APPEALS'");
if ($apl_check && $apl_check->num_rows > 0) {
    $latest_apl = $conn->query("SELECT appeal_id, status, submitted_at FROM APPEALS ORDER BY appeal_id DESC LIMIT 2");
    if ($latest_apl) {
        while ($a = $latest_apl->fetch_assoc()) {
            $recent_events[] = [
                'type' => 'appeal',
                'badge' => 'APPEAL PROTEST',
                'title' => '#APL-' . $a['appeal_id'] . ' Protest Filed',
                'status' => $a['status'],
                'time' => $a['submitted_at'],
                'is_violation' => false
            ];
        }
    }
}

// Sort combined feed by time descending
usort($recent_events, function($a, $b) {
    return strtotime($b['time']) - strtotime($a['time']);
});
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>FSMS | Central Race Command Hub</title>
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
            --f1-purple: #9d4edd;
            --f1-dark: #06060c;
            --f1-panel: rgba(18, 18, 28, 0.95);
            --f1-border: rgba(255, 255, 255, 0.08);
            --flag-color: #2ecc71;
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

        /* Scanline CRT overlay */
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
        .ambient-glow-cyan {
            position: absolute;
            width: 600px;
            height: 600px;
            background: radial-gradient(circle, rgba(0, 210, 190, 0.12) 0%, transparent 65%);
            bottom: -100px;
            left: -100px;
            pointer-events: none;
            z-index: 0;
            filter: blur(50px);
        }

        /* Telemetry Ribbon */
        .telemetry-bar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: rgba(14, 14, 24, 0.95);
            border: 1px solid var(--f1-border);
            border-left: 4px solid var(--flag-color);
            border-radius: 8px;
            padding: 12px 20px;
            margin-bottom: 24px;
            font-family: 'Share Tech Mono', monospace;
            font-size: 12px;
            box-shadow: 0 0 25px rgba(0, 210, 190, 0.12);
            position: relative;
            z-index: 10;
            transition: all 0.3s ease;
        }
        .telemetry-status {
            display: flex;
            align-items: center;
            gap: 12px;
            color: #ffffff;
            font-weight: 700;
        }
        .pulse-dot {
            width: 10px;
            height: 10px;
            background: var(--flag-color);
            border-radius: 50%;
            box-shadow: 0 0 10px var(--flag-color);
            animation: pulse 1.2s infinite;
        }
        @keyframes pulse {
            0% { transform: scale(0.9); opacity: 0.6; }
            50% { transform: scale(1.3); opacity: 1; filter: drop-shadow(0 0 8px var(--flag-color)); }
            100% { transform: scale(0.9); opacity: 0.6; }
        }

        /* Interactive Start Lights Reaction Rig */
        .lights-strip-interactive {
            display: flex;
            align-items: center;
            gap: 10px;
            background: rgba(0, 0, 0, 0.5);
            padding: 6px 14px;
            border-radius: 20px;
            border: 1px solid rgba(255, 255, 255, 0.1);
            cursor: pointer;
            transition: all 0.25s;
        }
        .lights-strip-interactive:hover {
            border-color: var(--f1-red);
            box-shadow: 0 0 15px rgba(255, 24, 1, 0.3);
        }
        .lights-box {
            display: flex;
            gap: 6px;
        }
        .light-bulb {
            width: 14px;
            height: 14px;
            border-radius: 50%;
            background: #1c0505;
            border: 1px solid #3d0a0a;
            transition: all 0.15s ease;
        }
        .light-bulb.on {
            background: #ff1801;
            box-shadow: 0 0 14px #ff1801, inset 0 0 4px #ffffff;
        }
        .light-bulb.green {
            background: #2ecc71;
            box-shadow: 0 0 14px #2ecc71, inset 0 0 4px #ffffff;
        }

        /* Flag Status Selector Bar */
        .flag-switchboard {
            display: flex;
            align-items: center;
            gap: 8px;
            background: rgba(18, 18, 28, 0.95);
            border: 1px solid var(--f1-border);
            border-radius: 10px;
            padding: 10px 16px;
            margin-bottom: 24px;
            position: relative;
            z-index: 10;
            overflow-x: auto;
        }
        .switchboard-title {
            font-family: 'Share Tech Mono', monospace;
            font-size: 11px;
            font-weight: 700;
            color: #8c8c9e;
            text-transform: uppercase;
            letter-spacing: 1px;
            white-space: nowrap;
            margin-right: 8px;
        }
        .flag-btn {
            background: rgba(255, 255, 255, 0.03);
            border: 1px solid rgba(255, 255, 255, 0.08);
            color: #8c8c9e;
            padding: 6px 12px;
            border-radius: 6px;
            font-family: 'Orbitron', sans-serif;
            font-size: 10px;
            font-weight: 800;
            letter-spacing: 1px;
            cursor: pointer;
            transition: all 0.2s;
            white-space: nowrap;
        }
        .flag-btn:hover { color: #ffffff; background: rgba(255, 255, 255, 0.08); }
        .flag-btn.active.green { background: rgba(46, 204, 113, 0.18); border-color: #2ecc71; color: #2ecc71; box-shadow: 0 0 12px rgba(46, 204, 113, 0.3); }
        .flag-btn.active.sc { background: rgba(255, 183, 3, 0.18); border-color: #ffb703; color: #ffb703; box-shadow: 0 0 12px rgba(255, 183, 3, 0.3); }
        .flag-btn.active.vsc { background: rgba(157, 78, 221, 0.18); border-color: #9d4edd; color: #c77dff; box-shadow: 0 0 12px rgba(157, 78, 221, 0.3); }
        .flag-btn.active.red { background: rgba(255, 24, 1, 0.18); border-color: #ff1801; color: #ff4757; box-shadow: 0 0 12px rgba(255, 24, 1, 0.3); }
        .flag-btn.active.chequered { background: rgba(255, 255, 255, 0.2); border-color: #ffffff; color: #ffffff; }

        /* HUD Metric Grid */
        .hud-grid {
            display: grid;
            grid-template-columns: repeat(5, 1fr);
            gap: 16px;
            margin-bottom: 28px;
            position: relative;
            z-index: 10;
        }
        .hud-card {
            background: linear-gradient(135deg, rgba(20, 20, 32, 0.9) 0%, rgba(12, 12, 20, 0.95) 100%);
            border: 1px solid var(--f1-border);
            border-radius: 10px;
            padding: 20px;
            position: relative;
            overflow: hidden;
            transition: all 0.3s ease;
            box-shadow: 0 10px 30px rgba(0,0,0,0.5);
        }
        .hud-card::after {
            content: '';
            position: absolute;
            top: 0; left: 0; width: 100%; height: 3px;
            background: linear-gradient(90deg, #ff1801, transparent);
        }
        .hud-card.cyan::after { background: linear-gradient(90deg, #00d2be, transparent); }
        .hud-card.amber::after { background: linear-gradient(90deg, #ffb703, transparent); }
        .hud-card.green::after { background: linear-gradient(90deg, #2ecc71, transparent); }
        .hud-card.red::after { background: linear-gradient(90deg, #ff1801, transparent); }
        .hud-card.purple::after { background: linear-gradient(90deg, #a855f7, transparent); }
        .hud-card.teal::after { background: linear-gradient(90deg, #14b8a6, transparent); }
        .hud-card:hover {
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
            font-size: 32px;
            font-weight: 900;
            color: #ffffff;
            line-height: 1;
        }

        /* Feature Module Cards */
        .feature-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 20px;
            margin-bottom: 28px;
            position: relative;
            z-index: 10;
        }
        .feature-card {
            background: linear-gradient(135deg, #161624 0%, #0e0e18 100%);
            border: 1px solid #242436;
            border-radius: 12px;
            padding: 24px;
            position: relative;
            overflow: hidden;
            text-decoration: none;
            color: inherit;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            transition: all 0.3s cubic-bezier(0.165, 0.84, 0.44, 1);
            box-shadow: 0 10px 30px rgba(0,0,0,0.6);
        }
        .feature-card::before {
            content: '';
            position: absolute;
            top: 0; left: 0; width: 4px; height: 100%;
            background: #ff1801;
            transition: width 0.3s ease;
        }
        .feature-card.cyan::before { background: #00d2be; }
        .feature-card.amber::before { background: #ffb703; }
        .feature-card.green::before { background: #2ecc71; }
        .feature-card.red::before { background: #ff1801; }
        .feature-card.purple::before { background: #a855f7; }
        .feature-card.teal::before { background: #14b8a6; }
        .feature-card:hover {
            transform: translateY(-4px) scale(1.01);
            border-color: rgba(255, 255, 255, 0.2);
            box-shadow: 0 15px 45px rgba(0,0,0,0.9);
        }
        .feature-badge {
            display: inline-block;
            font-family: 'Orbitron', sans-serif;
            font-size: 10px;
            font-weight: 900;
            padding: 3px 8px;
            border-radius: 4px;
            background: rgba(255, 24, 1, 0.15);
            color: #ff3838;
            border: 1px solid rgba(255, 24, 1, 0.3);
            margin-bottom: 12px;
            letter-spacing: 1px;
            align-self: flex-start;
        }
        .feature-badge.cyan { background: rgba(0, 210, 190, 0.15); color: #00d2be; border-color: rgba(0, 210, 190, 0.3); }
        .feature-badge.amber { background: rgba(255, 183, 3, 0.15); color: #ffb703; border-color: rgba(255, 183, 3, 0.3); }
        .feature-badge.green { background: rgba(46, 204, 113, 0.15); color: #2ecc71; border-color: rgba(46, 204, 113, 0.3); }
        .feature-badge.red { background: rgba(255, 24, 1, 0.15); color: #ff1801; border-color: rgba(255, 24, 1, 0.3); }
        .feature-badge.purple { background: rgba(168, 85, 247, 0.15); color: #a855f7; border-color: rgba(168, 85, 247, 0.3); }
        .feature-badge.teal { background: rgba(20, 184, 166, 0.15); color: #14b8a6; border-color: rgba(20, 184, 166, 0.3); }
        .feature-title {
            font-family: 'Orbitron', sans-serif;
            font-size: 17px;
            font-weight: 800;
            color: #ffffff;
            margin-bottom: 8px;
        }
        .feature-desc {
            color: #8c8c9e;
            font-size: 13px;
            line-height: 1.5;
            margin-bottom: 18px;
        }
        .btn-portal-action {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            font-family: 'Orbitron', sans-serif;
            font-size: 11px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 1px;
            color: #ffffff;
            margin-top: auto;
        }

        /* Mission Control Dynamic Dual Rig */
        .command-rig-grid {
            display: grid;
            grid-template-columns: 1.15fr 0.85fr;
            gap: 20px;
            position: relative;
            z-index: 10;
        }
        .command-panel {
            background: linear-gradient(135deg, rgba(18, 18, 28, 0.95) 0%, rgba(10, 10, 18, 0.98) 100%);
            border: 1px solid var(--f1-border);
            border-radius: 12px;
            padding: 24px;
            box-shadow: 0 15px 45px rgba(0,0,0,0.7);
        }
        .panel-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 1px solid rgba(255, 255, 255, 0.08);
            padding-bottom: 12px;
            margin-bottom: 18px;
        }
        .panel-title {
            font-family: 'Orbitron', sans-serif;
            font-size: 14px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 1.5px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        /* Circuit & Weather Selector */
        .circuit-bar {
            display: flex;
            gap: 8px;
            margin-bottom: 16px;
            flex-wrap: wrap;
        }
        .circuit-chip {
            background: rgba(255, 255, 255, 0.04);
            border: 1px solid #28283a;
            color: #8c8c9e;
            padding: 6px 12px;
            border-radius: 6px;
            font-family: 'Share Tech Mono', monospace;
            font-size: 11px;
            cursor: pointer;
            transition: all 0.2s;
        }
        .circuit-chip:hover, .circuit-chip.active {
            background: rgba(0, 210, 190, 0.15);
            border-color: #00d2be;
            color: #ffffff;
            box-shadow: 0 0 10px rgba(0, 210, 190, 0.3);
        }

        .weather-matrix {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 12px;
            margin-bottom: 18px;
        }
        .weather-cell {
            background: #0a0a14;
            border: 1px solid #202030;
            border-radius: 8px;
            padding: 12px;
            text-align: center;
        }
        .weather-prop {
            font-family: 'Share Tech Mono', monospace;
            font-size: 10px;
            color: #717188;
            text-transform: uppercase;
            margin-bottom: 4px;
        }
        .weather-val {
            font-family: 'Orbitron', sans-serif;
            font-size: 16px;
            font-weight: 700;
            color: #00d2be;
        }

        /* Speed Trap Leaderboard */
        .speed-bar-row {
            display: flex;
            flex-direction: column;
            gap: 4px;
            margin-bottom: 12px;
        }
        .speed-meta {
            display: flex;
            justify-content: space-between;
            font-family: 'Share Tech Mono', monospace;
            font-size: 11px;
            color: #a0a0b2;
        }
        .speed-progress-track {
            height: 6px;
            background: #141422;
            border-radius: 3px;
            overflow: hidden;
        }
        .speed-fill {
            height: 100%;
            background: linear-gradient(90deg, #00d2be, #ff1801);
            border-radius: 3px;
            transition: width 1s ease-in-out;
        }

        /* Team Radio Simulator & Voice Comms */
        .radio-box {
            background: #080812;
            border: 1px solid #232338;
            border-radius: 8px;
            padding: 14px;
            margin-bottom: 16px;
            display: flex;
            align-items: center;
            gap: 12px;
            position: relative;
            overflow: hidden;
        }
        .radio-icon {
            font-size: 24px;
            color: #ff1801;
            animation: radioGlow 1.5s infinite alternate;
        }
        @keyframes radioGlow {
            0% { transform: scale(1); filter: drop-shadow(0 0 2px #ff1801); }
            100% { transform: scale(1.15); filter: drop-shadow(0 0 10px #ff1801); }
        }
        .radio-msg-title {
            font-family: 'Share Tech Mono', monospace;
            font-size: 10px;
            color: #ff4757;
            font-weight: 700;
            letter-spacing: 1px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .radio-msg-body {
            font-family: 'Share Tech Mono', monospace;
            font-size: 13px;
            color: #ffffff;
            margin-top: 2px;
            font-weight: 600;
        }
        .radio-controls {
            margin-left: auto;
            display: flex;
            gap: 6px;
        }
        .btn-radio-play {
            background: rgba(255, 24, 1, 0.15);
            border: 1px solid #ff1801;
            color: #ffffff;
            padding: 6px 12px;
            border-radius: 4px;
            font-family: 'Orbitron', sans-serif;
            font-size: 10px;
            font-weight: 800;
            cursor: pointer;
            white-space: nowrap;
            transition: all 0.2s;
        }
        .btn-radio-play:hover { background: #ff1801; box-shadow: 0 0 12px rgba(255, 24, 1, 0.6); }
        .btn-radio-replay {
            background: rgba(0, 210, 190, 0.15);
            border: 1px solid #00d2be;
            color: #00d2be;
            padding: 6px 10px;
            border-radius: 4px;
            font-family: 'Orbitron', sans-serif;
            font-size: 10px;
            font-weight: 800;
            cursor: pointer;
            white-space: nowrap;
            transition: all 0.2s;
        }
        .btn-radio-replay:hover { background: #00d2be; color: #08080e; box-shadow: 0 0 12px rgba(0, 210, 190, 0.6); }

        .btn-sound-toggle {
            background: rgba(46, 204, 113, 0.15);
            border: 1px solid #2ecc71;
            color: #2ecc71;
            padding: 4px 10px;
            border-radius: 4px;
            font-family: 'Orbitron', sans-serif;
            font-size: 10px;
            font-weight: 800;
            cursor: pointer;
            transition: all 0.2s;
            letter-spacing: 0.5px;
        }
        .btn-sound-toggle.muted {
            background: rgba(255, 71, 87, 0.15);
            border-color: #ff4757;
            color: #ff4757;
        }

        /* Equalizer Wave Bars */
        .audio-wave-bars {
            display: inline-flex;
            align-items: center;
            gap: 2px;
            height: 12px;
            margin-left: 6px;
        }
        .wave-bar {
            width: 3px;
            height: 3px;
            background: #ff1801;
            border-radius: 1px;
            transition: height 0.1s ease;
        }
        .audio-wave-bars.active .wave-bar:nth-child(1) { animation: wave 0.4s infinite alternate; }
        .audio-wave-bars.active .wave-bar:nth-child(2) { animation: wave 0.6s infinite 0.1s alternate; }
        .audio-wave-bars.active .wave-bar:nth-child(3) { animation: wave 0.5s infinite 0.2s alternate; }
        .audio-wave-bars.active .wave-bar:nth-child(4) { animation: wave 0.7s infinite 0.15s alternate; }
        @keyframes wave {
            0% { height: 3px; background: #ff4757; }
            100% { height: 14px; background: #00d2be; }
        }

        /* Feed List */
        .feed-list {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }
        .feed-item {
            background: rgba(255, 255, 255, 0.02);
            border: 1px solid #202032;
            border-left: 3px solid #00d2be;
            border-radius: 6px;
            padding: 10px 14px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .feed-item.alert { border-left-color: #ff1801; }
        .feed-item-badge {
            font-family: 'Share Tech Mono', monospace;
            font-size: 9px;
            font-weight: 700;
            color: #8c8c9e;
            letter-spacing: 1px;
        }
        .feed-item-title {
            font-size: 12px;
            font-weight: 700;
            color: #ffffff;
            margin-top: 2px;
        }
        .feed-item-tag {
            font-family: 'Share Tech Mono', monospace;
            font-size: 10px;
            padding: 2px 6px;
            border-radius: 3px;
            background: rgba(0, 210, 190, 0.15);
            color: #00d2be;
        }
        .feed-item-tag.red { background: rgba(255, 24, 1, 0.15); color: #ff5252; }

        /* Reaction Mini-Game Modal */
        .game-overlay {
            display: none;
            position: fixed;
            top: 0; left: 0; width: 100%; height: 100%;
            background: rgba(0, 0, 0, 0.92);
            backdrop-filter: blur(12px);
            z-index: 3000;
            justify-content: center;
            align-items: center;
            padding: 20px;
        }
        .game-overlay.active { display: flex; }
        .game-card {
            background: #10101c;
            border: 2px solid #33334d;
            border-radius: 16px;
            padding: 36px;
            width: 100%;
            max-width: 580px;
            text-align: center;
            box-shadow: 0 25px 60px rgba(0,0,0,0.9), 0 0 40px rgba(255, 24, 1, 0.2);
            position: relative;
        }
        .game-title {
            font-family: 'Orbitron', sans-serif;
            font-size: 20px;
            font-weight: 900;
            color: #ffffff;
            letter-spacing: 2px;
            margin-bottom: 8px;
        }
        .game-subtitle {
            font-family: 'Share Tech Mono', monospace;
            font-size: 12px;
            color: #8c8c9e;
            margin-bottom: 24px;
        }
        .game-light-rack {
            display: flex;
            justify-content: center;
            gap: 14px;
            background: #08080f;
            border: 2px solid #232338;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 24px;
        }
        .big-light {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            background: #1c0505;
            border: 2px solid #4a0909;
            transition: all 0.1s;
        }
        .big-light.on {
            background: #ff1801;
            box-shadow: 0 0 25px #ff1801, inset 0 0 8px #ffffff;
        }
        .big-light.green {
            background: #2ecc71;
            box-shadow: 0 0 25px #2ecc71, inset 0 0 8px #ffffff;
        }
        .game-tap-zone {
            background: #19192c;
            border: 2px dashed #3a3a54;
            border-radius: 12px;
            padding: 30px;
            cursor: pointer;
            user-select: none;
            transition: all 0.2s;
        }
        .game-tap-zone:hover {
            border-color: #00d2be;
            background: #202038;
        }
        .game-tap-text {
            font-family: 'Orbitron', sans-serif;
            font-size: 16px;
            font-weight: 800;
            color: #ffffff;
            letter-spacing: 1.5px;
        }
        .score-display {
            font-family: 'Orbitron', sans-serif;
            font-size: 42px;
            font-weight: 900;
            color: #00d2be;
            margin-top: 14px;
        }

        @media (max-width: 1200px) {
            .hud-grid { grid-template-columns: repeat(3, 1fr); }
            .feature-grid { grid-template-columns: 1fr; }
            .command-rig-grid { grid-template-columns: 1fr; }
        }
        @media (max-width: 768px) {
            .hud-grid { grid-template-columns: 1fr 1fr; }
            .weather-matrix { grid-template-columns: 1fr 1fr; }
            .telemetry-bar { flex-direction: column; gap: 12px; align-items: flex-start; }
        }
    </style>
</head>
<body>

<div class="ambient-glow-red"></div>
<div class="ambient-glow-cyan"></div>

<?php include 'navbar.php'; ?>

<!-- Top Telemetry & Reaction Lights Bar -->
<div class="telemetry-bar" id="topTelemetryBar">
    <div class="telemetry-status">
        <div class="pulse-dot" id="mainPulseDot"></div>
        <span id="telemetryStatusText">FIA RACE CONTROL ACTIVE // MONACO GP CIRCUIT ONLINE</span>
    </div>
    
    <div style="display: flex; align-items: center; gap: 16px;">
        <span style="color: #8c8c9e; font-size: 11px;">LIGHTS OUT RIG:</span>
        <div class="lights-strip-interactive" onclick="openReactionGame()" title="Click to Test Reaction Time Mini-Game">
            <div class="lights-box">
                <div class="light-bulb on"></div>
                <div class="light-bulb on"></div>
                <div class="light-bulb on"></div>
                <div class="light-bulb on"></div>
                <div class="light-bulb on"></div>
            </div>
            <span style="font-family: 'Orbitron', sans-serif; font-size: 10px; font-weight: 800; color: #00d2be;">TEST REACTION</span>
        </div>
    </div>
</div>

<!-- Race Control Flag Switchboard -->
<div class="flag-switchboard">
    <span class="switchboard-title">🚩 Track Condition Mode:</span>
    <button type="button" class="flag-btn active green" onclick="setFlagStatus('GREEN')">🟢 GREEN FLAG</button>
    <button type="button" class="flag-btn sc" onclick="setFlagStatus('SC')">🟡 SAFETY CAR</button>
    <button type="button" class="flag-btn vsc" onclick="setFlagStatus('VSC')">🟣 VSC DELTA</button>
    <button type="button" class="flag-btn red" onclick="setFlagStatus('RED')">🔴 RED FLAG</button>
    <button type="button" class="flag-btn chequered" onclick="setFlagStatus('CHEQUERED')">🏁 CHEQUERED FLAG</button>
</div>

<!-- Primary Metric HUD Matrix -->
<div class="hud-grid">
    <div class="hud-card">
        <div class="hud-label"><span>Constructors</span> <span>🏎️</span></div>
        <div class="hud-value"><?php echo $teams_count; ?></div>
    </div>
    <div class="hud-card cyan">
        <div class="hud-label"><span>Homologated Grid</span> <span>⚡</span></div>
        <div class="hud-value" style="color: #00d2be;"><?php echo $cars_count; ?></div>
    </div>
    <div class="hud-card">
        <div class="hud-label"><span>Licensed Pilots</span> <span>👨‍✈️</span></div>
        <div class="hud-value" style="color: #ffffff;"><?php echo $drivers_count; ?></div>
    </div>
    <div class="hud-card green">
        <div class="hud-label"><span>Scrutineering Logs</span> <span>📋</span></div>
        <div class="hud-value" style="color: #2ecc71;">
            <?php echo $logs_count; ?> 
            <span style="font-size: 12px; color: #8c8c9e;">(<?php echo ($logs_count > 0) ? round(($pass_count / $logs_count) * 100) : 100; ?>%)</span>
        </div>
    </div>
    <div class="hud-card red">
        <div class="hud-label"><span>Violations Detected</span> <span>🚨</span></div>
        <div class="hud-value" style="color: #ff1801;"><?php echo $violations_count; ?></div>
    </div>
    <div class="hud-card purple">
        <div class="hud-label"><span>Steward Decisions</span> <span>⚖️</span></div>
        <div class="hud-value" style="color: #a855f7;"><?php echo $decisions_count; ?></div>
    </div>
    <div class="hud-card teal">
        <div class="hud-label"><span>Repairs & Re-Inspect</span> <span>🔧</span></div>
        <div class="hud-value" style="color: #14b8a6;">
            <?php echo $repairs_count; ?>
            <span style="font-size: 12px; color: #2ecc71;">(<?php echo $reinspections_count; ?> Passed)</span>
        </div>
    </div>
</div>

<!-- DRIVER SUPERLICENSE PENALTY POINTS RADAR -->
<?php include 'driver_superlicense_tracker.php'; ?>

<!-- INTERACTIVE GRAND PRIX TRACK INCIDENT & CORNER RADAR -->
<?php include 'track_incident_radar.php'; ?>

<!-- Feature Module Launchpads -->
<div class="feature-grid">
    <!-- Feature 1 -->
    <a href="feature1.php" class="feature-card">
        <div>
            <span class="feature-badge">FEATURE MODULE 01</span>
            <h2 class="feature-title">Grid & Chassis Homologation</h2>
            <p class="feature-desc">Homologate new Formula 1 chassis, manage power unit technical allocations, and license elite pilots on the active championship registry.</p>
        </div>
        <div class="btn-portal-action" style="color: #ff3838;">
            Launch Grid Console <span>→</span>
        </div>
    </a>

    <!-- Feature 2 -->
    <a href="feature2.php" class="feature-card cyan">
        <div>
            <span class="feature-badge cyan">FEATURE MODULE 02</span>
            <h2 class="feature-title">Technical Scrutineering Rig</h2>
            <p class="feature-desc">Execute post-session weighbridge diagnostics, rear wing flex deflection laser tests, and log official technical compliance records.</p>
        </div>
        <div class="btn-portal-action" style="color: #00d2be;">
            Launch Scrutineering Rig <span>→</span>
        </div>
    </a>

    <!-- Feature 3 -->
    <a href="stewards_infringements.php" class="feature-card amber">
        <div>
            <span class="feature-badge amber">FEATURE MODULE 03</span>
            <h2 class="feature-title">Stewards & Infringements</h2>
            <p class="feature-desc">Adjudicate track limits infractions, convene steward hearings, enforce time penalties, Super License points, and sporting sanctions.</p>
        </div>
        <div class="btn-portal-action" style="color: #ffb703;">
            Launch Stewards Room <span>→</span>
        </div>
    </a>

    <!-- Feature 4 -->
    <a href="feature4.php" class="feature-card green">
        <div>
            <span class="feature-badge green">FEATURE MODULE 04</span>
            <h2 class="feature-title">Bulletins & PU Allocations</h2>
            <p class="feature-desc">Track Power Unit & Gearbox component usage quotas, monitor grid drop penalties, and publish printable official FIA Technical Bulletins.</p>
        </div>
        <div class="btn-portal-action" style="color: #2ecc71;">
            Launch Bulletin Rig <span>→</span>
        </div>
    </a>

    <!-- Feature 5 -->
    <a href="feature5.php" class="feature-card red">
        <div>
            <span class="feature-badge red">FEATURE MODULE 05</span>
            <h2 class="feature-title">Violation Detection AI</h2>
            <p class="feature-desc">Automatic live stream scanning for front wing deflection, skid block plank wear, and fuel mass flow rate breaches with technical delegate review.</p>
        </div>
        <div class="btn-portal-action" style="color: #ff1801;">
            Launch Violation Scanner <span>→</span>
        </div>
    </a>

    <!-- Feature 6 -->
    <a href="feature6.php" class="feature-card purple">
        <div>
            <span class="feature-badge purple">FEATURE MODULE 06</span>
            <h2 class="feature-title">Steward Decisions Hub</h2>
            <p class="feature-desc">Issue official penalties (DSQ, grid drops, time penalties), state legal rationale, and ratify steward decisions for the official FIA Gazette.</p>
        </div>
        <div class="btn-portal-action" style="color: #a855f7;">
            Launch Judicial Hub <span>→</span>
        </div>
    </a>

    <!-- Feature 7 -->
    <a href="feature7.php" class="feature-card teal">
        <div>
            <span class="feature-badge teal">FEATURE MODULE 07</span>
            <h2 class="feature-title">Repair & Re-Inspection</h2>
            <p class="feature-desc">Track garage mechanic repairs on technical non-compliances and log official FIA delegate re-inspection verifications.</p>
        </div>
        <div class="btn-portal-action" style="color: #14b8a6;">
            Launch Repair Rig <span>→</span>
        </div>
    </a>

    <!-- Feature 8 -->
    <a href="feature8.php" class="feature-card cyan">
        <div>
            <span class="feature-badge cyan">FEATURE MODULE 08</span>
            <h2 class="feature-title">Delegate Re-Inspection Ledger</h2>
            <p class="feature-desc">ER relational database tracking connecting REPAIRS, RE_INSPECTIONS, VIOLATIONS, and INSPECTION_SESSIONS with official clearance ratings.</p>
        </div>
        <div class="btn-portal-action" style="color: #00d2be;">
            Launch Delegate Ledger <span>→</span>
        </div>
    </a>

    <!-- Feature 9 -->
    <a href="feature9.php" class="feature-card amber">
        <div>
            <span class="feature-badge amber">FEATURE MODULE 09</span>
            <h2 class="feature-title">Steward Appeals & Evidence</h2>
            <p class="feature-desc">Lodge legal protests against penalties, attach telemetry dossiers into APPEAL_EVIDENCE, and ratify formal steward appeal verdicts.</p>
        </div>
        <div class="btn-portal-action" style="color: #ffb703;">
            Launch Appeals Court <span>→</span>
        </div>
    </a>

    <!-- Feature 10 -->
    <a href="feature10.php" class="feature-card purple" style="border-color: rgba(155, 89, 182, 0.3);">
        <div>
            <span class="feature-badge purple" style="color: #9b59b6; border-color: rgba(155, 89, 182, 0.4); background: rgba(155, 89, 182, 0.1);">FEATURE MODULE 10</span>
            <h2 class="feature-title">Audit Trail & Master Dossier</h2>
            <p class="feature-desc">Query immutable AUDIT_LOGS, promulgate technical REGULATIONS, inspect end-to-end car audit trails, and export certified CSV/Print dossiers.</p>
        </div>
        <div class="btn-portal-action" style="color: #9b59b6;">
            Launch Master Dossier <span>→</span>
        </div>
    </a>

    <!-- Feature 11 -->
    <a href="feature11.php" class="feature-card cyan" style="border-color: rgba(0, 210, 190, 0.3);">
        <div>
            <span class="feature-badge cyan">FEATURE MODULE 11</span>
            <h2 class="feature-title">System Security & Audit Logs</h2>
            <p class="feature-desc">Real-time security log viewer with color-coded action pills, expandable JSON telemetry payloads, operator tracking, and IP address queries.</p>
        </div>
        <div class="btn-portal-action" style="color: #00d2be;">
            Launch Security Logs <span>→</span>
        </div>
    </a>

    <!-- Feature 12 -->
    <a href="feature12.php" class="feature-card red" style="border-color: rgba(255, 24, 1, 0.3);">
        <div>
            <span class="feature-badge red" style="color: #ff4757; border-color: rgba(255, 24, 1, 0.4); background: rgba(255, 24, 1, 0.1);">FEATURE MODULE 12</span>
            <h2 class="feature-title">Race Control Alert Center</h2>
            <p class="feature-desc">Real-time telemetry incident stream, Parc Fermé warnings, role-targeted directives, broadcast dispatch console, and one-click acknowledgements.</p>
        </div>
        <div class="btn-portal-action" style="color: #ff4757;">
            Launch Alert Center <span>→</span>
        </div>
    </a>
</div>

<!-- Mission Control Dynamic Live Rig -->
<div class="command-rig-grid">
    <!-- Left Rig: Live Track & Environmental Telemetry -->
    <div class="command-panel">
        <div class="panel-header">
            <div class="panel-title" style="color: #00d2be;">
                <span>📡</span> Live Track Telemetry & Speed Trap
            </div>
            <span style="font-family: 'Share Tech Mono', monospace; font-size: 11px; color: #717188;">SECTOR 1-3 SENSORS</span>
        </div>

        <!-- Circuit Selector -->
        <div class="circuit-bar">
            <button type="button" class="circuit-chip active" onclick="setCircuit('Monaco', 3.337, 28.4, 42.1, 312.4)">🇲🇨 Circuit de Monaco</button>
            <button type="button" class="circuit-chip" onclick="setCircuit('Silverstone', 5.891, 21.2, 33.5, 338.9)">🇬🇧 Silverstone Circuit</button>
            <button type="button" class="circuit-chip" onclick="setCircuit('Monza', 5.793, 29.8, 48.0, 354.2)">🇮🇹 Autodromo di Monza</button>
            <button type="button" class="circuit-chip" onclick="setCircuit('Spa', 7.004, 18.5, 24.2, 345.1)">🇧🇪 Spa-Francorchamps</button>
        </div>

        <!-- Weather Telemetry Grid -->
        <div class="weather-matrix">
            <div class="weather-cell">
                <div class="weather-prop">Track Temp</div>
                <div class="weather-val" id="trackTempVal">42.1 °C</div>
            </div>
            <div class="weather-cell">
                <div class="weather-prop">Air Temp</div>
                <div class="weather-val" id="airTempVal" style="color: #ffffff;">28.4 °C</div>
            </div>
            <div class="weather-cell">
                <div class="weather-prop">Rain Probability</div>
                <div class="weather-val" id="rainVal" style="color: #2ecc71;">0% (Dry)</div>
            </div>
            <div class="weather-cell">
                <div class="weather-prop">Sector 3 Trap</div>
                <div class="weather-val" id="trapVal" style="color: #ff1801;">312.4 km/h</div>
            </div>
        </div>

        <!-- Live Speed Trap Bar Comparisons -->
        <div style="font-family: 'Share Tech Mono', monospace; font-size: 11px; color: #8c8c9e; margin-bottom: 10px; text-transform: uppercase;">
            🏁 Top Speed Telemetry Records:
        </div>

        <div class="speed-bar-row">
            <div class="speed-meta">
                <span>1. Max Verstappen (Red Bull RB20)</span>
                <span id="speed1" style="color: #00d2be; font-weight: 700;">341.2 km/h</span>
            </div>
            <div class="speed-progress-track">
                <div class="speed-fill" style="width: 96%;"></div>
            </div>
        </div>

        <div class="speed-bar-row">
            <div class="speed-meta">
                <span>2. Charles Leclerc (Ferrari SF-24)</span>
                <span id="speed2" style="color: #ff3838; font-weight: 700;">339.8 km/h</span>
            </div>
            <div class="speed-progress-track">
                <div class="speed-fill" style="width: 94%;"></div>
            </div>
        </div>

        <div class="speed-bar-row">
            <div class="speed-meta">
                <span>3. Lando Norris (McLaren MCL38)</span>
                <span id="speed3" style="color: #ffb703; font-weight: 700;">340.5 km/h</span>
            </div>
            <div class="speed-progress-track">
                <div class="speed-fill" style="width: 95%;"></div>
            </div>
        </div>
    </div>

    <!-- Right Rig: Race Control Communicator & Live Feed -->
    <div class="command-panel">
        <div class="panel-header">
            <div class="panel-title" style="color: #ff1801;">
                <span>🎙️</span> Race Control Audio & Live Feed
            </div>
            <button type="button" class="btn-sound-toggle" id="radioVoiceToggleBtn" onclick="toggleRadioVoice()">
                🔊 SOUND COMMS: ON
            </button>
        </div>

        <!-- Team Radio Communicator Rig -->
        <div class="radio-box">
            <div class="radio-icon">📻</div>
            <div style="flex: 1;">
                <div class="radio-msg-title">
                    <span>FIA RACE CONTROL COMMS</span>
                    <span class="audio-wave-bars" id="audioEqualizer">
                        <span class="wave-bar"></span>
                        <span class="wave-bar"></span>
                        <span class="wave-bar"></span>
                        <span class="wave-bar"></span>
                    </span>
                </div>
                <div class="radio-msg-body" id="radioText">"Car 1 under investigation for Turn 4 contact."</div>
            </div>
            <div class="radio-controls">
                <button type="button" class="btn-radio-replay" onclick="broadcastCurrentRadio(true)" title="Play F1 radio sound transmission">
                    📢 SOUND
                </button>
                <button type="button" class="btn-radio-play" onclick="playNextRadioMessage()">
                    ▶ NEXT COMM
                </button>
            </div>
        </div>

        <!-- Latest Event Stream -->
        <div style="font-family: 'Share Tech Mono', monospace; font-size: 11px; color: #8c8c9e; margin-bottom: 8px; text-transform: uppercase;">
            📋 Recent Scrutineering & Steward Events:
        </div>

        <div class="feed-list">
            <?php if (empty($recent_events)): ?>
                <div style="text-align: center; color: #717188; padding: 20px; font-size: 12px;">
                    No recent events logged yet.
                </div>
            <?php else: ?>
                <?php foreach ($recent_events as $ev): ?>
                <div class="feed-item <?php echo $ev['is_violation'] ? 'alert' : ''; ?>">
                    <div>
                        <div class="feed-item-badge"><?php echo htmlspecialchars($ev['badge']); ?></div>
                        <div class="feed-item-title"><?php echo htmlspecialchars($ev['title']); ?></div>
                    </div>
                    <span class="feed-item-tag <?php echo $ev['is_violation'] ? 'red' : ''; ?>">
                        <?php echo htmlspecialchars($ev['status']); ?>
                    </span>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- REACTION TIMER MINI-GAME MODAL -->
<div class="game-overlay" id="gameOverlay">
    <div class="game-card">
        <div class="game-title">F1 START REACTION TEST</div>
        <div class="game-subtitle">WAIT FOR ALL 5 RED LIGHTS TO GO OUT — THEN TAP AS FAST AS YOU CAN!</div>
        
        <div class="game-light-rack">
            <div class="big-light" id="gLight1"></div>
            <div class="big-light" id="gLight2"></div>
            <div class="big-light" id="gLight3"></div>
            <div class="big-light" id="gLight4"></div>
            <div class="big-light" id="gLight5"></div>
        </div>

        <div class="game-tap-zone" id="tapZone" onclick="handleReactionTap()">
            <div class="game-tap-text" id="tapInstruction">CLICK TO ARM START LIGHTS</div>
            <div class="score-display" id="scoreValue">-- MS</div>
            <div id="driverGrade" style="font-family: 'Share Tech Mono', monospace; font-size: 13px; color: #ffb703; margin-top: 6px;"></div>
        </div>

        <button type="button" class="circuit-chip" style="margin-top: 20px; padding: 10px 24px; font-weight: 700;" onclick="closeReactionGame()">
            CLOSE CONSOLE
        </button>
    </div>
</div>

<!-- Synthesizer Audio & Dynamic Interactive Logic -->
<script>
// Web Audio API Synthesizer (Realistic F1 Beeps and Race Control Tones)
let audioCtx = null;
function getAudioContext() {
    if (!audioCtx) {
        audioCtx = new (window.AudioContext || window.webkitAudioContext)();
    }
    return audioCtx;
}

function playTone(freq, duration, type = 'sine') {
    try {
        const ctx = getAudioContext();
        const osc = ctx.createOscillator();
        const gain = ctx.createGain();
        osc.type = type;
        osc.frequency.value = freq;
        gain.gain.setValueAtTime(0.15, ctx.currentTime);
        gain.gain.exponentialRampToValueAtTime(0.001, ctx.currentTime + duration);
        osc.connect(gain);
        gain.connect(ctx.destination);
        osc.start();
        osc.stop(ctx.currentTime + duration);
    } catch(e) {}
}

// 1. Race Control Flag Mode Switcher
function setFlagStatus(mode) {
    const bar = document.getElementById('topTelemetryBar');
    const pulse = document.getElementById('mainPulseDot');
    const statusTxt = document.getElementById('telemetryStatusText');
    const btns = document.querySelectorAll('.flag-btn');

    btns.forEach(b => b.classList.remove('active'));

    let color = '#2ecc71';
    let text = 'FIA RACE CONTROL // GREEN FLAG // TRACK CLEAR';

    if (mode === 'GREEN') {
        color = '#2ecc71';
        text = 'FIA RACE CONTROL // 🟢 GREEN FLAG // RACING RESUMED';
        document.querySelector('.flag-btn.green').classList.add('active');
        playTone(520, 0.2);
    } else if (mode === 'SC') {
        color = '#ffb703';
        text = 'FIA RACE CONTROL // 🟡 FULL SAFETY CAR DEPLOYED // NO OVERTAKING';
        document.querySelector('.flag-btn.sc').classList.add('active');
        playTone(440, 0.4, 'sawtooth');
    } else if (mode === 'VSC') {
        color = '#9d4edd';
        text = 'FIA RACE CONTROL // 🟣 VIRTUAL SAFETY CAR // MAINTAIN POSITIVE DELTA';
        document.querySelector('.flag-btn.vsc').classList.add('active');
        playTone(600, 0.3);
    } else if (mode === 'RED') {
        color = '#ff1801';
        text = 'FIA RACE CONTROL // 🔴 RED FLAG // SESSION SUSPENDED // RETURN TO PIT LANE';
        document.querySelector('.flag-btn.red').classList.add('active');
        playTone(300, 0.5, 'square');
    } else if (mode === 'CHEQUERED') {
        color = '#ffffff';
        text = 'FIA RACE CONTROL // 🏁 CHEQUERED FLAG // SESSION COMPLETED';
        document.querySelector('.flag-btn.chequered').classList.add('active');
        playTone(800, 0.25);
    }

    document.documentElement.style.setProperty('--flag-color', color);
    bar.style.borderLeftColor = color;
    pulse.style.backgroundColor = color;
    pulse.style.boxShadow = `0 0 10px ${color}`;
    statusTxt.textContent = text;
}

// 2. Track Circuit Switcher
function setCircuit(name, length, air, track, trap) {
    document.querySelectorAll('.circuit-chip').forEach(c => c.classList.remove('active'));
    event.target.classList.add('active');

    document.getElementById('trackTempVal').textContent = track + ' °C';
    document.getElementById('airTempVal').textContent = air + ' °C';
    document.getElementById('trapVal').textContent = trap + ' km/h';

    // Slightly randomize top speed telemetry for excitement
    const s1 = (trap * 1.01).toFixed(1);
    const s2 = (trap * 0.99).toFixed(1);
    const s3 = (trap * 1.002).toFixed(1);

    document.getElementById('speed1').textContent = s1 + ' km/h';
    document.getElementById('speed2').textContent = s2 + ' km/h';
    document.getElementById('speed3').textContent = s3 + ' km/h';

    playTone(700, 0.1);
}

// 3. Simulated Team Radio Comms with Authentic Male F1 Pit Wall Sound
const radioMessages = [
    '"Race Control: All cars, track is clear. Green flag, green flag."',
    '"Pit Wall: Box, box, box! Confirming pit stop window is open. Hard tyres ready."',
    '"FIA Technical Delegate: Automatic violation scan clear. Front wing deflection compliant."',
    '"Pit Wall: Safety Car in this lap. Bring tyres and brakes up to temperature."',
    '"Race Control: Technical violation logged for Car 1. Front wing flap height exceeds tolerance."',
    '"Steward Decision: Disqualification from qualifying published for Car 1. Starting from pit lane."',
    '"Steward Decision: 10-Place grid drop ratified for Car 16 due to plank wear infringement."',
    '"FIA Technical Delegate: Car 1 repair completed and re-inspection verified passed."',
    '"FIA Court of Appeal: Car 1 protest reviewed. Telemetry dossier verified, penalty overturned."',
    '"Pit Wall: Mode push! Full battery deployment available for the main straight."',
    '"Race Control: Yellow flag Sector 2! Car stopped off track at Mirabeau."',
    '"FIA Technical Delegate: Car 1 fuel flow and turbo pressure within legal envelope."'
];
let currentRadioIdx = 0;
let isVoiceEnabled = true;

// Preload voices cache for quick access
let availableVoices = [];
function updateVoicesList() {
    if ('speechSynthesis' in window) {
        availableVoices = window.speechSynthesis.getVoices();
    }
}
if ('speechSynthesis' in window) {
    updateVoicesList();
    window.speechSynthesis.onvoiceschanged = updateVoicesList;
}

function toggleRadioVoice() {
    isVoiceEnabled = !isVoiceEnabled;
    const btn = document.getElementById('radioVoiceToggleBtn');
    if (isVoiceEnabled) {
        btn.textContent = '🔊 SOUND COMMS: ON';
        btn.className = 'btn-sound-toggle';
        broadcastCurrentRadio(true);
    } else {
        btn.textContent = '🔇 SOUND COMMS: OFF';
        btn.className = 'btn-sound-toggle muted';
        if (window.speechSynthesis) window.speechSynthesis.cancel();
    }
}

function broadcastCurrentRadio(forcePlay = false) {
    const rawMsg = radioMessages[currentRadioIdx];
    document.getElementById('radioText').textContent = rawMsg;
    const eq = document.getElementById('audioEqualizer');

    // 1. Authentic F1 radio double-chirp squelch sound
    playTone(1100, 0.06, 'sine');
    setTimeout(() => playTone(1500, 0.08, 'sine'), 65);

    if (!isVoiceEnabled && !forcePlay) return;

    // 2. Play transmission with deep male F1 pit wall voice
    if ('speechSynthesis' in window) {
        window.speechSynthesis.cancel(); // cancel any previous transmissions

        const cleanText = rawMsg.replace(/["']/g, '');
        const utterance = new SpeechSynthesisUtterance(cleanText);

        utterance.volume = 1.0; // High volume
        utterance.rate = 1.0;   // Measured, crisp F1 race engineer cadence
        utterance.pitch = 0.78; // Deep, commanding male baritone pitch

        // Select authentic male English voice
        const voices = availableVoices.length > 0 ? availableVoices : window.speechSynthesis.getVoices();
        
        // Priority list of masculine English voice names
        const maleVoiceNames = [
            'Daniel', 'George', 'Oliver', 'Arthur', 'Aaron', 'Gordon',
            'Microsoft George', 'Microsoft David', 'Microsoft Mark', 'Microsoft Ryan',
            'Google UK English Male', 'Google US English Male', 'Alex', 'Fred', 'Guy', 'Tom', 'James', 'Male'
        ];
        
        // List of female keywords to strictly avoid
        const femaleKeywords = [
            'samantha', 'victoria', 'karen', 'moira', 'tessa', 'zira', 'female',
            'jenny', 'fiona', 'susan', 'serena', 'stephanie', 'zoe', 'alva',
            'kate', 'ava', 'allison', 'veena', 'clara', 'catherine', 'emma',
            'hazel', 'salli', 'joanna', 'kendra', 'kimberly', 'ivy', 'aria', 'natasha'
        ];

        let selectedVoice = null;

        // 1st priority: Known male English voice
        for (const name of maleVoiceNames) {
            selectedVoice = voices.find(v => v.name && v.name.toLowerCase().includes(name.toLowerCase()) && v.lang && v.lang.startsWith('en'));
            if (selectedVoice) break;
        }

        // 2nd priority: Any English voice that is definitely not female
        if (!selectedVoice) {
            selectedVoice = voices.find(v => {
                if (!v.lang || !v.lang.startsWith('en')) return false;
                const vName = (v.name || '').toLowerCase();
                return !femaleKeywords.some(f => vName.includes(f));
            });
        }

        // 3rd priority: Fallback to any English voice
        if (!selectedVoice) {
            selectedVoice = voices.find(v => v.lang && v.lang.startsWith('en'));
        }

        if (selectedVoice) {
            utterance.voice = selectedVoice;
        }

        utterance.onstart = () => {
            if (eq) eq.classList.add('active');
        };

        utterance.onend = () => {
            if (eq) eq.classList.remove('active');
            // Closing radio squelch release click
            playTone(850, 0.05, 'triangle');
        };

        utterance.onerror = () => {
            if (eq) eq.classList.remove('active');
        };

        // Delay slightly for initial radio squelch burst
        setTimeout(() => {
            window.speechSynthesis.speak(utterance);
        }, 120);
    }
}

function playNextRadioMessage() {
    currentRadioIdx = (currentRadioIdx + 1) % radioMessages.length;
    broadcastCurrentRadio(isVoiceEnabled);
}

// 4. Start Lights Out Reaction Game
let gameState = 'idle'; // idle, arming, waiting, lights_out, finished
let startTime = 0;
let lightsTimeout = null;

function openReactionGame() {
    document.getElementById('gameOverlay').classList.add('active');
    resetGame();
}

function closeReactionGame() {
    document.getElementById('gameOverlay').classList.remove('active');
    clearTimeout(lightsTimeout);
    gameState = 'idle';
}

function resetGame() {
    clearTimeout(lightsTimeout);
    gameState = 'idle';
    for(let i = 1; i <= 5; i++) {
        const l = document.getElementById('gLight' + i);
        l.className = 'big-light';
    }
    document.getElementById('tapInstruction').textContent = 'CLICK TO ARM START LIGHTS';
    document.getElementById('scoreValue').textContent = '-- MS';
    document.getElementById('driverGrade').textContent = '';
}

function handleReactionTap() {
    if (gameState === 'idle') {
        startLightSequence();
    } else if (gameState === 'arming' || gameState === 'waiting') {
        // JUMP START PENALTY
        clearTimeout(lightsTimeout);
        gameState = 'finished';
        document.getElementById('tapInstruction').textContent = '⚠️ JUMP START DETECTED!';
        document.getElementById('scoreValue').textContent = 'FALSE START';
        document.getElementById('scoreValue').style.color = '#ff1801';
        document.getElementById('driverGrade').textContent = '10-Second Stop/Go Penalty assigned by Stewards!';
        playTone(200, 0.5, 'sawtooth');
    } else if (gameState === 'lights_out') {
        // SUCCESSFUL REACTION
        const elapsed = Date.now() - startTime;
        gameState = 'finished';
        document.getElementById('tapInstruction').textContent = 'REACTION RECORDED! (CLICK TO RETRY)';
        document.getElementById('scoreValue').textContent = elapsed + ' MS';
        document.getElementById('scoreValue').style.color = (elapsed < 240) ? '#2ecc71' : (elapsed < 320 ? '#00d2be' : '#ffb703');

        let grade = 'Grand Prix Master Class (Verstappen Level)';
        if (elapsed > 350) grade = 'Rookie Grid Reaction (Keep Practicing)';
        else if (elapsed > 280) grade = 'Solid F1 Midfield Reflexes';
        else if (elapsed < 200) grade = '⚡ INCREDIBLE! Alien Level Reaction!';

        document.getElementById('driverGrade').textContent = grade;
        playTone(900, 0.2);
    } else if (gameState === 'finished') {
        resetGame();
        startLightSequence();
    }
}

function startLightSequence() {
    gameState = 'arming';
    resetGame();
    gameState = 'arming';
    document.getElementById('tapInstruction').textContent = 'ARMING LIGHTS... WAIT FOR LIGHTS OUT!';
    
    let step = 1;
    function nextLight() {
        if (step <= 5) {
            document.getElementById('gLight' + step).classList.add('on');
            playTone(400 + (step * 80), 0.1);
            step++;
            lightsTimeout = setTimeout(nextLight, 600);
        } else {
            // All 5 red lights on, random delay 0.8s to 2.6s before lights out!
            gameState = 'waiting';
            const randomDelay = Math.floor(Math.random() * 1800) + 800;
            lightsTimeout = setTimeout(() => {
                // LIGHTS OUT!
                for(let i = 1; i <= 5; i++) {
                    document.getElementById('gLight' + i).classList.remove('on');
                }
                startTime = Date.now();
                gameState = 'lights_out';
                document.getElementById('tapInstruction').textContent = '🏁 LIGHTS OUT! TAP NOW!';
                playTone(1000, 0.15, 'square');
            }, randomDelay);
        }
    }
    lightsTimeout = setTimeout(nextLight, 500);
}
</script>

</body>
</html>
