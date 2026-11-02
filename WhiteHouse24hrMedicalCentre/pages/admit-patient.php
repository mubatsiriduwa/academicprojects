<?php
// pages/admit-patient.php
require_once '../includes/functions.php';
requireLogin();

$pageTitle = 'Admit as Inpatient';

$database = new Database();
$db = $database->getConnection();

$error = '';
$message = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    try {
        $db->beginTransaction();

        $user_id = $_SESSION['user_id'] ?? null;
        if ($user_id) {
            $user_check = $db->prepare("SELECT user_id FROM users WHERE user_id = :user_id");
            $user_check->execute([':user_id' => $user_id]);
            if (!$user_check->fetch()) { $user_id = null; }
        }
        if (!$user_id) {
            $any_user = $db->query("SELECT user_id FROM users ORDER BY user_id ASC LIMIT 1")->fetch(PDO::FETCH_ASSOC);
            if ($any_user) { $user_id = $any_user['user_id']; $_SESSION['user_id'] = $user_id; }
            else { throw new Exception("No users found."); }
        }

        $patient_id = (int)$_POST['patient_id'];
        $ward_name = sanitize($_POST['ward_name']);
        $bed_number = sanitize($_POST['bed_number']);
        $admitting_doctor = sanitize($_POST['admitting_doctor']);
        $admission_type = $_POST['admission_type'];
        $expected_stay_days = (int)$_POST['expected_stay_days'];
        $admission_notes = sanitize($_POST['admission_notes']);
        $visit_reason = sanitize($_POST['visit_reason']);

        $patient = getPatientById($db, $patient_id);
        if (!$patient) { throw new Exception("Patient not found."); }

        $visit_number = generateVisitNumber($db);

        $query = "INSERT INTO visits (patient_id, visit_number, check_in_date, check_in_time, visit_reason, status, registered_by)
                  VALUES (:patient_id, :visit_number, CURDATE(), CURTIME(), :visit_reason, 'checked_in', :registered_by)";
        $stmt = $db->prepare($query);
        $stmt->execute([':patient_id' => $patient_id, ':visit_number' => $visit_number, ':visit_reason' => $visit_reason, ':registered_by' => $user_id]);
        $visit_id = $db->lastInsertId();

        $query = "INSERT INTO inpatient_admissions (visit_id, ward_name, bed_number, admitting_doctor, admission_type, expected_stay_days, admission_notes)
                  VALUES (:visit_id, :ward_name, :bed_number, :admitting_doctor, :admission_type, :expected_stay_days, :admission_notes)";
        $stmt = $db->prepare($query);
        $stmt->execute([':visit_id' => $visit_id, ':ward_name' => $ward_name, ':bed_number' => $bed_number, ':admitting_doctor' => $admitting_doctor, ':admission_type' => $admission_type, ':expected_stay_days' => $expected_stay_days, ':admission_notes' => $admission_notes]);

        logActivity($user_id, 'INPATIENT_ADMITTED', "Patient admitted as inpatient: {$patient['patient_number']} - {$patient['first_name']} {$patient['last_name']}, Ward: $ward_name, Bed: $bed_number, Doctor: $admitting_doctor");
        $db->commit();
        $_SESSION['success_message'] = "Patient admitted successfully! Ward: $ward_name, Bed: $bed_number, Doctor: $admitting_doctor";
        header("Location: dashboard.php");
        exit();
    } catch (Exception $e) {
        $db->rollBack();
        $error = "Admission failed: " . $e->getMessage();
    }
}

$patients_query = "SELECT p.*, (SELECT COUNT(*) FROM visits WHERE patient_id = p.patient_id) as visit_count FROM patients p ORDER BY p.last_name ASC, p.first_name ASC";
$patients_stmt = $db->query($patients_query);
$all_patients = $patients_stmt->fetchAll(PDO::FETCH_ASSOC);

