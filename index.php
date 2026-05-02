<?php
require_once 'includes/config.php';
require_once 'includes/auth.php';

$pageTitle = "Home - HealthManagement";
$extraCSS = '<link rel="stylesheet" href="css/root.css">';
$extraJS = '<script src="js/root.js"></script>';
include 'includes/header.php';

// Get featured doctors - ONLY query if table exists
$featuredDoctors = [];
try {
    $checkTable = $pdo->query("SHOW TABLES LIKE 'doctors'");
    if ($checkTable->rowCount() > 0) {
        $stmt = $pdo->query("
            SELECT u.firstName, u.lastName, d.specialization, d.yearsOfExperience, d.doctorId, u.email
            FROM doctors d
            JOIN staff s ON d.staffId = s.staffId
            JOIN users u ON s.userId = u.userId
            WHERE d.isAvailable = 1
            LIMIT 4
        ");
        $featuredDoctors = $stmt->fetchAll();
    }
} catch (Exception $e) {
    // Tables not yet created - show placeholder
    $featuredDoctors = [];
}
?>

<style>
/* ============================================ */
/* MOBILE RESPONSIVE HERO SECTION               */
/* ============================================ */
.hero-section {
    background: linear-gradient(135deg, #1e3a5f 0%, #0f2440 100%);
    color: white;
    padding: 80px 0;
    text-align: center;
}

.hero-container {
    max-width: 1200px;
    margin: 0 auto;
    padding: 0 20px;
}

.hero-title {
    font-size: clamp(28px, 8vw, 48px);
    margin-bottom: 20px;
    color: white;
    font-weight: 700;
    line-height: 1.2;
    word-wrap: break-word;
    overflow-wrap: break-word;
    hyphens: auto;
}

.hero-subtitle {
    font-size: clamp(14px, 4vw, 18px);
    margin-bottom: 30px;
    opacity: 0.9;
    line-height: 1.5;
    padding: 0 10px;
}

.hero-buttons {
    display: flex;
    gap: 15px;
    justify-content: center;
    flex-wrap: wrap;
}

.hero-btn {
    padding: 12px 24px;
    font-size: clamp(14px, 3.5vw, 16px);
    border-radius: 8px;
    text-decoration: none;
    font-weight: 600;
    transition: all 0.3s ease;
    white-space: nowrap;
}

.hero-btn-primary {
    background: white;
    color: #1e3a5f;
}

.hero-btn-primary:hover {
    background: #f0f0f0;
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0,0,0,0.15);
}

.hero-btn-outline {
    background: transparent;
    border: 2px solid white;
    color: white !important;
}

.hero-btn-outline:hover {
    background: white;
    color: #1e3a5f;
    transform: translateY(-2px);
}

/* Section Titles */
.section-title {
    text-align: center;
    font-size: clamp(24px, 6vw, 36px);
    margin-bottom: 40px;
    color: #1e3a5f;
    font-weight: 700;
    line-height: 1.3;
    padding: 0 15px;
}

/* Features Grid */
.features-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 30px;
}

.feature-card {
    background: white;
    padding: 30px;
    border-radius: 16px;
    text-align: center;
    box-shadow: 0 4px 12px rgba(0,0,0,0.06);
    transition: transform 0.3s ease;
}

.feature-card:hover {
    transform: translateY(-5px);
}

.feature-icon {
    font-size: clamp(36px, 10vw, 48px);
    color: #1e3a5f;
    margin-bottom: 20px;
}

.feature-card h3 {
    font-size: clamp(18px, 4vw, 22px);
    margin-bottom: 15px;
    color: #1e293b;
}

.feature-card p {
    color: #64748b;
    font-size: clamp(13px, 3.5vw, 15px);
    line-height: 1.5;
}

/* Services Grid */
.services-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 30px;
}

.service-card {
    background: white;
    padding: 30px;
    border-radius: 16px;
    box-shadow: 0 4px 12px rgba(0,0,0,0.06);
    transition: transform 0.3s ease;
}

.service-card:hover {
    transform: translateY(-5px);
}

.service-icon {
    font-size: clamp(36px, 10vw, 48px);
    color: #1e3a5f;
    margin-bottom: 20px;
}

.service-card h3 {
    font-size: clamp(18px, 4vw, 22px);
    margin-bottom: 15px;
    color: #1e293b;
}

.service-card p {
    color: #64748b;
    font-size: clamp(13px, 3.5vw, 15px);
    margin-bottom: 15px;
    line-height: 1.5;
}

.service-link {
    color: #1e3a5f;
    text-decoration: none;
    font-weight: 500;
    font-size: clamp(13px, 3.5vw, 14px);
}

.service-link:hover {
    text-decoration: underline;
}

/* Doctors Grid */
.doctors-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 30px;
}

.doctor-card {
    background: white;
    border-radius: 16px;
    overflow: hidden;
    box-shadow: 0 4px 12px rgba(0,0,0,0.06);
    text-align: center;
    transition: transform 0.3s ease;
}

