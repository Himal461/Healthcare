<?php
require_once '../includes/config.php';
require_once '../includes/auth.php';
checkRole('staff');

$appointmentId = $_GET['id'] ?? 0;

if (!$appointmentId) {
    $_SESSION['error'] = "Invalid appointment ID.";
    header("Location: dashboard.php");
    exit();
}

try {
    $stmt = $pdo->prepare("
        UPDATE appointments 
        SET status = 'cancelled', 
            cancellationReason = 'Cancelled by reception staff',
            updatedAt = NOW()
        WHERE appointmentId = ? AND status = 'scheduled'
    ");
    $stmt->execute([$appointmentId]);
    
    // Get patient and doctor info for notifications
    $appointmentStmt = $pdo->prepare("
        SELECT a.patientId, a.doctorId, 
               pu.userId as patientUserId, 
               du.userId as doctorUserId,
               CONCAT(pu.firstName, ' ', pu.lastName) as patientName,
               CONCAT(du.firstName, ' ', du.lastName) as doctorName,
               a.dateTime
        FROM appointments a
        JOIN patients p ON a.patientId = p.patientId
        JOIN users pu ON p.userId = pu.userId
        JOIN doctors d ON a.doctorId = d.doctorId
        JOIN staff s ON d.staffId = s.staffId
        JOIN users du ON s.userId = du.userId
        WHERE a.appointmentId = ?
    ");
    $appointmentStmt->execute([$appointmentId]);
    $appointment = $appointmentStmt->fetch();
    
    if ($appointment) {
        // Notify patient
        createNotification(
            $appointment['patientUserId'],
            'appointment',
            'Appointment Cancelled',
            "Your appointment with Dr. {$appointment['doctorName']} on " . date('M j, Y g:i A', strtotime($appointment['dateTime'])) . " has been cancelled. Please contact reception to reschedule."
        );
        
        // Notify doctor
        createNotification(
            $appointment['doctorUserId'],
            'appointment',
            'Appointment Cancelled',
            "Appointment with patient {$appointment['patientName']} on " . date('M j, Y g:i A', strtotime($appointment['dateTime'])) . " has been cancelled."
        );
    }
    
    $_SESSION['success'] = "Appointment cancelled successfully!";
    logAction($_SESSION['user_id'], 'CANCEL_APPOINTMENT', "Cancelled appointment ID: $appointmentId");
    
} catch (Exception $e) {
    $_SESSION['error'] = "Failed to cancel appointment: " . $e->getMessage();
}

header("Location: dashboard.php");
exit();
?>