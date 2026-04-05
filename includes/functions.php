<?php
// Load PHPMailer classes manually
require_once __DIR__ . '/../PHPMailer/Exception.php';
require_once __DIR__ . '/../PHPMailer/PHPMailer.php';
require_once __DIR__ . '/../PHPMailer/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

/* ============================================
   EMAIL FUNCTIONS
   ============================================ */
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

/* ============================================
   VERIFICATION FUNCTIONS
   ============================================ */
function generateVerificationCode() {
    return bin2hex(random_bytes(32));
}

/* ============================================
   AUDIT LOG FUNCTIONS
   ============================================ */
function logAction($userId, $action, $details = '') {
    global $pdo;
    
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
    
    try {
        if (empty($userId) || $userId === 0) {
            $stmt = $pdo->prepare("INSERT INTO audit_log (userId, action, details, ipAddress, userAgent) VALUES (NULL, ?, ?, ?, ?)");
            $stmt->execute([$action, $details, $ip, $userAgent]);
        } else {
            $checkUser = $pdo->prepare("SELECT userId FROM users WHERE userId = ?");
            $checkUser->execute([$userId]);
            if ($checkUser->fetch()) {
                $stmt = $pdo->prepare("INSERT INTO audit_log (userId, action, details, ipAddress, userAgent) VALUES (?, ?, ?, ?, ?)");
                $stmt->execute([$userId, $action, $details, $ip, $userAgent]);
            } else {
                $stmt = $pdo->prepare("INSERT INTO audit_log (userId, action, details, ipAddress, userAgent) VALUES (NULL, ?, ?, ?, ?)");
                $stmt->execute([$action, $details, $ip, $userAgent]);
            }
        }
    } catch (Exception $e) {
        error_log("Failed to log action: " . $e->getMessage());
    }
}

/* ============================================
   PERMISSION FUNCTIONS
   ============================================ */
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

/* ============================================
   REDIRECT FUNCTION
   ============================================ */
function redirect($url) {
    header("Location: $url");
    exit();
}

/* ============================================
   SANITIZATION FUNCTIONS
   ============================================ */
function sanitizeInput($data) {
    return htmlspecialchars(strip_tags(trim($data)));
}

/* ============================================
   APPOINTMENT FUNCTIONS
   ============================================ */
