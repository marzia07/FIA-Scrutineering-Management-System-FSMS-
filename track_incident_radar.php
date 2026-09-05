<?php
// FIA FSMS Interactive Grand Prix Track Incident & Turn Radar
if (!isset($conn) && file_exists('db.php')) {
    require_once 'db.php';
}
?>

<!-- INTERACTIVE TRACK INCIDENT & TURN RADAR COMPONENT -->
<div class="f1-track-radar-card" id="f1TrackRadarCard">
    <div class="track-header">
        <div class="track-title-group">
            <span class="track-tag">FIA GPS INCIDENT TRACKER // 2026 MONACO GRAND PRIX</span>
            <h2 class="track-title">CIRCUIT DE MONACO // INTERACTIVE TURN INCIDENT RADAR</h2>
        </div>
        <div class="track-meta-pills">
            <span class="track-pill">LENGTH: 3.337 KM</span>
            <span class="track-pill">TURNS: 19</span>
            <span class="track-pill" style="color: #ff4757; border-color: #ff4757;">DRS ZONES: 1</span>
        </div>
    </div>

    <div class="track-stage">
        <!-- SVG Circuit Map with Clickable Interactive Corner Nodes -->
        <div class="track-canvas-container">
            <svg class="track-svg" viewBox="0 0 800 420" preserveAspectRatio="xMidYMid meet">
                <defs>
                    <linearGradient id="trackAsphaltGrad" x1="0%" y1="0%" x2="100%" y2="0%">
                        <stop offset="0%" stop-color="#ff1801" stop-opacity="0.8"/>
                        <stop offset="50%" stop-color="#00d2be" stop-opacity="0.8"/>
                        <stop offset="100%" stop-color="#ffb703" stop-opacity="0.8"/>
                    </linearGradient>
                    <filter id="trackGlow" x="-20%" y="-20%" width="140%" height="140%">
                        <feGaussianBlur stdDeviation="4" result="blur" />
                        <feComposite in="SourceGraphic" in2="blur" operator="over" />
                    </filter>
                </defs>

                <!-- Grid Background -->
                <pattern id="radarGrid" width="25" height="25" patternUnits="userSpaceOnUse">
                    <path d="M 25 0 L 0 0 0 25" fill="none" stroke="rgba(255,255,255,0.03)" stroke-width="1"/>
                </pattern>
                <rect width="100%" height="100%" fill="url(#radarGrid)" />

                <!-- Sector 1, 2, 3 Boundary Markers -->
                <text x="50" y="30" fill="#00d2be" font-family="'Share Tech Mono', monospace" font-size="10" font-weight="bold">SECTOR 1 [T1 - T4]</text>
                <text x="420" y="30" fill="#f1c40f" font-family="'Share Tech Mono', monospace" font-size="10" font-weight="bold">SECTOR 2 [T5 - T11]</text>
                <text x="620" y="390" fill="#ff4757" font-family="'Share Tech Mono', monospace" font-size="10" font-weight="bold">SECTOR 3 [T12 - T19]</text>

                <!-- Start / Finish Straight Line -->
                <line x1="160" y1="340" x2="160" y2="380" stroke="#ffffff" stroke-width="4" stroke-dasharray="4,4" />
                <text x="160" y="400" text-anchor="middle" fill="#ffffff" font-family="'Share Tech Mono', monospace" font-size="9" font-weight="bold">START / FINISH</text>

                <!-- Circuit de Monaco Track Layout Path (Glow underlay) -->
                <path d="M 160 360 
                         L 90 360 
                         Q 50 360 50 320 
                         L 50 240 
                         Q 50 200 80 180 
                         L 150 140 
                         Q 200 110 260 110 
                         L 320 110 
                         Q 380 110 400 150 
                         L 430 190 
                         Q 460 220 440 250 
                         L 410 270 
                         Q 380 290 410 320 
                         L 480 340 
                         Q 540 360 620 320 
                         L 720 220 
                         Q 760 180 730 130 
                         L 690 90 
                         Q 650 60 580 80 
                         L 520 100 
                         Q 480 110 490 140 
                         L 520 200 
                         Q 550 250 510 280 
                         L 360 330 
                         Q 280 360 160 360 Z" 
                      fill="none" stroke="rgba(0, 210, 190, 0.2)" stroke-width="18" stroke-linecap="round" stroke-linejoin="round" />

                <!-- Main Asphalt Ribbon -->
                <path d="M 160 360 
                         L 90 360 
                         Q 50 360 50 320 
                         L 50 240 
                         Q 50 200 80 180 
                         L 150 140 
                         Q 200 110 260 110 
                         L 320 110 
                         Q 380 110 400 150 
                         L 430 190 
                         Q 460 220 440 250 
                         L 410 270 
                         Q 380 290 410 320 
                         L 480 340 
                         Q 540 360 620 320 
                         L 720 220 
                         Q 760 180 730 130 
                         L 690 90 
                         Q 650 60 580 80 
                         L 520 100 
                         Q 480 110 490 140 
                         L 520 200 
                         Q 550 250 510 280 
                         L 360 330 
                         Q 280 360 160 360 Z" 
                      fill="none" stroke="#1b1b2a" stroke-width="12" stroke-linecap="round" stroke-linejoin="round" />

                <!-- Inner Racing Line (Neon Pulse) -->
                <path d="M 160 360 
                         L 90 360 
                         Q 50 360 50 320 
                         L 50 240 
                         Q 50 200 80 180 
                         L 150 140 
                         Q 200 110 260 110 
                         L 320 110 
                         Q 380 110 400 150 
                         L 430 190 
                         Q 460 220 440 250 
                         L 410 270 
                         Q 380 290 410 320 
                         L 480 340 
                         Q 540 360 620 320 
                         L 720 220 
                         Q 760 180 730 130 
                         L 690 90 
                         Q 650 60 580 80 
                         L 520 100 
                         Q 480 110 490 140 
                         L 520 200 
                         Q 550 250 510 280 
                         L 360 330 
                         Q 280 360 160 360 Z" 
                      fill="none" stroke="url(#trackAsphaltGrad)" stroke-width="2.5" stroke-dasharray="8,4" stroke-linecap="round" stroke-linejoin="round" />

                <!-- INTERACTIVE CORNER NODES -->
                <!-- 1. Turn 1: Sainte Dévote -->
                <g class="turn-node" onclick="selectTrackCorner(1)" transform="translate(60, 340)">
                    <circle r="14" fill="rgba(255, 24, 1, 0.3)" class="pulse-ring-track" />
                    <circle r="7" fill="#ff1801" />
                    <text x="0" y="24" text-anchor="middle" fill="#ff4757" font-family="'Orbitron', sans-serif" font-size="10" font-weight="bold">T1 STE DEVOTE</text>
                </g>

                <!-- 2. Turn 3: Massenet -->
                <g class="turn-node" onclick="selectTrackCorner(3)" transform="translate(130, 150)">
                    <circle r="12" fill="rgba(0, 210, 190, 0.25)" class="pulse-ring-track" />
                    <circle r="6" fill="#00d2be" />
                    <text x="0" y="-12" text-anchor="middle" fill="#00d2be" font-family="'Share Tech Mono', monospace" font-size="9" font-weight="bold">T3 MASSENET</text>
                </g>

                <!-- 3. Turn 4: Casino Square -->
                <g class="turn-node" onclick="selectTrackCorner(4)" transform="translate(290, 110)">
                    <circle r="14" fill="rgba(255, 24, 1, 0.3)" class="pulse-ring-track" />
                    <circle r="7" fill="#ff1801" />
                    <text x="0" y="-12" text-anchor="middle" fill="#ff4757" font-family="'Orbitron', sans-serif" font-size="10" font-weight="bold">T4 CASINO</text>
                </g>

                <!-- 4. Turn 6: Grand Hotel Hairpin -->
                <g class="turn-node" onclick="selectTrackCorner(6)" transform="translate(420, 260)">
                    <circle r="14" fill="rgba(241, 196, 15, 0.3)" class="pulse-ring-track" />
                    <circle r="7" fill="#f1c40f" />
                    <text x="0" y="22" text-anchor="middle" fill="#f1c40f" font-family="'Orbitron', sans-serif" font-size="10" font-weight="bold">T6 HAIRPIN</text>
                </g>

                <!-- 5. Turn 8: Portier -->
                <g class="turn-node" onclick="selectTrackCorner(8)" transform="translate(470, 340)">
                    <circle r="12" fill="rgba(0, 210, 190, 0.25)" class="pulse-ring-track" />
                    <circle r="6" fill="#00d2be" />
                    <text x="0" y="20" text-anchor="middle" fill="#00d2be" font-family="'Share Tech Mono', monospace" font-size="9" font-weight="bold">T8 PORTIER</text>
                </g>

                <!-- 6. Turn 10-11: Nouvelle Chicane -->
                <g class="turn-node" onclick="selectTrackCorner(10)" transform="translate(710, 140)">
                    <circle r="14" fill="rgba(255, 24, 1, 0.3)" class="pulse-ring-track" />
                    <circle r="7" fill="#ff1801" />
                    <text x="0" y="-12" text-anchor="middle" fill="#ff4757" font-family="'Orbitron', sans-serif" font-size="10" font-weight="bold">T10-11 CHICANE</text>
                </g>

                <!-- 7. Turn 12: Tabac -->
                <g class="turn-node" onclick="selectTrackCorner(12)" transform="translate(580, 80)">
                    <circle r="12" fill="rgba(0, 210, 190, 0.25)" class="pulse-ring-track" />
                    <circle r="6" fill="#00d2be" />
                    <text x="0" y="-12" text-anchor="middle" fill="#00d2be" font-family="'Share Tech Mono', monospace" font-size="9" font-weight="bold">T12 TABAC</text>
                </g>

                <!-- 8. Turn 15-16: Swimming Pool -->
                <g class="turn-node" onclick="selectTrackCorner(15)" transform="translate(520, 220)">
                    <circle r="14" fill="rgba(241, 196, 15, 0.3)" class="pulse-ring-track" />
                    <circle r="7" fill="#f1c40f" />
                    <text x="40" y="10" text-anchor="middle" fill="#f1c40f" font-family="'Orbitron', sans-serif" font-size="10" font-weight="bold">T15-16 POOL</text>
                </g>

                <!-- 9. Turn 18: La Rascasse -->
                <g class="turn-node" onclick="selectTrackCorner(18)" transform="translate(340, 330)">
                    <circle r="14" fill="rgba(255, 24, 1, 0.3)" class="pulse-ring-track" />
                    <circle r="7" fill="#ff1801" />
                    <text x="0" y="24" text-anchor="middle" fill="#ff4757" font-family="'Orbitron', sans-serif" font-size="10" font-weight="bold">T18 RASCASSE</text>
                </g>
            </svg>
        </div>

        <!-- RIGHT SIDE: CORNER INCIDENT DOSSIER HUD -->
        <div class="track-incident-panel" id="trackIncidentPanel">
            <div class="corner-tag-row">
                <span class="corner-id-badge" id="cornerIdBadge">TURN 01 // SAINTE DÉVOTE</span>
                <span class="corner-risk-badge" id="cornerRiskBadge">🔴 HIGH INCIDENT ZONE</span>
            </div>

            <h3 class="corner-title" id="cornerTitle">Sainte Dévote (Turn 1)</h3>
            <div class="corner-telemetry-meta" id="cornerSpeed">Apex Speed: 88 km/h // Gear: 2 // Braking: -4.8G</div>

            <div class="corner-matrix">
                <div class="c-matrix-row">
                    <span class="c-lbl">PRIMARY RISK:</span>
                    <span class="c-val" id="cornerRiskType">Turn 1 Squeeze & Runoff Track Limits</span>
                </div>
                <div class="c-matrix-row">
                    <span class="c-lbl">TOTAL INCIDENTS (2026):</span>
                    <span class="c-val highlight" id="cornerIncidentCount">5 Cases Investigated</span>
                </div>
                <div class="c-matrix-row">
                    <span class="c-lbl">ACTIVE PENALTIES:</span>
                    <span class="c-val" id="cornerActivePenalties">2 Imposed (+4 Superlicense Pts)</span>
                </div>
                <div class="c-matrix-row">
                    <span class="c-lbl">OFFICIAL ISC ARTICLE:</span>
                    <span class="c-val" id="cornerArticleRef">ISC App. L, Ch. IV, Art. 2(c)</span>
                </div>
            </div>

            <div class="corner-notes-box" id="cornerNotes">
                Heavy deceleration zone off the main straight. Car 16 and Car 55 investigated for Turn 1 entry contact during Lap 24 restart.
            </div>

            <div class="corner-actions">
                <button type="button" class="btn-track-filter" onclick="filterStewardTableByCorner()">
                    <span>🔍</span> FILTER INCIDENT REGISTER FOR THIS TURN
                </button>
            </div>
        </div>
    </div>
