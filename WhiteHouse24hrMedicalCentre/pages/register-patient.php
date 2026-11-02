<?php
// pages/register-patient.php
require_once '../includes/functions.php';
requireLogin();

$pageTitle = 'Register Patient (Check-In)';

$database = new Database();
$db = $database->getConnection();

$message = '';
$error = '';
$returning_patient = null;
$previous_visits = [];

$quick_patient_id = isset($_GET['quick']) ? (int)$_GET['quick'] : 0;

if ($quick_patient_id) {
    $returning_patient = getPatientById($db, $quick_patient_id);
    if ($returning_patient) {
        $previous_visits = getPatientVisits($db, $quick_patient_id);
    }
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    try {
        $db->beginTransaction();

        $user_id = $_SESSION['user_id'] ?? null;
        if (!$user_id) {
            $any_user = $db->query("SELECT user_id FROM users ORDER BY user_id ASC LIMIT 1")->fetch(PDO::FETCH_ASSOC);
            if ($any_user) { $user_id = $any_user['user_id']; $_SESSION['user_id'] = $user_id; }
            else { throw new Exception("No users found."); }
        }

        if (isset($_POST['existing_patient_id']) && !empty($_POST['existing_patient_id'])) {
            $patient_id = (int)$_POST['existing_patient_id'];
            $visit_reason = sanitize($_POST['visit_reason']);
            $patient = getPatientById($db, $patient_id);
            $visit_number = generateVisitNumber($db);

            $query = "INSERT INTO visits (patient_id, visit_number, check_in_date, check_in_time, visit_reason, status, registered_by)
                      VALUES (:patient_id, :visit_number, CURDATE(), CURTIME(), :visit_reason, 'checked_in', :registered_by)";
            $stmt = $db->prepare($query);
            $stmt->execute([':patient_id' => $patient_id, ':visit_number' => $visit_number, ':visit_reason' => $visit_reason, ':registered_by' => $user_id]);

            logActivity($user_id, 'RETURNING_PATIENT_CHECKED_IN', "Returning patient checked in: {$patient['patient_number']} - {$patient['first_name']} {$patient['last_name']} (Visit #{$visit_number})");
            $db->commit();
            $_SESSION['success_message'] = "Returning patient checked in successfully! Visit Number: $visit_number";
            header("Location: patients-list.php");
            exit();
        } else {
            $first_name = sanitize($_POST['first_name']);
            $last_name = sanitize($_POST['last_name']);
            $date_of_birth = $_POST['date_of_birth'];
            $gender = $_POST['gender'];
            $phone = sanitize($_POST['phone']);
            $email = sanitize($_POST['email']);
            $address = sanitize($_POST['address']);
            $emergency_contact_name = sanitize($_POST['emergency_contact_name']);
            $emergency_contact_phone = sanitize($_POST['emergency_contact_phone']);
            $visit_reason = sanitize($_POST['visit_reason']);

            $check_query = "SELECT * FROM patients WHERE (first_name = :first_name AND last_name = :last_name AND date_of_birth = :dob) OR phone = :phone";
            $check_stmt = $db->prepare($check_query);
            $check_stmt->execute([':first_name' => $first_name, ':last_name' => $last_name, ':dob' => $date_of_birth, ':phone' => $phone]);
            $existing = $check_stmt->fetch(PDO::FETCH_ASSOC);

            if ($existing) {
                $returning_patient = $existing;
                $previous_visits = getPatientVisits($db, $existing['patient_id']);
                $db->rollBack();
            } else {
                $patient_number = generatePatientNumber($db);
                $query = "INSERT INTO patients (patient_number, first_name, last_name, date_of_birth, gender, phone, email, address, emergency_contact_name, emergency_contact_phone)
                          VALUES (:patient_number, :first_name, :last_name, :date_of_birth, :gender, :phone, :email, :address, :emergency_contact_name, :emergency_contact_phone)";
                $stmt = $db->prepare($query);
                $stmt->execute([':patient_number' => $patient_number, ':first_name' => $first_name, ':last_name' => $last_name, ':date_of_birth' => $date_of_birth, ':gender' => $gender, ':phone' => $phone, ':email' => $email, ':address' => $address, ':emergency_contact_name' => $emergency_contact_name, ':emergency_contact_phone' => $emergency_contact_phone]);
                $patient_id = $db->lastInsertId();

                $visit_number = generateVisitNumber($db);
                $query = "INSERT INTO visits (patient_id, visit_number, check_in_date, check_in_time, visit_reason, status, registered_by)
                          VALUES (:patient_id, :visit_number, CURDATE(), CURTIME(), :visit_reason, 'checked_in', :registered_by)";
                $stmt = $db->prepare($query);
                $stmt->execute([':patient_id' => $patient_id, ':visit_number' => $visit_number, ':visit_reason' => $visit_reason, ':registered_by' => $user_id]);

                logActivity($user_id, 'NEW_PATIENT_CHECKED_IN', "New patient registered and checked in: $patient_number - $first_name $last_name");
                $db->commit();
                $_SESSION['success_message'] = "New patient registered and checked in successfully! Patient Number: $patient_number";
                header("Location: patients-list.php");
                exit();
            }
        }
    } catch (Exception $e) {
        $db->rollBack();
        $error = "Registration failed: " . $e->getMessage();
    }
}

