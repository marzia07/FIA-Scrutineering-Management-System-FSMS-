<?php
// FIA FSMS Interactive 1.6L V6 Turbo Hybrid Power Unit (PU) Exploded Architecture Diagram
if (!isset($conn) && file_exists('db.php')) {
    require_once 'db.php';
}

$pu_cars_list = [];
if (isset($conn)) {
    $c_res = $conn->query("SELECT c.car_id, c.car_name, c.chassis_number, c.engine_type, t.team_name, d.full_name as driver_name 
                           FROM CARS c 
                           LEFT JOIN TEAMS t ON c.team_id = t.team_id 
                           LEFT JOIN DRIVERS d ON c.driver_id = d.driver_id 
                           ORDER BY c.car_id ASC LIMIT 10");
    if ($c_res) {
        while ($r = $c_res->fetch_assoc()) {
            $pu_cars_list[] = $r;
        }
    }
}
?>

<!-- INTERACTIVE POWER UNIT (PU) ARCHITECTURE COMPONENT -->
<div class="f1-pu-card" id="f1PuDiagramCard">
    <div class="pu-header">
        <div class="pu-title-group">
            <span class="pu-tag">FIA SPORTING & TECHNICAL REGULATIONS // ARTICLE 5.1</span>
            <h2 class="pu-title">HYBRID POWER UNIT (PU) ARCHITECTURE & ALLOCATION RADAR</h2>
        </div>
        <div class="pu-car-select-wrap">
            <label for="puCarSelect">TARGET POWER UNIT:</label>
            <select id="puCarSelect" onchange="updatePuCar(this.value)">
                <?php if (!empty($pu_cars_list)): ?>
                    <?php foreach ($pu_cars_list as $pc): ?>
                        <option value="<?php echo $pc['car_id']; ?>" data-engine="<?php echo htmlspecialchars($pc['engine_type'] ?? '1.6L V6 Turbo Hybrid'); ?>" data-team="<?php echo htmlspecialchars($pc['team_name'] ?? 'Team'); ?>" data-driver="<?php echo htmlspecialchars($pc['driver_name'] ?? 'Driver'); ?>">
                            <?php echo htmlspecialchars($pc['car_name']); ?> — <?php echo htmlspecialchars($pc['team_name'] ?? ''); ?>
                        </option>
                    <?php endforeach; ?>
                <?php else: ?>
                    <option value="1" data-engine="Honda RBPTH002 1.6L V6 Turbo Hybrid" data-team="Red Bull Racing" data-driver="Max Verstappen">Red Bull RB20 #1 (Max Verstappen)</option>
                    <option value="2" data-engine="Ferrari 066/12 1.6L V6 Turbo Hybrid" data-team="Scuderia Ferrari" data-driver="Charles Leclerc">Ferrari SF-24 #16 (Charles Leclerc)</option>
                <?php endif; ?>
            </select>
        </div>
    </div>

    <div class="pu-stage">
        <!-- Exploded SVG Schematic of 6 PU Elements -->
        <div class="pu-canvas-container">
            <svg class="pu-svg" viewBox="0 0 760 380" preserveAspectRatio="xMidYMid meet">
                <defs>
                    <linearGradient id="iceGrad" x1="0%" y1="0%" x2="100%" y2="100%">
                        <stop offset="0%" stop-color="#ff1801" stop-opacity="0.25"/>
                        <stop offset="100%" stop-color="#b31000" stop-opacity="0.6"/>
                    </linearGradient>
                    <linearGradient id="turboGrad" x1="0%" y1="0%" x2="100%" y2="100%">
                        <stop offset="0%" stop-color="#00d2be" stop-opacity="0.25"/>
                        <stop offset="100%" stop-color="#008f82" stop-opacity="0.6"/>
                    </linearGradient>
                    <linearGradient id="esGrad" x1="0%" y1="0%" x2="100%" y2="100%">
                        <stop offset="0%" stop-color="#f1c40f" stop-opacity="0.25"/>
                        <stop offset="100%" stop-color="#b8930c" stop-opacity="0.6"/>
                    </linearGradient>
                </defs>

                <!-- Electrical & Thermal Flow Connecting Lines -->
                <!-- Exhaust to Turbo -->
                <path d="M 280 190 L 360 140" stroke="#ff4757" stroke-width="2.5" stroke-dasharray="4,3" fill="none" />
                <!-- Turbo to MGU-H -->
                <path d="M 390 140 L 460 140" stroke="#00d2be" stroke-width="2.5" stroke-dasharray="4,3" fill="none" />
                <!-- ICE to MGU-K -->
                <path d="M 260 220 L 260 290" stroke="#f1c40f" stroke-width="2.5" stroke-dasharray="4,3" fill="none" />
                <!-- MGU-K / MGU-H to Energy Store -->
                <path d="M 300 290 Q 420 300 520 270" stroke="#f1c40f" stroke-width="2.5" stroke-dasharray="4,3" fill="none" />
                <!-- Energy Store to Control Electronics -->
                <path d="M 570 240 L 570 170" stroke="#00d2be" stroke-width="2.5" stroke-dasharray="4,3" fill="none" />

                <!-- 1. ICE (Internal Combustion Engine) -->
                <g class="pu-component-node" onclick="selectPuElement('ICE')" transform="translate(180, 140)">
                    <rect x="0" y="0" width="130" height="90" rx="8" fill="url(#iceGrad)" stroke="#ff1801" stroke-width="2" class="pu-part-box" />
                    <!-- V6 Piston Cylinders wireframe -->
                    <circle cx="35" cy="35" r="14" fill="none" stroke="#ff7675" stroke-width="1.5" />
                    <circle cx="65" cy="35" r="14" fill="none" stroke="#ff7675" stroke-width="1.5" />
                    <circle cx="95" cy="35" r="14" fill="none" stroke="#ff7675" stroke-width="1.5" />
                    <circle cx="35" cy="65" r="14" fill="none" stroke="#ff7675" stroke-width="1.5" />
                    <circle cx="65" cy="65" r="14" fill="none" stroke="#ff7675" stroke-width="1.5" />
                    <circle cx="95" cy="65" r="14" fill="none" stroke="#ff7675" stroke-width="1.5" />
                    <text x="65" y="-10" text-anchor="middle" fill="#ff1801" font-family="'Orbitron', sans-serif" font-size="11" font-weight="900">1. ICE (V6 ENGINE)</text>
                    <text x="65" y="105" text-anchor="middle" fill="#8c8c9e" font-family="'Share Tech Mono', monospace" font-size="9">MAX 4 UNITS</text>
                </g>

                <!-- 2. TC (Turbocharger) -->
                <g class="pu-component-node" onclick="selectPuElement('TC')" transform="translate(340, 95)">
                    <circle cx="45" cy="45" r="38" fill="url(#turboGrad)" stroke="#00d2be" stroke-width="2" class="pu-part-box" />
                    <!-- Turbo turbine fan blades -->
                    <path d="M 45 15 L 45 75 M 15 45 L 75 45 M 24 24 L 66 66 M 24 66 L 66 24" stroke="#55ebd8" stroke-width="1.5" />
                    <circle cx="45" cy="45" r="10" fill="#00d2be" />
                    <text x="45" y="-12" text-anchor="middle" fill="#00d2be" font-family="'Orbitron', sans-serif" font-size="11" font-weight="900">2. TURBO (TC)</text>
                    <text x="45" y="98" text-anchor="middle" fill="#8c8c9e" font-family="'Share Tech Mono', monospace" font-size="9">MAX 4 UNITS</text>
                </g>

                <!-- 3. MGU-H (Motor Generator Heat) -->
                <g class="pu-component-node" onclick="selectPuElement('MGU-H')" transform="translate(470, 105)">
                    <rect x="0" y="0" width="80" height="70" rx="6" fill="rgba(0, 210, 190, 0.15)" stroke="#00d2be" stroke-width="2" class="pu-part-box" />
                    <line x1="20" y1="10" x2="20" y2="60" stroke="#00d2be" stroke-width="2" stroke-dasharray="3,2" />
                    <line x1="40" y1="10" x2="40" y2="60" stroke="#00d2be" stroke-width="2" stroke-dasharray="3,2" />
                    <line x1="60" y1="10" x2="60" y2="60" stroke="#00d2be" stroke-width="2" stroke-dasharray="3,2" />
                    <text x="40" y="-12" text-anchor="middle" fill="#00d2be" font-family="'Orbitron', sans-serif" font-size="11" font-weight="900">3. MGU-H</text>
                    <text x="40" y="86" text-anchor="middle" fill="#8c8c9e" font-family="'Share Tech Mono', monospace" font-size="9">MAX 4 UNITS</text>
                </g>

                <!-- 4. MGU-K (Motor Generator Kinetic) -->
                <g class="pu-component-node" onclick="selectPuElement('MGU-K')" transform="translate(200, 260)">
                    <rect x="0" y="0" width="95" height="65" rx="6" fill="rgba(241, 196, 15, 0.15)" stroke="#f1c40f" stroke-width="2" class="pu-part-box" />
                    <circle cx="47" cy="32" r="18" fill="none" stroke="#f1c40f" stroke-width="2" />
                    <circle cx="47" cy="32" r="8" fill="#f1c40f" />
                    <text x="47" y="-10" text-anchor="middle" fill="#f1c40f" font-family="'Orbitron', sans-serif" font-size="11" font-weight="900">4. MGU-K (120kW)</text>
                    <text x="47" y="80" text-anchor="middle" fill="#8c8c9e" font-family="'Share Tech Mono', monospace" font-size="9">MAX 4 UNITS</text>
                </g>

                <!-- 5. ES (Energy Store Battery) -->
                <g class="pu-component-node" onclick="selectPuElement('ES')" transform="translate(490, 230)">
                    <rect x="0" y="0" width="110" height="75" rx="6" fill="url(#esGrad)" stroke="#f1c40f" stroke-width="2" class="pu-part-box" />
                    <!-- Battery cells -->
                    <line x1="20" y1="15" x2="90" y2="15" stroke="#ffe066" stroke-width="3" />
                    <line x1="20" y1="35" x2="90" y2="35" stroke="#ffe066" stroke-width="3" />
                    <line x1="20" y1="55" x2="90" y2="55" stroke="#ffe066" stroke-width="3" />
                    <text x="55" y="-10" text-anchor="middle" fill="#f1c40f" font-family="'Orbitron', sans-serif" font-size="11" font-weight="900">5. ENERGY STORE (ES)</text>
                    <text x="55" y="90" text-anchor="middle" fill="#ff4757" font-family="'Share Tech Mono', monospace" font-size="9">STRICT MAX 2 UNITS</text>
                </g>

                <!-- 6. CE (Control Electronics) -->
                <g class="pu-component-node" onclick="selectPuElement('CE')" transform="translate(600, 110)">
                    <rect x="0" y="0" width="85" height="70" rx="6" fill="rgba(0, 210, 190, 0.15)" stroke="#00d2be" stroke-width="2" class="pu-part-box" />
                    <!-- Microchip PCB grid -->
                    <rect x="25" y="20" width="35" height="30" fill="#008f82" stroke="#55ebd8" stroke-width="1" />
                    <text x="42" y="-12" text-anchor="middle" fill="#00d2be" font-family="'Orbitron', sans-serif" font-size="11" font-weight="900">6. CONTROL ELEC (CE)</text>
                    <text x="42" y="86" text-anchor="middle" fill="#ff4757" font-family="'Share Tech Mono', monospace" font-size="9">STRICT MAX 2 UNITS</text>
                </g>

                <!-- 7. EX (Exhaust System) -->
                <g class="pu-component-node" onclick="selectPuElement('EX')" transform="translate(50, 160)">
                    <path d="M 20 20 Q 50 10 70 30 Q 90 50 110 35" fill="none" stroke="#ff9f43" stroke-width="4" />
                    <path d="M 20 40 Q 50 30 70 50 Q 90 70 110 55" fill="none" stroke="#ff9f43" stroke-width="4" />
                    <text x="65" y="0" text-anchor="middle" fill="#ff9f43" font-family="'Orbitron', sans-serif" font-size="10" font-weight="900">7. EXHAUST (EX)</text>
                    <text x="65" y="75" text-anchor="middle" fill="#8c8c9e" font-family="'Share Tech Mono', monospace" font-size="9">MAX 8 UNITS</text>
                </g>
            </svg>
        </div>

        <!-- RIGHT SIDE: PU COMPONENT METRIC TELEMETRY HUD -->
        <div class="pu-telemetry-panel" id="puPanel">
            <div class="pu-tag-row">
                <span class="pu-element-code" id="puElementCode">ELEMENT #01 // ICE</span>
                <span class="pu-status-pill status-within-cap" id="puStatusPill">🟢 WITHIN SEASON CAP</span>
            </div>

            <h3 class="pu-element-name" id="puElementName">Internal Combustion Engine (1.6L 90° V6)</h3>
            <div class="pu-homologation-ref" id="puHomologation">FIA Homologation Code: #FIA-PU2026-ICE-03</div>

            <div class="pu-meter-container">
                <div class="pu-meter-label-row">
                    <span>SEASON ALLOCATION QUOTA</span>
                    <span id="puQuotaText">3 / 4 UNITS USED</span>
                </div>
                <div class="pu-progress-bar">
                    <div class="pu-progress-fill" id="puProgressFill" style="width: 75%; background: #00d2be;"></div>
                </div>
                <div class="pu-penalty-warning" id="puPenaltyWarning">
                    ℹ️ 1 unit remaining before mandatory 10-place grid drop penalty is triggered.
                </div>
            </div>

            <div class="pu-telemetry-matrix">
                <div class="pu-matrix-row">
                    <span class="pu-lbl">PEAK OUTPUT:</span>
                    <span class="pu-val" id="puValPower">~850 BHP @ 15,000 RPM</span>
                </div>
                <div class="pu-matrix-row">
                    <span class="pu-lbl">FUEL MASS FLOW:</span>
                    <span class="pu-val" id="puValFuel">100.0 kg/h Statutory Ceiling</span>
                </div>
                <div class="pu-matrix-row">
                    <span class="pu-lbl">CURRENT CHASSIS:</span>
                    <span class="pu-val highlight" id="puValChassis">CHASSIS-RB20-01 (Red Bull RB20)</span>
                </div>
                <div class="pu-matrix-row">
                    <span class="pu-lbl">LAST REPLACED:</span>
                    <span class="pu-val" id="puValReplaced">Round 06 (Miami GP)</span>
                </div>
            </div>

            <div class="pu-notes-box" id="puNotes">
                FIA technical delegate seal #FIA-SEAL-ICE-8821 verified intact. Electronic pressure transducer calibrated to telemetry logger.
            </div>

            <div class="pu-actions">
                <a href="feature4.php#allocationTable" class="btn-pu-action">
                    <span>📑</span> VIEW FULL PU ALLOCATION TABLE
                </a>
            </div>
        </div>
    </div>
</div>

<!-- PU STYLES -->
<style>
.f1-pu-card {
    background: linear-gradient(135deg, rgba(16, 16, 26, 0.96) 0%, rgba(10, 10, 18, 0.98) 100%);
    border: 1px solid rgba(255, 255, 255, 0.08);
    border-top: 3px solid var(--f1-cyan);
    border-radius: 12px;
    padding: 24px;
    margin-bottom: 28px;
    box-shadow: 0 15px 40px rgba(0, 0, 0, 0.6);
    backdrop-filter: blur(20px);
    font-family: 'Titillium Web', sans-serif;
}
.pu-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    border-bottom: 1px solid rgba(255, 255, 255, 0.06);
    padding-bottom: 16px;
    margin-bottom: 20px;
    flex-wrap: wrap;
    gap: 12px;
}
.pu-tag {
    font-family: 'Share Tech Mono', monospace;
    font-size: 11px;
    color: var(--f1-cyan);
    letter-spacing: 1.5px;
    display: block;
    margin-bottom: 4px;
}
.pu-title {
    font-family: 'Orbitron', sans-serif;
    font-size: 17px;
    font-weight: 900;
    color: #ffffff;
    letter-spacing: 1px;
}
.pu-car-select-wrap {
    display: flex;
    align-items: center;
    gap: 10px;
    font-family: 'Share Tech Mono', monospace;
    font-size: 11px;
    color: #94a3b8;
}
.pu-car-select-wrap select {
    background: rgba(0, 0, 0, 0.6);
    border: 1px solid rgba(0, 210, 190, 0.4);
    color: #ffffff;
    font-family: 'Titillium Web', sans-serif;
    font-size: 12px;
    font-weight: 700;
    padding: 6px 12px;
    border-radius: 6px;
    outline: none;
    cursor: pointer;
}

