<?php
// Load PHPMailer classes manually
require_once __DIR__ . '/../PHPMailer/Exception.php';
require_once __DIR__ . '/../PHPMailer/PHPMailer.php';
require_once __DIR__ . '/../PHPMailer/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

function sendEmail($to, $subject, $message) {
    $mail = new PHPMailer(true);
    
    try {
        // Server settings
        $mail->SMTPDebug = SMTP::DEBUG_OFF;
        $mail->isSMTP();
        $mail->Host = SMTP_HOST;
        $mail->SMTPAuth = true;
        $mail->Username = SMTP_USERNAME;
        $mail->Password = SMTP_PASSWORD;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = SMTP_PORT;
        $mail->CharSet = 'UTF-8';

        // Recipients
        $mail->setFrom(SMTP_FROM, SMTP_FROM_NAME);
        $mail->addAddress($to);
        $mail->addReplyTo(SMTP_FROM, SMTP_FROM_NAME);

        // Content
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body = $message;
        $mail->AltBody = strip_tags($message);

        return $mail->send();
    } catch (Exception $e) {
        error_log("Mailer Error: " . $mail->ErrorInfo);
        return false;
    }
}

function generateVerificationCode() {
    return bin2hex(random_bytes(32));
}

function logAction($userId, $action, $details = '') {
    global $pdo;
    
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
    
    try {
        $stmt = $pdo->prepare("INSERT INTO audit_log (userId, action, details, ipAddress, userAgent) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$userId, $action, $details, $ip, $userAgent]);
    } catch (Exception $e) {
        error_log("Failed to log action: " . $e->getMessage());
    }
}

function hasPermission($requiredRole) {
    if (!isset($_SESSION['user_role'])) {
        return false;
    }
    
    $userRole = $_SESSION['user_role'];
    $rolesHierarchy = [
        'admin' => ['admin', 'doctor', 'nurse', 'staff', 'patient'],
        'doctor' => ['doctor', 'nurse', 'staff', 'patient'],
        'nurse' => ['nurse', 'staff', 'patient'],
        'staff' => ['staff', 'patient'],
        'patient' => ['patient']
    ];
    
    return in_array($requiredRole, $rolesHierarchy[$userRole] ?? []);
}

function redirect($url) {
    header("Location: $url");
    exit();
}

function sanitizeInput($data) {
    return htmlspecialchars(strip_tags(trim($data)));
}

function getAvailableTimeSlots($doctorId, $date, $duration = APPOINTMENT_DURATION) {
    global $pdo;
    
    $timeSlots = [];
    $start = strtotime($date . ' ' . WORKING_HOURS_START);
    $end = strtotime($date . ' ' . WORKING_HOURS_END);
    $breakStart = strtotime($date . ' ' . BREAK_START);
    $breakEnd = strtotime($date . ' ' . BREAK_END);
    
    $stmt = $pdo->prepare("
        SELECT dateTime, duration 
        FROM appointments 
        WHERE doctorId = ? AND DATE(dateTime) = ? 
        AND status NOT IN ('cancelled', 'no-show')
        ORDER BY dateTime
    ");
    $stmt->execute([$doctorId, $date]);
    $existingAppointments = $stmt->fetchAll();
    
    for ($time = $start; $time + ($duration * 60) <= $end; $time += ($duration * 60)) {
        if ($time >= $breakStart && $time < $breakEnd) {
            continue;
        }
        
        $isAvailable = true;
        foreach ($existingAppointments as $appointment) {
            $apptStart = strtotime($appointment['dateTime']);
            $apptEnd = $apptStart + ($appointment['duration'] * 60);
            
            if (($time >= $apptStart && $time < $apptEnd) || 
                ($time + ($duration * 60) > $apptStart && $time + ($duration * 60) <= $apptEnd)) {
                $isAvailable = false;
                break;
            }
        }
        
        if ($isAvailable && count($existingAppointments) >= MAX_APPOINTMENTS_PER_DAY) {
            $isAvailable = false;
        }
        
        if ($isAvailable) {
            $timeSlots[] = [
                'start' => date('g:i A', $time),
                'value' => date('H:i:s', $time),
                'timestamp' => $time
            ];
        }
    }
    
    return $timeSlots;
}

function suggestAlternativeSlots($doctorId, $preferredDateTime, $duration = APPOINTMENT_DURATION) {
    $preferredDate = date('Y-m-d', strtotime($preferredDateTime));
    $preferredTime = date('H:i:s', strtotime($preferredDateTime));
    
    $availableSlots = getAvailableTimeSlots($doctorId, $preferredDate, $duration);
    $isPreferredAvailable = false;
    
    foreach ($availableSlots as $slot) {
        if ($slot['value'] == $preferredTime) {
            $isPreferredAvailable = true;
            break;
        }
    }
    
    if ($isPreferredAvailable) {
        return ['available' => true, 'alternatives' => []];
    }
    
    $alternatives = [];
    for ($i = 1; $i <= 7; $i++) {
        $altDate = date('Y-m-d', strtotime($preferredDate . " + $i days"));
        $altSlots = getAvailableTimeSlots($doctorId, $altDate, $duration);
        
        foreach ($altSlots as $slot) {
            $alternatives[] = [
                'date' => $altDate,
                'date_formatted' => date('l, F j', strtotime($altDate)),
                'time' => $slot['start'],
                'time_value' => $slot['value']
            ];
            if (count($alternatives) >= 5) break 2;
        }
    }
    
    return ['available' => false, 'alternatives' => $alternatives];
}

function createNotification($userId, $type, $title, $message, $link = null) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("INSERT INTO notifications (userId, type, title, message, link) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$userId, $type, $title, $message, $link]);
        return true;
    } catch (Exception $e) {
        error_log("Failed to create notification: " . $e->getMessage());
        return false;
    }
}

function getDoctorSchedule($doctorId, $date) {
    global $pdo;
    
    $stmt = $pdo->prepare("
        SELECT a.*, u.firstName, u.lastName, u.phoneNumber, p.dateOfBirth, p.bloodType
        FROM appointments a
        JOIN patients p ON a.patientId = p.patientId
        JOIN users u ON p.userId = u.userId
        WHERE a.doctorId = ? AND DATE(a.dateTime) = ?
        AND a.status NOT IN ('cancelled', 'no-show')
        ORDER BY a.dateTime
    ");
    $stmt->execute([$doctorId, $date]);
    return $stmt->fetchAll();
}
?>