<?php
require_once 'includes/config.php';
require_once 'includes/auth.php';

$pageTitle = "Our Services - HealthManagement";
include 'includes/header.php';
?>

<section class="page-header">
    <div class="container">
        <h1>Our Medical Services</h1>
        <p>Comprehensive healthcare services tailored to meet your needs</p>
    </div>
</section>

<section class="services-detailed">
    <div class="container">
        <div class="services-grid-detailed">
            <div class="service-detailed-card">
                <div class="service-icon-large">
                    <i class="fas fa-stethoscope"></i>
                </div>
                <h2>Primary Care</h2>
                <p>Comprehensive primary care services for patients of all ages. Our primary care physicians provide preventive care, health screenings, and treatment for common illnesses.</p>
                <ul class="service-features">
                    <li><i class="fas fa-check-circle"></i> Routine check-ups</li>
                    <li><i class="fas fa-check-circle"></i> Vaccinations</li>
                    <li><i class="fas fa-check-circle"></i> Health screenings</li>
                    <li><i class="fas fa-check-circle"></i> Chronic disease management</li>
                </ul>
            </div>

            <div class="service-detailed-card">
                <div class="service-icon-large">
                    <i class="fas fa-heartbeat"></i>
                </div>
                <h2>Cardiology</h2>
                <p>Expert heart care and cardiovascular disease management. Our cardiologists use advanced diagnostic tools and treatments to ensure optimal heart health.</p>
                <ul class="service-features">
                    <li><i class="fas fa-check-circle"></i> ECG/EKG testing</li>
                    <li><i class="fas fa-check-circle"></i> Stress tests</li>
                    <li><i class="fas fa-check-circle"></i> Echocardiograms</li>
                    <li><i class="fas fa-check-circle"></i> Heart disease management</li>
                </ul>
            </div>

            <div class="service-detailed-card">
                <div class="service-icon-large">
                    <i class="fas fa-brain"></i>
                </div>
                <h2>Neurology</h2>
                <p>Specialized care for brain and nervous system conditions. Our neurologists diagnose and treat disorders affecting the nervous system.</p>
                <ul class="service-features">
                    <li><i class="fas fa-check-circle"></i> Headache management</li>
                    <li><i class="fas fa-check-circle"></i> Epilepsy treatment</li>
                    <li><i class="fas fa-check-circle"></i> Stroke care</li>
                    <li><i class="fas fa-check-circle"></i> Memory disorders</li>
                </ul>
            </div>

            <div class="service-detailed-card">
                <div class="service-icon-large">
                    <i class="fas fa-baby"></i>
                </div>
                <h2>Pediatrics</h2>
                <p>Complete healthcare services for infants, children, and adolescents. Our pediatricians provide compassionate care for young patients.</p>
                <ul class="service-features">
                    <li><i class="fas fa-check-circle"></i> Well-child visits</li>
                    <li><i class="fas fa-check-circle"></i> Developmental screenings</li>
                    <li><i class="fas fa-check-circle"></i> Childhood vaccinations</li>
                    <li><i class="fas fa-check-circle"></i> Pediatric emergency care</li>
                </ul>
            </div>

            <div class="service-detailed-card">
                <div class="service-icon-large">
                    <i class="fas fa-bone"></i>
                </div>
                <h2>Orthopedics</h2>
                <p>Treatment for bone, joint, and muscle conditions. Our orthopedic specialists help you regain mobility and reduce pain.</p>
                <ul class="service-features">
                    <li><i class="fas fa-check-circle"></i> Joint replacement</li>
                    <li><i class="fas fa-check-circle"></i> Sports medicine</li>
                    <li><i class="fas fa-check-circle"></i> Fracture care</li>
                    <li><i class="fas fa-check-circle"></i> Physical therapy</li>
                </ul>
            </div>

            <div class="service-detailed-card">
                <div class="service-icon-large">
                    <i class="fas fa-female"></i>
                </div>
                <h2>Obstetrics & Gynecology</h2>
                <p>Comprehensive women's health services from adolescence through menopause. Our OB/GYNs provide expert care for all stages of life.</p>
                <ul class="service-features">
                    <li><i class="fas fa-check-circle"></i> Prenatal care</li>
                    <li><i class="fas fa-check-circle"></i> Family planning</li>
                    <li><i class="fas fa-check-circle"></i> Menopause management</li>
                    <li><i class="fas fa-check-circle"></i> Gynecological surgery</li>
                </ul>
            </div>

            <div class="service-detailed-card">
                <div class="service-icon-large">
                    <i class="fas fa-eye"></i>
                </div>
                <h2>Ophthalmology</h2>
                <p>Comprehensive eye care services. Our ophthalmologists diagnose and treat a wide range of eye conditions.</p>
                <ul class="service-features">
                    <li><i class="fas fa-check-circle"></i> Vision testing</li>
                    <li><i class="fas fa-check-circle"></i> Cataract surgery</li>
                    <li><i class="fas fa-check-circle"></i> Glaucoma treatment</li>
                    <li><i class="fas fa-check-circle"></i> Laser eye surgery</li>
                </ul>
            </div>

            <div class="service-detailed-card">
                <div class="service-icon-large">
                    <i class="fas fa-x-ray"></i>
                </div>
                <h2>Radiology & Imaging</h2>
                <p>Advanced diagnostic imaging services to aid in accurate diagnosis and treatment planning.</p>
                <ul class="service-features">
                    <li><i class="fas fa-check-circle"></i> X-rays</li>
                    <li><i class="fas fa-check-circle"></i> MRI scans</li>
                    <li><i class="fas fa-check-circle"></i> CT scans</li>
                    <li><i class="fas fa-check-circle"></i> Ultrasound</li>
                </ul>
            </div>
        </div>
    </div>
</section>

<section class="emergency-section">
    <div class="container">
        <div class="emergency-card">
            <div class="emergency-icon">
                <i class="fas fa-ambulance"></i>
            </div>
            <div class="emergency-content">
                <h2>24/7 Emergency Care</h2>
                <p>Our emergency department is open 24 hours a day, 7 days a week, 365 days a year. We're always here when you need us most.</p>
                <a href="tel:000" class="btn btn-emergency">Call Emergency: 000</a>
            </div>
        </div>
    </div>
</section>

<?php include 'includes/footer.php'; ?>