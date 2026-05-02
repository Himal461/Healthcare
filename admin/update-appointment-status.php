<?php
require_once '../includes/config.php';
require_once '../includes/auth.php';
checkRole('admin');

// Validate request method
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $_SESSION['error'] = "Invalid request method.";
    header("Location: appointments.php");
    exit();
}

// Get and validate inputs
$appointmentId = (int)($_POST['appointment_id'] ?? 0);
$status = $_POST['status'] ?? '';

$validStatuses = ['scheduled', 'confirmed', 'in-progress', 'completed', 'cancelled', 'no-show'];

if (!$appointmentId) {
    $_SESSION['error'] = "Invalid appointment ID.";
    header("Location: appointments.php");
    exit();
}

if (!in_array($status, $validStatuses)) {
    $_SESSION['error'] = "Invalid status value.";
    header("Location: appointments.php");
    exit();
}

try {
    $pdo->beginTransaction();
    
    // First, get the old status to check if it actually changed
    $checkStmt = $pdo->prepare("SELECT status FROM appointments WHERE appointmentId = ?");
    $checkStmt->execute([$appointmentId]);
    $oldStatus = $checkStmt->fetchColumn();
    
    if ($oldStatus === $status) {
        $_SESSION['info'] = "No changes were made. Status is already: " . ucfirst($status);
        $pdo->rollBack();
        
        // Redirect back with filters
        $redirectParams = [];
        if (!empty($_POST['redirect_status'])) $redirectParams[] = 'status=' . urlencode($_POST['redirect_status']);
        if (!empty($_POST['redirect_doctor'])) $redirectParams[] = 'doctor=' . urlencode($_POST['redirect_doctor']);
        if (!empty($_POST['redirect_date_from'])) $redirectParams[] = 'date_from=' . urlencode($_POST['redirect_date_from']);
        if (!empty($_POST['redirect_date_to'])) $redirectParams[] = 'date_to=' . urlencode($_POST['redirect_date_to']);
        
        $redirectUrl = "appointments.php";
        if (!empty($redirectParams)) $redirectUrl .= '?' . implode('&', $redirectParams);
        header("Location: " . $redirectUrl);
        exit();
    }
    
    // Update appointment status
    $stmt = $pdo->prepare("
        UPDATE appointments 
        SET status = ?, updatedAt = NOW() 
        WHERE appointmentId = ?
    ");
    $stmt->execute([$status, $appointmentId]);
    
    if ($stmt->rowCount() > 0) {
        // Get appointment details for notifications
        $apptStmt = $pdo->prepare("
            SELECT a.*, 
                   p.userId as patientUserId, 
                   du.userId as doctorUserId,
                   CONCAT(pu.firstName, ' ', pu.lastName) as patientName,
                   CONCAT(du.firstName, ' ', du.lastName) as doctorName
            FROM appointments a
            JOIN patients p ON a.patientId = p.patientId
            JOIN users pu ON p.userId = pu.userId
            JOIN doctors d ON a.doctorId = d.doctorId
            JOIN staff s ON d.staffId = s.staffId
            JOIN users du ON s.userId = du.userId
            WHERE a.appointmentId = ?
        ");
        $apptStmt->execute([$appointmentId]);
        $appt = $apptStmt->fetch();
        
        if ($appt) {
            // Notify patient
            createNotification(
                $appt['patientUserId'],
                'appointment',
                'Appointment Status Updated',
                "Your appointment with Dr. {$appt['doctorName']} on " . 
                date('M j, Y g:i A', strtotime($appt['dateTime'])) . 
                " has been updated to: " . ucfirst($status)
            );
            
            // Notify doctor
            createNotification(
                $appt['doctorUserId'],
                'appointment',
                'Appointment Status Updated',
                "Appointment with patient {$appt['patientName']} on " . 
                date('M j, Y g:i A', strtotime($appt['dateTime'])) . 
                " has been updated to: " . ucfirst($status)
            );
        }
        
        $_SESSION['success'] = "Appointment #{$appointmentId} status updated to: " . ucfirst($status);
        logAction($_SESSION['user_id'], 'UPDATE_APPOINTMENT_STATUS', "Updated appointment $appointmentId from $oldStatus to $status");
    } else {
        $_SESSION['error'] = "Failed to update appointment. Please try again.";
    }
    
    $pdo->commit();
    
} catch (Exception $e) {
    $pdo->rollBack();
    $_SESSION['error'] = "Failed to update appointment: " . $e->getMessage();
    error_log("Update appointment status error: " . $e->getMessage());
}

// Redirect back to appointments page with any existing filters
$redirectParams = [];
if (!empty($_POST['redirect_status'])) $redirectParams[] = 'status=' . urlencode($_POST['redirect_status']);
if (!empty($_POST['redirect_doctor'])) $redirectParams[] = 'doctor=' . urlencode($_POST['redirect_doctor']);
if (!empty($_POST['redirect_date_from'])) $redirectParams[] = 'date_from=' . urlencode($_POST['redirect_date_from']);
if (!empty($_POST['redirect_date_to'])) $redirectParams[] = 'date_to=' . urlencode($_POST['redirect_date_to']);

$redirectUrl = "appointments.php";
if (!empty($redirectParams)) {
    $redirectUrl .= '?' . implode('&', $redirectParams);
}

header("Location: " . $redirectUrl);
exit();