<?php
// Detect active page for lighting up active button
$current_page = basename($_SERVER['PHP_SELF']);

if (!isset($conn) && file_exists('db.php')) {
    require_once 'db.php';
}

$active_red_alert = null;
if (isset($conn)) {
    // Check for unread, high-priority emergency notifications in the last 15 minutes
    $check_table = $conn->query("SHOW TABLES LIKE 'NOTIFICATIONS'");
    if ($check_table && $check_table->num_rows > 0) {
        $alert_stmt = $conn->query("SELECT notification_id, title, message, category, target_url, created_at 
                                    FROM NOTIFICATIONS 
                                    WHERE category IN ('VIOLATION', 'PENALTY') 
                                    AND is_read = 0 
                                    AND created_at >= NOW() - INTERVAL 15 MINUTE 
                                    ORDER BY notification_id DESC LIMIT 1");
        if ($alert_stmt && $alert_stmt->num_rows > 0) {
            $active_red_alert = $alert_stmt->fetch_assoc();
        }
    }
}
?>

<?php if ($active_red_alert): ?>
<!-- GLOBAL EMERGENCY RED ALERT DISCO TAKEOVER -->
<div class="red-alert-disco-curtain" id="redAlertCurtain">
    <div class="disco-strobe-overlay"></div>
    <div class="disco-scanner"></div>

    <div class="red-alert-banner" id="redAlertBanner">
        <div class="alert-beacon-row">
            <span class="alert-beacon">🚨</span>
            <span class="alert-tag">FIA EMERGENCY BROADCAST</span>
            <span class="alert-beacon">🚨</span>
        </div>

        <div class="alert-main-title">
            RED ALERT // TEAM ATTENTION
        </div>

        <div class="alert-sub-neon">
            <span class="alert-incident-type">[<?php echo htmlspecialchars($active_red_alert['category']); ?>]</span>
            <span class="alert-incident-headline"><?php echo htmlspecialchars($active_red_alert['title']); ?></span>
        </div>

        <div class="alert-desc-text">
            <?php echo htmlspecialchars($active_red_alert['message']); ?>
        </div>

        <div class="alert-action-buttons">
            <?php if (!empty($active_red_alert['target_url'])): ?>
                <a href="<?php echo htmlspecialchars($active_red_alert['target_url']); ?>" class="btn-investigate-now">
                    <span>⚡</span> REVIEW INCIDENT
                </a>
            <?php endif; ?>
            
            <button type="button" class="btn-silence-alert" id="btnSilenceAlert" onclick="silenceRedAlert(<?php echo $active_red_alert['notification_id']; ?>)">
                <span>🔕</span> ACKNOWLEDGE & SILENCE
            </button>
        </div>
    </div>
</div>
<?php endif; ?>

<?php
$current_role = get_current_user_role();
$user_home_dest = get_role_home_page();
?>

<!-- TOP TELEMETRY & ROLE SWITCHER HUD -->
<header class="f1-top-hud">
    <div class="hud-left">
        <span class="hud-status-badge">
            <span class="hud-status-pulse"></span>
            FIA TELEMETRY // 2026 RACE CONTROL
        </span>
        <button type="button" class="btn-spotlight-trigger" onclick="openF1Spotlight()" title="Search anything in FSMS (Cmd+K or Ctrl+K)">
            <span class="spotlight-icon">🔍</span>
            <span class="spotlight-label">Spotlight Search</span>
            <kbd class="spotlight-kbd">⌘K</kbd>
        </button>
    </div>

    <!-- QUICK-SWITCH DEMO ROLE SELECTOR (Admin Clearance Only) -->
    <div class="hud-role-switcher">
        <?php 
        $can_switch_roles = ($current_role === 'admin') || (!empty($_SESSION['is_admin_root']));
        if ($can_switch_roles): ?>
            <span class="switcher-title">ADMIN CONTROLS:</span>
            <div class="role-badge-group">
                <a href="switch_role.php?role_id=1&return_url=<?php echo urlencode($current_page); ?>" class="role-switch-badge role-admin <?php echo ($current_role === 'admin') ? 'active-role' : ''; ?>" title="Switch to Race Director (Admin)">
                    <span class="badge-icon">👑</span> ADMIN
                </a>
                <a href="switch_role.php?role_id=2&return_url=<?php echo urlencode($current_page); ?>" class="role-switch-badge role-inspector <?php echo ($current_role === 'inspector') ? 'active-role' : ''; ?>" title="Switch to Tech Delegate (Inspector)">
                    <span class="badge-icon">🛠️</span> SCRUTINEER
                </a>
                <a href="switch_role.php?role_id=3&return_url=<?php echo urlencode($current_page); ?>" class="role-switch-badge role-steward <?php echo ($current_role === 'steward') ? 'active-role' : ''; ?>" title="Switch to Judicial Panel (Steward)">
                    <span class="badge-icon">⚖️</span> STEWARD
                </a>
                <a href="switch_role.php?role_id=4&return_url=<?php echo urlencode($current_page); ?>" class="role-switch-badge role-team <?php echo ($current_role === 'team_representative') ? 'active-role' : ''; ?>" title="Switch to Constructor (Team Rep)">
                    <span class="badge-icon">🏎️</span> TEAM REP
                </a>
            </div>
        <?php else: ?>
            <span class="switcher-title">ACTIVE ROLE TIER:</span>
            <div class="role-badge-group">
                <span class="role-switch-badge active-role role-<?php echo $current_role; ?>">
                    🔒 <?php echo strtoupper(str_replace('_', ' ', $current_role)); ?>
                </span>
            </div>
        <?php endif; ?>
    </div>

    <div class="hud-right">
        <div class="hud-clock" id="f1HudClock">--:--:-- UTC</div>
    </div>
</header>

<!-- GLOBAL SPOTLIGHT SEARCH MODAL (Cmd + K) -->
<div class="f1-spotlight-overlay" id="f1SpotlightModal" style="display: none;" onclick="handleSpotlightBackdrop(event)">
    <div class="spotlight-dialog">
        <div class="spotlight-header">
            <span class="spotlight-search-icon">🔍</span>
            <input type="text" class="spotlight-input" id="f1SpotlightInput" placeholder="Search Cars, Drivers, Regs, Stewards, Features... (e.g. 'RB20', 'Plank', 'Doc', 'Penalty')" autocomplete="off">
            <button type="button" class="btn-spotlight-close" onclick="closeF1Spotlight()">ESC</button>
        </div>
        <div class="spotlight-results" id="f1SpotlightResults">
            <!-- Dynamically populated -->
        </div>
        <div class="spotlight-footer">
            <span><kbd>↑</kbd> <kbd>↓</kbd> to navigate</span>
            <span><kbd>↵</kbd> to select</span>
            <span><kbd>ESC</kbd> to dismiss</span>
        </div>
    </div>
</div>

<!-- F1 Telemetry Cyber Sidebar -->
<aside class="f1-sidebar" id="f1Sidebar">
    <!-- Portal Logo Area (Click to navigate to Designated Hub) -->
    <a href="<?php echo htmlspecialchars($user_home_dest); ?>" class="sidebar-portal-logo" id="portalLogo" title="Click to open Hub | Hover to Sync">
        <div class="logo-core-icon">🏎️</div>
        <div class="logo-text-group">
            <span class="portal-badge">FIA FSMS v3.0</span>
            <span class="portal-title">F1 PORTAL HUB</span>
        </div>
        <div class="portal-status-dot" id="portalStatusDot"></div>
    </a>

    <!-- Live Sync Toast Notification on Hover -->
    <div class="hover-sync-hud" id="hoverSyncHud">
        <span class="hud-blink">●</span> TELEMETRY SYNCED TO DB
    </div>

    <!-- Navigation Hub Links (RBAC Filtered) -->
    <nav class="sidebar-nav">
        <!-- 0. Dashboard Hub (Admin Exclusive) -->
        <?php if (has_role_access(['admin'])): ?>
        <a href="dashboard.php" class="nav-item <?php echo in_array($current_page, ['dashboard.php', 'index.php']) ? 'active' : ''; ?>">
            <span class="nav-icon">📊</span>
            <span class="nav-label">DASHBOARD HUB</span>
        </a>
        <?php endif; ?>

        <!-- 1. Grid & Cars (Inspector, Team Rep, Admin) -->
        <?php if (has_role_access(['inspector', 'team_representative'])): ?>
        <a href="feature1.php" class="nav-item <?php echo ($current_page == 'feature1.php') ? 'active' : ''; ?>">
            <span class="nav-icon">🏎️</span>
            <span class="nav-label">GRID & CARS</span>
        </a>
        <?php endif; ?>

        <!-- 2. Scrutineering (Inspector, Admin) -->
        <?php if (has_role_access(['inspector'])): ?>
        <a href="feature2.php" class="nav-item <?php echo ($current_page == 'feature2.php') ? 'active' : ''; ?>">
            <span class="nav-icon">🛠️</span>
            <span class="nav-label">SCRUTINEERING</span>
        </a>
        <?php endif; ?>

        <!-- 3. Stewards Room (Steward, Admin) -->
        <?php if (has_role_access(['steward'])): ?>
        <a href="stewards_infringements.php" class="nav-item <?php echo in_array($current_page, ['stewards_infringements.php', 'feature3.php']) ? 'active' : ''; ?>">
            <span class="nav-icon">⚖️</span>
            <span class="nav-label">STEWARDS ROOM</span>
        </a>
        <?php endif; ?>

        <!-- 4. Bulletins & PU Cap (All Roles) -->
        <a href="feature4.php" class="nav-item <?php echo ($current_page == 'feature4.php') ? 'active' : ''; ?>">
            <span class="nav-icon">📑</span>
            <span class="nav-label">BULLETINS & PU CAP</span>
        </a>

        <!-- 5. Violation Detect (Inspector, Admin) -->
        <?php if (has_role_access(['inspector'])): ?>
        <a href="feature5.php" class="nav-item <?php echo ($current_page == 'feature5.php') ? 'active' : ''; ?>">
            <span class="nav-icon">🚨</span>
            <span class="nav-label">VIOLATION DETECT</span>
        </a>
        <?php endif; ?>

        <!-- 6. Steward Decisions (Steward, Admin) -->
        <?php if (has_role_access(['steward'])): ?>
        <a href="feature6.php" class="nav-item <?php echo ($current_page == 'feature6.php') ? 'active' : ''; ?>">
            <span class="nav-icon">⚡</span>
            <span class="nav-label">STEWARD DECISIONS</span>
        </a>
        <?php endif; ?>

        <!-- 7. Repairs & Re-Inspect (Inspector, Team Rep, Admin) -->
        <?php if (has_role_access(['inspector', 'team_representative'])): ?>
        <a href="feature7.php" class="nav-item <?php echo ($current_page == 'feature7.php') ? 'active' : ''; ?>">
            <span class="nav-icon">🔧</span>
            <span class="nav-label">REPAIRS & RE-INSPECT</span>
        </a>
        <?php endif; ?>

        <!-- 8. Delegate Re-Inspect (Inspector, Team Rep, Admin) -->
        <?php if (has_role_access(['inspector', 'team_representative'])): ?>
        <a href="feature8.php" class="nav-item <?php echo ($current_page == 'feature8.php') ? 'active' : ''; ?>">
            <span class="nav-icon">🛠️</span>
            <span class="nav-label">DELEGATE RE-INSPECT</span>
        </a>
        <?php endif; ?>

        <!-- 9. Appeals & Evidence (Steward, Team Rep, Admin) -->
        <?php if (has_role_access(['steward', 'team_representative'])): ?>
        <a href="feature9.php" class="nav-item <?php echo ($current_page == 'feature9.php') ? 'active' : ''; ?>">
            <span class="nav-icon">📜</span>
            <span class="nav-label">APPEALS & EVIDENCE</span>
        </a>
        <?php endif; ?>

        <!-- 10. Audit & Master Dossier (Inspector, Steward, Admin) -->
        <?php if (has_role_access(['inspector', 'steward'])): ?>
        <a href="feature10.php" class="nav-item <?php echo ($current_page == 'feature10.php') ? 'active' : ''; ?>">
            <span class="nav-icon">🛡️</span>
            <span class="nav-label">AUDIT & DOSSIER</span>
        </a>
        <?php endif; ?>

        <!-- 11. Security Logs (Inspector, Admin) -->
        <?php if (has_role_access(['inspector'])): ?>
        <a href="feature11.php" class="nav-item <?php echo ($current_page == 'feature11.php') ? 'active' : ''; ?>">
            <span class="nav-icon">🔒</span>
            <span class="nav-label">SECURITY LOGS</span>
        </a>
        <?php endif; ?>

        <!-- 12. Alert Center (All Roles) -->
        <a href="feature12.php" class="nav-item <?php echo ($current_page == 'feature12.php') ? 'active' : ''; ?>">
            <span class="nav-icon">🚨</span>
            <span class="nav-label">ALERT CENTER</span>
        </a>
    </nav>

    <!-- Sidebar Bottom Controls with Role Chip -->
    <div class="sidebar-footer">
        <div class="user-chip">
            <span class="user-dot"></span>
            <div style="display: flex; flex-direction: column; overflow: hidden;">
                <span class="user-name"><?php echo htmlspecialchars($_SESSION['username'] ?? 'F1 DELEGATE'); ?></span>
                <span class="user-role-tag role-<?php echo get_current_user_role(); ?>"><?php echo strtoupper(get_current_user_role()); ?></span>
            </div>
        </div>
        <a href="logout.php" class="nav-item btn-signout">
            <span class="nav-icon">⏻</span>
            <span class="nav-label">SIGN OUT</span>
        </a>
    </div>
</aside>

<!-- Sidebar CSS -->
<style>
    :root {
        --f1-red: #ff1801;
        --f1-cyan: #00d2be;
        --f1-dark: #07070e;
        --f1-panel: rgba(14, 14, 24, 0.95);
        --sidebar-w: 260px;
    }

    /* Offset main content to the right of the sidebar and below top HUD */
    body {
        padding-left: calc(var(--sidebar-w) + 24px) !important;
        padding-top: 68px !important;
    }

    /* TOP HUD BAR */
    .f1-top-hud {
        position: fixed;
        top: 0;
        left: var(--sidebar-w);
        right: 0;
        height: 54px;
        background: rgba(10, 10, 18, 0.96);
        backdrop-filter: blur(20px);
        border-bottom: 1px solid rgba(255, 255, 255, 0.08);
        box-shadow: 0 4px 25px rgba(0, 0, 0, 0.5);
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 0 24px;
        z-index: 9998;
        font-family: 'Titillium Web', sans-serif;
    }
    .hud-left {
        display: flex;
        align-items: center;
        gap: 16px;
    }
    .hud-status-badge {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        font-family: 'Share Tech Mono', monospace;
        font-size: 11px;
        letter-spacing: 1.5px;
        color: #00d2be;
        background: rgba(0, 210, 190, 0.08);
        border: 1px solid rgba(0, 210, 190, 0.25);
        padding: 4px 10px;
        border-radius: 4px;
    }
    .hud-status-pulse {
        width: 7px;
        height: 7px;
        border-radius: 50%;
        background: #00d2be;
        box-shadow: 0 0 8px #00d2be;
        animation: hudPulse 1.5s infinite;
    }
    @keyframes hudPulse {
        0%, 100% { opacity: 1; transform: scale(1); }
        50% { opacity: 0.4; transform: scale(0.85); }
    }
    .btn-spotlight-trigger {
        background: rgba(255, 255, 255, 0.04);
        border: 1px solid rgba(255, 255, 255, 0.12);
        color: #94a3b8;
        font-family: 'Titillium Web', sans-serif;
        font-size: 12px;
        font-weight: 600;
        padding: 5px 12px;
        border-radius: 6px;
        cursor: pointer;
        display: inline-flex;
        align-items: center;
        gap: 8px;
        transition: all 0.2s ease;
    }
    .btn-spotlight-trigger:hover {
        background: rgba(255, 255, 255, 0.09);
        color: #ffffff;
        border-color: rgba(255, 255, 255, 0.3);
    }
    .spotlight-kbd {
        font-family: 'Share Tech Mono', monospace;
        font-size: 10px;
        background: rgba(255, 255, 255, 0.1);
        border: 1px solid rgba(255, 255, 255, 0.2);
        color: #cbd5e1;
        padding: 2px 5px;
        border-radius: 3px;
    }
    .hud-role-switcher {
        display: flex;
        align-items: center;
        gap: 10px;
    }
    .switcher-title {
        font-family: 'Share Tech Mono', monospace;
        font-size: 11px;
        color: #64748b;
        letter-spacing: 1px;
        font-weight: 700;
    }
    .role-badge-group {
        display: flex;
        gap: 6px;
        background: rgba(0, 0, 0, 0.4);
        padding: 3px;
        border-radius: 8px;
        border: 1px solid rgba(255, 255, 255, 0.06);
    }
    .role-switch-badge {
        font-family: 'Orbitron', sans-serif;
        font-size: 10px;
        font-weight: 700;
        letter-spacing: 0.8px;
        text-decoration: none;
        padding: 5px 11px;
        border-radius: 5px;
        color: #8c8c9e;
        display: inline-flex;
        align-items: center;
        gap: 5px;
        transition: all 0.2s ease;
        border: 1px solid transparent;
    }
    .role-switch-badge:hover {
        color: #ffffff;
        background: rgba(255, 255, 255, 0.06);
    }
    .role-switch-badge.role-admin.active-role {
        background: linear-gradient(90deg, #ff1801, #b31000);
        color: #ffffff;
        box-shadow: 0 0 14px rgba(255, 24, 1, 0.6);
        border-color: #ff4d4d;
    }
    .role-switch-badge.role-inspector.active-role {
        background: linear-gradient(90deg, #00d2be, #008f82);
        color: #07070e;
        box-shadow: 0 0 14px rgba(0, 210, 190, 0.6);
        border-color: #55ebd8;
        font-weight: 900;
    }
    .role-switch-badge.role-steward.active-role {
        background: linear-gradient(90deg, #f1c40f, #b8930c);
        color: #07070e;
        box-shadow: 0 0 14px rgba(241, 196, 15, 0.6);
        border-color: #ffe066;
        font-weight: 900;
    }
    .role-switch-badge.role-team.active-role {
        background: linear-gradient(90deg, #3498db, #1d6fa5);
        color: #ffffff;
        box-shadow: 0 0 14px rgba(52, 152, 219, 0.6);
        border-color: #70b6e5;
    }
    .hud-right {
        display: flex;
        align-items: center;
        gap: 12px;
    }
    .hud-clock {
        font-family: 'Share Tech Mono', monospace;
        font-size: 13px;
        color: #e2e8f0;
        letter-spacing: 1.5px;
        background: rgba(255, 255, 255, 0.03);
        border: 1px solid rgba(255, 255, 255, 0.08);
        padding: 4px 10px;
        border-radius: 4px;
    }

    /* SPOTLIGHT OVERLAY MODAL */
    .f1-spotlight-overlay {
        position: fixed;
        top: 0; left: 0; right: 0; bottom: 0;
        background: rgba(0, 0, 0, 0.75);
        backdrop-filter: blur(12px);
        z-index: 100000;
        display: flex;
        align-items: flex-start;
        justify-content: center;
        padding-top: 100px;
        animation: fadeInSpot 0.2s ease;
    }
    @keyframes fadeInSpot {
        from { opacity: 0; }
        to { opacity: 1; }
    }
    .spotlight-dialog {
        background: #0e0e18;
        border: 1px solid rgba(255, 255, 255, 0.15);
        border-top: 2px solid var(--f1-cyan);
        box-shadow: 0 25px 60px rgba(0, 0, 0, 0.9), 0 0 40px rgba(0, 210, 190, 0.2);
        border-radius: 12px;
        width: 100%;
        max-width: 680px;
        overflow: hidden;
        animation: slideDownSpot 0.2s ease;
    }
    @keyframes slideDownSpot {
        from { transform: translateY(-20px); }
        to { transform: translateY(0); }
    }
    .spotlight-header {
        display: flex;
        align-items: center;
        gap: 12px;
        padding: 16px 20px;
        border-bottom: 1px solid rgba(255, 255, 255, 0.08);
        background: rgba(255, 255, 255, 0.02);
    }
    .spotlight-search-icon {
        font-size: 20px;
    }
    .spotlight-input {
        flex: 1;
        background: transparent;
        border: none;
        outline: none;
        color: #ffffff;
        font-family: 'Titillium Web', sans-serif;
        font-size: 16px;
        letter-spacing: 0.5px;
    }
    .btn-spotlight-close {
        background: rgba(255, 255, 255, 0.08);
        border: 1px solid rgba(255, 255, 255, 0.15);
        color: #94a3b8;
        font-family: 'Share Tech Mono', monospace;
        font-size: 11px;
        padding: 3px 8px;
        border-radius: 4px;
        cursor: pointer;
    }
    .spotlight-results {
        max-height: 400px;
        overflow-y: auto;
        padding: 12px 14px;
    }
    .spotlight-group-title {
        font-family: 'Share Tech Mono', monospace;
        font-size: 11px;
        color: #64748b;
        letter-spacing: 1.5px;
        padding: 8px 10px 4px;
        text-transform: uppercase;
    }
    .spotlight-item {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 10px 14px;
        border-radius: 6px;
        text-decoration: none;
        color: #cbd5e1;
        transition: all 0.15s ease;
        margin-bottom: 2px;
        cursor: pointer;
    }
    .spotlight-item:hover, .spotlight-item.active-item {
        background: rgba(0, 210, 190, 0.12);
        color: #ffffff;
        border-left: 3px solid var(--f1-cyan);
    }
    .spot-left {
        display: flex;
        align-items: center;
        gap: 12px;
    }
    .spot-icon {
        font-size: 18px;
    }
    .spot-title {
        font-weight: 600;
        font-size: 14px;
    }
    .spot-sub {
        font-size: 11.5px;
        color: #8c8c9e;
    }
    .spot-tag {
        font-family: 'Share Tech Mono', monospace;
        font-size: 10px;
        padding: 2px 7px;
        border-radius: 3px;
        background: rgba(255, 255, 255, 0.06);
        color: #94a3b8;
    }
    .spotlight-footer {
        display: flex;
        align-items: center;
        gap: 16px;
        padding: 10px 20px;
        background: rgba(0, 0, 0, 0.4);
        border-top: 1px solid rgba(255, 255, 255, 0.06);
        font-family: 'Share Tech Mono', monospace;
        font-size: 11px;
        color: #64748b;
    }
    .spotlight-footer kbd {
        background: rgba(255, 255, 255, 0.08);
        padding: 2px 5px;
        border-radius: 3px;
        color: #cbd5e1;
    }

    .f1-sidebar {
        position: fixed;
        top: 0;
        left: 0;
        bottom: 0;
        width: var(--sidebar-w);
        height: 100vh;
        max-height: 100vh;
        box-sizing: border-box;
        background: linear-gradient(180deg, rgba(16, 16, 26, 0.98) 0%, rgba(8, 8, 14, 0.99) 100%);
        border-right: 1px solid rgba(255, 255, 255, 0.08);
        box-shadow: 10px 0 35px rgba(0, 0, 0, 0.8), inset -1px 0 0 rgba(255, 24, 1, 0.2);
        display: flex;
        flex-direction: column;
        justify-content: space-between;
        padding: 12px 10px;
        z-index: 99999;
        backdrop-filter: blur(25px);
        font-family: 'Titillium Web', sans-serif;
        overflow-y: auto;
        overflow-x: hidden;
    }

    /* Portal Logo Box */
    .sidebar-portal-logo {
        flex-shrink: 0;
        display: flex;
        align-items: center;
        gap: 10px;
        background: rgba(255, 255, 255, 0.03);
        border: 1px solid rgba(255, 255, 255, 0.08);
        border-radius: 8px;
        padding: 8px 10px;
        cursor: pointer;
        text-decoration: none;
        color: inherit;
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        position: relative;
        overflow: hidden;
    }
    .sidebar-portal-logo::before {
        content: '';
        position: absolute;
        top: 0; left: -100%; width: 100%; height: 100%;
        background: linear-gradient(90deg, transparent, rgba(255, 24, 1, 0.2), transparent);
        transition: all 0.5s;
    }
    .sidebar-portal-logo:hover {
        border-color: var(--f1-red);
        background: rgba(255, 24, 1, 0.08);
        box-shadow: 0 0 20px rgba(255, 24, 1, 0.4);
        transform: translateY(-2px);
    }
    .sidebar-portal-logo:hover::before {
        left: 100%;
    }

    .logo-core-icon {
        font-size: 18px;
        filter: drop-shadow(0 0 8px var(--f1-red));
    }
    .logo-text-group {
        display: flex;
        flex-direction: column;
    }
    .portal-badge {
        font-family: 'Share Tech Mono', monospace;
        font-size: 8px;
        color: var(--f1-cyan);
        letter-spacing: 1px;
    }
    .portal-title {
        font-family: 'Orbitron', sans-serif;
        font-size: 11px;
        font-weight: 900;
        color: #ffffff;
        letter-spacing: 1px;
    }
    .portal-status-dot {
        width: 6px;
        height: 6px;
        background: var(--f1-cyan);
        border-radius: 50%;
        margin-left: auto;
        box-shadow: 0 0 8px var(--f1-cyan);
    }

    /* Sync Alert HUD Toast */
    .hover-sync-hud {
        display: none;
        flex-shrink: 0;
        font-family: 'Share Tech Mono', monospace;
        font-size: 9.5px;
        color: var(--f1-cyan);
        background: rgba(0, 210, 190, 0.12);
        border: 1px solid var(--f1-cyan);
        border-radius: 6px;
        padding: 5px 8px;
        margin-top: 6px;
        text-align: center;
        animation: fadeInHud 0.3s ease-in-out;
    }
    @keyframes fadeInHud {
        from { opacity: 0; transform: translateY(-5px); }
        to { opacity: 1; transform: translateY(0); }
    }
    .hud-blink {
        animation: blink 0.8s infinite alternate;
    }
    @keyframes blink { from { opacity: 0.2; } to { opacity: 1; } }

    /* Navigation Links with Scroll Support */
    .sidebar-nav {
        display: flex;
        flex-direction: column;
        gap: 4px;
        margin-top: 8px;
        margin-bottom: 8px;
        flex: 1 1 auto;
        min-height: 0;
        overflow-y: auto;
        overflow-x: hidden;
        padding-right: 4px;
        scrollbar-width: thin;
        scrollbar-color: #ff1801 rgba(255, 255, 255, 0.05);
    }
    /* Custom F1 Telemetry Scrollbar */
    .f1-sidebar::-webkit-scrollbar,
    .sidebar-nav::-webkit-scrollbar {
        width: 5px;
    }
    .f1-sidebar::-webkit-scrollbar-track,
    .sidebar-nav::-webkit-scrollbar-track {
        background: rgba(255, 255, 255, 0.03);
        border-radius: 4px;
    }
    .f1-sidebar::-webkit-scrollbar-thumb,
    .sidebar-nav::-webkit-scrollbar-thumb {
        background: rgba(255, 24, 1, 0.6);
        border-radius: 4px;
        box-shadow: 0 0 6px rgba(255, 24, 1, 0.5);
    }
    .f1-sidebar::-webkit-scrollbar-thumb:hover,
    .sidebar-nav::-webkit-scrollbar-thumb:hover {
        background: #ff1801;
        box-shadow: 0 0 10px #ff1801;
    }

    .nav-item {
        display: flex;
        align-items: center;
        gap: 10px;
        padding: 8px 10px;
        border-radius: 6px;
        text-decoration: none;
        color: #8c8c9e;
        background: rgba(255, 255, 255, 0.02);
        border: 1px solid rgba(255, 255, 255, 0.04);
        font-family: 'Orbitron', sans-serif;
        font-size: 9.5px;
        font-weight: 700;
        letter-spacing: 1px;
        transition: all 0.2s ease;
        flex-shrink: 0;
    }
    .nav-item .nav-icon {
        font-size: 13px;
        display: flex;
        align-items: center;
        justify-content: center;
        width: 16px;
    }
    .nav-item .nav-label {
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }
    .nav-item:hover {
        color: #ffffff;
        background: rgba(255, 255, 255, 0.06);
        border-color: rgba(255, 255, 255, 0.15);
        transform: translateX(3px);
    }
    /* Active Item Highlight */
    .nav-item.active {
        color: #ffffff;
        background: rgba(255, 24, 1, 0.14);
        border: 1px solid #ff1801;
        box-shadow: 0 0 15px rgba(255, 24, 1, 0.4), inset 0 0 10px rgba(255, 24, 1, 0.2);
    }

    /* Footer & Sign Out (Always Pinned / Sticky) */
    .sidebar-footer {
        flex-shrink: 0;
        margin-top: auto;
        display: flex;
        flex-direction: column;
        gap: 6px;
        border-top: 1px solid rgba(255, 255, 255, 0.1);
        padding-top: 8px;
        background: rgba(10, 10, 18, 0.95);
        position: sticky;
        bottom: 0;
        z-index: 10;
    }
    .user-chip {
        display: flex;
        align-items: center;
        gap: 8px;
        font-family: 'Share Tech Mono', monospace;
        font-size: 9.5px;
        color: #717188;
        padding: 0 4px;
    }
    .user-role-tag {
        font-size: 8px;
        font-weight: 700;
        letter-spacing: 0.8px;
        padding: 1px 5px;
        border-radius: 3px;
        display: inline-block;
        width: fit-content;
        margin-top: 2px;
    }
    .role-admin { background: rgba(255, 24, 1, 0.2); color: #ff4757; border: 1px solid rgba(255, 24, 1, 0.4); }
    .role-inspector { background: rgba(0, 210, 190, 0.15); color: #00d2be; border: 1px solid rgba(0, 210, 190, 0.4); }
    .role-steward { background: rgba(255, 159, 26, 0.15); color: #ff9f1a; border: 1px solid rgba(255, 159, 26, 0.4); }
    .role-team_representative { background: rgba(155, 89, 182, 0.15); color: #9b59b6; border: 1px solid rgba(155, 89, 182, 0.4); }

    .user-dot {
        width: 6px;
        height: 6px;
        border-radius: 50%;
        background: #2ecc71;
        box-shadow: 0 0 8px #2ecc71;
    }
    .btn-signout {
        color: #ff4757;
        border-color: rgba(255, 71, 87, 0.25);
        background: rgba(255, 71, 87, 0.08);
        padding: 8px 10px;
    }
    .btn-signout:hover {
        color: #ffffff;
        background: #ff1801;
        border-color: #ff1801;
        box-shadow: 0 0 15px rgba(255, 24, 1, 0.6);
        transform: none;
    }
    /* Global Red Alert Disco Strobe & Takeover Overlay */
    .red-alert-disco-curtain {
        position: fixed;
        top: 0; left: 0; right: 0; bottom: 0;
        width: 100vw; height: 100vh;
        z-index: 999999;
        pointer-events: none;
        display: flex;
        align-items: center;
        justify-content: center;
        overflow: hidden;
    }

    .disco-strobe-overlay {
        position: absolute;
        top: 0; left: 0; width: 100%; height: 100%;
        pointer-events: none;
        box-shadow: inset 0 0 100px rgba(255, 0, 0, 0.8), inset 0 0 220px rgba(255, 24, 1, 0.6);
        background: radial-gradient(circle at 50% 50%, rgba(255, 0, 0, 0.12) 0%, rgba(255, 0, 0, 0.38) 100%);
        animation: discoFlash 0.9s infinite alternate ease-in-out;
    }

    @keyframes discoFlash {
        0% {
            opacity: 0.35;
            box-shadow: inset 0 0 60px rgba(255, 0, 0, 0.5);
            background-color: rgba(255, 0, 0, 0.08);
        }
        50% {
            opacity: 0.95;
            box-shadow: inset 0 0 180px rgba(255, 0, 0, 0.95), inset 0 0 320px rgba(255, 24, 1, 0.75);
            background-color: rgba(255, 0, 0, 0.22);
        }
        100% {
            opacity: 0.4;
            box-shadow: inset 0 0 80px rgba(255, 0, 0, 0.6);
            background-color: rgba(255, 0, 0, 0.1);
        }
    }

    .disco-scanner {
        position: absolute;
        top: 0; left: 0; width: 100%; height: 6px;
        background: linear-gradient(90deg, transparent, #ff1801, #ffffff, #ff1801, transparent);
        box-shadow: 0 0 25px 8px #ff1801;
        pointer-events: none;
        animation: laserScan 2.2s infinite linear;
    }

    @keyframes laserScan {
        0% { top: -10px; opacity: 0; }
        10% { opacity: 1; }
        90% { opacity: 1; }
        100% { top: 100vh; opacity: 0; }
    }

    .red-alert-banner {
        position: relative;
        pointer-events: auto;
        background: rgba(15, 8, 12, 0.96);
        border: 2px solid #ff1801;
        box-shadow: 0 0 50px rgba(255, 24, 1, 0.8), inset 0 0 25px rgba(255, 24, 1, 0.4);
        border-radius: 12px;
        padding: 24px 36px;
        max-width: 650px;
        width: 90%;
        text-align: center;
        backdrop-filter: blur(15px);
        animation: bannerPop 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
        z-index: 1000000;
    }

    @keyframes bannerPop {
        from { transform: scale(0.85); opacity: 0; }
        to { transform: scale(1); opacity: 1; }
    }

    .alert-beacon-row {
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 12px;
        margin-bottom: 8px;
    }
    .alert-tag {
        font-family: 'Share Tech Mono', monospace;
        font-size: 11px;
        color: #ff4757;
        letter-spacing: 2px;
        font-weight: 700;
        text-transform: uppercase;
    }
    .alert-beacon {
        font-size: 18px;
        animation: beaconSpin 1.2s infinite ease-in-out;
    }
    @keyframes beaconSpin {
        0% { transform: scale(0.9) rotate(-10deg); }
        50% { transform: scale(1.3) rotate(10deg); filter: drop-shadow(0 0 10px #ff1801); }
        100% { transform: scale(0.9) rotate(-10deg); }
    }

    .alert-main-title {
        font-family: 'Orbitron', sans-serif;
        font-size: 22px;
        font-weight: 900;
        color: #ffffff;
        letter-spacing: 2px;
        text-shadow: 0 0 15px rgba(255, 24, 1, 0.9), 0 0 30px rgba(255, 24, 1, 0.6);
        margin-bottom: 10px;
        animation: neonGlow 1.2s infinite alternate;
    }

    @keyframes neonGlow {
        from { text-shadow: 0 0 10px rgba(255, 24, 1, 0.8); }
        to { text-shadow: 0 0 25px rgba(255, 255, 255, 1), 0 0 40px rgba(255, 24, 1, 1); }
    }

    .alert-sub-neon {
        font-family: 'Share Tech Mono', monospace;
        font-size: 13px;
        color: #ff9f1a;
        letter-spacing: 1px;
        margin-bottom: 10px;
        word-break: break-word;
    }
    .alert-incident-type {
        color: #ff1801;
        font-weight: 700;
        margin-right: 6px;
    }
    .alert-desc-text {
        font-family: 'Titillium Web', sans-serif;
        font-size: 13px;
        line-height: 1.5;
        color: #e2e8f0;
        background: rgba(0, 0, 0, 0.5);
        border: 1px solid rgba(255, 255, 255, 0.08);
        border-radius: 6px;
        padding: 10px 14px;
        margin-bottom: 18px;
    }

    .alert-action-buttons {
        display: flex;
        justify-content: center;
        gap: 14px;
        flex-wrap: wrap;
    }

    .btn-investigate-now {
        background: linear-gradient(90deg, #ff1801 0%, #d63031 100%);
        border: none;
        color: #ffffff;
        font-family: 'Orbitron', sans-serif;
        font-size: 11px;
        font-weight: 700;
        letter-spacing: 1px;
        padding: 10px 20px;
        border-radius: 6px;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 6px;
        cursor: pointer;
        box-shadow: 0 0 15px rgba(255, 24, 1, 0.6);
        transition: all 0.2s ease;
    }
    .btn-investigate-now:hover {
        box-shadow: 0 0 25px rgba(255, 24, 1, 0.9);
        transform: translateY(-1px);
    }

    .btn-silence-alert {
        background: rgba(255, 255, 255, 0.08);
        border: 1px solid rgba(255, 255, 255, 0.25);
        color: #ffffff;
        font-family: 'Orbitron', sans-serif;
        font-size: 11px;
        font-weight: 700;
        letter-spacing: 1px;
        padding: 10px 18px;
        border-radius: 6px;
        cursor: pointer;
        display: inline-flex;
        align-items: center;
        gap: 6px;
        transition: all 0.2s ease;
    }
    .btn-silence-alert:hover {
        background: rgba(46, 204, 113, 0.2);
        border-color: #2ecc71;
        color: #2ecc71;
        box-shadow: 0 0 15px rgba(46, 204, 113, 0.4);
    }
</style>

<!-- F1 Global Audio Synthesizer & AJAX Hover Logger -->
<script>
// Formula 1 Web Audio Engine Synthesizer
let globalF1AudioCtx = null;
function getF1Audio() {
    if (!globalF1AudioCtx) {
        globalF1AudioCtx = new (window.AudioContext || window.webkitAudioContext)();
    }
    return globalF1AudioCtx;
}

// 1. F1 V6 Turbo Hybrid Engine Flyby Sound
function playGlobalF1Flyby() {
    try {
        const ctx = getF1Audio();
        if (ctx.state === 'suspended') ctx.resume();
        const now = ctx.currentTime;
        const duration = 1.3;

        // V6 Sawtooth Osc
        const osc1 = ctx.createOscillator();
        const gain1 = ctx.createGain();
        osc1.type = 'sawtooth';
        osc1.frequency.setValueAtTime(140, now);
        osc1.frequency.exponentialRampToValueAtTime(780, now + 0.6);
        osc1.frequency.exponentialRampToValueAtTime(940, now + 0.9);
        osc1.frequency.exponentialRampToValueAtTime(260, now + duration);

        gain1.gain.setValueAtTime(0.01, now);
        gain1.gain.linearRampToValueAtTime(0.24, now + 0.3);
        gain1.gain.linearRampToValueAtTime(0.32, now + 0.6);
        gain1.gain.exponentialRampToValueAtTime(0.001, now + duration);

        // Exhaust Sub Rumble
        const osc2 = ctx.createOscillator();
        const gain2 = ctx.createGain();
        osc2.type = 'triangle';
        osc2.frequency.setValueAtTime(70, now);
        osc2.frequency.exponentialRampToValueAtTime(390, now + 0.6);
        osc2.frequency.exponentialRampToValueAtTime(130, now + duration);

        gain2.gain.setValueAtTime(0.01, now);
        gain2.gain.linearRampToValueAtTime(0.25, now + 0.3);
        gain2.gain.exponentialRampToValueAtTime(0.001, now + duration);

        // Turbo Air Rush
        const bufferSize = ctx.sampleRate * duration;
        const buffer = ctx.createBuffer(1, bufferSize, ctx.sampleRate);
        const data = buffer.getChannelData(0);
        for (let i = 0; i < bufferSize; i++) data[i] = Math.random() * 2 - 1;

        const noise = ctx.createBufferSource();
        noise.buffer = buffer;
        const filter = ctx.createBiquadFilter();
        filter.type = 'bandpass';
        filter.frequency.setValueAtTime(1200, now);
        filter.frequency.exponentialRampToValueAtTime(3600, now + 0.6);
        filter.Q.value = 3.5;

        const noiseGain = ctx.createGain();
        noiseGain.gain.setValueAtTime(0.001, now);
        noiseGain.gain.linearRampToValueAtTime(0.12, now + 0.35);
        noiseGain.gain.exponentialRampToValueAtTime(0.001, now + duration);

        osc1.connect(gain1); gain1.connect(ctx.destination);
        osc2.connect(gain2); gain2.connect(ctx.destination);
        noise.connect(filter); filter.connect(noiseGain); noiseGain.connect(ctx.destination);

        osc1.start(now); osc2.start(now); noise.start(now);
        osc1.stop(now + duration); osc2.stop(now + duration); noise.stop(now + duration);
    } catch(e) {}
}

// 2. F1 Radio Chirp Tone
function playGlobalF1Radio() {
    try {
        const ctx = getF1Audio();
        if (ctx.state === 'suspended') ctx.resume();
        const now = ctx.currentTime;
        const osc = ctx.createOscillator();
        const gain = ctx.createGain();
        osc.type = 'sine';
        osc.frequency.setValueAtTime(980, now);
        osc.frequency.setValueAtTime(1350, now + 0.04);
        gain.gain.setValueAtTime(0.15, now);
        gain.gain.exponentialRampToValueAtTime(0.001, now + 0.1);
        osc.connect(gain); gain.connect(ctx.destination);
        osc.start(now); osc.stop(now + 0.1);
    } catch(e) {}
}

// 3. Web Audio Klaxon Emergency Siren Synthesizer
let redAlertSirenOsc = null;
let redAlertSirenGain = null;
let redAlertSirenInterval = null;

function startRedAlertSiren() {
    try {
        const ctx = getF1Audio();
        if (ctx.state === 'suspended') ctx.resume();
        if (redAlertSirenOsc) return;

        const now = ctx.currentTime;
        redAlertSirenOsc = ctx.createOscillator();
        redAlertSirenGain = ctx.createGain();

        redAlertSirenOsc.type = 'sawtooth';
        redAlertSirenOsc.frequency.setValueAtTime(440, now);

        redAlertSirenGain.gain.setValueAtTime(0.01, now);
        redAlertSirenGain.gain.linearRampToValueAtTime(0.16, now + 0.2);

        redAlertSirenOsc.connect(redAlertSirenGain);
        redAlertSirenGain.connect(ctx.destination);
        redAlertSirenOsc.start();

        // Dual-tone frequency klaxon sweep 440Hz <-> 880Hz
        let highTone = false;
        redAlertSirenInterval = setInterval(() => {
            if (!redAlertSirenOsc || !ctx) return;
            const cTime = ctx.currentTime;
            highTone = !highTone;
            const targetFreq = highTone ? 880 : 440;
            redAlertSirenOsc.frequency.exponentialRampToValueAtTime(targetFreq, cTime + 0.45);
        }, 500);
    } catch(e) {}
}

function stopRedAlertSiren() {
    try {
        if (redAlertSirenInterval) {
            clearInterval(redAlertSirenInterval);
            redAlertSirenInterval = null;
        }
        if (redAlertSirenGain && globalF1AudioCtx) {
            const cTime = globalF1AudioCtx.currentTime;
            redAlertSirenGain.gain.linearRampToValueAtTime(0.0001, cTime + 0.2);
            setTimeout(() => {
                if (redAlertSirenOsc) {
                    try { redAlertSirenOsc.stop(); } catch(e){}
                    redAlertSirenOsc = null;
                }
            }, 250);
        }
    } catch(e) {}
}

function silenceRedAlert(notifId) {
    stopRedAlertSiren();
    const curtain = document.getElementById('redAlertCurtain');
    if (curtain) {
        curtain.style.transition = 'opacity 0.4s ease';
        curtain.style.opacity = '0';
        setTimeout(() => { curtain.style.display = 'none'; }, 400);
    }
    // Asynchronous notification dismissal
    fetch('silence_alert.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'notification_id=' + encodeURIComponent(notifId)
    }).catch(e => {});
}

document.addEventListener('DOMContentLoaded', () => {
    const portalLogo = document.getElementById('portalLogo');
    const syncHud = document.getElementById('hoverSyncHud');
    let lastHoverTime = 0;

    // Check if Red Alert is active on page load
    const activeRedAlertCurtain = document.getElementById('redAlertCurtain');
    if (activeRedAlertCurtain) {
        startRedAlertSiren();
        // Browser autoplay interaction trigger fallback
        const triggerSirenOnFirstClick = () => {
            startRedAlertSiren();
            window.removeEventListener('click', triggerSirenOnFirstClick);
        };
        window.addEventListener('click', triggerSirenOnFirstClick, { once: true });
    }

    // Play engine rev on logo click
    if (portalLogo) {
        portalLogo.addEventListener('click', () => {
            playGlobalF1Flyby();
        });
    }

    // Play radio click on nav item clicks
    document.querySelectorAll('.sidebar-nav .nav-item').forEach(item => {
        item.addEventListener('click', () => {
            playGlobalF1Radio();
        });
    });

    // Auto trigger F1 sound on first user gesture on page
    const playFirstInteraction = () => {
        playGlobalF1Radio();
        document.removeEventListener('click', playFirstInteraction);
    };
    document.addEventListener('click', playFirstInteraction, { once: true });

    if (portalLogo && syncHud) {
        portalLogo.addEventListener('mouseenter', () => {
            playGlobalF1Radio();
            const now = Date.now();
            if (now - lastHoverTime < 4000) {
                syncHud.style.display = 'block';
                return;
            }
            lastHoverTime = now;
            syncHud.style.display = 'block';

            fetch('update_portal_hover.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'action=portal_hover'
            })
            .then(res => res.json())
            .then(data => {
                setTimeout(() => { syncHud.style.display = 'none'; }, 2500);
            })
            .catch(err => {});
        });

        portalLogo.addEventListener('mouseleave', () => {
            setTimeout(() => { syncHud.style.display = 'none'; }, 1500);
        });
    }

    const input = document.getElementById('f1SpotlightInput');
    if (input) {
        input.addEventListener('input', (e) => {
            renderSpotlightResults(e.target.value);
        });
    }
});

// --- SPOTLIGHT SEARCH ENGINE ---
const f1SearchIndex = [
    { title: 'Dashboard Hub', sub: 'Central Race Command & Telemetry', category: 'Hubs', url: 'dashboard.php', icon: '📊' },
    { title: 'Grid & Cars Registry', sub: 'Chassis, Power Units, Constructors', category: 'Hubs', url: 'feature1.php', icon: '🏎️' },
    { title: 'Scrutineering Rig', sub: 'Technical Inspections & Measurements', category: 'Hubs', url: 'feature2.php', icon: '🛠️' },
    { title: 'Stewards Room & Hearings', sub: 'Infringements & Superlicense Penalties', category: 'Hubs', url: 'stewards_infringements.php', icon: '⚖️' },
    { title: 'Bulletins & PU Allocations', sub: 'Official Technical Directives & Power Unit Limits', category: 'Hubs', url: 'feature4.php', icon: '📑' },
    { title: 'Violation Detection', sub: 'Automated Scrutineering Triggers', category: 'Hubs', url: 'feature5.php', icon: '🚨' },
    { title: 'Steward Decisions & Sanctions', sub: 'Judicial Orders & Grid Penalties', category: 'Hubs', url: 'feature6.php', icon: '⚡' },
    { title: 'Repairs & Re-Inspections', sub: 'Constructor Rectification & Delegate Pass', category: 'Hubs', url: 'feature8.php', icon: '🔧' },
    { title: 'Appeals & Evidence Dossier', sub: 'Constructor Contested Decisions & Video', category: 'Hubs', url: 'feature9.php', icon: '📜' },
    { title: 'Master Dossier & Audit Trail', sub: 'Championship Telemetry Archives', category: 'Hubs', url: 'feature10.php', icon: '🛡️' },
    { title: 'Security Audit Logs', sub: 'User Access & Tamper-Proof Trail', category: 'Hubs', url: 'feature11.php', icon: '🔒' },
    { title: 'Race Control Alert Center', sub: 'Emergency Broadcasts & Live Radio Pings', category: 'Hubs', url: 'feature12.php', icon: '🚨' },
    
    // Cars & Teams
    { title: 'Red Bull RB20 #1 (Max Verstappen)', sub: 'Chassis RB20-01 | Honda RBPTH002', category: 'Cars & Teams', url: 'feature1.php', icon: '🏎️' },
    { title: 'Red Bull RB20 #2 (Sergio Perez)', sub: 'Chassis RB20-02 | Honda RBPTH002', category: 'Cars & Teams', url: 'feature1.php', icon: '🏎️' },
    { title: 'Ferrari SF-24 #16 (Charles Leclerc)', sub: 'Chassis SF24-02 | Ferrari 066/12', category: 'Cars & Teams', url: 'feature1.php', icon: '🏎️' },
    { title: 'Mercedes W15 #63 (George Russell)', sub: 'Chassis W15-02 | Mercedes-AMG M15', category: 'Cars & Teams', url: 'feature1.php', icon: '🏎️' },
    { title: 'McLaren MCL38 #4 (Lando Norris)', sub: 'Chassis MCL38-01 | Mercedes-AMG M15', category: 'Cars & Teams', url: 'feature1.php', icon: '🏎️' },
    
    // Regulations & Tests
    { title: 'Art 3.5.1 - Front Wing Load Deflection', sub: 'Max 2.0mm deflection under 1000N vertical test load', category: 'Regulations', url: 'feature2.php', icon: '📐' },
    { title: 'Art 3.12.1 - Skid Block & Plank Wear', sub: 'Minimum 9.0mm mandatory thickness', category: 'Regulations', url: 'feature2.php', icon: '📐' },
    { title: 'Art 5.1 - Power Unit Component Limits', sub: 'Max 4 ICE, 4 TC, 4 MGU-H, 4 MGU-K per season', category: 'Regulations', url: 'feature4.php', icon: '📐' },
    { title: 'ISC Appendix L - Superlicense 12-Point Rule', sub: '1-race suspension triggered at 12 penalty points', category: 'Regulations', url: 'stewards_infringements.php', icon: '📐' },
    
    // Fast Actions
    { title: 'Log Technical Scrutineering Measurement', sub: 'Record vernier, laser or load test result', category: 'Actions', url: 'feature2.php', icon: '⚡' },
    { title: 'Issue Stewards Penalty Summons', sub: 'Open judicial hearing for track or technical incident', category: 'Actions', url: 'stewards_infringements.php', icon: '⚡' },
    { title: 'Export Official FIA Document / PDF', sub: 'Print official decision bulletin with barcode & seals', category: 'Actions', url: 'export_document.php?doc_type=decision&id=1', icon: '📄' }
];

function openF1Spotlight() {
    const modal = document.getElementById('f1SpotlightModal');
    const input = document.getElementById('f1SpotlightInput');
    if (!modal) return;
    modal.style.display = 'flex';
    if (input) {
        input.value = '';
        input.focus();
        renderSpotlightResults('');
    }
}

function closeF1Spotlight() {
    const modal = document.getElementById('f1SpotlightModal');
    if (modal) modal.style.display = 'none';
}

function handleSpotlightBackdrop(e) {
    if (e.target.id === 'f1SpotlightModal') {
        closeF1Spotlight();
    }
}

function renderSpotlightResults(query) {
    const container = document.getElementById('f1SpotlightResults');
    if (!container) return;
    const q = (query || '').toLowerCase().trim();
    
    const filtered = f1SearchIndex.filter(item => {
        if (!q) return true;
        return item.title.toLowerCase().includes(q) || 
               item.sub.toLowerCase().includes(q) || 
               item.category.toLowerCase().includes(q);
    });

    if (filtered.length === 0) {
        container.innerHTML = `
            <div style="padding: 30px; text-align: center; color: #64748b; font-family: 'Share Tech Mono', monospace;">
                <div style="font-size: 28px; margin-bottom: 8px;">🛰️</div>
                NO TELEMETRY MATCHES FOUND FOR "${escapeHtml(query)}"
            </div>
        `;
        return;
    }

    const groups = {};
    filtered.forEach(item => {
        if (!groups[item.category]) groups[item.category] = [];
        groups[item.category].push(item);
    });

    let html = '';
    let isFirst = true;
    for (const cat in groups) {
        html += `<div class="spotlight-group-title">${cat}</div>`;
        groups[cat].forEach(item => {
            const activeClass = isFirst ? 'active-item' : '';
            isFirst = false;
            html += `
                <a href="${item.url}" class="spotlight-item ${activeClass}">
                    <div class="spot-left">
                        <span class="spot-icon">${item.icon}</span>
                        <div>
                            <div class="spot-title">${escapeHtml(item.title)}</div>
                            <div class="spot-sub">${escapeHtml(item.sub)}</div>
                        </div>
                    </div>
                    <span class="spot-tag">${escapeHtml(item.category)}</span>
                </a>
            `;
        });
    }
    container.innerHTML = html;
}

function escapeHtml(str) {
    return (str || '').replace(/[&<>"']/g, m => ({ '&':'&amp;', '<':'&lt;', '>':'&gt;', '"':'&quot;', "'":'&#39;' })[m]);
}

// Live HUD Clock
function updateF1HudClock() {
    const clock = document.getElementById('f1HudClock');
    if (!clock) return;
    const now = new Date();
    const h = String(now.getUTCHours()).padStart(2, '0');
    const m = String(now.getUTCMinutes()).padStart(2, '0');
    const s = String(now.getUTCSeconds()).padStart(2, '0');
    clock.textContent = `${h}:${m}:${s} UTC`;
}
setInterval(updateF1HudClock, 1000);
updateF1HudClock();

// Keyboard shortcuts (Cmd+K / Ctrl+K / Escape)
window.addEventListener('keydown', (e) => {
    if ((e.metaKey || e.ctrlKey) && e.key.toLowerCase() === 'k') {
        e.preventDefault();
        const modal = document.getElementById('f1SpotlightModal');
        if (modal && modal.style.display === 'flex') {
            closeF1Spotlight();
        } else {
            openF1Spotlight();
        }
    } else if (e.key === 'Escape') {
        closeF1Spotlight();
    }
});
</script>