<?php
require_once 'includes/config.php';
require_once 'includes/auth.php';

$pageTitle = "Find a Doctor - HealthManagement";
include 'includes/header.php';

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
                    <div class="doctor-card-detailed">
                        <div class="doctor-avatar-large">
                            <i class="fas fa-user-md"></i>
                        </div>
                        <div class="doctor-info">
                            <h3>Dr. <?php echo htmlspecialchars($doctor['firstName'] . ' ' . $doctor['lastName']); ?></h3>
                            <p class="doctor-specialty"><?php echo htmlspecialchars($doctor['specialization']); ?></p>
                            <p class="doctor-experience"><?php echo $doctor['yearsOfExperience']; ?>+ years experience</p>
                            <div class="doctor-details">
                                <p><i class="fas fa-phone"></i> <?php echo htmlspecialchars($doctor['phoneNumber']); ?></p>
                                <p><i class="fas fa-envelope"></i> <?php echo htmlspecialchars($doctor['email']); ?></p>
                                <p><i class="fas fa-dollar-sign"></i> Consultation Fee: $<?php echo number_format($doctor['consultationFee'], 2); ?></p>
                            </div>
                            <div class="doctor-bio">
                                <p><?php echo htmlspecialchars($doctor['biography'] ?: 'Experienced doctor providing quality healthcare services.'); ?></p>
                            </div>
                            <?php if (isLoggedIn() && $_SESSION['user_role'] === 'patient'): ?>
                                <a href="patient/appointments.php?doctor_id=<?php echo $doctor['doctorId']; ?>" class="btn btn-primary">
                                    Book Appointment
                                </a>
                            <?php elseif (!isLoggedIn()): ?>
                                <a href="login.php" class="btn btn-primary">Login to Book</a>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
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

.doctor-search {
    padding: 60px 0;
}

.search-filters {
    margin-bottom: 40px;
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
    padding: 12px;
    border: 1px solid #ddd;
    border-radius: 8px;
    font-size: 14px;
}

.doctors-grid {
    display: flex;
    flex-direction: column;
    gap: 30px;
}

.doctor-card-detailed {
    display: flex;
    gap: 30px;
    background: white;
    border-radius: 12px;
    padding: 30px;
    box-shadow: 0 3px 15px rgba(0,0,0,0.1);
    transition: transform 0.3s ease;
}

.doctor-card-detailed:hover {
    transform: translateY(-2px);
    box-shadow: 0 5px 20px rgba(0,0,0,0.15);
}

.doctor-avatar-large {
    width: 150px;
    height: 150px;
    background: linear-gradient(135deg, #1a75bc 0%, #0a4299 100%);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}

.doctor-avatar-large i {
    font-size: 80px;
    color: white;
}

.doctor-info {
    flex: 1;
}

.doctor-info h3 {
    font-size: 24px;
    color: #1a75bc;
    margin-bottom: 5px;
}

.doctor-specialty {
    color: #666;
    font-size: 16px;
    margin-bottom: 5px;
}

.doctor-experience {
    color: #28a745;
    font-weight: 500;
    margin-bottom: 15px;
}

.doctor-details {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 10px;
    margin-bottom: 15px;
    padding: 15px 0;
    border-top: 1px solid #e9ecef;
    border-bottom: 1px solid #e9ecef;
}

.doctor-details p {
    margin: 0;
    color: #555;
}

.doctor-details i {
    width: 20px;
    color: #1a75bc;
}

.doctor-bio {
    margin-bottom: 20px;
    color: #666;
    line-height: 1.6;
}

.no-results {
    text-align: center;
    padding: 60px;
    background: white;
    border-radius: 12px;
}

.no-results i {
    font-size: 60px;
    color: #1a75bc;
    margin-bottom: 20px;
}

.no-results h3 {
    font-size: 24px;
    margin-bottom: 10px;
}

.btn-outline {
    background: transparent;
    border: 1px solid #1a75bc;
    color: #1a75bc;
}

.btn-outline:hover {
    background: #1a75bc;
    color: white;
}

@media (max-width: 768px) {
    .doctor-card-detailed {
        flex-direction: column;
        align-items: center;
        text-align: center;
    }
    
    .doctor-details {
        grid-template-columns: 1fr;
    }
    
    .search-filters form {
        flex-direction: column;
    }
    
    .filter-group {
        width: 100%;
    }
}
</style>

<?php include 'includes/footer.php'; ?>