include '../includes/header.php';
?>

<div class="container">
    <div class="form-container">
        <h1 class="form-title"><i class="fas fa-user-plus"></i> <?php echo $returning_patient ? 'Returning Patient Check-In' : 'Register New Patient (Check-In)'; ?></h1>

        <?php if($error): ?><div class="alert alert-error"><?php echo $error; ?></div><?php endif; ?>

        <?php if($returning_patient): ?>
            <div class="returning-patient-alert">
                <div class="alert alert-warning returning-warning">
                    <h4><i class="fas fa-user-check"></i> Returning Patient Detected!</h4>
                    <p><strong>Patient:</strong> <?php echo htmlspecialchars($returning_patient['first_name'] . ' ' . $returning_patient['last_name']); ?></p>
                    <p><strong>Patient Number:</strong> <?php echo $returning_patient['patient_number']; ?></p>
                    <p><strong>Phone:</strong> <?php echo $returning_patient['phone'] ?? 'Not provided'; ?></p>
                    <p><strong>Total Visits:</strong> <?php echo count($previous_visits); ?> time(s)</p>
                </div>
                <?php if(!empty($previous_visits)): ?>
                <div class="history-section">
                    <h4><i class="fas fa-history"></i> Previous Visit History</h4>
                    <div class="table-container history-table">
                        <table class="data-table">
                            <thead><tr><th>Visit #</th><th>Date</th><th>Reason</th><th>Status</th><th>Check-Out Date</th></tr></thead>
                            <tbody>
                                <?php foreach($previous_visits as $visit): ?>
                                <tr>
                                    <td><?php echo $visit['visit_number']; ?></td>
                                    <td><?php echo date('d M Y', strtotime($visit['check_in_date'])); ?></td>
                                    <td><?php echo htmlspecialchars($visit['visit_reason']); ?></td>
                                    <td><span class="status-badge <?php echo $visit['status'] == 'checked_in' ? 'status-checked-in' : 'status-checked-out'; ?>"><?php echo $visit['status'] == 'checked_in' ? 'Checked In' : 'Checked Out'; ?></span></td>
                                    <td><?php echo $visit['check_out_date'] ? date('d M Y', strtotime($visit['check_out_date'])) : '-'; ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                <?php endif; ?>
                <div class="quick-registration-box">
                    <h4><i class="fas fa-bolt"></i> Quick Check-In - Only need reason for visit</h4>
                    <form method="POST">
                        <input type="hidden" name="existing_patient_id" value="<?php echo $returning_patient['patient_id']; ?>">
                        <div class="form-group"><label>Reason for Visit *</label><textarea name="visit_reason" rows="3" required placeholder="Describe the reason for this visit"></textarea></div>
                        <div class="form-actions">
                            <button type="submit" class="btn btn-success"><i class="fas fa-check"></i> Quick Check-In</button>
                            <a href="register-patient.php" class="btn btn-secondary"><i class="fas fa-times"></i> Cancel</a>
                        </div>
                    </form>
                </div>
            </div>
        <?php else: ?>
            <form method="POST" action="" id="registrationForm">
                <div id="duplicateAlert" class="duplicate-alert" style="display: none;"><i class="fas fa-exclamation-triangle"></i><span id="duplicateMessage"></span></div>
                <h3 class="section-subtitle">Patient Information</h3>
                <div class="form-grid">
                    <div class="form-group"><label>First Name *</label><input type="text" name="first_name" id="first_name" required></div>
                    <div class="form-group"><label>Last Name *</label><input type="text" name="last_name" id="last_name" required></div>
                    <div class="form-group"><label>Date of Birth *</label><input type="date" name="date_of_birth" id="dob" required></div>
                    <div class="form-group"><label>Gender *</label><select name="gender" required><option value="">Select Gender</option><option value="Male">Male</option><option value="Female">Female</option><option value="Other">Other</option></select></div>
                    <div class="form-group"><label>Phone Number</label><input type="text" name="phone" id="phone" placeholder="e.g., 07XXXXXXXX"></div>
                    <div class="form-group"><label>Email</label><input type="email" name="email" placeholder="patient@example.com"></div>
                    <div class="form-group full-width"><label>Address</label><textarea name="address" rows="2" placeholder="Patient's address"></textarea></div>
                </div>
                <h3 class="section-subtitle">Emergency Contact</h3>
                <div class="form-grid">
                    <div class="form-group"><label>Emergency Contact Name</label><input type="text" name="emergency_contact_name"></div>
                    <div class="form-group"><label>Emergency Contact Phone</label><input type="text" name="emergency_contact_phone"></div>
                </div>
                <h3 class="section-subtitle">Visit Information</h3>
                <div class="form-group"><label>Reason for Visit *</label><textarea name="visit_reason" rows="3" required placeholder="Describe the reason for this visit"></textarea></div>
                <div class="form-actions">
                    <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Register Patient & Check-In</button>
                    <a href="patients-list.php" class="btn btn-secondary"><i class="fas fa-times"></i> Cancel</a>
                </div>
            </form>
        <?php endif; ?>
    </div>
