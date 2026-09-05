<?php
// FIA FSMS Interactive F1 Car Component Inspection Blueprint (Hotspots)
if (!isset($conn) && file_exists('db.php')) {
    require_once 'db.php';
}

// Fetch cars list for selector
$blueprint_cars = [];
if (isset($conn)) {
    $c_res = $conn->query("SELECT c.car_id, c.car_name, c.chassis_number, t.team_name, d.full_name as driver_name 
                           FROM CARS c 
                           LEFT JOIN TEAMS t ON c.team_id = t.team_id 
                           LEFT JOIN DRIVERS d ON c.driver_id = d.driver_id 
                           ORDER BY c.car_id ASC LIMIT 10");
    if ($c_res) {
        while ($r = $c_res->fetch_assoc()) {
            $blueprint_cars[] = $r;
        }
    }
}
?>
<!-- INTERACTIVE F1 CAR BLUEPRINT COMPONENT -->
<div class="f1-blueprint-card" id="f1BlueprintCard">
    <div class="blueprint-header">
        <div class="blueprint-title-group">
            <span class="blueprint-tag">FIA TECHNICAL SCANNERS // 3D/2D CHASSIS MAPPING</span>
            <h2 class="blueprint-title">INTERACTIVE F1 CAR COMPONENT INSPECTION RIG</h2>
        </div>
        <div class="blueprint-car-select-wrap">
            <label for="blueprintCarSelect">TARGET CHASSIS:</label>
            <select id="blueprintCarSelect" onchange="updateBlueprintCar(this.value)">
                <?php if (!empty($blueprint_cars)): ?>
                    <?php foreach ($blueprint_cars as $bc): ?>
                        <option value="<?php echo $bc['car_id']; ?>" data-driver="<?php echo htmlspecialchars($bc['driver_name'] ?? 'Driver'); ?>" data-chassis="<?php echo htmlspecialchars($bc['chassis_number']); ?>" data-team="<?php echo htmlspecialchars($bc['team_name'] ?? 'Team'); ?>">
                            <?php echo htmlspecialchars($bc['car_name']); ?> (<?php echo htmlspecialchars($bc['chassis_number']); ?>)
                        </option>
                    <?php endforeach; ?>
                <?php else: ?>
                    <option value="1" data-driver="Max Verstappen" data-chassis="CHASSIS-RB20-01" data-team="Red Bull Racing">Red Bull RB20 #1 (CHASSIS-RB20-01)</option>
                    <option value="2" data-driver="Charles Leclerc" data-chassis="CHASSIS-SF24-02" data-team="Ferrari">Ferrari SF-24 #16 (CHASSIS-SF24-02)</option>
                <?php endif; ?>
            </select>
        </div>
    </div>

    <div class="blueprint-stage">
        <!-- SVG Interactive F1 Blueprint Wireframe with Hotspot Nodes -->
        <div class="blueprint-canvas-container">
            <svg class="f1-chassis-svg" viewBox="0 0 800 380" preserveAspectRatio="xMidYMid meet">
                <defs>
                    <linearGradient id="chassisGrad" x1="0%" y1="0%" x2="100%" y2="0%">
                        <stop offset="0%" stop-color="#00d2be" stop-opacity="0.8"/>
                        <stop offset="50%" stop-color="#ff1801" stop-opacity="0.8"/>
                        <stop offset="100%" stop-color="#00d2be" stop-opacity="0.8"/>
                    </linearGradient>
                    <filter id="neonGlow" x="-20%" y="-20%" width="140%" height="140%">
                        <feGaussianBlur stdDeviation="3" result="blur" />
                        <feComposite in="SourceGraphic" in2="blur" operator="over" />
                    </filter>
                </defs>

                <!-- Grid Background lines inside SVG -->
                <pattern id="svgGrid" width="20" height="20" patternUnits="userSpaceOnUse">
                    <path d="M 20 0 L 0 0 0 20" fill="none" stroke="rgba(255,255,255,0.04)" stroke-width="1"/>
                </pattern>
                <rect width="100%" height="100%" fill="url(#svgGrid)" />

                <!-- Centerline & Dimension Guidelines -->
                <line x1="40" y1="190" x2="760" y2="190" stroke="rgba(0, 210, 190, 0.2)" stroke-dasharray="6,4" stroke-width="1.5" />
                <line x1="140" y1="40" x2="140" y2="340" stroke="rgba(255, 255, 255, 0.08)" stroke-width="1" />
                <line x1="680" y1="40" x2="680" y2="340" stroke="rgba(255, 255, 255, 0.08)" stroke-width="1" />

                <!-- F1 Top-Down Aerodynamic Body Outline -->
                <!-- Front Wing -->
                <path d="M 60 110 Q 80 190 60 270 L 110 260 L 110 210 L 140 200 L 140 180 L 110 170 L 110 120 Z" 
                      fill="rgba(0, 210, 190, 0.08)" stroke="#00d2be" stroke-width="2" class="svg-part" onclick="selectHotspot('front_wing')" />
                
                <!-- Front Nosecone & Suspension Arms -->
                <polygon points="140,180 280,160 280,220 140,200" fill="rgba(255, 255, 255, 0.03)" stroke="rgba(255,255,255,0.4)" stroke-width="1.5" />
                <line x1="200" y1="170" x2="160" y2="80" stroke="#8c8c9e" stroke-width="2" />
                <line x1="220" y1="170" x2="180" y2="80" stroke="#8c8c9e" stroke-width="2" />
                <line x1="200" y1="210" x2="160" y2="300" stroke="#8c8c9e" stroke-width="2" />
                <line x1="220" y1="210" x2="180" y2="300" stroke="#8c8c9e" stroke-width="2" />

                <!-- Front Wheels -->
                <rect x="140" y="50" width="80" height="40" rx="6" fill="#181824" stroke="#ff1801" stroke-width="2" class="svg-part" onclick="selectHotspot('tyres_brakes')" />
                <rect x="140" y="290" width="80" height="40" rx="6" fill="#181824" stroke="#ff1801" stroke-width="2" class="svg-part" onclick="selectHotspot('tyres_brakes')" />

                <!-- Monocoque Cockpit & Halo -->
                <path d="M 280 155 Q 360 145 420 150 L 420 230 Q 360 235 280 225 Z" 
                      fill="rgba(255, 24, 1, 0.06)" stroke="rgba(255,255,255,0.6)" stroke-width="1.8" class="svg-part" onclick="selectHotspot('cockpit_halo')" />
                <!-- Halo Triangular Structure -->
                <path d="M 330 190 L 370 170 L 370 210 Z" fill="rgba(0, 210, 190, 0.2)" stroke="#00d2be" stroke-width="1.5" />

                <!-- Underfloor Venturi & Central Skid Block / Plank -->
                <rect x="290" y="180" width="280" height="20" rx="3" fill="rgba(241, 196, 15, 0.2)" stroke="#f1c40f" stroke-width="2" stroke-dasharray="4,2" class="svg-part" onclick="selectHotspot('plank_skid')" />
                
                <!-- Sidepods & Radiator Inlets -->
                <path d="M 300 135 L 480 135 Q 560 140 590 165 L 590 215 Q 560 240 480 245 L 300 245 Z" 
                      fill="rgba(255, 255, 255, 0.02)" stroke="#8c8c9e" stroke-width="1.5" class="svg-part" onclick="selectHotspot('floor_venturi')" />

                <!-- Engine Cover & Hybrid Power Unit (ICE / Turbo / MGU-K) -->
                <ellipse cx="500" cy="190" rx="70" ry="25" fill="rgba(255, 24, 1, 0.12)" stroke="#ff4757" stroke-width="2" class="svg-part" onclick="selectHotspot('power_unit')" />

                <!-- Rear Suspension & Rear Wheels -->
                <line x1="600" y1="175" x2="620" y2="70" stroke="#8c8c9e" stroke-width="2" />
                <line x1="600" y1="205" x2="620" y2="310" stroke="#8c8c9e" stroke-width="2" />
                <rect x="580" y="45" width="90" height="48" rx="6" fill="#181824" stroke="#ff1801" stroke-width="2" class="svg-part" onclick="selectHotspot('tyres_brakes')" />
                <rect x="580" y="285" width="90" height="48" rx="6" fill="#181824" stroke="#ff1801" stroke-width="2" class="svg-part" onclick="selectHotspot('tyres_brakes')" />

                <!-- Rear Wing & DRS Actuator -->
                <rect x="680" y="120" width="40" height="140" rx="4" fill="rgba(0, 210, 190, 0.1)" stroke="#00d2be" stroke-width="2.5" class="svg-part" onclick="selectHotspot('rear_wing_drs')" />
                <line x1="700" y1="150" x2="700" y2="230" stroke="#ff1801" stroke-width="3" />

                <!-- INTERACTIVE HOTSPOT NODES WITH GLOW -->
                <!-- 1. Front Wing -->
                <g class="hotspot-node" onclick="selectHotspot('front_wing')" transform="translate(85, 190)">
                    <circle r="12" fill="rgba(0, 210, 190, 0.25)" class="pulse-ring" />
                    <circle r="6" fill="#00d2be" />
                    <text x="0" y="24" text-anchor="middle" fill="#00d2be" font-family="'Share Tech Mono', monospace" font-size="10" font-weight="bold">1. FRONT WING</text>
                </g>

                <!-- 2. Floor / Venturi -->
                <g class="hotspot-node" onclick="selectHotspot('floor_venturi')" transform="translate(380, 130)">
                    <circle r="12" fill="rgba(0, 210, 190, 0.25)" class="pulse-ring" />
                    <circle r="6" fill="#00d2be" />
                    <text x="0" y="-12" text-anchor="middle" fill="#00d2be" font-family="'Share Tech Mono', monospace" font-size="10" font-weight="bold">2. VENTURI FLOOR</text>
                </g>

                <!-- 3. Skid Block / Plank -->
                <g class="hotspot-node" onclick="selectHotspot('plank_skid')" transform="translate(420, 190)">
                    <circle r="14" fill="rgba(241, 196, 15, 0.3)" class="pulse-ring" />
                    <circle r="7" fill="#f1c40f" />
                    <text x="0" y="24" text-anchor="middle" fill="#f1c40f" font-family="'Share Tech Mono', monospace" font-size="10" font-weight="bold">3. JABROC PLANK</text>
                </g>

                <!-- 4. Power Unit -->
                <g class="hotspot-node" onclick="selectHotspot('power_unit')" transform="translate(520, 190)">
                    <circle r="12" fill="rgba(255, 24, 1, 0.3)" class="pulse-ring" />
                    <circle r="6" fill="#ff1801" />
                    <text x="0" y="24" text-anchor="middle" fill="#ff4757" font-family="'Share Tech Mono', monospace" font-size="10" font-weight="bold">4. POWER UNIT</text>
                </g>

                <!-- 5. Cockpit & Halo -->
                <g class="hotspot-node" onclick="selectHotspot('cockpit_halo')" transform="translate(350, 190)">
                    <circle r="12" fill="rgba(0, 210, 190, 0.25)" class="pulse-ring" />
                    <circle r="6" fill="#00d2be" />
                    <text x="0" y="-16" text-anchor="middle" fill="#00d2be" font-family="'Share Tech Mono', monospace" font-size="10" font-weight="bold">5. COCKPIT / HALO</text>
                </g>

                <!-- 6. Rear Wing DRS -->
                <g class="hotspot-node" onclick="selectHotspot('rear_wing_drs')" transform="translate(700, 190)">
                    <circle r="12" fill="rgba(0, 210, 190, 0.25)" class="pulse-ring" />
                    <circle r="6" fill="#00d2be" />
                    <text x="0" y="24" text-anchor="middle" fill="#00d2be" font-family="'Share Tech Mono', monospace" font-size="10" font-weight="bold">6. REAR WING DRS</text>
                </g>

                <!-- 7. Tyres & Brakes -->
                <g class="hotspot-node" onclick="selectHotspot('tyres_brakes')" transform="translate(180, 50)">
                    <circle r="12" fill="rgba(255, 24, 1, 0.25)" class="pulse-ring" />
                    <circle r="6" fill="#ff1801" />
                    <text x="0" y="-10" text-anchor="middle" fill="#ff1801" font-family="'Share Tech Mono', monospace" font-size="10" font-weight="bold">7. TYRES / BRAKES</text>
                </g>
            </svg>
        </div>

        <!-- RIGHT SIDE: TELEMETRY DIAGNOSTIC INSPECTION HUD -->
        <div class="blueprint-telemetry-panel" id="blueprintPanel">
            <div class="panel-tag-row">
                <span class="panel-hotspot-id" id="panelHotspotId">SUBSYSTEM #01</span>
                <span class="panel-status-pill status-pass" id="panelStatusPill">🟢 COMPLIANT</span>
            </div>
            
            <h3 class="panel-component-name" id="panelComponentName">Front Wing Assembly & Flaps</h3>
            <div class="panel-regulation-ref" id="panelRegRef">FIA Technical Regulations // Article 3.5.1</div>

            <div class="diag-matrix">
                <div class="matrix-row">
                    <span class="matrix-lbl">PRIMARY TEST:</span>
                    <span class="matrix-val" id="panelTestName">Vertical Load Deflection Test</span>
                </div>
                <div class="matrix-row">
                    <span class="matrix-lbl">STATUTORY LIMIT:</span>
                    <span class="matrix-val" id="panelLimitVal">≤ 2.00 mm (under 1000N Load)</span>
                </div>
                <div class="matrix-row">
                    <span class="matrix-lbl">LATEST MEASURED:</span>
                    <span class="matrix-val highlight" id="panelMeasuredVal">1.42 mm (Passed)</span>
                </div>
                <div class="matrix-row">
                    <span class="matrix-lbl">PARC FERMÉ SEAL:</span>
                    <span class="matrix-val" id="panelSealCode">FIA-SEAL-8841-PASS</span>
                </div>
                <div class="matrix-row">
                    <span class="matrix-lbl">ALLOCATION / HEALTH:</span>
                    <span class="matrix-val" id="panelHealthVal">Specification Rev-C (Homologated)</span>
                </div>
            </div>

            <div class="panel-notes" id="panelNotes">
                Ultrasonic laser coordinate measurement rig verified compliant deflection across trailing flap edges under 1.0kN statutory load application.
            </div>

            <div class="panel-actions">
                <a href="feature2.php" class="btn-diag-action">
                    <span>⚡</span> LOG SCRUTINEERING RECORD
                </a>
            </div>
        </div>
    </div>
</div>

<!-- BLUEPRINT STYLES -->
<style>
.f1-blueprint-card {
    background: linear-gradient(135deg, rgba(14, 14, 24, 0.96) 0%, rgba(8, 8, 14, 0.98) 100%);
    border: 1px solid rgba(255, 255, 255, 0.08);
    border-top: 3px solid var(--f1-cyan);
    border-radius: 12px;
    padding: 24px;
    margin-bottom: 28px;
    box-shadow: 0 15px 40px rgba(0, 0, 0, 0.6);
    backdrop-filter: blur(20px);
    position: relative;
    overflow: hidden;
}
.blueprint-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    border-bottom: 1px solid rgba(255, 255, 255, 0.06);
    padding-bottom: 16px;
    margin-bottom: 20px;
    flex-wrap: wrap;
    gap: 16px;
}
.blueprint-tag {
    font-family: 'Share Tech Mono', monospace;
    font-size: 11px;
    color: var(--f1-cyan);
    letter-spacing: 1.5px;
    display: block;
    margin-bottom: 4px;
}
.blueprint-title {
    font-family: 'Orbitron', sans-serif;
    font-size: 18px;
    font-weight: 900;
    color: #ffffff;
    letter-spacing: 1px;
}
.blueprint-car-select-wrap {
    display: flex;
    align-items: center;
    gap: 10px;
    font-family: 'Share Tech Mono', monospace;
    font-size: 11px;
    color: #94a3b8;
}
.blueprint-car-select-wrap select {
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

.blueprint-stage {
    display: grid;
    grid-template-columns: 1.5fr 1fr;
    gap: 24px;
    align-items: center;
}
@media (max-width: 1000px) {
    .blueprint-stage {
        grid-template-columns: 1fr;
    }
}

.blueprint-canvas-container {
    background: rgba(4, 4, 8, 0.7);
    border: 1px solid rgba(255, 255, 255, 0.06);
    border-radius: 8px;
    padding: 16px;
    box-shadow: inset 0 0 30px rgba(0, 0, 0, 0.8);
    position: relative;
}
.f1-chassis-svg {
    width: 100%;
    height: auto;
    display: block;
}
.svg-part {
    cursor: pointer;
    transition: all 0.2s ease;
}
.svg-part:hover {
    fill-opacity: 0.35 !important;
    filter: drop-shadow(0 0 8px #00d2be);
}
.hotspot-node {
    cursor: pointer;
    transition: transform 0.2s;
}
.hotspot-node:hover {
    transform: scale(1.15);
}
.pulse-ring {
    animation: ringGlow 2s infinite ease-out;
}
@keyframes ringGlow {
    0% { r: 6; opacity: 1; }
    100% { r: 18; opacity: 0; }
}

/* Right Side Diagnostic Panel */
.blueprint-telemetry-panel {
    background: rgba(0, 0, 0, 0.5);
    border: 1px solid rgba(255, 255, 255, 0.08);
    border-radius: 8px;
    padding: 20px;
    font-family: 'Titillium Web', sans-serif;
    box-shadow: 0 8px 25px rgba(0, 0, 0, 0.4);
}
.panel-tag-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 8px;
}
.panel-hotspot-id {
    font-family: 'Share Tech Mono', monospace;
    font-size: 11px;
    color: #64748b;
    letter-spacing: 1px;
}
.panel-status-pill {
    font-family: 'Share Tech Mono', monospace;
    font-size: 10.5px;
    font-weight: 700;
    padding: 3px 8px;
    border-radius: 4px;
}
.panel-status-pill.status-pass {
    background: rgba(0, 210, 190, 0.15);
    color: #00d2be;
    border: 1px solid rgba(0, 210, 190, 0.4);
}
.panel-status-pill.status-fail {
    background: rgba(255, 24, 1, 0.2);
    color: #ff4757;
    border: 1px solid #ff4757;
}
.panel-status-pill.status-warn {
    background: rgba(241, 196, 15, 0.2);
    color: #f1c40f;
    border: 1px solid #f1c40f;
}

.panel-component-name {
    font-family: 'Orbitron', sans-serif;
    font-size: 16px;
    font-weight: 900;
    color: #ffffff;
    margin-bottom: 4px;
}
.panel-regulation-ref {
    font-family: 'Share Tech Mono', monospace;
    font-size: 11px;
    color: #8c8c9e;
    margin-bottom: 16px;
}
.diag-matrix {
    border-top: 1px dashed rgba(255, 255, 255, 0.08);
    border-bottom: 1px dashed rgba(255, 255, 255, 0.08);
    padding: 12px 0;
    margin-bottom: 16px;
    font-size: 12px;
}
.matrix-row {
    display: flex;
    justify-content: space-between;
    padding: 4px 0;
}
.matrix-lbl { color: #64748b; font-family: 'Share Tech Mono', monospace; font-size: 11px; }
.matrix-val { color: #ffffff; font-weight: 700; }
.matrix-val.highlight { color: var(--f1-cyan); }
.panel-notes {
    font-size: 12px;
    line-height: 1.5;
    color: #cbd5e1;
    background: rgba(255, 255, 255, 0.02);
    padding: 10px 12px;
    border-radius: 6px;
    border-left: 3px solid var(--f1-cyan);
    margin-bottom: 18px;
}
.btn-diag-action {
    background: linear-gradient(90deg, #00d2be 0%, #008f82 100%);
    color: #07070e;
    font-family: 'Orbitron', sans-serif;
    font-size: 11px;
    font-weight: 900;
    letter-spacing: 1px;
    padding: 10px 16px;
    border-radius: 6px;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    box-shadow: 0 0 15px rgba(0, 210, 190, 0.4);
    transition: all 0.2s ease;
}
.btn-diag-action:hover {
    box-shadow: 0 0 25px rgba(0, 210, 190, 0.8);
    transform: translateY(-1px);
}
</style>

<!-- BLUEPRINT INTERACTION SCRIPT -->
<script>
const f1HotspotDatabase = {
    front_wing: {
        id: 'SUBSYSTEM #01',
        name: 'Front Wing Assembly & Flap Geometry',
        reg: 'FIA Technical Regulations // Article 3.5.1 & 3.5.2',
        test: 'Vertical Aerodynamic Load Deflection Test',
        limit: '≤ 2.00 mm (under 1000N vertical test load)',
        measured: '1.42 mm (Compliant)',
        statusText: '🟢 COMPLIANT',
        statusClass: 'status-pass',
        seal: 'FIA-SEAL-8841-PASS',
        health: 'Specification Rev-C (Homologated)',
        notes: 'Ultrasonic laser coordinate measurement rig verified compliant deflection across trailing flap edges under 1.0kN statutory load application.'
    },
    floor_venturi: {
        id: 'SUBSYSTEM #02',
        name: 'Floor Edge & Venturi Ground-Effect Tunnels',
        reg: 'FIA Technical Regulations // Article 3.5.6 & 3.5.8',
        test: 'Tunnel Throat Geometry & Floor Edge Stiffness',
        limit: 'Deflection ≤ 5.0 mm under 500N edge load',
        measured: '3.80 mm (Compliant)',
        statusText: '🟢 COMPLIANT',
        statusClass: 'status-pass',
        seal: 'FIA-SEAL-7712-VENTURI',
        health: 'Carbon composite floor undamaged',
        notes: 'Laser scanner confirmed 3D volume envelope compliance of underfloor diffuser expansion angles and fences.'
    },
    plank_skid: {
        id: 'SUBSYSTEM #03',
        name: 'Jabroc Skid Block & Central Plank Assembly',
        reg: 'FIA Technical Regulations // Article 3.12.1',
        test: 'Plank Wear Ultrasonic Thickness Gauge',
        limit: 'Mandatory Minimum ≥ 9.00 mm',
        measured: '9.45 mm (Compliant)',
        statusText: '🟢 COMPLIANT',
        statusClass: 'status-pass',
        seal: 'FIA-SEAL-9011-PLANK',
        health: '4 forward measurement holes within spec',
        notes: 'Ultrasonic thickness sensor verified 9.45mm across forward and rear statutory inspection apertures.'
    },
    power_unit: {
        id: 'SUBSYSTEM #04',
        name: 'Hybrid Power Unit (1.6L V6 Turbo + MGU-K/H)',
        reg: 'FIA Sporting & Technical Regulations // Article 5.1',
        test: 'PU Component Season Allocation & Temperature Map',
        limit: 'Max 4 ICE, 4 TC, 4 MGU-K, 4 MGU-H, 2 ES, 2 CE',
        measured: 'ICE #3 / 4 Units Used (Within Cap)',
        statusText: '🟢 COMPLIANT',
        statusClass: 'status-pass',
        seal: 'FIA-SEAL-PU-2026-09',
        health: 'All FIA homologated seals intact',
        notes: 'Parc Fermé electronic sensor interrogator confirmed matching serial numbers on ICE, Turbocharger and MGU-K units.'
    },
    cockpit_halo: {
        id: 'SUBSYSTEM #05',
        name: 'Cockpit Safety Cell & Grade 5 Titanium Halo',
        reg: 'FIA Technical Regulations // Article 12.4 & 13.1',
        test: 'Static Load Crash Test & Driver 7s Egress',
        limit: 'Survives 116kN static longitudinal test',
        measured: 'Passed FIA Homologation Test',
        statusText: '🟢 COMPLIANT',
        statusClass: 'status-pass',
        seal: 'FIA-SEAL-HALO-TITANIUM',
        health: 'Zero micro-fractures detected',
        notes: 'Cockpit padding density compliant. Driver 7-second emergency egress drill successfully demonstrated.'
    },
    rear_wing_drs: {
        id: 'SUBSYSTEM #06',
        name: 'Rear Wing & Hydraulic DRS Actuator System',
        reg: 'FIA Technical Regulations // Article 3.10.10',
        test: 'DRS Slot Gap Calibrated Ball Gauge Test',
        limit: '10.0mm to 15.0mm (Closed) / Max 85.0mm (Open)',
        measured: '12.4mm (Closed) / 84.6mm (Open)',
        statusText: '🟢 COMPLIANT',
        statusClass: 'status-pass',
        seal: 'FIA-SEAL-DRS-ACTUATOR',
        health: 'Hydraulic line pressure 180 bar',
        notes: 'Calibrated spherical go/no-go gauge verified 84.6mm opening clearance across entirety of upper wing flap span.'
    },
    tyres_brakes: {
        id: 'SUBSYSTEM #07',
        name: 'Pirelli 18-Inch Tyres & Carbon Brake Ducts',
        reg: 'FIA Technical Regulations // Article 10.5 & 10.8',
        test: 'Minimum Cold Pressure & Barcode Verification',
        limit: 'Front ≥ 23.5 PSI / Rear ≥ 21.0 PSI',
        measured: '24.2 PSI Front / 22.0 PSI Rear',
        statusText: '🟢 COMPLIANT',
        statusClass: 'status-pass',
        seal: 'FIA-RFID-TYRE-SET-4',
        health: 'Brake cake-tin internal sensors active',
        notes: 'RFID barcode telemetry matched officially allocated slick tyre sets. Wheel tether tensile cords inspected.'
    }
};

function selectHotspot(key) {
    const data = f1HotspotDatabase[key];
    if (!data) return;

    document.getElementById('panelHotspotId').textContent = data.id;
    document.getElementById('panelComponentName').textContent = data.name;
    document.getElementById('panelRegRef').textContent = data.reg;
    document.getElementById('panelTestName').textContent = data.test;
    document.getElementById('panelLimitVal').textContent = data.limit;
    document.getElementById('panelMeasuredVal').textContent = data.measured;
    document.getElementById('panelSealCode').textContent = data.seal;
    document.getElementById('panelHealthVal').textContent = data.health;
    document.getElementById('panelNotes').textContent = data.notes;

    const pill = document.getElementById('panelStatusPill');
    pill.className = 'panel-status-pill ' + data.statusClass;
    pill.textContent = data.statusText;

    if (typeof playGlobalF1Radio === 'function') {
        playGlobalF1Radio();
    }
}

function updateBlueprintCar(carId) {
    const sel = document.getElementById('blueprintCarSelect');
    const opt = sel.options[sel.selectedIndex];
    const driver = opt.getAttribute('data-driver') || 'Driver';
    const chassis = opt.getAttribute('data-chassis') || 'CHASSIS';

    // Randomize a subsystem status for demonstration
    if (carId == 1) {
        selectHotspot('front_wing');
    } else if (carId == 2) {
        selectHotspot('plank_skid');
    } else {
        selectHotspot('power_unit');
    }
}
</script>
