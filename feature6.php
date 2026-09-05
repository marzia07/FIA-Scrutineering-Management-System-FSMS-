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

// Auto-create steward_decisions table if it does not exist
$conn->query("CREATE TABLE IF NOT EXISTS steward_decisions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    decision_code VARCHAR(50) NOT NULL UNIQUE,
    violation_id INT NULL,
    target_entity VARCHAR(150) NOT NULL,
    driver_name VARCHAR(100) NULL,
    team_name VARCHAR(100) NULL,
    penalty_type VARCHAR(100) NOT NULL,
    rationale TEXT NOT NULL,
    steward_name VARCHAR(100) DEFAULT 'Garry Connelly',
    decision_status VARCHAR(60) DEFAULT 'Confirmed & Published',
    hearing_time VARCHAR(50) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

// Seed initial steward decision if empty
$chk_dec = $conn->query("SELECT COUNT(*) as cnt FROM steward_decisions");
if ($chk_dec && $chk_dec->fetch_assoc()['cnt'] == 0) {
    $conn->query("INSERT INTO steward_decisions (id, decision_code, violation_id, target_entity, driver_name, team_name, penalty_type, rationale, steward_name, decision_status, hearing_time) VALUES
    (201, 'PEN-201', 101, 'Max Verstappen / Red Bull Racing', 'Max Verstappen #1', 'RED BULL RACING', 'Disqualification from Qualifying', 'Car #1 front wing flap assembly failed vertical load deflection test by 50mm.', 'Garry Connelly', 'Confirmed & Published', '04/09/2026, 17:30'),
    (202, 'PEN-202', 102, 'Charles Leclerc / Scuderia Ferrari', 'Charles Leclerc #16', 'SCUDERIA FERRARI', '10-Place Grid Drop', 'Skid block plank wear exceeded 1.0mm tolerance under Technical Reg Article 3.5.9.', 'Derek Warwick', 'Confirmed & Published', '04/09/2026, 17:45')");
}

$message = '';
$action_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action']) && $_POST['action'] === 'issue_penalty') {
        $violation_id = intval($_POST['violation_id'] ?? 0);
        $penalty_type = trim($_POST['penalty_type'] ?? '');
        $target_entity = trim($_POST['target_entity'] ?? 'Driver / Team');
        $rationale = trim($_POST['rationale'] ?? '');
        $hearing_time = date('d/m/Y, H:i');

        // Extract team and driver
        $parts = explode('/', $target_entity);
        $driver_name = trim($parts[0] ?? $target_entity);
        $team_name = trim($parts[1] ?? 'FIA CONSTRUCTOR');

        // Generate next decision code
        $res_cnt = $conn->query("SELECT MAX(id) as max_id FROM steward_decisions");
        $next_num = 203;
        if ($res_cnt && $row_max = $res_cnt->fetch_assoc()) {
            $next_num = max(201, intval($row_max['max_id']) + 1);
        }
        $decision_code = "PEN-" . $next_num;

        $stmt = $conn->prepare("INSERT INTO steward_decisions (id, decision_code, violation_id, target_entity, driver_name, team_name, penalty_type, rationale, steward_name, decision_status, hearing_time) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'Garry Connelly', 'Confirmed & Published', ?)");
        if ($stmt) {
            $stmt->bind_param("isissssss", $next_num, $decision_code, $violation_id, $target_entity, $driver_name, $team_name, $penalty_type, $rationale, $hearing_time);
            $stmt->execute();
        }

        $message = "Steward decision officialized and communicated to team principal.";
        $action_type = "penalty";
    } elseif (isset($_POST['action']) && $_POST['action'] === 'signoff_decision') {
        $penalty_id = intval($_POST['penalty_id'] ?? 0);
        $steward_name = trim($_POST['steward_name'] ?? 'Garry Connelly');
        $decision_status = trim($_POST['decision_status'] ?? 'Confirmed & Published');
        $hearing_time = trim($_POST['hearing_time'] ?? date('d/m/Y, H:i'));

        $stmt = $conn->prepare("UPDATE steward_decisions SET steward_name = ?, decision_status = ?, hearing_time = ? WHERE id = ?");
        if ($stmt) {
            $stmt->bind_param("sssi", $steward_name, $decision_status, $hearing_time, $penalty_id);
            $stmt->execute();
        }

        $message = "Decision signed off by FIA Panel of Stewards.";
        $action_type = "signoff";
    }
}