.doctor-card:hover {
    transform: translateY(-5px);
}

.doctor-avatar {
    background: linear-gradient(135deg, #1e3a5f 0%, #0f2440 100%);
    padding: 30px 0;
}

.doctor-avatar i {
    font-size: clamp(60px, 15vw, 80px);
    color: white;
}

.doctor-info {
    padding: 20px;
}

.doctor-info h3 {
    font-size: clamp(18px, 4vw, 20px);
    color: #1e3a5f;
    margin-bottom: 5px;
}

.doctor-specialty {
    color: #10b981;
    font-weight: 500;
    font-size: clamp(13px, 3.5vw, 14px);
    margin-bottom: 5px;
}

.doctor-experience {
    color: #64748b;
    font-size: clamp(12px, 3vw, 14px);
    margin-bottom: 15px;
}

/* CTA Section */
.cta-section {
    background: linear-gradient(135deg, #1e3a5f 0%, #0f2440 100%);
    color: white;
    padding: 60px 0;
    text-align: center;
}

.cta-container {
    max-width: 800px;
    margin: 0 auto;
    padding: 0 20px;
}

.cta-title {
    font-size: clamp(24px, 6vw, 36px);
    margin-bottom: 20px;
    color: white;
    font-weight: 700;
    line-height: 1.3;
}

.cta-subtitle {
    font-size: clamp(14px, 4vw, 18px);
    margin-bottom: 30px;
    opacity: 0.9;
    line-height: 1.5;
}

.cta-btn {
    display: inline-block;
    padding: 14px 28px;
    font-size: clamp(14px, 3.5vw, 16px);
    background: white;
    color: #1e3a5f;
    border-radius: 8px;
    text-decoration: none;
    font-weight: 600;
    transition: all 0.3s ease;
}

.cta-btn:hover {
    background: #f0f0f0;
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0,0,0,0.15);
}

/* View All Button */
.view-all-container {
    text-align: center;
    margin-top: 40px;
}

.view-all-btn {
    display: inline-block;
    padding: 12px 28px;
    background: #1e3a5f;
    color: white;
    border-radius: 8px;
    text-decoration: none;
    font-weight: 600;
    font-size: clamp(14px, 3.5vw, 16px);
    transition: all 0.3s ease;
}

.view-all-btn:hover {
    background: #0f2440;
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(30, 58, 95, 0.3);
}

/* Mobile Responsive Fixes */
@media (max-width: 768px) {
    .hero-section {
        padding: 50px 0;
    }
    
    .hero-title {
        font-size: 26px;
    }
    
    .hero-subtitle {
        font-size: 15px;
        padding: 0 5px;
    }
    
    .hero-buttons {
        flex-direction: column;
        align-items: center;
        gap: 12px;
    }
    
    .hero-btn {
        width: 100%;
        max-width: 280px;
        text-align: center;
        white-space: normal;
        padding: 14px 20px;
    }
    
    .section-title {
        font-size: 24px;
        margin-bottom: 30px;
    }
    
    .features-grid,
    .services-grid,
    .doctors-grid {
        grid-template-columns: 1fr;
        gap: 20px;
    }
    
    .feature-card,
    .service-card {
        padding: 25px 20px;
    }
    
    .cta-section {
        padding: 50px 0;
    }
    
    .cta-title {
        font-size: 24px;
    }
    
    .cta-subtitle {
        font-size: 15px;
    }
}

@media (max-width: 480px) {
    .hero-section {
        padding: 40px 0;
    }
    
    .hero-title {
        font-size: 22px;
    }
    
    .hero-subtitle {
        font-size: 14px;
    }
    
    .hero-btn {
        font-size: 14px;
        padding: 12px 16px;
    }
    
    .section-title {
        font-size: 22px;
    }
    
    .feature-card h3,
    .service-card h3 {
        font-size: 18px;
    }
    
    .cta-title {
        font-size: 22px;
    }
}

@media (max-width: 360px) {
    .hero-title {
        font-size: 20px;
    }
    
    .hero-subtitle {
        font-size: 13px;
    }
}
</style>

<!-- Hero Section -->
<section class="hero-section">
    <div class="hero-container">
        <h1 class="hero-title">Welcome to HealthManagement</h1>
        <p class="hero-subtitle">Comprehensive healthcare services tailored to meet your needs and ensure your well-being.</p>
        <div class="hero-buttons">
            <?php if (isLoggedIn()): ?>
                <a href="dashboard.php" class="hero-btn hero-btn-primary">Go to Dashboard</a>
            <?php else: ?>
                <a href="register.php" class="hero-btn hero-btn-primary">Get Started</a>
                <a href="login.php" class="hero-btn hero-btn-outline">Login</a>
            <?php endif; ?>
        </div>
        <div style="margin-top: 25px;">
    <a href="medical-certificate.php" class="hero-btn hero-btn-outline" style="border-color: #10b981; color: #10b981;">
        <i class="fas fa-file-medical"></i> Get Medical Certificate ($13)
    </a>
</div>
    </div>
</section>

<!-- Features Section -->
<section style="padding: 60px 0;">
    <div style="max-width: 1200px; margin: 0 auto; padding: 0 20px;">
        <h2 class="section-title">Why Choose Us?</h2>
        <div class="features-grid">
            <div class="feature-card">
                <div class="feature-icon"><i class="fas fa-user-md"></i></div>
                <h3>Expert Doctors</h3>
                <p>Our team consists of highly qualified and experienced healthcare professionals.</p>
            </div>
            <div class="feature-card">
                <div class="feature-icon"><i class="fas fa-clock"></i></div>
                <h3>24/7 Availability</h3>
                <p>Round-the-clock medical services to address your healthcare needs anytime.</p>
            </div>
            <div class="feature-card">
                <div class="feature-icon"><i class="fas fa-shield-alt"></i></div>
                <h3>Quality & Safety</h3>
                <p>We maintain the highest standards of quality and patient safety.</p>
            </div>
            <div class="feature-card">
                <div class="feature-icon"><i class="fas fa-calendar-check"></i></div>
                <h3>Easy Booking</h3>
                <p>Book appointments online with real-time availability checking.</p>
            </div>
        </div>
    </div>
</section>

<!-- Services Section -->
<section style="padding: 60px 0; background: #f8fafc;">
    <div style="max-width: 1200px; margin: 0 auto; padding: 0 20px;">
        <h2 class="section-title">Our Services</h2>
        <div class="services-grid">
            <div class="service-card">
                <div class="service-icon"><i class="fas fa-stethoscope"></i></div>
                <h3>Primary Care</h3>
                <p>Comprehensive primary care services for patients of all ages.</p>
                <a href="services.php" class="service-link">Learn More →</a>
            </div>
            <div class="service-card">
                <div class="service-icon"><i class="fas fa-heartbeat"></i></div>
                <h3>Cardiology</h3>
                <p>Expert heart care and cardiovascular disease management.</p>
                <a href="services.php" class="service-link">Learn More →</a>
            </div>
            <div class="service-card">
                <div class="service-icon"><i class="fas fa-brain"></i></div>
                <h3>Neurology</h3>
                <p>Specialized care for brain and nervous system conditions.</p>
                <a href="services.php" class="service-link">Learn More →</a>
            </div>
            <div class="service-card">
                <div class="service-icon"><i class="fas fa-baby"></i></div>
                <h3>Pediatrics</h3>
                <p>Complete healthcare services for infants, children, and adolescents.</p>
                <a href="services.php" class="service-link">Learn More →</a>
            </div>
        </div>
        <div class="view-all-container">
            <a href="services.php" class="view-all-btn">View All Services</a>
        </div>
    </div>
</section>

<!-- Featured Doctors Section -->
<?php if (!empty($featuredDoctors)): ?>
<section style="padding: 60px 0;">
    <div style="max-width: 1200px; margin: 0 auto; padding: 0 20px;">
        <h2 class="section-title">Meet Our Doctors</h2>
        <div class="doctors-grid">
            <?php foreach ($featuredDoctors as $doctor): ?>
                <div class="doctor-card">
                    <div class="doctor-avatar">
                        <i class="fas fa-user-circle"></i>
                    </div>
                    <div class="doctor-info">
                        <h3>Dr. <?php echo htmlspecialchars($doctor['firstName'] . ' ' . $doctor['lastName']); ?></h3>
                        <p class="doctor-specialty"><?php echo htmlspecialchars($doctor['specialization']); ?></p>
                        <p class="doctor-experience"><?php echo $doctor['yearsOfExperience']; ?>+ years experience</p>
                        <p style="color: #64748b; font-size: 13px; margin-bottom: 15px;">
                            <i class="fas fa-envelope"></i> <?php echo htmlspecialchars($doctor['email']); ?>
                        </p>
                        <a href="doctor-profile.php?id=<?php echo $doctor['doctorId']; ?>" class="view-all-btn" style="padding: 8px 16px; font-size: 14px; margin-bottom: 10px; display: block;">
                            <i class="fas fa-id-card"></i> View Profile
                        </a>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        <div class="view-all-container">
            <a href="doctors.php" class="view-all-btn">Find a Doctor</a>
        </div>
    </div>
</section>
<?php endif; ?>

<!-- CTA Section -->
<section class="cta-section">
    <div class="cta-container">
        <h2 class="cta-title">Ready to Take Control of Your Health?</h2>
        <p class="cta-subtitle">Join thousands of satisfied patients who trust us with their healthcare needs.</p>
        <?php if (!isLoggedIn()): ?>
            <a href="register.php" class="cta-btn">Register Now</a>
        <?php else: ?>
            <a href="patient/appointments.php" class="cta-btn">Book Appointment</a>
        <?php endif; ?>
    </div>
</section>

<?php include 'includes/footer.php'; ?>