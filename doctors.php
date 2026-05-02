<?php
require_once 'includes/config.php';
require_once 'includes/auth.php';

$pageTitle = "Find a Doctor - HealthManagement";
$extraCSS = '<link rel="stylesheet" href="css/root.css">';
$extraJS = '<script src="js/root.js"></script>';
include 'includes/header.php';

$specialization = $_GET['specialization'] ?? '';
$search = $_GET['search'] ?? '';

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
    // Split search term into individual words
    $searchTerms = explode(' ', trim($search));
    $searchConditions = [];
    
    foreach ($searchTerms as $term) {
        if (!empty($term)) {
            $termConditions = [];
            // Search in first name, last name, full name, and specialization
            $termConditions[] = "u.firstName LIKE ?";
            $termConditions[] = "u.lastName LIKE ?";
            $termConditions[] = "CONCAT(u.firstName, ' ', u.lastName) LIKE ?";
            $termConditions[] = "d.specialization LIKE ?";
            
            $searchConditions[] = "(" . implode(' OR ', $termConditions) . ")";
            
            $searchPattern = "%$term%";
            // Add params for each condition (4 params per term)
            $params[] = $searchPattern; // firstName
            $params[] = $searchPattern; // lastName
            $params[] = $searchPattern; // fullName
            $params[] = $searchPattern; // specialization
        }
    }
    
    if (!empty($searchConditions)) {
        $query .= " AND (" . implode(' AND ', $searchConditions) . ")";
    }
}

$query .= " ORDER BY u.firstName, u.lastName";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$doctors = $stmt->fetchAll();

$specializations = $pdo->query("SELECT DISTINCT specialization FROM doctors WHERE specialization IS NOT NULL ORDER BY specialization")->fetchAll();
?>

<div class="root-container">
    <div class="root-page-header">
        <div class="header-title">
            <h1><i class="fas fa-user-md"></i> Find a Doctor</h1>
            <p>Search for the right healthcare provider for your needs</p>
        </div>
    </div>

    <div class="root-search-filters">
        <form method="GET" class="root-search-form">
            <div class="root-filter-group">
                <div class="root-search-input-wrapper">
                    <i class="fas fa-search root-search-icon"></i>
                    <input type="text" name="search" placeholder="Search by doctor name (first, last, or full name) or specialty" value="<?php echo htmlspecialchars($search); ?>" class="root-form-control root-search-input">
                </div>
            </div>
            <div class="root-filter-group">
                <select name="specialization" class="root-form-control">
                    <option value="">All Specializations</option>
                    <?php foreach ($specializations as $spec): ?>
                        <option value="<?php echo htmlspecialchars($spec['specialization']); ?>" <?php echo $specialization == $spec['specialization'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($spec['specialization']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <button type="submit" class="root-btn root-btn-primary">
                <i class="fas fa-search"></i> Search
            </button>
            <?php if ($search || $specialization): ?>
                <a href="doctors.php" class="root-btn root-btn-outline">
                    <i class="fas fa-times"></i> Clear Filters
                </a>
            <?php endif; ?>
        </form>
        
        <?php if ($search): ?>
            <div class="root-search-info">
                <p><i class="fas fa-info-circle"></i> Showing results for: <strong><?php echo htmlspecialchars($search); ?></strong></p>
            </div>
        <?php endif; ?>
    </div>

    <?php if (empty($doctors)): ?>
        <div class="root-no-results">
            <i class="fas fa-user-md"></i>
            <h3>No doctors found</h3>
            <p>Try adjusting your search criteria or check back later.</p>
            <?php if ($search): ?>
                <div class="root-search-suggestions">
                    <p>Suggestions:</p>
                    <ul>
                        <li>Try searching with just the first name or last name</li>
                        <li>Check for spelling errors</li>
                        <li>Try a different specialty</li>
                        <li>Clear filters to see all doctors</li>
                    </ul>
                </div>
            <?php endif; ?>
        </div>
    <?php else: ?>
        <div class="root-results-count">
            <p><i class="fas fa-users"></i> Found <?php echo count($doctors); ?> doctor(s)</p>
        </div>
        <div class="root-doctors-grid">
            <?php foreach ($doctors as $doctor): ?>
                <div class="root-doctor-card">
                    <div class="root-doctor-avatar">
                        <i class="fas fa-user-circle"></i>
                    </div>
                    <div class="root-doctor-info">
                        <h3>Dr. <?php echo htmlspecialchars($doctor['firstName'] . ' ' . $doctor['lastName']); ?></h3>
                        <p class="root-doctor-specialty">
                            <i class="fas fa-stethoscope"></i> 
                            <?php echo htmlspecialchars($doctor['specialization']); ?>
                        </p>
                        <p class="root-doctor-experience">
                            <i class="fas fa-briefcase"></i> 
                            <?php echo $doctor['yearsOfExperience']; ?>+ years experience
                        </p>
                        <div class="root-doctor-details">
                            <p><i class="fas fa-envelope"></i> <?php echo htmlspecialchars($doctor['email']); ?></p>
                            <p><i class="fas fa-dollar-sign"></i> $<?php echo number_format($doctor['consultationFee'], 2); ?></p>
                        </div>
                        <div class="root-doctor-bio">
                            <p><?php echo htmlspecialchars(substr($doctor['biography'] ?: 'Experienced doctor providing quality healthcare services.', 0, 120)) . (strlen($doctor['biography'] ?? '') > 120 ? '...' : ''); ?></p>
                        </div>
                        <?php if ($doctor['education']): ?>
                        <div class="root-doctor-education">
                            <p><i class="fas fa-graduation-cap"></i> <?php echo htmlspecialchars(substr($doctor['education'], 0, 60)) . (strlen($doctor['education']) > 60 ? '...' : ''); ?></p>
                        </div>
                        <?php endif; ?>
                        <div class="root-doctor-actions">
                            <a href="doctor-profile.php?id=<?php echo $doctor['doctorId']; ?>" class="root-btn root-btn-outline root-btn-block root-btn-view-profile">
                                <i class="fas fa-id-card"></i> View Profile
                            </a>
                            <?php if (isLoggedIn() && $_SESSION['user_role'] === 'patient'): ?>
                                <a href="patient/appointments.php?doctor_id=<?php echo $doctor['doctorId']; ?>" class="root-btn root-btn-primary root-btn-block">
                                    <i class="fas fa-calendar-plus"></i> Book Appointment
                                </a>
                            <?php elseif (!isLoggedIn()): ?>
                                <a href="login.php" class="root-btn root-btn-primary root-btn-block">
                                    <i class="fas fa-sign-in-alt"></i> Login to Book
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>


<?php include 'includes/footer.php'; ?>