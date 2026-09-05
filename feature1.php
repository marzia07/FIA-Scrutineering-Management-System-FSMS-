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

$msg = "";
$msg_type = "success";

// 1. DELETE CAR HANDLER
if (($_SERVER["REQUEST_METHOD"] ?? '') === "POST" && isset($_POST['delete_car_id'])) {
    $delete_id = intval($_POST['delete_car_id']);
    
    $del_stmt = $conn->prepare("DELETE FROM CARS WHERE car_id = ?");
    $del_stmt->bind_param("i", $delete_id);
    
    if ($del_stmt->execute()) {
        $msg = "CHASSIS #{$delete_id} DECOMMISSIONED: Vehicle successfully removed from Championship Grid.";
    } else {
        $msg = "DECOMMISSION ERROR: " . $conn->error;
        $msg_type = "error";
    }
}

// 2. ADD NEW CAR HANDLER
if (($_SERVER["REQUEST_METHOD"] ?? '') === "POST" && isset($_POST['add_car'])) {
    $team_id = intval($_POST['team_id']);
    $driver_id = !empty($_POST['driver_id']) ? intval($_POST['driver_id']) : null;
    $car_name = trim($_POST['car_name']);
    $chassis_number = trim($_POST['chassis_number']);
    $engine_type = trim($_POST['engine_type']);
    $category = trim($_POST['category']);

    if ($driver_id) {
        $insert_stmt = $conn->prepare("INSERT INTO CARS (team_id, driver_id, car_name, chassis_number, engine_type, category) VALUES (?, ?, ?, ?, ?, ?)");
        $insert_stmt->bind_param("iissss", $team_id, $driver_id, $car_name, $chassis_number, $engine_type, $category);
    } else {
        $insert_stmt = $conn->prepare("INSERT INTO CARS (team_id, car_name, chassis_number, engine_type, category) VALUES (?, ?, ?, ?, ?)");
        $insert_stmt->bind_param("issss", $team_id, $car_name, $chassis_number, $engine_type, $category);
    }
    
    if ($insert_stmt && $insert_stmt->execute()) {
        $msg = "HOMOLOGATION SUCCESSFUL: Vehicle chassis registered to Championship Grid.";
    } else {
        $msg = "HOMOLOGATION FAILED: " . $conn->error;
        $msg_type = "error";
    }
}

// 3. ADD NEW DRIVER HANDLER
if (($_SERVER["REQUEST_METHOD"] ?? '') === "POST" && isset($_POST['add_driver'])) {
    $full_name = trim($_POST['driver_name']);
    $nat = trim($_POST['nationality']);
    $dob = trim($_POST['date_of_birth'] ?? '2000-01-01');
    $lic = !empty($_POST['license_number']) ? trim($_POST['license_number']) : ('FIA-DRV-' . rand(100, 999));

    $chk_nat = $conn->query("SHOW COLUMNS FROM DRIVERS LIKE 'nationality'");
    $chk_lic = $conn->query("SHOW COLUMNS FROM DRIVERS LIKE 'license_number'");

    if ($chk_nat && $chk_nat->num_rows > 0 && $chk_lic && $chk_lic->num_rows > 0) {
        $driver_stmt = $conn->prepare("INSERT INTO DRIVERS (full_name, nationality, date_of_birth, license_number, country, status) VALUES (?, ?, ?, ?, ?, 'active')");
        $driver_stmt->bind_param("sssss", $full_name, $nat, $dob, $lic, $nat);
    } elseif ($chk_lic && $chk_lic->num_rows > 0) {
        $driver_stmt = $conn->prepare("INSERT INTO DRIVERS (full_name, country, date_of_birth, license_number, status) VALUES (?, ?, ?, ?, 'active')");
        $driver_stmt->bind_param("ssss", $full_name, $nat, $dob, $lic);
    } else {
        $driver_stmt = $conn->prepare("INSERT INTO DRIVERS (full_name, country, status) VALUES (?, ?, 'active')");
        $driver_stmt->bind_param("ss", $full_name, $nat);
    }

    if ($driver_stmt && $driver_stmt->execute()) {
        $msg = "FIA SUPER LICENSE ISSUED: Driver " . htmlspecialchars($full_name) . " entered into active registry.";
    } else {
        $msg = "REGISTRATION ERROR: " . $conn->error;
        $msg_type = "error";
    }
}

// Ensure at least 20 cars exist for a complete starting grid
$chk_cars_cnt = $conn->query("SELECT COUNT(*) as cnt FROM CARS");
$cars_total = ($chk_cars_cnt) ? intval($chk_cars_cnt->fetch_assoc()['cnt']) : 0;
if ($cars_total < 20) {
    // Add 2nd car for team 10 if needed
    $conn->query("INSERT IGNORE INTO CARS (car_id, team_id, car_name, chassis_number, engine_type, category) VALUES (20, 10, 'Haas VF-24 #2', 'CHASSIS-H-02', 'Ferrari 066/10 V6 Turbo', 'Formula 1')");
}

$teams = $conn->query("SELECT team_id, team_name FROM TEAMS ORDER BY team_name ASC");
$drivers_dropdown = $conn->query("SELECT * FROM DRIVERS ORDER BY full_name ASC");

$cars_query = "SELECT c.*, 
                      t.team_name, t.country as team_country,
                      d.full_name as driver_name, d.country as driver_nat
               FROM CARS c
               JOIN TEAMS t ON c.team_id = t.team_id
               LEFT JOIN DRIVERS d ON c.driver_id = d.driver_id
               ORDER BY c.car_id ASC";
$cars_res = $conn->query($cars_query);
$all_cars = [];
if ($cars_res) {
    while($row = $cars_res->fetch_assoc()) {
        $all_cars[] = $row;
    }
}

