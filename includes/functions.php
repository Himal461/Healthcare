<?php
require_once __DIR__ . '/../PHPMailer/Exception.php';
require_once __DIR__ . '/../PHPMailer/PHPMailer.php';
require_once __DIR__ . '/../PHPMailer/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

/* ============================================
   EMAIL FUNCTIONS
   ============================================ */
function sendEmail($to, $subject, $message) {
    // Validate email address
    if (empty($to) || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
        error_log("Invalid email address: " . ($to ?: 'empty'));
        return false;
    }
    
    $mail = new PHPMailer(true);
    
    try {
        $mail->SMTPDebug = 0;
        $mail->isSMTP();
        $mail->Host = SMTP_HOST;
        $mail->SMTPAuth = true;
        $mail->Username = SMTP_USERNAME;
        $mail->Password = SMTP_PASSWORD;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = SMTP_PORT;
        $mail->CharSet = 'UTF-8';

        $mail->setFrom(SMTP_FROM, SMTP_FROM_NAME);
        $mail->addAddress($to);
        $mail->addReplyTo(SMTP_FROM, SMTP_FROM_NAME);

        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body = $message;
        $mail->AltBody = strip_tags($message);

        $result = $mail->send();
        error_log("Email sent successfully to: $to | Subject: $subject");
        return $result;
    } catch (Exception $e) {
        error_log("Mailer Error sending to $to: " . $mail->ErrorInfo . " | Exception: " . $e->getMessage());
        return false;
    }
}

/* ============================================
   NOTIFICATION FUNCTIONS
   ============================================ */
function createNotification($userId, $type, $title, $message, $link = null) {
    global $pdo;
    
    if (empty($userId) || empty($title) || empty($message)) {
        error_log("Notification creation failed: Missing required fields. UserId: $userId");
        return false;
    }
    
    // Clean the link - remove any leading ../ or ./
    if ($link) {
        $link = preg_replace('/^(\.\.\/|\.\/)+/', '', $link);
    }
    
    try {
        $stmt = $pdo->prepare("
            INSERT INTO notifications (userId, type, title, message, link, isRead, sentDate) 
            VALUES (?, ?, ?, ?, ?, 0, NOW())
        ");
        $result = $stmt->execute([$userId, $type, $title, $message, $link]);
        error_log("Notification created for user $userId: $title | Link: $link");
        return $result;
    } catch (Exception $e) {
        error_log("Failed to create notification: " . $e->getMessage());
        return false;
    }
}

function markNotificationRead($notificationId, $userId) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("
            UPDATE notifications SET isRead = 1, readDate = NOW() 
            WHERE notificationId = ? AND userId = ?
        ");
        $stmt->execute([$notificationId, $userId]);
        return true;
    } catch (Exception $e) {
        return false;
    }
}

function getUnreadNotificationsCount($userId) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE userId = ? AND isRead = 0");
        $stmt->execute([$userId]);
        return (int)$stmt->fetchColumn();
    } catch (Exception $e) {
        return 0;
    }
}

function getUserNotifications($userId, $limit = 50) {
    global $pdo;
    
    try {
        $limit = (int)$limit;
        $stmt = $pdo->prepare("
            SELECT * FROM notifications 
            WHERE userId = ? 
            ORDER BY sentDate DESC 
            LIMIT " . $limit
        );
        $stmt->execute([$userId]);
        return $stmt->fetchAll();
    } catch (Exception $e) {
        error_log("getUserNotifications error: " . $e->getMessage());
        return [];
    }
}

/* ============================================
   AUDIT LOG FUNCTIONS
   ============================================ */
