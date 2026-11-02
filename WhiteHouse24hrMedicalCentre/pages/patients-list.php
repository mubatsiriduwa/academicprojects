<?php
// pages/patients-list.php
require_once '../includes/functions.php';
requireLogin();

$pageTitle = 'Patients List';

$database = new Database();
$db = $database->getConnection();

$search = $_GET['search'] ?? '';
$status_filter = $_GET['status'] ?? 'all';
$sort_by = $_GET['sort_by'] ?? 'created_at';
$sort_order = $_GET['sort_order'] ?? 'DESC';

$allowed_sort = ['created_at', 'last_name', 'patient_number', 'visit_count'];
$sort_by = in_array($sort_by, $allowed_sort) ? $sort_by : 'created_at';
$sort_order = ($sort_order == 'ASC') ? 'ASC' : 'DESC';

$query = "SELECT p.*,
          (SELECT COUNT(*) FROM visits WHERE patient_id = p.patient_id) as visit_count,
          (SELECT status FROM visits WHERE patient_id = p.patient_id ORDER BY check_in_date DESC LIMIT 1) as current_status,
          (SELECT check_in_date FROM visits WHERE patient_id = p.patient_id ORDER BY check_in_date DESC LIMIT 1) as last_visit_date,
          (SELECT COUNT(*) FROM visits v JOIN inpatient_admissions ia ON v.visit_id = ia.visit_id WHERE v.patient_id = p.patient_id AND v.status = 'checked_in' AND ia.actual_discharge_date IS NULL) as is_currently_inpatient
          FROM patients p";

$count_query = "SELECT COUNT(*) as total FROM patients p";
$params = [];

if (!empty($search)) {
    $where = " WHERE p.first_name LIKE :search OR p.last_name LIKE :search OR p.patient_number LIKE :search OR p.phone LIKE :search";
    $query .= $where;
    $count_query .= $where;
    $params[':search'] = "%$search%";
}

$query .= " ORDER BY p.$sort_by $sort_order";

$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 10;
$offset = ($page - 1) * $limit;

$stmt = $db->prepare($count_query);
foreach ($params as $key => $value) { $stmt->bindValue($key, $value); }
$stmt->execute();
$total_records = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
$total_pages = ceil($total_records / $limit);

$query .= " LIMIT :limit OFFSET :offset";
$stmt = $db->prepare($query);
foreach ($params as $key => $value) { $stmt->bindValue($key, $value); }
$stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$patients = $stmt->fetchAll(PDO::FETCH_ASSOC);

include '../includes/header.php';
?>

