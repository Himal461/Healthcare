        </div>
    </main>

    <footer>
        <div class="container">
            <div class="footer-content">
                <div class="footer-section">
                    <h3>About HealthManagement</h3>
                    <p>Your trusted partner in healthcare, providing comprehensive medical services with compassion and excellence.</p>
                </div>
                
                <div class="footer-section">
                    <h3>Quick Links</h3>
                    <a href="<?php echo SITE_URL; ?>/index.php"><i class="fas fa-home"></i> Home</a>
                    <a href="<?php echo SITE_URL; ?>/services.php"><i class="fas fa-stethoscope"></i> Services</a>
                    <a href="<?php echo SITE_URL; ?>/doctors.php"><i class="fas fa-user-md"></i> Find a Doctor</a>
                    <a href="<?php echo SITE_URL; ?>/contact.php"><i class="fas fa-envelope"></i> Contact Us</a>
                </div>
                
                <?php if (isLoggedIn() && $_SESSION['user_role'] === 'patient'): ?>
                <div class="footer-section">
                    <h3>My Account</h3>
                    <a href="<?php echo SITE_URL; ?>/patient/appointments.php"><i class="fas fa-calendar-check"></i> My Appointments</a>
                    <a href="<?php echo SITE_URL; ?>/patient/medical-records.php"><i class="fas fa-notes-medical"></i> Medical Records</a>
                    <a href="<?php echo SITE_URL; ?>/patient/prescriptions.php"><i class="fas fa-prescription"></i> Prescriptions</a>
                    <a href="<?php echo SITE_URL; ?>/profile.php"><i class="fas fa-user-edit"></i> Update Profile</a>
                    <a href="<?php echo SITE_URL; ?>/change-password.php"><i class="fas fa-key"></i> Change Password</a>
                </div>
                <?php endif; ?>
                
                <?php if (isLoggedIn() && $_SESSION['user_role'] === 'doctor'): ?>
                <div class="footer-section">
                    <h3>Doctor Panel</h3>
                    <a href="<?php echo SITE_URL; ?>/doctor/appointments.php"><i class="fas fa-calendar-alt"></i> My Schedule</a>
                    <a href="<?php echo SITE_URL; ?>/doctor/patients.php"><i class="fas fa-users"></i> My Patients</a>
                    <a href="<?php echo SITE_URL; ?>/doctor/availability.php"><i class="fas fa-clock"></i> Set Availability</a>
                    <a href="<?php echo SITE_URL; ?>/profile.php"><i class="fas fa-user-edit"></i> Update Profile</a>
                </div>
                <?php endif; ?>
                
                <div class="footer-section">
                    <h3>Contact Info</h3>
                    <p><i class="fas fa-map-marker-alt"></i> Fussel Lane, Gungahlin</p>
                    <p><i class="fas fa-phone"></i> +61 4383473483</p>
                    <p><i class="fas fa-envelope"></i> himalkumarkari@gmail.com</p>
                    <p><i class="fas fa-envelope"></i> abinashcarkee@gmail.com</p>
                </div>
                
                <div class="footer-section">
                    <h3>Hours</h3>
                    <p><i class="far fa-clock"></i> Mon-Fri: 9:00 AM - 5:00 PM</p>
                    <p><i class="far fa-clock"></i> Sat: 9:00 AM - 1:00 PM</p>
                    <p><i class="far fa-clock"></i> Sun: Closed</p>
                    <p><i class="fas fa-ambulance"></i> Emergency: 24/7</p>
                </div>
            </div>
            
            <div class="footer-bottom">
                <p>&copy; <?php echo date('Y'); ?> HealthManagement. All rights reserved.</p>
                <div class="footer-bottom-links">
                    <a href="<?php echo SITE_URL; ?>/privacy-policy.php">Privacy Policy</a>
                    <a href="<?php echo SITE_URL; ?>/terms-of-service.php">Terms of Service</a>
                    <a href="<?php echo SITE_URL; ?>/contact.php">Support</a>
                    <a href="#" id="backToTop"><i class="fas fa-arrow-up"></i> Back to Top</a>
                </div>
            </div>
        </div>
    </footer>

    <script src="<?php echo SITE_URL; ?>/js/script.js"></script>
</body>
</html>