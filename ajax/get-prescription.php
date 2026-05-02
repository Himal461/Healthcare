<?php
require_once '../includes/config.php';
session_start();

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit();
}

$prescriptionId = $_GET['id'] ?? 0;

if (!$prescriptionId) {
    echo json_encode(['success' => false, 'error' => 'Prescription ID required']);
    exit();
}

$stmt = $pdo->prepare("
    SELECT p.*, 
           CONCAT(u.firstName, ' ', u.lastName) as patientName,
           CONCAT(du.firstName, ' ', du.lastName) as doctorName,
           u.email as patientEmail,
           u.phoneNumber as patientPhone,
           mr.diagnosis
    FROM prescriptions p
    JOIN medical_records mr ON p.recordId = mr.recordId
    JOIN patients pt ON mr.patientId = pt.patientId
    JOIN users u ON pt.userId = u.userId
    JOIN doctors d ON p.prescribedBy = d.doctorId
    JOIN staff s ON d.staffId = s.staffId
    JOIN users du ON s.userId = du.userId
    WHERE p.prescriptionId = ?
");
$stmt->execute([$prescriptionId]);
$prescription = $stmt->fetch();

if (!$prescription) {
    echo json_encode(['success' => false, 'error' => 'Prescription not found']);
    exit();
}

echo json_encode(['success' => true, 'prescription' => $prescription]);