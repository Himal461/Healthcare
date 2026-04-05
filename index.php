<?php
require_once 'includes/config.php';
require_once 'includes/auth.php';

$pageTitle = "Home - HealthManagement";
include 'includes/header.php';
?>

<!-- Hero Section -->
<section class="hero">
    <div class="container">
        <h1>Welcome to HealthManagement</h1>
        <p>Comprehensive healthcare services tailored to meet your needs and ensure your well-being.</p>
        <div class="hero-buttons">
            <?php if (isLoggedIn()): ?>
                <a href="dashboard.php" class="btn btn-primary">Go to Dashboard</a>
            <?php else: ?>
                <a href="register.php" class="btn btn-primary">Get Started</a>
                <a href="login.php" class="btn btn-outline">Login</a>
            <?php endif; ?>
        </div>
    </div>
</section>

<!-- Features Section -->
<section class="features">
    <div class="container">
        <h2 class="section-title">Why Choose Us?</h2>
        <div class="features-grid">
            <div class="feature-card">
                <div class="feature-icon">
                    <i class="fas fa-user-md"></i>
                </div>
                <h3>Expert Doctors</h3>
                <p>Our team consists of highly qualified and experienced healthcare professionals.</p>
            </div>
            <div class="feature-card">
                <div class="feature-icon">
                    <i class="fas fa-clock"></i>
                </div>
                <h3>24/7 Availability</h3>
                <p>Round-the-clock medical services to address your healthcare needs anytime.</p>
            </div>
            <div class="feature-card">
                <div class="feature-icon">
                    <i class="fas fa-shield-alt"></i>
                </div>
                <h3>Quality & Safety</h3>
                <p>We maintain the highest standards of quality and patient safety.</p>
            </div>
            <div class="feature-card">
                <div class="feature-icon">
                    <i class="fas fa-calendar-check"></i>
                </div>
                <h3>Easy Booking</h3>
                <p>Book appointments online with real-time availability checking.</p>
            </div>
        </div>
    </div>
</section>

<!-- Services Section -->
<section class="services">
    <div class="container">
        <h2 class="section-title">Our Services</h2>
        <div class="services-grid">
            <div class="service-card">
                <div class="service-icon">
                    <i class="fas fa-stethoscope"></i>
                </div>
                <h3>Primary Care</h3>
                <p>Comprehensive primary care services for patients of all ages.</p>
                <a href="services.php#primary-care" class="btn-link">Learn More →</a>
            </div>
            <div class="service-card">
                <div class="service-icon">
                    <i class="fas fa-heartbeat"></i>
                </div>
                <h3>Cardiology</h3>
                <p>Expert heart care and cardiovascular disease management.</p>
                <a href="services.php#cardiology" class="btn-link">Learn More →</a>
            </div>
            <div class="service-card">
                <div class="service-icon">
                    <i class="fas fa-brain"></i>
                </div>
                <h3>Neurology</h3>
                <p>Specialized care for brain and nervous system conditions.</p>
                <a href="services.php#neurology" class="btn-link">Learn More →</a>
            </div>
            <div class="service-card">
                <div class="service-icon">
                    <i class="fas fa-baby"></i>
                </div>
                <h3>Pediatrics</h3>
                <p>Complete healthcare services for infants, children, and adolescents.</p>
                <a href="services.php#pediatrics" class="btn-link">Learn More →</a>
            </div>
            <div class="service-card">
                <div class="service-icon">
                    <i class="fas fa-bone"></i>
                </div>
                <h3>Orthopedics</h3>
                <p>Treatment for bone, joint, and muscle conditions.</p>
                <a href="services.php#orthopedics" class="btn-link">Learn More →</a>
            </div>
            <div class="service-card">
                <div class="service-icon">
                    <i class="fas fa-ambulance"></i>
                </div>
                <h3>Emergency Care</h3>
                <p>24/7 emergency medical services for urgent health issues.</p>
                <a href="services.php#emergency" class="btn-link">Learn More →</a>
            </div>
        </div>
        <div class="text-center" style="margin-top: 40px;">
            <a href="services.php" class="btn btn-primary">View All Services</a>
        </div>
    </div>
</section>

<!-- Doctors Section -->
<section class="doctors">
    <div class="container">
        <h2 class="section-title">Meet Our Doctors</h2>
        <div class="doctors-grid">
            <?php
            $stmt = $pdo->query("
                SELECT u.firstName, u.lastName, d.specialization, d.yearsOfExperience, d.doctorId
                FROM doctors d
                JOIN staff s ON d.staffId = s.staffId
                JOIN users u ON s.userId = u.userId
                WHERE d.isAvailable = 1
                LIMIT 4
            ");
            $doctors = $stmt->fetchAll();
            
            foreach ($doctors as $doctor):
            ?>
                <div class="doctor-card">
                    <div class="doctor-avatar">
                        <i class="fas fa-user-circle"></i>
                    </div>
                    <h3>Dr. <?php echo htmlspecialchars($doctor['firstName'] . ' ' . $doctor['lastName']); ?></h3>
                    <p class="doctor-specialty"><?php echo htmlspecialchars($doctor['specialization']); ?></p>
                    <p class="doctor-exp"><?php echo $doctor['yearsOfExperience']; ?>+ years experience</p>
                    <a href="doctors.php?doctor_id=<?php echo $doctor['doctorId']; ?>" class="btn btn-outline">View Profile</a>
                </div>
            <?php endforeach; ?>
        </div>
        <div class="text-center" style="margin-top: 40px;">
            <a href="doctors.php" class="btn btn-primary">Find a Doctor</a>
        </div>
    </div>
</section>

<!-- Testimonials Section -->
<section class="testimonials">
    <div class="container">
        <h2 class="section-title">What Our Patients Say</h2>
        <div class="testimonials-grid">
            <div class="testimonial-card">
                <div class="testimonial-text">
                    <i class="fas fa-quote-left"></i>
                    <p>The best healthcare experience I've ever had. The doctors are professional and caring.</p>
                </div>
                <div class="testimonial-author">
                    <strong>John Smith</strong>
                    <span>Patient since 2023</span>
                </div>
            </div>
            <div class="testimonial-card">
                <div class="testimonial-text">
                    <i class="fas fa-quote-left"></i>
                    <p>Easy appointment booking and great follow-up care. Highly recommended!</p>
                </div>
                <div class="testimonial-author">
                    <strong>Sarah Johnson</strong>
                    <span>Patient since 2024</span>
                </div>
            </div>
            <div class="testimonial-card">
                <div class="testimonial-text">
                    <i class="fas fa-quote-left"></i>
                    <p>The online system makes it so convenient to manage my health. Love it!</p>
                </div>
                <div class="testimonial-author">
                    <strong>Michael Brown</strong>
                    <span>Patient since 2023</span>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- CTA Section -->
<section class="cta">
    <div class="container">
        <h2>Ready to Take Control of Your Health?</h2>
        <p>Join thousands of satisfied patients who trust us with their healthcare needs.</p>
        <?php if (!isLoggedIn()): ?>
            <a href="register.php" class="btn btn-primary">Register Now</a>
        <?php else: ?>
            <a href="patient/appointments.php" class="btn btn-primary">Book Appointment</a>
        <?php endif; ?>
    </div>
</section>

<?php include 'includes/footer.php'; ?>