// Fetch active violations for dropdown
$active_violations = [];
$res_v = $conn->query("SELECT id, vehicle_name, parameter, actual_value FROM technical_violations ORDER BY id DESC");
if ($res_v && $res_v->num_rows > 0) {
    while ($row = $res_v->fetch_assoc()) {
        $active_violations[] = $row;
    }
} else {
    $active_violations = [
        ['id' => 101, 'vehicle_name' => 'Red Bull RB20 #1 (Max Verstappen)', 'parameter' => 'Front Wing Height', 'actual_value' => '150mm'],
        ['id' => 102, 'vehicle_name' => 'Ferrari SF-24 #16 (Charles Leclerc)', 'parameter' => 'Plank Wear', 'actual_value' => '8.2mm']
    ];
}

// Fetch all decisions for Table & Penalty Sign-Off dropdown
$decisions_list = [];
$res_d = $conn->query("SELECT * FROM steward_decisions ORDER BY id DESC");
if ($res_d) {
    while ($row = $res_d->fetch_assoc()) {
        $decisions_list[] = $row;
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
    <title>FIA FSMS - Feature 6: Penalties & Steward Decisions</title>
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
            <span class="live-dot">●</span> FIA STEWARDS PANEL // JUDICIAL DECISION HUB
        </div>
        <div class="hud-top-actions">
            <button type="button" class="btn-sound-pill" onclick="playRadioSquelchSound()" title="Broadcast Radio Comm">
                📢 RADIO SOUND
            </button>
            <div class="hud-protocol-tag">
                ARTICLE 33.3: INTERNATIONAL SPORTING CODE
            </div>
        </div>
    </div>

    <?php if ($message): ?>
        <div class="hud-alert" id="hudAlertBox">
            <span class="alert-icon">⚖️</span> <?php echo htmlspecialchars($message); ?>
        </div>
    <?php endif; ?>

    <!-- Dual Form Cards -->
    <div class="hud-grid-row">
        <!-- Left Form: Issue Penalty -->
        <div class="hud-card theme-red">
            <div class="hud-card-header">
                <h3><span class="card-num">01.</span> ISSUE STEWARD PENALTY</h3>
                <span class="hud-badge-tag">DOC-6A</span>
            </div>
            <form method="POST" action="feature6.php" onsubmit="playPenaltySubmitSound()">
                <input type="hidden" name="action" value="issue_penalty">

                <div class="form-group">
                    <label>LINKED TECHNICAL VIOLATION</label>
                    <select name="violation_id" required>
                        <option value="">-- Select Confirmed Violation --</option>
                        <?php foreach ($active_violations as $v): ?>
                            <option value="<?php echo $v['id']; ?>">
                                #<?php echo $v['id']; ?> - <?php echo htmlspecialchars($v['vehicle_name']); ?> (<?php echo htmlspecialchars($v['parameter']); ?> <?php echo htmlspecialchars($v['actual_value']); ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label>PENALTY TYPE</label>
                    <select name="penalty_type" required>
                        <option value="5-Second Time Penalty">5-Second Time Penalty</option>
                        <option value="10-Second Time Penalty">10-Second Time Penalty</option>
                        <option value="10-Place Grid Drop">10-Place Grid Drop</option>
                        <option value="Disqualification from Event">Disqualification from Event (DSQ)</option>
                        <option value="Constructor Fine (€50,000)">Constructor Fine (€50,000)</option>
                    </select>
                </div>

                <div class="form-group">
                    <label>APPLIED TO PILOT / CONSTRUCTOR</label>
                    <input type="text" name="target_entity" value="Max Verstappen / Red Bull Racing" required>
                </div>

                <div class="form-group">
                    <label>STEWARD RATIONALE & FINDINGS</label>
                    <textarea name="rationale" rows="3" placeholder="State reason for penalty under technical regulation clauses..." required></textarea>
                </div>

                <button type="submit" class="btn-hud btn-red">ISSUE OFFICIAL PENALTY</button>
            </form>
        </div>

        <!-- Right Form: Steward Sign-Off -->
        <div class="hud-card theme-cyan">
            <div class="hud-card-header">
                <h3><span class="card-num">02.</span> PANEL RATIFICATION & SIGN-OFF</h3>
                <span class="hud-badge-tag">SIGN-6B</span>
            </div>
            <form method="POST" action="feature6.php" onsubmit="playRatifySubmitSound()">
                <input type="hidden" name="action" value="signoff_decision">

                <div class="form-group">
                    <label>PENDING PENALTY RECORD</label>
                    <select name="penalty_id" required>
                        <option value="">-- Select Penalty Document --</option>
                        <?php if (!empty($decisions_list)): ?>
                            <?php foreach ($decisions_list as $d): ?>
                                <option value="<?php echo $d['id']; ?>">
                                    #<?php echo htmlspecialchars($d['decision_code']); ?> - <?php echo htmlspecialchars($d['driver_name'] ?: $d['target_entity']); ?> (<?php echo htmlspecialchars($d['penalty_type']); ?>)
                                </option>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <option value="201">#PEN-201 - RB20 #1 DSQ (Front Wing Height)</option>
                            <option value="202">#PEN-202 - SF-24 #16 10-Place Grid Drop</option>
                        <?php endif; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label>PRESIDING FIA STEWARD</label>
                    <select name="steward_name" required>
                        <option value="Garry Connelly">Garry Connelly (Chairman of Stewards)</option>
                        <option value="Felix Holter">Felix Holter (FIA Steward)</option>
                        <option value="Derek Warwick">Derek Warwick (Driver Steward)</option>
                    </select>
                </div>

                <div class="form-group">
                    <label>RATIFICATION STATUS</label>
                    <select name="decision_status">
                        <option value="Confirmed & Published">Confirmed & Published</option>
                        <option value="Suspended Pending Appeal">Suspended Pending Appeal</option>
                        <option value="Rescinded">Rescinded</option>
                    </select>
                </div>

                <div class="form-group">
                    <label>HEARING TIME STAMP</label>
                    <input type="text" name="hearing_time" value="<?php echo date('d/m/Y, H:i'); ?>" readonly>
                </div>

                <button type="submit" class="btn-hud btn-cyan">RATIFY STEWARD DECISION</button>
            </form>
        </div>
    </div>

    <!-- Official Register Table -->
    <div class="hud-table-wrapper">
        <div class="table-header-bar">
            <span>❖ OFFICIAL STEWARD DECISIONS REGISTER</span>
            <span class="count-tag">FIA GAZETTE RECORD (<?php echo count($decisions_list); ?> DECISIONS)</span>
        </div>
        <table class="hud-table">
            <thead>
                <tr>
                    <th>DECISION ID</th>
                    <th>TARGET ENTRY</th>
                    <th>PENALTY ISSUED</th>
                    <th>STEWARD RATIONALE</th>
                    <th>PRESIDING STEWARD</th>
                    <th>STATUS</th>
                    <th>OFFICIAL GAZETTE</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($decisions_list)): ?>
                    <?php foreach ($decisions_list as $d): 
                        $status_str = strtoupper($d['decision_status']);
                        $status_class = 'status-dsq';
                        if (stripos($status_str, 'SUSPEND') !== false || stripos($status_str, 'APPEAL') !== false) {
                            $status_class = 'status-appeal';
                        } elseif (stripos($status_str, 'RESCIND') !== false) {
                            $status_class = 'status-rescinded';
                        }
                    ?>
                        <tr>
                            <td><strong style="color: #00d2be; font-family: 'Share Tech Mono', monospace;">#<?php echo htmlspecialchars($d['decision_code']); ?></strong></td>
                            <td>
                                <span class="serial-pill"><?php echo htmlspecialchars($d['team_name'] ?: 'FIA CONSTRUCTOR'); ?></span><br>
                                <small style="color:#8c8c9e; font-size: 11px;"><?php echo htmlspecialchars($d['driver_name'] ?: $d['target_entity']); ?></small>
                            </td>
                            <td>
                                <strong style="color:#ff1801; font-family: 'Orbitron', sans-serif; font-size: 11px;">
                                    <?php echo htmlspecialchars($d['penalty_type']); ?>
                                </strong>
                            </td>
                            <td style="color: #dcdce6; font-size: 11px; max-width: 280px; line-height: 1.4;">
                                <?php echo htmlspecialchars($d['rationale']); ?>
                            </td>
                            <td>
                                <span style="font-family: 'Share Tech Mono', monospace; font-size: 11px; color: #ffffff;">
                                    <?php echo htmlspecialchars($d['steward_name']); ?>
                                </span>
                                <?php if (!empty($d['hearing_time'])): ?>
                                    <br><small style="color: #8c8c9e; font-size: 10px;"><?php echo htmlspecialchars($d['hearing_time']); ?></small>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="status-pill <?php echo $status_class; ?>">
                                    <?php echo htmlspecialchars($status_str); ?>
                                </span>
                            </td>
                            <td>
                                <a href="export_document.php?doc_type=decision&id=<?php echo $d['id']; ?>" target="_blank" class="btn-sound-pill" style="text-decoration:none; display:inline-flex; align-items:center; gap:5px; border-color:var(--f1-cyan); color:var(--f1-cyan); background:rgba(0,210,190,0.1);">
                                    <span>📄</span> PRINT DOC
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td>#PEN-201</td>
                        <td><span class="serial-pill">RED BULL RACING</span><br><small style="color:#8c8c9e;">Max Verstappen #1</small></td>
                        <td><strong style="color:#ff1801;">Disqualification from Qualifying</strong></td>
                        <td>Car #1 front wing flap assembly failed vertical load deflection test by 50mm.</td>
                        <td>Garry Connelly</td>
                        <td><span class="status-pill status-dsq">DSQ PUBLISHED</span></td>
                        <td>
                            <a href="export_document.php?doc_type=decision&id=1" target="_blank" class="btn-sound-pill" style="text-decoration:none; display:inline-flex; align-items:center; gap:5px; border-color:var(--f1-cyan); color:var(--f1-cyan); background:rgba(0,210,190,0.1);">
                                <span>📄</span> PRINT DOC
                            </a>
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

// 2. Penalty Gavel & Critical Warning Sound
function playPenaltySubmitSound() {
    try {
        // Heavy low-frequency steward gavel strike followed by alert chord
        playTone(180, 0.25, 'triangle', 0.3);
        setTimeout(() => playTone(120, 0.35, 'sine', 0.3), 80);
        setTimeout(() => playTone(880, 0.15, 'sawtooth', 0.2), 220);
        setTimeout(() => playTone(660, 0.2, 'sawtooth', 0.2), 340);
    } catch(e) {}
}

// 3. Ratification & Official Gazette Success Tone
function playRatifySubmitSound() {
    try {
        playTone(440, 0.1, 'sine', 0.15);
        setTimeout(() => playTone(554, 0.12, 'sine', 0.18), 90);
        setTimeout(() => playTone(659, 0.15, 'sine', 0.18), 180);
        setTimeout(() => playTone(880, 0.25, 'sine', 0.2), 280);
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
    if (action === 'penalty') {
        setTimeout(playPenaltySubmitSound, 300);
    } else if (action === 'signoff') {
        setTimeout(playRatifySubmitSound, 300);
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
        margin-bottom: 3px;
    }
    .status-pill.status-dsq {
        background: rgba(255, 24, 1, 0.2);
        color: #ff1801;
        border: 1px solid #ff1801;
        font-weight: bold;
        font-family: 'Share Tech Mono', monospace;
        font-size: 10px;
        padding: 4px 8px;
        border-radius: 4px;
        display: inline-block;
    }
    .status-pill.status-appeal {
        background: rgba(241, 196, 15, 0.2);
        color: #f1c40f;
        border: 1px solid #f1c40f;
        font-weight: bold;
        font-family: 'Share Tech Mono', monospace;
        font-size: 10px;
        padding: 4px 8px;
        border-radius: 4px;
        display: inline-block;
    }
    .status-pill.status-rescinded {
        background: rgba(140, 140, 158, 0.2);
        color: #8c8c9e;
        border: 1px solid #8c8c9e;
        font-weight: bold;
        font-family: 'Share Tech Mono', monospace;
        font-size: 10px;
        padding: 4px 8px;
        border-radius: 4px;
        display: inline-block;
    }

    @media (max-width: 900px) {
        .hud-grid-row {
            grid-template-columns: 1fr;
        }
    }
</style>

</body>
</html>
