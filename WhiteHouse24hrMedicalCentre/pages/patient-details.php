<?php
// pages/patient-details.php
require_once '../includes/functions.php';
requireLogin();

$pageTitle = 'Patient Details';

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

$stats_query = "SELECT COUNT(*) as total_visits,
                SUM(CASE WHEN status = 'checked_in' THEN 1 ELSE 0 END) as active_visits,
                MAX(check_in_date) as last_visit_date
                FROM visits WHERE patient_id = :patient_id";
$stmt = $db->prepare($stats_query);
$stmt->bindParam(':patient_id', $patient_id);
$stmt->execute();
$stats = $stmt->fetch(PDO::FETCH_ASSOC);

$visits_query = "SELECT v.*, u.full_name as registered_by_name,
                (SELECT COUNT(*) FROM inpatient_admissions WHERE visit_id = v.visit_id) as is_inpatient
                FROM visits v
                LEFT JOIN users u ON v.registered_by = u.user_id
                WHERE v.patient_id = :patient_id
                ORDER BY v.check_in_date DESC, v.check_in_time DESC
                LIMIT 5";
$stmt = $db->prepare($visits_query);
$stmt->bindParam(':patient_id', $patient_id);
$stmt->execute();
$recent_visits = $stmt->fetchAll(PDO::FETCH_ASSOC);

include '../includes/header.php';
?>

<div class="container">
    <div class="profile-container">
        <div class="page-header">
            <h1 class="dashboard-title"><i class="fas fa-user-circle"></i> Patient Details</h1>
            <a href="patients-list.php" class="btn btn-primary"><i class="fas fa-arrow-left"></i> Back to List</a>
        </div>

        <div class="profile-card">
            <div class="profile-header patient-header">
                <div class="profile-header-info">
                    <h2><?php echo htmlspecialchars($patient['first_name'] . ' ' . $patient['last_name']); ?></h2>
                    <p><i class="fas fa-id-card"></i> Patient #: <?php echo $patient['patient_number']; ?></p>
                </div>
                <div class="action-buttons">
                    <a href="register-patient.php?quick=<?php echo $patient_id; ?>" class="btn btn-success"><i class="fas fa-bolt"></i> Quick Check-In</a>
                    <a href="patient-history.php?id=<?php echo $patient_id; ?>" class="btn btn-info"><i class="fas fa-history"></i> Full History</a>
                </div>
            </div>

            <div class="stats-cards">
                <div class="report-card"><i class="fas fa-calendar-check"></i><h4>Total Visits</h4><div class="number"><?php echo $stats['total_visits'] ?? 0; ?></div></div>
                <div class="report-card"><i class="fas fa-clock"></i><h4>Currently Checked In</h4><div class="number"><?php echo $stats['active_visits'] ?? 0; ?></div></div>
                <div class="report-card"><i class="fas fa-history"></i><h4>Last Visit</h4><div class="number"><?php echo $stats['last_visit_date'] ? date('d M Y', strtotime($stats['last_visit_date'])) : 'Never'; ?></div></div>
            </div>

            <div class="profile-section">
                <h3><i class="fas fa-info-circle"></i> Personal Information</h3>
                <div class="info-grid">
                    <div class="info-item"><label>Date of Birth</label><div class="value"><?php echo date('d F Y', strtotime($patient['date_of_birth'])); ?></div></div>
                    <div class="info-item"><label>Gender</label><div class="value"><?php echo $patient['gender']; ?></div></div>
                    <div class="info-item"><label>Phone Number</label><div class="value"><?php echo $patient['phone'] ?? 'Not provided'; ?></div></div>
                    <div class="info-item"><label>Email</label><div class="value"><?php echo $patient['email'] ?? 'Not provided'; ?></div></div>
                    <div class="info-item full-width"><label>Address</label><div class="value"><?php echo $patient['address'] ?? 'Not provided'; ?></div></div>
                </div>
            </div>

            <div class="profile-section">
                <h3><i class="fas fa-phone-alt"></i> Emergency Contact</h3>
                <div class="info-grid">
                    <div class="info-item"><label>Contact Name</label><div class="value"><?php echo $patient['emergency_contact_name'] ?? 'Not provided'; ?></div></div>
                    <div class="info-item"><label>Contact Phone</label><div class="value"><?php echo $patient['emergency_contact_phone'] ?? 'Not provided'; ?></div></div>
                </div>
            </div>

            <div class="profile-section">
                <h3><i class="fas fa-clock"></i> Recent Visits</h3>
                <?php if(empty($recent_visits)): ?>
                    <div class="empty-message"><i class="fas fa-calendar-times"></i><p>No previous visits found</p></div>
                <?php else: ?>
                    <div class="table-container">
                        <table>
                            <thead><tr><th>Visit #</th><th>Date</th><th>Reason</th><th>Type</th><th>Status</th><th>Registered By</th><th>Actions</th></tr></thead>
                            <tbody>
                                <?php foreach($recent_visits as $visit): ?>
                                <tr>
                                    <td><?php echo $visit['visit_number']; ?></td>
                                    <td><?php echo date('d M Y', strtotime($visit['check_in_date'])); ?></td>
                                    <td><?php echo htmlspecialchars(substr($visit['visit_reason'], 0, 50)); ?></td>
                                    <td><?php echo ($visit['is_inpatient'] > 0) ? '<span class="status-badge status-admitted">Inpatient</span>' : '<span class="status-badge status-outpatient">Outpatient</span>'; ?></td>
                                    <td><span class="status-badge <?php echo $visit['status'] == 'checked_in' ? 'status-checked-in' : 'status-checked-out'; ?>"><?php echo $visit['status'] == 'checked_in' ? 'Checked In' : 'Checked Out'; ?></span></td>
                                    <td><?php echo $visit['registered_by_name'] ?? 'System'; ?></td>
                                    <td>
                                        <?php if($visit['status'] == 'checked_in'): ?>
                                            <?php if($visit['is_inpatient'] == 0): ?>
                                                <a href="admit-patient.php" class="btn btn-small btn-warning"><i class="fas fa-hospital"></i> Admit</a>
                                            <?php endif; ?>
                                            <a href="discharge-patient.php?visit_id=<?php echo $visit['visit_id']; ?>" class="btn btn-small btn-danger"><i class="fas fa-sign-out-alt"></i> Check-Out</a>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>

            <div class="info-item system-info"><i class="fas fa-database"></i> Patient record created: <?php echo date('d M Y H:i', strtotime($patient['created_at'])); ?></div>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
