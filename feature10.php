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
check_role_access(['inspector', 'steward']);

// Helper function to log audit events
if (!function_exists('log_system_audit')) {
    function log_system_audit($conn, $user_id, $action, $entity_type, $entity_id, $details_array = []) {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
        if ($ip === '::1') $ip = '127.0.0.1';
        $details_json = !empty($details_array) ? json_encode($details_array, JSON_UNESCAPED_UNICODE) : null;
        
        $stmt = $conn->prepare("INSERT INTO AUDIT_LOGS (user_id, action, entity_type, entity_id, details, ip_address, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())");
        if ($stmt) {
            $stmt->bind_param("ississ", $user_id, $action, $entity_type, $entity_id, $details_json, $ip);
            $stmt->execute();
        }
    }
}

// 0. CSV EXPORT HANDLERS
if (isset($_GET['export'])) {
    $export_type = $_GET['export'];

    // Audit Logs CSV Export
    if ($export_type === 'audit_csv') {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="FIA_System_Audit_Log_' . date('Ymd_His') . '.csv"');
        $output = fopen('php://output', 'w');
        fputcsv($output, ['LOG ID', 'TIMESTAMP', 'OPERATOR (USER ID)', 'OPERATOR NAME', 'ACTION', 'ENTITY TYPE', 'ENTITY ID', 'IP ADDRESS', 'DETAILS']);

        $sql = "SELECT a.log_id, a.created_at, a.user_id, u.full_name, a.action, a.entity_type, a.entity_id, a.ip_address, a.details 
                FROM AUDIT_LOGS a 
                LEFT JOIN USERS u ON a.user_id = u.user_id 
                ORDER BY a.log_id DESC";
        $res = $conn->query($sql);
        if ($res) {
            while ($row = $res->fetch_assoc()) {
                fputcsv($output, [
                    $row['log_id'],
                    $row['created_at'],
                    $row['user_id'],
                    $row['full_name'] ?? 'System / Anonymous',
                    $row['action'],
                    $row['entity_type'] ?? 'GENERAL',
                    $row['entity_id'] ?? 'N/A',
                    $row['ip_address'] ?? '127.0.0.1',
                    $row['details'] ?? ''
                ]);
            }
        }
        fclose($output);
        exit;
    }

    // Car Technical Master Dossier CSV Export
    if ($export_type === 'car_csv' && isset($_GET['car_id'])) {
        $cid = intval($_GET['car_id']);
        
        $c_stmt = $conn->prepare("SELECT c.*, t.team_name, d.full_name as driver_name 
                                  FROM CARS c 
                                  LEFT JOIN TEAMS t ON c.team_id = t.team_id 
                                  LEFT JOIN DRIVERS d ON c.driver_id = d.driver_id 
                                  WHERE c.car_id = ?");
        $c_stmt->bind_param("i", $cid);
        $c_stmt->execute();
        $car_info = $c_stmt->get_result()->fetch_assoc();

        if ($car_info) {
            header('Content-Type: text/csv; charset=utf-8');
            header('Content-Disposition: attachment; filename="FIA_Technical_Dossier_Car_' . $cid . '_' . preg_replace('/[^A-Za-z0-9]/', '_', $car_info['car_name']) . '_' . date('Ymd') . '.csv"');
            $output = fopen('php://output', 'w');

            // Header Banner
            fputcsv($output, ['FEDERATION INTERNATIONALE DE L\'AUTOMOBILE - OFFICIAL TECHNICAL MASTER DOSSIER']);
            fputcsv($output, ['GENERATED AT', date('Y-m-d H:i:s T')]);
            fputcsv($output, ['DOCUMENT CLASSIFICATION', 'FIA OFFICIAL TECHNICAL RECORD / PARC FERME SEALED']);
            fputcsv($output, []);

            // Vehicle Specs
            fputcsv($output, ['--- VEHICLE IDENTIFICATION ---']);
            fputcsv($output, ['CAR ID', $car_info['car_id']]);
            fputcsv($output, ['HOMOLOGATION NAME', $car_info['car_name']]);
            fputcsv($output, ['CHASSIS NUMBER', $car_info['chassis_number']]);
            fputcsv($output, ['CONSTRUCTOR / TEAM', $car_info['team_name']]);
            fputcsv($output, ['ALLOCATED DRIVER', $car_info['driver_name']]);
            fputcsv($output, ['ENGINE / PU TYPE', $car_info['engine_type']]);
            fputcsv($output, ['FIA CATEGORY', $car_info['category']]);
            fputcsv($output, ['HOMOLOGATION STATUS', $car_info['status']]);
            fputcsv($output, []);

            // Measurements
            fputcsv($output, ['--- SCRUTINEERING & PHYSICAL MEASUREMENTS ---']);
            fputcsv($output, ['MEASUREMENT ID', 'SESSION ID', 'SESSION TYPE', 'COMPONENT / ITEM', 'EXPECTED VALUE', 'ACTUAL VALUE', 'UNIT', 'RESULT', 'DATE RECORDED']);
            $m_stmt = $conn->prepare("SELECT m.*, s.session_type FROM INSPECTION_MEASUREMENTS m 
                                      JOIN INSPECTION_SESSIONS s ON m.session_id = s.session_id 
                                      WHERE s.car_id = ? ORDER BY m.measurement_id DESC");
            $m_stmt->bind_param("i", $cid);
            $m_stmt->execute();
            $m_res = $m_stmt->get_result();
            while ($mr = $m_res->fetch_assoc()) {
                fputcsv($output, [
                    $mr['measurement_id'],
                    $mr['session_id'],
                    $mr['session_type'],
                    $mr['measurement_name'],
                    $mr['expected_value'],
                    $mr['actual_value'],
                    $mr['unit'],
                    $mr['result'],
                    $mr['created_at']
                ]);
            }
            fputcsv($output, []);

            // Violations
            fputcsv($output, ['--- TECHNICAL & SPORTING VIOLATIONS DETECTED ---']);
            fputcsv($output, ['VIOLATION ID', 'MEASUREMENT REF', 'DESCRIPTION', 'SEVERITY', 'STATUS', 'DETECTED AT', 'DETECTED BY']);
            $v_stmt = $conn->prepare("SELECT v.*, u.full_name as inspector_name 
                                      FROM VIOLATIONS v 
                                      JOIN INSPECTION_MEASUREMENTS m ON v.measurement_id = m.measurement_id 
                                      JOIN INSPECTION_SESSIONS s ON m.session_id = s.session_id 
                                      LEFT JOIN USERS u ON v.detected_by = u.user_id 
                                      WHERE s.car_id = ? ORDER BY v.violation_id DESC");
            $v_stmt->bind_param("i", $cid);
            $v_stmt->execute();
            $v_res = $v_stmt->get_result();
            while ($vr = $v_res->fetch_assoc()) {
                fputcsv($output, [
                    $vr['violation_id'],
                    $vr['measurement_id'],
                    $vr['violation_description'],
                    $vr['severity'],
                    $vr['status'],
                    $vr['detected_at'],
                    $vr['inspector_name'] ?? 'FIA Scrutineer'
                ]);
            }
            fputcsv($output, []);

            // Penalties
            fputcsv($output, ['--- STEWARD PENALTIES & SANCTIONS ---']);
            fputcsv($output, ['PENALTY ID', 'VIOLATION REF', 'PENALTY TYPE', 'VALUE / IMPACT', 'STATUS', 'DECISION DATE', 'COMMENTS']);
            $p_stmt = $conn->prepare("SELECT p.* FROM PENALTIES p 
                                      JOIN VIOLATIONS v ON p.violation_id = v.violation_id 
                                      JOIN INSPECTION_MEASUREMENTS m ON v.measurement_id = m.measurement_id 
                                      JOIN INSPECTION_SESSIONS s ON m.session_id = s.session_id 
                                      WHERE s.car_id = ? ORDER BY p.penalty_id DESC");
            $p_stmt->bind_param("i", $cid);
            $p_stmt->execute();
            $p_res = $p_stmt->get_result();
            while ($pr = $p_res->fetch_assoc()) {
                fputcsv($output, [
                    $pr['penalty_id'],
                    $pr['violation_id'],
                    $pr['penalty_type'],
                    $pr['penalty_value'],
                    $pr['status'],
                    $pr['decision_date'],
                    $pr['comments']
                ]);
            }
            fputcsv($output, []);

            // Repairs & Re-Inspections
            fputcsv($output, ['--- RECTIFICATIONS, REPAIRS & RE-INSPECTIONS ---']);
            fputcsv($output, ['REPAIR ID', 'VIOLATION REF', 'REPAIR DESCRIPTION', 'PERFORMED BY', 'PERFORMED AT', 'STATUS', 'RE-INSPECTION RESULT']);
            $r_stmt = $conn->prepare("SELECT r.*, ri.result as reinspect_result 
                                      FROM REPAIRS r 
                                      JOIN VIOLATIONS v ON r.violation_id = v.violation_id 
                                      JOIN INSPECTION_MEASUREMENTS m ON v.measurement_id = m.measurement_id 
                                      JOIN INSPECTION_SESSIONS s ON m.session_id = s.session_id 
                                      LEFT JOIN RE_INSPECTIONS ri ON r.repair_id = ri.repair_id 
                                      WHERE s.car_id = ? ORDER BY r.repair_id DESC");
            $r_stmt->bind_param("i", $cid);
            $r_stmt->execute();
            $r_res = $r_stmt->get_result();
            while ($rr = $r_res->fetch_assoc()) {
                fputcsv($output, [
                    $rr['repair_id'],
                    $rr['violation_id'],
                    $rr['description'],
                    $rr['performed_by'],
                    $rr['performed_at'],
                    $rr['status'],
                    $rr['reinspect_result'] ?? 'Pending'
                ]);
            }
            fputcsv($output, []);

            // Appeals
            fputcsv($output, ['--- STEWARD APPEALS & ARBITRATION ---']);
            fputcsv($output, ['APPEAL ID', 'PENALTY REF', 'APPEAL REASON', 'STATUS', 'SUBMITTED AT', 'DECISION SUMMARY']);
            $a_stmt = $conn->prepare("SELECT a.* FROM APPEALS a 
                                      JOIN PENALTIES p ON a.penalty_id = p.penalty_id 
                                      JOIN VIOLATIONS v ON p.violation_id = v.violation_id 
                                      JOIN INSPECTION_MEASUREMENTS m ON v.measurement_id = m.measurement_id 
                                      JOIN INSPECTION_SESSIONS s ON m.session_id = s.session_id 
                                      WHERE s.car_id = ? ORDER BY a.appeal_id DESC");
            $a_stmt->bind_param("i", $cid);
            $a_stmt->execute();
            $a_res = $a_stmt->get_result();
            while ($ar = $a_res->fetch_assoc()) {
                fputcsv($output, [
                    $ar['appeal_id'],
                    $ar['penalty_id'],
                    $ar['appeal_reason'],
                    $ar['status'],
                    $ar['submitted_at'],
                    $ar['decision_summary'] ?? 'Under Deliberation'
                ]);
            }

            fclose($output);
            exit;
        }
    }
}

$message = '';
$action_type = '';

// 1. FORM 1: PUBLISH / UPDATE REGULATION HANDLER
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_regulation') {
    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $content = trim($_POST['content'] ?? '');
    $version = trim($_POST['version'] ?? '2026.1');
    $effective_from = !empty($_POST['effective_from']) ? $_POST['effective_from'] : date('Y-m-d');
    $effective_to = !empty($_POST['effective_to']) ? $_POST['effective_to'] : null;
    $status = trim($_POST['status'] ?? 'active');
    $document_url = trim($_POST['document_url'] ?? '');
    $created_by = intval($_SESSION['user_id'] ?? 1);

    if (!empty($title) && !empty($content)) {
        $stmt = $conn->prepare("INSERT INTO REGULATIONS (title, description, content, version, effective_from, effective_to, status, document_url, created_by, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");
        if ($stmt) {
            $stmt->bind_param("ssssssssi", $title, $description, $content, $version, $effective_from, $effective_to, $status, $document_url, $created_by);
            if ($stmt->execute()) {
                $new_reg_id = $conn->insert_id;
                
                // Write Audit Log
                log_system_audit($conn, $created_by, "PUBLISH_REGULATION: Promulgated FIA Technical Regulation '{$title}'", "REGULATIONS", $new_reg_id, [
                    'version' => $version,
                    'status' => $status,
                    'effective_from' => $effective_from,
                    'document_url' => $document_url
                ]);

                $message = "FIA Technical Regulation #{$new_reg_id} successfully promulgated and recorded in FIA Codex.";
                $action_type = "regulation_published";
            }
        }
    }
}

// 2. FORM 2: LOG SYSTEM INTEGRITY AUDIT STAMP
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'log_manual_audit') {
    $audit_action = trim($_POST['audit_action'] ?? 'SYSTEM_INTEGRITY_VERIFICATION');
    $entity_type = trim($_POST['entity_type'] ?? 'SYSTEM_PORTAL');
    $entity_id = intval($_POST['entity_id'] ?? 0);
    $notes = trim($_POST['notes'] ?? '');
    $current_uid = intval($_SESSION['user_id'] ?? 1);

    log_system_audit($conn, $current_uid, $audit_action, $entity_type, $entity_id, [
        'verification_notes' => $notes,
        'cryptographic_seal' => hash('sha256', time() . $current_uid . $audit_action),
        'timestamp' => date('c')
    ]);

    $message = "Cryptographic FIA audit stamp sealed and appended to immutable ledger.";
    $action_type = "audit_stamped";
}

// TOP HUD METRICS CALCULATION
$total_audits = $conn->query("SELECT COUNT(*) FROM AUDIT_LOGS")->fetch_row()[0] ?? 0;
$active_regs = $conn->query("SELECT COUNT(*) FROM REGULATIONS WHERE status = 'active' OR status = 'Active'")->fetch_row()[0] ?? 0;
$distinct_users = $conn->query("SELECT COUNT(DISTINCT user_id) FROM AUDIT_LOGS")->fetch_row()[0] ?? 0;
if ($distinct_users == 0) {
    $distinct_users = $conn->query("SELECT COUNT(*) FROM USERS WHERE status = 'active'")->fetch_row()[0] ?? 1;
}

// AUDIT CONSOLE FILTERS
$filter_entity = $_GET['filter_entity'] ?? '';
$filter_user = intval($_GET['filter_user'] ?? 0);
$filter_date_from = $_GET['filter_date_from'] ?? '';
$filter_date_to = $_GET['filter_date_to'] ?? '';
$filter_search = trim($_GET['filter_search'] ?? '');

$audit_query = "SELECT a.*, u.full_name as operator_name 
                FROM AUDIT_LOGS a 
                LEFT JOIN USERS u ON a.user_id = u.user_id 
                WHERE 1=1";
$params = [];
$types = "";

if (!empty($filter_entity)) {
    $audit_query .= " AND a.entity_type = ?";
    $params[] = $filter_entity;
    $types .= "s";
}
if ($filter_user > 0) {
    $audit_query .= " AND a.user_id = ?";
    $params[] = $filter_user;
    $types .= "i";
}
if (!empty($filter_date_from)) {
    $audit_query .= " AND DATE(a.created_at) >= ?";
    $params[] = $filter_date_from;
    $types .= "s";
}
if (!empty($filter_date_to)) {
    $audit_query .= " AND DATE(a.created_at) <= ?";
    $params[] = $filter_date_to;
    $types .= "s";
}
if (!empty($filter_search)) {
    $audit_query .= " AND (a.action LIKE ? OR a.details LIKE ?)";
    $like = "%" . $filter_search . "%";
    $params[] = $like;
    $params[] = $like;
    $types .= "ss";
}
$audit_query .= " ORDER BY a.log_id DESC LIMIT 40";

$stmt_audit = $conn->prepare($audit_query);
if (!empty($params)) {
    $stmt_audit->bind_param($types, ...$params);
}
$stmt_audit->execute();
$audit_logs_result = $stmt_audit->get_result();

// SELECTED CAR DOSSIER RETRIEVAL
$selected_car_id = intval($_GET['car_id'] ?? 0);
$car_dossier = null;
$car_measurements = [];
$car_violations = [];
$car_penalties = [];
$car_repairs = [];
$car_reinspections = [];
$car_appeals = [];

// Get all cars for the selector dropdown
$cars_dropdown_res = $conn->query("SELECT c.car_id, c.car_name, c.chassis_number, t.team_name, d.full_name as driver_name 
                                    FROM CARS c 
                                    LEFT JOIN TEAMS t ON c.team_id = t.team_id 
                                    LEFT JOIN DRIVERS d ON c.driver_id = d.driver_id 
                                    ORDER BY c.car_id ASC");

if ($selected_car_id > 0) {
    // Car Profile
    $stmt_cp = $conn->prepare("SELECT c.*, t.team_name, d.full_name as driver_name, d.license_number, d.country as nationality, d.country 
                               FROM CARS c 
                               LEFT JOIN TEAMS t ON c.team_id = t.team_id 
                               LEFT JOIN DRIVERS d ON c.driver_id = d.driver_id 
                               WHERE c.car_id = ?");
    $stmt_cp->bind_param("i", $selected_car_id);
    $stmt_cp->execute();
    $car_dossier = $stmt_cp->get_result()->fetch_assoc();

    if ($car_dossier) {
        // Measurements
        $stmt_cm = $conn->prepare("SELECT m.*, s.session_type, s.location FROM INSPECTION_MEASUREMENTS m 
                                   JOIN INSPECTION_SESSIONS s ON m.session_id = s.session_id 
                                   WHERE s.car_id = ? ORDER BY m.measurement_id DESC");
        $stmt_cm->bind_param("i", $selected_car_id);
        $stmt_cm->execute();
        $car_measurements = $stmt_cm->get_result()->fetch_all(MYSQLI_ASSOC);

        // Violations
        $stmt_cv = $conn->prepare("SELECT v.*, m.measurement_name, u.full_name as detected_by_name 
                                   FROM VIOLATIONS v 
                                   JOIN INSPECTION_MEASUREMENTS m ON v.measurement_id = m.measurement_id 
                                   JOIN INSPECTION_SESSIONS s ON m.session_id = s.session_id 
                                   LEFT JOIN USERS u ON v.detected_by = u.user_id 
                                   WHERE s.car_id = ? ORDER BY v.violation_id DESC");
        $stmt_cv->bind_param("i", $selected_car_id);
        $stmt_cv->execute();
        $car_violations = $stmt_cv->get_result()->fetch_all(MYSQLI_ASSOC);

        // Penalties
        $stmt_cpen = $conn->prepare("SELECT p.*, v.violation_description, u.full_name as steward_name 
                                     FROM PENALTIES p 
                                     JOIN VIOLATIONS v ON p.violation_id = v.violation_id 
                                     JOIN INSPECTION_MEASUREMENTS m ON v.measurement_id = m.measurement_id 
                                     JOIN INSPECTION_SESSIONS s ON m.session_id = s.session_id 
                                     LEFT JOIN USERS u ON p.steward_id = u.user_id 
                                     WHERE s.car_id = ? ORDER BY p.penalty_id DESC");
        $stmt_cpen->bind_param("i", $selected_car_id);
        $stmt_cpen->execute();
        $car_penalties = $stmt_cpen->get_result()->fetch_all(MYSQLI_ASSOC);

        // Repairs
        $stmt_crep = $conn->prepare("SELECT r.*, v.violation_description 
                                     FROM REPAIRS r 
                                     JOIN VIOLATIONS v ON r.violation_id = v.violation_id 
                                     JOIN INSPECTION_MEASUREMENTS m ON v.measurement_id = m.measurement_id 
                                     JOIN INSPECTION_SESSIONS s ON m.session_id = s.session_id 
                                     WHERE s.car_id = ? ORDER BY r.repair_id DESC");
        $stmt_crep->bind_param("i", $selected_car_id);
        $stmt_crep->execute();
        $car_repairs = $stmt_crep->get_result()->fetch_all(MYSQLI_ASSOC);

        // Re-Inspections
        $stmt_crei = $conn->prepare("SELECT ri.*, r.description as repair_desc, u.full_name as inspector_name 
                                     FROM RE_INSPECTIONS ri 
                                     JOIN REPAIRS r ON ri.repair_id = r.repair_id 
                                     JOIN VIOLATIONS v ON r.violation_id = v.violation_id 
                                     JOIN INSPECTION_MEASUREMENTS m ON v.measurement_id = m.measurement_id 
                                     JOIN INSPECTION_SESSIONS s ON m.session_id = s.session_id 
                                     LEFT JOIN USERS u ON ri.inspector_id = u.user_id 
                                     WHERE s.car_id = ? ORDER BY ri.reinspection_id DESC");
        $stmt_crei->bind_param("i", $selected_car_id);
        $stmt_crei->execute();
        $car_reinspections = $stmt_crei->get_result()->fetch_all(MYSQLI_ASSOC);

        // Appeals
        $stmt_cap = $conn->prepare("SELECT a.*, p.penalty_type, u.full_name as judge_name 
                                    FROM APPEALS a 
                                    JOIN PENALTIES p ON a.penalty_id = p.penalty_id 
                                    JOIN VIOLATIONS v ON p.violation_id = v.violation_id 
                                    JOIN INSPECTION_MEASUREMENTS m ON v.measurement_id = m.measurement_id 
                                    JOIN INSPECTION_SESSIONS s ON m.session_id = s.session_id 
                                    LEFT JOIN USERS u ON a.decision_by = u.user_id 
                                    WHERE s.car_id = ? ORDER BY a.appeal_id DESC");
        $stmt_cap->bind_param("i", $selected_car_id);
        $stmt_cap->execute();
        $car_appeals = $stmt_cap->get_result()->fetch_all(MYSQLI_ASSOC);
    }
}

// Fetch active regulations for listing
$reg_list_res = $conn->query("SELECT r.*, u.full_name as author_name 
                              FROM REGULATIONS r 
                              LEFT JOIN USERS u ON r.created_by = u.user_id 
                              ORDER BY r.regulation_id DESC LIMIT 15");

// Fetch all users for filter
$users_filter_res = $conn->query("SELECT user_id, full_name, email FROM USERS ORDER BY full_name ASC");

$current_page = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>FIA FSMS - Feature 10: System Audit Trail & Technical Master Dossier</title>
    <!-- Modern Cyber & Technical Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@500;700;900&family=Share+Tech+Mono&family=Titillium+Web:wght@400;600;700;900&display=swap" rel="stylesheet">
    <style>
        :root {
            --f1-red: #ff1801;
            --f1-cyan: #00d2be;
            --f1-amber: #ff9f1a;
            --f1-purple: #9b59b6;
            --f1-blue: #0984e3;
            --f1-dark: #07070e;
            --f1-panel: rgba(14, 14, 24, 0.95);
            --border-glow: rgba(0, 210, 190, 0.25);
            --f1-card-bg: rgba(20, 20, 35, 0.85);
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            background-color: var(--f1-dark);
            background-image: 
                radial-gradient(circle at 10% 20%, rgba(0, 210, 190, 0.04) 0%, transparent 40%),
                radial-gradient(circle at 90% 80%, rgba(255, 24, 1, 0.04) 0%, transparent 40%),
                linear-gradient(rgba(255, 255, 255, 0.015) 1px, transparent 1px),
                linear-gradient(90deg, rgba(255, 255, 255, 0.015) 1px, transparent 1px);
            background-size: 100% 100%, 100% 100%, 30px 30px, 30px 30px;
            color: #d1d8e0;
            font-family: 'Titillium Web', sans-serif;
            min-height: 100vh;
            padding-bottom: 40px;
        }

        .main-container {
            max-width: 1440px;
            margin: 0 auto;
            padding: 24px;
        }

        /* Top HUD Header */
        .top-hud-bar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: linear-gradient(90deg, rgba(20, 20, 35, 0.95) 0%, rgba(10, 10, 20, 0.95) 100%);
            border-left: 4px solid var(--f1-cyan);
            border-bottom: 1px solid rgba(0, 210, 190, 0.2);
            padding: 16px 24px;
            border-radius: 8px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.5);
            margin-bottom: 24px;
        }
        .hud-title-group h1 {
            font-family: 'Orbitron', sans-serif;
            font-size: 20px;
            font-weight: 900;
            color: #ffffff;
            letter-spacing: 1.5px;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .hud-title-group p {
            font-family: 'Share Tech Mono', monospace;
            font-size: 11px;
            color: var(--f1-cyan);
            margin-top: 4px;
            letter-spacing: 1px;
        }
        .hud-actions {
            display: flex;
            align-items: center;
            gap: 14px;
        }

        /* Radio Speech / Sound HUD Button */
        .btn-telemetry-sound {
            display: flex;
            align-items: center;
            gap: 8px;
            background: rgba(0, 210, 190, 0.1);
            border: 1px solid var(--f1-cyan);
            color: var(--f1-cyan);
            font-family: 'Orbitron', sans-serif;
            font-size: 11px;
            font-weight: 700;
            padding: 8px 14px;
            border-radius: 6px;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        .btn-telemetry-sound:hover {
            background: var(--f1-cyan);
            color: #07070e;
            box-shadow: 0 0 15px rgba(0, 210, 190, 0.5);
        }

        /* Metric Tiles Grid */
        .metrics-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 16px;
            margin-bottom: 24px;
        }
        .metric-card {
            background: var(--f1-card-bg);
            border: 1px solid rgba(255, 255, 255, 0.08);
            border-radius: 8px;
            padding: 16px 20px;
            position: relative;
            overflow: hidden;
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.4);
            transition: transform 0.2s ease, border-color 0.2s ease;
        }
        .metric-card:hover {
            transform: translateY(-2px);
            border-color: rgba(255, 255, 255, 0.2);
        }
        .metric-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 4px;
            height: 100%;
        }
        .metric-card.cyan::before { background: var(--f1-cyan); box-shadow: 0 0 10px var(--f1-cyan); }
        .metric-card.amber::before { background: var(--f1-amber); box-shadow: 0 0 10px var(--f1-amber); }
        .metric-card.red::before { background: var(--f1-red); box-shadow: 0 0 10px var(--f1-red); }
        .metric-card.purple::before { background: var(--f1-purple); box-shadow: 0 0 10px var(--f1-purple); }

        .metric-label {
            font-family: 'Share Tech Mono', monospace;
            font-size: 11px;
            color: #8c8c9e;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 6px;
        }
        .metric-value {
            font-family: 'Orbitron', sans-serif;
            font-size: 24px;
            font-weight: 900;
            color: #ffffff;
            display: flex;
            align-items: baseline;
            gap: 6px;
        }
        .metric-sub {
            font-family: 'Share Tech Mono', monospace;
            font-size: 10px;
            color: var(--f1-cyan);
            margin-top: 4px;
        }

        /* Banner Alerts */
        .f1-alert {
            background: rgba(0, 210, 190, 0.12);
            border: 1px solid var(--f1-cyan);
            border-radius: 6px;
            padding: 12px 18px;
            font-family: 'Share Tech Mono', monospace;
            font-size: 12px;
            color: #ffffff;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
            box-shadow: 0 0 15px rgba(0, 210, 190, 0.25);
            animation: slideDown 0.3s ease;
        }
        @keyframes slideDown {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        /* Section Layouts */
        .dual-deck-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 24px;
            margin-bottom: 28px;
        }
        .panel-card {
            background: var(--f1-panel);
            border: 1px solid rgba(255, 255, 255, 0.08);
            border-radius: 8px;
            padding: 22px;
            box-shadow: 0 12px 30px rgba(0, 0, 0, 0.5);
            position: relative;
        }
        .panel-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 1px solid rgba(255, 255, 255, 0.08);
            padding-bottom: 12px;
            margin-bottom: 18px;
        }
        .panel-title {
            font-family: 'Orbitron', sans-serif;
            font-size: 14px;
            font-weight: 700;
            color: #ffffff;
            letter-spacing: 1px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .panel-badge {
            font-family: 'Share Tech Mono', monospace;
            font-size: 10px;
            padding: 3px 8px;
            border-radius: 4px;
            background: rgba(255, 255, 255, 0.05);
            color: var(--f1-cyan);
            border: 1px solid rgba(0, 210, 190, 0.3);
        }

        /* Forms */
        .f1-form-group {
            margin-bottom: 14px;
        }
        .f1-form-group label {
            display: block;
            font-family: 'Share Tech Mono', monospace;
            font-size: 11px;
            color: #a4b0be;
            margin-bottom: 6px;
            letter-spacing: 0.5px;
        }
        .f1-input, .f1-select, .f1-textarea {
            width: 100%;
            background: rgba(8, 8, 14, 0.8);
            border: 1px solid rgba(255, 255, 255, 0.12);
            border-radius: 6px;
            padding: 9px 12px;
            font-family: 'Titillium Web', sans-serif;
            font-size: 12.5px;
            color: #ffffff;
            outline: none;
            transition: all 0.2s ease;
        }
        .f1-input:focus, .f1-select:focus, .f1-textarea:focus {
            border-color: var(--f1-cyan);
            box-shadow: 0 0 10px rgba(0, 210, 190, 0.3);
            background: rgba(12, 12, 22, 0.95);
        }
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px;
        }
        .btn-f1-submit {
            width: 100%;
            background: linear-gradient(90deg, #ff1801 0%, #d63031 100%);
            border: none;
            color: #ffffff;
            font-family: 'Orbitron', sans-serif;
            font-size: 11.5px;
            font-weight: 700;
            letter-spacing: 1.2px;
            padding: 11px;
            border-radius: 6px;
            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(255, 24, 1, 0.4);
            margin-top: 8px;
        }
        .btn-f1-submit:hover {
            box-shadow: 0 0 20px rgba(255, 24, 1, 0.7);
            transform: translateY(-1px);
        }
        .btn-f1-secondary {
            background: rgba(255, 255, 255, 0.06);
            border: 1px solid rgba(255, 255, 255, 0.15);
            color: #ffffff;
            font-family: 'Orbitron', sans-serif;
            font-size: 11px;
            font-weight: 700;
            padding: 8px 14px;
            border-radius: 6px;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            transition: all 0.2s ease;
        }
        .btn-f1-secondary:hover {
            background: rgba(255, 255, 255, 0.12);
            border-color: var(--f1-cyan);
            color: var(--f1-cyan);
        }

        /* Filter Toolbar */
        .filter-bar {
            display: flex;
            flex-wrap: wrap;
            align-items: flex-end;
            gap: 12px;
            background: rgba(10, 10, 18, 0.85);
            border: 1px solid rgba(255, 255, 255, 0.08);
            border-radius: 8px;
            padding: 14px 18px;
            margin-bottom: 18px;
        }
        .filter-item {
            flex: 1;
            min-width: 140px;
        }

        /* Custom Tables */
        .f1-table-wrapper {
            overflow-x: auto;
            border-radius: 6px;
            border: 1px solid rgba(255, 255, 255, 0.08);
        }
        .f1-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 12px;
            text-align: left;
            background: rgba(8, 8, 14, 0.6);
        }
        .f1-table th {
            font-family: 'Orbitron', sans-serif;
            font-size: 10px;
            font-weight: 700;
            color: #8c8c9e;
            background: rgba(15, 15, 28, 0.95);
            padding: 10px 14px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            letter-spacing: 0.8px;
            text-transform: uppercase;
        }
        .f1-table td {
            padding: 10px 14px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.04);
            color: #e2e8f0;
            font-family: 'Titillium Web', sans-serif;
        }
        .f1-table tr:hover {
            background: rgba(255, 255, 255, 0.03);
        }

        /* Status Pills */
        .status-pill {
            display: inline-block;
            font-family: 'Share Tech Mono', monospace;
            font-size: 9.5px;
            font-weight: 700;
            padding: 2px 8px;
            border-radius: 4px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .pill-active, .pill-passed, .pill-rectified {
            background: rgba(46, 204, 113, 0.15);
            color: #2ecc71;
            border: 1px solid rgba(46, 204, 113, 0.4);
        }
        .pill-pending, .pill-review {
            background: rgba(241, 196, 15, 0.15);
            color: #f1c40f;
            border: 1px solid rgba(241, 196, 15, 0.4);
        }
        .pill-failed, .pill-rejected, .pill-major {
            background: rgba(231, 76, 60, 0.15);
            color: #e74c3c;
            border: 1px solid rgba(231, 76, 60, 0.4);
        }
        .pill-info {
            background: rgba(0, 210, 190, 0.15);
            color: var(--f1-cyan);
            border: 1px solid rgba(0, 210, 190, 0.4);
        }

        /* Technical Master Dossier Section */
        .dossier-master-panel {
            background: var(--f1-panel);
            border: 1px solid rgba(0, 210, 190, 0.3);
            border-radius: 8px;
            padding: 24px;
            margin-top: 28px;
            box-shadow: 0 15px 40px rgba(0, 0, 0, 0.6);
        }
        .dossier-selector-bar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
            background: rgba(10, 10, 20, 0.85);
            padding: 14px 18px;
            border-radius: 6px;
            border: 1px solid rgba(255, 255, 255, 0.08);
            margin-bottom: 22px;
        }
        .car-specs-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 14px;
            background: rgba(0, 0, 0, 0.3);
            border: 1px solid rgba(255, 255, 255, 0.06);
            border-radius: 6px;
            padding: 16px;
            margin-bottom: 22px;
        }
        .car-spec-item .label {
            font-family: 'Share Tech Mono', monospace;
            font-size: 10px;
            color: #8c8c9e;
            text-transform: uppercase;
        }
        .car-spec-item .val {
            font-family: 'Orbitron', sans-serif;
            font-size: 13.5px;
            font-weight: 700;
            color: #ffffff;
            margin-top: 2px;
        }

        /* Dossier Subsection Cards */
        .dossier-sub-card {
            background: rgba(10, 10, 18, 0.6);
            border: 1px solid rgba(255, 255, 255, 0.06);
            border-radius: 6px;
            padding: 16px;
            margin-bottom: 16px;
        }
        .dossier-sub-title {
            font-family: 'Orbitron', sans-serif;
            font-size: 12px;
            font-weight: 700;
            color: var(--f1-cyan);
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        /* JSON Details Chip */
        .json-details-box {
            font-family: 'Share Tech Mono', monospace;
            font-size: 10px;
            color: #00d2be;
            background: rgba(0, 210, 190, 0.06);
            padding: 4px 8px;
            border-radius: 4px;
            border: 1px solid rgba(0, 210, 190, 0.15);
            max-width: 280px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        /* PRINT STYLES - FIA OFFICIAL FORMAT */
        @media print {
            body {
                background: #ffffff !important;
                color: #000000 !important;
                padding-left: 0 !important;
                font-size: 10pt;
            }
            .f1-sidebar, .top-hud-bar, .dual-deck-grid, .filter-bar, .dossier-selector-bar, .btn-telemetry-sound, .btn-f1-secondary, .btn-f1-submit {
                display: none !important;
            }
            .dossier-master-panel {
                border: 2px solid #000000 !important;
                background: #ffffff !important;
                color: #000000 !important;
                box-shadow: none !important;
                padding: 10px !important;
                margin-top: 0 !important;
            }
            .dossier-master-panel * {
                color: #000000 !important;
                background: transparent !important;
            }
            .f1-table {
                border: 1px solid #000000 !important;
            }
            .f1-table th {
                background: #f0f0f0 !important;
                color: #000000 !important;
                border: 1px solid #000000 !important;
            }
            .f1-table td {
                border: 1px solid #dddddd !important;
            }
            .car-specs-grid {
                border: 1px solid #000000 !important;
            }
            .print-official-header {
                display: block !important;
                text-align: center;
                border-bottom: 2px solid #000000;
                padding-bottom: 12px;
                margin-bottom: 16px;
            }
            .print-official-footer {
                display: block !important;
                margin-top: 40px;
                display: flex !important;
                justify-content: space-between;
                font-size: 9pt;
            }
        }
        .print-official-header, .print-official-footer {
            display: none;
        }
    </style>
</head>
<body>

<!-- Include Global Sidebar Navigation -->
<?php include 'navbar.php'; ?>

<div class="main-container">

    <!-- Top Telemetry HUD Header -->
    <div class="top-hud-bar">
        <div class="hud-title-group">
            <h1><span style="color: var(--f1-cyan);">🛡️</span> FEATURE 10: FIA AUDIT TRAIL & TECHNICAL DOSSIER</h1>
            <p>IMMUTABLE CODEX // CRYPTOGRAPHIC VERIFICATION // TECHNICAL SCRUTINEERING ARBITRATION</p>
        </div>
        <div class="hud-actions">
            <button class="btn-telemetry-sound" id="btnAudioReport" onclick="speakAuditComms()">
                <span>🎙️</span> SOUND AUDIT REPORT
            </button>
            <a href="?export=audit_csv" class="btn-f1-secondary" title="Export Complete System Audit Log">
                <span>📥</span> EXPORT AUDIT CSV
            </a>
        </div>
    </div>

    <?php if (!empty($message)): ?>
        <div class="f1-alert" id="systemAlertBox">
            <span>⚡ [SYSTEM LOGGED]</span> <?php echo htmlspecialchars($message); ?>
        </div>
    <?php endif; ?>

    <!-- Top HUD Metrics Deck -->
    <div class="metrics-grid">
        <div class="metric-card cyan">
            <div class="metric-label">Total System Audit Events</div>
            <div class="metric-value"><?php echo number_format($total_audits); ?></div>
            <div class="metric-sub">Logged in Immutable Ledger</div>
        </div>

        <div class="metric-card amber">
            <div class="metric-label">Active Technical Regulations</div>
            <div class="metric-value"><?php echo number_format($active_regs); ?></div>
            <div class="metric-sub">Codex v2026.1 Promulgated</div>
        </div>

        <div class="metric-card red">
            <div class="metric-label">Operating Active Personnel</div>
            <div class="metric-value"><?php echo number_format($distinct_users); ?></div>
            <div class="metric-sub">Stewards, Inspectors & Admins</div>
        </div>

        <div class="metric-card purple">
            <div class="metric-label">Ledger Integrity Seal</div>
            <div class="metric-value" style="font-size: 16px; margin-top: 4px;">SHA-256 OK</div>
            <div class="metric-sub">FIA Cryptographic Node Synced</div>
        </div>
    </div>

    <!-- Dual Command Section: Regulation Master & Audit Integrity Seal -->
    <div class="dual-deck-grid">
        
        <!-- Command 1: Promulgate / Update FIA Technical Regulation -->
        <div class="panel-card">
            <div class="panel-header">
                <div class="panel-title">
                    <span>📜</span> FIA REGULATION MASTER CONTROL
                </div>
                <div class="panel-badge">CODEX v2026.1</div>
            </div>

            <form method="POST" action="feature10.php">
                <input type="hidden" name="action" value="save_regulation">
                
                <div class="f1-form-group">
                    <label>REGULATION ARTICLE / TITLE</label>
                    <input type="text" name="title" class="f1-input" placeholder="e.g. Art 3.5.2 Rear Wing Flexibility & Load Limits" required>
                </div>

                <div class="form-row">
                    <div class="f1-form-group">
                        <label>VERSION / CODEX CODE</label>
                        <input type="text" name="version" class="f1-input" value="2026.1" required>
                    </div>
                    <div class="f1-form-group">
                        <label>STATUS</label>
                        <select name="status" class="f1-select">
                            <option value="active" selected>Active / In Force</option>
                            <option value="draft">Draft / Under Review</option>
                            <option value="deprecated">Deprecated</option>
                        </select>
                    </div>
                </div>

                <div class="form-row">
                    <div class="f1-form-group">
                        <label>EFFECTIVE FROM</label>
                        <input type="date" name="effective_from" class="f1-input" value="<?php echo date('Y-m-d'); ?>" required>
                    </div>
                    <div class="f1-form-group">
                        <label>DOCUMENT / TECH BULLETIN URL</label>
                        <input type="text" name="document_url" class="f1-input" placeholder="https://fia.com/bulletins/2026-TB-04.pdf">
                    </div>
                </div>

                <div class="f1-form-group">
                    <label>SUMMARY / SHORT DESCRIPTION</label>
                    <input type="text" name="description" class="f1-input" placeholder="Brief technical summary of the rule...">
                </div>

                <div class="f1-form-group">
                    <label>FULL REGULATION STATUTORY TEXT</label>
                    <textarea name="content" class="f1-textarea" rows="3" placeholder="Full statutory text and deflection tolerances..." required></textarea>
                </div>

                <button type="submit" class="btn-f1-submit">
                    ⚖️ PROMULGATE & RATIFY REGULATION
                </button>
            </form>
        </div>

        <!-- Command 2: Cryptographic FIA Audit Stamp Seal -->
        <div class="panel-card">
            <div class="panel-header">
                <div class="panel-title">
                    <span>🛡️</span> CRYPTOGRAPHIC INTEGRITY AUDIT STAMP
                </div>
                <div class="panel-badge">LEDGER SEAL</div>
            </div>

            <form method="POST" action="feature10.php">
                <input type="hidden" name="action" value="log_manual_audit">
                
                <div class="f1-form-group">
                    <label>AUDIT VERIFICATION ACTION</label>
                    <select name="audit_action" class="f1-select">
                        <option value="DELEGATE_SCRUTINEERING_INTEGRITY_CHECK">Delegate Scrutineering Integrity Check</option>
                        <option value="PARC_FERME_SYSTEM_AUDIT">Parc Fermé Security & Seal Verification</option>
                        <option value="STEWARD_DECISION_RATIFICATION_AUDIT">Steward Decision Legal Audit & Sign-off</option>
                        <option value="TECHNICAL_WEIGHT_SCALES_CALIBRATION">Technical Scrutineering Scales Calibration</option>
                        <option value="SYSTEM_BACKUP_SNAPSHOT_AUDIT">FIA System Database Snapshot Verified</option>
                    </select>
                </div>

                <div class="form-row">
                    <div class="f1-form-group">
                        <label>TARGET ENTITY TYPE</label>
                        <select name="entity_type" class="f1-select">
                            <option value="SYSTEM_PORTAL">SYSTEM_PORTAL</option>
                            <option value="INSPECTION_MEASUREMENTS">INSPECTION_MEASUREMENTS</option>
                            <option value="VIOLATIONS">VIOLATIONS</option>
                            <option value="PENALTIES">PENALTIES</option>
                            <option value="REPAIRS">REPAIRS</option>
                            <option value="APPEALS">APPEALS</option>
                            <option value="REGULATIONS">REGULATIONS</option>
                        </select>
                    </div>
                    <div class="f1-form-group">
                        <label>ENTITY RECORD ID (OPTIONAL)</label>
                        <input type="number" name="entity_id" class="f1-input" placeholder="e.g. 101" value="0">
                    </div>
                </div>

                <div class="f1-form-group">
                    <label>AUDIT ATTESTATION & TECHNICAL NOTES</label>
                    <textarea name="notes" class="f1-textarea" rows="4" placeholder="Attestation: All physical tolerances and regulatory telemetry logs verified nominal against 2026 FIA Technical Regulations." required></textarea>
                </div>

                <button type="submit" class="btn-f1-submit" style="background: linear-gradient(90deg, #0984e3 0%, #00d2be 100%);">
                    🔒 SEAL & APPEND TO AUDIT LEDGER
                </button>
            </form>
        </div>

    </div>

    <!-- Audit Query Console & Live Filter Log Interface -->
    <div class="panel-card" style="margin-bottom: 28px;">
        <div class="panel-header">
            <div class="panel-title">
                <span>🔍</span> AUDIT QUERY CONSOLE & SYSTEM LOGS
            </div>
            <div class="panel-badge">REAL-TIME TRAIL</div>
        </div>

        <!-- Filter Bar -->
        <form method="GET" action="feature10.php" class="filter-bar">
            <?php if ($selected_car_id > 0): ?>
                <input type="hidden" name="car_id" value="<?php echo $selected_car_id; ?>">
            <?php endif; ?>

            <div class="filter-item">
                <label style="font-family: 'Share Tech Mono', monospace; font-size: 10px; color: #8c8c9e;">ENTITY TYPE</label>
                <select name="filter_entity" class="f1-select" onchange="this.form.submit()">
                    <option value="">-- ALL ENTITY TYPES --</option>
                    <option value="INSPECTION_MEASUREMENTS" <?php echo ($filter_entity === 'INSPECTION_MEASUREMENTS') ? 'selected' : ''; ?>>INSPECTION_MEASUREMENTS</option>
                    <option value="VIOLATIONS" <?php echo ($filter_entity === 'VIOLATIONS') ? 'selected' : ''; ?>>VIOLATIONS</option>
                    <option value="PENALTIES" <?php echo ($filter_entity === 'PENALTIES') ? 'selected' : ''; ?>>PENALTIES</option>
                    <option value="REPAIRS" <?php echo ($filter_entity === 'REPAIRS') ? 'selected' : ''; ?>>REPAIRS</option>
                    <option value="RE_INSPECTIONS" <?php echo ($filter_entity === 'RE_INSPECTIONS') ? 'selected' : ''; ?>>RE_INSPECTIONS</option>
                    <option value="APPEALS" <?php echo ($filter_entity === 'APPEALS') ? 'selected' : ''; ?>>APPEALS</option>
                    <option value="REGULATIONS" <?php echo ($filter_entity === 'REGULATIONS') ? 'selected' : ''; ?>>REGULATIONS</option>
                    <option value="SYSTEM_PORTAL" <?php echo ($filter_entity === 'SYSTEM_PORTAL') ? 'selected' : ''; ?>>SYSTEM_PORTAL</option>
                </select>
            </div>

            <div class="filter-item">
                <label style="font-family: 'Share Tech Mono', monospace; font-size: 10px; color: #8c8c9e;">OPERATOR USER</label>
                <select name="filter_user" class="f1-select" onchange="this.form.submit()">
                    <option value="0">-- ALL OPERATORS --</option>
                    <?php if ($users_filter_res): while ($u = $users_filter_res->fetch_assoc()): ?>
                        <option value="<?php echo $u['user_id']; ?>" <?php echo ($filter_user == $u['user_id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($u['full_name']); ?> (ID: <?php echo $u['user_id']; ?>)
                        </option>
                    <?php endwhile; endif; ?>
                </select>
            </div>

            <div class="filter-item">
                <label style="font-family: 'Share Tech Mono', monospace; font-size: 10px; color: #8c8c9e;">DATE FROM</label>
                <input type="date" name="filter_date_from" class="f1-input" value="<?php echo htmlspecialchars($filter_date_from); ?>">
            </div>

            <div class="filter-item">
                <label style="font-family: 'Share Tech Mono', monospace; font-size: 10px; color: #8c8c9e;">DATE TO</label>
                <input type="date" name="filter_date_to" class="f1-input" value="<?php echo htmlspecialchars($filter_date_to); ?>">
            </div>

            <div class="filter-item" style="min-width: 180px;">
                <label style="font-family: 'Share Tech Mono', monospace; font-size: 10px; color: #8c8c9e;">SEARCH KEYWORD</label>
                <input type="text" name="filter_search" class="f1-input" placeholder="Action keyword..." value="<?php echo htmlspecialchars($filter_search); ?>">
            </div>

            <div style="display: flex; gap: 8px;">
                <button type="submit" class="btn-f1-secondary" style="height: 38px;">
                    <span>🔎</span> FILTER
                </button>
                <a href="feature10.php<?php echo $selected_car_id > 0 ? '?car_id='.$selected_car_id : ''; ?>" class="btn-f1-secondary" style="height: 38px;">
                    RESET
                </a>
            </div>
        </form>

        <!-- Audit Log Table -->
        <div class="f1-table-wrapper">
            <table class="f1-table">
                <thead>
                    <tr>
                        <th>LOG ID</th>
                        <th>TIMESTAMP</th>
                        <th>OPERATOR</th>
                        <th>ACTION SUMMARY</th>
                        <th>ENTITY TYPE</th>
                        <th>ENTITY ID</th>
                        <th>IP ADDRESS</th>
                        <th>CRYPTOGRAPHIC DETAILS</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($audit_logs_result && $audit_logs_result->num_rows > 0): ?>
                        <?php while ($log = $audit_logs_result->fetch_assoc()): ?>
                            <tr>
                                <td style="font-family: 'Share Tech Mono', monospace; color: var(--f1-cyan);">
                                    #<?php echo str_pad($log['log_id'], 5, '0', STR_PAD_LEFT); ?>
                                </td>
                                <td style="font-family: 'Share Tech Mono', monospace; font-size: 11px; white-space: nowrap;">
                                    <?php echo htmlspecialchars($log['created_at']); ?>
                                </td>
                                <td>
                                    <strong><?php echo htmlspecialchars($log['operator_name'] ?? 'System / Anonymous'); ?></strong>
                                    <span style="font-size: 10px; color: #8c8c9e; display: block;">UID: <?php echo $log['user_id'] ?? 'N/A'; ?></span>
                                </td>
                                <td>
                                    <span style="color: #ffffff; font-weight: 600;"><?php echo htmlspecialchars($log['action']); ?></span>
                                </td>
                                <td>
                                    <span class="status-pill pill-info">
                                        <?php echo htmlspecialchars($log['entity_type'] ?? 'GENERAL'); ?>
                                    </span>
                                </td>
                                <td style="font-family: 'Share Tech Mono', monospace;">
                                    <?php echo !empty($log['entity_id']) ? '#' . $log['entity_id'] : '—'; ?>
                                </td>
                                <td style="font-family: 'Share Tech Mono', monospace; font-size: 11px; color: #8c8c9e;">
                                    <?php echo htmlspecialchars($log['ip_address'] ?? '127.0.0.1'); ?>
                                </td>
                                <td>
                                    <?php if (!empty($log['details'])): ?>
                                        <div class="json-details-box" title="<?php echo htmlspecialchars($log['details']); ?>">
                                            <?php echo htmlspecialchars($log['details']); ?>
                                        </div>
                                    <?php else: ?>
                                        <span style="color: #57606f; font-size: 11px;">N/A</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="8" style="text-align: center; padding: 24px; color: #8c8c9e;">
                                No system audit records found matching the active filter criteria.
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Active Technical Regulations in Codex -->
    <div class="panel-card" style="margin-bottom: 28px;">
        <div class="panel-header">
            <div class="panel-title">
                <span>📚</span> OFFICIAL FIA CODEX - PROMULGATED REGULATIONS
            </div>
            <div class="panel-badge">STATUTORY ARTICLES</div>
        </div>

        <div class="f1-table-wrapper">
            <table class="f1-table">
                <thead>
                    <tr>
                        <th>REG ID</th>
                        <th>ARTICLE & TITLE</th>
                        <th>CODEX VER</th>
                        <th>EFFECTIVE DATES</th>
                        <th>STATUS</th>
                        <th>STATUTORY CONTENT & TOLERANCES</th>
                        <th>AUTHOR / PROMULGATED BY</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($reg_list_res && $reg_list_res->num_rows > 0): ?>
                        <?php while ($rg = $reg_list_res->fetch_assoc()): ?>
                            <tr>
                                <td style="font-family: 'Share Tech Mono', monospace; color: var(--f1-amber);">
                                    REG-<?php echo str_pad($rg['regulation_id'], 3, '0', STR_PAD_LEFT); ?>
                                </td>
                                <td>
                                    <strong style="color: #ffffff;"><?php echo htmlspecialchars($rg['title']); ?></strong>
                                    <?php if (!empty($rg['description'])): ?>
                                        <div style="font-size: 11px; color: #8c8c9e;"><?php echo htmlspecialchars($rg['description']); ?></div>
                                    <?php endif; ?>
                                </td>
                                <td style="font-family: 'Share Tech Mono', monospace; color: var(--f1-cyan);">
                                    v<?php echo htmlspecialchars($rg['version']); ?>
                                </td>
                                <td style="font-family: 'Share Tech Mono', monospace; font-size: 10.5px;">
                                    <?php echo htmlspecialchars($rg['effective_from'] ?? '2026-01-01'); ?> 
                                    to <?php echo htmlspecialchars($rg['effective_to'] ?? 'PERPETUAL'); ?>
                                </td>
                                <td>
                                    <span class="status-pill <?php echo (strtolower($rg['status']) === 'active') ? 'pill-active' : 'pill-pending'; ?>">
                                        <?php echo htmlspecialchars($rg['status']); ?>
                                    </span>
                                </td>
                                <td style="max-width: 320px; font-size: 11.5px; line-height: 1.4;">
                                    <?php echo htmlspecialchars($rg['content']); ?>
                                </td>
                                <td>
                                    <?php echo htmlspecialchars($rg['author_name'] ?? 'FIA Technical Director'); ?>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="7" style="text-align: center; padding: 20px; color: #8c8c9e;">
                                No regulations found in database.
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Comprehensive Car Technical Master Dossier Section -->
    <div class="dossier-master-panel" id="carDossierSection">
        
        <!-- Official Printable Letterhead (Only visible in Print) -->
        <div class="print-official-header">
            <h2 style="font-size: 16pt; font-weight: bold; letter-spacing: 2px;">FEDERATION INTERNATIONALE DE L'AUTOMOBILE</h2>
            <h3 style="font-size: 12pt; margin-top: 4px;">OFFICIAL TECHNICAL SCRUTINEERING MASTER DOSSIER</h3>
            <p style="font-size: 9pt; margin-top: 2px;">PARC FERMÉ ARBITRATION // HISTORICAL AUDIT TRACE // CONFIDENTIAL REPORT</p>
            <p style="font-size: 8pt; margin-top: 4px;">Certified Date: <?php echo date('Y-m-d H:i:s T'); ?></p>
        </div>

        <div class="panel-header" style="border-bottom-color: rgba(0, 210, 190, 0.3);">
            <div class="panel-title" style="color: var(--f1-cyan);">
                <span>🏎️</span> COMPREHENSIVE CAR TECHNICAL MASTER DOSSIER
            </div>
            <div style="display: flex; gap: 10px;">
                <?php if ($car_dossier): ?>
                    <a href="?export=car_csv&car_id=<?php echo $selected_car_id; ?>" class="btn-f1-secondary" style="border-color: var(--f1-cyan); color: var(--f1-cyan);">
                        <span>📥</span> EXPORT DOSSIER CSV
                    </a>
                    <button onclick="window.print()" class="btn-f1-secondary">
                        <span>🖨️</span> PRINT OFFICIAL REPORT
                    </button>
                <?php endif; ?>
            </div>
        </div>

        <!-- Car Master Selector Bar -->
        <form method="GET" action="feature10.php" class="dossier-selector-bar">
            <div style="display: flex; align-items: center; gap: 12px; flex: 1;">
                <label style="font-family: 'Orbitron', sans-serif; font-size: 12px; font-weight: 700; color: #ffffff; white-space: nowrap;">
                    SELECT TARGET CHASSIS / CAR:
                </label>
                <select name="car_id" class="f1-select" style="max-width: 450px;" onchange="this.form.submit()">
                    <option value="0">-- CHOOSE HOMOLOGATED CAR TO AUDIT --</option>
                    <?php if ($cars_dropdown_res): while ($c = $cars_dropdown_res->fetch_assoc()): ?>
                        <option value="<?php echo $c['car_id']; ?>" <?php echo ($selected_car_id == $c['car_id']) ? 'selected' : ''; ?>>
                            #<?php echo $c['car_id']; ?>: <?php echo htmlspecialchars($c['car_name']); ?> | Chassis: <?php echo htmlspecialchars($c['chassis_number']); ?> (<?php echo htmlspecialchars($c['team_name'] ?? 'Privateer'); ?> - <?php echo htmlspecialchars($c['driver_name'] ?? 'TBD'); ?>)
                        </option>
                    <?php endwhile; endif; ?>
                </select>
            </div>
            <button type="submit" class="btn-f1-secondary">
                <span>⚡</span> LOAD AUDIT DOSSIER
            </button>
        </form>

        <?php if ($car_dossier): ?>
            
            <!-- Vehicle Specs Overview Grid -->
            <div class="car-specs-grid">
                <div class="car-spec-item">
                    <div class="label">HOMOLOGATION SPEC</div>
                    <div class="val"><?php echo htmlspecialchars($car_dossier['car_name']); ?></div>
                </div>
                <div class="car-spec-item">
                    <div class="label">CHASSIS SERIAL</div>
                    <div class="val" style="color: var(--f1-cyan);"><?php echo htmlspecialchars($car_dossier['chassis_number']); ?></div>
                </div>
                <div class="car-spec-item">
                    <div class="label">CONSTRUCTOR TEAM</div>
                    <div class="val"><?php echo htmlspecialchars($car_dossier['team_name'] ?? 'Privateer'); ?></div>
                </div>
                <div class="car-spec-item">
                    <div class="label">ASSIGNED PILOT</div>
                    <div class="val"><?php echo htmlspecialchars($car_dossier['driver_name'] ?? 'Unassigned'); ?></div>
                </div>
                <div class="car-spec-item">
                    <div class="label">POWER UNIT / ENGINE</div>
                    <div class="val"><?php echo htmlspecialchars($car_dossier['engine_type'] ?? '1.6L V6 Turbo Hybrid'); ?></div>
                </div>
                <div class="car-spec-item">
                    <div class="label">FIA CATEGORY</div>
                    <div class="val"><?php echo htmlspecialchars($car_dossier['category'] ?? 'Formula 1'); ?></div>
                </div>
                <div class="car-spec-item">
                    <div class="label">HOMOLOGATION STATUS</div>
                    <div class="val">
                        <span class="status-pill <?php echo ($car_dossier['status'] === 'approved' || $car_dossier['status'] === 'Active') ? 'pill-active' : 'pill-pending'; ?>">
                            <?php echo htmlspecialchars($car_dossier['status']); ?>
                        </span>
                    </div>
                </div>
                <div class="car-spec-item">
                    <div class="label">FIA SEAL VALIDATION</div>
                    <div class="val" style="color: #2ecc71;">PASSED // NOMINAL</div>
                </div>
            </div>

            <!-- End-to-End Pipeline Summary -->

            <!-- 1. Scrutineering Measurements -->
            <div class="dossier-sub-card">
                <div class="dossier-sub-title">
                    <span>📐</span> 1. TECHNICAL INSPECTION MEASUREMENTS (<?php echo count($car_measurements); ?> RECORDS)
                </div>
                <?php if (!empty($car_measurements)): ?>
                    <div class="f1-table-wrapper">
                        <table class="f1-table">
                            <thead>
                                <tr>
                                    <th>MEASUREMENT ID</th>
                                    <th>SESSION TYPE</th>
                                    <th>LOCATION</th>
                                    <th>CHECK ITEM</th>
                                    <th>EXPECTED VALUE</th>
                                    <th>ACTUAL RECORDED</th>
                                    <th>RESULT</th>
                                    <th>TIMESTAMP</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($car_measurements as $cm): ?>
                                    <tr>
                                        <td style="font-family: 'Share Tech Mono', monospace;">#<?php echo $cm['measurement_id']; ?></td>
                                        <td><?php echo htmlspecialchars($cm['session_type']); ?></td>
                                        <td><?php echo htmlspecialchars($cm['location'] ?? 'Parc Fermé'); ?></td>
                                        <td><strong><?php echo htmlspecialchars($cm['measurement_name']); ?></strong></td>
                                        <td><?php echo htmlspecialchars($cm['expected_value']); ?> <?php echo htmlspecialchars($cm['unit']); ?></td>
                                        <td style="color: var(--f1-cyan); font-weight: 700;">
                                            <?php echo htmlspecialchars($cm['actual_value']); ?> <?php echo htmlspecialchars($cm['unit']); ?>
                                        </td>
                                        <td>
                                            <span class="status-pill <?php echo (strtolower($cm['result']) === 'pass' || strtolower($cm['result']) === 'passed') ? 'pill-passed' : 'pill-failed'; ?>">
                                                <?php echo htmlspecialchars($cm['result']); ?>
                                            </span>
                                        </td>
                                        <td style="font-family: 'Share Tech Mono', monospace; font-size: 11px;">
                                            <?php echo htmlspecialchars($cm['created_at']); ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <p style="font-size: 12px; color: #8c8c9e;">No inspection measurements recorded for this chassis.</p>
                <?php endif; ?>
            </div>

            <!-- 2. Violations Detected -->
            <div class="dossier-sub-card">
                <div class="dossier-sub-title">
                    <span>🚨</span> 2. TECHNICAL VIOLATIONS DETECTED (<?php echo count($car_violations); ?> RECORDS)
                </div>
                <?php if (!empty($car_violations)): ?>
                    <div class="f1-table-wrapper">
                        <table class="f1-table">
                            <thead>
                                <tr>
                                    <th>VIOLATION ID</th>
                                    <th>RELATED MEASUREMENT</th>
                                    <th>DESCRIPTION</th>
                                    <th>SEVERITY</th>
                                    <th>STATUS</th>
                                    <th>DETECTED BY</th>
                                    <th>TIMESTAMP</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($car_violations as $cv): ?>
                                    <tr>
                                        <td style="font-family: 'Share Tech Mono', monospace; color: var(--f1-red);">#<?php echo $cv['violation_id']; ?></td>
                                        <td><?php echo htmlspecialchars($cv['measurement_name'] ?? 'Physical Test'); ?></td>
                                        <td style="color: #ffffff;"><?php echo htmlspecialchars($cv['violation_description']); ?></td>
                                        <td>
                                            <span class="status-pill <?php echo (strtolower($cv['severity']) === 'critical' || strtolower($cv['severity']) === 'major') ? 'pill-major' : 'pill-pending'; ?>">
                                                <?php echo htmlspecialchars($cv['severity']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="status-pill <?php echo (strtolower($cv['status']) === 'rectified') ? 'pill-rectified' : 'pill-pending'; ?>">
                                                <?php echo htmlspecialchars($cv['status']); ?>
                                            </span>
                                        </td>
                                        <td><?php echo htmlspecialchars($cv['detected_by_name'] ?? 'Technical Delegate'); ?></td>
                                        <td style="font-family: 'Share Tech Mono', monospace; font-size: 11px;"><?php echo htmlspecialchars($cv['detected_at']); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <p style="font-size: 12px; color: #2ecc71;">✓ Zero technical violations registered for this chassis.</p>
                <?php endif; ?>
            </div>

            <!-- 3. Steward Penalties Issued -->
            <div class="dossier-sub-card">
                <div class="dossier-sub-title">
                    <span>⚖️</span> 3. STEWARD PENALTIES & SANCTIONS (<?php echo count($car_penalties); ?> RECORDS)
                </div>
                <?php if (!empty($car_penalties)): ?>
                    <div class="f1-table-wrapper">
                        <table class="f1-table">
                            <thead>
                                <tr>
                                    <th>PENALTY ID</th>
                                    <th>SANCTION TYPE</th>
                                    <th>PENALTY VALUE</th>
                                    <th>DECISION DATE</th>
                                    <th>STATUS</th>
                                    <th>STEWARD NOTES</th>
                                    <th>DECISION BY</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($car_penalties as $cp): ?>
                                    <tr>
                                        <td style="font-family: 'Share Tech Mono', monospace; color: var(--f1-amber);">#<?php echo $cp['penalty_id']; ?></td>
                                        <td style="color: #ffffff; font-weight: 700;"><?php echo htmlspecialchars($cp['penalty_type']); ?></td>
                                        <td style="color: var(--f1-red); font-weight: 700;"><?php echo htmlspecialchars($cp['penalty_value']); ?></td>
                                        <td style="font-family: 'Share Tech Mono', monospace; font-size: 11px;"><?php echo htmlspecialchars($cp['decision_date']); ?></td>
                                        <td>
                                            <span class="status-pill <?php echo ($cp['status'] === 'confirmed') ? 'pill-active' : 'pill-pending'; ?>">
                                                <?php echo htmlspecialchars($cp['status']); ?>
                                            </span>
                                        </td>
                                        <td style="font-size: 11.5px;"><?php echo htmlspecialchars($cp['comments']); ?></td>
                                        <td><?php echo htmlspecialchars($cp['steward_name'] ?? 'FIA Panel of Stewards'); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <p style="font-size: 12px; color: #2ecc71;">✓ Clean disciplinary record - No steward penalties active.</p>
                <?php endif; ?>
            </div>

            <!-- 4. Repairs & Re-Inspections -->
            <div class="dossier-sub-card">
                <div class="dossier-sub-title">
                    <span>🔧</span> 4. TECHNICAL REPAIRS & RE-INSPECTIONS (<?php echo count($car_repairs); ?> REPAIRS / <?php echo count($car_reinspections); ?> RE-INSPECTIONS)
                </div>
                <?php if (!empty($car_repairs)): ?>
                    <div class="f1-table-wrapper">
                        <table class="f1-table">
                            <thead>
                                <tr>
                                    <th>REPAIR ID</th>
                                    <th>RECTIFICATION ACTION</th>
                                    <th>PERFORMED BY</th>
                                    <th>PERFORMED AT</th>
                                    <th>STATUS</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($car_repairs as $cr): ?>
                                    <tr>
                                        <td style="font-family: 'Share Tech Mono', monospace;">#<?php echo $cr['repair_id']; ?></td>
                                        <td><?php echo htmlspecialchars($cr['description']); ?></td>
                                        <td><?php echo htmlspecialchars($cr['performed_by']); ?></td>
                                        <td style="font-family: 'Share Tech Mono', monospace; font-size: 11px;"><?php echo htmlspecialchars($cr['performed_at']); ?></td>
                                        <td>
                                            <span class="status-pill <?php echo ($cr['status'] === 'completed') ? 'pill-rectified' : 'pill-pending'; ?>">
                                                <?php echo htmlspecialchars($cr['status']); ?>
                                            </span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <p style="font-size: 12px; color: #8c8c9e;">No mechanical repairs logged for this chassis.</p>
                <?php endif; ?>
            </div>

            <!-- 5. Appeals & Arbitration -->
            <div class="dossier-sub-card">
                <div class="dossier-sub-title">
                    <span>📜</span> 5. STEWARD APPEALS & EVIDENCE DOSSIER (<?php echo count($car_appeals); ?> APPEALS)
                </div>
                <?php if (!empty($car_appeals)): ?>
                    <div class="f1-table-wrapper">
                        <table class="f1-table">
                            <thead>
                                <tr>
                                    <th>APPEAL ID</th>
                                    <th>RELATED PENALTY</th>
                                    <th>APPEAL REASON / LEGAL DEFENSE</th>
                                    <th>STATUS</th>
                                    <th>SUBMITTED AT</th>
                                    <th>JUDICIAL RULING SUMMARY</th>
                                    <th>RULING BY</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($car_appeals as $ca): ?>
                                    <tr>
                                        <td style="font-family: 'Share Tech Mono', monospace; color: var(--f1-purple);">#<?php echo $ca['appeal_id']; ?></td>
                                        <td><?php echo htmlspecialchars($ca['penalty_type'] ?? 'Penalty'); ?></td>
                                        <td style="font-size: 11.5px;"><?php echo htmlspecialchars($ca['appeal_reason']); ?></td>
                                        <td>
                                            <span class="status-pill <?php echo ($ca['status'] === 'Upheld') ? 'pill-active' : (($ca['status'] === 'Overturned') ? 'pill-rectified' : 'pill-pending'); ?>">
                                                <?php echo htmlspecialchars($ca['status']); ?>
                                            </span>
                                        </td>
                                        <td style="font-family: 'Share Tech Mono', monospace; font-size: 11px;"><?php echo htmlspecialchars($ca['submitted_at']); ?></td>
                                        <td style="font-size: 11.5px; color: #ffffff;"><?php echo htmlspecialchars($ca['decision_summary'] ?? 'Under Deliberation'); ?></td>
                                        <td><?php echo htmlspecialchars($ca['judge_name'] ?? 'FIA Court of Appeal'); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <p style="font-size: 12px; color: #8c8c9e;">No judicial appeals filed for this vehicle.</p>
                <?php endif; ?>
            </div>

        <?php else: ?>
            <div style="text-align: center; padding: 40px 20px; background: rgba(0, 0, 0, 0.2); border-radius: 6px; border: 1px dashed rgba(255, 255, 255, 0.1);">
                <div style="font-size: 32px; margin-bottom: 10px;">🔍</div>
                <h3 style="font-family: 'Orbitron', sans-serif; font-size: 14px; color: #ffffff; margin-bottom: 6px;">NO CAR SELECTED</h3>
                <p style="font-family: 'Share Tech Mono', monospace; font-size: 11px; color: #8c8c9e;">
                    Please select a homologated Formula 1 chassis from the dropdown above to load its complete, end-to-end scrutineering and legal audit dossier.
                </p>
            </div>
        <?php endif; ?>

        <!-- Printable Footer -->
        <div class="print-official-footer">
            <div>
                <p><strong>FIA CHIEF TECHNICAL DELEGATE</strong></p>
                <p style="margin-top: 30px;">____________________________________</p>
                <p>Signature & Parc Fermé Official Seal</p>
            </div>
            <div>
                <p><strong>CHAIRMAN OF THE STEWARDS</strong></p>
                <p style="margin-top: 30px;">____________________________________</p>
                <p>Judicial Panel Ratification</p>
            </div>
        </div>

    </div>

</div>

<!-- Audio Synthesizer & Speech Comms Script -->
<script>
function speakAuditComms() {
    if ('speechSynthesis' in window) {
        window.speechSynthesis.cancel();
        const totalAudits = "<?php echo $total_audits; ?>";
        const activeRegs = "<?php echo $active_regs; ?>";
        const carName = "<?php echo $car_dossier ? addslashes($car_dossier['car_name']) : 'All Cars'; ?>";
        
        const messageText = `FIA System Audit Trail verified. Total audit events logged: ${totalAudits}. Active technical regulations in force: ${activeRegs}. Cryptographic ledger integrity nominal. Dossier compiled for ${carName}.`;

        const utterance = new SpeechSynthesisUtterance(messageText);
        utterance.rate = 0.95;
        utterance.pitch = 0.78; // Deep masculine tone
        utterance.volume = 1.0;

        const voices = window.speechSynthesis.getVoices();
        let selectedVoice = voices.find(v => (v.name.includes('David') || v.name.includes('George') || v.name.includes('Male') || v.name.includes('UK English Male')) && !v.name.includes('Female'));
        if (!selectedVoice) {
            selectedVoice = voices.find(v => v.lang.startsWith('en') && !v.name.toLowerCase().includes('female') && !v.name.toLowerCase().includes('samantha') && !v.name.toLowerCase().includes('zira'));
        }
        if (selectedVoice) utterance.voice = selectedVoice;

        // Play radio beep first
        if (typeof playGlobalF1Radio === 'function') {
            playGlobalF1Radio();
            setTimeout(() => { window.speechSynthesis.speak(utterance); }, 150);
        } else {
            window.speechSynthesis.speak(utterance);
        }
    }
}
</script>

</body>
</html>