.pu-stage {
    display: grid;
    grid-template-columns: 1.4fr 1fr;
    gap: 24px;
    align-items: center;
}
@media (max-width: 1000px) {
    .pu-stage { grid-template-columns: 1fr; }
}

.pu-canvas-container {
    background: rgba(4, 4, 8, 0.7);
    border: 1px solid rgba(255, 255, 255, 0.06);
    border-radius: 8px;
    padding: 16px;
    box-shadow: inset 0 0 30px rgba(0, 0, 0, 0.8);
}
.pu-svg {
    width: 100%;
    height: auto;
    display: block;
}
.pu-component-node {
    cursor: pointer;
    transition: transform 0.2s ease;
}
.pu-component-node:hover {
    transform: scale(1.05);
    filter: drop-shadow(0 0 10px var(--f1-cyan));
}

.pu-telemetry-panel {
    background: rgba(0, 0, 0, 0.5);
    border: 1px solid rgba(255, 255, 255, 0.08);
    border-radius: 8px;
    padding: 20px;
}
.pu-tag-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 8px;
}
.pu-element-code {
    font-family: 'Share Tech Mono', monospace;
    font-size: 11px;
    color: #64748b;
    letter-spacing: 1px;
}
.pu-status-pill {
    font-family: 'Share Tech Mono', monospace;
    font-size: 10px;
    font-weight: 700;
    padding: 3px 8px;
    border-radius: 4px;
}
.pu-status-pill.status-within-cap {
    background: rgba(0, 210, 190, 0.15);
    color: #00d2be;
    border: 1px solid rgba(0, 210, 190, 0.4);
}
.pu-status-pill.status-penalty-risk {
    background: rgba(241, 196, 15, 0.15);
    color: #f1c40f;
    border: 1px solid #f1c40f;
}
.pu-status-pill.status-grid-penalty {
    background: rgba(255, 24, 1, 0.2);
    color: #ff4757;
    border: 1px solid #ff4757;
}

