<?php
require_once '../includes/config.php';
require_once '../includes/auth.php';
checkRole('accountant');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $_SESSION['error'] = "Invalid request method.";
    header("Location: salaries.php");
    exit();
}

$staffId = (int)($_POST['staffId'] ?? 0);
$baseSalary = floatval($_POST['baseSalary'] ?? 0);
$effectiveFrom = $_POST['effectiveFrom'] ?? date('Y-m-d');
$notes = trim($_POST['notes'] ?? '');

// Validation
$errors = [];

if (!$staffId) {
    $errors[] = "Invalid staff ID.";
}

if ($baseSalary <= 0) {
    $errors[] = "Base salary must be greater than zero.";
}

if (!strtotime($effectiveFrom)) {
    $errors[] = "Invalid effective date.";
}

if (!empty($errors)) {
    $_SESSION['error'] = implode(' ', $errors);
    header("Location: salaries.php");
    exit();
}

// Save salary configuration
$result = saveStaffSalaryConfig($staffId, $baseSalary, $effectiveFrom, $notes);

if ($result) {
    // Get staff name
    $stmt = $pdo->prepare("
        SELECT CONCAT(u.firstName, ' ', u.lastName) as name 
        FROM staff s 
        JOIN users u ON s.userId = u.userId 
        WHERE s.staffId = ?
    ");
    $stmt->execute([$staffId]);
    $staff = $stmt->fetch();
    
    $_SESSION['success'] = "Salary configuration for " . ($staff['name'] ?? 'staff member') . " updated successfully!";
    logAction($_SESSION['user_id'], 'UPDATE_SALARY_CONFIG', "Updated salary config for staff ID: $staffId to $baseSalary");
} else {
    $_SESSION['error'] = "Failed to update salary configuration.";
}

header("Location: salaries.php");
exit();