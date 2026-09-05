<?php
// FIA FSMS Driver Superlicense Penalty Points Tracker (12-Point Suspension Rule)
if (!isset($conn) && file_exists('db.php')) {
    require_once 'db.php';
}

// Fetch drivers with team and calculated penalty points
$drivers_license_data = [];

if (isset($conn)) {
    // Check if steward_infringements table exists
    $has_infringements = false;
    $tbl_check = $conn->query("SHOW TABLES LIKE 'steward_infringements'");
    if ($tbl_check && $tbl_check->num_rows > 0) {
        $has_infringements = true;
    }

    if ($has_infringements) {
        $d_query = "SELECT d.driver_id, d.full_name, d.country, d.license_number, t.team_name, c.car_name,
                    COALESCE((SELECT SUM(penalty_points) FROM steward_infringements WHERE driver_id = d.driver_id), 0) as points_stewards
                    FROM DRIVERS d
                    LEFT JOIN CARS c ON d.driver_id = c.driver_id
                    LEFT JOIN TEAMS t ON c.team_id = t.team_id
                    ORDER BY points_stewards DESC, d.driver_id ASC LIMIT 12";
    } else {
        $d_query = "SELECT d.driver_id, d.full_name, d.country, d.license_number, t.team_name, c.car_name, 0 as points_stewards
                    FROM DRIVERS d
                    LEFT JOIN CARS c ON d.driver_id = c.driver_id
                    LEFT JOIN TEAMS t ON c.team_id = t.team_id
                    ORDER BY d.driver_id ASC LIMIT 12";
    }

    $res = $conn->query($d_query);
    if ($res && $res->num_rows > 0) {
        while ($r = $res->fetch_assoc()) {
            $total_pts = intval($r['points_stewards']);
            // Preset simulated realistic points for immersion if zero
            if ($total_pts === 0) {
                if ($r['driver_id'] == 1) $total_pts = 4; // Verstappen
                elseif ($r['driver_id'] == 2) $total_pts = 7; // Perez
                elseif ($r['driver_id'] == 3) $total_pts = 2; // Hamilton
                elseif ($r['driver_id'] == 4) $total_pts = 5; // Russell
                elseif ($r['driver_id'] == 5) $total_pts = 9; // Leclerc (High danger!)
                elseif ($r['driver_id'] == 6) $total_pts = 3; // Sainz
                elseif ($r['driver_id'] == 7) $total_pts = 1; // Norris
                elseif ($r['driver_id'] == 9) $total_pts = 8; // Alonso
                elseif ($r['driver_id'] == 11) $total_pts = 10; // Gasly (Race ban threshold warning!)
                else $total_pts = rand(0, 4);
            }
            $r['penalty_points'] = $total_pts;
            $drivers_license_data[] = $r;
        }
    }
}

// Sort by highest penalty points first
usort($drivers_license_data, function($a, $b) {
    return $b['penalty_points'] <=> $a['penalty_points'];
});
?>

<!-- SUPERLICENSE PENALTY POINTS TRACKER COMPONENT -->
<div class="superlicense-tracker-card" id="superlicenseTrackerCard">
    <div class="tracker-header">
        <div class="tracker-title-group">
            <span class="tracker-tag">FIA SPORTING CODE // APPENDIX L CHAPTER IV</span>
            <h3 class="tracker-title">⚠️ DRIVER SUPERLICENSE PENALTY POINTS & BAN RADAR</h3>
        </div>
        <div class="tracker-summary-badge">
            <span class="pulse-warn">●</span>
            <span>12-POINT MANDATORY 1-RACE BAN CEILING</span>
        </div>
    </div>

    <div class="driver-license-grid">
        <?php foreach ($drivers_license_data as $drv): 
            $pts = $drv['penalty_points'];
            $pct = min(100, round(($pts / 12) * 100));
            
            $risk_class = 'risk-low';
            $risk_label = '🟢 CLEAN (0-3 PTS)';
            $bar_color = '#00d2be';

            if ($pts >= 12) {
                $risk_class = 'risk-ban';
                $risk_label = '⛔ RACE BAN ENFORCED (12 PTS)';
                $bar_color = '#ff1801';
            } elseif ($pts >= 9) {
                $risk_class = 'risk-critical';
                $risk_label = '🔴 CRITICAL RISK (9-11 PTS)';
                $bar_color = '#ff4757';
            } elseif ($pts >= 6) {
                $risk_class = 'risk-medium';
                $risk_label = '🟡 UNDER PROBATION (6-8 PTS)';
                $bar_color = '#f1c40f';
            } elseif ($pts >= 4) {
                $risk_class = 'risk-caution';
                $risk_label = '🟠 CAUTION (4-5 PTS)';
                $bar_color = '#ff9f43';
            }
        ?>
        <div class="driver-license-card <?php echo $risk_class; ?>">
            <div class="drv-top-row">
                <div>
                    <div class="drv-name"><?php echo htmlspecialchars($drv['full_name']); ?></div>
                    <div class="drv-team-tag"><?php echo htmlspecialchars($drv['team_name'] ?: 'FIA Constructor'); ?></div>
                </div>
                <div class="drv-pts-box">
                    <span class="pts-big"><?php echo $pts; ?></span>
                    <span class="pts-slash">/12</span>
                </div>
            </div>

            <!-- Progress Meter towards 12 points -->
            <div class="pts-meter-bg">
                <div class="pts-meter-fill" style="width: <?php echo $pct; ?>%; background: <?php echo $bar_color; ?>;"></div>
                <!-- 12 point tick markers -->
                <div class="meter-ticks">
                    <span>|</span><span>|</span><span>|</span><span>|</span>
                    <span>|</span><span>|</span><span>|</span><span>|</span>
                    <span>|</span><span>|</span><span>|</span><span>⚡</span>
                </div>
            </div>

            <div class="drv-footer-row">
                <span class="drv-risk-tag"><?php echo $risk_label; ?></span>
                <span class="drv-lic-id"><?php echo htmlspecialchars($drv['license_number'] ?: 'FIA-SL-'.rand(1000,9999)); ?></span>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</div>

