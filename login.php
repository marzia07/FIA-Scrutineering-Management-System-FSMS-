<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once 'db.php';

// Check for "Remember Me" Cookie Auto-Login
if (!isset($_SESSION['user_id']) && isset($_COOKIE['f1_remember_user'])) {
    $remembered_email = $_COOKIE['f1_remember_user'];
    $stmt = $conn->prepare("SELECT u.*, r.role_name 
                            FROM USERS u 
                            LEFT JOIN ROLES r ON u.role_id = r.role_id 
                            WHERE u.email = ? LIMIT 1");
    if ($stmt) {
        $stmt->bind_param("s", $remembered_email);
        $stmt->execute();
        $res = $stmt->get_result();
        if ($res && $res->num_rows === 1) {
            $user = $res->fetch_assoc();
            $_SESSION['user_id'] = $user['user_id'];
            $_SESSION['full_name'] = $user['full_name'] ?? 'FIA Delegate';
            $_SESSION['username'] = $user['full_name'] ?? 'FIA Delegate';
            $_SESSION['email'] = $user['email'];
            $_SESSION['role_id'] = intval($user['role_id'] ?? 2);
            $user_role_name = normalize_role_name($user['role_name'] ?? 'inspector');
            $_SESSION['role_name'] = $user_role_name;
            $_SESSION['is_admin_root'] = ($user_role_name === 'admin');
            $dest = get_role_home_page($user_role_name);
            header("Location: " . $dest);
            exit;
        }
    }
}

$error = "";
$success = "";

// Handle Registration
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action']) && $_POST['action'] === 'register') {
    $reg_name = trim($_POST['full_name'] ?? '');
    $reg_email = trim($_POST['email'] ?? '');
    $reg_pass = trim($_POST['password'] ?? '');
    $reg_role_id = intval($_POST['role_id'] ?? 2);

    // Strictly forbid Admin role creation via signup
    if ($reg_role_id === 1) {
        $error = "ACCESS PROTOCOL: Admin (Race Director) accounts cannot be registered publicly. Please sign in or contact FIA Central Administration.";
    } elseif ($reg_role_id < 2 || $reg_role_id > 4) {
        $reg_role_id = 2;
    }

    if (empty($error)) {
        if (empty($reg_name) || empty($reg_email) || empty($reg_pass)) {
            $error = "REGISTRATION REJECTED: All fields are mandatory.";
        } elseif (strpos($reg_email, '@admin.fia.com') !== false || strpos($reg_email, '@racecontrol.fia.com') !== false) {
            $error = "SECURITY PROTOCOL: Admin email domains are strictly restricted and cannot be registered publicly.";
        } else {
            // Enforce mandatory domain per role
            $domain_err = validate_role_email_domain($reg_email, $reg_role_id);
            if ($domain_err) {
                $error = "CREDENTIAL PROTOCOL: " . $domain_err;
            } else {
                $check_stmt = $conn->prepare("SELECT user_id FROM USERS WHERE email = ?");
                $check_stmt->bind_param("s", $reg_email);
                $check_stmt->execute();
                if ($check_stmt->get_result()->num_rows > 0) {
                    $error = "TELEMETRY CONFLICT: Delegate credentials already exist.";
                } else {
                    $ins = $conn->prepare("INSERT INTO USERS (full_name, email, password_hash, role_id, status) VALUES (?, ?, ?, ?, 'active')");
                    $ins->bind_param("sssi", $reg_name, $reg_email, $reg_pass, $reg_role_id);
                    if ($ins->execute()) {
                        $success = "FIA CLEARANCE ISSUED: Delegate credentials confirmed with role tier. Please log in.";
                    } else {
                        $error = "DATABASE ERROR: " . $conn->error;
                    }
                }
            }
        }
    }
}

