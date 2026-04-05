<?php
require_once '../includes/config.php';
require_once '../includes/auth.php';
checkRole('staff');

$appointmentId = $_GET['id'] ?? 0;

$stmt = $pdo->prepare("UPDATE appointments SET status = 'confirmed' WHERE appointmentId = ?");
$stmt->execute([$appointmentId]);

$_SESSION['success'] = "Patient checked in successfully!";
logAction($_SESSION['user_id'], 'CHECKIN_PATIENT', "Checked in appointment ID: $appointmentId");

header("Location: dashboard.php");
exit();
?>