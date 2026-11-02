<?php
// pages/dashboard.php
require_once '../includes/functions.php';
requireLogin();

$pageTitle = 'Dashboard';

$database = new Database();
$db = $database->getConnection();

$stats = getDashboardStats($db);
$recent_checkins = getRecentCheckins($db, 5);
$recent_checkouts = getRecentCheckouts($db, 5);

include '../includes/header.php';
?>

<div class="container">
    <h1 class="dashboard-title"><i class="fas fa-home"></i> Dashboard</h1>

    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-icon"><i class="fas fa-sign-in-alt"></i></div>
            <div class="stat-info">
                <h3>Today's Check-Ins</h3>
                <div class="stat-number"><?php echo $stats['today_checkins']; ?></div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon"><i class="fas fa-sign-out-alt"></i></div>
            <div class="stat-info">
                <h3>Today's Check-Outs</h3>
                <div class="stat-number"><?php echo $stats['today_checkouts']; ?></div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon"><i class="fas fa-clock"></i></div>
            <div class="stat-info">
                <h3>Currently Checked In</h3>
                <div class="stat-number"><?php echo $stats['currently_checked_in']; ?></div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon"><i class="fas fa-hospital"></i></div>
            <div class="stat-info">
                <h3>Currently Admitted</h3>
                <div class="stat-number"><?php echo $stats['currently_admitted']; ?></div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon"><i class="fas fa-users"></i></div>
            <div class="stat-info">
                <h3>Total Patients</h3>
                <div class="stat-number"><?php echo $stats['total_patients']; ?></div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon"><i class="fas fa-calendar-check"></i></div>
            <div class="stat-info">
                <h3>Today's Visits</h3>
                <div class="stat-number"><?php echo $stats['total_visits_today']; ?></div>
            </div>
        </div>
    </div>

    <div class="quick-actions">
        <a href="register-patient.php" class="btn btn-primary"><i class="fas fa-user-plus"></i> Register New Patient (Check-In)</a>
        <a href="admit-patient.php" class="btn btn-warning"><i class="fas fa-hospital"></i> Admit as Inpatient</a>
        <a href="patients-list.php" class="btn btn-success"><i class="fas fa-list"></i> View All Patients</a>
        <a href="reports.php" class="btn btn-secondary"><i class="fas fa-chart-bar"></i> Generate Reports</a>
    </div>

    <div style="margin-bottom: 30px;">
        <h2 class="section-title"><i class="fas fa-sign-in-alt"></i> Recent Check-Ins</h2>
        <div class="table-container">
            <?php if(empty($recent_checkins)): ?>
                <div class="empty-message"><i class="fas fa-calendar-day"></i><p>No check-ins today</p></div>
            <?php else: ?>
                <table>
                    <thead><tr><th>Time</th><th>Patient #</th><th>Patient Name</th><th>Reason</th><th>Type</th><th>Registered By</th><th>Action</th></tr></thead>
                    <tbody>
                        <?php foreach($recent_checkins as $checkin): ?>
                        <tr>
                            <td><?php echo date('h:i A', strtotime($checkin['check_in_time'])); ?></td>
                            <td><?php echo htmlspecialchars($checkin['patient_number']); ?></td>
                            <td><?php echo htmlspecialchars($checkin['first_name'] . ' ' . $checkin['last_name']); ?></td>
                            <td><?php echo htmlspecialchars(substr($checkin['visit_reason'], 0, 50)) . '...'; ?></td>
                            <td><?php echo ($checkin['is_inpatient'] > 0) ? '<span class="status-badge status-admitted">Inpatient</span>' : '<span class="status-badge status-checked-in">Outpatient</span>'; ?></td>
                            <td><?php echo htmlspecialchars($checkin['registered_by_name']); ?></td>
                            <td><a href="patient-details.php?id=<?php echo $checkin['patient_id']; ?>" class="btn btn-small btn-primary">View</a></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>

    <div>
        <h2 class="section-title"><i class="fas fa-sign-out-alt"></i> Recent Check-Outs</h2>
        <div class="table-container">
            <?php if(empty($recent_checkouts)): ?>
                <div class="empty-message"><i class="fas fa-calendar-day"></i><p>No check-outs today</p></div>
            <?php else: ?>
                <table>
                    <thead><tr><th>Time</th><th>Patient #</th><th>Patient Name</th><th>Check-Out Notes</th><th>Checked Out By</th><th>Action</th></tr></thead>
                    <tbody>
                        <?php foreach($recent_checkouts as $checkout): ?>
                        <tr>
                            <td><?php echo date('h:i A', strtotime($checkout['check_out_time'])); ?></td>
                            <td><?php echo htmlspecialchars($checkout['patient_number']); ?></td>
                            <td><?php echo htmlspecialchars($checkout['first_name'] . ' ' . $checkout['last_name']); ?></td>
                            <td><?php echo htmlspecialchars(substr($checkout['check_out_notes'], 0, 50)) . '...'; ?></td>
                            <td><?php echo htmlspecialchars($checkout['checked_out_by_name']); ?></td>
                            <td><a href="patient-details.php?id=<?php echo $checkout['patient_id']; ?>" class="btn btn-small btn-primary">View</a></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