// Handle Login
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action']) && $_POST['action'] === 'login') {
    $email = trim($_POST['email'] ?? '');
    $password = trim($_POST['password'] ?? '');
    $remember = isset($_POST['remember_me']);

    if (empty($email) || empty($password)) {
        $error = "ACCESS DENIED: Credentials missing.";
    } else {
        $stmt = $conn->prepare("SELECT u.*, r.role_name 
                                FROM USERS u 
                                LEFT JOIN ROLES r ON u.role_id = r.role_id 
                                WHERE u.email = ? LIMIT 1");
        if ($stmt) {
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $res = $stmt->get_result();

            if ($res && $res->num_rows === 1) {
                $user = $res->fetch_assoc();
                $stored_pass = $user['password_hash'] ?? $user['password'] ?? '';
                
                if ($password === $stored_pass || password_verify($password, $stored_pass)) {
                    $_SESSION['user_id'] = $user['user_id'];
                    $_SESSION['full_name'] = $user['full_name'] ?? 'FIA Delegate';
                    $_SESSION['username'] = $user['full_name'] ?? 'FIA Delegate';
                    $_SESSION['email'] = $user['email'];
                    $_SESSION['role_id'] = intval($user['role_id'] ?? 2);
                    $user_role_name = normalize_role_name($user['role_name'] ?? 'inspector');
                    $_SESSION['role_name'] = $user_role_name;
                    $_SESSION['is_admin_root'] = ($user_role_name === 'admin');

                    if ($remember) {
                        setcookie('f1_remember_user', $email, time() + (86400 * 30), "/");
                    }
                    
                    $dest = get_role_home_page($user_role_name);
                    header("Location: " . $dest);
                    exit;
                } else {
                    $error = "SECURITY ALERT: Invalid security passkey.";
                }
            } else {
                $error = "UNAUTHORIZED: Unrecognized FIA Delegate ID.";
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>FSMS | FIA Scrutineering Telemetry Gateway</title>
    <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@500;700;900&family=Share+Tech+Mono&family=Titillium+Web:wght@300;400;600;700;900&display=swap" rel="stylesheet">
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        
        body {
            background-color: #020205;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
            position: relative;
            font-family: 'Titillium Web', sans-serif;
            color: #ffffff;
            perspective: 1000px;
        }

        /* 3D Dynamic Racing Grid Floor */
        .grid-floor {
            position: absolute;
            bottom: -30%;
            left: -50%;
            width: 200%;
            height: 100%;
            background-image: 
                linear-gradient(rgba(255, 24, 1, 0.25) 1px, transparent 1px),
                linear-gradient(90deg, rgba(0, 210, 190, 0.2) 1px, transparent 1px);
            background-size: 60px 60px;
            transform: rotateX(75deg);
            animation: gridScroll 10s linear infinite;
            z-index: 1;
            mask-image: linear-gradient(to top, rgba(0,0,0,1) 0%, rgba(0,0,0,0) 80%);
            -webkit-mask-image: linear-gradient(to top, rgba(0,0,0,1) 0%, rgba(0,0,0,0) 80%);
        }

        @keyframes gridScroll {
            0% { transform: rotateX(75deg) translateY(0); }
            100% { transform: rotateX(75deg) translateY(60px); }
        }

        /* Ambient Lights */
        .ambient-orb-red {
            position: absolute;
            width: 800px;
            height: 800px;
            background: radial-gradient(circle, rgba(255, 24, 1, 0.22) 0%, transparent 65%);
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            z-index: 0;
            filter: blur(50px);
            pointer-events: none;
        }

        /* Left and Right Side Telemetry HUD Gauges */
        .side-hud {
            position: absolute;
            top: 50%;
            transform: translateY(-50%);
            font-family: 'Share Tech Mono', monospace;
            font-size: 11px;
            color: #6d6d84;
            letter-spacing: 2px;
            display: flex;
            flex-direction: column;
            gap: 22px;
            z-index: 5;
            user-select: none;
        }
        .hud-left { left: 40px; }
        .hud-right { right: 40px; text-align: right; }
        .hud-item {
            background: rgba(14, 14, 22, 0.7);
            border: 1px solid rgba(255, 255, 255, 0.06);
            padding: 12px 18px;
            border-radius: 6px;
            backdrop-filter: blur(10px);
        }
        .hud-val {
            font-family: 'Orbitron', sans-serif;
            font-size: 18px;
            font-weight: 900;
            color: #00d2be;
            margin-top: 4px;
            text-shadow: 0 0 10px rgba(0, 210, 190, 0.6);
        }
        .hud-val.red { color: #ff1801; text-shadow: 0 0 10px rgba(255, 24, 1, 0.6); }

        /* The Central Holographic Command Box */
        .cockpit-container {
            position: relative;
            z-index: 10;
            width: 100%;
            max-width: 520px;
            padding: 3px;
            background: linear-gradient(135deg, #ff1801 0%, rgba(0, 210, 190, 0.7) 50%, #ff1801 100%);
            border-radius: 20px;
            box-shadow: 0 0 60px rgba(255, 24, 1, 0.4), 0 35px 100px rgba(0, 0, 0, 0.95);
        }

        .cockpit-card {
            background: rgba(10, 10, 16, 0.96);
            backdrop-filter: blur(30px);
            border-radius: 18px;
            padding: 40px 38px;
            position: relative;
            overflow: hidden;
        }

        /* Top RPM Shift Lights */
        .rpm-bar {
            display: flex;
            justify-content: center;
            gap: 6px;
            margin-bottom: 22px;
        }
        .rpm-led {
            width: 16px;
            height: 6px;
            border-radius: 2px;
            background: #191924;
        }
        .rpm-led.green { background: #2ecc71; box-shadow: 0 0 8px #2ecc71; }
        .rpm-led.red { background: #ff1801; box-shadow: 0 0 8px #ff1801; }
        .rpm-led.blue { background: #00d2be; box-shadow: 0 0 10px #00d2be; animation: flashLed 0.5s infinite alternate; }
        @keyframes flashLed { 0% { opacity: 0.3; } 100% { opacity: 1; } }

        /* Radar Telemetry Line */
        .cockpit-card::before {
            content: '';
            position: absolute;
            top: -100%; left: 0; width: 100%; height: 50%;
            background: linear-gradient(180deg, transparent, rgba(255, 24, 1, 0.12), transparent);
            animation: scanRadar 5s ease-in-out infinite;
            pointer-events: none;
        }
        @keyframes scanRadar { 0% { top: -100%; } 100% { top: 150%; } }

        /* Header Details */
        .title-badge {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: rgba(255, 24, 1, 0.15);
            border: 1px solid rgba(255, 24, 1, 0.5);
            padding: 4px 14px;
            border-radius: 30px;
            font-family: 'Share Tech Mono', monospace;
            font-size: 11px;
            color: #ff4d4d;
            letter-spacing: 2px;
            margin-bottom: 12px;
        }
        .system-heading {
            font-family: 'Orbitron', sans-serif;
            font-size: 20px;
            font-weight: 900;
            text-transform: uppercase;
            letter-spacing: 1.5px;
            line-height: 1.35;
            color: #ffffff;
            text-shadow: 0 0 15px rgba(255, 255, 255, 0.3);
            text-align: center;
            margin-bottom: 24px;
        }

        /* Form Styling */
        .form-group {
            margin-bottom: 18px;
        }
        .label-row {
            display: flex;
            justify-content: space-between;
            font-family: 'Share Tech Mono', monospace;
            font-size: 11px;
            font-weight: 700;
            color: #8c8c9e;
            letter-spacing: 1.5px;
            margin-bottom: 8px;
            text-transform: uppercase;
        }
        .label-row span.accent { color: #00d2be; }

        .input-shell {
            position: relative;
            display: flex;
            align-items: center;
        }
        .input-shell input {
            width: 100%;
            padding: 14px 18px;
            background: #06060a;
            border: 1px solid #222232;
            border-radius: 8px;
            color: #ffffff;
            font-family: 'Titillium Web', sans-serif;
            font-size: 14px;
            letter-spacing: 0.5px;
            transition: all 0.3s cubic-bezier(0.165, 0.84, 0.44, 1);
        }
        .input-shell input:focus {
            outline: none;
            border-color: #ff1801;
            background: #0c0c14;
            box-shadow: 0 0 20px rgba(255, 24, 1, 0.5), inset 0 0 10px rgba(255, 24, 1, 0.2);
        }

        .pwd-toggle-btn {
            position: absolute;
            right: 14px;
            background: transparent;
            border: none;
            color: #6d6d84;
            cursor: pointer;
            font-size: 16px;
            transition: 0.2s;
        }
        .pwd-toggle-btn:hover { color: #00d2be; }

        .aux-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 12px;
            margin-bottom: 22px;
        }
        .check-container {
            display: flex;
            align-items: center;
            gap: 8px;
            color: #9292a4;
            cursor: pointer;
        }
        .check-container input { accent-color: #ff1801; }
        .help-link {
            color: #6d6d84;
            font-family: 'Share Tech Mono', monospace;
            text-decoration: none;
            font-size: 11px;
        }
        .help-link:hover { color: #00d2be; }

        /* Neon Ignition Button */
        .btn-launch-terminal {
            width: 100%;
            background: linear-gradient(135deg, #ff1801 0%, #b80000 100%);
            color: #ffffff;
            border: none;
            padding: 15px;
            border-radius: 8px;
            font-family: 'Orbitron', sans-serif;
            font-size: 13px;
            font-weight: 900;
            letter-spacing: 2px;
            text-transform: uppercase;
            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: 0 8px 25px rgba(255, 24, 1, 0.5);
        }
        .btn-launch-terminal:hover {
            transform: translateY(-2px);
            box-shadow: 0 12px 35px rgba(255, 24, 1, 0.85), 0 0 20px #ff1801;
            letter-spacing: 2.5px;
        }

        /* Fast SSO Connectors */
        .sso-divider {
            display: flex;
            align-items: center;
            text-align: center;
            margin: 24px 0 18px;
            color: #4a4a62;
            font-family: 'Share Tech Mono', monospace;
            font-size: 11px;
            letter-spacing: 1.5px;
        }
        .sso-divider::before, .sso-divider::after {
            content: '';
            flex: 1;
            border-bottom: 1px solid #1a1a27;
        }
        .sso-divider span { padding: 0 12px; }

        .sso-options {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px;
        }
        .sso-button {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            background: #090910;
            border: 1px solid #1e1e2d;
            color: #d1d1e0;
            padding: 11px;
            border-radius: 8px;
            font-size: 12px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.25s;
        }
        .sso-button:hover {
            background: #141422;
            border-color: #00d2be;
            color: #ffffff;
            box-shadow: 0 0 14px rgba(0, 210, 190, 0.3);
        }

        .switch-footer {
            text-align: center;
            margin-top: 22px;
            font-size: 13px;
            color: #8c8c9e;
        }
        .switch-action {
            color: #00d2be;
            font-weight: 700;
            cursor: pointer;
            text-decoration: underline;
        }

        .alert-panel {
            padding: 12px 16px;
            border-radius: 8px;
            font-size: 13px;
            margin-bottom: 18px;
            font-family: 'Share Tech Mono', monospace;
        }
        .alert-red {
            background: rgba(255, 24, 1, 0.18);
            border-left: 4px solid #ff1801;
            color: #ff6b6b;
            box-shadow: 0 0 15px rgba(255, 24, 1, 0.3);
        }
        .alert-cyan {
            background: rgba(0, 210, 190, 0.18);
            border-left: 4px solid #00d2be;
            color: #00d2be;
            box-shadow: 0 0 15px rgba(0, 210, 190, 0.3);
        }
    </style>
</head>
<body>

<div class="ambient-orb-red"></div>
<div class="grid-floor"></div>

<!-- Live Side Telemetry HUD Readings -->
<div class="side-hud hud-left">
    <div class="hud-item">
        <div>CORE ENGINE TELEMETRY</div>
        <div class="hud-val">12,450 RPM</div>
    </div>
    <div class="hud-item">
        <div>TURBO MGU-H BOOST</div>
        <div class="hud-val">3.85 BAR</div>
    </div>
    <div class="hud-item">
        <div>LATERAL G-FORCE</div>
        <div class="hud-val red">+4.2 G</div>
    </div>
</div>

<div class="side-hud hud-right">
    <div class="hud-item">
        <div>TIRE BLISTER INDEX</div>
        <div class="hud-val">0.04 %</div>
    </div>
    <div class="hud-item">
        <div>DRS REAR WING FLAP</div>
        <div class="hud-val red">ACTIVE</div>
    </div>
    <div class="hud-item">
        <div>HYDRAULIC PRESSURE</div>
        <div class="hud-val">205 BAR</div>
    </div>
</div>

<!-- Central Cockpit Card -->
<div class="cockpit-container">
    <div class="cockpit-card">
        
        <!-- F1 Shift Lights Bar -->
        <div class="rpm-bar">
            <div class="rpm-led green"></div>
            <div class="rpm-led green"></div>
            <div class="rpm-led green"></div>
            <div class="rpm-led red"></div>
            <div class="rpm-led red"></div>
            <div class="rpm-led red"></div>
            <div class="rpm-led blue"></div>
            <div class="rpm-led blue"></div>
        </div>

        <div style="text-align: center;">
            <div class="title-badge">
                <span>FIA TECHNICAL SCRUTINEERING GATEWAY // V3.0</span>
            </div>
            <h1 class="system-heading" id="form-heading">FIA Scrutineering Management System (FSMS)</h1>
        </div>

        <?php if($error): ?>
            <div class="alert-panel alert-red"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        <?php if($success): ?>
            <div class="alert-panel alert-cyan"><?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>

        <!-- SIGN IN FORM -->
        <form method="POST" action="login.php" id="loginForm">
            <input type="hidden" name="action" value="login">
            
            <div class="demo-quick-roles" style="display: flex; gap: 6px; margin-bottom: 18px; flex-wrap: wrap; justify-content: center;">
                <button type="button" class="btn-role-preset" onclick="fillCredentials('admin@racecontrol.fia.com', 'pass123')" style="background: rgba(255, 24, 1, 0.15); border: 1px solid #ff1801; color: #ff6b6b; padding: 5px 10px; border-radius: 4px; font-family: 'Share Tech Mono', monospace; font-size: 11px; cursor: pointer; transition: all 0.2s;">
                    👑 Admin
                </button>
                <button type="button" class="btn-role-preset" onclick="fillCredentials('bauer@inspector.fia.com', 'pass123')" style="background: rgba(0, 210, 190, 0.15); border: 1px solid #00d2be; color: #00d2be; padding: 5px 10px; border-radius: 4px; font-family: 'Share Tech Mono', monospace; font-size: 11px; cursor: pointer; transition: all 0.2s;">
                    🛠️ Inspector
                </button>
                <button type="button" class="btn-role-preset" onclick="fillCredentials('steward@stewards.fia.com', 'pass123')" style="background: rgba(255, 159, 26, 0.15); border: 1px solid #ff9f1a; color: #ff9f1a; padding: 5px 10px; border-radius: 4px; font-family: 'Share Tech Mono', monospace; font-size: 11px; cursor: pointer; transition: all 0.2s;">
                    ⚖️ Steward
                </button>
                <button type="button" class="btn-role-preset" onclick="fillCredentials('horner@redbull.f1team.com', 'pass123')" style="background: rgba(155, 89, 182, 0.15); border: 1px solid #9b59b6; color: #d6a2e8; padding: 5px 10px; border-radius: 4px; font-family: 'Share Tech Mono', monospace; font-size: 11px; cursor: pointer; transition: all 0.2s;">
                    🏎️ Team Rep
                </button>
            </div>

            <div class="form-group">
                <div class="label-row">
                    <span>Delegate Access ID</span>
                    <span class="accent">ENCRYPTED</span>
                </div>
                <div class="input-shell">
                    <input type="email" name="email" id="login_email" value="<?php echo htmlspecialchars($_COOKIE['f1_remember_user'] ?? 'admin@racecontrol.fia.com'); ?>" placeholder="admin@racecontrol.fia.com" required autocomplete="email">
                </div>
            </div>

            <div class="form-group">
                <div class="label-row">
                    <span>Security Passkey</span>
                    <span class="accent">SHA-256</span>
                </div>
                <div class="input-shell">
                    <input type="password" name="password" id="login_password" value="pass123" placeholder="••••••••" required>
                    <button type="button" class="pwd-toggle-btn" onclick="togglePassword('login_password', this)">👁️</button>
                </div>
            </div>

            <div class="aux-row">
                <label class="check-container">
                    <input type="checkbox" name="remember_me" checked> Maintain Telemetry Uplink
                </label>
                <a href="javascript:void(0)" class="help-link" onclick="alert('Contact the FIA Central Race Director to issue a key reset token.')">Reset Passkey?</a>
            </div>

            <button type="submit" class="btn-launch-terminal">Authenticate</button>

            <div class="sso-divider"><span>OR FAST CONNECT WITH</span></div>

            <div class="sso-options">
                <button type="button" class="sso-button" onclick="oauthLogin('Google')">
                    <span>🌐</span> Google SSO
                </button>
                <button type="button" class="sso-button" onclick="oauthLogin('Apple')">
                    <span>🍏</span> Apple ID
                </button>
            </div>

            <div class="switch-footer">
                New Technical Delegate? <span class="switch-action" onclick="toggleForms(true)">Sign Up</span>
            </div>
        </form>

        <!-- REGISTRATION FORM -->
        <form method="POST" action="login.php" id="registerForm" style="display: none;">
            <input type="hidden" name="action" value="register">

            <div class="form-group">
                <div class="label-row"><span>Delegate Full Name</span></div>
                <div class="input-shell">
                    <input type="text" name="full_name" placeholder="e.g. Scrutineer Marzia" required>
                </div>
            </div>

            <div class="form-group">
                <div class="label-row"><span>Assigned FIA Role Credential</span></div>
                <div class="input-shell">
                    <select name="role_id" id="regRoleId" onchange="updateRoleDomainGuidance(this.value)" style="width: 100%; background: transparent; border: none; color: #ffffff; font-family: 'Titillium Web', sans-serif; font-size: 13px; outline: none;">
                        <option value="2" style="background: #111122; color: #00d2be;" selected>Inspector / Scrutineer (@inspector.fia.com)</option>
                        <option value="3" style="background: #111122; color: #ff9f1a;">Steward (Judicial Panel) (@stewards.fia.com)</option>
                        <option value="4" style="background: #111122; color: #9b59b6;">Team Representative (Constructor) (@f1team.com)</option>
                    </select>
                </div>
            </div>

            <div class="form-group">
                <div class="label-row">
                    <span>Mandatory Role Email</span>
                    <span id="roleDomainHint" style="font-family: 'Share Tech Mono', monospace; font-size: 10px; color: #00d2be;">Must end in @inspector.fia.com</span>
                </div>
                <div class="input-shell">
                    <input type="email" name="email" id="regEmailInput" placeholder="delegate@inspector.fia.com" required>
                </div>
            </div>

            <div class="form-group">
                <div class="label-row"><span>Generate Security Key</span></div>
                <div class="input-shell">
                    <input type="password" name="password" id="reg_password" placeholder="••••••••" required>
                    <button type="button" class="pwd-toggle-btn" onclick="togglePassword('reg_password', this)">👁️</button>
                </div>
            </div>

            <button type="submit" class="btn-launch-terminal" style="background: linear-gradient(135deg, #00d2be 0%, #008f82 100%);">Sign Up</button>

            <div class="switch-footer">
                Already registered? <span class="switch-action" onclick="toggleForms(false)">Sign In</span>
            </div>
        </form>
    </div>
</div>

<script>
// Formula 1 Web Audio Synthesizer
let audioCtx = null;
function getAudioContext() {
    if (!audioCtx) {
        audioCtx = new (window.AudioContext || window.webkitAudioContext)();
    }
    return audioCtx;
}

// 1. F1 Engine Acceleration / Rev Flyby
function playF1EngineRev(callback) {
    try {
        const ctx = getAudioContext();
        if (ctx.state === 'suspended') ctx.resume();
        const now = ctx.currentTime;
        const duration = 1.4;

        // V6 Engine Primary Harmonics
        const osc1 = ctx.createOscillator();
        const gain1 = ctx.createGain();
        osc1.type = 'sawtooth';
        osc1.frequency.setValueAtTime(160, now);
        osc1.frequency.exponentialRampToValueAtTime(750, now + 0.7);
        osc1.frequency.exponentialRampToValueAtTime(920, now + 1.0);
        osc1.frequency.exponentialRampToValueAtTime(300, now + duration);

        gain1.gain.setValueAtTime(0.01, now);
        gain1.gain.linearRampToValueAtTime(0.3, now + 0.3);
        gain1.gain.linearRampToValueAtTime(0.38, now + 0.7);
        gain1.gain.exponentialRampToValueAtTime(0.001, now + duration);

        // Sub Exhaust Rumble
        const osc2 = ctx.createOscillator();
        const gain2 = ctx.createGain();
        osc2.type = 'triangle';
        osc2.frequency.setValueAtTime(75, now);
        osc2.frequency.exponentialRampToValueAtTime(380, now + 0.7);
        osc2.frequency.exponentialRampToValueAtTime(140, now + duration);

        gain2.gain.setValueAtTime(0.01, now);
        gain2.gain.linearRampToValueAtTime(0.32, now + 0.3);
        gain2.gain.exponentialRampToValueAtTime(0.001, now + duration);

        // Turbo Whistle & Air Rush
        const bufferSize = ctx.sampleRate * duration;
        const buffer = ctx.createBuffer(1, bufferSize, ctx.sampleRate);
        const data = buffer.getChannelData(0);
        for (let i = 0; i < bufferSize; i++) data[i] = Math.random() * 2 - 1;

        const noise = ctx.createBufferSource();
        noise.buffer = buffer;
        const filter = ctx.createBiquadFilter();
        filter.type = 'bandpass';
        filter.frequency.setValueAtTime(1100, now);
        filter.frequency.exponentialRampToValueAtTime(3400, now + 0.7);
        filter.Q.value = 3.5;

        const noiseGain = ctx.createGain();
        noiseGain.gain.setValueAtTime(0.001, now);
        noiseGain.gain.linearRampToValueAtTime(0.14, now + 0.4);
        noiseGain.gain.exponentialRampToValueAtTime(0.001, now + duration);

        osc1.connect(gain1); gain1.connect(ctx.destination);
        osc2.connect(gain2); gain2.connect(ctx.destination);
        noise.connect(filter); filter.connect(noiseGain); noiseGain.connect(ctx.destination);

        osc1.start(now); osc2.start(now); noise.start(now);
        osc1.stop(now + duration); osc2.stop(now + duration); noise.stop(now + duration);

        if (callback) setTimeout(callback, 800);
    } catch(e) {
        if (callback) callback();
    }
}

// 2. F1 Radio Chirp Beep
function playF1RadioChirp() {
    try {
        const ctx = getAudioContext();
        if (ctx.state === 'suspended') ctx.resume();
        const now = ctx.currentTime;
        const osc = ctx.createOscillator();
        const gain = ctx.createGain();
        osc.type = 'sine';
        osc.frequency.setValueAtTime(1050, now);
        osc.frequency.setValueAtTime(1400, now + 0.05);
        gain.gain.setValueAtTime(0.2, now);
        gain.gain.exponentialRampToValueAtTime(0.001, now + 0.12);
        osc.connect(gain); gain.connect(ctx.destination);
        osc.start(now); osc.stop(now + 0.12);
    } catch(e) {}
}

function togglePassword(inputId, btn) {
    playF1RadioChirp();
    const input = document.getElementById(inputId);
    if (input.type === "password") {
        input.type = "text";
        btn.textContent = "🔒";
    } else {
        input.type = "password";
        btn.textContent = "👁️";
    }
}

function toggleForms(showRegister) {
    playF1RadioChirp();
    document.getElementById('loginForm').style.display = showRegister ? 'none' : 'block';
    document.getElementById('registerForm').style.display = showRegister ? 'block' : 'none';
    document.getElementById('form-heading').textContent = showRegister ? 'FSMS DELEGATE CLEARANCE' : 'FIA Scrutineering Management System (FSMS)';
}

function updateRoleDomainGuidance(roleId) {
    const hint = document.getElementById('roleDomainHint');
    const emailInput = document.getElementById('regEmailInput');
    
    if (roleId === '2') {
        hint.textContent = 'Must end in @inspector.fia.com or @scrutineering.fia.com';
        hint.style.color = '#00d2be';
        emailInput.placeholder = 'delegate@inspector.fia.com';
    } else if (roleId === '3') {
        hint.textContent = 'Must end in @stewards.fia.com';
        hint.style.color = '#ff9f1a';
        emailInput.placeholder = 'panel@stewards.fia.com';
    } else if (roleId === '4') {
        hint.textContent = 'Must be team constructor domain (e.g. @f1team.com)';
        hint.style.color = '#9b59b6';
        emailInput.placeholder = 'engineer@redbull.f1team.com';
    }
}

function fillCredentials(email, pass) {
    playF1RadioChirp();
    const emailInput = document.getElementById('login_email');
    const passInput = document.getElementById('login_password');
    if (emailInput) emailInput.value = email;
    if (passInput) passInput.value = pass;
}

function oauthLogin(provider) {
    playF1EngineRev(() => {
        document.getElementById('login_email').value = 'admin@racecontrol.fia.com';
        document.getElementById('login_password').value = 'pass123';
        document.getElementById('loginForm').submit();
    });
}

// Intercept login submit to play engine rev sound
document.addEventListener('DOMContentLoaded', () => {
    const loginForm = document.getElementById('loginForm');
    const authBtn = loginForm.querySelector('.btn-launch-terminal');

    loginForm.addEventListener('submit', (e) => {
        if (!loginForm.dataset.soundPlayed) {
            e.preventDefault();
            loginForm.dataset.soundPlayed = "true";
            authBtn.textContent = '🏎️ ACCELERATING...';
            authBtn.style.background = 'linear-gradient(135deg, #00d2be 0%, #008f82 100%)';
            playF1EngineRev(() => {
                loginForm.submit();
            });
        }
    });
});
</script>

</body>
</html>