function logAction($userId, $action, $details = '') {
    global $pdo;
    
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
    
    try {
        $stmt = $pdo->prepare("
            INSERT INTO audit_log (userId, action, details, ipAddress, userAgent) 
            VALUES (?, ?, ?, ?, ?)
        ");
        $stmt->execute([$userId ?: null, $action, $details, $ip, $userAgent]);
        return true;
    } catch (Exception $e) {
        error_log("Failed to log action: " . $e->getMessage());
        return false;
    }
}

/* ============================================
   SANITIZATION FUNCTIONS
   ============================================ */
function sanitizeInput($data) {
    if ($data === null) return null;
    return htmlspecialchars(strip_tags(trim($data)), ENT_QUOTES, 'UTF-8');
}

/* ============================================
   APPOINTMENT FUNCTIONS
   ============================================ */
function getAvailableTimeSlots($doctorId, $date) {
    global $pdo;
    
    $timeSlots = [];
    
    $stmt = $pdo->prepare("
        SELECT startTime, endTime, isAvailable, isDayOff
        FROM doctor_availability 
        WHERE doctorId = ? AND availabilityDate = ?
    ");
    $stmt->execute([$doctorId, $date]);
    $availability = $stmt->fetch();
    
    if (!$availability || $availability['isDayOff'] || !$availability['isAvailable']) {
        return [];
    }
    
    $startTime = $availability['startTime'];
    $endTime = $availability['endTime'];
    
    $start = strtotime($date . ' ' . $startTime);
    $end = strtotime($date . ' ' . $endTime);
    $duration = APPOINTMENT_DURATION;
    
    $stmt = $pdo->prepare("
        SELECT TIME(dateTime) as slot_time 
        FROM appointments 
        WHERE doctorId = ? AND DATE(dateTime) = ? 
        AND status NOT IN ('cancelled', 'no-show')
    ");
    $stmt->execute([$doctorId, $date]);
    $bookedSlots = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    $currentTime = time();
    
    for ($time = $start; $time + ($duration * 60) <= $end; $time += ($duration * 60)) {
        if ($date === date('Y-m-d') && $time < $currentTime) continue;
        
        $timeValue = date('H:i:s', $time);
        $displayTime = date('g:i A', $time);
        
        if (!in_array($timeValue, $bookedSlots)) {
            $timeSlots[] = ['value' => $timeValue, 'display' => $displayTime, 'start' => $displayTime];
        }
    }
    
    return $timeSlots;
}

function getDoctorSchedule($doctorId, $date) {
    global $pdo;
    
    $stmt = $pdo->prepare("
        SELECT a.*, 
               CONCAT(u.firstName, ' ', u.lastName) as patientName,
               u.phoneNumber as patientPhone,
               p.dateOfBirth,
               p.bloodType
        FROM appointments a
        JOIN patients p ON a.patientId = p.patientId
        JOIN users u ON p.userId = u.userId
        WHERE a.doctorId = ? AND DATE(a.dateTime) = ? AND a.status NOT IN ('cancelled')
        ORDER BY a.dateTime
    ");
    $stmt->execute([$doctorId, $date]);
    return $stmt->fetchAll();
}

function cancelAppointment($appointmentId, $cancelledBy, $reason = null) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("
            SELECT a.*, p.userId as patientUserId, d.doctorId,
                   CONCAT(du.firstName, ' ', du.lastName) as doctorName,
                   CONCAT(pu.firstName, ' ', pu.lastName) as patientName
            FROM appointments a
            JOIN patients p ON a.patientId = p.patientId
            JOIN users pu ON p.userId = pu.userId
            JOIN doctors d ON a.doctorId = d.doctorId
            JOIN staff s ON d.staffId = s.staffId
            JOIN users du ON s.userId = du.userId
            WHERE a.appointmentId = ?
        ");
        $stmt->execute([$appointmentId]);
        $appointment = $stmt->fetch();
        
        if (!$appointment) return false;
        
        $stmt = $pdo->prepare("
            UPDATE appointments SET status = 'cancelled', cancellationReason = ?, updatedAt = NOW() 
            WHERE appointmentId = ?
        ");
        $stmt->execute([$reason ?: 'Cancelled', $appointmentId]);
        
        createNotification($appointment['patientUserId'], 'appointment', 'Appointment Cancelled',
            "Your appointment with Dr. {$appointment['doctorName']} on " . 
            date('M j, Y g:i A', strtotime($appointment['dateTime'])) . " has been cancelled.");
        
        $doctorStmt = $pdo->prepare("SELECT userId FROM staff s JOIN doctors d ON s.staffId = d.staffId WHERE d.doctorId = ?");
        $doctorStmt->execute([$appointment['doctorId']]);
        $doctorUser = $doctorStmt->fetch();
        
        if ($doctorUser) {
            createNotification($doctorUser['userId'], 'appointment', 'Appointment Cancelled',
                "Appointment with patient {$appointment['patientName']} on " . 
                date('M j, Y g:i A', strtotime($appointment['dateTime'])) . " has been cancelled.");
        }
        
        logAction($_SESSION['user_id'] ?? null, 'CANCEL_APPOINTMENT', "Cancelled appointment ID: $appointmentId");
        return true;
        
    } catch (Exception $e) {
        error_log("Cancel appointment error: " . $e->getMessage());
        return false;
    }
}

function suggestAlternativeSlots($doctorId, $dateTime) {
    global $pdo;
    
    $date = date('Y-m-d', strtotime($dateTime));
    $time = date('H:i:s', strtotime($dateTime));
    $alternatives = [];
    $available = true;
    
    $stmt = $pdo->prepare("
        SELECT COUNT(*) FROM appointments 
        WHERE doctorId = ? AND DATE(dateTime) = ? AND TIME(dateTime) = ? 
        AND status NOT IN ('cancelled', 'no-show')
    ");
    $stmt->execute([$doctorId, $date, $time]);
    
    if ($stmt->fetchColumn() > 0) {
        $available = false;
        $timeSlots = getAvailableTimeSlots($doctorId, $date);
        $alternatives = array_slice($timeSlots, 0, 5);
    }
    
    return ['available' => $available, 'alternatives' => $alternatives];
}

/* ============================================
   BILLING FUNCTIONS - UNIFIED
   ============================================ */
function generateBill($patientId, $appointmentId, $recordId, $consultationFee, $additionalCharges = []) {
    global $pdo;
    
    try {
        $additionalTotal = is_array($additionalCharges) ? array_sum($additionalCharges) : 0;
        $subtotal = $consultationFee + $additionalTotal;
        $serviceCharge = round($subtotal * 0.03, 2);
        $gst = round($subtotal * 0.13, 2);
        $totalAmount = round($subtotal + $serviceCharge + $gst, 2);
        
        $pdo->beginTransaction();
        
        $stmt = $pdo->prepare("
            INSERT INTO bills (
                patientId, appointmentId, recordId, consultationFee, 
                additionalCharges, serviceCharge, gst, totalAmount, 
                status, generatedAt
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'unpaid', NOW())
        ");
        $stmt->execute([
            $patientId, $appointmentId ?: null, $recordId,
            $consultationFee, $additionalTotal, $serviceCharge, $gst, $totalAmount
        ]);
        $billId = $pdo->lastInsertId();
        
        if (!empty($additionalCharges) && is_array($additionalCharges)) {
            $chargeStmt = $pdo->prepare("INSERT INTO bill_charges (billId, chargeName, amount) VALUES (?, ?, ?)");
            foreach ($additionalCharges as $name => $amount) {
                if ($amount > 0 && !empty($name)) {
                    $chargeStmt->execute([$billId, $name, $amount]);
                }
            }
        }
        
        $patientStmt = $pdo->prepare("SELECT userId FROM patients WHERE patientId = ?");
        $patientStmt->execute([$patientId]);
        $patient = $patientStmt->fetch();
        
        if ($patient) {
            createNotification($patient['userId'], 'billing', 'New Bill Generated',
                "A new bill of $" . number_format($totalAmount, 2) . " has been generated.",
                "patient/view-bill.php?bill_id=" . $billId);
        }
        
        $pdo->commit();
        return $billId;
        
    } catch (Exception $e) {
        $pdo->rollBack();
        error_log("Generate bill error: " . $e->getMessage());
        return false;
    }
}

function markBillPaid($billId, $paymentMethod = null) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("SELECT b.*, p.userId as patientUserId FROM bills b JOIN patients p ON b.patientId = p.patientId WHERE b.billId = ?");
        $stmt->execute([$billId]);
        $bill = $stmt->fetch();
        
        if (!$bill || $bill['status'] == 'paid') return false;
        
        $stmt = $pdo->prepare("UPDATE bills SET status = 'paid', paidAt = NOW() WHERE billId = ?");
        $stmt->execute([$billId]);
        
        createNotification($bill['patientUserId'], 'billing', 'Payment Confirmed',
            "Your payment of $" . number_format($bill['totalAmount'], 2) . " has been confirmed.",
            "patient/view-bill.php?bill_id=" . $billId);
        
        logAction($_SESSION['user_id'] ?? null, 'MARK_BILL_PAID', "Marked bill #$billId as paid");
        return true;
        
    } catch (Exception $e) {
        error_log("Mark bill paid error: " . $e->getMessage());
        return false;
    }
}

function updateBillStatus($billId, $status) {
    global $pdo;
    
    try {
        $validStatuses = ['paid', 'unpaid', 'cancelled'];
        if (!in_array($status, $validStatuses)) return false;
        
        $pdo->beginTransaction();
        
        if ($status === 'paid') {
            $stmt = $pdo->prepare("UPDATE bills SET status = ?, paidAt = NOW() WHERE billId = ?");
        } else {
            $stmt = $pdo->prepare("UPDATE bills SET status = ? WHERE billId = ?");
        }
        $stmt->execute([$status, $billId]);
        
        if ($stmt->rowCount() > 0) {
            $billStmt = $pdo->prepare("SELECT b.*, p.userId as patientUserId FROM bills b JOIN patients p ON b.patientId = p.patientId WHERE b.billId = ?");
            $billStmt->execute([$billId]);
            $bill = $billStmt->fetch();
            
            if ($bill) {
                if ($status === 'paid') {
                    createNotification($bill['patientUserId'], 'billing', 'Payment Confirmed',
                        "Your payment of $" . number_format($bill['totalAmount'], 2) . " has been confirmed.",
                        "../patient/view-bill.php?bill_id=" . $billId);
                } elseif ($status === 'cancelled') {
                    createNotification($bill['patientUserId'], 'billing', 'Bill Cancelled',
                        "Your bill #" . str_pad($billId, 6, '0', STR_PAD_LEFT) . " has been cancelled.",
                        "../patient/view-bill.php?bill_id=" . $billId);
                }
            }
            
            logAction($_SESSION['user_id'] ?? null, 'UPDATE_BILL_STATUS', "Updated bill #$billId to $status");
            $pdo->commit();
            return true;
        }
        
        $pdo->rollBack();
        return false;
        
    } catch (Exception $e) {
        $pdo->rollBack();
        error_log("Update bill status error: " . $e->getMessage());
        return false;
    }
}

function getBillById($billId) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("
            SELECT b.*, 
                   CONCAT(u.firstName, ' ', u.lastName) as patientName,
                   u.email as patientEmail, u.phoneNumber as patientPhone,
                   p.dateOfBirth, p.bloodType, p.address,
                   mr.diagnosis, mr.treatmentNotes, mr.creationDate as consultationDate,
                   a.dateTime as appointmentDate,
                   CONCAT(du.firstName, ' ', du.lastName) as doctorName,
                   d.specialization
            FROM bills b
            JOIN patients p ON b.patientId = p.patientId
            JOIN users u ON p.userId = u.userId
            LEFT JOIN medical_records mr ON b.recordId = mr.recordId
            LEFT JOIN appointments a ON b.appointmentId = a.appointmentId
            LEFT JOIN doctors d ON mr.doctorId = d.doctorId
            LEFT JOIN staff s ON d.staffId = s.staffId
            LEFT JOIN users du ON s.userId = du.userId
            WHERE b.billId = ?
        ");
        $stmt->execute([$billId]);
        return $stmt->fetch();
    } catch (Exception $e) {
        error_log("Get bill error: " . $e->getMessage());
        return false;
    }
}

function getAllBills($filters = []) {
    global $pdo;
    
    try {
        $query = "
            SELECT b.*, 
                   CONCAT(u.firstName, ' ', u.lastName) as patientName,
                   u.email as patientEmail
            FROM bills b
            JOIN patients p ON b.patientId = p.patientId
            JOIN users u ON p.userId = u.userId
            WHERE 1=1
        ";
        $params = [];
        
        if (!empty($filters['status'])) {
            $query .= " AND b.status = ?";
            $params[] = $filters['status'];
        }
        
        if (!empty($filters['patient_id'])) {
            $query .= " AND b.patientId = ?";
            $params[] = $filters['patient_id'];
        }
        
        if (!empty($filters['date_from'])) {
            $query .= " AND DATE(b.generatedAt) >= ?";
            $params[] = $filters['date_from'];
        }
        
        if (!empty($filters['date_to'])) {
            $query .= " AND DATE(b.generatedAt) <= ?";
            $params[] = $filters['date_to'];
        }
        
        $query .= " ORDER BY b.generatedAt DESC";
        
        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
        return $stmt->fetchAll();
    } catch (Exception $e) {
        error_log("Get all bills error: " . $e->getMessage());
        return [];
    }
}

function getBillCharges($billId) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("SELECT * FROM bill_charges WHERE billId = ? ORDER BY id ASC");
        $stmt->execute([$billId]);
        return $stmt->fetchAll();
    } catch (Exception $e) {
        error_log("Get bill charges error: " . $e->getMessage());
        return [];
    }
}

/* ============================================
   FINANCE FUNCTIONS
   ============================================ */
function addFinanceTransaction($type, $category, $amount, $referenceId = null, $description = null) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("
            INSERT INTO hospital_finance 
            (transactionType, category, amount, referenceId, description, transactionDate, createdBy) 
            VALUES (?, ?, ?, ?, ?, NOW(), ?)
        ");
        $stmt->execute([$type, $category, $amount, $referenceId, $description, $_SESSION['user_id'] ?? null]);
        return $pdo->lastInsertId();
    } catch (Exception $e) {
        error_log("Finance transaction error: " . $e->getMessage());
        return false;
    }
}

