<?php
// FIA FSMS RBAC & Telemetry Security Guard
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Normalizes role names to standard lowercase identifiers:
 * 'admin', 'inspector', 'steward', 'team_representative'
 */
if (!function_exists('normalize_role_name')) {
    function normalize_role_name($raw_role) {
        $r = strtolower(trim($raw_role ?? ''));
        if ($r === 'scrutineer' || $r === 'inspector' || $r === 'technical_delegate') {
            return 'inspector';
        }
        if ($r === 'steward' || $r === 'judge' || $r === 'panel_steward') {
            return 'steward';
        }
        if ($r === 'team representative' || $r === 'team_representative' || $r === 'team' || $r === 'constructor') {
            return 'team_representative';
        }
        if ($r === 'admin' || $r === 'administrator' || $r === 'race_director') {
            return 'admin';
        }
        return !empty($r) ? $r : 'inspector';
    }
}

if (!function_exists('validate_role_email_domain')) {
    function validate_role_email_domain($email, $role_id) {
        $email = strtolower(trim($email));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return "INVALID FORMAT: Please provide a valid email address.";
        }

        $domain = substr(strrchr($email, "@"), 1);

        if ($role_id == 1) { // Admin (Race Director / Admin)
            if ($domain !== 'admin.fia.com' && $domain !== 'racecontrol.fia.com') {
                return "ROLE-DOMAIN MISMATCH: Admin accounts MUST use @admin.fia.com or @racecontrol.fia.com (no generic @fia.com).";
            }
        } elseif ($role_id == 2) { // Inspector (Technical Delegate / Scrutineer)
            if ($domain !== 'inspector.fia.com' && $domain !== 'scrutineering.fia.com') {
                return "ROLE-DOMAIN MISMATCH: Inspector accounts MUST use @inspector.fia.com or @scrutineering.fia.com.";
            }
        } elseif ($role_id == 3) { // Steward (Judicial Panel)
            if ($domain !== 'stewards.fia.com') {
                return "ROLE-DOMAIN MISMATCH: Steward accounts MUST use @stewards.fia.com.";
            }
        } elseif ($role_id == 4) { // Team Representative (Constructor Competitor)
            if ($domain === 'fia.com' || strpos($domain, '.fia.com') !== false) {
                return "ROLE-DOMAIN MISMATCH: Team Representatives are competitors and CANNOT use @fia.com official domains. Please use @f1team.com or constructor domain (e.g. @redbull.f1team.com, @ferrari.f1team.com, @mercedes.f1team.com).";
            }
        }
        return null; // Valid
    }
}

if (!function_exists('get_current_user_role')) {
    function get_current_user_role() {
        if (isset($_SESSION['role_name']) && !empty($_SESSION['role_name'])) {
            return normalize_role_name($_SESSION['role_name']);
        }
        
        // Fallback: lookup in database if session has user_id
        global $conn;
        if (isset($_SESSION['user_id']) && isset($conn)) {
            $stmt = $conn->prepare("SELECT u.role_id, r.role_name FROM USERS u LEFT JOIN ROLES r ON u.role_id = r.role_id WHERE u.user_id = ? LIMIT 1");
            if ($stmt) {
                $stmt->bind_param("i", $_SESSION['user_id']);
                $stmt->execute();
                $res = $stmt->get_result();
                if ($res && $row = $res->fetch_assoc()) {
                    $_SESSION['role_id'] = intval($row['role_id']);
                    $_SESSION['role_name'] = normalize_role_name($row['role_name']);
                    return $_SESSION['role_name'];
                }
            }
        }
        return 'inspector';
    }
}

if (!function_exists('has_role_access')) {
    function has_role_access(array $allowed_roles) {
        $current_role = get_current_user_role();
        if ($current_role === 'admin') {
            return true;
        }
        $normalized_allowed = array_map('normalize_role_name', $allowed_roles);
        return in_array($current_role, $normalized_allowed, true);
    }
}

