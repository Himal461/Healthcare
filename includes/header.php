<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle ?? SITE_NAME; ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="<?php echo SITE_URL; ?>/css/style.css">
</head>
<body>
    <header>
        <div class="container">
            <div class="top-bar">
                <a href="<?php echo SITE_URL; ?>/index.php" class="logo">
                    <i class="fas fa-heartbeat"></i>
                    <span>HealthManagement</span>
                </a>
                <nav>
                    <ul>
                        <li><a href="<?php echo SITE_URL; ?>/index.php">Home</a></li>
                        <?php if (isLoggedIn()): ?>
                            <li><a href="<?php echo SITE_URL; ?>/dashboard.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'dashboard.php' ? 'active' : ''; ?>">Dashboard</a></li>
                            
                            <?php if ($_SESSION['user_role'] === 'patient'): ?>
                                <li><a href="<?php echo SITE_URL; ?>/patient/appointments.php" class="<?php echo strpos($_SERVER['PHP_SELF'], 'appointments') !== false ? 'active' : ''; ?>">Appointments</a></li>
                                <li><a href="<?php echo SITE_URL; ?>/patient/medical-records.php" class="<?php echo strpos($_SERVER['PHP_SELF'], 'medical-records') !== false ? 'active' : ''; ?>">Medical Records</a></li>
                                <li><a href="<?php echo SITE_URL; ?>/patient/prescriptions.php" class="<?php echo strpos($_SERVER['PHP_SELF'], 'prescriptions') !== false ? 'active' : ''; ?>">Prescriptions</a></li>
                            <?php elseif ($_SESSION['user_role'] === 'doctor'): ?>
                                <li><a href="<?php echo SITE_URL; ?>/doctor/appointments.php" class="<?php echo strpos($_SERVER['PHP_SELF'], 'appointments') !== false ? 'active' : ''; ?>">Schedule</a></li>
                                <li><a href="<?php echo SITE_URL; ?>/doctor/patients.php" class="<?php echo strpos($_SERVER['PHP_SELF'], 'patients') !== false ? 'active' : ''; ?>">My Patients</a></li>
                                <li><a href="<?php echo SITE_URL; ?>/doctor/availability.php">Availability</a></li>
                            <?php elseif ($_SESSION['user_role'] === 'admin'): ?>
                                <li><a href="<?php echo SITE_URL; ?>/admin/users.php" class="<?php echo strpos($_SERVER['PHP_SELF'], 'users') !== false ? 'active' : ''; ?>">Users</a></li>
                                <li><a href="<?php echo SITE_URL; ?>/admin/appointments.php">Appointments</a></li>
                                <li><a href="<?php echo SITE_URL; ?>/admin/doctors.php">Doctors</a></li>
                                <li><a href="<?php echo SITE_URL; ?>/admin/reports.php">Reports</a></li>
                            <?php endif; ?>
                            
                            <li><a href="<?php echo SITE_URL; ?>/profile.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'profile.php' ? 'active' : ''; ?>">Profile</a></li>
                        <?php else: ?>
                            <li><a href="<?php echo SITE_URL; ?>/services.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'services.php' ? 'active' : ''; ?>">Services</a></li>
                            <li><a href="<?php echo SITE_URL; ?>/doctors.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'doctors.php' ? 'active' : ''; ?>">Find a Doctor</a></li>
                            <li><a href="<?php echo SITE_URL; ?>/contact.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'contact.php' ? 'active' : ''; ?>">Contact Us</a></li>
                        <?php endif; ?>
                    </ul>
                </nav>
                <div class="auth-buttons">
                    <?php if (isLoggedIn()): ?>
                        <div class="user-menu">
                            <a href="#" class="user-dropdown-trigger" id="userDropdownBtn">
                                <i class="fas fa-user-circle"></i> <?php echo htmlspecialchars($_SESSION['username']); ?>
                                <i class="fas fa-chevron-down"></i>
                            </a>
                            <div class="user-dropdown" id="userDropdown">
                                <a href="<?php echo SITE_URL; ?>/profile.php"><i class="fas fa-user"></i> Profile</a>
                                <a href="<?php echo SITE_URL; ?>/change-password.php"><i class="fas fa-key"></i> Change Password</a>
                                <a href="<?php echo SITE_URL; ?>/logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
                            </div>
                        </div>
                    <?php else: ?>
                        <a href="<?php echo SITE_URL; ?>/login.php" class="login-btn">Login</a>
                        <a href="<?php echo SITE_URL; ?>/register.php" class="register-btn">Register</a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </header>

    <main>
        <div class="container">
            <?php if (isset($_SESSION['success'])): ?>
                <div class="alert alert-success alert-dismissible">
                    <i class="fas fa-check-circle"></i>
                    <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
                    <button class="close-alert">&times;</button>
                </div>
            <?php endif; ?>
            
            <?php if (isset($_SESSION['error'])): ?>
                <div class="alert alert-error alert-dismissible">
                    <i class="fas fa-exclamation-circle"></i>
                    <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
                    <button class="close-alert">&times;</button>
                </div>
            <?php endif; ?>