function getTotalRevenue() {
    global $pdo;
    try {
        $stmt = $pdo->query("SELECT SUM(amount) FROM hospital_finance WHERE transactionType = 'revenue'");
        return $stmt->fetchColumn() ?? 0;
    } catch (Exception $e) {
        return 0;
    }
}

function getTotalExpenses() {
    global $pdo;
    try {
        $stmt = $pdo->query("SELECT SUM(amount) FROM hospital_finance WHERE transactionType = 'expense'");
        return $stmt->fetchColumn() ?? 0;
    } catch (Exception $e) {
        return 0;
    }
}

function getTotalSalariesPaid($dateFrom = null, $dateTo = null) {
    global $pdo;
    try {
        $query = "SELECT SUM(amount) FROM salary_payments WHERE status = 'paid'";
        $params = [];
        
        if ($dateFrom && $dateTo) {
            $query .= " AND DATE(paymentDate) BETWEEN ? AND ?";
            $params = [$dateFrom, $dateTo];
        }
        
        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
        return $stmt->fetchColumn() ?? 0;
    } catch (Exception $e) {
        return 0;
    }
}

function getNetBalance() {
    return getTotalRevenue() - getTotalExpenses();
}

function processSalaryPayment($userId, $staffId, $role, $amount, $salaryMonth, $notes = null) {
    global $pdo;
    
    try {
        $pdo->beginTransaction();
        
        $stmt = $pdo->prepare("
            INSERT INTO salary_payments 
            (userId, staffId, role, amount, paymentDate, salaryMonth, paidBy, notes, status) 
            VALUES (?, ?, ?, ?, NOW(), ?, ?, ?, 'paid')
        ");
        $stmt->execute([$userId, $staffId, $role, $amount, $salaryMonth, $_SESSION['user_id'], $notes]);
        $salaryId = $pdo->lastInsertId();
        
        addFinanceTransaction('expense', 'salary', $amount, $salaryId, "Salary payment - {$role} - {$salaryMonth}");
        
        $pdo->commit();
        logAction($_SESSION['user_id'], 'PROCESS_SALARY', "Processed salary for {$role} ID: {$userId}, Amount: {$amount}");
        return $salaryId;
        
    } catch (Exception $e) {
        $pdo->rollBack();
        error_log("Process salary error: " . $e->getMessage());
        return false;
    }
}

function getStaffSalaryConfig($staffId) {
    global $pdo;
    try {
        $stmt = $pdo->prepare("
            SELECT * FROM staff_salary_config 
            WHERE staffId = ? AND (effectiveTo IS NULL OR effectiveTo >= CURDATE())
            ORDER BY effectiveFrom DESC LIMIT 1
        ");
        $stmt->execute([$staffId]);
        return $stmt->fetch();
    } catch (Exception $e) {
        return null;
    }
}

function saveStaffSalaryConfig($staffId, $baseSalary, $effectiveFrom, $notes = null) {
    global $pdo;
    try {
        $stmt = $pdo->prepare("
            INSERT INTO staff_salary_config (staffId, baseSalary, effectiveFrom, notes) 
            VALUES (?, ?, ?, ?)
        ");
        $stmt->execute([$staffId, $baseSalary, $effectiveFrom, $notes]);
        return $pdo->lastInsertId();
    } catch (Exception $e) {
        error_log("Save salary config error: " . $e->getMessage());
        return false;
    }
}

/* ============================================
   EMAIL NOTIFICATION FUNCTIONS
   ============================================ */
function sendAppointmentConfirmationEmail($appointmentId) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("
            SELECT a.*, 
                   CONCAT(pu.firstName, ' ', pu.lastName) as patientName,
                   pu.email as patientEmail,
                   CONCAT(du.firstName, ' ', du.lastName) as doctorName,
                   d.specialization
            FROM appointments a
            JOIN patients p ON a.patientId = p.patientId
            JOIN users pu ON p.userId = pu.userId
            JOIN doctors d ON a.doctorId = d.doctorId
            JOIN staff s ON d.staffId = s.staffId
            JOIN users du ON s.userId = du.userId
            WHERE a.appointmentId = ?
        ");
        $stmt->execute([$appointmentId]);
        $appt = $stmt->fetch();
        
        if (!$appt) return false;
        
        $subject = "Appointment Confirmation - " . SITE_NAME;
        $dateTime = date('l, F j, Y \a\t g:i A', strtotime($appt['dateTime']));
        
        $message = "
            <!DOCTYPE html>
            <html>
            <head><style>body{font-family:Arial,sans-serif;}.container{max-width:600px;margin:0 auto;padding:20px;}.header{background:#1a75bc;color:white;padding:30px;text-align:center;border-radius:10px 10px 0 0;}.content{background:#f9f9f9;padding:30px;border-radius:0 0 10px 10px;}.button{display:inline-block;background:#1a75bc;color:white;padding:12px 30px;text-decoration:none;border-radius:5px;}</style></head>
            <body><div class='container'><div class='header'><h2>Appointment Confirmed</h2></div>
            <div class='content'><p>Dear <strong>{$appt['patientName']}</strong>,</p>
            <p>Your appointment has been successfully booked with Dr. {$appt['doctorName']} ({$appt['specialization']}) on {$dateTime}.</p>
            <p>Please arrive 15 minutes before your appointment time.</p>
            <a href='" . SITE_URL . "/patient/appointments.php' class='button'>View My Appointments</a></div></div></body></html>";
        
        return sendEmail($appt['patientEmail'], $subject, $message);
    } catch (Exception $e) {
        error_log("Appointment confirmation email error: " . $e->getMessage());
        return false;
    }
}

function sendAppointmentCancellationEmail($appointmentId, $reason = null) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("
            SELECT a.*, 
                   CONCAT(pu.firstName, ' ', pu.lastName) as patientName,
                   pu.email as patientEmail,
                   CONCAT(du.firstName, ' ', du.lastName) as doctorName
            FROM appointments a
            JOIN patients p ON a.patientId = p.patientId
            JOIN users pu ON p.userId = pu.userId
            JOIN doctors d ON a.doctorId = d.doctorId
            JOIN staff s ON d.staffId = s.staffId
            JOIN users du ON s.userId = du.userId
            WHERE a.appointmentId = ?
        ");
        $stmt->execute([$appointmentId]);
        $appt = $stmt->fetch();
        
        if (!$appt) return false;
        
        $subject = "Appointment Cancelled - " . SITE_NAME;
        $dateTime = date('l, F j, Y \a\t g:i A', strtotime($appt['dateTime']));
        
        $message = "
            <!DOCTYPE html>
            <html>
            <head><style>body{font-family:Arial,sans-serif;}.container{max-width:600px;margin:0 auto;padding:20px;}.header{background:#dc3545;color:white;padding:30px;text-align:center;border-radius:10px 10px 0 0;}.content{background:#f9f9f9;padding:30px;border-radius:0 0 10px 10px;}.button{display:inline-block;background:#1a75bc;color:white;padding:12px 30px;text-decoration:none;border-radius:5px;}</style></head>
            <body><div class='container'><div class='header'><h2>Appointment Cancelled</h2></div>
            <div class='content'><p>Dear <strong>{$appt['patientName']}</strong>,</p>
            <p>Your appointment with Dr. {$appt['doctorName']} scheduled for {$dateTime} has been cancelled.</p>"
            . ($reason ? "<p><strong>Reason:</strong> {$reason}</p>" : "") .
            "<p>To reschedule, please book a new appointment.</p>
            <a href='" . SITE_URL . "/patient/appointments.php' class='button'>Book New Appointment</a></div></div></body></html>";
        
        return sendEmail($appt['patientEmail'], $subject, $message);
    } catch (Exception $e) {
        error_log("Appointment cancellation email error: " . $e->getMessage());
        return false;
    }
}

/**
 * Send appointment reschedule confirmation email
 */
function sendAppointmentRescheduleEmail($appointmentId, $oldDateTime, $newDateTime) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("
            SELECT a.*, 
                   CONCAT(pu.firstName, ' ', pu.lastName) as patientName,
                   pu.email as patientEmail,
                   CONCAT(du.firstName, ' ', du.lastName) as doctorName,
                   d.specialization
            FROM appointments a
            JOIN patients p ON a.patientId = p.patientId
            JOIN users pu ON p.userId = pu.userId
            JOIN doctors d ON a.doctorId = d.doctorId
            JOIN staff s ON d.staffId = s.staffId
            JOIN users du ON s.userId = du.userId
            WHERE a.appointmentId = ?
        ");
        $stmt->execute([$appointmentId]);
        $appt = $stmt->fetch();
        
        if (!$appt) return false;
        
        $subject = "Appointment Rescheduled - " . SITE_NAME;
        $oldDateFormatted = date('l, F j, Y \a\t g:i A', strtotime($oldDateTime));
        $newDateFormatted = date('l, F j, Y \a\t g:i A', strtotime($newDateTime));
        
        $message = "
            <!DOCTYPE html>
            <html>
            <head>
                <style>
                    body { font-family: Arial, sans-serif; line-height: 1.6; }
                    .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                    .header { background: #1e3a5f; color: white; padding: 30px; text-align: center; border-radius: 10px 10px 0 0; }
                    .content { background: #f9f9f9; padding: 30px; border-radius: 0 0 10px 10px; }
                    .info-box { background: white; padding: 20px; border-radius: 8px; margin: 20px 0; border: 1px solid #e2e8f0; }
                    .old { color: #ef4444; text-decoration: line-through; }
                    .new { color: #10b981; font-weight: bold; }
                    .button { display: inline-block; background: #1e3a5f; color: white; padding: 12px 30px; text-decoration: none; border-radius: 5px; margin-top: 20px; }
                </style>
            </head>
            <body>
                <div class='container'>
                    <div class='header'><h2>Appointment Rescheduled</h2></div>
                    <div class='content'>
                        <p>Dear <strong>{$appt['patientName']}</strong>,</p>
                        <p>Your appointment with Dr. {$appt['doctorName']} ({$appt['specialization']}) has been rescheduled.</p>
                        <div class='info-box'>
                            <p><span class='old'><i class='fas fa-calendar-times'></i> Previous: {$oldDateFormatted}</span></p>
                            <p><span class='new'><i class='fas fa-calendar-check'></i> New: {$newDateFormatted}</span></p>
                        </div>
                        <p>Please arrive 15 minutes before your appointment time.</p>
                        <p>If you need to reschedule again, please contact us or use the online portal.</p>
                        <a href='" . SITE_URL . "/patient/view-appointments.php' class='button'>View My Appointments</a>
                        <p style='margin-top: 20px;'>Thank you for choosing HealthManagement System.</p>
                    </div>
                </div>
            </body>
            </html>";
        
        return sendEmail($appt['patientEmail'], $subject, $message);
    } catch (Exception $e) {
        error_log("Reschedule email error: " . $e->getMessage());
        return false;
    }
}

function sendBillGeneratedEmail($billId) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("
            SELECT b.*, 
                   CONCAT(u.firstName, ' ', u.lastName) as patientName,
                   u.email as patientEmail
            FROM bills b
            JOIN patients p ON b.patientId = p.patientId
            JOIN users u ON p.userId = u.userId
            WHERE b.billId = ?
        ");
        $stmt->execute([$billId]);
        $bill = $stmt->fetch();
        
        if (!$bill) return false;
        
        $subject = "New Bill Generated - " . SITE_NAME;
        
        $message = "
            <!DOCTYPE html>
            <html>
            <head><style>body{font-family:Arial,sans-serif;}.container{max-width:600px;margin:0 auto;padding:20px;}.header{background:#1a75bc;color:white;padding:30px;text-align:center;border-radius:10px 10px 0 0;}.content{background:#f9f9f9;padding:30px;border-radius:0 0 10px 10px;}.amount{font-size:32px;color:#1a75bc;font-weight:bold;text-align:center;}.button{display:inline-block;background:#28a745;color:white;padding:12px 30px;text-decoration:none;border-radius:5px;}</style></head>
            <body><div class='container'><div class='header'><h2>New Bill Generated</h2></div>
            <div class='content'><p>Dear <strong>{$bill['patientName']}</strong>,</p>
            <p>A new bill has been generated for your recent consultation.</p>
            <div class='amount'>$" . number_format($bill['totalAmount'], 2) . "</div>
            <p><strong>Bill #:</strong> " . str_pad($billId, 6, '0', STR_PAD_LEFT) . "</p>
            <p><strong>Date:</strong> " . date('F j, Y', strtotime($bill['generatedAt'])) . "</p>
            <a href='" . SITE_URL . "/patient/view-bill.php?bill_id={$billId}' class='button'>View & Pay Bill</a></div></div></body></html>";
        
        return sendEmail($bill['patientEmail'], $subject, $message);
    } catch (Exception $e) {
        error_log("Bill generated email error: " . $e->getMessage());
        return false;
    }
}

function sendPaymentConfirmationEmail($billId) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("
            SELECT b.*, 
                   CONCAT(u.firstName, ' ', u.lastName) as patientName,
                   u.email as patientEmail
            FROM bills b
            JOIN patients p ON b.patientId = p.patientId
            JOIN users u ON p.userId = u.userId
            WHERE b.billId = ? AND b.status = 'paid'
        ");
        $stmt->execute([$billId]);
        $bill = $stmt->fetch();
        
        if (!$bill) return false;
        
        $subject = "Payment Confirmed - " . SITE_NAME;
        
        $message = "
            <!DOCTYPE html>
            <html>
            <head><style>body{font-family:Arial,sans-serif;}.container{max-width:600px;margin:0 auto;padding:20px;}.header{background:#28a745;color:white;padding:30px;text-align:center;border-radius:10px 10px 0 0;}.content{background:#f9f9f9;padding:30px;border-radius:0 0 10px 10px;}.amount{font-size:32px;color:#28a745;font-weight:bold;text-align:center;}</style></head>
            <body><div class='container'><div class='header'><h2>Payment Confirmed</h2></div>
            <div class='content'><p>Dear <strong>{$bill['patientName']}</strong>,</p>
            <p>Your payment has been received successfully.</p>
            <div class='amount'>$" . number_format($bill['totalAmount'], 2) . "</div>
            <p><strong>Bill #:</strong> " . str_pad($billId, 6, '0', STR_PAD_LEFT) . "</p>
            <p><strong>Paid On:</strong> " . date('F j, Y g:i A', strtotime($bill['paidAt'])) . "</p>
            <p>Thank you for your payment!</p></div></div></body></html>";
        
        return sendEmail($bill['patientEmail'], $subject, $message);
    } catch (Exception $e) {
        error_log("Payment confirmation email error: " . $e->getMessage());
        return false;
    }
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

function updateUserRole($userId, $newRole) {
    global $pdo;
    
    $validRoles = ['patient', 'staff', 'nurse', 'doctor', 'admin', 'accountant'];
    if (!in_array($newRole, $validRoles)) {
        error_log("Invalid role attempted in updateUserRole: " . $newRole);
        return false;
    }
    
    try {
        $pdo->beginTransaction();
        
        $stmt = $pdo->prepare("UPDATE users SET role = ? WHERE userId = ?");
        $stmt->execute([$newRole, $userId]);
        
        if ($newRole !== 'patient') {
            $stmt = $pdo->prepare("SELECT staffId FROM staff WHERE userId = ?");
            $stmt->execute([$userId]);
            $staff = $stmt->fetch();
            
            if (!$staff) {
                $stmt = $pdo->prepare("
                    INSERT INTO staff (userId, licenseNumber, hireDate, department, position, createdAt, updatedAt) 
                    VALUES (?, '', CURDATE(), '', ?, NOW(), NOW())
                ");
                $stmt->execute([$userId, ucfirst($newRole)]);
                $staffId = $pdo->lastInsertId();
                
                if ($newRole === 'doctor') {
                    $stmt = $pdo->prepare("
                        INSERT INTO doctors (staffId, specialization, consultationFee, isAvailable) 
                        VALUES (?, 'General', 100.00, 1)
                    ");
                    $stmt->execute([$staffId]);
                } elseif ($newRole === 'nurse') {
                    $stmt = $pdo->prepare("
                        INSERT INTO nurses (staffId, nursingSpecialty) 
                        VALUES (?, 'General')
                    ");
                    $stmt->execute([$staffId]);
                } elseif ($newRole === 'admin') {
                    $stmt = $pdo->prepare("
                        INSERT INTO administrators (staffId, adminLevel, permissions) 
                        VALUES (?, 'regular', '{\"all\":true}')
                    ");
                    $stmt->execute([$staffId]);
                }
            }
        }
        
        $pdo->commit();
        logAction($_SESSION['user_id'] ?? null, 'UPDATE_USER_ROLE', "Changed user $userId role to $newRole");
        return true;
        
    } catch (Exception $e) {
        $pdo->rollBack();
        error_log("Update user role error: " . $e->getMessage());
        return false;
    }
}

/* ============================================
   FORMATTING FUNCTIONS
   ============================================ */
function formatDate($date) { 
    if (!$date) return 'N/A';
    return date('F j, Y', strtotime($date)); 
}

function formatDateTime($datetime) { 
    if (!$datetime) return 'N/A';
    return date('F j, Y g:i A', strtotime($datetime)); 
}

function calculateAge($birthDate) {
    if (!$birthDate) return 'N/A';
    $birthDate = new DateTime($birthDate);
    $today = new DateTime('today');
    return $birthDate->diff($today)->y;
}

function generateVerificationCode() { 
    return bin2hex(random_bytes(32)); 
}

/* ============================================
   VALIDATION FUNCTIONS
   ============================================ */
function isValidRole($role) {
    $validRoles = ['patient', 'staff', 'nurse', 'doctor', 'admin', 'accountant'];
    return in_array($role, $validRoles);
}

function getValidRoles() {
    return ['patient', 'staff', 'nurse', 'doctor', 'admin', 'accountant'];
}

/* ============================================
   DOCTOR AVAILABILITY FUNCTIONS
   ============================================ */
function isTimeSlotAvailable($doctorId, $date, $time) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("
            SELECT isAvailable, isDayOff, startTime, endTime
            FROM doctor_availability 
            WHERE doctorId = ? AND availabilityDate = ?
        ");
        $stmt->execute([$doctorId, $date]);
        $availability = $stmt->fetch();
        
        if (!$availability || $availability['isDayOff'] || !$availability['isAvailable']) {
            return ['available' => false, 'reason' => 'Doctor not available on this day'];
        }
        
        if ($time < $availability['startTime'] || $time > $availability['endTime']) {
            return ['available' => false, 'reason' => 'Time outside working hours'];
        }
        
        $stmt = $pdo->prepare("
            SELECT COUNT(*) FROM appointments 
            WHERE doctorId = ? AND DATE(dateTime) = ? AND TIME(dateTime) = ?
            AND status NOT IN ('cancelled', 'no-show')
        ");
        $stmt->execute([$doctorId, $date, $time]);
        
        if ($stmt->fetchColumn() > 0) {
            return ['available' => false, 'reason' => 'Time slot already booked'];
        }
        
        return ['available' => true];
        
    } catch (Exception $e) {
        error_log("Slot availability check error: " . $e->getMessage());
        return ['available' => false, 'reason' => 'System error'];
    }
}

function getDoctorAvailableDates($doctorId, $days = 30) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("
            SELECT availabilityDate, startTime, endTime
            FROM doctor_availability 
            WHERE doctorId = ? 
            AND availabilityDate BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL ? DAY)
            AND isAvailable = 1 
            AND isDayOff = 0
            ORDER BY availabilityDate
        ");
        $stmt->execute([$doctorId, $days]);
        return $stmt->fetchAll();
    } catch (Exception $e) {
        error_log("Get available dates error: " . $e->getMessage());
        return [];
    }
}

/* ============================================ */
/* MEDICAL CERTIFICATE FUNCTIONS                */
/* ============================================ */

// Include the PDF generation function
require_once __DIR__ . '/../ajax/generate-medical-certificate-pdf.php';
?>