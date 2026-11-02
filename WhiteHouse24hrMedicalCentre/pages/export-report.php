<?php
// pages/export-report.php
require_once '../includes/functions.php';
requireLogin();

$start_date = $_GET['start_date'] ?? date('Y-m-01');
$end_date = $_GET['end_date'] ?? date('Y-m-t');

$database = new Database();
$db = $database->getConnection();

$query = "SELECT v.visit_number, p.patient_number, CONCAT(p.first_name, ' ', p.last_name) as patient_name,
            p.gender, p.phone, v.check_in_date, v.check_in_time, v.visit_reason, v.status,
            v.check_out_date, v.check_out_time, v.check_out_notes,
            u1.full_name as registered_by, u2.full_name as checked_out_by,
            (SELECT COUNT(*) FROM inpatient_admissions WHERE visit_id = v.visit_id) as is_inpatient
          FROM visits v
          JOIN patients p ON v.patient_id = p.patient_id
          LEFT JOIN users u1 ON v.registered_by = u1.user_id
          LEFT JOIN users u2 ON v.checked_out_by = u2.user_id
          WHERE v.check_in_date BETWEEN :start_date AND :end_date
          ORDER BY v.check_in_date DESC, v.check_in_time DESC";

$stmt = $db->prepare($query);
$stmt->bindParam(':start_date', $start_date);
$stmt->bindParam(':end_date', $end_date);
$stmt->execute();
$results = $stmt->fetchAll(PDO::FETCH_ASSOC);

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename=whitehouse_report_' . $start_date . '_to_' . $end_date . '.csv');

$output = fopen('php://output', 'w');
fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

fputcsv($output, ['Visit Number', 'Patient Number', 'Patient Name', 'Gender', 'Phone', 'Check-In Date', 'Check-In Time', 'Visit Reason', 'Patient Type', 'Status', 'Check-Out Date', 'Check-Out Time', 'Check-Out Notes', 'Registered By', 'Checked Out By']);

foreach ($results as $row) {
    $patient_type = ($row['is_inpatient'] > 0) ? 'Inpatient' : 'Outpatient';
    fputcsv($output, [
        $row['visit_number'], $row['patient_number'], $row['patient_name'], $row['gender'], $row['phone'],
        $row['check_in_date'], $row['check_in_time'], $row['visit_reason'], $patient_type,
        $row['status'] == 'checked_in' ? 'Checked In' : 'Checked Out',
        $row['check_out_date'] ?? '', $row['check_out_time'] ?? '', $row['check_out_notes'] ?? '',
        $row['registered_by'] ?? '', $row['checked_out_by'] ?? ''
    ]);
}

fclose($output);
exit();
?>
