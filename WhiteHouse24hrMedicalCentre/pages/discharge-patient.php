<?php
// pages/discharge-patient.php - Check-Out Patient
require_once '../includes/functions.php';
requireLogin();

$pageTitle = 'Check-Out Patient';

$database = new Database();
$db = $database->getConnection();

$patient_id = $_GET['id'] ?? 0;
$visit_id = $_GET['visit_id'] ?? 0;
$error = '';

$patient = null;
$active_visit = null;

if ($patient_id) {
    $patient = getPatientById($db, $patient_id);
    $active_visit = getActiveVisit($db, $patient_id);
} elseif ($visit_id) {
    $query = "SELECT * FROM visits WHERE visit_id = :visit_id AND status = 'checked_in'";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':visit_id', $visit_id);
    $stmt->execute();
    $active_visit = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($active_visit) {
        $patient = getPatientById($db, $active_visit['patient_id']);
    }
}

if (!$patient || !$active_visit) {
    $_SESSION['error_message'] = "No active check-in found for this patient.";
    header("Location: patients-list.php");
    exit();
}

$is_inpatient = isInpatient($db, $active_visit['visit_id']);

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    try {
        $db->beginTransaction();

        $check_out_notes = sanitize($_POST['check_out_notes']);

        if ($is_inpatient && isset($_POST['discharge_summary'])) {
            $discharge_summary = sanitize($_POST['discharge_summary']);
            $update_inpatient = "UPDATE inpatient_admissions
                                SET actual_discharge_date = CURDATE(), discharge_summary = :discharge_summary
                                WHERE visit_id = :visit_id AND actual_discharge_date IS NULL";
            $stmt = $db->prepare($update_inpatient);
            $stmt->execute([
                ':discharge_summary' => $discharge_summary,
                ':visit_id' => $active_visit['visit_id']
            ]);
        }

        $query = "UPDATE visits
                  SET status = 'checked_out',
                      check_out_date = CURDATE(),
                      check_out_time = CURTIME(),
                      check_out_notes = :check_out_notes,
                      checked_out_by = :checked_out_by
                  WHERE visit_id = :visit_id";

        $stmt = $db->prepare($query);
        $stmt->execute([
            ':check_out_notes' => $check_out_notes,
            ':checked_out_by' => $_SESSION['user_id'],
            ':visit_id' => $active_visit['visit_id']
        ]);

        $action_label = $is_inpatient ? 'INPATIENT_CHECKED_OUT' : 'PATIENT_CHECKED_OUT';
        $desc_label = $is_inpatient ? 'Inpatient checked out' : 'Patient checked out';
        logActivity($_SESSION['user_id'], $action_label,
                   "$desc_label: {$patient['patient_number']} - {$patient['first_name']} {$patient['last_name']}");

        $db->commit();

        $_SESSION['success_message'] = "Patient checked out successfully!";
        header("Location: patient-history.php?id=" . $patient['patient_id']);
        exit();

    } catch (Exception $e) {
        $db->rollBack();
        $error = "Check-out failed: " . $e->getMessage();
    }
}

include '../includes/header.php';
?>

<div class="container">
    <div class="form-container">
        <h1 class="form-title"><i class="fas fa-sign-out-alt"></i> Check-Out Patient</h1>

        <?php if($error): ?><div class="alert alert-error"><?php echo $error; ?></div><?php endif; ?>

        <?php if($is_inpatient): ?>
            <div class="info-box">
                <i class="fas fa-hospital"></i>
                <div><strong>Inpatient Check-Out:</strong> This patient is currently admitted as an inpatient. A discharge summary will be required.</div>
            </div>
        <?php endif; ?>

        <div class="patient-summary">
            <h3><i class="fas fa-user"></i> Patient Information</h3>
            <div class="summary-grid">
                <div class="summary-item"><strong>Patient Number:</strong><span><?php echo $patient['patient_number']; ?></span></div>
                <div class="summary-item"><strong>Full Name:</strong><span><?php echo htmlspecialchars($patient['first_name'] . ' ' . $patient['last_name']); ?></span></div>
                <div class="summary-item"><strong>Visit Number:</strong><span><?php echo $active_visit['visit_number']; ?></span></div>
                <div class="summary-item"><strong>Check-In Date:</strong><span><?php echo date('d M Y', strtotime($active_visit['check_in_date'])); ?> at <?php echo date('h:i A', strtotime($active_visit['check_in_time'])); ?></span></div>
                <div class="summary-item"><strong>Reason for Visit:</strong><span><?php echo htmlspecialchars($active_visit['visit_reason']); ?></span></div>
                <div class="summary-item"><strong>Patient Type:</strong><span><?php echo $is_inpatient ? 'Inpatient' : 'Outpatient'; ?></span></div>
                <div class="summary-item"><strong>Phone:</strong><span><?php echo $patient['phone'] ?? 'Not provided'; ?></span></div>
            </div>
        </div>

        <div class="warning-box">
            <i class="fas fa-exclamation-triangle"></i>
            <div><strong>Important:</strong> This action will mark the patient as checked out. <?php if($is_inpatient): ?>This will also complete the inpatient admission record.<?php endif; ?></div>
        </div>

        <form method="POST" action="">
            <div class="form-group">
                <label>Check-Out Notes *</label>
                <textarea name="check_out_notes" class="discharge-notes" rows="4" required placeholder="Enter check-out notes, follow-up instructions, or recommendations..."></textarea>
                <small class="field-hint">Include any important information or follow-up instructions</small>
            </div>

            <?php if($is_inpatient): ?>
            <div class="form-group">
                <label>Discharge Summary *</label>
                <textarea name="discharge_summary" class="discharge-notes" rows="4" required placeholder="Enter inpatient discharge summary, treatment received, condition on departure..."></textarea>
                <small class="field-hint">Required for inpatient records</small>
            </div>
            <?php endif; ?>

            <div class="confirmation-checkbox">
                <input type="checkbox" id="confirm" required>
                <label for="confirm">I confirm that this patient is ready to be checked out</label>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn btn-danger btn-block"><i class="fas fa-check-circle"></i> Confirm Check-Out</button>
                <a href="patient-details.php?id=<?php echo $patient['patient_id']; ?>" class="btn btn-secondary btn-block"><i class="fas fa-times"></i> Cancel</a>
            </div>
        </form>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
