<?php
require_once '../includes/config.php';
require_once '../includes/auth.php';
checkRole('admin');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $_SESSION['error'] = "Invalid request method.";
    header("Location: staff.php");
    exit();
}

// Get form data
$username = trim($_POST['username'] ?? '');
$email = trim($_POST['email'] ?? '');
$password = $_POST['password'] ?? '';
$confirmPassword = $_POST['confirm_password'] ?? '';
$firstName = trim($_POST['first_name'] ?? '');
$lastName = trim($_POST['last_name'] ?? '');
$phoneNumber = trim($_POST['phone_number'] ?? '');
$staffRole = $_POST['staff_role'] ?? 'staff';
$licenseNumber = trim($_POST['license_number'] ?? '');
$hireDate = $_POST['hire_date'] ?? date('Y-m-d');
$department = trim($_POST['department'] ?? '');
$position = trim($_POST['position'] ?? '');
$salary = floatval($_POST['salary'] ?? 2500.00);

// Validation
$errors = [];
$validRoles = ['staff', 'nurse', 'doctor', 'admin', 'accountant'];

if (empty($username)) $errors[] = "Username is required.";
if (empty($email)) $errors[] = "Email is required.";
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = "Invalid email format.";
if (empty($password)) $errors[] = "Password is required.";
if (strlen($password) < 8) $errors[] = "Password must be at least 8 characters.";
if ($password !== $confirmPassword) $errors[] = "Passwords do not match.";
if (empty($firstName)) $errors[] = "First name is required.";
if (empty($lastName)) $errors[] = "Last name is required.";
if (!in_array($staffRole, $validRoles)) $errors[] = "Invalid staff role: " . $staffRole;
if ($salary <= 0) $errors[] = "Salary must be greater than zero.";

if (!empty($errors)) {
    $_SESSION['error'] = implode(' ', $errors);
    $_SESSION['form_data'] = $_POST;
    header("Location: staff.php");
    exit();
}

try {
    $pdo->beginTransaction();
    
    // Check if username or email already exists
    $stmt = $pdo->prepare("SELECT userId FROM users WHERE username = ? OR email = ?");
    $stmt->execute([$username, $email]);
    
    if ($stmt->fetch()) {
        throw new Exception("Username or email already exists.");
    }
    
    // Hash password
    $passwordHash = password_hash($password, PASSWORD_DEFAULT);
    
    // Insert user
    $stmt = $pdo->prepare("
        INSERT INTO users (username, passwordHash, email, firstName, lastName, phoneNumber, role, isVerified, dateCreated) 
        VALUES (?, ?, ?, ?, ?, ?, ?, 1, NOW())
    ");
    $stmt->execute([$username, $passwordHash, $email, $firstName, $lastName, $phoneNumber, $staffRole]);
    $userId = $pdo->lastInsertId();
    
    // Insert into staff table WITH SALARY
    $stmt = $pdo->prepare("
        INSERT INTO staff (userId, licenseNumber, hireDate, department, position, salary, createdAt, updatedAt) 
        VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW())
    ");
    $stmt->execute([$userId, $licenseNumber, $hireDate, $department, $position, $salary]);
    $staffId = $pdo->lastInsertId();
    
    // Role-specific inserts
    if ($staffRole === 'doctor') {
        $specialization = trim($_POST['specialization'] ?? 'General');
        $consultationFee = floatval($_POST['consultation_fee'] ?? 100);
        $yearsOfExperience = intval($_POST['years_of_experience'] ?? 0);
        $education = trim($_POST['education'] ?? '');
        $biography = trim($_POST['biography'] ?? '');
        
        $stmt = $pdo->prepare("
            INSERT INTO doctors (staffId, specialization, consultationFee, yearsOfExperience, education, biography, isAvailable) 
            VALUES (?, ?, ?, ?, ?, ?, 1)
        ");
        $stmt->execute([$staffId, $specialization, $consultationFee, $yearsOfExperience, $education, $biography]);
        
    } elseif ($staffRole === 'nurse') {
        $nursingSpecialty = trim($_POST['nursing_specialty'] ?? 'General');
        $certification = trim($_POST['certification'] ?? '');
        
        $stmt = $pdo->prepare("
            INSERT INTO nurses (staffId, nursingSpecialty, certification) 
            VALUES (?, ?, ?)
        ");
        $stmt->execute([$staffId, $nursingSpecialty, $certification]);
        
    } elseif ($staffRole === 'admin') {
        $adminLevel = $_POST['admin_level'] ?? 'regular';
        $permissions = trim($_POST['permissions'] ?? '');
        
        $stmt = $pdo->prepare("
            INSERT INTO administrators (staffId, adminLevel, permissions) 
            VALUES (?, ?, ?)
        ");
        $stmt->execute([$staffId, $adminLevel, $permissions]);
        
    } elseif ($staffRole === 'accountant') {
        $qualification = trim($_POST['qualification'] ?? '');
        $certification = trim($_POST['accountant_certification'] ?? '');
        $specialization = trim($_POST['accountant_specialization'] ?? 'General Accounting');
        $yearsOfExperience = intval($_POST['accountant_experience'] ?? 0);
        
        $stmt = $pdo->prepare("
            INSERT INTO accountants (staffId, qualification, certification, specialization, yearsOfExperience) 
            VALUES (?, ?, ?, ?, ?)
        ");
        $stmt->execute([$staffId, $qualification, $certification, $specialization, $yearsOfExperience]);
    }
    
    $pdo->commit();
    
    $roleDisplayNames = [
        'staff' => 'Support Staff',
        'nurse' => 'Nurse',
        'doctor' => 'Doctor',
        'admin' => 'Administrator',
        'accountant' => 'Accountant'
    ];
    
    $displayRole = $roleDisplayNames[$staffRole] ?? ucfirst($staffRole);
    
    $_SESSION['success'] = "{$displayRole} '{$firstName} {$lastName}' created successfully! Salary: $" . number_format($salary, 2);
    logAction($_SESSION['user_id'], 'CREATE_STAFF', "Created {$staffRole}: {$username} (User ID: {$userId}) with salary: {$salary}");
    
    unset($_SESSION['form_data']);
    
} catch (Exception $e) {
    $pdo->rollBack();
    $_SESSION['error'] = "Failed to create staff: " . $e->getMessage();
    $_SESSION['form_data'] = $_POST;
    error_log("Create staff error: " . $e->getMessage());
}

header("Location: staff.php");
exit();
?>