.pu-element-name {
    font-family: 'Orbitron', sans-serif;
    font-size: 15px;
    font-weight: 900;
    color: #ffffff;
    margin-bottom: 4px;
}
.pu-homologation-ref {
    font-family: 'Share Tech Mono', monospace;
    font-size: 11px;
    color: #8c8c9e;
    margin-bottom: 14px;
}

.pu-meter-container {
    background: rgba(255, 255, 255, 0.02);
    border: 1px solid rgba(255, 255, 255, 0.06);
    border-radius: 6px;
    padding: 12px;
    margin-bottom: 16px;
}
.pu-meter-label-row {
    display: flex;
    justify-content: space-between;
    font-family: 'Share Tech Mono', monospace;
    font-size: 10.5px;
    color: #94a3b8;
    margin-bottom: 6px;
}
.pu-progress-bar {
    height: 8px;
    background: rgba(255, 255, 255, 0.08);
    border-radius: 4px;
    overflow: hidden;
    margin-bottom: 8px;
}
.pu-progress-fill {
    height: 100%;
    border-radius: 4px;
    transition: width 0.4s ease;
    box-shadow: 0 0 10px currentColor;
}
.pu-penalty-warning {
    font-size: 11px;
    color: #cbd5e1;
    line-height: 1.4;
}

