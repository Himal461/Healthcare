<?php
function isLoggedIn() {
    // Check if session exists and user is logged in
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

function checkRole($requiredRole) {
    checkAuth();
    
    if (!hasPermission($requiredRole)) {
        $_SESSION['error'] = "You don't have permission to access this page.";
        redirect('dashboard.php');
    }
}

function loginUser($userId, $username, $role) {
    global $pdo;
    
    // Regenerate session ID to prevent session fixation
    session_regenerate_id(true);
    
    $_SESSION['user_id'] = $userId;
    $_SESSION['username'] = $username;
    $_SESSION['user_role'] = $role;
    $_SESSION['login_time'] = time();
    $_SESSION['ip_address'] = $_SERVER['REMOTE_ADDR'];
    $_SESSION['user_agent'] = $_SERVER['HTTP_USER_AGENT'];
    
    // Update last login
    try {
        $stmt = $pdo->prepare("UPDATE users SET lastLogin = NOW() WHERE userId = ?");
        $stmt->execute([$userId]);
    } catch (Exception $e) {
        error_log("Failed to update last login: " . $e->getMessage());
    }
    
    logAction($userId, 'LOGIN', "User logged in successfully");
}

function logoutUser() {
    // Store user ID before destroying session
    $userId = $_SESSION['user_id'] ?? null;
    
    if ($userId) {
        try {
            logAction($userId, 'LOGOUT', "User logged out");
        } catch (Exception $e) {
            error_log("Failed to log logout action: " . $e->getMessage());
        }
    }
    
    // Clear all session variables
    $_SESSION = array();
    
    // Delete the session cookie
    if (isset($_COOKIE[session_name()])) {
        setcookie(session_name(), '', time() - 3600, '/');
    }
    
    // Destroy the session
    session_destroy();
    
    // Clear remember me cookie
    if (isset($_COOKIE['remember_token'])) {
        setcookie('remember_token', '', time() - 3600, '/');
    }
    
    // Redirect to login page
    redirect('login.php');
}
?>