if (!function_exists('get_role_home_page')) {
    function get_role_home_page($role_name = null) {
        $role = $role_name ? normalize_role_name($role_name) : get_current_user_role();
        if ($role === 'admin') {
            return 'dashboard.php';
        } elseif ($role === 'inspector') {
            return 'feature2.php';
        } elseif ($role === 'steward') {
            return 'stewards_infringements.php';
        } elseif ($role === 'team_representative') {
            return 'feature8.php';
        }
        return 'feature4.php';
    }
}

/**
 * Universal FIA System Audit Logger
 */
if (!function_exists('log_system_audit')) {
    function log_system_audit($conn, $user_id, $action, $entity_type = null, $entity_id = null, $details_array = []) {
        if (!$conn) return false;
        $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
        if ($ip === '::1') $ip = '127.0.0.1';
        $details_json = !empty($details_array) ? json_encode($details_array, JSON_UNESCAPED_UNICODE) : null;
        
        $stmt = $conn->prepare("INSERT INTO AUDIT_LOGS (user_id, action, entity_type, entity_id, details, ip_address, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())");
        if ($stmt) {
            $uid = !empty($user_id) ? intval($user_id) : ($_SESSION['user_id'] ?? 1);
            $eid = !empty($entity_id) ? intval($entity_id) : 0;
            $stmt->bind_param("ississ", $uid, $action, $entity_type, $eid, $details_json, $ip);
            return $stmt->execute();
        }
        return false;
    }
}

/**
 * Universal CSRF Token Helpers
 */
