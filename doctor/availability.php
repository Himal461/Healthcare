<?php
require_once '../includes/config.php';
require_once '../includes/auth.php';
checkRole('doctor');

$pageTitle = "Set Availability - HealthManagement";
$extraCSS = '<link rel="stylesheet" href="../css/doctor.css">';
$extraJS = '<script src="../js/doctor.js"></script>';
include '../includes/header.php';

$userId = $_SESSION['user_id'];

// Get doctor ID
$stmt = $pdo->prepare("
    SELECT d.doctorId, d.specialization, CONCAT(u.firstName, ' ', u.lastName) as doctorName
    FROM doctors d 
    JOIN staff s ON d.staffId = s.staffId 
    JOIN users u ON s.userId = u.userId 
    WHERE s.userId = ?
");
$stmt->execute([$userId]);
$doctor = $stmt->fetch();

if (!$doctor) {
    $_SESSION['error'] = "Doctor profile not found.";
    header("Location: ../login.php");
    exit();
}

$doctorId = $doctor['doctorId'];

$error = null;
$success = $_SESSION['success'] ?? null;
unset($_SESSION['success']);

// Handle form submit - SAVE MONTHLY SCHEDULE
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['save_schedule'])) {
        $startDate = $_POST['start_date'] ?? '';
        $endDate = $_POST['end_date'] ?? '';
        $weekdays = $_POST['weekdays'] ?? [];
        $startTime = $_POST['start_time'] ?? '09:00';
        $endTime = $_POST['end_time'] ?? '17:00';
        
        if (!$startDate || !$endDate) {
            $error = "Please select start and end dates.";
        } elseif (empty($weekdays)) {
            $error = "Please select at least one weekday.";
        } else {
            try {
                $pdo->beginTransaction();
                
                $current = new DateTime($startDate);
                $end = new DateTime($endDate);
                $end->modify('+1 day');
                
                $insertedCount = 0;
                
                while ($current < $end) {
                    $dayOfWeek = $current->format('w');
                    
                    if (in_array($dayOfWeek, $weekdays)) {
                        $dateStr = $current->format('Y-m-d');
                        
                        $stmt = $pdo->prepare("
                            INSERT INTO doctor_availability (doctorId, availabilityDate, startTime, endTime, isAvailable, isDayOff)
                            VALUES (?, ?, ?, ?, 1, 0)
                            ON DUPLICATE KEY UPDATE
                                startTime = VALUES(startTime),
                                endTime = VALUES(endTime),
                                isAvailable = 1,
                                isDayOff = 0,
                                updatedAt = CURRENT_TIMESTAMP
                        ");
                        $stmt->execute([$doctorId, $dateStr, $startTime . ':00', $endTime . ':00']);
                        $insertedCount++;
                    }
                    
                    $current->modify('+1 day');
                }
                
                $pdo->commit();
                $_SESSION['success'] = "Schedule saved for $insertedCount days!";
                logAction($userId, 'UPDATE_AVAILABILITY', "Updated monthly schedule for doctor ID: $doctorId");
                header("Location: availability.php");
                exit();
                
            } catch (Exception $e) {
                $pdo->rollBack();
                $error = "Failed to save schedule: " . $e->getMessage();
            }
        }
    }
    
    // Handle QUICK SET - This Week
    if (isset($_POST['quick_this_week'])) {
        $startTime = $_POST['quick_start_time'] ?? '09:00';
        $endTime = $_POST['quick_end_time'] ?? '17:00';
        
        try {
            $pdo->beginTransaction();
            
            $startOfWeek = new DateTime('monday this week');
            if ($startOfWeek < new DateTime('today')) {
                $startOfWeek = new DateTime('today');
            }
            $endOfWeek = new DateTime('friday this week');
            
            $current = clone $startOfWeek;
            $insertedCount = 0;
            
            while ($current <= $endOfWeek) {
                $dateStr = $current->format('Y-m-d');
                
                $stmt = $pdo->prepare("
                    INSERT INTO doctor_availability (doctorId, availabilityDate, startTime, endTime, isAvailable, isDayOff)
                    VALUES (?, ?, ?, ?, 1, 0)
                    ON DUPLICATE KEY UPDATE
                        startTime = VALUES(startTime),
                        endTime = VALUES(endTime),
                        isAvailable = 1,
                        isDayOff = 0,
                        updatedAt = CURRENT_TIMESTAMP
                ");
                $stmt->execute([$doctorId, $dateStr, $startTime . ':00', $endTime . ':00']);
                $insertedCount++;
                
                $current->modify('+1 day');
            }
            
            $pdo->commit();
            $_SESSION['success'] = "This week's schedule set! ($insertedCount days)";
            header("Location: availability.php");
            exit();
            
        } catch (Exception $e) {
            $pdo->rollBack();
            $error = "Failed to set schedule: " . $e->getMessage();
        }
    }
    
    // Handle MARK DAY OFF
    if (isset($_POST['mark_day_off'])) {
        $dayOffDate = $_POST['day_off_date'] ?? '';
        
        if ($dayOffDate) {
            $stmt = $pdo->prepare("
                INSERT INTO doctor_availability (doctorId, availabilityDate, startTime, endTime, isAvailable, isDayOff)
                VALUES (?, ?, '00:00:00', '00:00:00', 0, 1)
                ON DUPLICATE KEY UPDATE
                    isAvailable = 0,
                    isDayOff = 1,
                    updatedAt = CURRENT_TIMESTAMP
            ");
            $stmt->execute([$doctorId, $dayOffDate]);
            
            // Cancel any existing appointments on this day
            $stmt = $pdo->prepare("
                UPDATE appointments 
                SET status = 'cancelled', cancellationReason = 'Doctor unavailable (Day Off)', updatedAt = NOW()
                WHERE doctorId = ? AND DATE(dateTime) = ? AND status IN ('scheduled', 'confirmed')
            ");
            $stmt->execute([$doctorId, $dayOffDate]);
            
            $_SESSION['success'] = "Day off marked for " . date('F j, Y', strtotime($dayOffDate));
            header("Location: availability.php");
            exit();
        }
    }
    
    // Handle REMOVE DAY OFF
    if (isset($_POST['remove_day_off'])) {
        $removeDate = $_POST['remove_date'] ?? '';
        
        if ($removeDate) {
            $stmt = $pdo->prepare("
                UPDATE doctor_availability 
                SET isDayOff = 0, isAvailable = 1, startTime = '09:00:00', endTime = '17:00:00'
                WHERE doctorId = ? AND availabilityDate = ?
            ");
            $stmt->execute([$doctorId, $removeDate]);
            
            $_SESSION['success'] = "Day off removed for " . date('F j, Y', strtotime($removeDate));
            header("Location: availability.php");
            exit();
        }
    }
    
    // Handle CLEAR ALL
    if (isset($_POST['clear_all'])) {
        $stmt = $pdo->prepare("DELETE FROM doctor_availability WHERE doctorId = ?");
        $stmt->execute([$doctorId]);
        $_SESSION['success'] = "All availability cleared!";
        header("Location: availability.php");
        exit();
    }
}

// Get existing availability
$stmt = $pdo->prepare("
    SELECT * FROM doctor_availability 
    WHERE doctorId = ? AND availabilityDate >= CURDATE()
    ORDER BY availabilityDate
");
$stmt->execute([$doctorId]);
$availabilities = $stmt->fetchAll();

// Group by month for display
$availabilityByMonth = [];
foreach ($availabilities as $avail) {
    $month = date('Y-m', strtotime($avail['availabilityDate']));
    $availabilityByMonth[$month][] = $avail;
}

// Get upcoming day offs
$dayOffs = array_filter($availabilities, fn($a) => $a['isDayOff'] == 1);
?>

<div class="doctor-container">
    <div class="doctor-page-header">
        <div class="header-title">
            <h1><i class="fas fa-calendar-alt"></i> Manage Availability</h1>
            <p>Set your working hours and days off</p>
        </div>
    </div>

    <?php if ($error): ?>
        <div class="doctor-alert doctor-alert-error">
            <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?>
        </div>
    <?php endif; ?>
    
    <?php if ($success): ?>
        <div class="doctor-alert doctor-alert-success">
            <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success); ?>
        </div>
    <?php endif; ?>

    <!-- Quick Actions Row -->
    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 20px;">
        <!-- Quick Set This Week -->
        <div class="doctor-card">
            <div class="doctor-card-header">
                <h3><i class="fas fa-calendar-week"></i> Quick Set This Week</h3>
            </div>
            <div class="doctor-card-body" style="padding: 20px;">
                <form method="POST" style="display: flex; align-items: flex-end; gap: 15px; flex-wrap: wrap;">
                    <div style="flex: 1; min-width: 120px;">
                        <label style="font-size: 13px; color: #64748b;">Start Time</label>
                        <select name="quick_start_time" class="doctor-form-control" style="padding: 10px;">
                            <?php for ($h = 8; $h <= 12; $h++): ?>
                                <option value="<?php echo sprintf('%02d:00', $h); ?>" <?php echo $h == 9 ? 'selected' : ''; ?>>
                                    <?php echo date('g:i A', strtotime(sprintf('%02d:00', $h))); ?>
                                </option>
                            <?php endfor; ?>
                        </select>
                    </div>
                    <div style="flex: 1; min-width: 120px;">
                        <label style="font-size: 13px; color: #64748b;">End Time</label>
                        <select name="quick_end_time" class="doctor-form-control" style="padding: 10px;">
                            <?php for ($h = 13; $h <= 20; $h++): ?>
                                <option value="<?php echo sprintf('%02d:00', $h); ?>" <?php echo $h == 17 ? 'selected' : ''; ?>>
                                    <?php echo date('g:i A', strtotime(sprintf('%02d:00', $h))); ?>
                                </option>
                            <?php endfor; ?>
                        </select>
                    </div>
                    <button type="submit" name="quick_this_week" class="doctor-btn doctor-btn-primary" style="padding: 10px 20px;">
                        <i class="fas fa-check"></i> Set Mon-Fri
                    </button>
                </form>
                <small style="color: #64748b; display: block; margin-top: 10px;">
                    Sets availability for Monday through Friday of this week
                </small>
            </div>
        </div>

        <!-- Mark Day Off -->
        <div class="doctor-card">
            <div class="doctor-card-header">
                <h3><i class="fas fa-calendar-times"></i> Mark Day Off</h3>
            </div>
            <div class="doctor-card-body" style="padding: 20px;">
                <form method="POST" style="display: flex; align-items: flex-end; gap: 15px; flex-wrap: wrap;">
                    <div style="flex: 2; min-width: 180px;">
                        <label style="font-size: 13px; color: #64748b;">Select Date</label>
                        <input type="date" name="day_off_date" class="doctor-form-control" style="padding: 10px;" required min="<?php echo date('Y-m-d'); ?>">
                    </div>
                    <button type="submit" name="mark_day_off" class="doctor-btn doctor-btn-warning" style="padding: 10px 20px;">
                        <i class="fas fa-ban"></i> Mark Day Off
                    </button>
                </form>
                <small style="color: #ef4444; display: block; margin-top: 10px;">
                    <i class="fas fa-exclamation-triangle"></i> Existing appointments will be cancelled
                </small>
            </div>
        </div>
    </div>

    <!-- Custom Date Range Schedule -->
    <div class="doctor-card">
        <div class="doctor-card-header">
            <h3><i class="fas fa-calendar-range"></i> Custom Date Range Schedule</h3>
        </div>
        <div class="doctor-card-body" style="padding: 20px;">
            <form method="POST">
                <div style="display: grid; grid-template-columns: 1fr 1fr 1fr 1fr; gap: 15px; align-items: end;">
                    <div>
                        <label style="font-size: 13px; color: #64748b;">Start Date</label>
                        <input type="date" name="start_date" class="doctor-form-control" style="padding: 10px;" min="<?php echo date('Y-m-d'); ?>">
                    </div>
                    <div>
                        <label style="font-size: 13px; color: #64748b;">End Date</label>
                        <input type="date" name="end_date" class="doctor-form-control" style="padding: 10px;" min="<?php echo date('Y-m-d'); ?>">
                    </div>
                    <div>
                        <label style="font-size: 13px; color: #64748b;">Start Time</label>
                        <select name="start_time" class="doctor-form-control" style="padding: 10px;">
                            <?php for ($h = 8; $h <= 18; $h++): ?>
                                <option value="<?php echo sprintf('%02d:00', $h); ?>" <?php echo $h == 9 ? 'selected' : ''; ?>>
                                    <?php echo date('g:i A', strtotime(sprintf('%02d:00', $h))); ?>
                                </option>
                            <?php endfor; ?>
                        </select>
                    </div>
                    <div>
                        <label style="font-size: 13px; color: #64748b;">End Time</label>
                        <select name="end_time" class="doctor-form-control" style="padding: 10px;">
                            <?php for ($h = 12; $h <= 20; $h++): ?>
                                <option value="<?php echo sprintf('%02d:00', $h); ?>" <?php echo $h == 17 ? 'selected' : ''; ?>>
                                    <?php echo date('g:i A', strtotime(sprintf('%02d:00', $h))); ?>
                                </option>
                            <?php endfor; ?>
                        </select>
                    </div>
                </div>
                
                <div style="margin-top: 15px;">
                    <label style="font-size: 13px; color: #64748b; margin-bottom: 8px; display: block;">Working Days</label>
                    <div style="display: flex; flex-wrap: wrap; gap: 12px;">
                        <?php
                        $weekdays = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
                        foreach ($weekdays as $index => $day): 
                            $isWeekday = ($index >= 1 && $index <= 5);
                        ?>
                            <label style="display: flex; align-items: center; gap: 5px; cursor: pointer; padding: 5px 10px; background: <?php echo $isWeekday ? '#f0fdf4' : '#fef3c7'; ?>; border-radius: 20px;">
                                <input type="checkbox" name="weekdays[]" value="<?php echo $index; ?>" <?php echo $isWeekday ? 'checked' : ''; ?>>
                                <span style="font-size: 13px;"><?php echo $day; ?></span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>
                
                <div style="margin-top: 20px; display: flex; gap: 10px;">
                    <button type="submit" name="save_schedule" class="doctor-btn doctor-btn-primary">
                        <i class="fas fa-save"></i> Save Schedule
                    </button>
                    <button type="submit" name="clear_all" class="doctor-btn doctor-btn-danger" onclick="return confirm('Clear ALL availability? This cannot be undone.');">
                        <i class="fas fa-trash"></i> Clear All
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Current Schedule -->
    <div class="doctor-card">
        <div class="doctor-card-header">
            <h3><i class="fas fa-calendar-check"></i> Your Upcoming Schedule</h3>
        </div>
        <div class="doctor-card-body" style="padding: 20px;">
            <?php if (empty($availabilities)): ?>
                <div style="text-align: center; padding: 40px;">
                    <i class="fas fa-calendar-alt" style="font-size: 48px; color: #cbd5e1; margin-bottom: 15px;"></i>
                    <p style="color: #64748b;">No availability set yet. Use the forms above to set your schedule.</p>
                </div>
            <?php else: ?>
                <!-- Upcoming Day Offs Summary -->
                <?php if (!empty($dayOffs)): ?>
                    <div style="margin-bottom: 20px; padding: 15px; background: #fef2f2; border-radius: 12px;">
                        <h4 style="color: #991b1b; margin-bottom: 10px; font-size: 14px;">
                            <i class="fas fa-calendar-times"></i> Upcoming Days Off
                        </h4>
                        <div style="display: flex; flex-wrap: wrap; gap: 10px;">
                            <?php foreach (array_slice($dayOffs, 0, 10) as $dayOff): ?>
                                <div style="display: flex; align-items: center; gap: 8px; background: white; padding: 5px 12px; border-radius: 20px;">
                                    <span style="font-size: 13px;"><?php echo date('M j, Y', strtotime($dayOff['availabilityDate'])); ?></span>
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="remove_date" value="<?php echo $dayOff['availabilityDate']; ?>">
                                        <button type="submit" name="remove_day_off" style="background: none; border: none; color: #ef4444; cursor: pointer; padding: 0; font-size: 14px;" title="Remove day off">
                                            <i class="fas fa-times-circle"></i>
                                        </button>
                                    </form>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>
                
                <!-- Monthly Schedule -->
                <?php foreach ($availabilityByMonth as $month => $days): ?>
                    <h4 style="color: #1e293b; margin: 20px 0 12px; font-size: 16px;">
                        <?php echo date('F Y', strtotime($month . '-01')); ?>
                        <span style="font-size: 13px; color: #64748b; margin-left: 10px;">
                            <?php echo count($days); ?> days scheduled
                        </span>
                    </h4>
                    <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(130px, 1fr)); gap: 8px;">
                        <?php foreach ($days as $day): 
                            $isDayOff = $day['isDayOff'] == 1;
                            $bgColor = $isDayOff ? '#fee2e2' : '#dcfce7';
                            $borderColor = $isDayOff ? '#ef4444' : '#10b981';
                            $textColor = $isDayOff ? '#991b1b' : '#166534';
                        ?>
                            <div style="padding: 8px 10px; background: <?php echo $bgColor; ?>; border-radius: 6px; border-left: 3px solid <?php echo $borderColor; ?>;">
                                <strong style="font-size: 13px;"><?php echo date('D, M j', strtotime($day['availabilityDate'])); ?></strong>
                                <?php if ($isDayOff): ?>
                                    <span style="color: <?php echo $textColor; ?>; display: block; font-size: 11px;">Day Off</span>
                                <?php else: ?>
                                    <span style="color: <?php echo $textColor; ?>; display: block; font-size: 11px;">
                                        <?php echo date('g:i A', strtotime($day['startTime'])); ?> - 
                                        <?php echo date('g:i A', strtotime($day['endTime'])); ?>
                                    </span>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<style>
.doctor-btn-warning {
    background: #f59e0b;
    color: white;
}
.doctor-btn-warning:hover {
    background: #d97706;
}
</style>

<?php include '../includes/footer.php'; ?>