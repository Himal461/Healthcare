<?php
function isLoggedIn() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

function checkAuth() {
    if (!isLoggedIn()) {
        $_SESSION['redirect_url'] = $_SERVER['REQUEST_URI'];
        redirect('login.php');
    }
}

/**
 * Check if current user has the required role
 * @param string|array $requiredRole Single role or array of allowed roles
 */
function checkRole($requiredRole) {
    checkAuth();
    
    $userRole = $_SESSION['user_role'] ?? '';
    
    // Debug log
    error_log("checkRole: User role = '$userRole', Required = " . print_r($requiredRole, true));
    
    // Admin has access to everything
    if ($userRole === 'admin') {
        return true;
    }
    
    $allowedRoles = is_array($requiredRole) ? $requiredRole : [$requiredRole];
    
    // Check if user's role is in allowed roles
    if (in_array($userRole, $allowedRoles)) {
        return true;
    }
    
    $_SESSION['error'] = "You don't have permission to access this page. Your role: {$userRole}";
    redirect('dashboard.php');
}

function checkAnyRole($roles) {
    return checkRole($roles);
}

function loginUser($userId, $username, $role) {
    global $pdo;
    
    session_regenerate_id(true);
    
    $_SESSION['user_id'] = $userId;
    $_SESSION['username'] = $username;
    $_SESSION['user_role'] = $role;
    $_SESSION['login_time'] = time();
    $_SESSION['ip_address'] = $_SERVER['REMOTE_ADDR'];
    $_SESSION['user_agent'] = $_SERVER['HTTP_USER_AGENT'];
    
    try {
        $stmt = $pdo->prepare("UPDATE users SET lastLogin = NOW() WHERE userId = ?");
        $stmt->execute([$userId]);
    } catch (Exception $e) {
        error_log("Failed to update last login: " . $e->getMessage());
    }
    
    logAction($userId, 'LOGIN', "User logged in successfully as {$role}");
}

function redirect($url) {
    // Remove any leading slashes and SITE_URL from the URL
    $url = ltrim($url, '/');
    $fullUrl = SITE_URL . '/' . $url;
    header("Location: " . $fullUrl);
    exit();
}

function hasBill($appointmentId) {
    global $pdo;
    try {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM bills WHERE appointmentId = ?");
        $stmt->execute([$appointmentId]);
        return $stmt->fetchColumn() > 0;
    } catch (Exception $e) {
        return false;
    }
}

function isAccountant() {
    return isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'accountant';
}

function canViewFinance() {
    $role = $_SESSION['user_role'] ?? '';
    return in_array($role, ['admin', 'accountant']);
}

function canProcessSalary() {
    $role = $_SESSION['user_role'] ?? '';
    return in_array($role, ['admin', 'accountant']);
}