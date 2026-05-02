<?php
require_once '../includes/config.php';
require_once '../includes/auth.php';
checkRole('staff');

$appointmentId = $_GET['id'] ?? 0;
if (!$appointmentId) { $_SESSION['error'] = "Invalid ID."; header("Location: dashboard.php"); exit(); }

$stmt = $pdo->prepare("UPDATE appointments SET status = 'cancelled', cancellationReason = 'Cancelled by staff', updatedAt = NOW() WHERE appointmentId = ? AND status = 'scheduled'");
$stmt->execute([$appointmentId]);

$appointmentStmt = $pdo->prepare("SELECT a.patientId, a.doctorId, pu.userId as patientUserId, du.userId as doctorUserId, CONCAT(pu.firstName,' ',pu.lastName) as patientName, CONCAT(du.firstName,' ',du.lastName) as doctorName, a.dateTime FROM appointments a JOIN patients p ON a.patientId = p.patientId JOIN users pu ON p.userId = pu.userId JOIN doctors d ON a.doctorId = d.doctorId JOIN staff s ON d.staffId = s.staffId JOIN users du ON s.userId = du.userId WHERE a.appointmentId = ?");
$appointmentStmt->execute([$appointmentId]);
$appt = $appointmentStmt->fetch();
if ($appt) {
    createNotification($appt['patientUserId'], 'appointment', 'Appointment Cancelled', "Your appointment with Dr. {$appt['doctorName']} on ".date('M j, Y g:i A', strtotime($appt['dateTime']))." has been cancelled.");
    createNotification($appt['doctorUserId'], 'appointment', 'Appointment Cancelled', "Appointment with {$appt['patientName']} on ".date('M j, Y g:i A', strtotime($appt['dateTime']))." cancelled.");
}

$_SESSION['success'] = "Appointment cancelled!";
logAction($_SESSION['user_id'], 'CANCEL_APPOINTMENT', "Cancelled appointment $appointmentId");
header("Location: dashboard.php");
exit();
?>