$drivers_list = $conn->query("SELECT d.*, c.car_name, t.team_name 
                              FROM DRIVERS d 
                              LEFT JOIN CARS c ON d.driver_id = c.driver_id 
                              LEFT JOIN TEAMS t ON c.team_id = t.team_id 
                              ORDER BY d.driver_id DESC");

/**
 * Team Metadata & Color Styling Palettes
 */
function get_team_palette($team_name) {
    $t = strtolower($team_name);
    if (strpos($t, 'red bull') !== false) {
        return [
            'primary' => '#061329',
            'secondary' => '#cc1e4a',
            'accent' => '#fcd116',
            'glow' => '#cc1e4a',
            'border' => '#1e3d61',
            'logo' => '🐂',
            'tag' => 'RBR',
            'driver1' => 'Max Verstappen #1',
            'driver2' => 'Sergio Perez #11'
        ];
    } elseif (strpos($t, 'mercedes') !== false) {
        return [
            'primary' => '#0c1215',
            'secondary' => '#00a19c',
            'accent' => '#c0c0c0',
            'glow' => '#00d2be',
            'border' => '#00a19c',
            'logo' => '⭐',
            'tag' => 'MERC',
            'driver1' => 'Lewis Hamilton #44',
            'driver2' => 'George Russell #63'
        ];
    } elseif (strpos($t, 'ferrari') !== false) {
        return [
            'primary' => '#c80000',
            'secondary' => '#ffe500',
            'accent' => '#111111',
            'glow' => '#ff1801',
            'border' => '#e80020',
            'logo' => '🐎',
            'tag' => 'FER',
            'driver1' => 'Charles Leclerc #16',
            'driver2' => 'Carlos Sainz #55'
        ];
    } elseif (strpos($t, 'mclaren') !== false) {
        return [
            'primary' => '#ff8000',
            'secondary' => '#47c7fc',
            'accent' => '#111111',
            'glow' => '#ff8000',
            'border' => '#ff8000',
            'logo' => '🧡',
            'tag' => 'MCL',
            'driver1' => 'Lando Norris #4',
            'driver2' => 'Oscar Piastri #81'
        ];
    } elseif (strpos($t, 'aston martin') !== false) {
        return [
            'primary' => '#004838',
            'secondary' => '#cedc00',
            'accent' => '#00241b',
            'glow' => '#229971',
            'border' => '#229971',
            'logo' => '🦅',
            'tag' => 'AMR',
            'driver1' => 'Fernando Alonso #14',
            'driver2' => 'Lance Stroll #18'
        ];
    } elseif (strpos($t, 'alpine') !== false) {
        return [
            'primary' => '#0078b6',
            'secondary' => '#ff87bc',
            'accent' => '#111111',
            'glow' => '#ff87bc',
            'border' => '#0093cc',
            'logo' => '🏔️',
            'tag' => 'ALP',
            'driver1' => 'Pierre Gasly #10',
            'driver2' => 'Esteban Ocon #31'
        ];
    } elseif (strpos($t, 'williams') !== false) {
        return [
            'primary' => '#041e42',
            'secondary' => '#00a0de',
            'accent' => '#ffffff',
            'glow' => '#00a0de',
            'border' => '#00a0de',
            'logo' => '🔷',
            'tag' => 'WIL',
            'driver1' => 'Alex Albon #23',
            'driver2' => 'Logan Sargeant #2'
        ];
    } elseif (strpos($t, 'rb') !== false || strpos($t, 'cash app') !== false || strpos($t, 'alpha') !== false) {
        return [
            'primary' => '#1434cb',
            'secondary' => '#ffffff',
            'accent' => '#dc0000',
            'glow' => '#6692ff',
            'border' => '#6692ff',
            'logo' => '⚡',
            'tag' => 'VCARB',
            'driver1' => 'Yuki Tsunoda #22',
            'driver2' => 'Daniel Ricciardo #3'
        ];
    } elseif (strpos($t, 'sauber') !== false || strpos($t, 'kick') !== false) {
        return [
            'primary' => '#111111',
            'secondary' => '#52e252',
            'accent' => '#00ff41',
            'glow' => '#52e252',
            'border' => '#52e252',
            'logo' => '🟢',
            'tag' => 'KICK',
            'driver1' => 'Valtteri Bottas #77',
            'driver2' => 'Zhou Guanyu #24'
        ];
    } elseif (strpos($t, 'haas') !== false) {
        return [
            'primary' => '#1f2022',
            'secondary' => '#e10600',
            'accent' => '#ffffff',
            'glow' => '#e10600',
            'border' => '#b6babd',
            'logo' => '🇺🇸',
            'tag' => 'HAAS',
            'driver1' => 'Nico Hulkenberg #27',
            'driver2' => 'Kevin Magnussen #20'
        ];
    }
    return [
        'primary' => '#1a1a27',
        'secondary' => '#ff1801',
        'accent' => '#00d2be',
        'glow' => '#00d2be',
        'border' => '#33334d',
        'logo' => '🏎️',
        'tag' => 'F1',
        'driver1' => 'Reserve Pilot #01',
        'driver2' => 'Reserve Pilot #02'
    ];
}

/**
 * Top-Down Vector SVG Formula 1 Car Livery Generator
 */
function render_f1_car_svg($team_name, $car_number = "1", $tyre = "soft", $is_interactive = false) {
    $pal = get_team_palette($team_name);
    $primary = $pal['primary'];
    $secondary = $pal['secondary'];
    $accent = $pal['accent'];
    $glow = $pal['glow'];

    // Tyre compound stripe color
    $tyre_colors = [
        'soft' => '#ff1801',
        'medium' => '#ffd32a',
        'hard' => '#ffffff',
        'inter' => '#0be881',
        'wet' => '#0fbcf9'
    ];
    $t_color = $tyre_colors[strtolower($tyre)] ?? '#ff1801';

    $svg = '<svg viewBox="0 0 160 380" class="f1-car-svg" xmlns="http://www.w3.org/2000/svg" role="img" aria-label="F1 Car Livery">';
    $svg .= '<defs>';
    $svg .= '  <linearGradient id="bodyGrad_' . md5($team_name . $car_number) . '" x1="0%" y1="0%" x2="100%" y2="0%">';
    $svg .= '    <stop offset="0%" stop-color="' . $primary . '" stop-opacity="0.95"/>';
    $svg .= '    <stop offset="50%" stop-color="' . $secondary . '" stop-opacity="0.95"/>';
    $svg .= '    <stop offset="100%" stop-color="' . $primary . '" stop-opacity="0.95"/>';
    $svg .= '  </linearGradient>';
    $svg .= '  <linearGradient id="carbonGrad" x1="0%" y1="0%" x2="100%" y2="100%">';
    $svg .= '    <stop offset="0%" stop-color="#141419"/>';
    $svg .= '    <stop offset="100%" stop-color="#08080c"/>';
    $svg .= '  </linearGradient>';
    $svg .= '  <filter id="laserGlow_' . md5($team_name) . '" x="-20%" y="-20%" width="140%" height="140%">';
    $svg .= '    <feGaussianBlur stdDeviation="3" result="blur"/>';
    $svg .= '    <feComposite in="SourceGraphic" in2="blur" operator="over"/>';
    $svg .= '  </filter>';
    $svg .= '</defs>';

    // Shadow on asphalt
    $svg .= '<ellipse cx="80" cy="195" rx="55" ry="165" fill="rgba(0,0,0,0.65)" filter="blur(8px)"/>';

    // 1. Underfloor Diffuser & Floor Edge Aerodynamics
    $svg .= '<path d="M 38 130 L 22 220 L 32 320 L 128 320 L 138 220 L 122 130 Z" fill="url(#carbonGrad)" stroke="' . $glow . '" stroke-width="1.2" stroke-opacity="0.4"/>';
    $svg .= '<rect x="20" y="210" width="8" height="60" rx="2" fill="' . $secondary . '" opacity="0.8"/>';
    $svg .= '<rect x="132" y="210" width="8" height="60" rx="2" fill="' . $secondary . '" opacity="0.8"/>';

    // 2. Suspension Wishbones (Front & Rear)
    $svg .= '<line x1="80" y1="95" x2="25" y2="90" stroke="#444" stroke-width="3.5" stroke-linecap="round"/>';
    $svg .= '<line x1="80" y1="105" x2="25" y2="100" stroke="#222" stroke-width="2.5"/>';
    $svg .= '<line x1="80" y1="95" x2="135" y2="90" stroke="#444" stroke-width="3.5" stroke-linecap="round"/>';
    $svg .= '<line x1="80" y1="105" x2="135" y2="100" stroke="#222" stroke-width="2.5"/>';

    $svg .= '<line x1="80" y1="285" x2="25" y2="285" stroke="#444" stroke-width="4" stroke-linecap="round"/>';
    $svg .= '<line x1="80" y1="295" x2="25" y2="295" stroke="#222" stroke-width="3"/>';
    $svg .= '<line x1="80" y1="285" x2="135" y2="285" stroke="#444" stroke-width="4" stroke-linecap="round"/>';
    $svg .= '<line x1="80" y1="295" x2="135" y2="295" stroke="#222" stroke-width="3"/>';

    // 3. Four Pirelli Wheels with Compound Stripe
    // Front Left
    $svg .= '<rect x="10" y="68" width="22" height="52" rx="6" fill="#111116" stroke="#2b2b36" stroke-width="1.5"/>';
    $svg .= '<rect x="14" y="74" width="14" height="40" rx="3" fill="#1a1a24"/>';
    $svg .= '<ellipse cx="21" cy="94" rx="4" ry="14" fill="none" stroke="' . $t_color . '" stroke-width="2.2"/>';
    // Front Right
    $svg .= '<rect x="128" y="68" width="22" height="52" rx="6" fill="#111116" stroke="#2b2b36" stroke-width="1.5"/>';
    $svg .= '<rect x="132" y="74" width="14" height="40" rx="3" fill="#1a1a24"/>';
    $svg .= '<ellipse cx="139" cy="94" rx="4" ry="14" fill="none" stroke="' . $t_color . '" stroke-width="2.2"/>';
    // Rear Left
    $svg .= '<rect x="6" y="260" width="26" height="60" rx="7" fill="#111116" stroke="#2b2b36" stroke-width="1.5"/>';
    $svg .= '<rect x="11" y="266" width="16" height="48" rx="4" fill="#1a1a24"/>';
    $svg .= '<ellipse cx="19" cy="290" rx="5" ry="18" fill="none" stroke="' . $t_color . '" stroke-width="2.5"/>';
    // Rear Right
    $svg .= '<rect x="128" y="260" width="26" height="60" rx="7" fill="#111116" stroke="#2b2b36" stroke-width="1.5"/>';
    $svg .= '<rect x="133" y="266" width="16" height="48" rx="4" fill="#1a1a24"/>';
    $svg .= '<ellipse cx="141" cy="290" rx="5" ry="18" fill="none" stroke="' . $t_color . '" stroke-width="2.5"/>';

    // 4. Front Wing Assembly & Cascade Elements
    $svg .= '<path d="M 12 40 C 45 28, 115 28, 148 40 L 144 54 C 115 44, 45 44, 16 54 Z" fill="' . $primary . '" stroke="' . $secondary . '" stroke-width="1.5"/>';
    $svg .= '<path d="M 18 32 C 50 20, 110 20, 142 32 L 138 38 C 110 26, 50 26, 22 38 Z" fill="' . $secondary . '" opacity="0.9"/>';
    // Endplates
    $svg .= '<rect x="10" y="25" width="4" height="35" rx="1.5" fill="' . $secondary . '"/>';
    $svg .= '<rect x="146" y="25" width="4" height="35" rx="1.5" fill="' . $secondary . '"/>';

    // 5. Main Monocoque Chassis & Nose Cone
    $svg .= '<path d="M 72 34 L 88 34 L 92 110 L 118 150 L 116 260 L 98 290 L 62 290 L 44 260 L 42 150 L 68 110 Z" fill="url(#bodyGrad_' . md5($team_name . $car_number) . ')" stroke="' . $glow . '" stroke-width="1.2"/>';

    // Nose Cone Highlight Stripe
    $svg .= '<polygon points="76,38 84,38 86,105 74,105" fill="' . $accent . '" opacity="0.85"/>';

    // Driver Number on Nose
    $svg .= '<circle cx="80" cy="72" r="9" fill="#000000" stroke="' . $secondary . '" stroke-width="1.5"/>';
    $svg .= '<text x="80" y="76" font-family="Orbitron, sans-serif" font-size="10" font-weight="900" fill="#ffffff" text-anchor="middle">' . htmlspecialchars($car_number) . '</text>';

    // 6. Sidepod Air Inlets
    $svg .= '<path d="M 44 150 Q 55 155 58 185 L 46 220 Z" fill="#050508" stroke="' . $glow . '" stroke-width="1"/>';
    $svg .= '<path d="M 116 150 Q 105 155 102 185 L 114 220 Z" fill="#050508" stroke="' . $glow . '" stroke-width="1"/>';

    // 7. Cockpit Opening, Halo & Driver Helmet
    $svg .= '<ellipse cx="80" cy="148" rx="14" ry="24" fill="#05050a" stroke="#222" stroke-width="2"/>';
    // Driver Helmet
    $svg .= '<circle cx="80" cy="148" r="9" fill="' . $secondary . '" stroke="#ffffff" stroke-width="1.5"/>';
    $svg .= '<path d="M 73 144 Q 80 141 87 144 L 85 149 Q 80 147 75 149 Z" fill="#111111"/>'; // Visor
    // Halo Structure
    $svg .= '<path d="M 72 165 C 70 135, 90 135, 88 165" fill="none" stroke="#2c2c36" stroke-width="3.5" stroke-linecap="round"/>';
    $svg .= '<line x1="80" y1="126" x2="80" y2="138" stroke="#2c2c36" stroke-width="3.5" stroke-linecap="round"/>';

    // 8. Airbox / Engine Intake & Camera Pod
    $svg .= '<ellipse cx="80" cy="180" rx="7" ry="9" fill="#000000" stroke="' . $glow . '" stroke-width="1.5"/>';
    $svg .= '<rect x="77" y="184" width="6" height="7" rx="1.5" fill="' . ($car_number == '1' || $car_number == '16' || $car_number == '44' || $car_number == '4' ? '#ffee00' : '#111111') . '"/>';

    // 9. Engine Cover Shark Fin
    $svg .= '<path d="M 78 190 L 82 190 L 81 270 L 79 270 Z" fill="' . $secondary . '" opacity="0.95"/>';

    // 10. Rear Wing Assembly & DRS Flap
    $svg .= '<rect x="24" y="325" width="112" height="22" rx="3" fill="' . $primary . '" stroke="' . $secondary . '" stroke-width="1.5"/>';
    $svg .= '<rect x="30" y="332" width="100" height="8" rx="2" fill="' . $secondary . '" class="drs-flap-element"/>';
    // Rear Wing Endplates
    $svg .= '<rect x="22" y="318" width="5" height="34" rx="2" fill="' . $secondary . '"/>';
    $svg .= '<rect x="133" y="318" width="5" height="34" rx="2" fill="' . $secondary . '"/>';

    // 11. Rear Safety Flashing Rain Light (Active LED)
    $svg .= '<circle cx="80" cy="354" r="3.5" fill="#ff1801" class="f1-rear-beacon" filter="url(#laserGlow_' . md5($team_name) . ')"/>';

    $svg .= '</svg>';
    return $svg;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>FSMS | Interactive F1 Starting Grid & Chassis Registry</title>
    <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@500;700;900&family=Share+Tech+Mono&family=Titillium+Web:wght@300;400;600;700;900&display=swap" rel="stylesheet">
    <style>
        :root {
            --f1-red: #ff1801;
            --f1-cyan: #00d2be;
            --f1-gold: #ffd32a;
            --f1-dark: #06060c;
            --f1-card: rgba(14, 14, 24, 0.95);
            --asphalt-dark: #12121c;
            --asphalt-light: #1c1c2b;
        }
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            background-color: var(--f1-dark);
            color: #ffffff;
            min-height: 100vh;
            padding: 24px;
            position: relative;
            font-family: 'Titillium Web', sans-serif;
            overflow-x: hidden;
        }

        /* Ambient Scanning Glows */
        .ambient-glow-red {
            position: absolute;
            width: 800px;
            height: 800px;
            background: radial-gradient(circle, rgba(255, 24, 1, 0.15) 0%, transparent 65%);
            top: -150px;
            right: -150px;
            pointer-events: none;
            z-index: 0;
            filter: blur(60px);
        }
        .ambient-glow-cyan {
            position: absolute;
            width: 800px;
            height: 800px;
            background: radial-gradient(circle, rgba(0, 210, 190, 0.12) 0%, transparent 65%);
            bottom: -150px;
            left: -150px;
            pointer-events: none;
            z-index: 0;
            filter: blur(60px);
        }

        /* 1. Header Ribbon */
        .grid-header-ribbon {
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: linear-gradient(90deg, rgba(20, 20, 35, 0.95) 0%, rgba(10, 10, 18, 0.98) 100%);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-left: 5px solid var(--f1-red);
            border-radius: 10px;
            padding: 16px 24px;
            margin-bottom: 24px;
            position: relative;
            z-index: 10;
            box-shadow: 0 10px 30px rgba(0,0,0,0.6);
            backdrop-filter: blur(20px);
        }
        .ribbon-left {
            display: flex;
            align-items: center;
            gap: 16px;
        }
        .live-beacon {
            display: flex;
            align-items: center;
            gap: 8px;
            font-family: 'Share Tech Mono', monospace;
            font-size: 11px;
            color: #ff4757;
            font-weight: 700;
            letter-spacing: 2px;
            text-transform: uppercase;
        }
        .pulse-core {
            width: 10px;
            height: 10px;
            background: var(--f1-red);
            border-radius: 50%;
            box-shadow: 0 0 12px var(--f1-red);
            animation: beaconPulse 1.2s infinite alternate;
        }
        @keyframes beaconPulse { 0% { opacity: 0.3; transform: scale(0.9); } 100% { opacity: 1; transform: scale(1.3); } }
        .ribbon-title {
            font-family: 'Orbitron', sans-serif;
            font-size: 20px;
            font-weight: 900;
            letter-spacing: 1.5px;
            text-shadow: 0 0 15px rgba(255,255,255,0.2);
        }

        /* 2. FIA 5-Red-Lights Race Start Gantry */
        .start-gantry-deck {
            background: linear-gradient(180deg, #101018 0%, #08080f 100%);
            border: 2px solid #28283c;
            border-radius: 12px;
            padding: 20px 28px;
            margin-bottom: 28px;
            position: relative;
            z-index: 10;
            box-shadow: 0 15px 40px rgba(0,0,0,0.8), inset 0 0 20px rgba(0,0,0,0.5);
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 20px;
        }
        .gantry-lights-housing {
            display: flex;
            align-items: center;
            gap: 14px;
            background: #050508;
            padding: 12px 20px;
            border-radius: 10px;
            border: 1px solid #1a1a27;
            box-shadow: inset 0 0 15px rgba(0,0,0,0.9);
        }
        .light-pod {
            width: 32px;
            height: 64px;
            background: #111118;
            border: 2px solid #222233;
            border-radius: 6px;
            display: flex;
            flex-direction: column;
            justify-content: space-around;
            align-items: center;
            padding: 6px 0;
            box-shadow: inset 0 2px 5px rgba(0,0,0,0.8);
        }
        .light-bulb {
            width: 18px;
            height: 18px;
            background: #200000;
            border-radius: 50%;
            border: 1px solid #330000;
            transition: all 0.15s ease;
        }
        .light-bulb.active {
            background: #ff0000;
            box-shadow: 0 0 20px #ff0000, 0 0 40px #ff1801;
            border-color: #ffcccc;
        }
        .gantry-actions {
            display: flex;
            gap: 12px;
            align-items: center;
        }
        .btn-gantry-launch {
            background: linear-gradient(135deg, #ff1801 0%, #a80000 100%);
            border: none;
            color: #ffffff;
            font-family: 'Orbitron', sans-serif;
            font-size: 12px;
            font-weight: 900;
            padding: 14px 24px;
            border-radius: 6px;
            cursor: pointer;
            letter-spacing: 1.5px;
            display: inline-flex;
            align-items: center;
            gap: 10px;
            box-shadow: 0 0 25px rgba(255, 24, 1, 0.5);
            transition: all 0.25s ease;
        }
        .btn-gantry-launch:hover {
            transform: translateY(-2px);
            box-shadow: 0 0 40px rgba(255, 24, 1, 0.8);
        }
        .btn-gantry-reset {
            background: rgba(255, 255, 255, 0.08);
            border: 1px solid rgba(255, 255, 255, 0.2);
            color: #cbd5e1;
            font-family: 'Orbitron', sans-serif;
            font-size: 11px;
            font-weight: 700;
            padding: 14px 20px;
            border-radius: 6px;
            cursor: pointer;
            transition: all 0.2s;
        }
        .btn-gantry-reset:hover {
            background: rgba(255, 255, 255, 0.15);
            color: #ffffff;
            border-color: var(--f1-cyan);
        }
        .reaction-hud {
            font-family: 'Share Tech Mono', monospace;
            font-size: 13px;
            color: var(--f1-cyan);
            background: rgba(0, 210, 190, 0.08);
            border: 1px solid rgba(0, 210, 190, 0.3);
            padding: 10px 18px;
            border-radius: 6px;
            min-width: 220px;
            text-align: center;
        }

        /* 3. Interactive View & Filter Controls */
        .grid-control-toolbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: var(--f1-card);
            border: 1px solid rgba(255, 255, 255, 0.08);
            border-radius: 10px;
            padding: 14px 20px;
            margin-bottom: 24px;
            position: relative;
            z-index: 10;
            flex-wrap: wrap;
            gap: 14px;
        }
        .view-mode-tabs {
            display: flex;
            gap: 8px;
        }
        .tab-btn {
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.1);
            color: #94a3b8;
            font-family: 'Orbitron', sans-serif;
            font-size: 11px;
            font-weight: 700;
            padding: 10px 18px;
            border-radius: 6px;
            cursor: pointer;
            transition: all 0.2s;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .tab-btn.active, .tab-btn:hover {
            background: rgba(0, 210, 190, 0.15);
            border-color: var(--f1-cyan);
            color: #ffffff;
            box-shadow: 0 0 15px rgba(0, 210, 190, 0.3);
        }
        .filter-search-box {
            display: flex;
            gap: 12px;
            align-items: center;
            flex-grow: 1;
            max-width: 450px;
        }
        .search-input-field {
            width: 100%;
            background: #080810;
            border: 1px solid #222233;
            border-radius: 6px;
            padding: 10px 14px;
            color: #ffffff;
            font-family: 'Titillium Web', sans-serif;
            font-size: 13px;
            outline: none;
            transition: border-color 0.2s;
        }
        .search-input-field:focus {
            border-color: var(--f1-cyan);
            box-shadow: 0 0 15px rgba(0, 210, 190, 0.3);
        }

        /* 4. AUTHENTIC F1 ASPHALT STARTING GRID TRACK */
        .track-grid-container {
            position: relative;
            z-index: 10;
            background: #0d0d15;
            background-image: 
                radial-gradient(rgba(255, 255, 255, 0.03) 1px, transparent 1px),
                linear-gradient(to bottom, #11111a, #090910);
            background-size: 20px 20px, 100% 100%;
            border: 2px solid #232338;
            border-radius: 16px;
            padding: 40px 30px 60px 30px;
            margin-bottom: 36px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.9), inset 0 0 60px rgba(0,0,0,0.7);
            overflow: hidden;
        }
        /* Kerbs & Racing Line Markings */
        .track-grid-container::before {
            content: '';
            position: absolute;
            top: 0; bottom: 0; left: 10px; width: 12px;
            background: repeating-linear-gradient(0deg, #ff1801, #ff1801 24px, #ffffff 24px, #ffffff 48px);
            border-radius: 4px;
            opacity: 0.8;
        }
        .track-grid-container::after {
            content: '';
            position: absolute;
            top: 0; bottom: 0; right: 10px; width: 12px;
            background: repeating-linear-gradient(0deg, #ff1801, #ff1801 24px, #ffffff 24px, #ffffff 48px);
            border-radius: 4px;
            opacity: 0.8;
        }
        .start-finish-line {
            position: absolute;
            top: 30px;
            left: 24px;
            right: 24px;
            height: 18px;
            background: repeating-linear-gradient(90deg, #ffffff 0px, #ffffff 18px, #111111 18px, #111111 36px);
            border: 1px solid #444;
            box-shadow: 0 0 15px rgba(255,255,255,0.4);
            border-radius: 2px;
        }
        .start-line-label {
            position: absolute;
            top: -24px;
            left: 50%;
            transform: translateX(-50%);
            font-family: 'Orbitron', sans-serif;
            font-size: 11px;
            font-weight: 900;
            color: #ffd32a;
            letter-spacing: 3px;
            text-shadow: 0 0 10px rgba(255, 211, 42, 0.8);
        }

        /* Staggered Grid Slots */
        .starting-grid-matrix {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 48px 36px;
            margin-top: 40px;
            position: relative;
        }
        .grid-slot-row-left {
            display: flex;
            justify-content: flex-end;
            padding-right: 20px;
        }
        .grid-slot-row-right {
            display: flex;
            justify-content: flex-start;
            padding-left: 20px;
            margin-top: 60px; /* Staggered formation */
        }

        .grid-box-card {
            background: linear-gradient(135deg, rgba(22, 22, 36, 0.95) 0%, rgba(14, 14, 22, 0.98) 100%);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 12px;
            width: 100%;
            max-width: 460px;
            padding: 18px 22px;
            position: relative;
            cursor: pointer;
            transition: all 0.35s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            box-shadow: 0 10px 30px rgba(0,0,0,0.6);
            backdrop-filter: blur(15px);
        }
        /* Yellow Asphalt Grid Box Marking */
        .grid-box-card::before {
            content: '';
            position: absolute;
            top: -6px; left: -6px; right: -6px; bottom: -6px;
            border: 2px dashed rgba(255, 211, 42, 0.3);
            border-radius: 16px;
            pointer-events: none;
            transition: all 0.3s;
        }
        .grid-box-card:hover {
            transform: translateY(-8px) scale(1.02);
            border-color: var(--f1-cyan);
            box-shadow: 0 20px 45px rgba(0, 210, 190, 0.25), 0 0 25px rgba(0, 210, 190, 0.4);
        }
        .grid-box-card:hover::before {
            border-color: var(--f1-cyan);
            box-shadow: 0 0 15px rgba(0, 210, 190, 0.5);
        }

        .grid-slot-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 1px solid rgba(255, 255, 255, 0.08);
            padding-bottom: 10px;
            margin-bottom: 14px;
        }
        .pos-badge {
            font-family: 'Orbitron', sans-serif;
            font-size: 13px;
            font-weight: 900;
            padding: 4px 10px;
            border-radius: 4px;
            background: rgba(255, 255, 255, 0.08);
            color: #ffffff;
            letter-spacing: 1px;
            display: flex;
            align-items: center;
            gap: 6px;
        }
        .pos-badge.pole-pos {
            background: linear-gradient(135deg, #ffd32a 0%, #e58e26 100%);
            color: #06060c;
            box-shadow: 0 0 15px rgba(255, 211, 42, 0.6);
        }
        .team-tag-pill {
            font-family: 'Share Tech Mono', monospace;
            font-size: 11px;
            font-weight: 700;
            letter-spacing: 1px;
            padding: 3px 8px;
            border-radius: 4px;
        }

        .grid-slot-body {
            display: flex;
            align-items: center;
            gap: 20px;
        }
        .car-svg-preview-wrap {
            width: 105px;
            height: 185px;
            flex-shrink: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
            transition: transform 0.4s ease;
        }
        .grid-box-card:hover .car-svg-preview-wrap {
            transform: scale(1.08) translateY(-4px);
        }
        .f1-car-svg {
            width: 100%;
            height: 100%;
            filter: drop-shadow(0 8px 15px rgba(0,0,0,0.8));
        }

        .slot-telemetry-info {
            flex-grow: 1;
            display: flex;
            flex-direction: column;
            gap: 6px;
            font-family: 'Share Tech Mono', monospace;
            font-size: 11.5px;
        }
        .pilot-name {
            font-family: 'Orbitron', sans-serif;
            font-size: 15px;
            font-weight: 800;
            color: #ffffff;
            margin-bottom: 2px;
        }
        .team-title {
            color: #8c8c9e;
            font-size: 12px;
            margin-bottom: 6px;
        }
        .spec-item {
            display: flex;
            justify-content: space-between;
            border-bottom: 1px dashed rgba(255, 255, 255, 0.06);
            padding: 3px 0;
        }
        .spec-label { color: #6e6e85; }
        .spec-val { color: #e2e8f0; font-weight: 700; }

        /* Animated Launch Class */
        .car-launch-animated {
            animation: launchDownStraight 2.2s cubic-bezier(0.25, 1, 0.5, 1) forwards;
        }
        @keyframes launchDownStraight {
            0% { transform: translateY(0); filter: drop-shadow(0 0 0 transparent); }
            30% { transform: translateY(-40px); filter: drop-shadow(0 20px 25px rgba(255, 24, 1, 0.8)); }
            100% { transform: translateY(-380px); filter: drop-shadow(0 40px 40px rgba(0, 210, 190, 0.9)); opacity: 0.1; }
        }

        /* 5. INTERACTIVE 3D/WIND TUNNEL GARAGE SHOWCASE */
        .wind-tunnel-showcase {
            background: linear-gradient(135deg, rgba(16, 16, 28, 0.98) 0%, rgba(8, 8, 16, 0.98) 100%);
            border: 2px solid rgba(0, 210, 190, 0.3);
            border-radius: 14px;
            padding: 28px;
            margin-bottom: 36px;
            position: relative;
            z-index: 10;
            box-shadow: 0 15px 50px rgba(0,0,0,0.8), 0 0 30px rgba(0, 210, 190, 0.15);
        }
        .showcase-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 1px solid rgba(255, 255, 255, 0.08);
            padding-bottom: 16px;
            margin-bottom: 24px;
        }
        .showcase-title {
            font-family: 'Orbitron', sans-serif;
            font-size: 18px;
            font-weight: 900;
            color: var(--f1-cyan);
            letter-spacing: 2px;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .showcase-grid {
            display: grid;
            grid-template-columns: 1fr 1.2fr 1fr;
            gap: 28px;
            align-items: center;
        }
        .wind-tunnel-viewport {
            background: #05050a;
            border: 1px solid #1a1a2e;
            border-radius: 10px;
            height: 380px;
            position: relative;
            overflow: hidden;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: inset 0 0 30px rgba(0,0,0,0.9);
        }
        /* Wind Tunnel Streamlines */
        .streamline-overlay {
            position: absolute;
            top: 0; left: 0; width: 100%; height: 100%;
            pointer-events: none;
        }
        .flow-line {
            stroke: rgba(0, 210, 190, 0.5);
            stroke-width: 1.5;
            stroke-dasharray: 12, 18;
            animation: flowMove 1.4s infinite linear;
        }
        @keyframes flowMove {
            0% { stroke-dashoffset: 0; }
            100% { stroke-dashoffset: -60; }
        }
        .aero-drag-zone {
            position: absolute;
            bottom: 20px;
            left: 50%;
            transform: translateX(-50%);
            background: rgba(0,0,0,0.7);
            border: 1px solid var(--f1-cyan);
            padding: 6px 14px;
            border-radius: 20px;
            font-family: 'Share Tech Mono', monospace;
            font-size: 11px;
            color: #ffffff;
            display: flex;
            gap: 12px;
        }

        .showcase-controls-panel {
            display: flex;
            flex-direction: column;
            gap: 16px;
        }
        .control-widget-card {
            background: rgba(255, 255, 255, 0.03);
            border: 1px solid rgba(255, 255, 255, 0.08);
            border-radius: 8px;
            padding: 16px;
        }
        .widget-title {
            font-family: 'Orbitron', sans-serif;
            font-size: 11px;
            font-weight: 700;
            color: #8c8c9e;
            letter-spacing: 1px;
            margin-bottom: 12px;
            text-transform: uppercase;
        }
        .compound-btn-group {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 8px;
        }
        .btn-compound {
            border: 1px solid rgba(255,255,255,0.1);
            background: #090912;
            color: #cbd5e1;
            font-family: 'Share Tech Mono', monospace;
            font-size: 11px;
            font-weight: 700;
            padding: 8px;
            border-radius: 4px;
            cursor: pointer;
            text-align: center;
            transition: all 0.2s;
        }
        .btn-compound.active-compound {
            border-color: currentColor;
            box-shadow: 0 0 12px currentColor;
            transform: scale(1.05);
        }
        .btn-drs-toggle {
            width: 100%;
            padding: 12px;
            border-radius: 6px;
            border: 1px solid #ff1801;
            background: rgba(255, 24, 1, 0.15);
            color: #ff6b6b;
            font-family: 'Orbitron', sans-serif;
            font-size: 12px;
            font-weight: 800;
            cursor: pointer;
            letter-spacing: 1.5px;
            transition: all 0.2s;
        }
        .btn-drs-toggle.active {
            background: #2ecc71;
            border-color: #2ecc71;
            color: #06060c;
            box-shadow: 0 0 20px rgba(46, 204, 113, 0.6);
        }

        /* 6. MODAL INSPECTION POPUP */
        .f1-modal-backdrop {
            position: fixed;
            top: 0; left: 0; width: 100%; height: 100%;
            background: rgba(4, 4, 10, 0.88);
            backdrop-filter: blur(16px);
            z-index: 9999;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 24px;
        }
        .modal-telemetry-dialog {
            background: linear-gradient(135deg, #141422 0%, #0c0c16 100%);
            border: 2px solid var(--f1-cyan);
            border-radius: 16px;
            max-width: 720px;
            width: 100%;
            padding: 32px;
            box-shadow: 0 0 60px rgba(0, 210, 190, 0.4);
            position: relative;
            animation: modalFade 0.3s ease;
        }
        @keyframes modalFade { from { opacity: 0; transform: scale(0.92); } to { opacity: 1; transform: scale(1); } }
        .modal-close-btn {
            position: absolute;
            top: 18px; right: 18px;
            background: rgba(255, 255, 255, 0.1);
            border: none;
            color: #ffffff;
            font-family: 'Orbitron', sans-serif;
            font-size: 12px;
            padding: 6px 12px;
            border-radius: 4px;
            cursor: pointer;
        }

        /* 7. Homologation Registry Forms Deck */
        .forms-deck {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 24px;
            margin-bottom: 32px;
            position: relative;
            z-index: 10;
        }
        .telemetry-card {
            background: var(--f1-card);
            border: 1px solid rgba(255, 255, 255, 0.08);
            border-radius: 12px;
            padding: 26px;
            position: relative;
            box-shadow: 0 15px 40px rgba(0, 0, 0, 0.6);
            backdrop-filter: blur(20px);
        }
        .telemetry-card.red-trim { border-top: 3px solid var(--f1-red); }
        .telemetry-card.cyan-trim { border-top: 3px solid var(--f1-cyan); }
        .input-matrix {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px;
        }
        .form-unit label {
            display: flex;
            justify-content: space-between;
            font-family: 'Share Tech Mono', monospace;
            font-size: 10.5px;
            font-weight: 700;
            color: #8c8c9e;
            letter-spacing: 1px;
            margin-bottom: 6px;
            text-transform: uppercase;
        }
        input, select {
            width: 100%;
            padding: 11px 14px;
            background: #090912;
            border: 1px solid #242436;
            border-radius: 6px;
            color: #ffffff;
            font-family: 'Titillium Web', sans-serif;
            font-size: 13px;
            transition: all 0.25s;
        }
        input:focus, select:focus {
            outline: none;
            border-color: var(--f1-cyan);
            box-shadow: 0 0 12px rgba(0, 210, 190, 0.35);
        }
        .btn-action-trigger {
            grid-column: 1 / -1;
            padding: 14px;
            border-radius: 6px;
            font-family: 'Orbitron', sans-serif;
            font-size: 12px;
            font-weight: 900;
            letter-spacing: 1.5px;
            text-transform: uppercase;
            border: none;
            cursor: pointer;
            transition: all 0.3s ease;
            margin-top: 6px;
        }
        .btn-action-red {
            background: linear-gradient(135deg, #ff1801 0%, #a80000 100%);
            color: #ffffff;
            box-shadow: 0 4px 15px rgba(255, 24, 1, 0.4);
        }
        .btn-action-cyan {
            background: linear-gradient(135deg, #00d2be 0%, #008f82 100%);
            color: #07070d;
            box-shadow: 0 4px 15px rgba(0, 210, 190, 0.4);
        }

        /* 8. Table Registry */
        .table-console {
            background: var(--f1-card);
            border: 1px solid rgba(255, 255, 255, 0.08);
            border-radius: 12px;
            padding: 26px;
            margin-bottom: 28px;
            position: relative;
            z-index: 10;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            text-align: left;
        }
        th {
            background: #0b0b14;
            color: #717188;
            font-family: 'Share Tech Mono', monospace;
            font-size: 11px;
            font-weight: 700;
            letter-spacing: 1.5px;
            text-transform: uppercase;
            padding: 14px 16px;
            border-bottom: 2px solid #232336;
        }
        td {
            padding: 14px 16px;
            font-size: 13px;
            border-bottom: 1px solid #1a1a27;
            color: #d1d1e0;
        }
        tr:hover td { background: rgba(255, 255, 255, 0.02); }
        .chassis-badge {
            font-family: 'Share Tech Mono', monospace;
            font-size: 11px;
            background: rgba(0, 210, 190, 0.08);
            border: 1px solid rgba(0, 210, 190, 0.3);
            color: var(--f1-cyan);
            padding: 4px 8px;
            border-radius: 4px;
        }
        .btn-kill-switch {
            background: rgba(255, 24, 1, 0.1);
            border: 1px solid rgba(255, 24, 1, 0.4);
            color: #ff4757;
            padding: 6px 12px;
            border-radius: 4px;
            font-family: 'Orbitron', sans-serif;
            font-size: 10px;
            font-weight: 800;
            cursor: pointer;
            transition: all 0.2s;
        }
        .btn-kill-switch:hover {
            background: var(--f1-red);
            color: #ffffff;
            box-shadow: 0 0 12px rgba(255, 24, 1, 0.6);
        }

        .alert-banner {
            padding: 14px 20px;
            border-radius: 8px;
            margin-bottom: 24px;
            font-family: 'Share Tech Mono', monospace;
            font-size: 13px;
            position: relative;
            z-index: 10;
        }
        .alert-banner.success {
            background: rgba(0, 210, 190, 0.15);
            border-left: 4px solid var(--f1-cyan);
            color: var(--f1-cyan);
            box-shadow: 0 0 20px rgba(0, 210, 190, 0.2);
        }
        .alert-banner.error {
            background: rgba(255, 24, 1, 0.15);
            border-left: 4px solid var(--f1-red);
            color: #ff6b6b;
        }
    </style>
</head>
<body>

<div class="ambient-glow-red"></div>
<div class="ambient-glow-cyan"></div>

<?php include 'navbar.php'; ?>

<!-- Top Live Ribbon -->
<div class="grid-header-ribbon">
    <div class="ribbon-left">
        <div class="pulse-core"></div>
        <div>
            <div class="live-beacon">FIA 2026 TECHNICAL HOMOLOGATION</div>
            <div class="ribbon-title">OFFICIAL FORMULA 1 STARTING GRID & CHASSIS PADDOCK</div>
        </div>
    </div>
    <div style="font-family: 'Share Tech Mono', monospace; font-size: 12px; color: #8c8c9e;">
        TOTAL HOMOLOGATED MONOCOQUES: <span style="color: var(--f1-cyan); font-weight: 700; font-size: 14px;"><?php echo count($all_cars); ?> / 20</span>
    </div>
</div>

<?php if($msg): ?>
    <div class="alert-banner <?php echo $msg_type; ?>"><?php echo htmlspecialchars($msg); ?></div>
<?php endif; ?>

<!-- 1. FIA 5-RED-LIGHTS RACE START ANIMATION GANTRY -->
<div class="start-gantry-deck">
    <div>
        <div style="font-family: 'Share Tech Mono', monospace; font-size: 11px; color: #8c8c9e; letter-spacing: 2px; margin-bottom: 6px;">
            RACE CONTROL // START PROCEDURE
        </div>
        <div style="font-family: 'Orbitron', sans-serif; font-size: 15px; font-weight: 800; color: #ffffff;">
            FIA OFFICIAL START GANTRY
        </div>
    </div>

    <!-- 5 Light Pods -->
    <div class="gantry-lights-housing">
        <?php for($i = 1; $i <= 5; $i++): ?>
            <div class="light-pod" id="gantryPod_<?php echo $i; ?>">
                <div class="light-bulb"></div>
                <div class="light-bulb"></div>
            </div>
        <?php endfor; ?>
    </div>

    <!-- Launch Actions & Reaction Clock -->
    <div class="gantry-actions">
        <button type="button" class="btn-gantry-launch" id="btnStartLights" onclick="triggerStartLightsSequence()">
            <span>🚥</span> INITIATE START SEQUENCE
        </button>
        <button type="button" class="btn-gantry-reset" id="btnResetGrid" onclick="resetGridFormation()">
            <span>🔄</span> RESET FORMATION
        </button>
        <div class="reaction-hud" id="reactionHud">
            STATUS: <span style="color: #ffd32a;">FORMATION GRID READY</span>
        </div>
    </div>
</div>

<!-- 2. INTERACTIVE VIEW & SEARCH TOOLBAR -->
<div class="grid-control-toolbar">
    <div class="view-mode-tabs">
        <button type="button" class="tab-btn active" id="tabTrackView" onclick="switchViewMode('track')">
            <span>🏁</span> Track Starting Grid
        </button>
        <button type="button" class="tab-btn" id="tabGarageView" onclick="switchViewMode('garage')">
            <span>🏎️</span> Aero Wind Tunnel Showcase
        </button>
        <button type="button" class="tab-btn" id="tabTableView" onclick="switchViewMode('table')">
            <span>📋</span> Homologation Table
        </button>
        <button type="button" class="tab-btn" id="tabDriversView" onclick="switchViewMode('drivers')">
            <span>👨‍🚀</span> Super License Registry
        </button>
    </div>

    <div class="filter-search-box">
        <input type="text" class="search-input-field" id="gridFilterInput" placeholder="Filter Cars, Monocoque, Pilots, Teams... (e.g. 'RB20', 'Leclerc', 'Ferrari')" onkeyup="filterStartingGrid(this.value)">
    </div>
</div>

<!-- 3. VIEW 1: FULL-WIDTH AUTHENTIC F1 ASPHALT STARTING GRID -->
<div id="viewTrackContainer" class="track-grid-container">
    <div class="start-finish-line">
        <span class="start-line-label">START / FINISH LINE // POLE SECTOR</span>
    </div>

    <div class="starting-grid-matrix" id="startingGridMatrix">
        <?php 
        $pos = 1;
        foreach($all_cars as $car): 
            $team_pal = get_team_palette($car['team_name']);
            $is_left = ($pos % 2 !== 0); // Odd positions left (clean side), Even right
            $assigned_pilot = !empty($car['driver_name']) ? $car['driver_name'] : ($pos % 2 !== 0 ? $team_pal['driver1'] : $team_pal['driver2']);
            $driver_numbers = [1 => '1', 2 => '11', 3 => '16', 4 => '55', 5 => '44', 6 => '63', 7 => '4', 8 => '81', 9 => '14', 10 => '18', 11 => '10', 12 => '31', 13 => '23', 14 => '2', 15 => '22', 16 => '3', 17 => '77', 18 => '24', 19 => '27', 20 => '20'];
            $car_num = $driver_numbers[$pos] ?? (string)$pos;
        ?>
            <div class="<?php echo $is_left ? 'grid-slot-row-left' : 'grid-slot-row-right'; ?> grid-entry-item" data-team="<?php echo strtolower($car['team_name']); ?>" data-model="<?php echo strtolower($car['car_name']); ?>" data-pilot="<?php echo strtolower($assigned_pilot); ?>">
                <div class="grid-box-card" onclick="openCarInspectorModal(<?php echo htmlspecialchars(json_encode($car)); ?>, '<?php echo $car_num; ?>', '<?php echo addslashes($assigned_pilot); ?>')">
                    
                    <div class="grid-slot-header">
                        <div class="pos-badge <?php echo ($pos === 1) ? 'pole-pos' : ''; ?>">
                            <span><?php echo ($pos === 1) ? '👑 P1 POLE' : 'P' . $pos; ?></span>
                        </div>
                        <div class="team-tag-pill" style="background: rgba(<?php echo hexdec(substr($team_pal['border'],1,2)) . ',' . hexdec(substr($team_pal['border'],3,2)) . ',' . hexdec(substr($team_pal['border'],5,2)); ?>, 0.2); color: <?php echo $team_pal['glow']; ?>; border: 1px solid <?php echo $team_pal['border']; ?>;">
                            <?php echo $team_pal['logo'] . ' ' . $team_pal['tag']; ?>
                        </div>
                    </div>

                    <div class="grid-slot-body">
                        <!-- Top-Down Vector SVG Car Livery -->
                        <div class="car-svg-preview-wrap" id="carWrap_<?php echo $car['car_id']; ?>">
                            <?php echo render_f1_car_svg($car['team_name'], $car_num, 'soft'); ?>
                        </div>

                        <!-- Telemetry Specs -->
                        <div class="slot-telemetry-info">
                            <div class="pilot-name"><?php echo htmlspecialchars($assigned_pilot); ?></div>
                            <div class="team-title"><?php echo htmlspecialchars($car['team_name']); ?></div>
                            
                            <div class="spec-item">
                                <span class="spec-label">CHASSIS:</span>
                                <span class="spec-val"><?php echo htmlspecialchars($car['car_name']); ?></span>
                            </div>
                            <div class="spec-item">
                                <span class="spec-label">MONOCOQUE:</span>
                                <span class="spec-val" style="color: var(--f1-cyan);"><?php echo htmlspecialchars($car['chassis_number']); ?></span>
                            </div>
                            <div class="spec-item">
                                <span class="spec-label">POWER UNIT:</span>
                                <span class="spec-val"><?php echo htmlspecialchars($car['engine_type']); ?></span>
                            </div>
                            <div class="spec-item">
                                <span class="spec-label">STATUS:</span>
                                <span class="spec-val" style="color: #2ecc71;">HOMOLOGATED</span>
                            </div>
                        </div>
                    </div>

                </div>
            </div>
        <?php 
            $pos++;
        endforeach; 
        ?>
    </div>
</div>

<!-- 4. VIEW 2: INTERACTIVE AERO WIND TUNNEL & 3D TURNTABLE SHOWCASE -->
<div id="viewGarageContainer" class="wind-tunnel-showcase" style="display: none;">
    <div class="showcase-header">
        <div class="showcase-title">
            <span>🌀</span> FIA AERODYNAMIC WIND TUNNEL & CAR SHOWCASE
        </div>
        <div style="display: flex; gap: 10px;">
            <select id="showcaseCarSelector" onchange="updateShowcaseCar(this.value)" style="background: #0b0b14; border: 1px solid var(--f1-cyan); color: #ffffff; padding: 8px 14px; border-radius: 6px; font-family: 'Share Tech Mono', monospace; font-size: 12px; outline: none;">
                <?php foreach($all_cars as $c): ?>
                    <option value="<?php echo $c['car_id']; ?>"><?php echo htmlspecialchars($c['car_name'] . ' - ' . $c['team_name']); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
    </div>

    <div class="showcase-grid">
        <!-- Telemetry Diagnostics Column -->
        <div class="showcase-controls-panel">
            <div class="control-widget-card">
                <div class="widget-title">AERO CFD TELEMETRY</div>
                <div style="font-family: 'Share Tech Mono', monospace; font-size: 12px; line-height: 1.8;">
                    <div style="display: flex; justify-content: space-between;">
                        <span style="color: #8c8c9e;">AIR VELOCITY:</span>
                        <span style="color: var(--f1-cyan); font-weight: 700;" id="cfdAirSpeed">320.4 KM/H</span>
                    </div>
                    <div style="display: flex; justify-content: space-between;">
                        <span style="color: #8c8c9e;">DOWNFORCE:</span>
                        <span style="color: #2ecc71; font-weight: 700;" id="cfdDownforce">1,840 KG</span>
                    </div>
                    <div style="display: flex; justify-content: space-between;">
                        <span style="color: #8c8c9e;">DRAG COEFFICIENT:</span>
                        <span style="color: #ffd32a; font-weight: 700;" id="cfdDrag">0.78 Cd</span>
                    </div>
                    <div style="display: flex; justify-content: space-between;">
                        <span style="color: #8c8c9e;">FRONT/REAR BIAS:</span>
                        <span style="color: #ffffff; font-weight: 700;">44.5% / 55.5%</span>
                    </div>
                </div>
            </div>

            <div class="control-widget-card">
                <div class="widget-title">DRS WING ACTUATION</div>
                <button type="button" class="btn-drs-toggle" id="btnToggleDrs" onclick="toggleDrsFlap()">
                    DRS FLAP: [CLOSED] - ACTIVATE
                </button>
            </div>
        </div>

        <!-- Central Wind Tunnel Viewport with Streamlines -->
        <div class="wind-tunnel-viewport" id="windTunnelViewport">
            <!-- Animated SVG Streamlines -->
            <svg class="streamline-overlay" viewBox="0 0 300 380">
                <path class="flow-line" d="M 40 0 C 40 100, 70 140, 70 380"/>
                <path class="flow-line" d="M 100 0 C 100 80, 120 130, 115 380" style="animation-duration: 1.2s;"/>
                <path class="flow-line" d="M 150 0 C 150 60, 150 140, 150 380" style="animation-duration: 1.0s; stroke: #ff1801;"/>
                <path class="flow-line" d="M 200 0 C 200 80, 180 130, 185 380" style="animation-duration: 1.2s;"/>
                <path class="flow-line" d="M 260 0 C 260 100, 230 140, 230 380"/>
            </svg>

            <!-- Active Showcased SVG Car -->
            <div id="showcaseCarContainer" style="width: 140px; height: 280px; position: relative; z-index: 5; transition: transform 0.3s ease;">
                <?php echo render_f1_car_svg($all_cars[0]['team_name'] ?? 'Oracle Red Bull Racing', '1', 'soft'); ?>
            </div>

            <div class="aero-drag-zone">
                <span>LAMINAR BOUNDARY LAYER: 99.4%</span>
                <span style="color: var(--f1-cyan);">TURBULENCE: LOW</span>
            </div>
        </div>

        <!-- Tyre Compounds & Engine Throttle Column -->
        <div class="showcase-controls-panel">
            <div class="control-widget-card">
                <div class="widget-title">PIRELLI TYRE COMPOUND</div>
                <div class="compound-btn-group">
                    <button type="button" class="btn-compound active-compound" style="color: #ff1801;" onclick="changeShowcaseTyre('soft', this)">
                        🔴 SOFT (C5)
                    </button>
                    <button type="button" class="btn-compound" style="color: #ffd32a;" onclick="changeShowcaseTyre('medium', this)">
                        🟡 MED (C3)
                    </button>
                    <button type="button" class="btn-compound" style="color: #ffffff;" onclick="changeShowcaseTyre('hard', this)">
                        ⚪ HARD (C1)
                    </button>
                    <button type="button" class="btn-compound" style="color: #0be881;" onclick="changeShowcaseTyre('inter', this)">
                        🟢 INTER
                    </button>
                    <button type="button" class="btn-compound" style="color: #0fbcf9;" onclick="changeShowcaseTyre('wet', this)">
                        🔵 WET
                    </button>
                </div>
            </div>

            <div class="control-widget-card">
                <div class="widget-title">ACOUSTIC TELEMETRY</div>
                <button type="button" class="btn-action-trigger btn-action-red" onclick="playF1EngineRevSound()" style="margin: 0;">
                    🔊 REV V6 TURBO ENGINE
                </button>
            </div>
        </div>
    </div>
</div>

<!-- 5. VIEW 3: HOMOLOGATION TABULAR REGISTRY -->
<div id="viewTableContainer" class="table-console" style="display: none;">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
        <div style="font-family: 'Orbitron', sans-serif; font-size: 16px; font-weight: 800;">
            <span>🏁</span> OFFICIAL HOMOLOGATION REGISTRY (<?php echo count($all_cars); ?> ENTRIES)
        </div>
        <span style="font-family: 'Share Tech Mono', monospace; font-size: 11px; color: var(--f1-cyan);">
            FIA ENCRYPTED REGISTRATION
        </span>
    </div>

    <div style="overflow-x:auto;">
        <table>
            <thead>
                <tr>
                    <th>#ID</th>
                    <th>Chassis Model</th>
                    <th>Monocoque Serial</th>
                    <th>Constructor Team</th>
                    <th>Assigned Pilot</th>
                    <th>Power Unit</th>
                    <th>Status</th>
                    <th style="text-align: center;">Decommission</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach($all_cars as $row): ?>
                <tr>
                    <td style="font-family: 'Share Tech Mono', monospace; font-weight: 700; color: #ff1801;">
                        #<?php echo str_pad($row['car_id'], 2, '0', STR_PAD_LEFT); ?>
                    </td>
                    <td style="font-weight: 700; color: #ffffff;">
                        <?php echo htmlspecialchars($row['car_name']); ?>
                    </td>
                    <td>
                        <span class="chassis-badge"><?php echo htmlspecialchars($row['chassis_number']); ?></span>
                    </td>
                    <td style="color: #cbd5e1;">
                        <?php echo htmlspecialchars($row['team_name']); ?>
                    </td>
                    <td style="color: var(--f1-cyan); font-weight: 600;">
                        <?php echo htmlspecialchars($row['driver_name'] ?? 'Reserve Test Pilot'); ?>
                    </td>
                    <td style="color: #8c8c9e; font-size: 12px;">
                        <?php echo htmlspecialchars($row['engine_type']); ?>
                    </td>
                    <td>
                        <span style="color: #2ecc71; font-family: 'Share Tech Mono', monospace; font-size: 11px;">● HOMOLOGATED</span>
                    </td>
                    <td style="text-align: center;">
                        <form method="POST" onsubmit="return confirm('WARNING: Are you certain you want to decommission chassis #<?php echo $row['car_id']; ?>?');" style="display:inline;">
                            <input type="hidden" name="delete_car_id" value="<?php echo $row['car_id']; ?>">
                            <button type="submit" class="btn-kill-switch">TERMINATE</button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- 5B. VIEW 4: OFFICIAL SUPER LICENSE PILOTS REGISTRY -->
<div id="viewDriversContainer" class="table-console" style="display: none;">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
        <div style="font-family: 'Orbitron', sans-serif; font-size: 16px; font-weight: 800; color: var(--f1-cyan);">
            <span>👨‍🚀</span> OFFICIAL FIA SUPER LICENSE PILOTS ROSTER
        </div>
        <span style="font-family: 'Share Tech Mono', monospace; font-size: 11px; color: #8c8c9e;">
            FIA INTERNATIONAL SPORTING CODE // APPENDIX L
        </span>
    </div>

    <div style="overflow-x:auto;">
        <table>
            <thead>
                <tr>
                    <th>#DRIVER ID</th>
                    <th>Pilot Name</th>
                    <th>Nationality</th>
                    <th>Super License #</th>
                    <th>Assigned Monocoque</th>
                    <th>Constructor Team</th>
                    <th>License Status</th>
                </tr>
            </thead>
            <tbody>
                <?php 
                if ($drivers_list && $drivers_list->num_rows > 0):
                    $drivers_list->data_seek(0);
                    while($d = $drivers_list->fetch_assoc()): 
                        $has_car = !empty($d['car_name']);
                ?>
                <tr>
                    <td style="font-family: 'Share Tech Mono', monospace; font-weight: 700; color: #ff1801;">
                        #<?php echo str_pad($d['driver_id'], 2, '0', STR_PAD_LEFT); ?>
                    </td>
                    <td style="font-weight: 800; color: #ffffff; font-size: 14px;">
                        <?php echo htmlspecialchars($d['full_name']); ?>
                    </td>
                    <td style="color: #cbd5e1;">
                        <?php echo htmlspecialchars($d['nationality'] ?? $d['country'] ?? 'FIA'); ?>
                    </td>
                    <td>
                        <span class="chassis-badge" style="border-color: #ffd32a; color: #ffd32a; background: rgba(255, 211, 42, 0.08);">
                            <?php echo htmlspecialchars($d['license_number'] ?? ('FIA-SL-' . str_pad($d['driver_id'] + 100, 3, '0', STR_PAD_LEFT))); ?>
                        </span>
                    </td>
                    <td>
                        <?php if ($has_car): ?>
                            <strong style="color: var(--f1-cyan);"><?php echo htmlspecialchars($d['car_name']); ?></strong>
                        <?php else: ?>
                            <span style="color: #8c8c9e; font-style: italic;">Reserve / Unassigned</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if (!empty($d['team_name'])): ?>
                            <span style="color: #ffffff;"><?php echo htmlspecialchars($d['team_name']); ?></span>
                        <?php else: ?>
                            <span style="color: #8c8c9e;">Free Agent Roster</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <span style="color: #2ecc71; font-family: 'Share Tech Mono', monospace; font-size: 11px;">
                            ● ACTIVE SUPER LICENSE
                        </span>
                    </td>
                </tr>
                <?php 
                    endwhile;
                endif; 
                ?>
            </tbody>
        </table>
    </div>
</div>

<!-- 6. HOMOLOGATION & SUPER LICENSE REGISTRATION FORMS -->
<div class="forms-deck">
    <!-- Card 1: Homologate Chassis -->
    <div class="telemetry-card red-trim">
        <div style="display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid rgba(255,255,255,0.08); padding-bottom: 14px; margin-bottom: 20px;">
            <div style="font-family: 'Orbitron', sans-serif; font-size: 15px; font-weight: 800; color: #ff4757; display: flex; align-items: center; gap: 8px;">
                <span>🏎️</span> 01. Chassis Homologation
            </div>
            <span style="font-family: 'Share Tech Mono', monospace; font-size: 10px; color: #8c8c9e; background: rgba(255,255,255,0.05); padding: 3px 8px; border-radius: 4px;">TECH-FORM 1A</span>
        </div>
        <form method="POST">
            <input type="hidden" name="add_car" value="1">
            <div class="input-matrix">
                <div class="form-unit">
                    <label><span>Chassis Model</span> <span style="color: #ff4757;">*</span></label>
                    <input type="text" name="car_name" placeholder="e.g. SF-26 Telemetry Evo" required>
                </div>
                <div class="form-unit">
                    <label><span>Monocoque Serial #</span> <span style="color: #ff4757;">*</span></label>
                    <input type="text" name="chassis_number" placeholder="e.g. CHASSIS-SF-04" required>
                </div>
                <div class="form-unit">
                    <label><span>Power Unit Architecture</span> <span style="color: #ff4757;">*</span></label>
                    <input type="text" name="engine_type" placeholder="e.g. Ferrari 066/14 V6 Turbo" required>
                </div>
                <div class="form-unit">
                    <label><span>Constructor Team</span> <span style="color: #ff4757;">*</span></label>
                    <select name="team_id" required>
                        <?php while($t = $teams->fetch_assoc()): ?>
                            <option value="<?php echo $t['team_id']; ?>"><?php echo htmlspecialchars($t['team_name']); ?></option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div class="form-unit">
                    <label><span>Pilot Assignment</span></label>
                    <select name="driver_id">
                        <option value="">-- Reserve Pilot --</option>
                        <?php while($d = $drivers_dropdown->fetch_assoc()): ?>
                            <option value="<?php echo $d['driver_id']; ?>">
                                <?php echo htmlspecialchars($d['full_name'] . ' (' . ($d['nationality'] ?? $d['country'] ?? 'FIA') . ')'); ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div class="form-unit">
                    <label><span>FIA Category</span></label>
                    <input type="text" name="category" value="Formula 1" required>
                </div>
                <button type="submit" class="btn-action-trigger btn-action-red">Homologate Chassis Specification</button>
            </div>
        </form>
    </div>

    <!-- Card 2: Register Super License Driver -->
    <div class="telemetry-card cyan-trim">
        <div style="display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid rgba(255,255,255,0.08); padding-bottom: 14px; margin-bottom: 20px;">
            <div style="font-family: 'Orbitron', sans-serif; font-size: 15px; font-weight: 800; color: #00d2be; display: flex; align-items: center; gap: 8px;">
                <span>👨‍🚀</span> 02. Driver Super License
            </div>
            <span style="font-family: 'Share Tech Mono', monospace; font-size: 10px; color: #8c8c9e; background: rgba(255,255,255,0.05); padding: 3px 8px; border-radius: 4px;">SL-ISSUANCE</span>
        </div>
        <form method="POST">
            <input type="hidden" name="add_driver" value="1">
            <div class="input-matrix">
                <div class="form-unit">
                    <label><span>Pilot Full Name</span> <span style="color: #ff4757;">*</span></label>
                    <input type="text" name="driver_name" placeholder="e.g. Andrea Kimi Antonelli" required>
                </div>
                <div class="form-unit">
                    <label><span>Nationality / Country</span> <span style="color: #ff4757;">*</span></label>
                    <input type="text" name="nationality" placeholder="e.g. Italian" required>
                </div>
                <div class="form-unit">
                    <label><span>Date of Birth</span></label>
                    <input type="date" name="date_of_birth" value="2006-08-25">
                </div>
                <div class="form-unit">
                    <label><span>FIA License Number</span></label>
                    <input type="text" name="license_number" placeholder="e.g. FIA-SL-099">
                </div>
                <button type="submit" class="btn-action-trigger btn-action-cyan" style="grid-column: 1 / -1; margin-top: 14px;">Issue Official Super License</button>
            </div>
        </form>
    </div>
</div>

<!-- 7. INTERACTIVE 360 CAR INSPECTION MODAL -->
<div class="f1-modal-backdrop" id="carInspectorModal" style="display: none;" onclick="closeModalOnBackdrop(event)">
    <div class="modal-telemetry-dialog">
        <button type="button" class="modal-close-btn" onclick="closeCarInspectorModal()">ESC ✕</button>
        
        <div style="display: flex; gap: 24px; align-items: center;">
            <div style="width: 130px; height: 260px; flex-shrink: 0;" id="modalCarSvgContainer">
                <!-- Injected via JS -->
            </div>

            <div style="flex-grow: 1;">
                <div style="font-family: 'Share Tech Mono', monospace; font-size: 11px; color: var(--f1-cyan); margin-bottom: 4px;" id="modalTeamTag">
                    CONSTRUCTOR DOSSIER
                </div>
                <div style="font-family: 'Orbitron', sans-serif; font-size: 22px; font-weight: 900; color: #ffffff; margin-bottom: 4px;" id="modalCarTitle">
                    Red Bull RB20 #1
                </div>
                <div style="font-size: 14px; color: #8c8c9e; margin-bottom: 16px;" id="modalPilotName">
                    Max Verstappen #1 (Oracle Red Bull Racing)
                </div>

                <div style="background: rgba(0,0,0,0.5); border: 1px solid rgba(255,255,255,0.08); border-radius: 8px; padding: 14px; font-family: 'Share Tech Mono', monospace; font-size: 12px; line-height: 1.8; margin-bottom: 20px;">
                    <div style="display: flex; justify-content: space-between;">
                        <span style="color: #6e6e85;">MONOCOQUE SERIAL:</span>
                        <span style="color: #ffffff; font-weight: 700;" id="modalMonocoque">CHASSIS-RB-01</span>
                    </div>
                    <div style="display: flex; justify-content: space-between;">
                        <span style="color: #6e6e85;">POWER UNIT ARCHITECTURE:</span>
                        <span style="color: #ffd32a; font-weight: 700;" id="modalPu">Honda RBPTH002 1.6L V6 Turbo</span>
                    </div>
                    <div style="display: flex; justify-content: space-between;">
                        <span style="color: #6e6e85;">MINIMUM WEIGHT:</span>
                        <span style="color: #2ecc71; font-weight: 700;">798 KG (FIA COMPLIANT)</span>
                    </div>
                    <div style="display: flex; justify-content: space-between;">
                        <span style="color: #6e6e85;">SCRUTINEERING STATUS:</span>
                        <span style="color: var(--f1-cyan); font-weight: 700;">PASSED HOMOLOGATION</span>
                    </div>
                </div>

                <div style="display: flex; gap: 10px;">
                    <a href="feature2.php" class="btn-action-trigger btn-action-cyan" style="text-decoration: none; text-align: center; padding: 10px 16px; margin: 0; font-size: 11px;">
                        🛠️ RIG INSPECTOR
                    </a>
                    <a href="feature4.php" class="btn-action-trigger btn-action-red" style="text-decoration: none; text-align: center; padding: 10px 16px; margin: 0; font-size: 11px;">
                        📑 PU CAP ALLOCATIONS
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Web Audio Synthesizer for F1 Engine & Beeps
let audioCtx = null;
function getAudioCtx() {
    if (!audioCtx) {
        audioCtx = new (window.AudioContext || window.webkitAudioContext)();
    }
    return audioCtx;
}

function playGantryBeep(freq = 1100, duration = 0.12) {
    try {
        const ctx = getAudioCtx();
        if (ctx.state === 'suspended') ctx.resume();
        const now = ctx.currentTime;
        const osc = ctx.createOscillator();
        const gain = ctx.createGain();
        osc.type = 'sine';
        osc.frequency.setValueAtTime(freq, now);
        gain.gain.setValueAtTime(0.25, now);
        gain.gain.exponentialRampToValueAtTime(0.001, now + duration);
        osc.connect(gain); gain.connect(ctx.destination);
        osc.start(now); osc.stop(now + duration);
    } catch(e) {}
}

function playF1EngineRevSound() {
    try {
        const ctx = getAudioCtx();
        if (ctx.state === 'suspended') ctx.resume();
        const now = ctx.currentTime;
        const duration = 1.8;

        // V6 Sawtooth
        const osc1 = ctx.createOscillator();
        const gain1 = ctx.createGain();
        osc1.type = 'sawtooth';
        osc1.frequency.setValueAtTime(140, now);
        osc1.frequency.exponentialRampToValueAtTime(800, now + 0.6);
        osc1.frequency.exponentialRampToValueAtTime(1100, now + 1.1);
        osc1.frequency.exponentialRampToValueAtTime(320, now + duration);

        gain1.gain.setValueAtTime(0.01, now);
        gain1.gain.linearRampToValueAtTime(0.35, now + 0.3);
        gain1.gain.linearRampToValueAtTime(0.4, now + 0.8);
        gain1.gain.exponentialRampToValueAtTime(0.001, now + duration);

        // Sub Rumble
        const osc2 = ctx.createOscillator();
        const gain2 = ctx.createGain();
        osc2.type = 'triangle';
        osc2.frequency.setValueAtTime(70, now);
        osc2.frequency.exponentialRampToValueAtTime(360, now + 0.6);
        osc2.frequency.exponentialRampToValueAtTime(120, now + duration);
        gain2.gain.setValueAtTime(0.01, now);
        gain2.gain.linearRampToValueAtTime(0.3, now + 0.3);
        gain2.gain.exponentialRampToValueAtTime(0.001, now + duration);

        osc1.connect(gain1); gain1.connect(ctx.destination);
        osc2.connect(gain2); gain2.connect(ctx.destination);

        osc1.start(now); osc2.start(now);
        osc1.stop(now + duration); osc2.stop(now + duration);
    } catch(e) {}
}

// 1. FIA 5-Red-Lights Sequence Controller
let sequenceRunning = false;
function triggerStartLightsSequence() {
    if (sequenceRunning) return;
    sequenceRunning = true;

    const btnLaunch = document.getElementById('btnStartLights');
    const hud = document.getElementById('reactionHud');
    btnLaunch.disabled = true;
    hud.innerHTML = '<span style="color: #ff1801; animation: blink 0.5s infinite;">LIGHTS SEQUENCE ARMED...</span>';

    // Reset all light bulbs
    for(let i = 1; i <= 5; i++) {
        const pod = document.getElementById('gantryPod_' + i);
        if (pod) {
            pod.querySelectorAll('.light-bulb').forEach(b => b.classList.remove('active'));
        }
    }

    let lightIndex = 1;
    const interval = setInterval(() => {
        if (lightIndex <= 5) {
            const pod = document.getElementById('gantryPod_' + lightIndex);
            if (pod) {
                pod.querySelectorAll('.light-bulb').forEach(b => b.classList.add('active'));
            }
            playGantryBeep(1000 + (lightIndex * 150), 0.15);
            lightIndex++;
        } else {
            clearInterval(interval);
            
            // Random FIA holding delay (1.2s to 2.4s)
            const randomHold = 1200 + Math.random() * 1200;
            setTimeout(() => {
                // LIGHTS OUT!
                for(let i = 1; i <= 5; i++) {
                    const pod = document.getElementById('gantryPod_' + i);
                    if (pod) {
                        pod.querySelectorAll('.light-bulb').forEach(b => b.classList.remove('active'));
                    }
                }
                
                // Play launch engine roar
                playF1EngineRevSound();

                // Animate all grid cars forward
                const allCarWraps = document.querySelectorAll('.car-svg-preview-wrap');
                allCarWraps.forEach((wrap, idx) => {
                    setTimeout(() => {
                        wrap.classList.add('car-launch-animated');
                    }, idx * 35);
                });

                const reactionTime = (0.180 + Math.random() * 0.090).toFixed(3);
                hud.innerHTML = '<span style="color: #00d2be; font-weight: 900;">LIGHTS OUT! POLE REACTION: ' + reactionTime + 's</span>';
                
                btnLaunch.disabled = false;
                sequenceRunning = false;
            }, randomHold);
        }
    }, 1000);
}

function resetGridFormation() {
    const allCarWraps = document.querySelectorAll('.car-svg-preview-wrap');
    allCarWraps.forEach(wrap => {
        wrap.classList.remove('car-launch-animated');
    });
    for(let i = 1; i <= 5; i++) {
        const pod = document.getElementById('gantryPod_' + i);
        if (pod) {
            pod.querySelectorAll('.light-bulb').forEach(b => b.classList.remove('active'));
        }
    }
    const hud = document.getElementById('reactionHud');
    hud.innerHTML = 'STATUS: <span style="color: #ffd32a;">FORMATION GRID RESET & READY</span>';
    playGantryBeep(850, 0.1);
}

// 2. View Mode Tabs Switcher
function switchViewMode(mode) {
    playGantryBeep(1200, 0.05);
    document.getElementById('viewTrackContainer').style.display = (mode === 'track') ? 'block' : 'none';
    document.getElementById('viewGarageContainer').style.display = (mode === 'garage') ? 'block' : 'none';
    document.getElementById('viewTableContainer').style.display = (mode === 'table') ? 'block' : 'none';
    const driversView = document.getElementById('viewDriversContainer');
    if (driversView) driversView.style.display = (mode === 'drivers') ? 'block' : 'none';

    document.getElementById('tabTrackView').classList.toggle('active', mode === 'track');
    document.getElementById('tabGarageView').classList.toggle('active', mode === 'garage');
    document.getElementById('tabTableView').classList.toggle('active', mode === 'table');
    const tabDrv = document.getElementById('tabDriversView');
    if (tabDrv) tabDrv.classList.toggle('active', mode === 'drivers');
}

// 3. Search & Real-time Grid Filtering
function filterStartingGrid(query) {
    const q = query.toLowerCase().trim();
    const entries = document.querySelectorAll('.grid-entry-item');
    entries.forEach(entry => {
        const team = entry.dataset.team || '';
        const model = entry.dataset.model || '';
        const pilot = entry.dataset.pilot || '';
        if (team.includes(q) || model.includes(q) || pilot.includes(q)) {
            entry.style.display = 'flex';
        } else {
            entry.style.display = 'none';
        }
    });
}

// 4. DRS Actuation & Tyre Compound in Wind Tunnel
let drsOpen = false;
function toggleDrsFlap() {
    playGantryBeep(1400, 0.08);
    drsOpen = !drsOpen;
    const btn = document.getElementById('btnToggleDrs');
    const cfdSpeed = document.getElementById('cfdAirSpeed');
    const cfdDownforce = document.getElementById('cfdDownforce');
    const cfdDrag = document.getElementById('cfdDrag');
    const flaps = document.querySelectorAll('.drs-flap-element');

    if (drsOpen) {
        btn.classList.add('active');
        btn.textContent = 'DRS FLAP: [OPEN] - 22% DRAG REDUCTION';
        cfdSpeed.textContent = '334.8 KM/H (+14.4)';
        cfdDownforce.textContent = '1,410 KG (-430)';
        cfdDrag.textContent = '0.61 Cd (-21.8%)';
        flaps.forEach(f => {
            f.setAttribute('transform', 'scale(1, 0.3) translate(0, 10)');
            f.setAttribute('fill', '#2ecc71');
        });
    } else {
        btn.classList.remove('active');
        btn.textContent = 'DRS FLAP: [CLOSED] - ACTIVATE';
        cfdSpeed.textContent = '320.4 KM/H';
        cfdDownforce.textContent = '1,840 KG';
        cfdDrag.textContent = '0.78 Cd';
        flaps.forEach(f => {
            f.removeAttribute('transform');
            f.setAttribute('fill', '#ff8000');
        });
    }
}

function changeShowcaseTyre(compound, btn) {
    playGantryBeep(1300, 0.06);
    document.querySelectorAll('.btn-compound').forEach(b => b.classList.remove('active-compound'));
    btn.classList.add('active-compound');

    const tyreColors = {
        'soft': '#ff1801',
        'medium': '#ffd32a',
        'hard': '#ffffff',
        'inter': '#0be881',
        'wet': '#0fbcf9'
    };
    const c = tyreColors[compound] || '#ff1801';

    // Update ellipses in active SVG
    const showcaseContainer = document.getElementById('showcaseCarContainer');
    if (showcaseContainer) {
        showcaseContainer.querySelectorAll('ellipse[stroke]').forEach(el => {
            el.setAttribute('stroke', c);
        });
    }
}

function updateShowcaseCar(carId) {
    const selector = document.getElementById('showcaseCarSelector');
    const text = selector.options[selector.selectedIndex].text;
    playGantryBeep(1150, 0.08);
}

// 5. Modal Inspection Dialog
function openCarInspectorModal(carData, carNum, pilotName) {
    playGantryBeep(1250, 0.08);
    const modal = document.getElementById('carInspectorModal');
    document.getElementById('modalTeamTag').textContent = carData.team_name.toUpperCase() + ' // OFFICIAL REGISTRY';
    document.getElementById('modalCarTitle').textContent = carData.car_name + ' #' + carNum;
    document.getElementById('modalPilotName').textContent = pilotName + ' (' + carData.team_name + ')';
    document.getElementById('modalMonocoque').textContent = carData.chassis_number;
    document.getElementById('modalPu').textContent = carData.engine_type;

    // Clone SVG from grid card
    const sourceCardSvg = document.getElementById('carWrap_' + carData.car_id);
    const modalSvgContainer = document.getElementById('modalCarSvgContainer');
    if (sourceCardSvg && modalSvgContainer) {
        modalSvgContainer.innerHTML = sourceCardSvg.innerHTML;
    }

    modal.style.display = 'flex';
}

function closeCarInspectorModal() {
    playGantryBeep(900, 0.05);
    document.getElementById('carInspectorModal').style.display = 'none';
}

function closeModalOnBackdrop(e) {
    if (e.target.id === 'carInspectorModal') {
        closeCarInspectorModal();
    }
}

document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') closeCarInspectorModal();
});
</script>

</body>
</html>