</div>

<script>
let duplicateCheckTimer;
function checkForDuplicate() {
    clearTimeout(duplicateCheckTimer);
    duplicateCheckTimer = setTimeout(() => {
        const firstName = document.getElementById('first_name')?.value;
        const lastName = document.getElementById('last_name')?.value;
        const phone = document.getElementById('phone')?.value;
        if ((firstName && firstName.length > 2) || (lastName && lastName.length > 2) || (phone && phone.length > 5)) {
            fetch(`check-duplicate-patient.php?first_name=${encodeURIComponent(firstName)}&last_name=${encodeURIComponent(lastName)}&phone=${encodeURIComponent(phone)}`)
                .then(response => response.json())
                .then(data => {
                    const alertDiv = document.getElementById('duplicateAlert');
                    const messageSpan = document.getElementById('duplicateMessage');
                    if (data.found && data.patients && data.patients.length > 0) {
                        let html = `<strong>Potential duplicate found!</strong> This patient appears to have visited before.<br><br>`;
                        html += `<strong>Existing records:</strong><ul class="existing-records-list">`;
                        data.patients.forEach(p => { html += `<li>${p.patient_number} - ${p.first_name} ${p.last_name} (${p.phone || 'No phone'}) - ${p.visit_count} visit(s)</li>`; });
                        html += `</ul><div class="quick-register-link"><a href="register-patient.php?quick=${data.patients[0].patient_id}" class="btn btn-small btn-success">Click here to quick check-in this returning patient</a></div>`;
                        messageSpan.innerHTML = html;
                        alertDiv.style.display = 'block';
                    } else { alertDiv.style.display = 'none'; }
                }).catch(err => console.log('Duplicate check error:', err));
        }
    }, 800);
}
document.addEventListener('DOMContentLoaded', function() {
    const firstName = document.getElementById('first_name');
    const lastName = document.getElementById('last_name');
    const phone = document.getElementById('phone');
    if (firstName) firstName.addEventListener('blur', checkForDuplicate);
    if (lastName) lastName.addEventListener('blur', checkForDuplicate);
    if (phone) phone.addEventListener('blur', checkForDuplicate);
});
</script>

<?php include '../includes/footer.php'; ?>
