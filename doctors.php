<?php
require_once 'includes/config.php';
require_once 'includes/auth.php';

$pageTitle = "Find a Doctor - HealthManagement";
include 'includes/header.php';

// Define all medical specializations with descriptions
$specializationsList = [
    'Cardiology' => 'Heart and Cardiovascular System',
    'Neurology' => 'Brain and Nervous System',
    'Pediatrics' => 'Child Healthcare',
    'Orthopedics' => 'Bone and Joint',
    'Dermatology' => 'Skin Care',
    'Ophthalmology' => 'Eye Care',
    'Obstetrics & Gynecology' => 'Women\'s Health',
    'Radiology' => 'Medical Imaging',
    'Emergency Medicine' => 'Emergency Care',
    'Primary Care' => 'General Medicine',
    'Urology' => 'Urinary Tract and Male Reproductive Health',
    'Gastroenterology' => 'Digestive System',
    'Pulmonology' => 'Respiratory System',
    'Endocrinology' => 'Hormone and Metabolic Disorders',
    'Oncology' => 'Cancer Treatment',
    'Psychiatry' => 'Mental Health',
    'Nephrology' => 'Kidney Care',
    'Rheumatology' => 'Autoimmune and Joint Disorders',
    'Infectious Disease' => 'Infection Management',
    'Hematology' => 'Blood Disorders'
];

// Get filters
$specialization = $_GET['specialization'] ?? '';
$search = $_GET['search'] ?? '';

// Build query
$query = "
    SELECT d.*, u.firstName, u.lastName, u.email, u.phoneNumber,
           d.specialization, d.yearsOfExperience, d.consultationFee,
           d.biography, d.education, d.isAvailable
    FROM doctors d
    JOIN staff s ON d.staffId = s.staffId
    JOIN users u ON s.userId = u.userId
    WHERE d.isAvailable = 1
";

$params = [];

if ($specialization) {
    $query .= " AND d.specialization = ?";
    $params[] = $specialization;
}

if ($search) {
    $query .= " AND (u.firstName LIKE ? OR u.lastName LIKE ? OR d.specialization LIKE ?)";
    $searchTerm = "%$search%";
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $params[] = $searchTerm;
}

$query .= " ORDER BY u.firstName, u.lastName";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$doctors = $stmt->fetchAll();

// Get all specializations for filter
$specializations = $pdo->query("SELECT DISTINCT specialization FROM doctors WHERE specialization IS NOT NULL")->fetchAll();
?>

<section class="page-header">
    <div class="container">
        <h1>Find a Doctor</h1>
        <p>Search for the right healthcare provider for your needs</p>
    </div>
</section>

<section class="doctor-search">
    <div class="container">
        <div class="search-filters">
            <form method="GET" action="">
                <div class="filter-group">
                    <input type="text" name="search" placeholder="Search by name or specialty" value="<?php echo htmlspecialchars($search); ?>">
                </div>
                <div class="filter-group">
                    <select name="specialization">
                        <option value="">All Specializations</option>
                        <?php foreach ($specializations as $spec): ?>
                            <option value="<?php echo htmlspecialchars($spec['specialization']); ?>" 
                                <?php echo $specialization == $spec['specialization'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($spec['specialization']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <button type="submit" class="btn btn-primary">Search</button>
                <?php if ($search || $specialization): ?>
                    <a href="doctors.php" class="btn btn-outline">Clear Filters</a>
                <?php endif; ?>
            </form>
        </div>

        <div class="doctors-grid">
            <?php if (empty($doctors)): ?>
                <div class="no-results">
                    <i class="fas fa-user-md"></i>
                    <h3>No doctors found</h3>
                    <p>Try adjusting your search criteria or check back later.</p>
                </div>
            <?php else: ?>
                <?php foreach ($doctors as $doctor): ?>
                    <div class="doctor-card">
                        <div class="doctor-avatar">
                            <i class="fas fa-user-md"></i>
                        </div>
                        <div class="doctor-info">
                            <h3>Dr. <?php echo htmlspecialchars($doctor['firstName'] . ' ' . $doctor['lastName']); ?></h3>
                            <p class="doctor-specialty"><?php echo htmlspecialchars($doctor['specialization']); ?></p>
                            <p class="doctor-experience"><?php echo $doctor['yearsOfExperience']; ?>+ years experience</p>
                            <div class="doctor-details">
                                <p><i class="fas fa-phone"></i> <?php echo htmlspecialchars($doctor['phoneNumber']); ?></p>
                                <p><i class="fas fa-envelope"></i> <?php echo htmlspecialchars($doctor['email']); ?></p>
                                <p><i class="fas fa-dollar-sign"></i> $<?php echo number_format($doctor['consultationFee'], 2); ?></p>
                            </div>
                            <div class="doctor-bio">
                                <p><?php echo htmlspecialchars(substr($doctor['biography'] ?: 'Experienced doctor providing quality healthcare services.', 0, 120)) . (strlen($doctor['biography'] ?? '') > 120 ? '...' : ''); ?></p>
                            </div>
                            <?php if ($doctor['education']): ?>
                            <div class="doctor-education">
                                <p><i class="fas fa-graduation-cap"></i> <?php echo htmlspecialchars(substr($doctor['education'], 0, 60)) . (strlen($doctor['education']) > 60 ? '...' : ''); ?></p>
                            </div>
                            <?php endif; ?>
                            <div class="doctor-actions">
                                <?php if (isLoggedIn() && $_SESSION['user_role'] === 'patient'): ?>
                                    <a href="patient/appointments.php?doctor_id=<?php echo $doctor['doctorId']; ?>" class="btn btn-primary btn-block">
                                        <i class="fas fa-calendar-plus"></i> Book Appointment
                                    </a>
                                <?php elseif (!isLoggedIn()): ?>
                                    <a href="login.php" class="btn btn-primary btn-block">Login to Book</a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</section>

<style>
/* Page Header */
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

.page-header p {
    font-size: 18px;
    opacity: 0.9;
}

/* Search Filters */
.doctor-search {
    padding: 60px 0;
    background: #f8f9fa;
}

.search-filters {
    background: white;
    padding: 25px;
    border-radius: 12px;
    margin-bottom: 40px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.05);
}

.search-filters form {
    display: flex;
    gap: 15px;
    flex-wrap: wrap;
    align-items: flex-end;
}

.filter-group {
    flex: 1;
    min-width: 200px;
}

.filter-group input,
.filter-group select {
    width: 100%;
    padding: 12px 15px;
    border: 1px solid #ddd;
    border-radius: 8px;
    font-size: 14px;
    transition: all 0.3s ease;
}

.filter-group input:focus,
.filter-group select:focus {
    outline: none;
    border-color: #1a75bc;
    box-shadow: 0 0 0 3px rgba(26,117,188,0.1);
}

/* Doctors Grid - 3 columns per row */
.doctors-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 30px;
}

