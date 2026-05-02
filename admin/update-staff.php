<?php
require_once '../includes/config.php';
require_once '../includes/auth.php';
checkRole('admin');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $_SESSION['error'] = "Invalid request method.";
    header("Location: staff.php");
    exit();
}

$staffId = (int)($_POST['staff_id'] ?? 0);
$userId = (int)($_POST['user_id'] ?? 0);
$firstName = trim($_POST['first_name'] ?? '');
$lastName = trim($_POST['last_name'] ?? '');
$email = trim($_POST['email'] ?? '');
$phoneNumber = trim($_POST['phone_number'] ?? '');
$licenseNumber = trim($_POST['license_number'] ?? '');
$department = trim($_POST['department'] ?? '');
$position = trim($_POST['position'] ?? '');
$staffRole = $_POST['staff_role'] ?? '';
$salary = floatval($_POST['salary'] ?? 2500.00);

$errors = [];
$validRoles = ['staff', 'nurse', 'doctor', 'admin', 'accountant'];

if (!$staffId) $errors[] = "Invalid staff ID.";
if (!$userId) $errors[] = "Invalid user ID.";
if (empty($firstName)) $errors[] = "First name is required.";
if (empty($lastName)) $errors[] = "Last name is required.";
if (empty($email)) $errors[] = "Email is required.";
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = "Invalid email format.";
if (!in_array($staffRole, $validRoles)) $errors[] = "Invalid staff role: " . $staffRole;
if ($salary <= 0) $errors[] = "Salary must be greater than zero.";

if (!empty($errors)) {
    $_SESSION['error'] = implode(' ', $errors);
    header("Location: staff.php");
    exit();
}

try {
    $pdo->beginTransaction();
    
    // Check if email is already used by another user
    $stmt = $pdo->prepare("SELECT userId FROM users WHERE email = ? AND userId != ?");
    $stmt->execute([$email, $userId]);
    if ($stmt->fetch()) {
        throw new Exception("Email address is already used by another user.");
    }
    
    // Update users table
    $stmt = $pdo->prepare("
        UPDATE users 
        SET firstName = ?, lastName = ?, email = ?, phoneNumber = ?, role = ? 
        WHERE userId = ?
    ");
    $stmt->execute([$firstName, $lastName, $email, $phoneNumber, $staffRole, $userId]);
    
    // Update staff table WITH SALARY
    $stmt = $pdo->prepare("
        UPDATE staff 
        SET licenseNumber = ?, department = ?, position = ?, salary = ?, updatedAt = NOW() 
        WHERE staffId = ? AND userId = ?
    ");
    $stmt->execute([$licenseNumber, $department, $position, $salary, $staffId, $userId]);
    
    // Handle role-specific updates
    if ($staffRole === 'doctor') {
        $specialization = trim($_POST['specialization'] ?? '');
        $consultationFee = floatval($_POST['consultation_fee'] ?? 0);
        $yearsOfExperience = intval($_POST['years_of_experience'] ?? 0);
        $education = trim($_POST['education'] ?? '');
        $biography = trim($_POST['biography'] ?? '');
        $isAvailable = isset($_POST['is_available']) ? 1 : 0;
        
        $checkStmt = $pdo->prepare("SELECT doctorId FROM doctors WHERE staffId = ?");
        $checkStmt->execute([$staffId]);
        
        if ($checkStmt->fetch()) {
            $stmt = $pdo->prepare("
                UPDATE doctors 
                SET specialization = ?, consultationFee = ?, yearsOfExperience = ?, 
                    education = ?, biography = ?, isAvailable = ? 
                WHERE staffId = ?
            ");
            $stmt->execute([$specialization, $consultationFee, $yearsOfExperience, $education, $biography, $isAvailable, $staffId]);
        } else {
            $stmt = $pdo->prepare("
                INSERT INTO doctors (staffId, specialization, consultationFee, yearsOfExperience, education, biography, isAvailable) 
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([$staffId, $specialization, $consultationFee, $yearsOfExperience, $education, $biography, $isAvailable]);
        }
        
    } elseif ($staffRole === 'nurse') {
        $nursingSpecialty = trim($_POST['nursing_specialty'] ?? '');
        $certification = trim($_POST['certification'] ?? '');
        
        $checkStmt = $pdo->prepare("SELECT nurseId FROM nurses WHERE staffId = ?");
        $checkStmt->execute([$staffId]);
        
        if ($checkStmt->fetch()) {
            $stmt = $pdo->prepare("
                UPDATE nurses 
                SET nursingSpecialty = ?, certification = ? 
                WHERE staffId = ?
            ");
            $stmt->execute([$nursingSpecialty, $certification, $staffId]);
        } else {
            $stmt = $pdo->prepare("
                INSERT INTO nurses (staffId, nursingSpecialty, certification) 
                VALUES (?, ?, ?)
            ");
            $stmt->execute([$staffId, $nursingSpecialty, $certification]);
        }
        
    } elseif ($staffRole === 'accountant') {
        $qualification = trim($_POST['qualification'] ?? '');
        $certification = trim($_POST['accountant_certification'] ?? '');
        $specialization = trim($_POST['accountant_specialization'] ?? 'General Accounting');
        $yearsOfExperience = intval($_POST['accountant_experience'] ?? 0);
        
        $checkStmt = $pdo->prepare("SELECT accountantId FROM accountants WHERE staffId = ?");
        $checkStmt->execute([$staffId]);
        
        if ($checkStmt->fetch()) {
            $stmt = $pdo->prepare("
                UPDATE accountants 
                SET qualification = ?, certification = ?, specialization = ?, yearsOfExperience = ? 
                WHERE staffId = ?
            ");
            $stmt->execute([$qualification, $certification, $specialization, $yearsOfExperience, $staffId]);
        } else {
            $stmt = $pdo->prepare("
                INSERT INTO accountants (staffId, qualification, certification, specialization, yearsOfExperience) 
                VALUES (?, ?, ?, ?, ?)
            ");
            $stmt->execute([$staffId, $qualification, $certification, $specialization, $yearsOfExperience]);
        }
    }
    
    $pdo->commit();
    
    $_SESSION['success'] = "Staff member '{$firstName} {$lastName}' updated successfully!";
    logAction($_SESSION['user_id'], 'UPDATE_STAFF', "Updated staff ID: {$staffId}, Salary: {$salary}");
    
} catch (Exception $e) {
    $pdo->rollBack();
    $_SESSION['error'] = "Failed to update staff: " . $e->getMessage();
    error_log("Update staff error: " . $e->getMessage());
}

header("Location: staff.php");
exit();
?>