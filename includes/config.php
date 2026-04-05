<?php
// Database configuration
define('DB_HOST', 'localhost');
define('DB_NAME', 'healthmanagement');
define('DB_USER', 'root');
define('DB_PASS', '');

// SMTP Configuration for Gmail
define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_PORT', 587);
define('SMTP_USERNAME', 'himalkumarkari@gmail.com');
define('SMTP_PASSWORD', 'zzzy qqwx dawy itxv');
define('SMTP_FROM', 'himalkumarkari@gmail.com');
define('SMTP_FROM_NAME', 'Healthcare System');

// Site configuration
define('SITE_URL', 'http://localhost/healthcare');
define('SITE_NAME', 'HealthManagement');

// Verification settings
define('VERIFICATION_REQUIRED', true);

// Appointment Settings
define('APPOINTMENT_DURATION', 30);
define('MAX_APPOINTMENTS_PER_DAY', 10);
define('WORKING_HOURS_START', '09:00');
define('WORKING_HOURS_END', '17:00');
define('BREAK_START', '13:00');
define('BREAK_END', '14:00');

// Error Reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../logs/error.log');

// Start session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Database connection
try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $pdo->exec("SET NAMES utf8mb4");
} catch(PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}

// Include required files
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/auth.php';
?>