$doctors = ['Dr. Moyo', 'Dr. Ndlovu', 'Dr. Sibanda', 'Dr. Dube', 'Dr. Chikomo', 'Dr. Phiri', 'Dr. Nyathi', 'Dr. Zhou', 'Dr. Tshuma', 'Dr. Banda', 'Dr. Muti', 'Dr. Gumbo', 'Other / Visiting Doctor'];

include '../includes/header.php';
?>

<div class="container">
    <div class="form-container">
        <h1 class="form-title"><i class="fas fa-hospital"></i> Admit Patient as Inpatient</h1>

        <div class="info-box">
            <i class="fas fa-info-circle"></i>
            <div><strong>Important:</strong> Use this form when a doctor has determined that a patient needs to stay overnight or longer. For walk-in patients, use <a href="register-patient.php">Register Patient (Check-In)</a> instead.</div>
        </div>

        <?php if($error): ?><div class="alert alert-error"><i class="fas fa-exclamation-circle"></i> <?php echo $error; ?></div><?php endif; ?>

        <form method="POST" action="">
            <h3 class="section-subtitle">Patient Selection</h3>
            <div class="form-group">
                <label>Select Patient *</label>
                <select name="patient_id" required class="patient-select">
                    <option value="">-- Select a patient --</option>
                    <?php foreach($all_patients as $patient): ?>
                        <option value="<?php echo $patient['patient_id']; ?>"><?php echo $patient['patient_number']; ?> - <?php echo htmlspecialchars($patient['first_name'] . ' ' . $patient['last_name']); ?> (<?php echo $patient['visit_count']; ?> previous visit(s))</option>
                    <?php endforeach; ?>
                </select>
            </div>

            <h3 class="section-subtitle">Visit Information</h3>
            <div class="form-group"><label>Reason for Admission *</label><textarea name="visit_reason" rows="3" required placeholder="Describe the reason for this admission"></textarea></div>

            <h3 class="section-subtitle">Inpatient Admission Details</h3>
            <div class="form-grid">
                <div class="form-group">
                    <label>Admitting Doctor *</label>
                    <select name="admitting_doctor" required>
                        <option value="">-- Select admitting doctor --</option>
                        <?php foreach($doctors as $doctor): ?><option value="<?php echo htmlspecialchars($doctor); ?>"><?php echo htmlspecialchars($doctor); ?></option><?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Admission Type *</label>
                    <select name="admission_type" required>
                        <option value="">-- Select admission type --</option>
                        <option value="Planned">Planned</option><option value="Emergency">Emergency</option><option value="Transfer">Transfer</option><option value="Maternity">Maternity</option><option value="Other">Other</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Ward Name/Number *</label>
                    <select name="ward_name" required>
                        <option value="">-- Select ward --</option>
                        <option value="Ward A - Male">Ward A - Male</option><option value="Ward B - Female">Ward B - Female</option><option value="Ward C - Maternity">Ward C - Maternity</option><option value="Ward D - Paediatric">Ward D - Paediatric</option><option value="ICU">ICU</option><option value="High Care">High Care</option><option value="Private Ward">Private Ward</option><option value="Other">Other</option>
                    </select>
                </div>
                <div class="form-group"><label>Bed Number *</label><input type="text" name="bed_number" required placeholder="e.g., Bed 3, Cot 2"></div>
                <div class="form-group"><label>Expected Length of Stay (Days)</label><input type="number" name="expected_stay_days" min="1" value="1" placeholder="e.g., 3"></div>
            </div>
            <div class="form-group full-width"><label>Admission Notes</label><textarea name="admission_notes" rows="3" placeholder="Additional notes..."></textarea></div>

            <div class="warning-box"><i class="fas fa-exclamation-triangle"></i><div><strong>Important:</strong> This will create a new check-in record and mark the patient as an inpatient.</div></div>

            <div class="form-actions">
                <button type="submit" class="btn btn-warning"><i class="fas fa-hospital"></i> Confirm Admission</button>
                <a href="dashboard.php" class="btn btn-secondary"><i class="fas fa-times"></i> Cancel</a>
            </div>
        </form>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
