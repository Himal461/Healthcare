<?php
require_once '../includes/config.php';
session_start();

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit();
}

$recordId = $_GET['id'] ?? 0;

if (!$recordId) {
    echo json_encode(['success' => false, 'error' => 'Record ID required']);
    exit();
}

$stmt = $pdo->prepare("
    SELECT mr.*, 
           CONCAT(pu.firstName, ' ', pu.lastName) as patientName,
           CONCAT(du.firstName, ' ', du.lastName) as doctorName,
           pu.email as patientEmail,
           pu.phoneNumber as patientPhone
    FROM medical_records mr
    JOIN patients p ON mr.patientId = p.patientId
    JOIN users pu ON p.userId = pu.userId
    JOIN doctors d ON mr.doctorId = d.doctorId
    JOIN staff s ON d.staffId = s.staffId
    JOIN users du ON s.userId = du.userId
    WHERE mr.recordId = ?
");
$stmt->execute([$recordId]);
$record = $stmt->fetch();

if (!$record) {
    echo json_encode(['success' => false, 'error' => 'Record not found']);
    exit();
}

$vitalsStmt = $pdo->prepare("
    SELECT v.*, CONCAT(u.firstName, ' ', u.lastName) as recordedByName
    FROM vitals v
    LEFT JOIN staff s ON v.recordedBy = s.staffId
    LEFT JOIN users u ON s.userId = u.userId
    WHERE v.recordId = ? ORDER BY v.recordedDate DESC
");
$vitalsStmt->execute([$recordId]);
$vitals = $vitalsStmt->fetchAll();

echo json_encode(['success' => true, 'record' => $record, 'vitals' => $vitals]);