<div class="container">
    <div class="page-header">
        <h1 class="dashboard-title"><i class="fas fa-users"></i> Patients List</h1>
    </div>

    <div class="search-filter-bar">
        <form method="GET" action="" class="search-form">
            <div class="search-input-group">
                <i class="fas fa-search"></i>
                <input type="text" name="search" placeholder="Search by patient name, number, or phone..." value="<?php echo htmlspecialchars($search); ?>">
            </div>
            <button type="submit" class="btn search-btn"><i class="fas fa-search"></i> Search Patients</button>
            <?php if(!empty($search) || $status_filter != 'all'): ?>
                <a href="patients-list.php" class="btn clear-btn"><i class="fas fa-times"></i> Clear</a>
            <?php endif; ?>
        </form>
    </div>

    <div class="section-spacer"></div>

    <div class="register-action-bar">
        <a href="register-patient.php" class="btn btn-success btn-lg-action"><i class="fas fa-user-plus"></i> Register New Patient (Check-In)</a>
    </div>

    <div class="section-spacer"></div>

    <div class="records-count"><i class="fas fa-info-circle"></i> Showing <?php echo count($patients); ?> of <?php echo $total_records; ?> patients</div>

    <div class="table-container">
        <?php if(empty($patients)): ?>
            <div class="empty-message">
                <i class="fas fa-users"></i><h3>No patients found</h3>
                <?php if(!empty($search)): ?>
                    <p>No results match your search criteria. Try different keywords.</p>
                    <a href="patients-list.php" class="btn btn-primary"><i class="fas fa-times"></i> Clear Search</a>
                <?php else: ?>
                    <p>Get started by registering your first patient.</p>
                    <a href="register-patient.php" class="btn btn-success"><i class="fas fa-user-plus"></i> Register New Patient</a>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <table>
                <thead>
                    <tr>
                        <th>Patient #</th><th>Name</th><th>Date of Birth</th><th>Gender</th><th>Phone</th>
                        <th>Visits</th><th>Last Visit</th><th>Type</th><th>Status</th><th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($patients as $patient): ?>
                    <tr>
                        <td><strong><?php echo htmlspecialchars($patient['patient_number']); ?></strong></td>
                        <td><?php echo htmlspecialchars($patient['first_name'] . ' ' . $patient['last_name']); ?></td>
                        <td><?php echo date('d M Y', strtotime($patient['date_of_birth'])); ?></td>
                        <td><?php echo $patient['gender']; ?></td>
                        <td><?php echo htmlspecialchars($patient['phone'] ?? 'N/A'); ?></td>
                        <td><?php echo $patient['visit_count']; ?></td>
                        <td><?php echo $patient['last_visit_date'] ? date('d M Y', strtotime($patient['last_visit_date'])) : 'Never'; ?></td>
                        <td>
                            <?php if($patient['is_currently_inpatient'] > 0): ?>
                                <span class="status-badge status-admitted"><i class="fas fa-hospital"></i> Inpatient</span>
                            <?php elseif($patient['current_status'] == 'checked_in'): ?>
                                <span class="status-badge status-checked-in"><i class="fas fa-clock"></i> Outpatient</span>
                            <?php else: ?>
                                <span class="status-badge status-checked-out"><i class="fas fa-check-circle"></i> Outpatient</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if($patient['current_status'] == 'checked_in'): ?>
                                <span class="status-badge status-checked-in"><i class="fas fa-clock"></i> Checked In</span>
                            <?php else: ?>
                                <span class="status-badge status-checked-out"><i class="fas fa-check-circle"></i> Checked Out</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <div class="action-buttons">
                                <a href="patient-details.php?id=<?php echo $patient['patient_id']; ?>" class="btn btn-small btn-primary" title="View"><i class="fas fa-eye"></i></a>
                                <a href="patient-history.php?id=<?php echo $patient['patient_id']; ?>" class="btn btn-small btn-info" title="History"><i class="fas fa-history"></i></a>
                                <?php if($patient['current_status'] == 'checked_in'): ?>
                                    <?php if($patient['is_currently_inpatient'] == 0): ?>
                                        <a href="admit-patient.php" class="btn btn-small btn-warning" title="Admit"><i class="fas fa-hospital"></i></a>
                                    <?php endif; ?>
                                    <a href="discharge-patient.php?id=<?php echo $patient['patient_id']; ?>" class="btn btn-small btn-danger" title="Check-Out"><i class="fas fa-sign-out-alt"></i></a>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <?php if($total_pages > 1): ?>
                <div class="pagination">
                    <?php if($page > 1): ?>
                        <a href="?page=<?php echo $page-1; ?>&search=<?php echo urlencode($search); ?>" class="btn btn-small">&laquo; Previous</a>
                    <?php endif; ?>
                    <?php for($i = 1; $i <= $total_pages; $i++): ?>
                        <?php if($i == $page): ?>
                            <span class="btn btn-small active"><?php echo $i; ?></span>
                        <?php else: ?>
                            <a href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>" class="btn btn-small"><?php echo $i; ?></a>
                        <?php endif; ?>
                    <?php endfor; ?>
                    <?php if($page < $total_pages): ?>
                        <a href="?page=<?php echo $page+1; ?>&search=<?php echo urlencode($search); ?>" class="btn btn-small">Next &raquo;</a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
