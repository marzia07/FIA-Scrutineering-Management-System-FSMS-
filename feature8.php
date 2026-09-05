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
check_role_access(['inspector', 'team_representative']);

// Auto-seed required ER records if tables are empty
$chk_v = $conn->query("SELECT COUNT(*) as cnt FROM VIOLATIONS");
if ($chk_v && $chk_v->fetch_assoc()['cnt'] == 0) {
    // Check if inspection measurements exist or create sample ones
    $chk_m = $conn->query("SELECT measurement_id FROM INSPECTION_MEASUREMENTS LIMIT 2");
    $m_ids = [];
    if ($chk_m && $chk_m->num_rows > 0) {
        while ($mr = $chk_m->fetch_assoc()) $m_ids[] = $mr['measurement_id'];
    }
    if (empty($m_ids)) {
        $conn->query("INSERT INTO INSPECTION_MEASUREMENTS (session_id, measurement_name, expected_value, actual_value, unit, result) VALUES 
        (1, 'Front Wing Deflection', 100, 150, 'mm', 'Failed'),
        (1, 'Plank Wear Thickness', 9.0, 8.2, 'mm', 'Failed')");
        $m_ids = [1, 2];
    } elseif (count($m_ids) < 2) {
        $conn->query("INSERT INTO INSPECTION_MEASUREMENTS (session_id, measurement_name, expected_value, actual_value, unit, result) VALUES 
        (1, 'Plank Wear Thickness', 9.0, 8.2, 'mm', 'Failed')");
        $m_ids[] = $conn->insert_id;
    }

    $m1 = $m_ids[0];
    $m2 = $m_ids[1] ?? $m_ids[0];
    $uid = $_SESSION['user_id'] ?? 1;

    $conn->query("INSERT INTO VIOLATIONS (measurement_id, violation_description, severity, status, detected_by) VALUES 
    ($m1, 'Front Wing Height exceeds maximum allowable limit by 50mm under aerodynamic load.', 'Major', 'rectified', $uid),
    ($m2, 'Skid block plank wear measured at 8.2mm, below the 9.0mm mandatory minimum.', 'Critical', 'open', $uid)");
}

$chk_r = $conn->query("SELECT COUNT(*) as cnt FROM REPAIRS");
if ($chk_r && $chk_r->fetch_assoc()['cnt'] == 0) {
    $v_first = $conn->query("SELECT violation_id FROM VIOLATIONS LIMIT 1")->fetch_assoc()['violation_id'] ?? 1;
    $conn->query("INSERT INTO REPAIRS (violation_id, description, performed_at, performed_by, status) VALUES 
    ($v_first, 'Adjusted front wing pylon mounting brackets and removed upper spacer shims to lower mainplane to 100mm regulation.', NOW(), 'Red Bull Racing - Aero Crew', 'Completed')");
}

$chk_reins = $conn->query("SELECT COUNT(*) as cnt FROM RE_INSPECTIONS");
if ($chk_reins && $chk_reins->fetch_assoc()['cnt'] == 0) {
    $r_first = $conn->query("SELECT repair_id FROM REPAIRS LIMIT 1")->fetch_assoc()['repair_id'] ?? 1;
    $s_first = $conn->query("SELECT session_id FROM INSPECTION_SESSIONS LIMIT 1")->fetch_assoc()['session_id'] ?? 1;
    $u_insp = 2; // Jo Bauer or fallback
    $conn->query("INSERT INTO RE_INSPECTIONS (repair_id, session_id, inspector_id, result, inspected_at) VALUES 
    ($r_first, $s_first, $u_insp, 'Passed', NOW())");
}

$message = '';
$action_type = '';

// 1. Handle Form 1: Log Team Repair Action (Team Rep, Inspector, Admin)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'log_repair') {
    check_role_access(['team_representative', 'inspector']);
    $violation_id = intval($_POST['violation_id'] ?? 0);
    $description = trim($_POST['description'] ?? '');
    $performed_by = trim($_POST['performed_by'] ?? 'Team Technical Crew');
    $status = trim($_POST['status'] ?? 'Completed');
    $performed_at = date('Y-m-d H:i:s');

    if ($violation_id > 0 && !empty($description)) {
        $stmt = $conn->prepare("INSERT INTO REPAIRS (violation_id, description, performed_at, performed_by, status) VALUES (?, ?, ?, ?, ?)");
        if ($stmt) {
            $stmt->bind_param("issss", $violation_id, $description, $performed_at, $performed_by, $status);
            $stmt->execute();

            // Update violation status
            $v_status = ($status === 'Completed') ? 'rectified' : 'under_repair';
            $v_upd = $conn->prepare("UPDATE VIOLATIONS SET status = ? WHERE violation_id = ?");
            if ($v_upd) {
                $v_upd->bind_param("si", $v_status, $violation_id);
                $v_upd->execute();
            }

            $message = "Vehicle repair action recorded in FIA database under REPAIRS schema.";
            $action_type = "repair";
        }
    }
}

// 2. Handle Form 2: Delegate Re-Inspection Verdict (Inspector & Admin strictly)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_reinspection') {
    check_role_access(['inspector']);
    $repair_id = intval($_POST['repair_id'] ?? 0);
    $session_id = intval($_POST['session_id'] ?? 1);
    $inspector_id = intval($_POST['inspector_id'] ?? ($_SESSION['user_id'] ?? 1));
    $result = trim($_POST['result'] ?? 'Passed');
    $inspected_at = date('Y-m-d H:i:s');

    if ($repair_id > 0) {
        $stmt = $conn->prepare("INSERT INTO RE_INSPECTIONS (repair_id, session_id, inspector_id, result, inspected_at) VALUES (?, ?, ?, ?, ?)");
        if ($stmt) {
            $stmt->bind_param("iiiss", $repair_id, $session_id, $inspector_id, $result);
            $stmt->execute();

            // If passed, update linked violation to closed
            if (strcasecmp($result, 'Passed') === 0) {
                $conn->query("UPDATE VIOLATIONS v 
                              JOIN REPAIRS r ON v.violation_id = r.violation_id 
                              SET v.status = 'closed' 
                              WHERE r.repair_id = $repair_id");
            }

            $message = "Re-inspection verdict officialized and registered in RE_INSPECTIONS ledger.";
            $action_type = "reinspection";
        }
    }
}

// 3. Compute Top HUD Metrics
$pending_rectifications = 0;
$total_reinspections = 0;
$passed_reinspections = 0;
$clearance_rate = 100;
$open_failures = 0;

$m_rep = $conn->query("SELECT COUNT(*) as total, SUM(CASE WHEN LOWER(status) != 'completed' THEN 1 ELSE 0 END) as pending FROM REPAIRS");
if ($m_rep && $r_row = $m_rep->fetch_assoc()) {
    $pending_rectifications = intval($r_row['pending'] ?? 0);
}

$m_reins = $conn->query("SELECT COUNT(*) as total, SUM(CASE WHEN LOWER(result) = 'passed' THEN 1 ELSE 0 END) as passed FROM RE_INSPECTIONS");
if ($m_reins && $re_row = $m_reins->fetch_assoc()) {
    $total_reinspections = intval($re_row['total'] ?? 0);
    $passed_reinspections = intval($re_row['passed'] ?? 0);
    $clearance_rate = ($total_reinspections > 0) ? round(($passed_reinspections / $total_reinspections) * 100) : 100;
}

$m_viol = $conn->query("SELECT COUNT(*) as open_cnt FROM VIOLATIONS WHERE LOWER(status) IN ('open', 'pending')");
if ($m_viol && $v_row = $m_viol->fetch_assoc()) {
    $open_failures = intval($v_row['open_cnt'] ?? 0);
}

// 4. Query Data for Dropdowns
// Violations dropdown
$violations_query = $conn->query("SELECT v.violation_id, v.violation_description, v.severity, v.status,
                                          m.measurement_name, m.expected_value, m.actual_value, m.unit,
                                          c.car_name, c.chassis_number, t.team_name, d.full_name as driver_name
                                   FROM VIOLATIONS v
                                   LEFT JOIN INSPECTION_MEASUREMENTS m ON v.measurement_id = m.measurement_id
                                   LEFT JOIN INSPECTION_SESSIONS s ON m.session_id = s.session_id
                                   LEFT JOIN CARS c ON s.car_id = c.car_id
                                   LEFT JOIN TEAMS t ON c.team_id = t.team_id
                                   LEFT JOIN DRIVERS d ON c.driver_id = d.driver_id
                                   ORDER BY v.violation_id DESC");
$violations_list = [];
if ($violations_query) {
    while ($r = $violations_query->fetch_assoc()) $violations_list[] = $r;
}

// Repairs dropdown (for re-inspection)
$repairs_query = $conn->query("SELECT r.repair_id, r.description, r.status, r.performed_by,
                                      v.violation_id, v.violation_description,
                                      c.car_name, c.chassis_number
                               FROM REPAIRS r
                               LEFT JOIN VIOLATIONS v ON r.violation_id = v.violation_id
                               LEFT JOIN INSPECTION_MEASUREMENTS m ON v.measurement_id = m.measurement_id
                               LEFT JOIN INSPECTION_SESSIONS s ON m.session_id = s.session_id
                               LEFT JOIN CARS c ON s.car_id = c.car_id
                               ORDER BY r.repair_id DESC");
$repairs_dropdown = [];
if ($repairs_query) {
    while ($r = $repairs_query->fetch_assoc()) $repairs_dropdown[] = $r;
}

// Sessions dropdown
$sessions_query = $conn->query("SELECT s.session_id, s.session_type, s.location, c.car_name 
                                FROM INSPECTION_SESSIONS s 
                                LEFT JOIN CARS c ON s.car_id = c.car_id 
                                ORDER BY s.session_id DESC");
$sessions_list = [];
if ($sessions_query) {
    while ($s = $sessions_query->fetch_assoc()) $sessions_list[] = $s;
}

// Inspectors dropdown
$inspectors_query = $conn->query("SELECT user_id, full_name, email FROM USERS ORDER BY user_id ASC");
$inspectors_list = [];
if ($inspectors_query) {
    while ($u = $inspectors_query->fetch_assoc()) $inspectors_list[] = $u;
}

// 5. Query Master Table (JOINing REPAIRS, RE_INSPECTIONS, VIOLATIONS, CARS, USERS)
$master_query = $conn->query("SELECT r.repair_id, r.description as repair_desc, r.performed_by, r.performed_at, r.status as repair_status,
                                     v.violation_id, v.violation_description, v.severity,
                                     m.measurement_name, m.expected_value, m.actual_value, m.unit,
                                     c.car_name, c.chassis_number, t.team_name, d.full_name as driver_name,
                                     re.reinspection_id, re.result as reinspection_result, re.inspected_at,
                                     u.full_name as inspector_name,
                                     iss.session_type
                              FROM REPAIRS r
                              LEFT JOIN VIOLATIONS v ON r.violation_id = v.violation_id
                              LEFT JOIN INSPECTION_MEASUREMENTS m ON v.measurement_id = m.measurement_id
                              LEFT JOIN INSPECTION_SESSIONS s ON m.session_id = s.session_id
                              LEFT JOIN CARS c ON s.car_id = c.car_id
                              LEFT JOIN TEAMS t ON c.team_id = t.team_id
                              LEFT JOIN DRIVERS d ON c.driver_id = d.driver_id
                              LEFT JOIN RE_INSPECTIONS re ON r.repair_id = re.repair_id
                              LEFT JOIN USERS u ON re.inspector_id = u.user_id
                              LEFT JOIN INSPECTION_SESSIONS iss ON re.session_id = iss.session_id
                              ORDER BY r.repair_id DESC");
$data_deck = [];
if ($master_query) {
    while ($row = $master_query->fetch_assoc()) $data_deck[] = $row;
}

$current_page = basename($_SERVER['PHP_SELF']);
include 'navbar.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>FIA FSMS - Feature 8: Technical Repairs & Delegate Re-Inspections</title>
    <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@500;700;900&family=Share+Tech+Mono&family=Titillium+Web:wght@400;600;700&display=swap" rel="stylesheet">
</head>
<body>

<!-- Ambient CRT Scanline & Glow Backdrop -->
<div class="crt-scanline"></div>
<div class="ambient-glow glow-red"></div>
<div class="ambient-glow glow-cyan"></div>

<div class="main-content">
    <!-- Top Telemetry Header -->
    <div class="telemetry-top-bar">
        <div class="hud-status-live">
            <span class="live-dot">●</span> FIA TECHNICAL REPAIRS & RE-INSPECTION LEDGER // ACTIVE
        </div>
        <div class="hud-top-actions">
            <button type="button" class="btn-sound-pill" onclick="playRadioSquelchSound()" title="Broadcast Radio Comm">
                📢 RADIO SOUND
            </button>
            <div class="hud-protocol-tag">
                FIA ER REPAIRS & RE_INSPECTIONS SCHEMA
            </div>
        </div>
    </div>

    <?php if ($message): ?>
        <div class="hud-alert" id="hudAlertBox">
            <span class="alert-icon">🔧</span> <?php echo htmlspecialchars($message); ?>
        </div>
    <?php endif; ?>

    <!-- Top 4 HUD Metrics -->
    <div class="hud-metrics-grid">
        <div class="hud-stat-card theme-amber">
            <div class="stat-label">
                <span>PENDING RECTIFICATIONS</span>
                <span>⏳</span>
            </div>
            <div class="stat-value" style="color: #ffb703;"><?php echo $pending_rectifications; ?></div>
            <div class="stat-foot">Repairs in progress</div>
        </div>

        <div class="hud-stat-card theme-cyan">
            <div class="stat-label">
                <span>TOTAL RE-INSPECTIONS</span>
                <span>📋</span>
            </div>
            <div class="stat-value" style="color: #00d2be;"><?php echo $total_reinspections; ?></div>
            <div class="stat-foot">Completed by delegates</div>
        </div>

        <div class="hud-stat-card theme-green">
            <div class="stat-label">
                <span>CLEARANCE RATE</span>
                <span>🛡️</span>
            </div>
            <div class="stat-value" style="color: #2ecc71;"><?php echo $clearance_rate; ?><span style="font-size: 16px;">%</span></div>
            <div class="stat-foot"><?php echo $passed_reinspections; ?> Passed verifications</div>
        </div>

        <div class="hud-stat-card theme-red">
            <div class="stat-label">
                <span>OPEN FAILURES</span>
                <span>🚨</span>
            </div>
            <div class="stat-value" style="color: #ff1801;"><?php echo $open_failures; ?></div>
            <div class="stat-foot">Violations awaiting action</div>
        </div>
    </div>

    <!-- Dual Command Forms -->
    <div class="hud-grid-row">
        <!-- Form 1: Log Team Repair Action -->
        <div class="hud-card theme-red">
            <div class="hud-card-header">
                <h3><span class="card-num">01.</span> LOG TEAM REPAIR ACTION</h3>
                <span class="hud-badge-tag">REPAIR-8A</span>
            </div>
            <form method="POST" action="feature8.php" onsubmit="playRepairActionSound()">
                <input type="hidden" name="action" value="log_repair">

                <div class="form-group">
                    <label>SELECT ACTIVE VIOLATION (FROM VIOLATIONS TABLE)</label>
                    <select name="violation_id" required>
                        <option value="">-- Select Open Non-Compliance --</option>
                        <?php foreach ($violations_list as $v): ?>
                            <option value="<?php echo $v['violation_id']; ?>">
                                #<?php echo $v['violation_id']; ?> - <?php echo htmlspecialchars($v['car_name'] ?: 'Grid Entry'); ?> (<?php echo htmlspecialchars($v['measurement_name'] ?: 'Technical Spec'); ?>) - Status: [<?php echo strtoupper($v['status']); ?>]
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label>PERFORMED BY (TECHNICIAN / TEAM CREW)</label>
                    <input type="text" name="performed_by" value="Red Bull Racing - Aero Crew" placeholder="e.g. Lead Chassis Mechanic" required>
                </div>

                <div class="form-row-2">
                    <div class="form-group">
                        <label>REPAIR STATUS</label>
                        <select name="status">
                            <option value="Completed" selected>Completed</option>
                            <option value="In Progress">In Progress</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>TIMESTAMP</label>
                        <input type="text" value="<?php echo date('d/m/Y, H:i'); ?>" readonly>
                    </div>
                </div>

                <div class="form-group">
                    <label>DETAILED REPAIR DESCRIPTION</label>
                    <textarea name="description" rows="3" placeholder="Specify mechanical rectifications, component replacements, or dimension adjustments..." required>Adjusted front wing pylon mounting brackets and removed upper spacer shims to lower the front wing mainplane, reducing height from 150 mm down to the required 100 mm specification.</textarea>
                </div>

                <button type="submit" class="btn-hud btn-red">SUBMIT REPAIR ACTION</button>
            </form>
        </div>

        <!-- Form 2: Delegate Re-Inspection Verdict -->
        <div class="hud-card theme-cyan">
            <div class="hud-card-header">
                <h3><span class="card-num">02.</span> DELEGATE RE-INSPECTION VERDICT</h3>
                <span class="hud-badge-tag">INSPECT-8B</span>
            </div>
            <form method="POST" action="feature8.php" onsubmit="playReinspectVerdictSound()">
                <input type="hidden" name="action" value="save_reinspection">

                <div class="form-group">
                    <label>SELECT COMPLETED REPAIR (FROM REPAIRS TABLE)</label>
                    <select name="repair_id" required>
                        <option value="">-- Select Logged Repair --</option>
                        <?php foreach ($repairs_dropdown as $rep): ?>
                            <option value="<?php echo $rep['repair_id']; ?>">
                                #<?php echo $rep['repair_id']; ?> - <?php echo htmlspecialchars($rep['car_name'] ?: 'Chassis'); ?> - <?php echo htmlspecialchars(mb_strimwidth($rep['description'], 0, 45, '...')); ?> [<?php echo strtoupper($rep['status']); ?>]
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-row-2">
                    <div class="form-group">
                        <label>INSPECTION SESSION</label>
                        <select name="session_id" required>
                            <?php foreach ($sessions_list as $sess): ?>
                                <option value="<?php echo $sess['session_id']; ?>">
                                    #<?php echo $sess['session_id']; ?> - <?php echo htmlspecialchars($sess['session_type']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>ASSIGNED FIA INSPECTOR</label>
                        <select name="inspector_id" required>
                            <?php foreach ($inspectors_list as $insp): ?>
                                <option value="<?php echo $insp['user_id']; ?>" <?php echo ($insp['user_id'] == 2) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($insp['full_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="form-row-2">
                    <div class="form-group">
                        <label>RE-INSPECTION VERDICT</label>
                        <select name="result">
                            <option value="Passed" selected>Passed (Full Clearance)</option>
                            <option value="Failed">Failed (Requires Further Rectification)</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>INSPECTION TIMESTAMP</label>
                        <input type="text" value="<?php echo date('d/m/Y, H:i'); ?>" readonly>
                    </div>
                </div>

                <button type="submit" class="btn-hud btn-cyan">RECORD OFFICIAL VERDICT</button>
            </form>
        </div>
    </div>

    <!-- Master Data Deck Table -->
    <div class="hud-table-wrapper">
        <div class="table-header-bar">
            <span>❖ MASTER REPAIR & RE-INSPECTION DATA DECK</span>
            <span class="count-tag">RELATIONAL ER JOIN: REPAIRS ⨝ RE_INSPECTIONS (<?php echo count($data_deck); ?> ENTRIES)</span>
        </div>
        <table class="hud-table">
            <thead>
                <tr>
                    <th>REPAIR ID</th>
                    <th>VEHICLE / CHASSIS</th>
                    <th>ORIGINAL VIOLATION</th>
                    <th>REPAIR ACTION PERFORMED</th>
                    <th>DELEGATE & SESSION</th>
                    <th>FINAL CLEARANCE</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($data_deck)): ?>
                    <?php foreach ($data_deck as $row): 
                        $res = strtoupper($row['reinspection_result'] ?? '');
                        $badge_class = 'status-pending';
                        $badge_label = 'PENDING RE-INSPECTION';

                        if ($res === 'PASSED') {
                            $badge_class = 'status-passed';
                            $badge_label = 'PASSED & CLEARED';
                        } elseif ($res === 'FAILED') {
                            $badge_class = 'status-failed';
                            $badge_label = 'FAILED';
                        }
                    ?>
                        <tr>
                            <td><strong style="color: #00d2be; font-family: 'Share Tech Mono', monospace;">#REP-<?php echo $row['repair_id']; ?></strong></td>
                            <td>
                                <strong style="color:#ffffff; font-size: 13px;"><?php echo htmlspecialchars($row['car_name'] ?: 'Grid Entry'); ?></strong><br>
                                <span class="serial-pill"><?php echo htmlspecialchars($row['chassis_number'] ?: 'CHASSIS-SPEC'); ?></span>
                                <?php if (!empty($row['team_name'])): ?>
                                    <br><small style="color: #8c8c9e; font-size: 10px;"><?php echo htmlspecialchars($row['team_name']); ?></small>
                                <?php endif; ?>
                            </td>
                            <td style="max-width: 220px; font-size: 11px; color: #dcdce6; line-height: 1.4;">
                                <?php if (!empty($row['measurement_name'])): ?>
                                    <strong style="color: #ff1801;"><?php echo htmlspecialchars($row['measurement_name']); ?>:</strong> 
                                <?php endif; ?>
                                <?php echo htmlspecialchars($row['violation_description'] ?: 'Non-compliance logged'); ?>
                            </td>
                            <td style="max-width: 260px; font-size: 11px; color: #a0a0b2; line-height: 1.4;">
                                <?php echo htmlspecialchars($row['repair_desc']); ?>
                                <br><small style="color: #00d2be; font-family: 'Share Tech Mono', monospace; font-size: 10px;">By: <?php echo htmlspecialchars($row['performed_by']); ?> [<?php echo htmlspecialchars($row['performed_at']); ?>]</small>
                            </td>
                            <td>
                                <?php if (!empty($row['inspector_name'])): ?>
                                    <span style="font-family: 'Share Tech Mono', monospace; font-size: 11px; color: #ffffff;">
                                        <?php echo htmlspecialchars($row['inspector_name']); ?>
                                    </span>
                                    <br><small style="color: #8c8c9e; font-size: 10px;"><?php echo htmlspecialchars($row['session_type'] ?: 'Scrutineering'); ?></small>
                                <?php else: ?>
                                    <span style="color: #8c8c9e; font-style: italic; font-size: 11px;">Awaiting Assignment</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="status-pill <?php echo $badge_class; ?>">
                                    <?php echo htmlspecialchars($badge_label); ?>
                                </span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="6" style="text-align: center; color: #8c8c9e; padding: 20px;">
                            No technical repairs logged yet. Use Form 01 above to record team rectifications.
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- F1 Web Audio Sound Engine -->
<script>
let audioCtx = null;
function getAudioContext() {
    if (!audioCtx) {
        audioCtx = new (window.AudioContext || window.webkitAudioContext)();
    }
    if (audioCtx.state === 'suspended') {
        audioCtx.resume();
    }
    return audioCtx;
}

// 1. Synthesizer Tone Generator
function playTone(freq, duration, type = 'sine', gainVal = 0.15) {
    try {
        const ctx = getAudioContext();
        const osc = ctx.createOscillator();
        const gain = ctx.createGain();

        osc.type = type;
        osc.frequency.setValueAtTime(freq, ctx.currentTime);

        gain.gain.setValueAtTime(gainVal, ctx.currentTime);
        gain.gain.exponentialRampToValueAtTime(0.001, ctx.currentTime + duration);

        osc.connect(gain);
        gain.connect(ctx.destination);

        osc.start();
        osc.stop(ctx.currentTime + duration);
    } catch(e) {}
}

// 2. Mechanical Tool / Repair Action Sound
function playRepairActionSound() {
    try {
        playTone(320, 0.08, 'sawtooth', 0.2);
        setTimeout(() => playTone(480, 0.08, 'sawtooth', 0.2), 70);
        setTimeout(() => playTone(640, 0.12, 'sawtooth', 0.25), 140);
        setTimeout(() => playTone(820, 0.18, 'sine', 0.2), 220);
    } catch(e) {}
}

// 3. Re-Inspection Verdict Success Sound
function playReinspectVerdictSound() {
    try {
        playTone(523, 0.1, 'sine', 0.15);
        setTimeout(() => playTone(659, 0.12, 'sine', 0.18), 80);
        setTimeout(() => playTone(783, 0.15, 'sine', 0.2), 160);
        setTimeout(() => playTone(1046, 0.25, 'sine', 0.22), 240);
    } catch(e) {}
}

// 4. Radio Squelch Sound
function playRadioSquelchSound() {
    try {
        playTone(1100, 0.06, 'sine', 0.2);
        setTimeout(() => playTone(1500, 0.08, 'sine', 0.2), 65);
    } catch(e) {}
}

// Auto play audio feedback on page load if notification message is displayed
window.addEventListener('DOMContentLoaded', () => {
    const action = "<?php echo $action_type; ?>";
    if (action === 'repair') {
        setTimeout(playRepairActionSound, 300);
    } else if (action === 'reinspection') {
        setTimeout(playReinspectVerdictSound, 300);
    }
});
</script>

<style>
    :root {
        --f1-red: #ff1801;
        --f1-cyan: #00d2be;
        --f1-amber: #ffb703;
        --f1-green: #2ecc71;
        --f1-bg: #07070e;
        --f1-panel: rgba(12, 12, 20, 0.95);
    }
    body {
        background-color: var(--f1-bg);
        color: #ffffff;
        font-family: 'Titillium Web', sans-serif;
        margin: 0;
        position: relative;
        min-height: 100vh;
    }

    /* Ambient CRT scanline and glowing halos */
    .crt-scanline {
        position: fixed;
        top: 0; left: 0; width: 100%; height: 100%;
        background: linear-gradient(rgba(18, 16, 16, 0) 50%, rgba(0, 0, 0, 0.25) 50%);
        background-size: 100% 4px;
        z-index: 0;
        pointer-events: none;
        opacity: 0.6;
    }
    .ambient-glow {
        position: fixed;
        width: 450px;
        height: 450px;
        border-radius: 50%;
        filter: blur(140px);
        pointer-events: none;
        z-index: 0;
        opacity: 0.12;
    }
    .glow-red { top: -80px; right: -80px; background: var(--f1-red); }
    .glow-cyan { bottom: -80px; left: calc(var(--sidebar-w, 260px) + 50px); background: var(--f1-cyan); }

    .main-content {
        position: relative;
        z-index: 1;
        padding: 24px;
    }
    .telemetry-top-bar {
        display: flex;
        justify-content: space-between;
        align-items: center;
        background: rgba(14, 14, 24, 0.85);
        border: 1px solid rgba(255, 24, 1, 0.3);
        border-left: 4px solid var(--f1-red);
        border-radius: 6px;
        padding: 12px 18px;
        margin-bottom: 24px;
        font-family: 'Share Tech Mono', monospace;
        font-size: 11px;
        letter-spacing: 1.5px;
        box-shadow: 0 4px 20px rgba(0, 0, 0, 0.4);
    }
    .hud-status-live { color: var(--f1-red); font-weight: 700; display: flex; align-items: center; gap: 8px; }
    .hud-top-actions { display: flex; align-items: center; gap: 16px; }
    .hud-protocol-tag { color: #8c8c9e; }
    .live-dot { animation: pulse 1s infinite alternate; }
    @keyframes pulse { from { opacity: 0.2; transform: scale(0.9); } to { opacity: 1; transform: scale(1.1); } }

    .btn-sound-pill {
        background: rgba(255, 24, 1, 0.15);
        border: 1px solid var(--f1-red);
        color: #ffffff;
        font-family: 'Share Tech Mono', monospace;
        font-size: 10px;
        padding: 4px 10px;
        border-radius: 4px;
        cursor: pointer;
        transition: all 0.2s;
    }
    .btn-sound-pill:hover {
        background: var(--f1-red);
        box-shadow: 0 0 10px rgba(255, 24, 1, 0.7);
    }

    .hud-alert {
        background: rgba(0, 210, 190, 0.12);
        border: 1px solid var(--f1-cyan);
        color: var(--f1-cyan);
        padding: 12px 18px;
        border-radius: 6px;
        font-family: 'Share Tech Mono', monospace;
        font-size: 12px;
        margin-bottom: 20px;
        display: flex;
        align-items: center;
        gap: 10px;
        box-shadow: 0 0 20px rgba(0, 210, 190, 0.2);
        animation: slideDown 0.3s ease-out;
    }
    @keyframes slideDown {
        from { opacity: 0; transform: translateY(-10px); }
        to { opacity: 1; transform: translateY(0); }
    }

    /* Top HUD Metrics Grid */
    .hud-metrics-grid {
        display: grid;
        grid-template-columns: repeat(4, 1fr);
        gap: 16px;
        margin-bottom: 24px;
    }
    .hud-stat-card {
        background: linear-gradient(135deg, rgba(20, 20, 32, 0.9) 0%, rgba(12, 12, 20, 0.95) 100%);
        border: 1px solid rgba(255, 255, 255, 0.08);
        border-radius: 10px;
        padding: 16px;
        position: relative;
        overflow: hidden;
        box-shadow: 0 10px 30px rgba(0, 0, 0, 0.5);
    }
    .hud-stat-card::after {
        content: '';
        position: absolute;
        top: 0; left: 0; width: 100%; height: 3px;
    }
    .theme-amber::after { background: linear-gradient(90deg, var(--f1-amber), transparent); }
    .theme-cyan::after { background: linear-gradient(90deg, var(--f1-cyan), transparent); }
    .theme-green::after { background: linear-gradient(90deg, var(--f1-green), transparent); }
    .theme-red::after { background: linear-gradient(90deg, var(--f1-red), transparent); }

    .stat-label {
        font-family: 'Share Tech Mono', monospace;
        font-size: 10px;
        color: #8c8c9e;
        display: flex;
        justify-content: space-between;
        margin-bottom: 6px;
        letter-spacing: 1px;
    }
    .stat-value {
        font-family: 'Orbitron', sans-serif;
        font-size: 28px;
        font-weight: 900;
        line-height: 1;
        margin-bottom: 4px;
    }
    .stat-foot {
        font-size: 11px;
        color: #717188;
        font-family: 'Titillium Web', sans-serif;
    }

    /* Dual Form Cards Grid */
    .hud-grid-row {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 20px;
        margin-bottom: 28px;
    }
    .hud-card {
        background: var(--f1-panel);
        border: 1px solid rgba(255, 255, 255, 0.08);
        border-radius: 10px;
        padding: 20px;
        backdrop-filter: blur(15px);
        box-shadow: 0 10px 30px rgba(0, 0, 0, 0.5);
    }
    .hud-card.theme-red {
        border-top: 2px solid var(--f1-red);
        box-shadow: inset 0 2px 20px rgba(255, 24, 1, 0.05), 0 10px 30px rgba(0, 0, 0, 0.5);
    }
    .hud-card.theme-cyan {
        border-top: 2px solid var(--f1-cyan);
        box-shadow: inset 0 2px 20px rgba(0, 210, 190, 0.05), 0 10px 30px rgba(0, 0, 0, 0.5);
    }
    .hud-card-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        border-bottom: 1px solid rgba(255, 255, 255, 0.06);
        padding-bottom: 12px;
        margin-bottom: 18px;
    }
    .hud-card-header h3 { font-family: 'Orbitron', sans-serif; font-size: 13px; letter-spacing: 1px; margin: 0; }
    .theme-red .card-num, .theme-red .hud-badge-tag { color: var(--f1-red); }
    .theme-cyan .card-num, .theme-cyan .hud-badge-tag { color: var(--f1-cyan); }
    
    .hud-badge-tag {
        font-family: 'Share Tech Mono', monospace;
        font-size: 10px;
        background: rgba(255, 255, 255, 0.04);
        padding: 2px 8px;
        border-radius: 4px;
        border: 1px solid rgba(255, 255, 255, 0.08);
    }

    .form-group { margin-bottom: 14px; }
    .form-row-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
    label {
        display: block;
        font-family: 'Share Tech Mono', monospace;
        font-size: 10px;
        color: #8c8c9e;
        margin-bottom: 6px;
        letter-spacing: 1px;
    }
    input, select, textarea {
        width: 100%;
        padding: 10px 12px;
        background: rgba(0, 0, 0, 0.4);
        border: 1px solid rgba(255, 255, 255, 0.1);
        border-radius: 6px;
        color: #ffffff;
        font-family: 'Titillium Web', sans-serif;
        font-size: 12px;
        box-sizing: border-box;
        transition: border-color 0.2s;
    }
    input:focus, select:focus, textarea:focus {
        outline: none;
        border-color: rgba(255, 255, 255, 0.3);
        background: rgba(0, 0, 0, 0.6);
    }

    .btn-hud {
        width: 100%;
        padding: 12px;
        font-family: 'Orbitron', sans-serif;
        font-size: 11px;
        font-weight: 900;
        letter-spacing: 1.5px;
        border-radius: 6px;
        cursor: pointer;
        margin-top: 10px;
        transition: all 0.25s ease;
    }
    .btn-red {
        background: linear-gradient(135deg, #ff1801 0%, #b31000 100%);
        color: #ffffff;
        border: 1px solid #ff1801;
        box-shadow: 0 0 15px rgba(255, 24, 1, 0.4);
    }
    .btn-red:hover { box-shadow: 0 0 25px rgba(255, 24, 1, 0.8); transform: translateY(-1px); }
    .btn-cyan {
        background: linear-gradient(135deg, #00d2be 0%, #008a7d 100%);
        color: #000000;
        border: 1px solid #00d2be;
        box-shadow: 0 0 15px rgba(0, 210, 190, 0.4);
    }
    .btn-cyan:hover { box-shadow: 0 0 25px rgba(0, 210, 190, 0.8); transform: translateY(-1px); }

    /* Table Data Deck */
    .hud-table-wrapper {
        background: var(--f1-panel);
        border: 1px solid rgba(255, 255, 255, 0.08);
        border-radius: 10px;
        overflow: hidden;
        backdrop-filter: blur(15px);
        box-shadow: 0 10px 30px rgba(0, 0, 0, 0.5);
    }
    .table-header-bar {
        background: rgba(20, 20, 32, 0.8);
        padding: 14px 18px;
        font-family: 'Orbitron', sans-serif;
        font-size: 11px;
        font-weight: 700;
        display: flex;
        justify-content: space-between;
        border-bottom: 1px solid rgba(255, 255, 255, 0.08);
    }
    .count-tag { font-family: 'Share Tech Mono', monospace; color: #8c8c9e; font-size: 10px; }
    .hud-table { width: 100%; border-collapse: collapse; font-size: 12px; }
    .hud-table th {
        background: rgba(0, 0, 0, 0.3);
        padding: 12px 18px;
        font-family: 'Share Tech Mono', monospace;
        font-size: 10px;
        color: #8c8c9e;
        border-bottom: 1px solid rgba(255, 255, 255, 0.06);
    }
    .hud-table td { padding: 14px 18px; border-bottom: 1px solid rgba(255, 255, 255, 0.04); vertical-align: middle; }
    .hud-table tr:hover td { background: rgba(255, 255, 255, 0.02); }
    .serial-pill {
        font-family: 'Share Tech Mono', monospace;
        color: var(--f1-cyan);
        background: rgba(0, 210, 190, 0.1);
        border: 1px solid rgba(0, 210, 190, 0.3);
        padding: 2px 6px;
        border-radius: 4px;
        font-size: 10px;
        display: inline-block;
        margin-top: 3px;
    }
    .status-pill {
        font-family: 'Share Tech Mono', monospace;
        font-size: 10px;
        padding: 4px 8px;
        border-radius: 4px;
        font-weight: bold;
        display: inline-block;
    }
    .status-passed { background: rgba(46, 204, 113, 0.15); color: var(--f1-green); border: 1px solid var(--f1-green); }
    .status-failed { background: rgba(255, 24, 1, 0.15); color: var(--f1-red); border: 1px solid var(--f1-red); }
    .status-pending { background: rgba(255, 183, 3, 0.15); color: var(--f1-amber); border: 1px solid var(--f1-amber); }

    @media (max-width: 900px) {
        .hud-metrics-grid { grid-template-columns: 1fr 1fr; }
        .hud-grid-row { grid-template-columns: 1fr; }
    }
</style>

</body>
</html>
