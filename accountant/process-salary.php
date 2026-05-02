<?php
require_once '../includes/config.php';
require_once '../includes/auth.php';
checkRole('accountant');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $_SESSION['error'] = "Invalid request method.";
    header("Location: salaries.php");
    exit();
}

$userId = (int)($_POST['userId'] ?? 0);
$staffId = (int)($_POST['staffId'] ?? 0);
$role = $_POST['role'] ?? '';
$amount = floatval($_POST['amount'] ?? 0);
$salaryMonth = $_POST['salaryMonth'] ?? date('Y-m');
$notes = trim($_POST['notes'] ?? '');

// Validation
$errors = [];

if (!$userId) $errors[] = "Invalid user ID.";
if (!$staffId) $errors[] = "Invalid staff ID.";
if (!in_array($role, ['doctor', 'nurse', 'staff', 'accountant', 'admin'])) $errors[] = "Invalid role.";
if ($amount <= 0) $errors[] = "Amount must be greater than zero.";

// Check if already paid
$checkStmt = $pdo->prepare("SELECT salaryId FROM salary_payments WHERE userId = ? AND salaryMonth = ? AND status = 'paid'");
$checkStmt->execute([$userId, $salaryMonth]);
if ($checkStmt->fetch()) {
    $errors[] = "Salary already paid for this month.";
}

if (!empty($errors)) {
    $_SESSION['error'] = implode(' ', $errors);
    header("Location: salaries.php");
    exit();
}

// Process payment
$result = processSalaryPayment($userId, $staffId, $role, $amount, $salaryMonth, $notes);

if ($result) {
    // Get employee details for email
    $userStmt = $pdo->prepare("SELECT firstName, lastName, email FROM users WHERE userId = ?");
    $userStmt->execute([$userId]);
    $employee = $userStmt->fetch();
    
    if ($employee && $employee['email']) {
        // Send salary payment email
        $subject = "Salary Payment Confirmation - " . SITE_NAME;
        $monthName = date('F Y', strtotime($salaryMonth));
        
        $message = "
            <!DOCTYPE html>
            <html>
            <head>
                <style>
                    body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                    .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                    .header { background: #10b981; color: white; padding: 30px; text-align: center; border-radius: 10px 10px 0 0; }
                    .content { background: #f9f9f9; padding: 30px; border-radius: 0 0 10px 10px; }
                    .amount { font-size: 36px; color: #10b981; font-weight: bold; text-align: center; margin: 20px 0; }
                    .details { background: white; padding: 20px; border-radius: 8px; margin: 20px 0; }
                    .footer { text-align: center; color: #666; font-size: 12px; margin-top: 30px; }
                </style>
            </head>
            <body>
                <div class='container'>
                    <div class='header'>
                        <h2>Salary Payment Confirmed</h2>
                    </div>
                    <div class='content'>
                        <p>Dear <strong>{$employee['firstName']} {$employee['lastName']}</strong>,</p>
                        <p>Your salary for <strong>{$monthName}</strong> has been processed and credited to your account.</p>
                        <div class='amount'>$" . number_format($amount, 2) . "</div>
                        <div class='details'>
                            <p><strong>Employee:</strong> {$employee['firstName']} {$employee['lastName']}</p>
                            <p><strong>Role:</strong> " . ucfirst($role) . "</p>
                            <p><strong>Salary Month:</strong> {$monthName}</p>
                            <p><strong>Payment Date:</strong> " . date('F j, Y') . "</p>
                        </div>
                        <p>Thank you for your continued service at " . SITE_NAME . ".</p>
                        <div class='footer'>
                            <p>This is an automated message from " . SITE_NAME . " Finance Department.</p>
                            <p>For any queries, please contact the administration.</p>
                        </div>
                    </div>
                </div>
            </body>
            </html>
        ";
        
        sendEmail($employee['email'], $subject, $message);
        
        // Create in-app notification
        createNotification(
            $userId,
            'salary',
            'Salary Payment Received',
            "Your salary of $" . number_format($amount, 2) . " for {$monthName} has been processed."
        );
    }
    
    $_SESSION['success'] = "Salary payment of $" . number_format($amount, 2) . " processed successfully! Email notification sent.";
} else {
    $_SESSION['error'] = "Failed to process salary payment. Please try again.";
}

header("Location: salaries.php?month=" . $salaryMonth);
exit();