</div>

<!-- TRACK RADAR CSS -->
<style>
.f1-track-radar-card {
    background: linear-gradient(135deg, rgba(16, 16, 26, 0.96) 0%, rgba(10, 10, 18, 0.98) 100%);
    border: 1px solid rgba(255, 255, 255, 0.08);
    border-top: 3px solid var(--f1-red);
    border-radius: 12px;
    padding: 24px;
    margin-bottom: 28px;
    box-shadow: 0 15px 40px rgba(0, 0, 0, 0.6);
    backdrop-filter: blur(20px);
    font-family: 'Titillium Web', sans-serif;
}
.track-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    border-bottom: 1px solid rgba(255, 255, 255, 0.06);
    padding-bottom: 16px;
    margin-bottom: 20px;
    flex-wrap: wrap;
    gap: 12px;
}
.track-tag {
    font-family: 'Share Tech Mono', monospace;
    font-size: 11px;
    color: var(--f1-red);
    letter-spacing: 1.5px;
    display: block;
    margin-bottom: 4px;
}
.track-title {
    font-family: 'Orbitron', sans-serif;
    font-size: 17px;
    font-weight: 900;
    color: #ffffff;
    letter-spacing: 1px;
}
.track-meta-pills {
    display: flex;
    gap: 8px;
}
.track-pill {
    background: rgba(255, 255, 255, 0.04);
    border: 1px solid rgba(255, 255, 255, 0.12);
    color: #cbd5e1;
    font-family: 'Share Tech Mono', monospace;
    font-size: 10.5px;
    padding: 4px 10px;
    border-radius: 4px;
}

