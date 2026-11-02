<?php
// includes/functions.php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config/database.php';

// Check if user is logged in
function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

// Redirect if not logged in
function requireLogin() {
    if (!isLoggedIn()) {
        $_SESSION['redirect_url'] = $_SERVER['REQUEST_URI'];
        header("Location: index.php");
        exit();
    }
}

// Check if user is admin
function isAdmin() {
    return (isset($_SESSION['role']) && $_SESSION['role'] === 'admin');
}

// Require admin role
function requireAdmin() {
    if (!isAdmin()) {
        header("Location: dashboard.php?error=unauthorized");
        exit();
    }
}

// Sanitize input
function sanitize($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $data;
}

// Generate patient number
function generatePatientNumber($db) {
    $year = date('Y');
    $month = date('m');

    $query = "SELECT COUNT(*) as count FROM patients WHERE patient_number LIKE 'WH-$year$month%'";
    $stmt = $db->query($query);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    $count = $row['count'] + 1;

    return "WH-$year$month-" . str_pad($count, 4, '0', STR_PAD_LEFT);
}

// Generate visit number
function generateVisitNumber($db) {
    $date = date('Ymd');

    $query = "SELECT COUNT(*) as count FROM visits WHERE visit_number LIKE 'VIS-$date%'";
    $stmt = $db->query($query);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    $count = $row['count'] + 1;

    return "VIS-$date-" . str_pad($count, 3, '0', STR_PAD_LEFT);
}

// Log user activity
function logActivity($user_id, $action, $description) {
    $database = new Database();
    $db = $database->getConnection();

    $ip_address = $_SERVER['REMOTE_ADDR'] ?? '';

    $query = "INSERT INTO activity_log (user_id, action, description, ip_address)
              VALUES (:user_id, :action, :description, :ip_address)";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':user_id', $user_id);
    $stmt->bindParam(':action', $action);
    $stmt->bindParam(':description', $description);
    $stmt->bindParam(':ip_address', $ip_address);
    return $stmt->execute();
}

// Get dashboard statistics
function getDashboardStats($db) {
    $stats = [];

    // Today's check-ins (all new visits started today)
    $query = "SELECT COUNT(*) as count FROM visits WHERE check_in_date = CURDATE()";
    $stmt = $db->query($query);
    $stats['today_checkins'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

    // Today's check-outs (outpatient visits closed today, NOT inpatients)
    $query = "SELECT COUNT(*) as count FROM visits v
              WHERE v.check_out_date = CURDATE() AND v.status = 'checked_out'
              AND v.visit_id NOT IN (SELECT visit_id FROM inpatient_admissions)";
    $stmt = $db->query($query);
    $stats['today_checkouts'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

    // Currently checked in but NOT admitted as inpatient (outpatients still being seen)
    $query = "SELECT COUNT(*) as count FROM visits v
              WHERE v.status = 'checked_in'
              AND v.visit_id NOT IN (SELECT visit_id FROM inpatient_admissions)";
    $stmt = $db->query($query);
    $stats['currently_checked_in'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

    // Current inpatients (patients with active inpatient admission, not yet discharged)
    $query = "SELECT COUNT(*) as count FROM inpatient_admissions ia
              JOIN visits v ON ia.visit_id = v.visit_id
              WHERE v.status = 'checked_in' AND ia.actual_discharge_date IS NULL";
    $stmt = $db->query($query);
    $stats['currently_admitted'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

    // Total unique patients
    $query = "SELECT COUNT(*) as count FROM patients";
    $stmt = $db->query($query);
    $stats['total_patients'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

    // Total visits (including repeat visits) for today
    $query = "SELECT COUNT(*) as count FROM visits WHERE check_in_date = CURDATE()";
    $stmt = $db->query($query);
    $stats['total_visits_today'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

    return $stats;
}

// Get recent check-ins
function getRecentCheckins($db, $limit = 10) {
    $query = "SELECT v.*, p.first_name, p.last_name, p.patient_number,
              u.full_name as registered_by_name,
              (SELECT COUNT(*) FROM inpatient_admissions WHERE visit_id = v.visit_id) as is_inpatient
              FROM visits v
              JOIN patients p ON v.patient_id = p.patient_id
              LEFT JOIN users u ON v.registered_by = u.user_id
              WHERE v.check_in_date = CURDATE()
              ORDER BY v.check_in_time DESC
              LIMIT :limit";

    $stmt = $db->prepare($query);
    $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
    $stmt->execute();

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Get recent check-outs
function getRecentCheckouts($db, $limit = 10) {
    $query = "SELECT v.*, p.first_name, p.last_name, p.patient_number,
              u.full_name as checked_out_by_name
              FROM visits v
              JOIN patients p ON v.patient_id = p.patient_id
              LEFT JOIN users u ON v.checked_out_by = u.user_id
              WHERE v.check_out_date = CURDATE() AND v.status = 'checked_out'
              ORDER BY v.check_out_time DESC
              LIMIT :limit";

    $stmt = $db->prepare($query);
    $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
    $stmt->execute();

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Get patient by ID
function getPatientById($db, $patient_id) {
    $query = "SELECT * FROM patients WHERE patient_id = :patient_id";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':patient_id', $patient_id);
    $stmt->execute();

    return $stmt->fetch(PDO::FETCH_ASSOC);
}

// Get active visit for patient
function getActiveVisit($db, $patient_id) {
    $query = "SELECT * FROM visits
              WHERE patient_id = :patient_id AND status = 'checked_in'
              ORDER BY check_in_date DESC, check_in_time DESC
              LIMIT 1";

    $stmt = $db->prepare($query);
    $stmt->bindParam(':patient_id', $patient_id);
    $stmt->execute();

    return $stmt->fetch(PDO::FETCH_ASSOC);
}

// Get patient visit history
function getPatientVisits($db, $patient_id) {
    $query = "SELECT v.*,
              u1.full_name as registered_by_name,
              u2.full_name as checked_out_by_name,
              (SELECT COUNT(*) FROM inpatient_admissions WHERE visit_id = v.visit_id) as is_inpatient
              FROM visits v
              LEFT JOIN users u1 ON v.registered_by = u1.user_id
              LEFT JOIN users u2 ON v.checked_out_by = u2.user_id
              WHERE v.patient_id = :patient_id
              ORDER BY v.check_in_date DESC, v.check_in_time DESC";

    $stmt = $db->prepare($query);
    $stmt->bindParam(':patient_id', $patient_id);
    $stmt->execute();

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Check if patient has an active inpatient admission
function isInpatient($db, $visit_id) {
    $query = "SELECT * FROM inpatient_admissions
              WHERE visit_id = :visit_id AND actual_discharge_date IS NULL";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':visit_id', $visit_id);
    $stmt->execute();

    return $stmt->fetch(PDO::FETCH_ASSOC);
}

// Get inpatient details for a visit
function getInpatientDetails($db, $visit_id) {
    $query = "SELECT * FROM inpatient_admissions WHERE visit_id = :visit_id";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':visit_id', $visit_id);
    $stmt->execute();

    return $stmt->fetch(PDO::FETCH_ASSOC);
}
?>
