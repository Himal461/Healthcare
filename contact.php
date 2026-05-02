<?php
require_once 'includes/config.php';
require_once 'includes/auth.php';

$pageTitle = "Contact Us - HealthManagement";
$extraCSS = '<link rel="stylesheet" href="css/root.css">';
$extraJS = '<script src="js/root.js"></script>';
include 'includes/header.php';

$messageSent = false;
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = sanitizeInput($_POST['name']);
    $email = sanitizeInput($_POST['email']);
    $subject = sanitizeInput($_POST['subject']);
    $message = sanitizeInput($_POST['message']);
    
    if (empty($name) || empty($email) || empty($subject) || empty($message)) {
        $error = "Please fill in all fields.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Please enter a valid email address.";
    } else {
        $adminEmail = "himalkumarkari@gmail.com";
        $emailSubject = "Contact Form: " . $subject;
        $emailMessage = "
            <html>
            <head><style>body{font-family:Arial,sans-serif;line-height:1.6;}.container{max-width:600px;margin:0 auto;padding:20px;}.header{background:#1e3a5f;color:white;padding:20px;text-align:center;}.content{padding:20px;background:#f9f9f9;}</style></head>
            <body>
                <div class='container'>
                    <div class='header'><h2>New Contact Form Submission</h2></div>
                    <div class='content'>
                        <p><strong>Name:</strong> $name</p>
                        <p><strong>Email:</strong> $email</p>
                        <p><strong>Subject:</strong> $subject</p>
                        <p><strong>Message:</strong></p>
                        <p>$message</p>
                    </div>
                </div>
            </body>
            </html>
        ";
        
        if (sendEmail($adminEmail, $emailSubject, $emailMessage)) {
            $messageSent = true;
        } else {
            $error = "Failed to send message. Please try again later.";
        }
    }
}
?>

<div class="root-container">
    <div class="root-page-header">
        <div class="header-title">
            <h1><i class="fas fa-envelope"></i> Contact Us</h1>
            <p>We're here to help. Reach out to us anytime.</p>
        </div>
    </div>

    <div class="root-contact-grid">
        <div class="root-contact-info">
            <h2><i class="fas fa-comments"></i> Get in Touch</h2>
            <p>Have questions? We'd love to hear from you. Send us a message and we'll respond as soon as possible.</p>
            
            <div style="margin-top: 30px;">
                <div class="root-info-item">
                    <i class="fas fa-map-marker-alt"></i>
                    <div>
                        <h3>Visit Us</h3>
                        <p>Fussel Lane, Gungahlin, ACT 2912, Australia</p>
                    </div>
                </div>
                <div class="root-info-item">
                    <i class="fas fa-phone"></i>
                    <div>
                        <h3>Call Us</h3>
                        <p>Main: <a href="tel:+614383473483">+61 438 347 3483</a></p>
                        <p>Emergency: <a href="tel:+614552627">+61 455 2627</a></p>
                    </div>
                </div>
                <div class="root-info-item">
                    <i class="fas fa-envelope"></i>
                    <div>
                        <h3>Email Us</h3>
                        <p><a href="mailto:himalkumarkari@gmail.com">himalkumarkari@gmail.com</a></p>
                        <p><a href="mailto:abinashcarkee@gmail.com">abinashcarkee@gmail.com</a></p>
                    </div>
                </div>
                <div class="root-info-item">
                    <i class="fas fa-clock"></i>
                    <div>
                        <h3>Hours of Operation</h3>
                        <p>Monday - Friday: 9:00 AM - 5:00 PM</p>
                        <p>Saturday: 9:00 AM - 1:00 PM</p>
                        <p>Sunday: Closed</p>
                        <p><strong>Emergency: 24/7</strong></p>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="root-contact-form">
            <h2><i class="fas fa-paper-plane"></i> Send Us a Message</h2>
            
            <?php if ($messageSent): ?>
                <div class="root-alert root-alert-success">
                    <i class="fas fa-check-circle"></i> Thank you for your message! We'll get back to you soon.
                </div>
            <?php endif; ?>
            
            <?php if ($error): ?>
                <div class="root-alert root-alert-error">
                    <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>
            
            <form method="POST" id="contact-form">
                <div class="root-form-group">
                    <label for="name">Your Name <span class="required">*</span></label>
                    <input type="text" id="name" name="name" class="root-form-control" required>
                </div>
                <div class="root-form-group">
                    <label for="email">Email Address <span class="required">*</span></label>
                    <input type="email" id="email" name="email" class="root-form-control" required>
                </div>
                <div class="root-form-group">
                    <label for="subject">Subject <span class="required">*</span></label>
                    <input type="text" id="subject" name="subject" class="root-form-control" required>
                </div>
                <div class="root-form-group">
                    <label for="message">Message <span class="required">*</span></label>
                    <textarea id="message" name="message" rows="5" class="root-form-control" required></textarea>
                </div>
                <button type="submit" class="root-btn root-btn-primary root-btn-block">Send Message</button>
            </form>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>