<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Fallback for getUnreadNotificationsCount if not defined
if (!function_exists('getUnreadNotificationsCount')) {
    function getUnreadNotificationsCount($userId) {
        return 0;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($pageTitle) ? $pageTitle : SITE_NAME; ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="<?php echo SITE_URL; ?>/css/style.css">
    <link rel="stylesheet" href="<?php echo SITE_URL; ?>/css/includes.css">
    <?php if (isset($extraCSS)) echo $extraCSS; ?>
    <?php if (isset($extraJS)) echo $extraJS; ?>
</head>
<body>
    <header class="includes-header">
        <div class="container">
            <div class="includes-top-bar">
                <a href="<?php echo SITE_URL; ?>/index.php" class="includes-logo">
                    <i class="fas fa-heartbeat"></i>
                    <span>HealthManagement</span>
                </a>
                <nav class="includes-nav">
                    <ul>
                        <?php if (isLoggedIn()): ?>
                            <li><a href="<?php echo SITE_URL; ?>/index.php"><i class="fas fa-home"></i> Home</a></li>
                            <li><a href="<?php echo SITE_URL; ?>/dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
                            
                            <?php if ($_SESSION['user_role'] === 'patient'): ?>
                                <li><a href="<?php echo SITE_URL; ?>/patient/appointments.php"><i class="fas fa-calendar-alt"></i> Appointments</a></li>
                                <li><a href="<?php echo SITE_URL; ?>/patient/my-prescriptions.php"><i class="fas fa-prescription"></i> Prescriptions</a></li>
                                <li><a href="<?php echo SITE_URL; ?>/patient/view-bills.php"><i class="fas fa-file-invoice"></i> Bills</a></li>
                                
                            <?php elseif ($_SESSION['user_role'] === 'doctor'): ?>
                                <li><a href="<?php echo SITE_URL; ?>/doctor/patients.php"><i class="fas fa-users"></i> My Patients</a></li>
                                <li><a href="<?php echo SITE_URL; ?>/doctor/prescriptions.php"><i class="fas fa-prescription"></i> Prescriptions</a></li>
                                <li><a href="<?php echo SITE_URL; ?>/doctor/availability.php"><i class="fas fa-clock"></i> Availability</a></li>
                                
                            <?php elseif ($_SESSION['user_role'] === 'nurse'): ?>
                                <li><a href="<?php echo SITE_URL; ?>/nurse/vitals.php"><i class="fas fa-heartbeat"></i> Vitals</a></li>
                                <li><a href="<?php echo SITE_URL; ?>/nurse/medical-records.php"><i class="fas fa-notes-medical"></i> Medical Records</a></li>
                                <li><a href="<?php echo SITE_URL; ?>/nurse/prescriptions.php"><i class="fas fa-prescription"></i> Prescriptions</a></li>
                                
                            <?php elseif ($_SESSION['user_role'] === 'staff'): ?>
                                <li><a href="<?php echo SITE_URL; ?>/staff/book-appointment.php"><i class="fas fa-calendar-plus"></i> Book Appointment</a></li>
                                <li><a href="<?php echo SITE_URL; ?>/staff/process-payment.php"><i class="fas fa-credit-card"></i> Process Payment</a></li>
                                
                            <?php elseif ($_SESSION['user_role'] === 'accountant'): ?>
                                <li><a href="<?php echo SITE_URL; ?>/accountant/finance.php"><i class="fas fa-chart-pie"></i> Finance</a></li>
                                <li><a href="<?php echo SITE_URL; ?>/accountant/salaries.php"><i class="fas fa-money-bill-wave"></i> Salaries</a></li>
                                <li><a href="<?php echo SITE_URL; ?>/accountant/revenue.php"><i class="fas fa-chart-line"></i> Revenue</a></li>
                                
                            <?php elseif ($_SESSION['user_role'] === 'admin'): ?>
                                <li><a href="<?php echo SITE_URL; ?>/admin/staff.php"><i class="fas fa-user-md"></i> Staff</a></li>
                                <li><a href="<?php echo SITE_URL; ?>/admin/revenue.php"><i class="fas fa-chart-bar"></i> Revenue</a></li>
                                <li><a href="<?php echo SITE_URL; ?>/admin/reports.php"><i class="fas fa-flag"></i> Reports</a></li>
                            <?php endif; ?>
                        <?php else: ?>
                            <li><a href="<?php echo SITE_URL; ?>/index.php"><i class="fas fa-home"></i> Home</a></li>
                            <li><a href="<?php echo SITE_URL; ?>/services.php"><i class="fas fa-stethoscope"></i> Services</a></li>
                            <li><a href="<?php echo SITE_URL; ?>/doctors.php"><i class="fas fa-user-md"></i> Find a Doctor</a></li>
                            <li><a href="<?php echo SITE_URL; ?>/contact.php"><i class="fas fa-envelope"></i> Contact Us</a></li>
                        <?php endif; ?>
                    </ul>
                </nav>
                <div class="includes-auth-buttons">
                    <?php if (isLoggedIn()): ?>
                        <?php 
                        $unreadCount = 0;
                        if (function_exists('getUnreadNotificationsCount') && isset($_SESSION['user_id'])) {
                            $unreadCount = getUnreadNotificationsCount($_SESSION['user_id']);
                        }
                        ?>
                        <div class="includes-user-menu">
                            <button type="button" class="includes-user-dropdown-trigger" id="userDropdownBtn">
                                <i class="fas fa-user-circle"></i>
                                <span><?php echo htmlspecialchars($_SESSION['username']); ?></span>
                                <i class="fas fa-chevron-down" id="dropdownArrow"></i>
                                <?php if ($unreadCount > 0): ?>
                                    <span class="includes-notification-badge"><?php echo $unreadCount; ?></span>
                                <?php endif; ?>
                            </button>
                            <div class="includes-user-dropdown" id="userDropdown">
                                <a href="<?php echo SITE_URL; ?>/profile.php"><i class="fas fa-user"></i> Profile</a>
                                <a href="<?php echo SITE_URL; ?>/notifications.php"><i class="fas fa-bell"></i> Notifications
                                    <?php if ($unreadCount > 0): ?>
                                        <span class="badge"><?php echo $unreadCount; ?></span>
                                    <?php endif; ?>
                                </a>
                                <?php
                                $role = $_SESSION['user_role'];
                                $salaryLink = '';
                                if ($role === 'doctor') $salaryLink = 'doctor/my-salary.php';
                                elseif ($role === 'nurse') $salaryLink = 'nurse/my-salary.php';
                                elseif ($role === 'staff') $salaryLink = 'staff/my-salary.php';
                                elseif ($role === 'accountant') $salaryLink = 'accountant/my-salary.php';
                                elseif ($role === 'admin') $salaryLink = 'admin/my-salary.php';
                                
                                if ($salaryLink): ?>
                                    <a href="<?php echo SITE_URL . '/' . $salaryLink; ?>"><i class="fas fa-wallet"></i> My Salary</a>
                                <?php endif; ?>
                                <a href="<?php echo SITE_URL; ?>/logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
                            </div>
                        </div>
                    <?php else: ?>
                        <a href="<?php echo SITE_URL; ?>/login.php" class="includes-login-btn">Login</a>
                        <a href="<?php echo SITE_URL; ?>/register.php" class="includes-register-btn">Register</a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </header>

    <main class="includes-main">
        <div class="container">
            <?php if (isset($_SESSION['success'])): ?>
                <div class="includes-alert includes-alert-success">
                    <i class="fas fa-check-circle"></i>
                    <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
                    <button class="includes-close-alert" type="button">&times;</button>
                </div>
            <?php endif; ?>
            
            <?php if (isset($_SESSION['error'])): ?>
                <div class="includes-alert includes-alert-error">
                    <i class="fas fa-exclamation-circle"></i>
                    <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
                    <button class="includes-close-alert" type="button">&times;</button>
                </div>
            <?php endif; ?>
            
            <?php if (isset($_SESSION['info'])): ?>
                <div class="includes-alert includes-alert-info">
                    <i class="fas fa-info-circle"></i>
                    <?php echo $_SESSION['info']; unset($_SESSION['info']); ?>
                    <button class="includes-close-alert" type="button">&times;</button>
                </div>
            <?php endif; ?>