.track-stage {
    display: grid;
    grid-template-columns: 1.4fr 1fr;
    gap: 24px;
    align-items: center;
}
@media (max-width: 1000px) {
    .track-stage { grid-template-columns: 1fr; }
}

.track-canvas-container {
    background: rgba(4, 4, 8, 0.7);
    border: 1px solid rgba(255, 255, 255, 0.06);
    border-radius: 8px;
    padding: 16px;
    box-shadow: inset 0 0 30px rgba(0, 0, 0, 0.8);
}
.track-svg {
    width: 100%;
    height: auto;
    display: block;
}

.turn-node {
    cursor: pointer;
    transition: transform 0.2s ease;
}
.turn-node:hover {
    transform: scale(1.18);
}
.pulse-ring-track {
    animation: ringGlowTrack 2s infinite ease-out;
}
@keyframes ringGlowTrack {
    0% { r: 7; opacity: 1; }
    100% { r: 20; opacity: 0; }
}

/* Right Side Corner Incident Panel */
.track-incident-panel {
    background: rgba(0, 0, 0, 0.5);
    border: 1px solid rgba(255, 255, 255, 0.08);
    border-radius: 8px;
    padding: 20px;
}
.corner-tag-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 8px;
}
.corner-id-badge {
    font-family: 'Share Tech Mono', monospace;
    font-size: 11px;
    color: #64748b;
    letter-spacing: 1px;
}
.corner-risk-badge {
    font-family: 'Share Tech Mono', monospace;
    font-size: 10px;
    font-weight: 700;
    padding: 3px 8px;
    border-radius: 4px;
    background: rgba(255, 24, 1, 0.15);
    color: #ff4757;
    border: 1px solid #ff4757;
}