.pu-telemetry-matrix {
    border-top: 1px dashed rgba(255, 255, 255, 0.08);
    border-bottom: 1px dashed rgba(255, 255, 255, 0.08);
    padding: 10px 0;
    margin-bottom: 14px;
    font-size: 11.5px;
}
.pu-matrix-row {
    display: flex;
    justify-content: space-between;
    padding: 3px 0;
}
.pu-lbl { color: #64748b; font-family: 'Share Tech Mono', monospace; font-size: 10.5px; }
.pu-val { color: #ffffff; font-weight: 700; }
.pu-val.highlight { color: var(--f1-cyan); }

.pu-notes-box {
    font-size: 11.5px;
    line-height: 1.5;
    color: #cbd5e1;
    background: rgba(255, 255, 255, 0.02);
    padding: 10px;
    border-radius: 6px;
    border-left: 3px solid var(--f1-cyan);
    margin-bottom: 16px;
}
.btn-pu-action {
    background: linear-gradient(90deg, #00d2be 0%, #008f82 100%);
    color: #07070e;
    font-family: 'Orbitron', sans-serif;
    font-size: 10.5px;
    font-weight: 900;
    letter-spacing: 1px;
    padding: 9px 14px;
    border-radius: 6px;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    box-shadow: 0 0 15px rgba(0, 210, 190, 0.4);
    transition: all 0.2s ease;
}
.btn-pu-action:hover {
    box-shadow: 0 0 25px rgba(0, 210, 190, 0.8);
    transform: translateY(-1px);
}
</style>

<!-- PU INTERACTION SCRIPT -->
<script>
const f1PuElementsDatabase = {
    'ICE': {
        code: 'ELEMENT #01 // ICE',
        name: 'Internal Combustion Engine (1.6L 90° V6)',
        homologation: 'FIA Homologation Code: #FIA-PU2026-ICE-03',
        quota: '3 / 4 UNITS USED',
        pct: 75,
        color: '#00d2be',
        status: '🟢 WITHIN SEASON CAP',
        statusClass: 'status-within-cap',
        warning: 'ℹ️ 1 unit remaining before mandatory 10-place grid drop penalty is triggered.',
        power: '~850 BHP @ 15,000 RPM Max',
        fuel: '100.0 kg/h Statutory Ceiling',
        chassis: 'CHASSIS-RB20-01',
        replaced: 'Round 06 (Miami GP)',
        notes: 'FIA technical delegate seal #FIA-SEAL-ICE-8821 verified intact. Direct fuel injection pressure 500 bar compliant.'
    },
    'TC': {
        code: 'ELEMENT #02 // TC',
        name: 'Turbocharger & Exhaust Turbine Unit',
        homologation: 'FIA Homologation Code: #FIA-PU2026-TC-03',
        quota: '3 / 4 UNITS USED',
        pct: 75,
        color: '#00d2be',
        status: '🟢 WITHIN SEASON CAP',
        statusClass: 'status-within-cap',
        warning: 'ℹ️ 1 unit remaining in quota allowance.',
        power: 'Boost Pressure ~4.5 bar absolute',
        fuel: 'Max Turbine Speed 125,000 RPM',
        chassis: 'CHASSIS-RB20-01',
        replaced: 'Round 06 (Miami GP)',
        notes: 'Ultrasonic crack detection completed. Turbine housing ceramic coating homologated.'
    },
    'MGU-H': {
        code: 'ELEMENT #03 // MGU-H',
        name: 'Motor Generator Unit - Heat (MGU-H)',
        homologation: 'FIA Homologation Code: #FIA-PU2026-MGUH-03',
        quota: '3 / 4 UNITS USED',
        pct: 75,
        color: '#00d2be',
        status: '🟢 WITHIN SEASON CAP',
        statusClass: 'status-within-cap',
        warning: 'ℹ️ Connected directly to turbo shaft for unlimited energy harvesting.',
        power: 'Unlimited MJ Energy Recovery',
        fuel: 'Thermal Harvest Efficiency ~92%',
        chassis: 'CHASSIS-RB20-01',
        replaced: 'Round 05 (Chinese GP)',
        notes: 'Stator winding insulation resistance > 100 MΩ. No thermal degradation detected.'
    },
    'MGU-K': {
        code: 'ELEMENT #04 // MGU-K',
        name: 'Motor Generator Unit - Kinetic (MGU-K)',
        homologation: 'FIA Homologation Code: #FIA-PU2026-MGUK-03',
        quota: '3 / 4 UNITS USED',
        pct: 75,
        color: '#00d2be',
        status: '🟢 WITHIN SEASON CAP',
        statusClass: 'status-within-cap',
        warning: 'ℹ️ Max 120kW (160bhp) deployment power cap enforced by FIA SECU.',
        power: '120 kW (160 BHP) / 2MJ Per Lap Harvest',
        fuel: 'Max Crankshaft Speed 50,000 RPM',
        chassis: 'CHASSIS-RB20-01',
        replaced: 'Round 06 (Miami GP)',
        notes: 'Torque sensor calibration verified against FIA SECU telemetry telemetry logger.'
    },
    'ES': {
        code: 'ELEMENT #05 // ES',
        name: 'Energy Store (Lithium-Ion High Voltage Battery)',
        homologation: 'FIA Homologation Code: #FIA-PU2026-ES-02',
        quota: '2 / 2 UNITS USED (MAX LIMIT REACHED)',
        pct: 100,
        color: '#ff4757',
        status: '⚠️ ALLOCATION CAP REACHED',
        statusClass: 'status-penalty-risk',
        warning: '⚠️ WARNING: Next Energy Store replacement will incur a mandatory 10-place grid drop penalty!',
        power: '4 MJ Max Deployment Per Lap',
        fuel: 'High-Voltage DC 800V Bus',
        chassis: 'CHASSIS-RB20-01',
        replaced: 'Round 07 (Emilia Romagna GP)',
        notes: 'Parc Fermé tamper seal #FIA-SEAL-ES-9904 intact. Cell voltage balancing within 5mV nominal delta.'
    },
    'CE': {
        code: 'ELEMENT #06 // CE',
        name: 'Control Electronics (FIA SECU Processing Unit)',
        homologation: 'FIA Homologation Code: #FIA-PU2026-CE-02',
        quota: '2 / 2 UNITS USED (MAX LIMIT REACHED)',
        pct: 100,
        color: '#ff4757',
        status: '⚠️ ALLOCATION CAP REACHED',
        statusClass: 'status-penalty-risk',
        warning: '⚠️ WARNING: All 2 allowed units allocated. Any further replacement incurs grid penalties.',
        power: 'FIA Standard SECU Architecture',
        fuel: 'Encrypted Telemetry Transceiver',
        chassis: 'CHASSIS-RB20-01',
        replaced: 'Round 07 (Emilia Romagna GP)',
        notes: 'FIA cryptographic signature verified on powertrain engine management firmware map v2026.4.'
    },
    'EX': {
        code: 'ELEMENT #07 // EX',
        name: 'Inconel Exhaust Piping & Tailpipe Assembly',
        homologation: 'FIA Homologation Code: #FIA-PU2026-EX-04',
        quota: '4 / 8 UNITS USED',
        pct: 50,
        color: '#ff9f43',
        status: '🟢 WITHIN SEASON CAP',
        statusClass: 'status-within-cap',
        warning: 'ℹ️ 4 exhaust assemblies remaining in 8-unit season cap.',
        power: 'Inconel 625 Superalloy Wall 0.8mm',
        fuel: 'Max Exhaust Temp ~1020°C',
        chassis: 'CHASSIS-RB20-01',
        replaced: 'Round 08 (Monaco GP)',
        notes: 'Exhaust tailpipe diameter & angle compliant with Article 5.8 technical specifications.'
    }
};

function selectPuElement(key) {
    const d = f1PuElementsDatabase[key];
    if (!d) return;

    document.getElementById('puElementCode').textContent = d.code;
    document.getElementById('puElementName').textContent = d.name;
    document.getElementById('puHomologation').textContent = d.homologation;
    document.getElementById('puQuotaText').textContent = d.quota;
    document.getElementById('puPenaltyWarning').textContent = d.warning;
    document.getElementById('puValPower').textContent = d.power;
    document.getElementById('puValFuel').textContent = d.fuel;
    document.getElementById('puValChassis').textContent = d.chassis;
    document.getElementById('puValReplaced').textContent = d.replaced;
    document.getElementById('puNotes').textContent = d.notes;

    const fill = document.getElementById('puProgressFill');
    fill.style.width = d.pct + '%';
    fill.style.background = d.color;

    const pill = document.getElementById('puStatusPill');
    pill.className = 'pu-status-pill ' + d.statusClass;
    pill.textContent = d.status;

    if (typeof playGlobalF1Radio === 'function') {
        playGlobalF1Radio();
    }
}

function updatePuCar(carId) {
    const sel = document.getElementById('puCarSelect');
    const opt = sel.options[sel.selectedIndex];
    const team = opt.getAttribute('data-team') || 'Constructor';
    const driver = opt.getAttribute('data-driver') || 'Driver';

    // Highlight ICE by default on car switch
    selectPuElement('ICE');
}
</script>