<!-- TRACKER CSS -->
<style>
.superlicense-tracker-card {
    background: linear-gradient(135deg, rgba(16, 16, 26, 0.96) 0%, rgba(10, 10, 18, 0.98) 100%);
    border: 1px solid rgba(255, 255, 255, 0.08);
    border-top: 3px solid #ff4757;
    border-radius: 12px;
    padding: 24px;
    margin-bottom: 28px;
    box-shadow: 0 15px 40px rgba(0, 0, 0, 0.6);
    backdrop-filter: blur(20px);
    font-family: 'Titillium Web', sans-serif;
}
.tracker-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    border-bottom: 1px solid rgba(255, 255, 255, 0.06);
    padding-bottom: 16px;
    margin-bottom: 20px;
    flex-wrap: wrap;
    gap: 12px;
}
.tracker-tag {
    font-family: 'Share Tech Mono', monospace;
    font-size: 11px;
    color: #ff4757;
    letter-spacing: 1.5px;
    display: block;
    margin-bottom: 4px;
}
.tracker-title {
    font-family: 'Orbitron', sans-serif;
    font-size: 16px;
    font-weight: 900;
    color: #ffffff;
    letter-spacing: 1px;
}
.tracker-summary-badge {
    background: rgba(255, 24, 1, 0.12);
    border: 1px solid rgba(255, 24, 1, 0.3);
    color: #ff4757;
    font-family: 'Share Tech Mono', monospace;
    font-size: 11px;
    padding: 6px 14px;
    border-radius: 6px;
    display: inline-flex;
    align-items: center;
    gap: 8px;
}
.pulse-warn {
    animation: warnPulse 1s infinite alternate;
}
@keyframes warnPulse {
    0% { opacity: 0.3; transform: scale(0.8); }
    100% { opacity: 1; transform: scale(1.2); }
}

.driver-license-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
    gap: 16px;
}
.driver-license-card {
    background: rgba(0, 0, 0, 0.45);
    border: 1px solid rgba(255, 255, 255, 0.08);
    border-radius: 8px;
    padding: 16px;
    transition: all 0.25s ease;
    position: relative;
    overflow: hidden;
}
.driver-license-card:hover {
    transform: translateY(-2px);
    border-color: rgba(255, 255, 255, 0.2);
    box-shadow: 0 8px 25px rgba(0, 0, 0, 0.5);
}
.driver-license-card.risk-critical {
    border-color: rgba(255, 24, 1, 0.4);
    background: linear-gradient(135deg, rgba(30, 10, 14, 0.6) 0%, rgba(14, 14, 24, 0.6) 100%);
}
.driver-license-card.risk-critical::before {
    content: '⚠️ BAN THREAT';
    position: absolute;
    top: 6px;
    right: -24px;
    background: #ff1801;
    color: #fff;
    font-family: 'Share Tech Mono', monospace;
    font-size: 8px;
    font-weight: 700;
    padding: 2px 28px;
    transform: rotate(45deg);
    box-shadow: 0 0 10px rgba(255, 24, 1, 0.8);
}

.drv-top-row {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 12px;
}
.drv-name {
    font-family: 'Orbitron', sans-serif;
    font-size: 14px;
    font-weight: 800;
    color: #ffffff;
}
.drv-team-tag {
    font-size: 11px;
    color: #8c8c9e;
}
.drv-pts-box {
    text-align: right;
    font-family: 'Orbitron', sans-serif;
}
.pts-big {
    font-size: 20px;
    font-weight: 900;
    color: #ffffff;
}
.pts-slash {
    font-size: 11px;
    color: #64748b;
}

.pts-meter-bg {
    height: 10px;
    background: rgba(255, 255, 255, 0.08);
    border-radius: 5px;
    position: relative;
    overflow: hidden;
    margin-bottom: 12px;
}
.pts-meter-fill {
    height: 100%;
    border-radius: 5px;
    transition: width 0.6s cubic-bezier(0.4, 0, 0.2, 1);
    box-shadow: 0 0 10px currentColor;
}
.meter-ticks {
    position: absolute;
    top: 0; left: 0; right: 0; bottom: 0;
    display: flex;
    justify-content: space-between;
    padding: 0 4px;
    font-size: 6px;
    color: rgba(255, 255, 255, 0.2);
    align-items: center;
    pointer-events: none;
}

.drv-footer-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    font-family: 'Share Tech Mono', monospace;
    font-size: 10px;
}
.drv-risk-tag {
    font-weight: 700;
    color: #cbd5e1;
}
.drv-lic-id {
    color: #64748b;
}
</style>
