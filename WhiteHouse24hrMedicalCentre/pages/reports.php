<?php
// pages/reports.php
require_once '../includes/functions.php';
requireLogin();

$pageTitle = 'Reports';

$database = new Database();
$db = $database->getConnection();

$start_date = $_GET['start_date'] ?? date('Y-m-01');
$end_date = $_GET['end_date'] ?? date('Y-m-t');

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

include '../includes/header.php';
?>

<div class="container">
    <h1 class="dashboard-title"><i class="fas fa-chart-bar"></i> Reports</h1>

    <div class="report-header">
        <h3><i class="fas fa-filter"></i> Filter by Date Range</h3>
        <form method="GET" class="report-filters">
            <div class="filter-group"><label>Start Date:</label><input type="date" name="start_date" value="<?php echo $start_date; ?>" class="date-input"></div>
            <div class="filter-group"><label>End Date:</label><input type="date" name="end_date" value="<?php echo $end_date; ?>" class="date-input"></div>
            <button type="submit" class="btn btn-primary"><i class="fas fa-chart-line"></i> Generate Report</button>
        </form>
    </div>

    <div class="stats-cards">
        <div class="report-card"><i class="fas fa-users"></i><h4>Total Patients</h4><div class="number"><?php echo number_format($summary['total_patients'] ?? 0); ?></div><small>Unique individuals</small></div>
        <div class="report-card"><i class="fas fa-calendar-check"></i><h4>Total Visits</h4><div class="number"><?php echo number_format($summary['total_visits'] ?? 0); ?></div><small>Including repeat visits</small></div>
        <div class="report-card"><i class="fas fa-sign-in-alt"></i><h4>Check-Ins</h4><div class="number"><?php echo number_format($summary['checked_in_count'] ?? 0); ?></div><small>Currently checked in</small></div>
        <div class="report-card"><i class="fas fa-sign-out-alt"></i><h4>Check-Outs</h4><div class="number"><?php echo number_format($summary['checked_out_count'] ?? 0); ?></div><small>Completed visits</small></div>
        <div class="report-card"><i class="fas fa-hospital"></i><h4>Inpatient Admissions</h4><div class="number"><?php echo number_format($summary['admitted_count'] ?? 0); ?></div><small>New admissions</small></div>
        <div class="report-card"><i class="fas fa-home"></i><h4>Inpatient Discharges</h4><div class="number"><?php echo number_format($summary['inpatient_discharged_count'] ?? 0); ?></div><small>Completed stays</small></div>
    </div>

    <div class="table-container">
        <div class="table-header-reports">
            <h3><i class="fas fa-chart-bar"></i> Daily Statistics</h3>
            <span class="date-range-badge"><i class="fas fa-calendar-day"></i> <?php echo date('d M Y', strtotime($start_date)); ?> - <?php echo date('d M Y', strtotime($end_date)); ?></span>
        </div>
        <?php if(empty($daily_stats)): ?>
            <div class="empty-message"><i class="fas fa-chart-line"></i><p>No data available for the selected date range.</p></div>
        <?php else: ?>
            <table class="data-table">
                <thead><tr><th>Date</th><th>Check-Ins</th><th>Check-Outs</th><th>Total</th><th>Net</th></tr></thead>
                <tbody>
                    <?php foreach($daily_stats as $stat): ?>
                    <tr>
                        <td><strong><?php echo date('l, d M Y', strtotime($stat['check_in_date'])); ?></strong></td>
                        <td><span class="stat-admission">+<?php echo $stat['checked_in']; ?></span></td>
                        <td><span class="stat-discharge">-<?php echo $stat['checked_out']; ?></span></td>
                        <td><strong><?php echo $stat['total']; ?></strong></td>
                        <td><?php $net = $stat['checked_in'] - $stat['checked_out'];
                            if($net > 0) echo '<span class="net-positive">+' . $net . '</span>';
                            elseif($net < 0) echo '<span class="net-negative">' . $net . '</span>';
                            else echo '<span class="net-zero">0</span>'; ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>

    <div class="table-container">
        <div class="table-header-reports">
            <h3><i class="fas fa-list-alt"></i> Detailed Patient Visits</h3>
            <span class="records-badge"><i class="fas fa-users"></i> <?php echo count($details); ?> Records Found</span>
        </div>
        <?php if(empty($details)): ?>
            <div class="empty-message"><i class="fas fa-users"></i><p>No patient visits found for the selected date range.</p></div>
        <?php else: ?>
            <table class="data-table">
                <thead><tr><th>Visit #</th><th>Patient #</th><th>Patient Name</th><th>Check-In Date</th><th>Type</th><th>Status</th><th>Check-Out Date</th></tr></thead>
                <tbody>
                    <?php foreach($details as $detail): ?>
                    <tr>
                        <td><strong><?php echo $detail['visit_number']; ?></strong></td>
                        <td><?php echo $detail['patient_number']; ?></td>
                        <td><?php echo htmlspecialchars($detail['patient_name']); ?></td>
                        <td><?php echo date('d M Y', strtotime($detail['check_in_date'])); ?></td>
                        <td><?php echo ($detail['is_inpatient'] > 0) ? '<span class="status-badge status-admitted">Inpatient</span>' : '<span class="status-badge status-outpatient">Outpatient</span>'; ?></td>
                        <td><span class="status-badge <?php echo $detail['status'] == 'checked_in' ? 'status-checked-in' : 'status-checked-out'; ?>"><?php echo $detail['status'] == 'checked_in' ? 'Checked In' : 'Checked Out'; ?></span></td>
                        <td><?php echo $detail['check_out_date'] ? date('d M Y', strtotime($detail['check_out_date'])) : '<span class="currently-admitted">Still Checked In</span>'; ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>

    <div class="export-buttons">
        <button onclick="window.location.href='print-report.php?start_date=<?php echo $start_date; ?>&end_date=<?php echo $end_date; ?>'" class="btn btn-primary"><i class="fas fa-print"></i> Print Report</button>
        <a href="export-report.php?start_date=<?php echo $start_date; ?>&end_date=<?php echo $end_date; ?>" class="btn btn-success"><i class="fas fa-download"></i> Export CSV</a>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
