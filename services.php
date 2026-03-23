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
                <a href="tel:911" class="btn btn-emergency">Call Emergency: 911</a>
            </div>
        </div>
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

.services-detailed {
    padding: 60px 0;
    background: #f8f9fa;
}

.services-grid-detailed {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
    gap: 30px;
}

.service-detailed-card {
    background: white;
    border-radius: 12px;
    padding: 30px;
    box-shadow: 0 3px 15px rgba(0,0,0,0.1);
    transition: transform 0.3s ease;
}

.service-detailed-card:hover {
    transform: translateY(-5px);
}

.service-icon-large {
    width: 80px;
    height: 80px;
    background: linear-gradient(135deg, #1a75bc 0%, #0a4299 100%);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    margin-bottom: 20px;
}

.service-icon-large i {
    font-size: 40px;
    color: white;
}

.service-detailed-card h2 {
    color: #1a75bc;
    margin-bottom: 15px;
    font-size: 24px;
}

.service-detailed-card p {
    color: #666;
    margin-bottom: 20px;
    line-height: 1.6;
}

.service-features {
    list-style: none;
    padding: 0;
}

.service-features li {
    padding: 8px 0;
    color: #555;
    display: flex;
    align-items: center;
    gap: 10px;
}

.service-features li i {
    color: #28a745;
}

.emergency-section {
    padding: 60px 0;
    background: #fff;
}

.emergency-card {
    background: linear-gradient(135deg, #dc3545 0%, #c82333 100%);
    border-radius: 12px;
    padding: 40px;
    color: white;
    display: flex;
    align-items: center;
    gap: 30px;
    flex-wrap: wrap;
}

.emergency-icon i {
    font-size: 60px;
}

.emergency-content h2 {
    margin-bottom: 10px;
}

.btn-emergency {
    background: white;
    color: #dc3545;
    margin-top: 15px;
    display: inline-block;
}

.btn-emergency:hover {
    background: #f8f9fa;
    transform: translateY(-2px);
}

@media (max-width: 768px) {
    .services-grid-detailed {
        grid-template-columns: 1fr;
    }
    
    .emergency-card {
        flex-direction: column;
        text-align: center;
    }
}
</style>

<?php include 'includes/footer.php'; ?>