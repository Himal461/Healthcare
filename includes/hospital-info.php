<?php
define('HOSPITAL_NAME', 'HealthManagement System');
define('HOSPITAL_ADDRESS', 'Fussel Lane, Gungahlin, ACT 2912, Australia');
define('HOSPITAL_PHONE', '+61 438 347 3483');
define('HOSPITAL_EMERGENCY', '+61 455 2627');
define('HOSPITAL_EMAIL_PRIMARY', 'himalkumarkari@gmail.com');
define('HOSPITAL_EMAIL_SECONDARY', 'abinashcarkee@gmail.com');
define('HOSPITAL_HOURS_WEEKDAY', '9:00 AM - 5:00 PM');
define('HOSPITAL_HOURS_SATURDAY', '9:00 AM - 1:00 PM');
define('HOSPITAL_HOURS_SUNDAY', 'Closed');
define('HOSPITAL_EMERGENCY_HOURS', '24/7');
?>

<div class="includes-hospital-info-card">
    <h3><i class="fas fa-hospital"></i> <?php echo HOSPITAL_NAME; ?></h3>
    <p><i class="fas fa-map-marker-alt"></i> <?php echo HOSPITAL_ADDRESS; ?></p>
    <p><i class="fas fa-phone"></i> Main: <a href="tel:<?php echo HOSPITAL_PHONE; ?>"><?php echo HOSPITAL_PHONE; ?></a></p>
    <p><i class="fas fa-ambulance"></i> Emergency: <a href="tel:<?php echo HOSPITAL_EMERGENCY; ?>"><?php echo HOSPITAL_EMERGENCY; ?></a></p>
    <p><i class="fas fa-envelope"></i> Email: <a href="mailto:<?php echo HOSPITAL_EMAIL_PRIMARY; ?>"><?php echo HOSPITAL_EMAIL_PRIMARY; ?></a> | <a href="mailto:<?php echo HOSPITAL_EMAIL_SECONDARY; ?>"><?php echo HOSPITAL_EMAIL_SECONDARY; ?></a></p>
    <p><i class="fas fa-clock"></i> Hours: <?php echo HOSPITAL_HOURS_WEEKDAY; ?> (Sat: <?php echo HOSPITAL_HOURS_SATURDAY; ?>, Sun: <?php echo HOSPITAL_HOURS_SUNDAY; ?>)</p>
</div>