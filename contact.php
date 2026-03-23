<?php
require_once 'includes/config.php';
require_once 'includes/auth.php';

$pageTitle = "Contact Us - HealthManagement";
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
        // Send email to admin
        $adminEmail = SMTP_FROM;
        $emailSubject = "Contact Form: " . $subject;
        $emailMessage = "
            <html>
            <body>
                <h2>New Contact Form Submission</h2>
                <p><strong>Name:</strong> $name</p>
                <p><strong>Email:</strong> $email</p>
                <p><strong>Subject:</strong> $subject</p>
                <p><strong>Message:</strong></p>
                <p>$message</p>
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

<section class="page-header">
    <div class="container">
        <h1>Contact Us</h1>
        <p>We're here to help. Reach out to us anytime.</p>
    </div>
</section>

<section class="contact-section">
    <div class="container">
        <div class="contact-grid">
            <div class="contact-info">
                <h2>Get in Touch</h2>
                <p>Have questions? We'd love to hear from you. Send us a message and we'll respond as soon as possible.</p>
                
                <div class="info-details">
                    <div class="info-item">
                        <i class="fas fa-map-marker-alt"></i>
                        <div>
                            <h3>Visit Us</h3>
                            <p>123 Medical Drive, Health City, HC 12345</p>
                        </div>
                    </div>
                    
                    <div class="info-item">
                        <i class="fas fa-phone"></i>
                        <div>
                            <h3>Call Us</h3>
                            <p>Main: (555) 123-4567</p>
                            <p>Emergency: (555) 123-4568</p>
                        </div>
                    </div>
                    
                    <div class="info-item">
                        <i class="fas fa-envelope"></i>
                        <div>
                            <h3>Email Us</h3>
                            <p>info@healthmanagement.com</p>
                            <p>support@healthmanagement.com</p>
                        </div>
                    </div>
                    
                    <div class="info-item">
                        <i class="fas fa-clock"></i>
                        <div>
                            <h3>Hours of Operation</h3>
                            <p>Monday - Friday: 9:00 AM - 5:00 PM</p>
                            <p>Saturday: 9:00 AM - 1:00 PM</p>
                            <p>Sunday: Closed</p>
                            <p>Emergency: 24/7</p>
                        </div>
                    </div>
                </div>
                
                <div class="social-links">
                    <a href="#" class="social-link"><i class="fab fa-facebook-f"></i></a>
                    <a href="#" class="social-link"><i class="fab fa-twitter"></i></a>
                    <a href="#" class="social-link"><i class="fab fa-linkedin-in"></i></a>
                    <a href="#" class="social-link"><i class="fab fa-instagram"></i></a>
                </div>
            </div>
            
            <div class="contact-form">
                <h2>Send Us a Message</h2>
                
                <?php if ($messageSent): ?>
                    <div class="alert alert-success">
                        <i class="fas fa-check-circle"></i>
                        Thank you for your message! We'll get back to you soon.
                    </div>
                <?php endif; ?>
                
                <?php if ($error): ?>
                    <div class="alert alert-error">
                        <i class="fas fa-exclamation-circle"></i>
                        <?php echo $error; ?>
                    </div>
                <?php endif; ?>
                
                <form method="POST" action="">
                    <div class="form-group">
                        <label for="name">Your Name *</label>
                        <input type="text" id="name" name="name" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="email">Email Address *</label>
                        <input type="email" id="email" name="email" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="subject">Subject *</label>
                        <input type="text" id="subject" name="subject" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="message">Message *</label>
                        <textarea id="message" name="message" rows="5" required></textarea>
                    </div>
                    
                    <button type="submit" class="btn btn-primary btn-block">Send Message</button>
                </form>
            </div>
        </div>
    </div>
</section>

<section class="map-section">
    <div class="container">
        <iframe 
            src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d3024.2219901290355!2d-74.00369368400567!3d40.71312937933153!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x89c25a316bb1dd1b%3A0xc89c7f8e3d4d6d2e!2sWall%20Street!5e0!3m2!1sen!2sus!4v1699999999999!5m2!1sen!2sus" 
            width="100%" 
            height="400" 
            style="border:0; border-radius: 12px;" 
            allowfullscreen="" 
            loading="lazy">
        </iframe>
    </div>
</section>

<style>
.page-header {
    background: linear-gradient(135deg, #1a75bc 0%, #0a4299 100%);
    color: white;
    padding: 60px 0;
    text-align: center;
}

.page-header h1 {
    font-size: 48px;
    margin-bottom: 15px;
}

.contact-section {
    padding: 60px 0;
    background: #f8f9fa;
}

.contact-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 40px;
}

.contact-info,
.contact-form {
    background: white;
    padding: 40px;
    border-radius: 12px;
    box-shadow: 0 3px 15px rgba(0,0,0,0.1);
}

.contact-info h2,
.contact-form h2 {
    color: #1a75bc;
    margin-bottom: 20px;
}

.info-details {
    margin: 30px 0;
}

.info-item {
    display: flex;
    gap: 15px;
    margin-bottom: 25px;
}

.info-item i {
    font-size: 24px;
    color: #1a75bc;
    margin-top: 5px;
}

.info-item h3 {
    font-size: 16px;
    margin-bottom: 5px;
    color: #333;
}

.info-item p {
    color: #666;
    margin: 0;
}

.social-links {
    display: flex;
    gap: 15px;
    margin-top: 20px;
}

.social-link {
    width: 40px;
    height: 40px;
    background: #f0f0f0;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #1a75bc;
    transition: all 0.3s ease;
}

.social-link:hover {
    background: #1a75bc;
    color: white;
    transform: translateY(-2px);
}

.form-group {
    margin-bottom: 20px;
}

.form-group label {
    display: block;
    margin-bottom: 8px;
    font-weight: 500;
    color: #333;
}

.form-group input,
.form-group textarea {
    width: 100%;
    padding: 12px;
    border: 1px solid #ddd;
    border-radius: 8px;
    font-size: 14px;
    transition: border-color 0.3s ease;
}

.form-group input:focus,
.form-group textarea:focus {
    outline: none;
    border-color: #1a75bc;
}

.btn-block {
    width: 100%;
}

.map-section {
    padding: 60px 0;
    background: white;
}

@media (max-width: 768px) {
    .contact-grid {
        grid-template-columns: 1fr;
    }
    
    .info-item {
        flex-direction: column;
        text-align: center;
    }
    
    .social-links {
        justify-content: center;
    }
}
</style>

<?php include 'includes/footer.php'; ?>