.corner-title {
    font-family: 'Orbitron', sans-serif;
    font-size: 16px;
    font-weight: 900;
    color: #ffffff;
    margin-bottom: 4px;
}
.corner-telemetry-meta {
    font-family: 'Share Tech Mono', monospace;
    font-size: 11px;
    color: #8c8c9e;
    margin-bottom: 14px;
}
.corner-matrix {
    border-top: 1px dashed rgba(255, 255, 255, 0.08);
    border-bottom: 1px dashed rgba(255, 255, 255, 0.08);
    padding: 10px 0;
    margin-bottom: 14px;
    font-size: 11.5px;
}
.c-matrix-row {
    display: flex;
    justify-content: space-between;
    padding: 3px 0;
}
.c-lbl { color: #64748b; font-family: 'Share Tech Mono', monospace; font-size: 10.5px; }
.c-val { color: #ffffff; font-weight: 700; }
.c-val.highlight { color: #00d2be; }

.corner-notes-box {
    font-size: 11.5px;
    line-height: 1.5;
    color: #cbd5e1;
    background: rgba(255, 255, 255, 0.02);
    padding: 10px;
    border-radius: 6px;
    border-left: 3px solid #ff1801;
    margin-bottom: 16px;
}
.btn-track-filter {
    background: linear-gradient(90deg, #ff1801 0%, #d63031 100%);
    color: #ffffff;
    font-family: 'Orbitron', sans-serif;
    font-size: 10.5px;
    font-weight: 900;
    letter-spacing: 1px;
    padding: 10px 14px;
    border-radius: 6px;
    border: none;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    box-shadow: 0 0 15px rgba(255, 24, 1, 0.4);
    transition: all 0.2s ease;
    width: 100%;
    justify-content: center;
}
.btn-track-filter:hover {
    box-shadow: 0 0 25px rgba(255, 24, 1, 0.8);
    transform: translateY(-1px);
}
</style>

<!-- TRACK RADAR INTERACTION SCRIPT -->
<script>
const f1TrackCornersData = {
    1: {
        id: 'TURN 01 // SAINTE DÉVOTE',
        title: 'Sainte Dévote (Turn 1)',
        speed: 'Apex Speed: 88 km/h // Gear: 2 // Braking: -4.8G',
        risk: '🔴 CRITICAL INCIDENT ZONE',
        riskType: 'Turn 1 Squeeze & Runoff Track Limits',
        incidents: '5 Cases Investigated',
        penalties: '2 Imposed (+4 Superlicense Pts)',
        article: 'ISC App. L, Ch. IV, Art. 2(c)',
        notes: 'Heavy deceleration zone off the main straight. Car 16 and Car 55 investigated for Turn 1 entry contact during Lap 24 restart.'
    },
    3: {
        id: 'TURN 03 // MASSENET',
        title: 'Massenet (Turn 3)',
        speed: 'Apex Speed: 145 km/h // Gear: 4 // Lateral G: 3.2G',
        risk: '🟡 MODERATE RISK ZONE',
        riskType: 'Understeer Barrier Impact',
        incidents: '2 Cases Investigated',
        penalties: '1 Reprimand Issued',
        article: 'Art 33.3 Sporting Regs',
        notes: 'Uphill long left curve around the Hotel de Paris. Yellow flags deployed during FP2 for barrier contact.'
    },
    4: {
        id: 'TURN 04 // CASINO SQUARE',
        title: 'Casino Square (Turn 4)',
        speed: 'Apex Speed: 125 km/h // Gear: 3 // Lateral G: 2.8G',
        risk: '🔴 HIGH INCIDENT ZONE',
        riskType: 'Overtaking under Yellow Flags',
        incidents: '4 Cases Investigated',
        penalties: '2 Time Penalties Imposed',
        article: 'Art 12.2.1.i ISC (Yellow Flags)',
        notes: 'Car 63 and Car 4 investigated for overtaking prior to green light signal board post-safety car restart.'
    },
    6: {
        id: 'TURN 06 // GRAND HOTEL HAIRPIN',
        title: 'Grand Hotel Hairpin (Turn 6)',
        speed: 'Apex Speed: 48 km/h (Slowest on Grid) // Gear: 1',
        risk: '🟡 TIGHT LOCK CONTACT',
        riskType: 'Front Wing Endplate Clipping',
        incidents: '6 Minor Contacts Lodged',
        penalties: 'No Further Action (Racing Incident)',
        article: 'ISC Driving Conduct',
        notes: 'Maximum steering wheel rotation lock (over 180°). Minor front wing endplate rubs logged without structural failure.'
    },
    8: {
        id: 'TURN 08 // PORTIER',
        title: 'Portier (Turn 8)',
        speed: 'Apex Speed: 72 km/h // Gear: 2 // Exit to Tunnel',
        risk: '🟡 MODERATE RISK ZONE',
        riskType: 'Apex Kerb Traction Loss',
        incidents: '2 Cases Investigated',
        penalties: '1 Grid Drop Imposed',
        article: 'Art 33.4 Impeding in Quali',
        notes: 'Critical corner determining top speed through the covered tunnel. Car 14 impeded on hot lap during Q3.'
    },
    10: {
        id: 'TURN 10-11 // NOUVELLE CHICANE',
        title: 'Nouvelle Chicane (Turn 10-11)',
        speed: 'Braking: 290 km/h -> 72 km/h // -5.2G Max Decel',
        risk: '🔴 CRITICAL INCIDENT ZONE',
        riskType: 'Chicane Cutting & Lasting Advantage',
        incidents: '8 Cases Investigated',
        penalties: '4 Time Penalties Imposed (+6 Pts)',
        article: 'Art 33.3 Persistent Track Limits',
        notes: 'Highest braking energy on circuit upon exiting the tunnel. Mandatory return through chicane bollards enforced.'
    },
    12: {
        id: 'TURN 12 // TABAC',
        title: 'Tabac (Turn 12)',
        speed: 'Apex Speed: 165 km/h // Gear: 4 // Lateral G: 3.5G',
        risk: '🟡 HIGH-SPEED PRECISION',
        riskType: 'Inside Armco Barrier Snag',
        incidents: '3 Cases Investigated',
        penalties: 'Official Reprimands',
        article: 'Safety Car Delta Infringement',
        notes: 'High-speed entry into the harbour complex. Virtual Safety Car delta time compliance monitored.'
    },
    15: {
        id: 'TURN 15-16 // SWIMMING POOL CHICANE',
        title: 'Louis Chiron / Swimming Pool (Turn 15-16)',
        speed: 'Apex Speed: 205 km/h // Gear: 5 // Kerb Impact',
        risk: '🔴 HIGH HAZARD ZONE',
        riskType: 'Aggressive Kerb Deflection & Suspension Fail',
        incidents: '6 Cases Investigated',
        penalties: '3 Disqualifications / Grid Drops',
        article: 'Art 3.5.1 Aerodynamic Flexing',
        notes: 'Extreme kerb hopping causes high load deflections. Floor wear and skid block plank ultrasonic tests triggered.'
    },
    18: {
        id: 'TURN 18 // LA RASCASSE',
        title: 'La Rascasse & Pit Entry (Turn 18-19)',
        speed: 'Apex Speed: 55 km/h // Gear: 1 // Pit Lane Entry',
        risk: '🔴 PIT ENTRY CROSSING VIOLATION',
        riskType: 'Crossing Solid White Pit Entry Line',
        incidents: '4 Cases Investigated',
        penalties: '2 €1,000 Fines + 5-Sec Penalties',
        article: 'Art 34.14 Pit Lane Entry Rules',
        notes: 'Drivers must not cross the solid white safety line separating the pit entry from the active racing track.'
    }
};

let currentSelectedTurn = 1;

function selectTrackCorner(turnNum) {
    const d = f1TrackCornersData[turnNum];
    if (!d) return;

    currentSelectedTurn = turnNum;

    document.getElementById('cornerIdBadge').textContent = d.id;
    document.getElementById('cornerTitle').textContent = d.title;
    document.getElementById('cornerSpeed').textContent = d.speed;
    document.getElementById('cornerRiskBadge').textContent = d.risk;
    document.getElementById('cornerRiskType').textContent = d.riskType;
    document.getElementById('cornerIncidentCount').textContent = d.incidents;
    document.getElementById('cornerActivePenalties').textContent = d.penalties;
    document.getElementById('cornerArticleRef').textContent = d.article;
    document.getElementById('cornerNotes').textContent = d.notes;

    if (typeof playGlobalF1Radio === 'function') {
        playGlobalF1Radio();
    }
}

function filterStewardTableByCorner() {
    const searchInput = document.getElementById('filterSearch');
    if (searchInput) {
        const turnText = "Turn " + currentSelectedTurn;
        searchInput.value = turnText;
        searchInput.dispatchEvent(new Event('input'));
        
        const table = document.querySelector('.table-responsive');
        if (table) {
            table.scrollIntoView({ behavior: 'smooth' });
        }
    } else {
        window.location.href = 'stewards_infringements.php';
    }
}
</script>
