<?php
require_once '../includes/config.php';
require_once '../includes/auth.php';
checkRole('staff');

$appointmentId = $_GET['id'] ?? 0;
if (!$appointmentId) { 
    $_SESSION['error'] = "Invalid ID."; 
    header("Location: dashboard.php"); 
    exit(); 
}

$stmt = $pdo->prepare("UPDATE appointments SET status = 'confirmed', updatedAt = NOW() WHERE appointmentId = ?");
$stmt->execute([$appointmentId]);

$_SESSION['success'] = "Patient checked in successfully!";
logAction($_SESSION['user_id'], 'CHECKIN_PATIENT', "Checked in appointment $appointmentId");
header("Location: dashboard.php");
exit();
?>