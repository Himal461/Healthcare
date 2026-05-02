<?php
require_once '../includes/config.php';

$doctorId = (int)($_GET['doctor_id'] ?? 0);

if (!$doctorId) {
    echo "Please provide doctor_id parameter";
    exit();
}

echo "<h2>Doctor Availability Debug - Doctor ID: $doctorId</h2>";

// Check doctor exists
$stmt = $pdo->prepare("
    SELECT d.doctorId, u.firstName, u.lastName 
    FROM doctors d
    JOIN staff s ON d.staffId = s.staffId
    JOIN users u ON s.userId = u.userId
    WHERE d.doctorId = ?
");
$stmt->execute([$doctorId]);
$doctor = $stmt->fetch();

if (!$doctor) {
    echo "<p style='color:red'>Doctor not found!</p>";
    exit();
}

echo "<p>Doctor: Dr. {$doctor['firstName']} {$doctor['lastName']}</p>";

// Check all availability records
$stmt = $pdo->prepare("
    SELECT * FROM doctor_availability 
    WHERE doctorId = ?
    ORDER BY availabilityDate ASC
");
$stmt->execute([$doctorId]);
$availabilities = $stmt->fetchAll();

echo "<h3>All Availability Records (" . count($availabilities) . ")</h3>";
echo "<table border='1' cellpadding='5'>";
echo "<tr><th>Date</th><th>Start</th><th>End</th><th>Available</th><th>Day Off</th></tr>";

foreach ($availabilities as $a) {
    $color = $a['isAvailable'] && !$a['isDayOff'] ? '#d4edda' : '#f8d7da';
    echo "<tr style='background:$color'>";
    echo "<td>{$a['availabilityDate']}</td>";
    echo "<td>{$a['startTime']}</td>";
    echo "<td>{$a['endTime']}</td>";
    echo "<td>" . ($a['isAvailable'] ? 'Yes' : 'No') . "</td>";
    echo "<td>" . ($a['isDayOff'] ? 'Yes' : 'No') . "</td>";
    echo "</tr>";
}
echo "</table>";

// Check future availability
$stmt = $pdo->prepare("
    SELECT * FROM doctor_availability 
    WHERE doctorId = ? 
    AND availabilityDate >= CURDATE()
    AND isAvailable = 1 
    AND isDayOff = 0
    ORDER BY availabilityDate ASC
    LIMIT 10
");
$stmt->execute([$doctorId]);
$futureAvail = $stmt->fetchAll();

echo "<h3>Future Available Dates (" . count($futureAvail) . ")</h3>";
echo "<table border='1' cellpadding='5'>";
echo "<tr><th>Date</th><th>Start</th><th>End</th></tr>";

foreach ($futureAvail as $a) {
    echo "<tr>";
    echo "<td>{$a['availabilityDate']}</td>";
    echo "<td>{$a['startTime']}</td>";
    echo "<td>{$a['endTime']}</td>";
    echo "</tr>";
}
echo "</table>";

// Check appointments
$stmt = $pdo->prepare("
    SELECT DATE(dateTime) as date, TIME(dateTime) as time, status
    FROM appointments 
    WHERE doctorId = ? 
    AND dateTime >= NOW()
    AND status NOT IN ('cancelled', 'no-show')
    ORDER BY dateTime ASC
    LIMIT 20
");
$stmt->execute([$doctorId]);
$appointments = $stmt->fetchAll();

echo "<h3>Upcoming Appointments (" . count($appointments) . ")</h3>";
echo "<table border='1' cellpadding='5'>";
echo "<tr><th>Date</th><th>Time</th><th>Status</th></tr>";

foreach ($appointments as $a) {
    echo "<tr>";
    echo "<td>{$a['date']}</td>";
    echo "<td>{$a['time']}</td>";
    echo "<td>{$a['status']}</td>";
    echo "</tr>";
}
echo "</table>";
?>