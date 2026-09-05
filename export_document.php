<?php
// FIA Official Document & Decision Bulletin Print / PDF Generator
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}
require_once 'db.php';

$doc_type = $_GET['doc_type'] ?? 'decision'; // 'decision', 'bulletin', 'scrutineering'
$item_id = intval($_GET['id'] ?? 1);

$gp_name = "2026 FORMULA 1 MONACO GRAND PRIX";
$session_name = "Race";
$doc_no = "Doc " . str_pad($item_id + 32, 2, '0', STR_PAD_LEFT);
$date_str = date('d F Y');
$time_str = date('H:i') . " UTC";

// Default preset data
$driver_name = "Charles Leclerc";
$car_no = "16";
$team_name = "Scuderia Ferrari";
$chassis_no = "CHASSIS-SF24-02";
$offence = "Breach of Article 2 (c) Chapter IV Appendix L of the FIA International Sporting Code (Causing a collision at Turn 4).";
$fact = "Car 16 made contact with Car 55 at the entry of Turn 4 resulting in Car 55 spinning off track.";
$decision = "10-Second Time Penalty imposed. 2 Penalty Points allocated on FIA Superlicense (Total: 4 points in 12-month period).";
$reason = "The Stewards reviewed video telemetry, multiple onboard camera angles, and GPS tracking. Car 16 was judged wholly to blame for the incident as it attempted an overtake on the inside without achieving significant overlap prior to apex.";
$stewards = [
    "Garry Connelly (FIA Steward)",
    "Mathieu Remmerie (FIA Steward)",
    "Vitantonio Liuzzi (Driver Steward)",
    "Matteo Perini (National Steward)"
];

