<?php
function isLoggedIn() {
    return isset($_SESSION['user_id']);
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
    
    $_SESSION['user_id'] = $userId;
    $_SESSION['username'] = $username;
    $_SESSION['user_role'] = $role;
    
    // Update last login
    $stmt = $pdo->prepare("UPDATE users SET lastLogin = NOW() WHERE userId = ?");
    $stmt->execute([$userId]);
    
    logAction($userId, 'LOGIN', "User logged in successfully");
}

function logoutUser() {
    if (isset($_SESSION['user_id'])) {
        logAction($_SESSION['user_id'], 'LOGOUT', "User logged out");
    }
    
    session_destroy();
    redirect('login.php');
}
?>