<?php
// pages/patient-history.php
require_once '../includes/functions.php';
requireLogin();

$pageTitle = 'Patient Visit History';

$database = new Database();
$db = $database->getConnection();

$patient_id = $_GET['id'] ?? 0;

if (!$patient_id) {
    $_SESSION['error_message'] = "No patient selected";
    header("Location: patients-list.php");
    exit();
}

$patient = getPatientById($db, $patient_id);

if (!$patient) {
    $_SESSION['error_message'] = "Patient not found";
    header("Location: patients-list.php");
    exit();
}

$visits = getPatientVisits($db, $patient_id);

include '../includes/header.php';
?>

<div class="container">
    <div class="history-container">
        <div class="page-header">
            <h1 class="dashboard-title"><i class="fas fa-history"></i> Patient Visit History</h1>
            <a href="patients-list.php" class="btn btn-primary"><i class="fas fa-arrow-left"></i> Back to List</a>
        </div>

        <div class="patient-info-card">
            <div class="patient-info-content">
                <div>
                    <h2><?php echo htmlspecialchars($patient['first_name'] . ' ' . $patient['last_name']); ?></h2>
                    <p><strong>Patient #:</strong> <?php echo $patient['patient_number']; ?></p>
                    <p><strong>Phone:</strong> <?php echo $patient['phone'] ?? 'Not provided'; ?></p>
                </div>
                <div class="total-visits"><div class="total-visits-number"><?php echo count($visits); ?></div><div>Total Visits</div></div>
            </div>
        </div>

        <div class="action-buttons history-actions">
            <a href="register-patient.php?quick=<?php echo $patient_id; ?>" class="btn btn-success"><i class="fas fa-bolt"></i> Quick Check-In</a>
            <a href="patient-details.php?id=<?php echo $patient_id; ?>" class="btn btn-primary"><i class="fas fa-eye"></i> View Patient Details</a>
        </div>

        <?php if(empty($visits)): ?>
            <div class="empty-message">
                <i class="fas fa-calendar-times"></i><h3>No Visit History</h3>
                <p>This patient has no previous visits.</p>
                <a href="register-patient.php?quick=<?php echo $patient_id; ?>" class="btn btn-success"><i class="fas fa-plus"></i> Check-In Now</a>
            </div>
        <?php else: ?>
            <div class="table-container">
                <table>
                    <thead>
                        <tr><th>Visit #</th><th>Check-In Date</th><th>Check-In Time</th><th>Reason</th><th>Type</th><th>Status</th><th>Check-Out Date</th><th>Registered By</th><th>Checked Out By</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach($visits as $visit): ?>
                        <tr>
                            <td><strong><?php echo $visit['visit_number']; ?></strong></td>
                            <td><?php echo date('d M Y', strtotime($visit['check_in_date'])); ?></td>
                            <td><?php echo date('h:i A', strtotime($visit['check_in_time'])); ?></td>
                            <td><?php echo htmlspecialchars($visit['visit_reason']); ?></td>
                            <td><?php echo ($visit['is_inpatient'] > 0) ? '<span class="status-badge status-admitted">Inpatient</span>' : '<span class="status-badge status-outpatient">Outpatient</span>'; ?></td>
                            <td><span class="status-badge <?php echo $visit['status'] == 'checked_in' ? 'status-checked-in' : 'status-checked-out'; ?>"><?php echo $visit['status'] == 'checked_in' ? 'Checked In' : 'Checked Out'; ?></span></td>
                            <td><?php echo $visit['check_out_date'] ? date('d M Y', strtotime($visit['check_out_date'])) . '<br><small>' . date('h:i A', strtotime($visit['check_out_time'])) . '</small>' : '-'; ?></td>
                            <td><?php echo $visit['registered_by_name'] ?? 'System'; ?></td>
                            <td><?php echo $visit['checked_out_by_name'] ?? '-'; ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <div class="history-stats">
                <div class="history-stat-card"><div class="history-stat-number"><?php echo count($visits); ?></div><div>Total Visits</div></div>
                <div class="history-stat-card"><div class="history-stat-number"><?php echo count(array_filter($visits, function($v) { return $v['status'] == 'checked_in'; })); ?></div><div>Currently Checked In</div></div>
                <div class="history-stat-card"><div class="history-stat-number"><?php echo count(array_filter($visits, function($v) { return $v['status'] == 'checked_out'; })); ?></div><div>Checked Out</div></div>
                <div class="history-stat-card"><div class="history-stat-number"><?php $first = end($visits); echo $first ? date('d M Y', strtotime($first['check_in_date'])) : '-'; ?></div><div>First Visit</div></div>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
