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

// Auto-create technical_violations table if it does not exist
$conn->query("CREATE TABLE IF NOT EXISTS technical_violations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    vehicle_id INT NULL,
    vehicle_name VARCHAR(150) NOT NULL,
    chassis_code VARCHAR(50) NOT NULL,
    parameter VARCHAR(100) NOT NULL,
    expected_value VARCHAR(50) NOT NULL,
    actual_value VARCHAR(50) NOT NULL,
    severity VARCHAR(60) NOT NULL,
    delegate VARCHAR(100) DEFAULT 'Jo Bauer',
    verification_status VARCHAR(60) DEFAULT 'Under Investigation',
    notes TEXT,
    detected_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

// Seed initial technical violations if empty
$chk_viol = $conn->query("SELECT COUNT(*) as cnt FROM technical_violations");
if ($chk_viol && $chk_viol->fetch_assoc()['cnt'] == 0) {
    $conn->query("INSERT INTO technical_violations (id, vehicle_id, vehicle_name, chassis_code, parameter, expected_value, actual_value, severity, delegate, verification_status, notes) VALUES
    (101, 1, 'Red Bull RB20 #1 (Max Verstappen)', 'CHASSIS-RB20-01', 'Front Wing Height', '100 mm', '150 mm', 'Major Safety Violation', 'Jo Bauer', 'Pending Stewards', 'Front wing main plane trailing edge exceeds 100mm height limit under 50N test load.'),
    (102, 3, 'Ferrari SF-24 #16 (Charles Leclerc)', 'CHASSIS-SF24-02', 'Plank Wear Thickness', '9.0 mm', '8.2 mm', 'Critical Performance Advantage', 'Matteo Perini', 'Under Investigation', 'Post-qualifying ultrasonic gauge measured 8.2mm at forward skid block holes.')");
}

$message = '';
$action_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action']) && $_POST['action'] === 'log_violation') {
        $vehicle_id = intval($_POST['vehicle_id'] ?? 0);
        $parameter = trim($_POST['parameter'] ?? '');
        $expected_value = trim($_POST['expected_value'] ?? '');
        $actual_value = trim($_POST['actual_value'] ?? '');
        $severity = trim($_POST['severity'] ?? 'Minor Technical Non-Compliance');

        // Look up car details if available in DB
        $v_name = "Vehicle #{$vehicle_id}";
        $c_code = "CHASSIS-GEN-" . str_pad($vehicle_id, 2, '0', STR_PAD_LEFT);
        
        $car_query = $conn->query("SELECT c.chassis_number, c.car_name, d.full_name, t.team_name 
                                  FROM CARS c 
                                  LEFT JOIN DRIVERS d ON c.driver_id = d.driver_id 
                                  LEFT JOIN TEAMS t ON c.team_id = t.team_id 
                                  WHERE c.car_id = $vehicle_id LIMIT 1");
        if ($car_query && $c_row = $car_query->fetch_assoc()) {
            $v_name = ($c_row['team_name'] ?? 'Team') . " " . ($c_row['car_name'] ?? 'F1 Car') . " (" . ($c_row['full_name'] ?? 'Driver') . ")";
            $c_code = $c_row['chassis_number'] ?: $c_code;
        } else {
            // Fallback presets
            $presets = [
                1 => ['Red Bull RB20 #1 (Max Verstappen)', 'CHASSIS-RB20-01'],
                2 => ['Red Bull RB20 #2 (Sergio Perez)', 'CHASSIS-RB20-02'],
                3 => ['Ferrari SF-24 #16 (Charles Leclerc)', 'CHASSIS-SF24-02'],
                4 => ['Mercedes W15 #63 (George Russell)', 'CHASSIS-W15-01']
            ];
            if (isset($presets[$vehicle_id])) {
                $v_name = $presets[$vehicle_id][0];
                $c_code = $presets[$vehicle_id][1];
            }
        }

        $stmt = $conn->prepare("INSERT INTO technical_violations (vehicle_id, vehicle_name, chassis_code, parameter, expected_value, actual_value, severity, verification_status) VALUES (?, ?, ?, ?, ?, ?, ?, 'Pending Stewards')");
        if ($stmt) {
            $stmt->bind_param("issssss", $vehicle_id, $v_name, $c_code, $parameter, $expected_value, $actual_value, $severity);
            $stmt->execute();
        }

        $message = "Violation successfully detected and logged into FIA database.";
        $action_type = "violation";
    } elseif (isset($_POST['action']) && $_POST['action'] === 'flag_verification') {
        $violation_id = intval($_POST['violation_id'] ?? 0);
        $delegate = trim($_POST['delegate'] ?? 'Jo Bauer');
        $verification_status = trim($_POST['verification_status'] ?? 'Under Investigation');
        $notes = trim($_POST['notes'] ?? '');

        $stmt = $conn->prepare("UPDATE technical_violations SET delegate = ?, verification_status = ?, notes = ? WHERE id = ?");
        if ($stmt) {
            $stmt->bind_param("sssi", $delegate, $verification_status, $notes, $violation_id);
            $stmt->execute();
        }

        $message = "Violation verification status updated.";
        $action_type = "verification";
    }
}

// Fetch all violations for table & select dropdowns
$violations_list = [];
$res_v = $conn->query("SELECT * FROM technical_violations ORDER BY id DESC");
if ($res_v) {
    while ($row = $res_v->fetch_assoc()) {
        $violations_list[] = $row;
    }
}

// Fetch vehicle options from CARS table if available
$db_cars = [];
$res_c = $conn->query("SELECT c.car_id, c.car_name, c.chassis_number, d.full_name, t.team_name 
                       FROM CARS c 
                       LEFT JOIN DRIVERS d ON c.driver_id = d.driver_id 
                       LEFT JOIN TEAMS t ON c.team_id = t.team_id 
                       ORDER BY c.car_id ASC");
if ($res_c && $res_c->num_rows > 0) {
    while ($r = $res_c->fetch_assoc()) {
        $db_cars[] = $r;
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
    <title>FIA FSMS - Feature 5: Violation Detection</title>
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
            <span class="live-dot">●</span> AUTOMATIC TECHNICAL VIOLATION DETECTION // LIVE STREAM
        </div>
        <div class="hud-top-actions">
            <button type="button" class="btn-sound-pill" onclick="playTelemetrySquelch()" title="Trigger Audio Sound Check">
                📢 RADIO SOUND
            </button>
            <div class="hud-protocol-tag">
                GRID PROTOCOL: FIA 2026 TECHNICAL REGS
            </div>
        </div>
    </div>

    <?php if ($message): ?>
        <div class="hud-alert" id="hudAlertBox">
            <span class="alert-icon">⚡</span> <?php echo htmlspecialchars($message); ?>
        </div>
    <?php endif; ?>

    <!-- Dual Action HUD Cards -->
    <div class="hud-grid-row">
        <!-- Left Form: Log Technical Violation -->
        <div class="hud-card theme-red">
            <div class="hud-card-header">
                <h3><span class="card-num">01.</span> LOG TECHNICAL VIOLATION</h3>
                <span class="hud-badge-tag">SCAN-5A</span>
            </div>
            <form method="POST" action="feature5.php" onsubmit="playViolationTriggerSound()">
                <input type="hidden" name="action" value="log_violation">
                
                <div class="form-group">
                    <label>SELECT VEHICLE ENTRY</label>
                    <select name="vehicle_id" required>
                        <option value="">-- Select Monocoque / Vehicle --</option>
                        <?php if (!empty($db_cars)): ?>
                            <?php foreach ($db_cars as $c): ?>
                                <option value="<?php echo $c['car_id']; ?>">
                                    <?php echo htmlspecialchars(($c['team_name'] ?: 'Team') . ' ' . $c['car_name'] . ' (' . ($c['full_name'] ?: 'Chassis ' . $c['chassis_number']) . ')'); ?>
                                </option>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <option value="1">Red Bull RB20 #1 (Max Verstappen)</option>
                            <option value="2">Red Bull RB20 #2 (Sergio Perez)</option>
                            <option value="3">Ferrari SF-24 #16 (Charles Leclerc)</option>
                            <option value="4">Mercedes W15 #63 (George Russell)</option>
                        <?php endif; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label>REGULATION PARAMETER</label>
                    <select name="parameter" required>
                        <option value="Front Wing Height">Front Wing Height (Max Allowed: 100 mm)</option>
                        <option value="Plank Wear Thickness">Plank Wear Thickness (Min Allowed: 9.0 mm)</option>
                        <option value="Fuel Mass Flow Rate">Fuel Mass Flow Rate (Max Peak: 100 kg/h)</option>
                        <option value="Rear Wing DRS Opening">Rear Wing DRS Opening (Max Gap: 85 mm)</option>
                    </select>
                </div>

                <div class="form-row-2">
                    <div class="form-group">
                        <label>EXPECTED VALUE</label>
                        <input type="text" name="expected_value" placeholder="e.g. 100 mm" required>
                    </div>
                    <div class="form-group">
                        <label>MEASURED ACTUAL</label>
                        <input type="text" name="actual_value" placeholder="e.g. 150 mm" required>
                    </div>
                </div>

                <div class="form-group">
                    <label>SEVERITY LEVEL</label>
                    <select name="severity">
                        <option value="Minor Technical Non-Compliance">Minor Technical Non-Compliance</option>
                        <option value="Major Safety Violation">Major Safety Violation</option>
                        <option value="Critical Performance Advantage">Critical Performance Advantage</option>
                    </select>
                </div>

                <button type="submit" class="btn-hud btn-red">TRIGGER VIOLATION ENTRY</button>
            </form>
        </div>

        <!-- Right Form: Flag & Delegate Review -->
        <div class="hud-card theme-cyan">
            <div class="hud-card-header">
                <h3><span class="card-num">02.</span> DELEGATE REVIEW & VERIFICATION</h3>
                <span class="hud-badge-tag">VERIFY-5B</span>
            </div>
            <form method="POST" action="feature5.php" onsubmit="playVerificationSubmitSound()">
                <input type="hidden" name="action" value="flag_verification">
                
                <div class="form-group">
                    <label>ACTIVE VIOLATION INCIDENT</label>
                    <select name="violation_id" required>
                        <option value="">-- Select Active Violation --</option>
                        <?php if (!empty($violations_list)): ?>
                            <?php foreach ($violations_list as $v): ?>
                                <option value="<?php echo $v['id']; ?>">
                                    #<?php echo $v['id']; ?> - <?php echo htmlspecialchars($v['vehicle_name']); ?> - <?php echo htmlspecialchars($v['parameter']); ?> (<?php echo htmlspecialchars($v['actual_value']); ?>)
                                </option>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <option value="101">#101 - RB20 #1 - Front Wing Height Exceeded (+50mm)</option>
                            <option value="102">#102 - SF-24 #16 - Skid Block Wear Under Limit (-0.8mm)</option>
                        <?php endif; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label>ASSIGN TECHNICAL DELEGATE</label>
                    <select name="delegate" required>
                        <option value="Jo Bauer">Jo Bauer (FIA Chief Technical Delegate)</option>
                        <option value="Tim Malyon">Tim Malyon (FIA Safety Director)</option>
                        <option value="Matteo Perini">Matteo Perini (Technical Inspector)</option>
                    </select>
                </div>

                <div class="form-group">
                    <label>VERIFICATION STATUS</label>
                    <select name="verification_status">
                        <option value="Under Investigation">Under Investigation</option>
                        <option value="Confirmed Non-Compliant">Confirmed Non-Compliant</option>
                        <option value="Dismissed / Sensor Error">Dismissed / Sensor Error</option>
                    </select>
                </div>

                <div class="form-group">
                    <label>DELEGATE NOTES</label>
                    <textarea name="notes" rows="3" placeholder="Telemetry anomalies or physical measurement verification notes..."></textarea>
                </div>

                <button type="submit" class="btn-hud btn-cyan">UPDATE VERIFICATION STATUS</button>
            </form>
        </div>
    </div>

    <!-- Telemetry Data Register Table -->
    <div class="hud-table-wrapper">
        <div class="table-header-bar">
            <span>❖ DETECTED TECHNICAL VIOLATIONS REGISTER</span>
            <span class="count-tag">ACTIVE STREAM LOGS (<?php echo count($violations_list); ?>)</span>
        </div>
        <table class="hud-table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>VEHICLE</th>
                    <th>PARAMETER</th>
                    <th>EXPECTED / ACTUAL</th>
                    <th>SEVERITY</th>
                    <th>STATUS</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($violations_list)): ?>
                    <?php foreach ($violations_list as $v): 
                        $sev_class = 'tag-red';
                        if (stripos($v['severity'], 'Minor') !== false) $sev_class = 'tag-amber';
                        
                        $stat_class = 'status-pending';
                        if (stripos($v['verification_status'], 'Investig') !== false || stripos($v['verification_status'], 'Review') !== false) {
                            $stat_class = 'status-active';
                        } elseif (stripos($v['verification_status'], 'Dismiss') !== false) {
                            $stat_class = 'status-dismissed';
                        } elseif (stripos($v['verification_status'], 'Confirm') !== false) {
                            $stat_class = 'status-confirmed';
                        }
                    ?>
                        <tr>
                            <td>#<?php echo $v['id']; ?></td>
                            <td>
                                <span class="serial-pill"><?php echo htmlspecialchars($v['chassis_code'] ?: 'CHASSIS-SPEC'); ?></span><br>
                                <small style="color:#8c8c9e; font-size: 11px;"><?php echo htmlspecialchars($v['vehicle_name']); ?></small>
                            </td>
                            <td>
                                <strong style="color: #fff;"><?php echo htmlspecialchars($v['parameter']); ?></strong>
                                <?php if (!empty($v['delegate'])): ?>
                                    <br><small style="color: #00d2be; font-family: 'Share Tech Mono', monospace; font-size: 10px;">Del: <?php echo htmlspecialchars($v['delegate']); ?></small>
                                <?php endif; ?>
                            </td>
                            <td>
                                Expected: <?php echo htmlspecialchars($v['expected_value']); ?><br>
                                <strong style="color:#ff1801;">Actual: <?php echo htmlspecialchars($v['actual_value']); ?></strong>
                            </td>
                            <td>
                                <span class="badge-tag <?php echo $sev_class; ?>">
                                    <?php echo htmlspecialchars($v['severity']); ?>
                                </span>
                            </td>
                            <td>
                                <span class="status-pill <?php echo $stat_class; ?>">
                                    <?php echo htmlspecialchars(strtoupper($v['verification_status'])); ?>
                                </span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td>#101</td>
                        <td><span class="serial-pill">CHASSIS-RB20-01</span><br><small style="color:#8c8c9e;">Red Bull RB20 #1</small></td>
                        <td>Front Wing Height</td>
                        <td>Expected: 100 mm<br><strong style="color:#ff1801;">Actual: 150 mm</strong></td>
                        <td><span class="badge-tag tag-red">Major Non-Compliance</span></td>
                        <td><span class="status-pill status-pending">PENDING STEWARDS</span></td>
                    </tr>
                    <tr>
                        <td>#102</td>
                        <td><span class="serial-pill">CHASSIS-SF24-02</span><br><small style="color:#8c8c9e;">Ferrari SF-24 #16</small></td>
                        <td>Plank Wear Thickness</td>
                        <td>Expected: 9.0 mm<br><strong style="color:#ff1801;">Actual: 8.2 mm</strong></td>
                        <td><span class="badge-tag tag-red">Performance Advantage</span></td>
                        <td><span class="status-pill status-active">UNDER REVIEW</span></td>
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

// 1. Play Tone Generator
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

// 2. F1 Violation Alarm Sound (Warning Sweep)
function playViolationTriggerSound() {
    try {
        const ctx = getAudioContext();
        // High alert sweep (1200Hz down to 400Hz)
        playTone(950, 0.15, 'sawtooth', 0.2);
        setTimeout(() => playTone(650, 0.25, 'sawtooth', 0.2), 150);
        setTimeout(() => playTone(1200, 0.1, 'sine', 0.15), 350);
    } catch(e) {}
}

// 3. Delegate Verification Tone (Positive Cyan Confirmation)
function playVerificationSubmitSound() {
    try {
        playTone(520, 0.08, 'sine', 0.15);
        setTimeout(() => playTone(780, 0.12, 'sine', 0.18), 80);
        setTimeout(() => playTone(1040, 0.18, 'sine', 0.15), 180);
    } catch(e) {}
}

// 4. Radio Squelch Sound
function playTelemetrySquelch() {
    try {
        playTone(1150, 0.06, 'sine', 0.2);
        setTimeout(() => playTone(1550, 0.08, 'sine', 0.2), 65);
    } catch(e) {}
}

// Auto play notification sound on page load if message is displayed
window.addEventListener('DOMContentLoaded', () => {
    const action = "<?php echo $action_type; ?>";
    if (action === 'violation') {
        setTimeout(playViolationTriggerSound, 300);
    } else if (action === 'verification') {
        setTimeout(playVerificationSubmitSound, 300);
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
    .hud-card-header h3 {
        font-family: 'Orbitron', sans-serif;
        font-size: 13px;
        letter-spacing: 1px;
        margin: 0;
    }
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

    .form-group {
        margin-bottom: 14px;
    }
    .form-row-2 {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 12px;
    }
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
    .hud-table {
        width: 100%;
        border-collapse: collapse;
        text-align: left;
        font-size: 12px;
    }
    .hud-table th {
        background: rgba(0, 0, 0, 0.3);
        padding: 12px 18px;
        font-family: 'Share Tech Mono', monospace;
        font-size: 10px;
        color: #8c8c9e;
        border-bottom: 1px solid rgba(255, 255, 255, 0.06);
    }
    .hud-table td {
        padding: 14px 18px;
        border-bottom: 1px solid rgba(255, 255, 255, 0.04);
        vertical-align: middle;
    }
    .hud-table tr:hover td {
        background: rgba(255, 255, 255, 0.02);
    }
    .serial-pill {
        font-family: 'Share Tech Mono', monospace;
        color: var(--f1-cyan);
        background: rgba(0, 210, 190, 0.1);
        border: 1px solid rgba(0, 210, 190, 0.3);
        padding: 2px 6px;
        border-radius: 4px;
        font-size: 10px;
        display: inline-block;
        margin-bottom: 3px;
    }
    .badge-tag {
        font-family: 'Share Tech Mono', monospace;
        font-size: 10px;
        padding: 2px 6px;
        border-radius: 4px;
        display: inline-block;
    }
    .tag-red { background: rgba(255, 24, 1, 0.15); color: #ff4757; border: 1px solid rgba(255, 24, 1, 0.3); }
    .tag-amber { background: rgba(241, 196, 15, 0.15); color: #f1c40f; border: 1px solid rgba(241, 196, 15, 0.3); }

    .status-pill {
        font-family: 'Share Tech Mono', monospace;
        font-size: 10px;
        padding: 4px 8px;
        border-radius: 4px;
        font-weight: bold;
        display: inline-block;
    }
    .status-pending { background: rgba(241, 196, 15, 0.15); color: #f1c40f; border: 1px solid #f1c40f; }
    .status-active { background: rgba(0, 210, 190, 0.15); color: var(--f1-cyan); border: 1px solid var(--f1-cyan); }
    .status-confirmed { background: rgba(255, 24, 1, 0.15); color: #ff4757; border: 1px solid #ff4757; }
    .status-dismissed { background: rgba(140, 140, 158, 0.15); color: #8c8c9e; border: 1px solid #8c8c9e; }

    @media (max-width: 900px) {
        .hud-grid-row {
            grid-template-columns: 1fr;
        }
    }
</style>

</body>
</html>
