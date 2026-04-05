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
        $adminEmail = "himalkumarkari@gmail.com";
        $emailSubject = "Contact Form: " . $subject;
        $emailMessage = "
            <html>
            <head>
                <style>
                    body { font-family: Arial, sans-serif; line-height: 1.6; }
                    .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                    .header { background: #1a75bc; color: white; padding: 20px; text-align: center; }
                    .content { padding: 20px; background: #f9f9f9; }
                    .field { margin-bottom: 15px; }
                    .field strong { display: inline-block; width: 100px; }
                </style>
            </head>
            <body>
                <div class='container'>
                    <div class='header'>
                        <h2>New Contact Form Submission</h2>
                    </div>
                    <div class='content'>
                        <div class='field'><strong>Name:</strong> $name</div>
                        <div class='field'><strong>Email:</strong> $email</div>
                        <div class='field'><strong>Subject:</strong> $subject</div>
                        <div class='field'><strong>Message:</strong></div>
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
                            <p>Fussel Lane, Gungahlin, ACT 2912, Australia</p>
                        </div>
                    </div>
                    
                    <div class="info-item">
                        <i class="fas fa-phone"></i>
                        <div>
                            <h3>Call Us</h3>
                            <p>Main: <a href="tel:+614383473483">+61 438 347 3483</a></p>
                            <p>Emergency: <a href="tel:+614552627">+61 455 2627</a></p>
                        </div>
                    </div>
                    
                    <div class="info-item">
                        <i class="fas fa-envelope"></i>
                        <div>
                            <h3>Email Us</h3>
                            <p><a href="mailto:himalkumarkari@gmail.com">himalkumarkari@gmail.com</a></p>
                            <p><a href="mailto:abinashcarkee@gmail.com">abinashcarkee@gmail.com</a></p>
                        </div>
                    </div>
                    
                    <div class="info-item">
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
                
                <form method="POST" action="" id="contact-form">
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
            src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d3254.728641732676!2d149.1345983152368!3d-35.18495568029428!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x6b1650d4f6e6d6a1%3A0x2e3b8e6e5c0f7f5!2sGungahlin%20ACT%202912!5e0!3m2!1sen!2sau!4v1699999999999!5m2!1sen!2sau" 
            width="100%" 
            height="400" 
            style="border:0; border-radius: 12px;" 
            allowfullscreen="" 
            loading="lazy">
        </iframe>
    </div>
</section>

<script>
document.getElementById('contact-form')?.addEventListener('submit', function(e) {
    const email = document.getElementById('email').value;
    const emailPattern = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    
    if (!emailPattern.test(email)) {
        e.preventDefault();
        alert('Please enter a valid email address.');
    }
});
</script>

<style>
.contact-section {
    padding: 60px 0;
    background: #f8f9fa;
}

.contact-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 40px;
}

.contact-info {
    background: white;
    padding: 30px;
    border-radius: 12px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
}

.contact-info h2 {
    color: #1a75bc;
    margin-bottom: 15px;
}

.contact-info > p {
    color: #666;
    margin-bottom: 30px;
    line-height: 1.6;
}

.info-details {
    margin-bottom: 30px;
}

.info-item {
    display: flex;
    gap: 15px;
    margin-bottom: 25px;
    padding-bottom: 20px;
    border-bottom: 1px solid #e9ecef;
}

.info-item:last-child {
    border-bottom: none;
    padding-bottom: 0;
}

.info-item i {
    font-size: 24px;
    color: #1a75bc;
    margin-top: 5px;
}

.info-item h3 {
    margin: 0 0 8px 0;
    font-size: 16px;
    color: #333;
}

.info-item p {
    margin: 5px 0;
    color: #666;
}

.info-item a {
    color: #1a75bc;
    text-decoration: none;
}

.info-item a:hover {
    text-decoration: underline;
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
    transform: translateY(-3px);
}

.contact-form {
    background: white;
    padding: 30px;
    border-radius: 12px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
}

.contact-form h2 {
    color: #1a75bc;
    margin-bottom: 20px;
}

.form-group {
    margin-bottom: 20px;
}

.form-group label {
    display: block;
    margin-bottom: 8px;
    font-weight: 600;
    color: #495057;
}

.form-group input,
.form-group textarea {
    width: 100%;
    padding: 10px 12px;
    border: 1px solid #ddd;
    border-radius: 5px;
    font-size: 14px;
    transition: border-color 0.3s;
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
        gap: 30px;
    }
    
    .info-item {
        flex-direction: column;
        text-align: center;
    }
    
    .info-item i {
        margin: 0 auto 10px;
    }
}
</style>

<?php include 'includes/footer.php'; ?>