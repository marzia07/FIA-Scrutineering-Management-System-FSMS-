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

// Auto-create vehicle_repairs and vehicle_reinspections tables if not exist
$conn->query("CREATE TABLE IF NOT EXISTS vehicle_repairs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    violation_id INT NULL,
    vehicle_name VARCHAR(150) NOT NULL,
    chassis_code VARCHAR(50) NOT NULL,
    violation_desc TEXT NOT NULL,
    repair_description TEXT NOT NULL,
    performed_by VARCHAR(150) NOT NULL,
    status VARCHAR(50) DEFAULT 'Completed',
    performed_at VARCHAR(50) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

$conn->query("CREATE TABLE IF NOT EXISTS vehicle_reinspections (
    id INT AUTO_INCREMENT PRIMARY KEY,
    repair_record_id INT NOT NULL,
    vehicle_name VARCHAR(150) NOT NULL,
    chassis_code VARCHAR(50) NOT NULL,
    inspection_session VARCHAR(100) NOT NULL,
    inspector VARCHAR(100) NOT NULL,
    result VARCHAR(50) DEFAULT 'Passed',
    inspected_at VARCHAR(50) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

// Seed initial repair and re-inspection if empty
$chk_rep = $conn->query("SELECT COUNT(*) as cnt FROM vehicle_repairs");
if ($chk_rep && $chk_rep->fetch_assoc()['cnt'] == 0) {
    $conn->query("INSERT INTO vehicle_repairs (id, violation_id, vehicle_name, chassis_code, violation_desc, repair_description, performed_by, status, performed_at, created_at) VALUES
    (1, 101, 'Red Bull RB20 #1', 'CHASSIS-RB20-01', 'Automatic violation detected: Front Wing Height. Expected: 100 mm, Actual: 150 mm', 'Adjusted front wing pylon mounting brackets and removed upper spacer shims to lower the front wing mainplane, reducing height from 150 mm down to the required 100 mm specification.', 'Red Bull Racing - Aero Crew', 'Completed', '2026-09-03 00:35:00', '2026-09-03 00:35:00'),
    (2, 102, 'Ferrari SF-24 #16', 'CHASSIS-SF24-02', 'Automatic violation detected: Plank Wear. Expected: 9.0 mm, Actual: 8.2 mm', 'Replaced worn forward jabroc plank assembly with compliant 9.5 mm baseline FIA homologated skid block unit.', 'Scuderia Ferrari - Chassis Team', 'Completed', '2026-09-03 01:15:00', '2026-09-03 01:15:00')");
}

$chk_reins = $conn->query("SELECT COUNT(*) as cnt FROM vehicle_reinspections");
if ($chk_reins && $chk_reins->fetch_assoc()['cnt'] == 0) {
    $conn->query("INSERT INTO vehicle_reinspections (id, repair_record_id, vehicle_name, chassis_code, inspection_session, inspector, result, inspected_at, created_at) VALUES
    (1, 1, 'Red Bull RB20 #1', 'CHASSIS-RB20-01', 'Post-Qualifying Scrutineering', 'Jo Bauer', 'Passed', '2026-09-03 00:37:00', '2026-09-03 00:37:00'),
    (2, 2, 'Ferrari SF-24 #16', 'CHASSIS-SF24-02', 'Post-Qualifying Scrutineering', 'Matteo Perini', 'Passed', '2026-09-03 01:22:00', '2026-09-03 01:22:00')");
}

$message = '';
$action_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action']) && $_POST['action'] === 'log_repair') {
        $violation_raw = trim($_POST['violation'] ?? '1');
        $performed_by = trim($_POST['performed_by'] ?? 'Aero Crew');
        $performed_at = trim($_POST['performed_at'] ?? date('d/m/Y, H:i'));
        $status = trim($_POST['status'] ?? 'Completed');
        $repair_description = trim($_POST['repair_description'] ?? '');

        $violation_id = intval($violation_raw);
        $vehicle_name = 'Red Bull RB20 #1';
        $chassis_code = 'CHASSIS-RB20-01';
        $violation_desc = "Automatic violation detected: Front Wing Height. Expected: 100 mm, Actual: 150 mm";

        // Query violation if exists
        $v_query = $conn->query("SELECT * FROM technical_violations WHERE id = $violation_id LIMIT 1");
        if ($v_query && $v_row = $v_query->fetch_assoc()) {
            $vehicle_name = $v_row['vehicle_name'];
            $chassis_code = $v_row['chassis_code'] ?: 'CHASSIS-SPEC';
            $violation_desc = "Automatic violation detected: {$v_row['parameter']}. Expected: {$v_row['expected_value']}, Actual: {$v_row['actual_value']}";
        } elseif ($violation_id === 2) {
            $vehicle_name = 'Ferrari SF-24 #16';
            $chassis_code = 'CHASSIS-SF24-02';
            $violation_desc = "Automatic violation detected: Plank Wear. Expected: 9.0 mm, Actual: 8.2 mm";
        }

        $stmt = $conn->prepare("INSERT INTO vehicle_repairs (violation_id, vehicle_name, chassis_code, violation_desc, repair_description, performed_by, status, performed_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        if ($stmt) {
            $stmt->bind_param("isssssss", $violation_id, $vehicle_name, $chassis_code, $violation_desc, $repair_description, $performed_by, $status, $performed_at);
            $stmt->execute();
        }

        $message = "Vehicle repair action recorded in FIA database.";
        $action_type = "repair";
    } elseif (isset($_POST['action']) && $_POST['action'] === 'save_reinspection') {
        $repair_record_id = intval($_POST['repair_record_id'] ?? 1);
        $inspection_session = trim($_POST['inspection_session'] ?? 'Post-Qualifying Scrutineering');
        $inspector = trim($_POST['inspector'] ?? 'Jo Bauer');
        $result = trim($_POST['result'] ?? 'Passed');
        $inspected_at = trim($_POST['inspected_at'] ?? date('d/m/Y, H:i'));

        $vehicle_name = 'Red Bull RB20 #1';
        $chassis_code = 'CHASSIS-RB20-01';

        $r_query = $conn->query("SELECT vehicle_name, chassis_code FROM vehicle_repairs WHERE id = $repair_record_id LIMIT 1");
        if ($r_query && $r_row = $r_query->fetch_assoc()) {
            $vehicle_name = $r_row['vehicle_name'];
            $chassis_code = $r_row['chassis_code'];
        }

        $stmt = $conn->prepare("INSERT INTO vehicle_reinspections (repair_record_id, vehicle_name, chassis_code, inspection_session, inspector, result, inspected_at) VALUES (?, ?, ?, ?, ?, ?, ?)");
        if ($stmt) {
            $stmt->bind_param("issssss", $repair_record_id, $vehicle_name, $chassis_code, $inspection_session, $inspector, $result, $inspected_at);
            $stmt->execute();
        }

        $message = "Re-inspection result logged and status updated.";
        $action_type = "reinspection";
    }
}

// Fetch violations for Select
$violations_for_select = [];
$res_tv = $conn->query("SELECT id, vehicle_name, chassis_code, parameter, expected_value, actual_value FROM technical_violations ORDER BY id DESC");
if ($res_tv && $res_tv->num_rows > 0) {
    while ($r = $res_tv->fetch_assoc()) {
        $violations_for_select[] = $r;
    }
}

// Fetch repair records
$repairs_list = [];
$res_rep = $conn->query("SELECT * FROM vehicle_repairs ORDER BY id DESC");
if ($res_rep) {
    while ($row = $res_rep->fetch_assoc()) {
        $repairs_list[] = $row;
    }
}

// Fetch re-inspections
$reinspections_list = [];
$res_reins = $conn->query("SELECT * FROM vehicle_reinspections ORDER BY id DESC");
if ($res_reins) {
    while ($row = $res_reins->fetch_assoc()) {
        $reinspections_list[] = $row;
    }
}

$current_page = basename($_SERVER['PHP_SELF']);
include 'navbar.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>FIA FSMS - Feature 7: Repair & Re-Inspection Tracking</title>
    <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@500;700;900&family=Share+Tech+Mono&family=Titillium+Web:wght@400;600;700&display=swap" rel="stylesheet">
</head>
<body>

<!-- Ambient F1 Scanline & Glow Backdrop -->
<div class="crt-scanline"></div>
<div class="ambient-glow glow-red"></div>
<div class="ambient-glow glow-cyan"></div>

<div class="main-content">
    <!-- Top Telemetry Header -->
    <div class="telemetry-top-bar">
        <div class="hud-status-live">
            <span class="live-dot">●</span> REPAIR & RE-INSPECTION STREAM // ACTIVE
        </div>
        <div class="hud-top-actions">
            <button type="button" class="btn-sound-pill" onclick="playRadioSquelchSound()" title="Broadcast Radio Comm">
                📢 RADIO SOUND
            </button>
            <div class="hud-protocol-tag">
                GRID PROTOCOL: FIA 2026 TECHNICAL REGS
            </div>
        </div>
    </div>

    <?php if ($message): ?>
        <div class="hud-alert" id="hudAlertBox">
            <span class="alert-icon">🛠️</span> <?php echo htmlspecialchars($message); ?>
        </div>
    <?php endif; ?>

    <!-- Dual Form Cards -->
    <div class="hud-grid-row">
        <!-- Left Form: Log Vehicle Repair Action -->
        <div class="hud-card theme-red">
            <div class="hud-card-header">
                <h3><span class="card-num">01.</span> LOG VEHICLE REPAIR ACTION</h3>
                <span class="hud-badge-tag">FORM-7A</span>
            </div>
            <form method="POST" action="feature7.php" onsubmit="playRepairSubmitSound()">
                <input type="hidden" name="action" value="log_repair">

                <div class="form-group">
                    <label>VIOLATION</label>
                    <select name="violation" required>
                        <option value="">Select violation</option>
                        <?php if (!empty($violations_for_select)): ?>
                            <?php foreach ($violations_for_select as $idx => $v): ?>
                                <option value="<?php echo $v['id']; ?>" <?php echo ($idx === 0) ? 'selected' : ''; ?>>
                                    #<?php echo $v['id']; ?> - <?php echo htmlspecialchars($v['vehicle_name']); ?> - Automatic violation detected: <?php echo htmlspecialchars($v['parameter']); ?>. Expected: <?php echo htmlspecialchars($v['expected_value']); ?>, Actual: <?php echo htmlspecialchars($v['actual_value']); ?>
                                </option>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <option value="1" selected>#1 - Red Bull RB20 #1 - Automatic violation detected: Front Wing Height. Expected: 100 mm, Actual: 150 mm</option>
                            <option value="2">#2 - Ferrari SF-24 #16 - Automatic violation detected: Plank Wear. Expected: 9.0 mm, Actual: 8.2 mm</option>
                        <?php endif; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label>PERFORMED BY (TECHNICIAN / TEAM)</label>
                    <input type="text" name="performed_by" value="Red Bull Racing - Aero Crew" placeholder="e.g. Lead Mechanic" required>
                </div>

                <div class="form-group">
                    <label>PERFORMED AT</label>
                    <input type="text" name="performed_at" value="<?php echo date('d/m/Y, H:i'); ?>" readonly>
                </div>

                <div class="form-group">
                    <label>STATUS</label>
                    <select name="status">
                        <option value="In Progress">In Progress</option>
                        <option value="Completed" selected>Completed</option>
                    </select>
                </div>

                <div class="form-group">
                    <label>REPAIR DESCRIPTION</label>
                    <textarea name="repair_description" rows="3" placeholder="Describe the mechanical or aerodynamic fix..">Adjusted front wing pylon mounting brackets and removed upper spacer shims to lower the front wing mainplane, reducing height from 150 mm down to the required 100 mm specification.</textarea>
                </div>

                <button type="submit" class="btn-hud btn-red">LOG REPAIR ACTION</button>
            </form>
        </div>

        <!-- Right Form: Record Re-Inspection -->
        <div class="hud-card theme-cyan">
            <div class="hud-card-header">
                <h3><span class="card-num">02.</span> RECORD RE-INSPECTION</h3>
                <span class="hud-badge-tag">VERIFY-7B</span>
            </div>
            <form method="POST" action="feature7.php" onsubmit="playReinspectionSubmitSound()">
                <input type="hidden" name="action" value="save_reinspection">

                <div class="form-group">
                    <label>REPAIR RECORD</label>
                    <select name="repair_record_id" required>
                        <option value="">Select repair action</option>
                        <?php if (!empty($repairs_list)): ?>
                            <?php foreach ($repairs_list as $idx => $rep): ?>
                                <option value="<?php echo $rep['id']; ?>" <?php echo ($idx === 0) ? 'selected' : ''; ?>>
                                    #<?php echo $rep['id']; ?> - <?php echo htmlspecialchars($rep['vehicle_name']); ?> (<?php echo htmlspecialchars(mb_strimwidth($rep['repair_description'], 0, 40, '...')); ?>)
                                </option>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <option value="1" selected>#1 - Red Bull RB20 #1 (Front Wing Adjustment)</option>
                        <?php endif; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label>INSPECTION SESSION</label>
                    <select name="inspection_session" required>
                        <option value="">Select session</option>
                        <option value="Post-Qualifying Scrutineering" selected>Post-Qualifying Scrutineering</option>
                        <option value="Pre-Race Scrutineering">Pre-Race Scrutineering</option>
                    </select>
                </div>

                <div class="form-group">
                    <label>INSPECTOR</label>
                    <select name="inspector" required>
                        <option value="">Select inspector</option>
                        <option value="Jo Bauer" selected>Jo Bauer (Technical Delegate)</option>
                        <option value="Tim Malyon">Tim Malyon (Safety Director)</option>
                        <option value="Matteo Perini">Matteo Perini (Technical Inspector)</option>
                    </select>
                </div>

                <div class="form-group">
                    <label>RE-INSPECTION RESULT</label>
                    <select name="result">
                        <option value="Passed" selected>Passed</option>
                        <option value="Failed">Failed</option>
                    </select>
                </div>

                <div class="form-group">
                    <label>INSPECTED AT</label>
                    <input type="text" name="inspected_at" value="<?php echo date('d/m/Y, H:i'); ?>" readonly>
                </div>

                <button type="submit" class="btn-hud btn-cyan">SAVE RE-INSPECTION</button>
            </form>
        </div>
    </div>

    <!-- Repair History Register Table -->
    <div class="hud-table-wrapper" style="margin-bottom: 24px;">
        <div class="table-header-bar">
            <span>❖ REPAIR HISTORY REGISTER</span>
            <span class="count-tag"><?php echo count($repairs_list); ?> LOGGED REPAIRS</span>
        </div>
        <table class="hud-table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>VEHICLE</th>
                    <th>VIOLATION</th>
                    <th>DESCRIPTION</th>
                    <th>PERFORMED BY</th>
                    <th>STATUS</th>
                    <th>DATE</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($repairs_list)): ?>
                    <?php foreach ($repairs_list as $rep): 
                        $status_str = strtoupper($rep['status'] ?? 'COMPLETED');
                        $status_class = ($status_str === 'COMPLETED') ? 'status-completed' : 'status-pending';
                    ?>
                        <tr>
                            <td>#<?php echo $rep['id']; ?></td>
                            <td>
                                <strong style="color:#ffffff;"><?php echo htmlspecialchars($rep['vehicle_name']); ?></strong><br>
                                <span class="serial-pill"><?php echo htmlspecialchars($rep['chassis_code'] ?: 'CHASSIS-SPEC'); ?></span>
                            </td>
                            <td style="max-width: 220px; font-size: 11px; color: #dcdce6; line-height: 1.4;">
                                <?php echo htmlspecialchars($rep['violation_desc']); ?>
                            </td>
                            <td style="max-width: 250px; font-size: 11px; color: #a0a0b2; line-height: 1.4;">
                                <?php echo htmlspecialchars($rep['repair_description']); ?>
                            </td>
                            <td style="font-family: 'Share Tech Mono', monospace; font-size: 11px; color: #00d2be;">
                                <?php echo htmlspecialchars($rep['performed_by']); ?>
                            </td>
                            <td>
                                <span class="status-pill <?php echo $status_class; ?>">
                                    <?php echo htmlspecialchars($status_str); ?>
                                </span>
                            </td>
                            <td style="font-family: 'Share Tech Mono', monospace; font-size: 10px; color: #8c8c9e;">
                                <?php echo htmlspecialchars($rep['performed_at'] ?: $rep['created_at']); ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td>#1</td>
                        <td><strong style="color:#ffffff;">Red Bull RB20 #1</strong><br><span class="serial-pill">CHASSIS-RB20-01</span></td>
                        <td style="max-width: 220px;">Automatic violation detected: Front Wing Height. Expected: 100 mm, Actual: 150 mm</td>
                        <td style="max-width: 250px;">Adjusted front wing pylon mounting brackets and removed upper spacer shims to lower the front wing mainplane, reducing height from 150 mm down to the required 100 mm specification.</td>
                        <td>Red Bull Racing - Aero Crew</td>
                        <td><span class="status-pill status-completed">COMPLETED</span></td>
                        <td>2026-09-03 00:35:00</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Re-Inspection Results Register Table -->
    <div class="hud-table-wrapper">
        <div class="table-header-bar">
            <span>❖ RE-INSPECTION RESULTS REGISTER</span>
            <span class="count-tag"><?php echo count($reinspections_list); ?> VERIFICATIONS</span>
        </div>
        <table class="hud-table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>REPAIR ID</th>
                    <th>VEHICLE</th>
                    <th>SESSION</th>
                    <th>INSPECTOR</th>
                    <th>RESULT</th>
                    <th>INSPECTED AT</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($reinspections_list)): ?>
                    <?php foreach ($reinspections_list as $reins): 
                        $res_str = strtoupper($reins['result'] ?? 'PASSED');
                        $res_class = ($res_str === 'PASSED') ? 'status-passed' : 'status-failed';
                    ?>
                        <tr>
                            <td>#<?php echo $reins['id']; ?></td>
                            <td><strong style="color: #00d2be; font-family: 'Share Tech Mono', monospace;">#<?php echo $reins['repair_record_id']; ?></strong></td>
                            <td>
                                <strong style="color:#ffffff;"><?php echo htmlspecialchars($reins['vehicle_name']); ?></strong><br>
                                <span class="serial-pill"><?php echo htmlspecialchars($reins['chassis_code'] ?: 'CHASSIS-SPEC'); ?></span>
                            </td>
                            <td style="font-size: 11px; color: #8c8c9e;">
                                <?php echo htmlspecialchars($reins['inspection_session']); ?>
                            </td>
                            <td style="font-family: 'Share Tech Mono', monospace; font-size: 11px; color: #ffffff;">
                                <?php echo htmlspecialchars($reins['inspector']); ?>
                            </td>
                            <td>
                                <span class="status-pill <?php echo $res_class; ?>">
                                    <?php echo htmlspecialchars($res_str); ?>
                                </span>
                            </td>
                            <td style="font-family: 'Share Tech Mono', monospace; font-size: 10px; color: #8c8c9e;">
                                <?php echo htmlspecialchars($reins['inspected_at'] ?: $reins['created_at']); ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td>#1</td>
                        <td>#1</td>
                        <td><strong style="color:#ffffff;">Red Bull RB20 #1</strong><br><span class="serial-pill">CHASSIS-RB20-01</span></td>
                        <td>Post-Qualifying Scrutineering</td>
                        <td>Jo Bauer</td>
                        <td><span class="status-pill status-passed">PASSED</span></td>
                        <td>2026-09-03 00:37:00</td>
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

