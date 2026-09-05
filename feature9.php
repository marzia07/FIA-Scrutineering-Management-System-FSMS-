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
check_role_access(['steward', 'team_representative']);

// Auto-seed baseline ER records if PENALTIES, APPEALS, or APPEAL_EVIDENCE are empty
$chk_p = $conn->query("SELECT COUNT(*) as cnt FROM PENALTIES");
if ($chk_p && $chk_p->fetch_assoc()['cnt'] == 0) {
    // Ensure violations exist
    $chk_v = $conn->query("SELECT violation_id FROM VIOLATIONS");
    $v_ids = [];
    if ($chk_v && $chk_v->num_rows > 0) {
        while ($vr = $chk_v->fetch_assoc()) $v_ids[] = $vr['violation_id'];
    }
    if (empty($v_ids)) {
        $conn->query("INSERT INTO VIOLATIONS (measurement_id, violation_description, severity, status, detected_by) VALUES (1, 'Front Wing Deflection failed 50mm tolerance load test.', 'Major', 'rectified', 1)");
        $v_ids[] = $conn->insert_id;
    }

    $steward_uid = 1;
    foreach ($v_ids as $vid) {
        $conn->query("INSERT INTO PENALTIES (violation_id, steward_id, penalty_type, penalty_value, decision_date, comments, status) VALUES 
        ($vid, $steward_uid, 'Disqualification from Qualifying (DSQ)', 'Pit Lane Start', NOW(), 'Aerodynamic deflection test non-compliant under Article 3.5.1.', 'confirmed')");
    }
}

$chk_a = $conn->query("SELECT COUNT(*) as cnt FROM APPEALS");
if ($chk_a && $chk_a->fetch_assoc()['cnt'] == 0) {
    $p_first = $conn->query("SELECT penalty_id FROM PENALTIES LIMIT 1")->fetch_assoc()['penalty_id'] ?? 1;
    $conn->query("INSERT INTO APPEALS (penalty_id, appeal_reason, status, submitted_at, decision_by, decision_at, decision_summary) VALUES 
    ($p_first, 'Team telemetry demonstrates kerb strike on Turn 14 caused localized mounting fracture, not intentional aerodynamic non-compliance.', 'Under Review', NOW(), NULL, NULL, NULL)");
}

$chk_e = $conn->query("SELECT COUNT(*) as cnt FROM APPEAL_EVIDENCE");
if ($chk_e && $chk_e->fetch_assoc()['cnt'] == 0) {
    $a_first = $conn->query("SELECT appeal_id FROM APPEALS LIMIT 1")->fetch_assoc()['appeal_id'] ?? 1;
    $u_uid = $_SESSION['user_id'] ?? 1;
    $conn->query("INSERT INTO APPEAL_EVIDENCE (appeal_id, file_name, file_url, file_type, uploaded_by, description, uploaded_at) VALUES 
    ($a_first, 'FIA_T14_Kerb_Telemetry_Trace.pdf', 'https://fia.com/telemetry/RB20_T14_strain_gauge.pdf', 'PDF Technical Report', $u_uid, 'High-speed strain gauge telemetry data demonstrating transient kerb impact force exceeding 25kN at apex.', NOW())");
}

$message = '';
$action_type = '';

// 1. Form 1: Lodge Official Appeal Handler (Team Rep, Steward, Admin)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'lodge_appeal') {
    check_role_access(['team_representative', 'steward']);
    $penalty_id = intval($_POST['penalty_id'] ?? 0);
    $appeal_reason = trim($_POST['appeal_reason'] ?? '');

    if ($penalty_id > 0 && !empty($appeal_reason)) {
        $stmt = $conn->prepare("INSERT INTO APPEALS (penalty_id, appeal_reason, status, submitted_at) VALUES (?, ?, 'Under Review', NOW())");
        if ($stmt) {
            $stmt->bind_param("is", $penalty_id, $appeal_reason);
            $stmt->execute();

            $message = "Official appeal successfully lodged and queued for Steward Panel review.";
            $action_type = "appeal";
        }
    }
}

// 2. Form 2: Steward Ruling & Evidence Upload Handler (Steward & Admin strictly)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'ruling_evidence') {
    check_role_access(['steward']);
    $appeal_id = intval($_POST['appeal_id'] ?? 0);
    $status = trim($_POST['status'] ?? 'Upheld');
    $decision_summary = trim($_POST['decision_summary'] ?? '');
    $decision_by = intval($_POST['decision_by'] ?? ($_SESSION['user_id'] ?? 1));

    $file_name = trim($_POST['file_name'] ?? '');
    $file_url = trim($_POST['file_url'] ?? '');
    $file_type = trim($_POST['file_type'] ?? 'PDF Technical Report');
    $evidence_desc = trim($_POST['evidence_desc'] ?? '');

    if ($appeal_id > 0) {
        // Update Appeal Ruling
        $stmt = $conn->prepare("UPDATE APPEALS SET status = ?, decision_summary = ?, decision_by = ?, decision_at = NOW() WHERE appeal_id = ?");
        if ($stmt) {
            $stmt->bind_param("ssii", $status, $decision_summary, $decision_by, $appeal_id);
            $stmt->execute();
        }

        // Insert Evidence Dossier if provided
        if (!empty($file_name)) {
            if (empty($file_url)) $file_url = "#dossier-" . time();
            $stmt_ev = $conn->prepare("INSERT INTO APPEAL_EVIDENCE (appeal_id, file_name, file_url, file_type, uploaded_by, description, uploaded_at) VALUES (?, ?, ?, ?, ?, ?, NOW())");
            if ($stmt_ev) {
                $stmt_ev->bind_param("isssis", $appeal_id, $file_name, $file_url, $file_type, $decision_by, $evidence_desc);
                $stmt_ev->execute();
            }
        }

        $message = "Steward panel verdict officialized and evidence dossier attached.";
        $action_type = "ruling";
    }
}

// 3. Compute Top HUD Metrics
$total_appeals = 0;
$active_reviews = 0;
$overturned_count = 0;
$upheld_count = 0;
$total_evidence = 0;

$m_apl = $conn->query("SELECT COUNT(*) as total, 
                              SUM(CASE WHEN LOWER(status) IN ('under review', 'submitted') THEN 1 ELSE 0 END) as active_cnt,
                              SUM(CASE WHEN LOWER(status) = 'overturned' THEN 1 ELSE 0 END) as overturned_cnt,
                              SUM(CASE WHEN LOWER(status) = 'upheld' THEN 1 ELSE 0 END) as upheld_cnt
                       FROM APPEALS");
if ($m_apl && $a_row = $m_apl->fetch_assoc()) {
    $total_appeals = intval($a_row['total'] ?? 0);
    $active_reviews = intval($a_row['active_cnt'] ?? 0);
    $overturned_count = intval($a_row['overturned_cnt'] ?? 0);
    $upheld_count = intval($a_row['upheld_cnt'] ?? 0);
}

$m_ev = $conn->query("SELECT COUNT(*) as total FROM APPEAL_EVIDENCE");
if ($m_ev && $e_row = $m_ev->fetch_assoc()) {
    $total_evidence = intval($e_row['total'] ?? 0);
}

// 4. Query Data for Dropdowns
// Active Penalties dropdown (for Form 1)
$penalties_query = $conn->query("SELECT p.penalty_id, p.penalty_type, p.penalty_value, p.comments,
                                        v.violation_description,
                                        c.car_name, c.chassis_number, t.team_name, d.full_name as driver_name
                                 FROM PENALTIES p
                                 LEFT JOIN VIOLATIONS v ON p.violation_id = v.violation_id
                                 LEFT JOIN INSPECTION_MEASUREMENTS m ON v.measurement_id = m.measurement_id
                                 LEFT JOIN INSPECTION_SESSIONS s ON m.session_id = s.session_id
                                 LEFT JOIN CARS c ON s.car_id = c.car_id
                                 LEFT JOIN TEAMS t ON c.team_id = t.team_id
                                 LEFT JOIN DRIVERS d ON c.driver_id = d.driver_id
                                 ORDER BY p.penalty_id DESC");
$penalties_list = [];
if ($penalties_query) {
    while ($r = $penalties_query->fetch_assoc()) $penalties_list[] = $r;
}

// Open Appeals dropdown (for Form 2)
$appeals_dropdown = $conn->query("SELECT a.appeal_id, a.appeal_reason, a.status,
                                         p.penalty_type, c.car_name, d.full_name as driver_name
                                  FROM APPEALS a
                                  LEFT JOIN PENALTIES p ON a.penalty_id = p.penalty_id
                                  LEFT JOIN VIOLATIONS v ON p.violation_id = v.violation_id
                                  LEFT JOIN INSPECTION_MEASUREMENTS m ON v.measurement_id = m.measurement_id
                                  LEFT JOIN INSPECTION_SESSIONS s ON m.session_id = s.session_id
                                  LEFT JOIN CARS c ON s.car_id = c.car_id
                                  LEFT JOIN DRIVERS d ON c.driver_id = d.driver_id
                                  ORDER BY a.appeal_id DESC");
$open_appeals_list = [];
if ($appeals_dropdown) {
    while ($r = $appeals_dropdown->fetch_assoc()) $open_appeals_list[] = $r;
}

// Stewards/Users dropdown
$users_query = $conn->query("SELECT user_id, full_name, email FROM USERS ORDER BY user_id ASC");
$users_list = [];
if ($users_query) {
    while ($u = $users_query->fetch_assoc()) $users_list[] = $u;
}

// 5. Query Master Table (JOINing APPEALS, PENALTIES, VIOLATIONS, CARS, USERS, and aggregated EVIDENCE)
$master_appeals = $conn->query("SELECT a.appeal_id, a.appeal_reason, a.status as appeal_status, a.submitted_at, a.decision_at, a.decision_summary,
                                       p.penalty_id, p.penalty_type, p.penalty_value, p.comments as penalty_comments,
                                       v.violation_description,
                                       c.car_name, c.chassis_number, t.team_name, d.full_name as driver_name,
                                       u.full_name as steward_name
                                FROM APPEALS a
                                LEFT JOIN PENALTIES p ON a.penalty_id = p.penalty_id
                                LEFT JOIN VIOLATIONS v ON p.violation_id = v.violation_id
                                LEFT JOIN INSPECTION_MEASUREMENTS m ON v.measurement_id = m.measurement_id
                                LEFT JOIN INSPECTION_SESSIONS s ON m.session_id = s.session_id
                                LEFT JOIN CARS c ON s.car_id = c.car_id
                                LEFT JOIN TEAMS t ON c.team_id = t.team_id
                                LEFT JOIN DRIVERS d ON c.driver_id = d.driver_id
                                LEFT JOIN USERS u ON a.decision_by = u.user_id
                                ORDER BY a.appeal_id DESC");
$data_deck = [];
if ($master_appeals) {
    while ($row = $master_appeals->fetch_assoc()) {
        $aid = $row['appeal_id'];
        // Fetch attached evidence files for this appeal
        $ev_res = $conn->query("SELECT * FROM APPEAL_EVIDENCE WHERE appeal_id = $aid ORDER BY evidence_id ASC");
        $ev_list = [];
        if ($ev_res) {
            while ($ev = $ev_res->fetch_assoc()) $ev_list[] = $ev;
        }
        $row['evidence_files'] = $ev_list;
        $data_deck[] = $row;
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
    <title>FIA FSMS - Feature 9: Steward Appeals & Evidence Dossier</title>
    <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@500;700;900&family=Share+Tech+Mono&family=Titillium+Web:wght@400;600;700&display=swap" rel="stylesheet">
</head>
<body>

<!-- Ambient CRT Scanlines & Glow Backdrop -->
<div class="crt-scanline"></div>
<div class="ambient-glow glow-red"></div>
<div class="ambient-glow glow-cyan"></div>

<div class="main-content">
    <!-- Top Telemetry Header -->
    <div class="telemetry-top-bar">
        <div class="hud-status-live">
            <span class="live-dot">●</span> FIA COURT OF APPEAL & EVIDENCE DOSSIER // LIVE STREAM
        </div>
        <div class="hud-top-actions">
            <button type="button" class="btn-sound-pill" onclick="playRadioSquelchSound()" title="Broadcast Radio Comm">
                📢 RADIO SOUND
            </button>
            <div class="hud-protocol-tag">
                FIA ER APPEALS & APPEAL_EVIDENCE SCHEMA
            </div>
        </div>
    </div>

    <?php if ($message): ?>
        <div class="hud-alert" id="hudAlertBox">
            <span class="alert-icon">⚖️</span> <?php echo htmlspecialchars($message); ?>
        </div>
    <?php endif; ?>

    <!-- Top 4 HUD Metrics -->
    <div class="hud-metrics-grid">
        <div class="hud-stat-card theme-amber">
            <div class="stat-label">
                <span>TOTAL APPEALS FILED</span>
                <span>📑</span>
            </div>
            <div class="stat-value" style="color: #ffb703;"><?php echo $total_appeals; ?></div>
            <div class="stat-foot">Protests lodged by constructors</div>
        </div>

        <div class="hud-stat-card theme-red">
            <div class="stat-label">
                <span>ACTIVE UNDER REVIEW</span>
                <span>⏳</span>
            </div>
            <div class="stat-value" style="color: #ff1801;"><?php echo $active_reviews; ?></div>
            <div class="stat-foot">Awaiting steward adjudication</div>
        </div>

        <div class="hud-stat-card theme-green">
            <div class="stat-label">
                <span>OVERTURNED / UPHELD</span>
                <span>⚖️</span>
            </div>
            <div class="stat-value" style="color: #2ecc71;">
                <?php echo $overturned_count; ?> <span style="font-size: 16px; color: #8c8c9e;">:</span> <?php echo $upheld_count; ?>
            </div>
            <div class="stat-foot"><?php echo $overturned_count; ?> Overturned / <?php echo $upheld_count; ?> Upheld</div>
        </div>

        <div class="hud-stat-card theme-cyan">
            <div class="stat-label">
                <span>EVIDENCE DOSSIER FILES</span>
                <span>📂</span>
            </div>
            <div class="stat-value" style="color: #00d2be;"><?php echo $total_evidence; ?></div>
            <div class="stat-foot">Telemetry traces & scans attached</div>
        </div>
    </div>

    <!-- Dual Command Deck -->
    <div class="hud-grid-row">
        <!-- Form 1: Lodge Official Appeal -->
        <div class="hud-card theme-red">
            <div class="hud-card-header">
                <h3><span class="card-num">01.</span> LODGE OFFICIAL STEWARD APPEAL</h3>
                <span class="hud-badge-tag">APPEAL-9A</span>
            </div>
            <form method="POST" action="feature9.php" onsubmit="playAppealLodgeSound()">
                <input type="hidden" name="action" value="lodge_appeal">

                <div class="form-group">
                    <label>SELECT ACTIVE PENALTY (FROM PENALTIES TABLE)</label>
                    <select name="penalty_id" required>
                        <option value="">-- Select Sanction to Appeal --</option>
                        <?php foreach ($penalties_list as $p): ?>
                            <option value="<?php echo $p['penalty_id']; ?>">
                                #PEN-<?php echo $p['penalty_id']; ?> - <?php echo htmlspecialchars($p['car_name'] ?: 'Competitor'); ?> (<?php echo htmlspecialchars($p['penalty_type']); ?>: <?php echo htmlspecialchars($p['penalty_value']); ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label>LEGAL APPEAL REASON & TECHNICAL MITIGATION</label>
                    <textarea name="appeal_reason" rows="4" placeholder="Detail the grounds of protest, telemetry data anomalies, force majeure, or regulatory misinterpretation..." required>Team telemetry demonstrates kerb strike on Turn 14 caused localized mounting fracture, not intentional aerodynamic non-compliance.</textarea>
                </div>

                <div class="form-group">
                    <label>SUBMISSION TIMESTAMP</label>
                    <input type="text" value="<?php echo date('d/m/Y, H:i'); ?>" readonly>
                </div>

                <button type="submit" class="btn-hud btn-red">LODGE OFFICIAL APPEAL PROTEST</button>
            </form>
        </div>

        <!-- Form 2: Steward Ruling & Evidence Upload -->
        <div class="hud-card theme-cyan">
            <div class="hud-card-header">
                <h3><span class="card-num">02.</span> STEWARD RULING & EVIDENCE DOSSIER</h3>
                <span class="hud-badge-tag">RULING-9B</span>
            </div>
            <form method="POST" action="feature9.php" onsubmit="playRulingVerdictSound()">
                <input type="hidden" name="action" value="ruling_evidence">

                <div class="form-group">
                    <label>SELECT OPEN APPEAL (FROM APPEALS TABLE)</label>
                    <select name="appeal_id" required>
                        <option value="">-- Select Appeal Incident --</option>
                        <?php foreach ($open_appeals_list as $apl): ?>
                            <option value="<?php echo $apl['appeal_id']; ?>">
                                #APL-<?php echo $apl['appeal_id']; ?> - <?php echo htmlspecialchars($apl['car_name'] ?: 'Competitor'); ?> - [Status: <?php echo strtoupper($apl['status']); ?>]
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-row-2">
                    <div class="form-group">
                        <label>EVIDENCE FILE NAME</label>
                        <input type="text" name="file_name" value="FIA_T14_Kerb_Telemetry_Trace.pdf" placeholder="e.g. Telemetry_Trace_T4.pdf">
                    </div>
                    <div class="form-group">
                        <label>EVIDENCE FILE TYPE</label>
                        <select name="file_type">
                            <option value="PDF Technical Report" selected>PDF Technical Report</option>
                            <option value="Telemetry Trace (CSV/DAT)">Telemetry Trace (CSV/DAT)</option>
                            <option value="Onboard Video Footage (MP4)">Onboard Video Footage (MP4)</option>
                            <option value="Photogrammetry Laser Scan">Photogrammetry Laser Scan</option>
                        </select>
                    </div>
                </div>

                <div class="form-group">
                    <label>EVIDENCE DOCUMENT / REPO URL</label>
                    <input type="text" name="file_url" value="https://fia.com/telemetry/RB20_T14_strain_gauge.pdf" placeholder="e.g. https://fia.com/evidence/doc.pdf">
                </div>

                <div class="form-group">
                    <label>EVIDENCE DESCRIPTION & TELEMETRY NOTES</label>
                    <input type="text" name="evidence_desc" value="High-speed strain gauge telemetry data demonstrating transient kerb impact force exceeding 25kN." placeholder="Brief description of findings...">
                </div>

                <div class="form-row-2">
                    <div class="form-group">
                        <label>PANEL VERDICT</label>
                        <select name="status">
                            <option value="Upheld">Upheld (Penalty Stands)</option>
                            <option value="Overturned" selected>Overturned (Penalty Rescinded)</option>
                            <option value="Reduced">Reduced (Sanction Mitigated)</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>PRESIDING STEWARD</label>
                        <select name="decision_by">
                            <?php foreach ($users_list as $u): ?>
                                <option value="<?php echo $u['user_id']; ?>" <?php echo ($u['user_id'] == 2) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($u['full_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="form-group">
                    <label>OFFICIAL DECISION SUMMARY</label>
                    <textarea name="decision_summary" rows="2" placeholder="State formal steward rationale for ratifying, overturning, or mitigating the penalty..." required>Upon review of high-rate load sensor telemetry and high-speed kerb footage, the panel confirms deflection was transient impact damage. Penalty rescinded.</textarea>
                </div>

                <button type="submit" class="btn-hud btn-cyan">RATIFY RULING & ATTACH DOSSIER</button>
            </form>
        </div>
    </div>

    <!-- Data Deck: Appeals & Evidence Register -->
    <div class="hud-table-wrapper">
        <div class="table-header-bar">
            <span>❖ STEWARD APPEALS & EVIDENCE DOSSIER REGISTER</span>
            <span class="count-tag">ER JOIN: APPEALS ⨝ APPEAL_EVIDENCE ⨝ PENALTIES (<?php echo count($data_deck); ?> CASES)</span>
        </div>
        <table class="hud-table">
            <thead>
                <tr>
                    <th>APPEAL ID</th>
                    <th>COMPETITOR</th>
                    <th>ORIGINAL SANCTION</th>
                    <th>APPEAL GROUNDS & RATIONALE</th>
                    <th>STEWARD RULING & SUMMARY</th>
                    <th>EVIDENCE DOSSIER</th>
                    <th>STATUS</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($data_deck)): ?>
                    <?php foreach ($data_deck as $row): 
                        $stat = strtoupper($row['appeal_status'] ?? 'UNDER REVIEW');
                        $pill_class = 'status-pending';

                        if ($stat === 'OVERTURNED') {
                            $pill_class = 'status-passed';
                        } elseif ($stat === 'UPHELD') {
                            $pill_class = 'status-failed';
                        } elseif ($stat === 'REDUCED') {
                            $pill_class = 'status-reduced';
                        }
                    ?>
                        <tr>
                            <td>
                                <strong style="color: #00d2be; font-family: 'Share Tech Mono', monospace; font-size: 13px;">#APL-<?php echo $row['appeal_id']; ?></strong><br>
                                <small style="color: #8c8c9e; font-size: 10px;"><?php echo htmlspecialchars($row['submitted_at']); ?></small>
                            </td>
                            <td>
                                <strong style="color: #ffffff;"><?php echo htmlspecialchars($row['car_name'] ?: 'Competitor'); ?></strong><br>
                                <span class="serial-pill"><?php echo htmlspecialchars($row['chassis_number'] ?: 'CHASSIS-SPEC'); ?></span>
                                <?php if (!empty($row['driver_name'])): ?>
                                    <br><small style="color: #8c8c9e; font-size: 10px;"><?php echo htmlspecialchars($row['driver_name']); ?></small>
                                <?php endif; ?>
                            </td>
                            <td>
                                <strong style="color: #ff1801; font-family: 'Orbitron', sans-serif; font-size: 11px;">
                                    <?php echo htmlspecialchars($row['penalty_type']); ?>
                                </strong>
                                <?php if (!empty($row['penalty_value'])): ?>
                                    <br><small style="color: #ffb703; font-family: 'Share Tech Mono', monospace; font-size: 10px;"><?php echo htmlspecialchars($row['penalty_value']); ?></small>
                                <?php endif; ?>
                            </td>
                            <td style="max-width: 220px; font-size: 11px; color: #dcdce6; line-height: 1.4;">
                                <?php echo htmlspecialchars($row['appeal_reason']); ?>
                            </td>
                            <td style="max-width: 240px; font-size: 11px; line-height: 1.4;">
                                <?php if (!empty($row['decision_summary'])): ?>
                                    <span style="color: #ffffff;"><?php echo htmlspecialchars($row['decision_summary']); ?></span>
                                    <?php if (!empty($row['steward_name'])): ?>
                                        <br><small style="color: #00d2be; font-family: 'Share Tech Mono', monospace; font-size: 10px;">Steward: <?php echo htmlspecialchars($row['steward_name']); ?> [<?php echo htmlspecialchars($row['decision_at']); ?>]</small>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <span style="color: #8c8c9e; font-style: italic;">Hearing scheduled / In deliberation</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if (!empty($row['evidence_files'])): ?>
                                    <?php foreach ($row['evidence_files'] as $ev): ?>
                                        <div class="evidence-chip" title="<?php echo htmlspecialchars($ev['description'] ?? ''); ?>">
                                            <span>📄</span> 
                                            <a href="<?php echo htmlspecialchars($ev['file_url']); ?>" target="_blank" style="color: #00d2be; text-decoration: none;">
                                                <?php echo htmlspecialchars($ev['file_name']); ?>
                                            </a>
                                            <small style="color: #8c8c9e; font-size: 9px; display: block;">(<?php echo htmlspecialchars($ev['file_type']); ?>)</small>
                                        </div>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <small style="color: #717188; font-style: italic;">No evidence attached</small>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="status-pill <?php echo $pill_class; ?>">
                                    <?php echo htmlspecialchars($stat); ?>
                                </span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="7" style="text-align: center; color: #8c8c9e; padding: 20px;">
                            No steward appeals lodged. Use Form 01 above to lodge a legal protest against an active sanction.
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

// 2. Appeal Lodge Sound (Gavel Strike + Siren Sweep)
function playAppealLodgeSound() {
    try {
        playTone(160, 0.25, 'triangle', 0.3);
        setTimeout(() => playTone(110, 0.35, 'sine', 0.3), 80);
        setTimeout(() => playTone(780, 0.15, 'sawtooth', 0.2), 220);
        setTimeout(() => playTone(540, 0.25, 'sawtooth', 0.2), 340);
    } catch(e) {}
}

// 3. Ruling Verdict Fanfare (Confirmation Chord)
function playRulingVerdictSound() {
    try {
        playTone(440, 0.1, 'sine', 0.15); // A4
        setTimeout(() => playTone(554, 0.12, 'sine', 0.18), 80); // C#5
        setTimeout(() => playTone(659, 0.15, 'sine', 0.18), 160); // E5
        setTimeout(() => playTone(880, 0.25, 'sine', 0.22), 240); // A5
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
    if (action === 'appeal') {
        setTimeout(playAppealLodgeSound, 300);
    } else if (action === 'ruling') {
        setTimeout(playRulingVerdictSound, 300);
    }
});
</script>

<style>
    :root {
        --f1-red: #ff1801;
        --f1-cyan: #00d2be;
        --f1-amber: #ffb703;
        --f1-green: #2ecc71;
        --f1-purple: #a855f7;
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

    /* Ambient CRT scanlines and glowing halos */
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

    /* Dual Command Deck */
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

    /* Data Deck Table */
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
    .evidence-chip {
        background: rgba(255, 255, 255, 0.04);
        border: 1px solid rgba(0, 210, 190, 0.3);
        padding: 4px 8px;
        border-radius: 4px;
        margin-bottom: 4px;
        font-family: 'Share Tech Mono', monospace;
        font-size: 10px;
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
    .status-reduced { background: rgba(168, 85, 247, 0.15); color: var(--f1-purple); border: 1px solid var(--f1-purple); }

    @media (max-width: 900px) {
        .hud-metrics-grid { grid-template-columns: 1fr 1fr; }
        .hud-grid-row { grid-template-columns: 1fr; }
    }
</style>

</body>
</html>