// Query from PENALTIES / VIOLATIONS if exists
if ($doc_type === 'decision') {
    // Try relational PENALTIES
    $stmt = $conn->prepare("SELECT p.*, v.violation_description, c.car_name, c.chassis_number, t.team_name, d.full_name as driver_name, d.license_number 
                            FROM PENALTIES p 
                            LEFT JOIN VIOLATIONS v ON p.violation_id = v.violation_id 
                            LEFT JOIN INSPECTION_MEASUREMENTS m ON v.measurement_id = m.measurement_id 
                            LEFT JOIN INSPECTION_SESSIONS s ON m.session_id = s.session_id 
                            LEFT JOIN CARS c ON s.car_id = c.car_id 
                            LEFT JOIN TEAMS t ON c.team_id = t.team_id 
                            LEFT JOIN DRIVERS d ON c.driver_id = d.driver_id 
                            WHERE p.penalty_id = ? LIMIT 1");
    if ($stmt) {
        $stmt->bind_param("i", $item_id);
        $stmt->execute();
        $res = $stmt->get_result();
        if ($res && $row = $res->fetch_assoc()) {
            if (!empty($row['driver_name'])) $driver_name = $row['driver_name'];
            if (!empty($row['team_name'])) $team_name = $row['team_name'];
            if (!empty($row['chassis_number'])) $chassis_no = $row['chassis_number'];
            if (!empty($row['violation_description'])) $fact = $row['violation_description'];
            if (!empty($row['penalty_type'])) $decision = $row['penalty_type'] . " - " . ($row['description'] ?? '');
            if (!empty($row['description'])) $offence = $row['description'];
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>FIA Document <?php echo htmlspecialchars($doc_no); ?> - Official Bulletin</title>
    <link href="https://fonts.googleapis.com/css2?family=Share+Tech+Mono&family=Titillium+Web:wght@400;600;700;900&display=swap" rel="stylesheet">
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            background-color: #0b0b14;
            color: #1a1a24;
            font-family: 'Titillium Web', sans-serif;
            padding: 30px 20px;
            display: flex;
            flex-direction: column;
            align-items: center;
            min-height: 100vh;
        }

        /* Top Action Bar (hidden on print) */
        .doc-action-bar {
            width: 100%;
            max-width: 820px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 24px;
        }
        .btn-action {
            background: #1e1e2d;
            border: 1px solid rgba(255, 255, 255, 0.15);
            color: #ffffff;
            font-family: 'Titillium Web', sans-serif;
            font-size: 13px;
            font-weight: 700;
            padding: 9px 18px;
            border-radius: 6px;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            cursor: pointer;
            transition: all 0.2s ease;
        }
        .btn-action:hover {
            background: #2a2a3e;
            border-color: #00d2be;
            color: #00d2be;
        }
        .btn-print {
            background: linear-gradient(90deg, #ff1801, #d63031);
            border: none;
            box-shadow: 0 0 15px rgba(255, 24, 1, 0.4);
        }
        .btn-print:hover {
            color: #ffffff;
            box-shadow: 0 0 25px rgba(255, 24, 1, 0.7);
            transform: translateY(-1px);
        }

        /* Printable Document Sheet */
        .fia-sheet {
            background: #ffffff;
            width: 100%;
            max-width: 820px;
            padding: 50px 60px;
            box-shadow: 0 15px 40px rgba(0, 0, 0, 0.7);
            border-radius: 4px;
            position: relative;
            color: #111118;
            font-size: 13.5px;
            line-height: 1.5;
        }

        /* FIA Header */
        .fia-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            border-bottom: 3px solid #000000;
            padding-bottom: 16px;
            margin-bottom: 24px;
        }
        .fia-logo-badge {
            display: flex;
            align-items: center;
            gap: 14px;
        }
        .fia-emblem {
            font-size: 38px;
            font-weight: 900;
            background: #000;
            color: #fff;
            padding: 4px 12px;
            border-radius: 4px;
            letter-spacing: -2px;
            font-family: 'Share Tech Mono', monospace;
        }
        .fia-titles {
            display: flex;
            flex-direction: column;
        }
        .fia-title-main {
            font-size: 18px;
            font-weight: 900;
            letter-spacing: 1px;
            text-transform: uppercase;
        }
        .fia-title-sub {
            font-size: 11px;
            color: #555;
            font-weight: 700;
            letter-spacing: 0.5px;
        }
        .fia-doc-meta {
            text-align: right;
            font-family: 'Share Tech Mono', monospace;
            font-size: 12px;
        }
        .doc-number {
            font-size: 18px;
            font-weight: 900;
            color: #d63031;
        }

        /* GP Banner */
        .gp-banner {
            background: #f1f2f6;
            border-left: 5px solid #d63031;
            padding: 10px 16px;
            margin-bottom: 24px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .gp-title {
            font-weight: 900;
            font-size: 15px;
            letter-spacing: 0.5px;
        }
        .gp-date {
            font-family: 'Share Tech Mono', monospace;
            font-size: 12px;
            color: #555;
        }

        /* Field Grid */
        .doc-grid {
            display: grid;
            grid-template-columns: 140px 1fr;
            row-gap: 12px;
            margin-bottom: 28px;
            border-bottom: 1px solid #e1e2e6;
            padding-bottom: 20px;
        }
        .grid-label {
            font-weight: 900;
            color: #444;
            text-transform: uppercase;
            font-size: 12px;
        }
        .grid-value {
            color: #000;
            font-weight: 600;
        }

        /* Section Blocks */
        .section-block {
            margin-bottom: 22px;
        }
        .section-heading {
            font-size: 12px;
            font-weight: 900;
            text-transform: uppercase;
            color: #d63031;
            letter-spacing: 1px;
            margin-bottom: 6px;
            border-bottom: 1px solid #eee;
            padding-bottom: 4px;
        }
        .section-body {
            font-size: 13.5px;
            line-height: 1.6;
            color: #222;
        }
        .penalty-highlight {
            background: #fff0f0;
            border: 1px solid #ffcccc;
            border-left: 4px solid #d63031;
            padding: 12px 16px;
            font-weight: 700;
            border-radius: 2px;
            margin: 10px 0;
        }

        /* Steward Signatures */
        .signatures-area {
            margin-top: 36px;
            padding-top: 20px;
            border-top: 2px solid #000;
        }
        .stewards-title {
            font-size: 12px;
            font-weight: 900;
            text-transform: uppercase;
            margin-bottom: 20px;
            letter-spacing: 1px;
        }
        .steward-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }
        .steward-sign-box {
            border-top: 1px dashed #888;
            padding-top: 6px;
        }
        .steward-name {
            font-weight: 700;
            font-size: 13px;
        }
        .steward-seal {
            font-family: 'Share Tech Mono', monospace;
            font-size: 10px;
            color: #008f82;
            margin-top: 2px;
        }

        /* Document Footer */
        .doc-footer {
            margin-top: 40px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-top: 1px solid #ddd;
            padding-top: 14px;
            font-family: 'Share Tech Mono', monospace;
            font-size: 10.5px;
            color: #777;
        }
        .official-seal-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background: #f1f2f6;
            padding: 4px 10px;
            border: 1px solid #ccc;
            border-radius: 4px;
            font-weight: 700;
            color: #222;
        }

        /* Print Media Styles */
        @media print {
            body {
                background: #ffffff;
                padding: 0;
            }
            .doc-action-bar {
                display: none !important;
            }
            .fia-sheet {
                box-shadow: none;
                max-width: 100%;
                padding: 30px;
            }
        }
    </style>
</head>
<body>

    <!-- TOP ACTION BAR (Hidden in print/PDF) -->
    <div class="doc-action-bar">
        <a href="feature6.php" class="btn-action">
            <span>←</span> RETURN TO STEWARD DECISIONS
        </a>
        <div style="display: flex; gap: 10px;">
            <button type="button" class="btn-action" onclick="copyDocumentText()">
                <span>📋</span> COPY TEXT
            </button>
            <button type="button" class="btn-action btn-print" onclick="window.print()">
                <span>🖨️</span> PRINT / SAVE AS PDF
            </button>
        </div>
    </div>

    <!-- OFFICIAL FIA DOCUMENT CONTAINER -->
    <main class="fia-sheet">
        <!-- HEADER -->
        <header class="fia-header">
            <div class="fia-logo-badge">
                <div class="fia-emblem">FIA</div>
                <div class="fia-titles">
                    <span class="fia-title-main">Fédération Internationale de l'Automobile</span>
                    <span class="fia-title-sub">FIA FORMULA ONE WORLD CHAMPIONSHIP // RACE STEWARDS</span>
                </div>
            </div>
            <div class="fia-doc-meta">
                <div class="doc-number"><?php echo htmlspecialchars($doc_no); ?></div>
                <div>Date: <?php echo htmlspecialchars($date_str); ?></div>
                <div>Time: <?php echo htmlspecialchars($time_str); ?></div>
            </div>
        </header>

        <!-- GRAND PRIX BANNER -->
        <div class="gp-banner">
            <span class="gp-title"><?php echo htmlspecialchars($gp_name); ?></span>
            <span class="gp-date">ROUND 08 // OFFICIAL BULLETIN</span>
        </div>

        <!-- RECIPIENT & INCIDENT META -->
        <div class="doc-grid">
            <span class="grid-label">From:</span>
            <span class="grid-value">The Stewards</span>

            <span class="grid-label">To:</span>
            <span class="grid-value">The Team Representative, <?php echo htmlspecialchars($team_name); ?></span>

            <span class="grid-label">Document:</span>
            <span class="grid-value"><?php echo htmlspecialchars($doc_no); ?> (Official Decision)</span>

            <span class="grid-label">Competitor:</span>
            <span class="grid-value"><?php echo htmlspecialchars($team_name); ?></span>

            <span class="grid-label">Driver / Car:</span>
            <span class="grid-value"><?php echo htmlspecialchars($driver_name); ?> (Car #<?php echo htmlspecialchars($car_no); ?>) // Chassis: <?php echo htmlspecialchars($chassis_no); ?></span>

            <span class="grid-label">Session:</span>
            <span class="grid-value"><?php echo htmlspecialchars($session_name); ?></span>
        </div>

        <!-- FACT -->
        <section class="section-block">
            <h3 class="section-heading">Fact</h3>
            <div class="section-body">
                <?php echo nl2br(htmlspecialchars($fact)); ?>
            </div>
        </section>

        <!-- OFFENCE -->
        <section class="section-block">
            <h3 class="section-heading">Offence</h3>
            <div class="section-body">
                <?php echo nl2br(htmlspecialchars($offence)); ?>
            </div>
        </section>

        <!-- DECISION -->
        <section class="section-block">
            <h3 class="section-heading">Decision</h3>
            <div class="penalty-highlight">
                <?php echo nl2br(htmlspecialchars($decision)); ?>
            </div>
        </section>

        <!-- REASON -->
        <section class="section-block">
            <h3 class="section-heading">Reason</h3>
            <div class="section-body">
                <?php echo nl2br(htmlspecialchars($reason)); ?>
            </div>
        </section>

        <!-- RIGHT OF APPEAL ADVISORY -->
        <section class="section-block" style="margin-top: 20px;">
            <div class="section-body" style="font-size: 11.5px; color: #666; font-style: italic;">
                Competitors are reminded that they have the right to appeal certain decisions of the Stewards, in accordance with Article 15 of the FIA International Sporting Code and Chapter 4 of the FIA Judicial and Disciplinary Rules, within the applicable time limits.
            </div>
        </section>

        <!-- SIGNATURES -->
        <div class="signatures-area">
            <div class="stewards-title">The Panel of Stewards</div>
            <div class="steward-grid">
                <?php foreach ($stewards as $steward): ?>
                <div class="steward-sign-box">
                    <div class="steward-name"><?php echo htmlspecialchars($steward); ?></div>
                    <div class="steward-seal">DIGITALLY SIGNED // FIA PKI CERT: #<?php echo strtoupper(substr(md5($steward), 0, 8)); ?></div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- FOOTER -->
        <footer class="doc-footer">
            <div class="official-seal-badge">
                <span>🛡️</span> OFFICIAL FIA RATIFIED DOCUMENT
            </div>
            <div>VERIFICATION HASH: <?php echo strtoupper(sha1($doc_no . $driver_name)); ?></div>
            <div>PAGE 1 OF 1</div>
        </footer>
    </main>

    <script>
    function copyDocumentText() {
        const text = `FIA OFFICIAL DECISION ${<?php echo json_encode($doc_no); ?>}\n${<?php echo json_encode($gp_name); ?>}\nCompetitor: ${<?php echo json_encode($team_name); ?>}\nDriver: ${<?php echo json_encode($driver_name); ?>} (#${<?php echo json_encode($car_no); ?>})\n\nFACT: ${<?php echo json_encode($fact); ?>}\n\nDECISION: ${<?php echo json_encode($decision); ?>}\n\nREASON: ${<?php echo json_encode($reason); ?>}`;
        navigator.clipboard.writeText(text).then(() => {
            alert("Official FIA Document text copied to clipboard!");
        });
    }
    </script>
</body>
</html>
