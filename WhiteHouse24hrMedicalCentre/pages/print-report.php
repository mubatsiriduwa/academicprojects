<?php
// pages/print-report.php
require_once '../includes/functions.php';
requireLogin();

$start_date = $_GET['start_date'] ?? date('Y-m-01');
$end_date = $_GET['end_date'] ?? date('Y-m-t');

$database = new Database();
$db = $database->getConnection();

$query = "SELECT COUNT(DISTINCT patient_id) as total_patients, COUNT(*) as total_visits,
            SUM(CASE WHEN status = 'checked_in' THEN 1 ELSE 0 END) as checked_in_count,
            SUM(CASE WHEN status = 'checked_out' THEN 1 ELSE 0 END) as checked_out_count,
            (SELECT COUNT(*) FROM visits v2 JOIN inpatient_admissions ia ON v2.visit_id = ia.visit_id WHERE v2.check_in_date BETWEEN :start_date AND :end_date) as admitted_count,
            (SELECT COUNT(*) FROM visits v2 JOIN inpatient_admissions ia ON v2.visit_id = ia.visit_id WHERE ia.actual_discharge_date BETWEEN :start_date AND :end_date) as inpatient_discharged_count
          FROM visits WHERE check_in_date BETWEEN :start_date AND :end_date";
$stmt = $db->prepare($query);
$stmt->bindParam(':start_date', $start_date);
$stmt->bindParam(':end_date', $end_date);
$stmt->execute();
$summary = $stmt->fetch(PDO::FETCH_ASSOC);

$query = "SELECT check_in_date, COUNT(*) as total,
            SUM(CASE WHEN status = 'checked_in' THEN 1 ELSE 0 END) as checked_in,
            SUM(CASE WHEN status = 'checked_out' THEN 1 ELSE 0 END) as checked_out
          FROM visits WHERE check_in_date BETWEEN :start_date AND :end_date
          GROUP BY check_in_date ORDER BY check_in_date DESC";
$stmt = $db->prepare($query);
$stmt->bindParam(':start_date', $start_date);
$stmt->bindParam(':end_date', $end_date);
$stmt->execute();
$daily_stats = $stmt->fetchAll(PDO::FETCH_ASSOC);

$query = "SELECT v.visit_number, p.patient_number, CONCAT(p.first_name, ' ', p.last_name) as patient_name,
            v.check_in_date, v.status, v.check_out_date,
            (SELECT COUNT(*) FROM inpatient_admissions WHERE visit_id = v.visit_id) as is_inpatient
          FROM visits v JOIN patients p ON v.patient_id = p.patient_id
          WHERE v.check_in_date BETWEEN :start_date AND :end_date
          ORDER BY v.check_in_date DESC";
$stmt = $db->prepare($query);
$stmt->bindParam(':start_date', $start_date);
$stmt->bindParam(':end_date', $end_date);
$stmt->execute();
$details = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Print Report - Whitehouse Medical</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; line-height: 1.6; color: #2c3e50; padding: 40px; max-width: 1200px; margin: 0 auto; }
        .header { text-align: center; margin-bottom: 40px; padding-bottom: 20px; border-bottom: 2px solid #1a5d3c; }
        .header h1 { color: #1a5d3c; margin-bottom: 10px; }
        .header h2 { color: #2d7a4f; font-weight: 400; }
        .summary { display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px; margin-bottom: 40px; }
        .summary-card { background: #f8f9fa; padding: 20px; border-radius: 10px; text-align: center; border: 1px solid #ddd; }
        .summary-card h3 { color: #7f8c8d; font-size: 14px; margin-bottom: 10px; }
        .summary-card .number { font-size: 32px; font-weight: 700; color: #1a5d3c; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 40px; }
        th { background: #ecf0f1; padding: 12px; text-align: left; font-weight: 600; color: #1a5d3c; border-bottom: 2px solid #ddd; }
        td { padding: 10px; border-bottom: 1px solid #ddd; }
        .footer { text-align: center; margin-top: 40px; padding-top: 20px; border-top: 1px solid #ddd; color: #7f8c8d; font-size: 12px; }
        @media print { body { padding: 20px; } .no-print { display: none; } }
    </style>
</head>
<body>
    <div class="header">
        <h1>Whitehouse 24hr Medical Centre</h1>
        <h2>Patient Visit Report</h2>
        <p>Period: <?php echo date('d M Y', strtotime($start_date)); ?> - <?php echo date('d M Y', strtotime($end_date)); ?></p>
        <p>Generated on: <?php echo date('d M Y H:i:s'); ?></p>
    </div>

    <div class="summary">
        <div class="summary-card"><h3>Total Patients</h3><div class="number"><?php echo $summary['total_patients'] ?? 0; ?></div><small>Unique individuals</small></div>
        <div class="summary-card"><h3>Total Visits</h3><div class="number"><?php echo $summary['total_visits'] ?? 0; ?></div><small>Including repeat visits</small></div>
        <div class="summary-card"><h3>Check-Ins</h3><div class="number"><?php echo $summary['checked_in_count'] ?? 0; ?></div><small>New check-ins</small></div>
        <div class="summary-card"><h3>Check-Outs</h3><div class="number"><?php echo $summary['checked_out_count'] ?? 0; ?></div><small>Completed visits</small></div>
        <div class="summary-card"><h3>Inpatient Admissions</h3><div class="number"><?php echo $summary['admitted_count'] ?? 0; ?></div><small>New admissions</small></div>
        <div class="summary-card"><h3>Inpatient Discharges</h3><div class="number"><?php echo $summary['inpatient_discharged_count'] ?? 0; ?></div><small>Completed stays</small></div>
    </div>

    <h3>Daily Summary</h3>
    <table>
        <thead><tr><th>Date</th><th>Check-Ins</th><th>Check-Outs</th><th>Total</th></tr></thead>
        <tbody>
            <?php foreach ($daily_stats as $stat): ?>
            <tr><td><?php echo date('d M Y', strtotime($stat['check_in_date'])); ?></td><td><?php echo $stat['checked_in']; ?></td><td><?php echo $stat['checked_out']; ?></td><td><strong><?php echo $stat['total']; ?></strong></td></tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <h3>Detailed Patient Visits</h3>
    <table>
        <thead><tr><th>Visit #</th><th>Patient #</th><th>Patient Name</th><th>Check-In Date</th><th>Type</th><th>Status</th><th>Check-Out Date</th></tr></thead>
        <tbody>
            <?php foreach ($details as $detail): ?>
            <tr>
                <td><?php echo $detail['visit_number']; ?></td>
                <td><?php echo $detail['patient_number']; ?></td>
                <td><?php echo htmlspecialchars($detail['patient_name']); ?></td>
                <td><?php echo date('d M Y', strtotime($detail['check_in_date'])); ?></td>
                <td><?php echo ($detail['is_inpatient'] > 0) ? 'Inpatient' : 'Outpatient'; ?></td>
                <td><?php echo $detail['status'] == 'checked_in' ? 'Checked In' : 'Checked Out'; ?></td>
                <td><?php echo $detail['check_out_date'] ? date('d M Y', strtotime($detail['check_out_date'])) : '-'; ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <div class="footer"><p>Generated by Whitehouse 24hr Medical Centre Reception Management System</p><p>Confidential - For internal use only</p></div>

    <div class="no-print" style="text-align: center; margin-top: 20px;">
        <button onclick="window.print()" style="padding: 10px 25px; font-size: 16px; cursor: pointer;">Print</button>
        <button onclick="window.close()" style="padding: 10px 25px; font-size: 16px; cursor: pointer;">Close</button>
    </div>
</body>
</html>
