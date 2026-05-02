<?php
require_once 'includes/config.php';
require_once 'includes/auth.php';

if (!isLoggedIn()) {
    redirect('login.php');
}

$role = $_SESSION['user_role'] ?? '';

// Debug log
error_log("Dashboard redirect: User role = '$role'");

switch ($role) {
    case 'admin':
        header("Location: " . SITE_URL . "/admin/dashboard.php");
        exit();
    case 'doctor':
        header("Location: " . SITE_URL . "/doctor/dashboard.php");
        exit();
    case 'nurse':
        header("Location: " . SITE_URL . "/nurse/dashboard.php");
        exit();
    case 'staff':
        header("Location: " . SITE_URL . "/staff/dashboard.php");
        exit();
    case 'accountant':
        header("Location: " . SITE_URL . "/accountant/dashboard.php");
        exit();
    case 'patient':
        header("Location: " . SITE_URL . "/patient/dashboard.php");
        exit();
    default:
        // If role is unknown, log out and redirect to login
        session_destroy();
        header("Location: " . SITE_URL . "/login.php?error=invalid_role");
        exit();
}