/* Doctor Card */
.doctor-card {
    background: white;
    border-radius: 16px;
    overflow: hidden;
    box-shadow: 0 3px 15px rgba(0,0,0,0.08);
    transition: transform 0.3s ease, box-shadow 0.3s ease;
    display: flex;
    flex-direction: column;
    height: 100%;
}

.doctor-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 10px 30px rgba(0,0,0,0.12);
}

/* Doctor Avatar */
.doctor-avatar {
    background: linear-gradient(135deg, #1a75bc 0%, #0a4299 100%);
    padding: 30px 0;
    text-align: center;
}

.doctor-avatar i {
    font-size: 80px;
    color: white;
}

/* Doctor Info */
.doctor-info {
    padding: 20px;
    flex: 1;
    display: flex;
    flex-direction: column;
}

.doctor-info h3 {
    font-size: 20px;
    color: #1a75bc;
    margin-bottom: 5px;
    text-align: center;
}

.doctor-specialty {
    color: #28a745;
    font-size: 14px;
    font-weight: 500;
    margin-bottom: 5px;
    text-align: center;
}

.doctor-experience {
    color: #666;
    font-size: 12px;
    margin-bottom: 15px;
    text-align: center;
}

/* Doctor Details */
.doctor-details {
    margin-bottom: 15px;
    padding: 10px 0;
    border-top: 1px solid #e9ecef;
    border-bottom: 1px solid #e9ecef;
}

.doctor-details p {
    margin: 8px 0;
    color: #555;
    font-size: 13px;
    display: flex;
    align-items: center;
    gap: 8px;
    word-break: break-all;
}

.doctor-details i {
    width: 16px;
    color: #1a75bc;
    font-size: 12px;
    flex-shrink: 0;
}

/* Doctor Bio */
.doctor-bio {
    margin-bottom: 15px;
    color: #666;
    line-height: 1.5;
    font-size: 13px;
    flex: 1;
}

.doctor-bio p {
    margin: 0;
}

/* Doctor Education */
.doctor-education {
    margin-bottom: 15px;
    padding: 10px;
    background: #f8f9fa;
    border-radius: 8px;
    border-left: 3px solid #1a75bc;
}

.doctor-education p {
    margin: 0;
    color: #555;
    font-size: 12px;
    line-height: 1.4;
}

.doctor-education i {
    color: #1a75bc;
    margin-right: 5px;
}

/* Doctor Actions */
.doctor-actions {
    margin-top: auto;
}

.btn-block {
    width: 100%;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    padding: 10px 15px;
    font-size: 14px;
    font-weight: 500;
    border-radius: 8px;
    text-decoration: none;
    transition: all 0.3s ease;
    cursor: pointer;
    border: none;
}

.btn-primary {
    background: #1a75bc;
    color: white;
}

.btn-primary:hover {
    background: #0a5a9a;
    transform: translateY(-2px);
}

/* No Results */
.no-results {
    grid-column: 1 / -1;
    text-align: center;
    padding: 80px 40px;
    background: white;
    border-radius: 16px;
}

.no-results i {
    font-size: 80px;
    color: #1a75bc;
    margin-bottom: 20px;
    opacity: 0.5;
}

.no-results h3 {
    font-size: 24px;
    margin-bottom: 10px;
    color: #333;
}

.no-results p {
    color: #666;
}

/* Responsive Design */
@media (max-width: 1200px) {
    .doctors-grid {
        grid-template-columns: repeat(2, 1fr);
        gap: 25px;
    }
}

@media (max-width: 768px) {
    .page-header {
        padding: 40px 0;
    }
    
    .page-header h1 {
        font-size: 32px;
    }
    
    .search-filters form {
        flex-direction: column;
    }
    
    .filter-group {
        width: 100%;
    }
    
    .doctors-grid {
        grid-template-columns: 1fr;
        gap: 20px;
    }
    
    .doctor-card {
        max-width: 100%;
    }
    
    .doctor-avatar i {
        font-size: 60px;
    }
    
    .doctor-info h3 {
        font-size: 18px;
    }
}

@media (max-width: 480px) {
    .doctor-card {
        margin: 0 10px;
    }
    
    .doctor-details p {
        font-size: 12px;
        word-break: break-word;
    }
    
    .doctor-bio p {
        font-size: 12px;
    }
}
</style>

<?php include 'includes/footer.php'; ?>