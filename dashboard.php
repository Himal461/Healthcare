<?php
require_once 'includes/config.php';
require_once 'includes/auth.php';

// Redirect based on role
if (isLoggedIn()) {
    if ($_SESSION['user_role'] === 'admin') {
        redirect('admin/dashboard.php');
    } elseif ($_SESSION['user_role'] === 'doctor') {
        redirect('doctor/dashboard.php');
    } elseif ($_SESSION['user_role'] === 'nurse') {
        redirect('nurse/dashboard.php');
    } elseif ($_SESSION['user_role'] === 'staff') {
        redirect('staff/dashboard.php');
    } elseif ($_SESSION['user_role'] === 'patient') {
        redirect('patient/dashboard.php');
    }
}

// If not logged in, redirect to login
redirect('login.php');
?>