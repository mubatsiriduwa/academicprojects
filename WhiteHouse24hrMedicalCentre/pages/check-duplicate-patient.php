<?php
// pages/check-duplicate-patient.php
require_once '../includes/functions.php';
requireLogin();

$database = new Database();
$db = $database->getConnection();

$first_name = $_GET['first_name'] ?? '';
$last_name = $_GET['last_name'] ?? '';
$phone = $_GET['phone'] ?? '';

$query = "SELECT p.*,
          (SELECT COUNT(*) FROM visits WHERE patient_id = p.patient_id) as visit_count
          FROM patients p
          WHERE (p.first_name LIKE :first_name AND p.last_name LIKE :last_name)
          OR p.phone = :phone
          ORDER BY p.created_at DESC
          LIMIT 5";

$stmt = $db->prepare($query);
$stmt->execute([
    ':first_name' => "%$first_name%",
    ':last_name' => "%$last_name%",
    ':phone' => $phone
]);

$patients = $stmt->fetchAll(PDO::FETCH_ASSOC);

header('Content-Type: application/json');
echo json_encode([
    'found' => count($patients) > 0,
    'patients' => $patients
]);
?>