function getAvailableTimeSlots($doctorId, $date) {
    global $pdo;
    
    $timeSlots = [];
    
    // Define working hours (9:00 AM to 5:00 PM)
    $start = strtotime($date . ' 09:00:00');
    $end = strtotime($date . ' 17:00:00');
    $breakStart = strtotime($date . ' 13:00:00');
    $breakEnd = strtotime($date . ' 14:00:00');
    $duration = 30; // minutes
    
    // Get existing appointments for the doctor on this date
    $stmt = $pdo->prepare("
        SELECT dateTime, duration 
        FROM appointments 
        WHERE doctorId = ? AND DATE(dateTime) = ? 
        AND status NOT IN ('cancelled', 'no-show')
        ORDER BY dateTime
    ");
    $stmt->execute([$doctorId, $date]);
    $existingAppointments = $stmt->fetchAll();
    
    // Convert existing appointments to timestamps for easier comparison
    $bookedSlots = [];
    foreach ($existingAppointments as $appointment) {
        $apptTime = strtotime($appointment['dateTime']);
        $bookedSlots[] = $apptTime;
    }
    
    // Generate all possible time slots (30-minute intervals)
    for ($time = $start; $time + ($duration * 60) <= $end; $time += ($duration * 60)) {
        $hour = (int)date('H', $time);
        $minute = (int)date('i', $time);
        
        // Format the time for display
        if ($hour < 12) {
            $displayHour = $hour == 0 ? 12 : $hour;
            $ampm = 'AM';
        } else {
            $displayHour = $hour == 12 ? 12 : $hour - 12;
            $ampm = 'PM';
        }
        $slotStart = sprintf("%d:%02d %s", $displayHour, $minute, $ampm);
        $slotValue = date('H:i:s', $time);
        
        // Skip break time (1:00 PM - 2:00 PM)
        if ($time >= $breakStart && $time < $breakEnd) {
            continue;
        }
        
        // Check if slot is booked
        $isBooked = false;
        foreach ($bookedSlots as $bookedTime) {
            if ($time == $bookedTime) {
                $isBooked = true;
                break;
            }
        }
        
        // Also check if slot overlaps with any existing appointment
        foreach ($existingAppointments as $appointment) {
            $apptStart = strtotime($appointment['dateTime']);
            $apptEnd = $apptStart + ($appointment['duration'] * 60);
            
            if ($time >= $apptStart && $time < $apptEnd) {
                $isBooked = true;
                break;
            }
        }
        
        // Check daily appointment limit (max 10 per day)
        $isAvailable = !$isBooked && count($existingAppointments) < 10;
        
        if ($isAvailable) {
            $timeSlots[] = [
                'start' => $slotStart,
                'value' => $slotValue,
                'timestamp' => $time
            ];
        }
    }
    
    return $timeSlots;
}

function suggestAlternativeSlots($doctorId, $preferredDateTime) {
    $preferredDate = date('Y-m-d', strtotime($preferredDateTime));
    $preferredTime = date('H:i:s', strtotime($preferredDateTime));
    
    // Check if preferred slot is available
    $availableSlots = getAvailableTimeSlots($doctorId, $preferredDate);
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
    
    // Find alternative dates (next 7 days)
    $alternatives = [];
    for ($i = 1; $i <= 7; $i++) {
        $altDate = date('Y-m-d', strtotime($preferredDate . " + $i days"));
        $altSlots = getAvailableTimeSlots($doctorId, $altDate);
        
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

/* ============================================
   NOTIFICATION FUNCTIONS
   ============================================ */
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

function sendAppointmentReminders() {
    global $pdo;
    
    $tomorrow = date('Y-m-d', strtotime('+1 day'));
    
    $stmt = $pdo->prepare("
        SELECT a.*, u.email, u.firstName, u.lastName, 
               du.firstName as doctorFirstName, du.lastName as doctorLastName
        FROM appointments a
        JOIN patients p ON a.patientId = p.patientId
        JOIN users u ON p.userId = u.userId
        JOIN doctors d ON a.doctorId = d.doctorId
        JOIN staff s ON d.staffId = s.staffId
        JOIN users du ON s.userId = du.userId
        WHERE DATE(a.dateTime) = ? AND a.status = 'scheduled'
        AND (a.reminderSent = 0 OR a.reminderSent IS NULL)
    ");
    $stmt->execute([$tomorrow]);
    $appointments = $stmt->fetchAll();
    
    foreach ($appointments as $appointment) {
        $subject = "Appointment Reminder - " . SITE_NAME;
        $message = "
            <html>
            <head>
                <style>
                    body { font-family: Arial, sans-serif; line-height: 1.6; }
                    .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                    .header { background: #1a75bc; color: white; padding: 20px; text-align: center; border-radius: 5px; }
                    .content { padding: 20px; background: #f9f9f9; margin-top: 20px; border-radius: 5px; }
                    .footer { text-align: center; padding: 20px; color: #666; font-size: 12px; margin-top: 20px; }
                </style>
            </head>
            <body>
                <div class='container'>
                    <div class='header'>
                        <h2>Appointment Reminder</h2>
                    </div>
                    <div class='content'>
                        <p>Dear <strong>{$appointment['firstName']} {$appointment['lastName']}</strong>,</p>
                        <p>This is a reminder for your appointment with <strong>Dr. {$appointment['doctorFirstName']} {$appointment['doctorLastName']}</strong> tomorrow at <strong>" . date('g:i A', strtotime($appointment['dateTime'])) . "</strong>.</p>
                        <p>Please arrive 15 minutes before your scheduled time.</p>
                        <p>If you need to reschedule or cancel, please log in to your account.</p>
                        <p>Thank you,<br>" . SITE_NAME . " Team</p>
                    </div>
                    <div class='footer'>
                        <p>This is an automated message. Please do not reply to this email.</p>
                        <p>&copy; " . date('Y') . " " . SITE_NAME . ". All rights reserved.</p>
                    </div>
                </div>
            </body>
            </html>
        ";
        
        if (sendEmail($appointment['email'], $subject, $message)) {
            $update = $pdo->prepare("UPDATE appointments SET reminderSent = 1 WHERE appointmentId = ?");
            $update->execute([$appointment['appointmentId']]);
        }
    }
}

/* ============================================
   BILLING FUNCTIONS
   ============================================ */
function hasBill($appointmentId) {
    global $pdo;
    try {
        $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM billing WHERE appointmentId = ?");
        $stmt->execute([$appointmentId]);
        $result = $stmt->fetch();
        return $result['count'] > 0;
    } catch (Exception $e) {
        error_log("Error checking bill existence: " . $e->getMessage());
        return false;
    }
}

/* ============================================
   FORMATTING FUNCTIONS
   ============================================ */
function formatDate($date) {
    return date('F j, Y', strtotime($date));
}

function formatDateTime($datetime) {
    return date('F j, Y g:i A', strtotime($datetime));
}

function calculateAge($birthDate) {
    if (!$birthDate) return 'N/A';
    $birthDate = new DateTime($birthDate);
    $today = new DateTime('today');
    $age = $birthDate->diff($today)->y;
    return $age;
}

/* ============================================
   USER FUNCTIONS
   ============================================ */
function getUserFullName($userId) {
    global $pdo;
    try {
        $stmt = $pdo->prepare("SELECT CONCAT(firstName, ' ', lastName) as fullName FROM users WHERE userId = ?");
        $stmt->execute([$userId]);
        $result = $stmt->fetch();
        return $result['fullName'] ?? 'Unknown User';
    } catch (Exception $e) {
        return 'Unknown User';
    }
}

function getUserRole($userId) {
    global $pdo;
    try {
        $stmt = $pdo->prepare("SELECT role FROM users WHERE userId = ?");
        $stmt->execute([$userId]);
        $result = $stmt->fetch();
        return $result['role'] ?? 'unknown';
    } catch (Exception $e) {
        return 'unknown';
    }
}

/* ============================================
   DASHBOARD STATISTICS FUNCTIONS
   ============================================ */
function getTotalUsers() {
    global $pdo;
    $stmt = $pdo->query("SELECT COUNT(*) FROM users");
    return $stmt->fetchColumn();
}

function getTotalDoctors() {
    global $pdo;
    $stmt = $pdo->query("SELECT COUNT(*) FROM doctors");
    return $stmt->fetchColumn();
}

function getTotalPatients() {
    global $pdo;
    $stmt = $pdo->query("SELECT COUNT(*) FROM patients");
    return $stmt->fetchColumn();
}

function getTotalAppointments() {
    global $pdo;
    $stmt = $pdo->query("SELECT COUNT(*) FROM appointments");
    return $stmt->fetchColumn();
}

function getTodayAppointments() {
    global $pdo;
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM appointments WHERE DATE(dateTime) = CURDATE()");
    $stmt->execute();
    return $stmt->fetchColumn();
}

/* ============================================
   DEBUG FUNCTIONS
   ============================================ */
function debug_log($message, $data = null) {
    $logMessage = date('Y-m-d H:i:s') . " - " . $message;
    if ($data !== null) {
        $logMessage .= " - " . print_r($data, true);
    }
    error_log($logMessage);
}
?>