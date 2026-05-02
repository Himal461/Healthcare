<?php
require_once '../includes/config.php';
require_once '../includes/auth.php';
checkRole('admin');

$reportType = $_GET['type'] ?? 'appointments';
$dateFrom = $_GET['date_from'] ?? date('Y-m-d', strtotime('-30 days'));
$dateTo = $_GET['date_to'] ?? date('Y-m-d');

// Set headers for CSV download
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $reportType . '_report_' . date('Y-m-d') . '.csv"');
header('Pragma: no-cache');
header('Expires: 0');

// Create output stream
$output = fopen('php://output', 'w');

// Add BOM for UTF-8 support in Excel
fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

// Export based on report type
if ($reportType === 'appointments') {
    // Header rows
    fputcsv($output, ['APPOINTMENTS REPORT']);
    fputcsv($output, ['Generated:', date('Y-m-d H:i:s')]);
    fputcsv($output, ['Period:', $dateFrom, 'to', $dateTo]);
    fputcsv($output, []);
    fputcsv($output, ['Appointment ID', 'Date', 'Time', 'Patient Name', 'Patient Email', 'Patient Phone', 'Doctor Name', 'Specialization', 'Status', 'Reason']);
    
    $stmt = $pdo->prepare("
        SELECT a.appointmentId, a.dateTime, a.status, a.reason,
               CONCAT(pu.firstName, ' ', pu.lastName) as patientName,
               pu.email as patientEmail,
               pu.phoneNumber as patientPhone,
               CONCAT(du.firstName, ' ', du.lastName) as doctorName,
               d.specialization
        FROM appointments a
        JOIN patients p ON a.patientId = p.patientId 
        JOIN users pu ON p.userId = pu.userId
        JOIN doctors d ON a.doctorId = d.doctorId 
        JOIN staff s ON d.staffId = s.staffId 
        JOIN users du ON s.userId = du.userId
        WHERE DATE(a.dateTime) BETWEEN ? AND ?
        ORDER BY a.dateTime DESC
    ");
    $stmt->execute([$dateFrom, $dateTo]);
    
    while ($row = $stmt->fetch()) {
        fputcsv($output, [
            $row['appointmentId'],
            date('Y-m-d', strtotime($row['dateTime'])),
            date('H:i', strtotime($row['dateTime'])),
            $row['patientName'],
            $row['patientEmail'],
            $row['patientPhone'],
            'Dr. ' . $row['doctorName'],
            $row['specialization'],
            ucfirst($row['status']),
            $row['reason'] ?: '-'
        ]);
    }
    
    // Summary
    $stmt = $pdo->prepare("
        SELECT status, COUNT(*) as count 
        FROM appointments 
        WHERE DATE(dateTime) BETWEEN ? AND ? 
        GROUP BY status
    ");
    $stmt->execute([$dateFrom, $dateTo]);
    $summary = $stmt->fetchAll();
    
    fputcsv($output, []);
    fputcsv($output, ['SUMMARY BY STATUS']);
    foreach ($summary as $s) {
        fputcsv($output, [ucfirst($s['status']), $s['count']]);
    }

} elseif ($reportType === 'revenue') {
    fputcsv($output, ['REVENUE REPORT']);
    fputcsv($output, ['Generated:', date('Y-m-d H:i:s')]);
    fputcsv($output, ['Period:', $dateFrom, 'to', $dateTo]);
    fputcsv($output, []);
    fputcsv($output, ['Bill ID', 'Date', 'Patient Name', 'Patient Email', 'Consultation Fee', 'Additional Charges', 'Service Charge', 'GST', 'Total Amount', 'Status', 'Paid Date']);
    
    $stmt = $pdo->prepare("
        SELECT b.*, 
               CONCAT(u.firstName, ' ', u.lastName) as patientName,
               u.email as patientEmail
        FROM bills b
        JOIN patients p ON b.patientId = p.patientId
        JOIN users u ON p.userId = u.userId
        WHERE DATE(b.generatedAt) BETWEEN ? AND ?
        ORDER BY b.generatedAt DESC
    ");
    $stmt->execute([$dateFrom, $dateTo]);
    
    $totalRevenue = 0;
    $totalPaid = 0;
    $totalUnpaid = 0;
    
    while ($row = $stmt->fetch()) {
        $totalRevenue += $row['totalAmount'];
        if ($row['status'] == 'paid') $totalPaid += $row['totalAmount'];
        if ($row['status'] == 'unpaid') $totalUnpaid += $row['totalAmount'];
        
        fputcsv($output, [
            $row['billId'],
            date('Y-m-d', strtotime($row['generatedAt'])),
            $row['patientName'],
            $row['patientEmail'],
            number_format($row['consultationFee'], 2),
            number_format($row['additionalCharges'], 2),
            number_format($row['serviceCharge'], 2),
            number_format($row['gst'], 2),
            number_format($row['totalAmount'], 2),
            ucfirst($row['status']),
            $row['paidAt'] ? date('Y-m-d H:i', strtotime($row['paidAt'])) : '-'
        ]);
    }
    
    fputcsv($output, []);
    fputcsv($output, ['SUMMARY']);
    fputcsv($output, ['Total Revenue (All Bills):', '$' . number_format($totalRevenue, 2)]);
    fputcsv($output, ['Total Paid:', '$' . number_format($totalPaid, 2)]);
    fputcsv($output, ['Total Unpaid:', '$' . number_format($totalUnpaid, 2)]);
    fputcsv($output, ['Total Cancelled:', '$' . number_format($totalRevenue - $totalPaid - $totalUnpaid, 2)]);

} elseif ($reportType === 'patients') {
    fputcsv($output, ['PATIENTS REPORT']);
    fputcsv($output, ['Generated:', date('Y-m-d H:i:s')]);
    fputcsv($output, ['Registration Period:', $dateFrom, 'to', $dateTo]);
    fputcsv($output, []);
    fputcsv($output, ['Patient ID', 'Username', 'First Name', 'Last Name', 'Email', 'Phone', 'Date of Birth', 'Blood Type', 'Address', 'Allergies', 'Registered Date']);
    
    $stmt = $pdo->prepare("
        SELECT p.patientId, u.username, u.firstName, u.lastName, u.email, u.phoneNumber,
               p.dateOfBirth, p.bloodType, p.address, p.knownAllergies, u.dateCreated
        FROM patients p
        JOIN users u ON p.userId = u.userId
        WHERE u.role = 'patient' AND DATE(u.dateCreated) BETWEEN ? AND ?
        ORDER BY u.dateCreated DESC
    ");
    $stmt->execute([$dateFrom, $dateTo]);
    
    while ($row = $stmt->fetch()) {
        fputcsv($output, [
            $row['patientId'],
            $row['username'],
            $row['firstName'],
            $row['lastName'],
            $row['email'],
            $row['phoneNumber'] ?: '-',
            $row['dateOfBirth'] ?: '-',
            $row['bloodType'] ?: '-',
            $row['address'] ?: '-',
            $row['knownAllergies'] ?: '-',
            date('Y-m-d', strtotime($row['dateCreated']))
        ]);
    }
    
    // Summary
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as total FROM users 
        WHERE role = 'patient' AND DATE(dateCreated) BETWEEN ? AND ?
    ");
    $stmt->execute([$dateFrom, $dateTo]);
    $total = $stmt->fetchColumn();
    
    fputcsv($output, []);
    fputcsv($output, ['Total New Patients:', $total]);

} elseif ($reportType === 'staff') {
    fputcsv($output, ['STAFF REPORT']);
    fputcsv($output, ['Generated:', date('Y-m-d H:i:s')]);
    fputcsv($output, []);
    fputcsv($output, ['Staff ID', 'Username', 'First Name', 'Last Name', 'Email', 'Phone', 'Role', 'Department', 'Position', 'License Number', 'Hire Date']);
    
    $stmt = $pdo->query("
        SELECT s.staffId, u.username, u.firstName, u.lastName, u.email, u.phoneNumber, u.role,
               s.department, s.position, s.licenseNumber, s.hireDate
        FROM staff s
        JOIN users u ON s.userId = u.userId
        ORDER BY u.role, u.firstName
    ");
    
    while ($row = $stmt->fetch()) {
        fputcsv($output, [
            $row['staffId'],
            $row['username'],
            $row['firstName'],
            $row['lastName'],
            $row['email'],
            $row['phoneNumber'] ?: '-',
            ucfirst($row['role']),
            $row['department'] ?: '-',
            $row['position'] ?: '-',
            $row['licenseNumber'] ?: '-',
            $row['hireDate'] ?: '-'
        ]);
    }
    
    // Summary by role
    $stmt = $pdo->query("
        SELECT u.role, COUNT(*) as count 
        FROM staff s 
        JOIN users u ON s.userId = u.userId 
        GROUP BY u.role
    ");
    $summary = $stmt->fetchAll();
    
    fputcsv($output, []);
    fputcsv($output, ['SUMMARY BY ROLE']);
    foreach ($summary as $s) {
        fputcsv($output, [ucfirst($s['role']), $s['count']]);
    }

} elseif ($reportType === 'lab-tests') {
    fputcsv($output, ['LAB TESTS REPORT']);
    fputcsv($output, ['Generated:', date('Y-m-d H:i:s')]);
    fputcsv($output, ['Period:', $dateFrom, 'to', $dateTo]);
    fputcsv($output, []);
    fputcsv($output, ['Test ID', 'Ordered Date', 'Patient Name', 'Test Name', 'Test Type', 'Ordered By', 'Status', 'Performed Date', 'Results']);
    
    $stmt = $pdo->prepare("
        SELECT lt.*, 
               CONCAT(u.firstName, ' ', u.lastName) as patientName,
               CONCAT(du.firstName, ' ', du.lastName) as orderedByName
        FROM lab_tests lt
        JOIN patients p ON lt.patientId = p.patientId
        JOIN users u ON p.userId = u.userId
        LEFT JOIN doctors d ON lt.orderedBy = d.doctorId
        LEFT JOIN staff s ON d.staffId = s.staffId
        LEFT JOIN users du ON s.userId = du.userId
        WHERE DATE(lt.orderedDate) BETWEEN ? AND ?
        ORDER BY lt.orderedDate DESC
    ");
    $stmt->execute([$dateFrom, $dateTo]);
    
    while ($row = $stmt->fetch()) {
        fputcsv($output, [
            $row['testId'],
            date('Y-m-d H:i', strtotime($row['orderedDate'])),
            $row['patientName'],
            $row['testName'],
            $row['testType'] ?: '-',
            $row['orderedByName'] ?: 'N/A',
            ucfirst(str_replace('-', ' ', $row['status'])),
            $row['performedDate'] ? date('Y-m-d H:i', strtotime($row['performedDate'])) : '-',
            $row['results'] ? substr(str_replace(["\r", "\n"], ' ', $row['results']), 0, 100) . '...' : '-'
        ]);
    }
    
    // Summary by status
    $stmt = $pdo->prepare("
        SELECT status, COUNT(*) as count 
        FROM lab_tests 
        WHERE DATE(orderedDate) BETWEEN ? AND ? 
        GROUP BY status
    ");
    $stmt->execute([$dateFrom, $dateTo]);
    $summary = $stmt->fetchAll();
    
    fputcsv($output, []);
    fputcsv($output, ['SUMMARY BY STATUS']);
    foreach ($summary as $s) {
        fputcsv($output, [ucfirst(str_replace('-', ' ', $s['status'])), $s['count']]);
    }

} elseif ($reportType === 'salaries') {
    fputcsv($output, ['SALARY PAYMENTS REPORT']);
    fputcsv($output, ['Generated:', date('Y-m-d H:i:s')]);
    fputcsv($output, ['Period:', $dateFrom, 'to', $dateTo]);
    fputcsv($output, []);
    fputcsv($output, ['Salary ID', 'Payment Date', 'Employee Name', 'Role', 'Salary Month', 'Amount', 'Processed By', 'Notes']);
    
    $stmt = $pdo->prepare("
        SELECT sp.*, 
               CONCAT(u.firstName, ' ', u.lastName) as employeeName,
               CONCAT(pu.firstName, ' ', pu.lastName) as paidByName
        FROM salary_payments sp
        JOIN users u ON sp.userId = u.userId
        LEFT JOIN users pu ON sp.paidBy = pu.userId
        WHERE DATE(sp.paymentDate) BETWEEN ? AND ? AND sp.status = 'paid'
        ORDER BY sp.paymentDate DESC
    ");
    $stmt->execute([$dateFrom, $dateTo]);
    
    $totalSalaries = 0;
    
    while ($row = $stmt->fetch()) {
        $totalSalaries += $row['amount'];
        fputcsv($output, [
            $row['salaryId'],
            date('Y-m-d H:i', strtotime($row['paymentDate'])),
            $row['employeeName'],
            ucfirst($row['role']),
            date('F Y', strtotime($row['salaryMonth'])),
            number_format($row['amount'], 2),
            $row['paidByName'] ?: 'System',
            $row['notes'] ?: '-'
        ]);
    }
    
    fputcsv($output, []);
    fputcsv($output, ['Total Salary Payments:', '$' . number_format($totalSalaries, 2)]);
    fputcsv($output, ['Number of Payments:', $stmt->rowCount()]);
}

fclose($output);

// Log the export action
logAction($_SESSION['user_id'], 'EXPORT_REPORT', "Exported $reportType report from $dateFrom to $dateTo");
exit();
?>