// 1. Play Synthesizer Tone
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
function playRepairSubmitSound() {
    try {
        // Pneumatic wrench and mechanical click sequence
        playTone(320, 0.08, 'sawtooth', 0.2);
        setTimeout(() => playTone(480, 0.08, 'sawtooth', 0.2), 70);
        setTimeout(() => playTone(640, 0.12, 'sawtooth', 0.25), 140);
        setTimeout(() => playTone(820, 0.18, 'sine', 0.2), 220);
    } catch(e) {}
}

// 3. Re-Inspection Passed Confirmation Sound
function playReinspectionSubmitSound() {
    try {
        playTone(523, 0.1, 'sine', 0.15); // C5
        setTimeout(() => playTone(659, 0.12, 'sine', 0.18), 80); // E5
        setTimeout(() => playTone(783, 0.15, 'sine', 0.2), 160); // G5
        setTimeout(() => playTone(1046, 0.25, 'sine', 0.22), 240); // C6
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
        setTimeout(playRepairSubmitSound, 300);
    } else if (action === 'reinspection') {
        setTimeout(playReinspectionSubmitSound, 300);
    }
});
</script>

<style>
    :root {
        --f1-red: #ff1801;
        --f1-cyan: #00d2be;
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
    .status-completed { background: rgba(0, 210, 190, 0.15); color: var(--f1-cyan); border: 1px solid var(--f1-cyan); }
    .status-pending { background: rgba(241, 196, 15, 0.15); color: #f1c40f; border: 1px solid #f1c40f; }
    .status-passed { background: rgba(46, 204, 113, 0.15); color: #2ecc71; border: 1px solid #2ecc71; }
    .status-failed { background: rgba(255, 24, 1, 0.15); color: #ff1801; border: 1px solid #ff1801; }

    @media (max-width: 900px) {
        .hud-grid-row {
            grid-template-columns: 1fr;
        }
    }
</style>

</body>
</html>
