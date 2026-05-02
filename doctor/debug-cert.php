<?php
require_once '../includes/config.php';
require_once '../includes/auth.php';
checkRole('doctor');

$userId = $_SESSION['user_id'];

// Get doctor info
$stmt = $pdo->prepare("
    SELECT d.doctorId, u.username, u.firstName, u.lastName
    FROM doctors d 
    JOIN staff s ON d.staffId = s.staffId 
    JOIN users u ON s.userId = u.userId 
    WHERE s.userId = ?
");
$stmt->execute([$userId]);
$doctor = $stmt->fetch();
$doctorId = (int)$doctor['doctorId'];

echo "<h2>Your Doctor Info</h2>";
echo "<p>Doctor ID (raw): " . var_export($doctor['doctorId'], true) . " | Type: " . gettype($doctor['doctorId']) . "</p>";
echo "<p>Doctor ID (cast): " . $doctorId . " | Type: " . gettype($doctorId) . "</p>";

echo "<h2>All Medical Certificates</h2>";
$certs = $pdo->query("
    SELECT mc.*, a.doctorId as appt_doctor_id, a.status as appt_status
    FROM medical_certificates mc
    LEFT JOIN appointments a ON mc.appointment_id = a.appointmentId
    ORDER BY mc.certificate_id DESC
")->fetchAll();

echo "<table border='1' cellpadding='5'>";
echo "<tr><th>Cert ID</th><th>Cert #</th><th>Doctor ID (raw)</th><th>Type</th><th>Cast to int</th><th>Match?</th><th>Status</th></tr>";
foreach ($certs as $c) {
    $rawDoctorId = $c['doctor_id'];
    $castDoctorId = (int)$c['doctor_id'];
    $type = gettype($rawDoctorId);
    $match = ($castDoctorId === $doctorId) ? '✅ MATCH' : '❌ NO (' . $castDoctorId . ' vs ' . $doctorId . ')';
    
    echo "<tr>
        <td>{$c['certificate_id']}</td>
        <td>{$c['certificate_number']}</td>
        <td>" . var_export($rawDoctorId, true) . "</td>
        <td>$type</td>
        <td>$castDoctorId</td>
        <td>$match</td>
        <td>{$c['approval_status']}</td>
    </tr>";
}
echo "</table>";

echo "<h2>Quick Links</h2>";
foreach ($certs as $c) {
    if ($c['approval_status'] == 'pending_consultation' || $c['approval_status'] === null) {
        $link = "certificate-consultation.php?certificate_id={$c['certificate_id']}";
        echo "<p><a href='$link' style='color: blue; font-size: 16px;'>Start Consultation for Certificate #{$c['certificate_id']} ({$c['certificate_number']})</a></p>";
    }
}
?>