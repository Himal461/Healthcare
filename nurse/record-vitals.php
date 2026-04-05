<?php
require_once '..\/includes\/config.php';
require_once '..\/includes\/auth.php';
checkRole('nurse');

$patientId = $_POST['patient_id'] ?? 0;
$height = $_POST['height'] ?: null;
$weight = $_POST['weight'] ?: null;
$bodyTemperature = $_POST['body_temperature'] ?: null;
$heartRate = $_POST['heart_rate'] ?: null;
$bpSystolic = $_POST['bp_systolic'] ?: null;
$bpDiastolic = $_POST['bp_diastolic'] ?: null;
$respiratoryRate = $_POST['respiratory_rate'] ?: null;
$oxygenSaturation = $_POST['oxygen_saturation'] ?: null;
$notes = sanitizeInput($_POST['notes'] ?? '');

// Get the latest medical record for this patient
$stmt = $pdo->prepare("
    SELECT recordId FROM medical_records 
    WHERE patientId = ? 
    ORDER BY creationDate DESC 
    LIMIT 1
");
$stmt->execute([$patientId]);
$record = $stmt->fetch();

if (!$record) {
    $_SESSION['error'] = "No medical record found for this patient.";
    header("Location: dashboard.php");
    exit();
}

// Get nurse staff ID
$stmt = $pdo->prepare("
    SELECT staffId FROM staff WHERE userId = ?
");
$stmt->execute([$_SESSION['user_id']]);
$nurse = $stmt->fetch();

// Insert vitals
$stmt = $pdo->prepare("
    INSERT INTO vitals (recordId, height, weight, bodyTemperature, 
                        bloodPressureSystolic, bloodPressureDiastolic, 
                        heartRate, respiratoryRate, oxygenSaturation, 
                        notes, recordedBy, recordedDate) 
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
");
$stmt->execute([
    $record['recordId'], $height, $weight, $bodyTemperature,
    $bpSystolic, $bpDiastolic, $heartRate, $respiratoryRate,
    $oxygenSaturation, $notes, $nurse['staffId']
]);

$_SESSION['success'] = "Patient vitals recorded successfully!";
logAction($_SESSION['user_id'], 'RECORD_VITALS', "Recorded vitals for patient ID: $patientId");

header("Location: dashboard.php");
exit();
?>