if (!function_exists('generate_csrf_token')) {
    function generate_csrf_token() {
        if (empty($_SESSION['f1_csrf_token'])) {
            $_SESSION['f1_csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['f1_csrf_token'];
    }
}

if (!function_exists('verify_csrf_token')) {
    function verify_csrf_token($token = null) {
        $candidate = $token ?? ($_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
        if (empty($candidate) || empty($_SESSION['f1_csrf_token'])) {
            return false;
        }
        return hash_equals($_SESSION['f1_csrf_token'], $candidate);
    }
}

/**
 * Quick Role Switcher Helper for Demo & RBAC Validation
 */
if (!function_exists('switch_active_role')) {
    function switch_active_role($role_id, $conn = null) {
        $role_id = intval($role_id);
        $role_presets = [
            1 => [
                'user_id' => 1,
                'role_id' => 1,
                'role_name' => 'admin',
                'full_name' => 'Marzia Tabassum',
                'username' => 'Marzia (Race Director)',
                'email' => 'marzia@admin.fia.com'
            ],
            2 => [
                'user_id' => 2,
                'role_id' => 2,
                'role_name' => 'inspector',
                'full_name' => 'Jo Bauer',
                'username' => 'Jo Bauer (Tech Delegate)',
                'email' => 'bauer@inspector.fia.com'
            ],
            3 => [
                'user_id' => 3,
                'role_id' => 3,
                'role_name' => 'steward',
                'full_name' => 'Matteo Perini',
                'username' => 'Matteo Perini (FIA Steward)',
                'email' => 'perini@stewards.fia.com'
            ],
            4 => [
                'user_id' => 4,
                'role_id' => 4,
                'role_name' => 'team_representative',
                'full_name' => 'Christian Horner',
                'username' => 'Christian Horner (Red Bull Racing)',
                'email' => 'chorner@redbull.f1team.com'
            ]
        ];

        if (!isset($role_presets[$role_id])) {
            $role_id = 1;
        }

        $p = $role_presets[$role_id];
        $_SESSION['user_id'] = $p['user_id'];
        $_SESSION['role_id'] = $p['role_id'];
        $_SESSION['role_name'] = $p['role_name'];
        $_SESSION['full_name'] = $p['full_name'];
        $_SESSION['username'] = $p['username'];
        $_SESSION['email'] = $p['email'];

        if ($conn) {
            log_system_audit($conn, $p['user_id'], "DEMO_ROLE_SWITCH: Switched active role to {$p['role_name']}", "ROLES", $role_id, [
                'switched_to' => $p['role_name'],
                'user' => $p['full_name']
            ]);
        }

        return get_role_home_page($p['role_name']);
    }
}

/**
 * Enforces role access. If unauthorized, halts execution and renders 403 screen.
 */
if (!function_exists('check_role_access')) {
    function check_role_access(array $allowed_roles) {
        if (!has_role_access($allowed_roles)) {
            render_403_screen($allowed_roles);
            exit;
        }
    }
}

if (!function_exists('render_403_screen')) {
    function render_403_screen(array $allowed_roles) {
    http_response_code(403);
    $current_role = get_current_user_role();
    $user_name = $_SESSION['username'] ?? $_SESSION['full_name'] ?? 'FIA Officer';
    $user_email = $_SESSION['email'] ?? 'delegate@fia.internal';
    $req_roles_str = strtoupper(implode(' / ', array_map('normalize_role_name', $allowed_roles)));
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>FIA FSMS - 403 ACCESS DENIED</title>
        <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@500;700;900&family=Share+Tech+Mono&family=Titillium+Web:wght@400;600;700&display=swap" rel="stylesheet">
        <style>
            :root {
                --f1-red: #ff1801;
                --f1-cyan: #00d2be;
                --f1-dark: #07070e;
                --f1-panel: rgba(14, 14, 24, 0.96);
            }
            * { box-sizing: border-box; margin: 0; padding: 0; }
            body {
                background-color: var(--f1-dark);
                background-image: 
                    radial-gradient(circle at 50% 50%, rgba(255, 24, 1, 0.12) 0%, transparent 60%),
                    linear-gradient(rgba(255, 255, 255, 0.02) 1px, transparent 1px),
                    linear-gradient(90deg, rgba(255, 255, 255, 0.02) 1px, transparent 1px);
                background-size: 100% 100%, 28px 28px, 28px 28px;
                color: #d1d8e0;
                font-family: 'Titillium Web', sans-serif;
                min-height: 100vh;
                display: flex;
                align-items: center;
                justify-content: center;
                padding: 24px;
            }
            .denied-card {
                background: var(--f1-panel);
                border: 2px solid var(--f1-red);
                border-radius: 12px;
                max-width: 620px;
                width: 100%;
                padding: 32px;
                box-shadow: 0 0 50px rgba(255, 24, 1, 0.6), inset 0 0 25px rgba(255, 24, 1, 0.25);
                text-align: center;
                position: relative;
                overflow: hidden;
                backdrop-filter: blur(20px);
                animation: shakeCard 0.4s ease-in-out;
            }
            @keyframes shakeCard {
                0%, 100% { transform: translateX(0); }
                20%, 60% { transform: translateX(-8px); }
                40%, 80% { transform: translateX(8px); }
            }
            .denied-card::before {
                content: '';
                position: absolute;
                top: 0; left: 0; width: 100%; height: 4px;
                background: linear-gradient(90deg, transparent, #ff1801, transparent);
                animation: scanBar 2s infinite linear;
            }
            @keyframes scanBar {
                0% { transform: translateX(-100%); }
                100% { transform: translateX(100%); }
            }
            .denied-icon {
                font-size: 48px;
                margin-bottom: 12px;
                filter: drop-shadow(0 0 15px #ff1801);
            }
            .denied-code {
                font-family: 'Share Tech Mono', monospace;
                font-size: 13px;
                color: #ff4757;
                letter-spacing: 3px;
                font-weight: 700;
                margin-bottom: 6px;
            }
            .denied-title {
                font-family: 'Orbitron', sans-serif;
                font-size: 24px;
                font-weight: 900;
                color: #ffffff;
                letter-spacing: 1.5px;
                text-shadow: 0 0 20px rgba(255, 24, 1, 0.8);
                margin-bottom: 18px;
            }
            .denied-diagnostics {
                background: rgba(0, 0, 0, 0.6);
                border: 1px solid rgba(255, 255, 255, 0.08);
                border-radius: 8px;
                padding: 18px;
                text-align: left;
                font-family: 'Share Tech Mono', monospace;
                font-size: 11.5px;
                margin-bottom: 24px;
                line-height: 1.8;
            }
            .diag-row {
                display: flex;
                justify-content: space-between;
                border-bottom: 1px dashed rgba(255, 255, 255, 0.06);
                padding: 4px 0;
            }
            .diag-label { color: #8c8c9e; }
            .diag-val { color: #ffffff; font-weight: 700; }
            .diag-val.role { color: var(--f1-cyan); }
            .diag-val.required { color: var(--f1-red); }
            .denied-actions {
                display: flex;
                gap: 12px;
                justify-content: center;
                flex-wrap: wrap;
            }
            .btn-f1-back {
                background: linear-gradient(90deg, #ff1801 0%, #d63031 100%);
                border: none;
                color: #ffffff;
                font-family: 'Orbitron', sans-serif;
                font-size: 11px;
                font-weight: 700;
                padding: 12px 24px;
                border-radius: 6px;
                text-decoration: none;
                display: inline-flex;
                align-items: center;
                gap: 8px;
                box-shadow: 0 0 20px rgba(255, 24, 1, 0.5);
                transition: all 0.2s ease;
            }
            .btn-f1-back:hover {
                box-shadow: 0 0 30px rgba(255, 24, 1, 0.8);
                transform: translateY(-1px);
            }
            .btn-f1-switch {
                background: rgba(255, 255, 255, 0.06);
                border: 1px solid rgba(255, 255, 255, 0.2);
                color: #cbd5e1;
                font-family: 'Orbitron', sans-serif;
                font-size: 11px;
                font-weight: 700;
                padding: 12px 20px;
                border-radius: 6px;
                text-decoration: none;
                display: inline-flex;
                align-items: center;
                gap: 8px;
                transition: all 0.2s ease;
            }
            .btn-f1-switch:hover {
                background: rgba(255, 255, 255, 0.12);
                color: #ffffff;
                border-color: var(--f1-cyan);
            }
        </style>
    </head>
    <body>
        <div class="denied-card">
            <div class="denied-icon">🛑</div>
            <div class="denied-code">FIA SECURITY PROTOCOL // ERROR 403</div>
            <h1 class="denied-title">UNAUTHORIZED CREDENTIALS</h1>
            
            <div class="denied-diagnostics">
                <div class="diag-row">
                    <span class="diag-label">AUTHENTICATED USER:</span>
                    <span class="diag-val"><?php echo htmlspecialchars($user_name); ?> (<?php echo htmlspecialchars($user_email); ?>)</span>
                </div>
                <div class="diag-row">
                    <span class="diag-label">ASSIGNED ROLE TIER:</span>
                    <span class="diag-val role"><?php echo strtoupper(htmlspecialchars($current_role)); ?></span>
                </div>
                <div class="diag-row">
                    <span class="diag-label">MANDATORY ROLE ACCESS:</span>
                    <span class="diag-val required"><?php echo htmlspecialchars($req_roles_str); ?></span>
                </div>
                <div class="diag-row">
                    <span class="diag-label">SECURITY DIRECTIVE:</span>
                    <span class="diag-val" style="color: #f1c40f;">STRICT ROLE SEGREGATION ENFORCED</span>
                </div>
            </div>

            <div class="denied-actions">
                <a href="<?php echo htmlspecialchars(get_role_home_page($current_role)); ?>" class="btn-f1-back">
                    <span>←</span> RETURN TO AUTHORIZED HUB
                </a>
                <a href="logout.php" class="btn-f1-switch">
                    <span>⏻</span> SWITCH CREDENTIALS (LOGOUT)
                </a>
            </div>
        </div>
    </body>
    </html>
    <?php
    }
}
