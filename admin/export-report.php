<?php
require_once '../includes/config.php';
require_once '../includes/auth.php';
checkRole('admin');

// Get report parameters
$reportType = $_GET['type'] ?? 'appointments';
$dateFrom = $_GET['date_from'] ?? date('Y-m-d', strtotime('-30 days'));
$dateTo = $_GET['date_to'] ?? date('Y-m-d');

// Set headers for CSV download
header('Content-Type: text/csv');
header('Content-Disposition: attachment; filename="' . $reportType . '_report_' . date('Y-m-d') . '.csv"');
header('Pragma: no-cache');
header('Expires: 0');

// Create output stream
$output = fopen('php://output', 'w');

// Add UTF-8 BOM for Excel compatibility
fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

// Generate report based on type
if ($reportType === 'appointments') {
    // Headers
    fputcsv($output, ['Appointments Report', 'From: ' . $dateFrom, 'To: ' . $dateTo]);
    fputcsv($output, []);
    fputcsv($output, ['Date', 'Patient', 'Doctor', 'Specialization', 'Status', 'Reason', 'Notes']);
    
    // Data
    $stmt = $pdo->prepare("
        SELECT 
            a.dateTime,
            CONCAT(pu.firstName, ' ', pu.lastName) as patientName,
            CONCAT(du.firstName, ' ', du.lastName) as doctorName,
            d.specialization,
            a.status,
            a.reason,
            a.notes
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
    $appointments = $stmt->fetchAll();
    
    foreach ($appointments as $appointment) {
        fputcsv($output, [
            date('Y-m-d H:i', strtotime($appointment['dateTime'])),
            $appointment['patientName'],
            'Dr. ' . $appointment['doctorName'],
            $appointment['specialization'],
            ucfirst($appointment['status']),
            $appointment['reason'],
            $appointment['notes']
        ]);
    }
    
} elseif ($reportType === 'revenue') {
    // Headers
    fputcsv($output, ['Revenue Report', 'From: ' . $dateFrom, 'To: ' . $dateTo]);
    fputcsv($output, []);
    fputcsv($output, ['Date', 'Bill ID', 'Patient', 'Amount', 'Tax', 'Discount', 'Total', 'Status', 'Payment Method', 'Description']);
    
    // Data
    $stmt = $pdo->prepare("
        SELECT 
            b.createdAt,
            b.billId,
            CONCAT(u.firstName, ' ', u.lastName) as patientName,
            b.amount,
            b.tax,
            b.discount,
            b.totalAmount,
            b.status,
            b.paymentMethod,
            b.description
        FROM billing b
        JOIN patients p ON b.patientId = p.patientId
        JOIN users u ON p.userId = u.userId
        WHERE DATE(b.createdAt) BETWEEN ? AND ?
        ORDER BY b.createdAt DESC
    ");
    $stmt->execute([$dateFrom, $dateTo]);
    $bills = $stmt->fetchAll();
    
    foreach ($bills as $bill) {
        fputcsv($output, [
            date('Y-m-d', strtotime($bill['createdAt'])),
            '#' . $bill['billId'],
            $bill['patientName'],
            number_format($bill['amount'], 2),
            number_format($bill['tax'], 2),
            number_format($bill['discount'], 2),
            number_format($bill['totalAmount'], 2),
            ucfirst($bill['status']),
            $bill['paymentMethod'] ?: 'Not Specified',
            $bill['description']
        ]);
    }
    
    // Add summary
    fputcsv($output, []);
    fputcsv($output, ['Summary']);
    $totalPaid = $pdo->prepare("SELECT SUM(totalAmount) as total FROM billing WHERE status = 'paid' AND DATE(createdAt) BETWEEN ? AND ?");
    $totalPaid->execute([$dateFrom, $dateTo]);
    $paid = $totalPaid->fetch()['total'] ?? 0;
    
    $totalPending = $pdo->prepare("SELECT SUM(totalAmount) as total FROM billing WHERE status = 'pending' AND DATE(createdAt) BETWEEN ? AND ?");
    $totalPending->execute([$dateFrom, $dateTo]);
    $pending = $totalPending->fetch()['total'] ?? 0;
    
    fputcsv($output, ['Total Paid', '$' . number_format($paid, 2)]);
    fputcsv($output, ['Total Pending', '$' . number_format($pending, 2)]);
    fputcsv($output, ['Total Revenue', '$' . number_format($paid + $pending, 2)]);
    
} elseif ($reportType === 'patients') {
    // Headers
    fputcsv($output, ['Patients Report', 'Generated: ' . date('Y-m-d H:i:s')]);
    fputcsv($output, []);
    fputcsv($output, ['Patient ID', 'Name', 'Email', 'Phone', 'Date of Birth', 'Age', 'Blood Type', 'Address', 'Emergency Contact', 'Registration Date']);
    
    // Data
    $stmt = $pdo->prepare("
        SELECT 
            p.patientId,
            CONCAT(u.firstName, ' ', u.lastName) as name,
            u.email,
            u.phoneNumber,
            p.dateOfBirth,
            p.bloodType,
            p.address,
            p.emergencyContactName,
            p.emergencyContactPhone,
            u.dateCreated
        FROM patients p
        JOIN users u ON p.userId = u.userId
        WHERE DATE(u.dateCreated) BETWEEN ? AND ?
        ORDER BY u.dateCreated DESC
    ");
    $stmt->execute([$dateFrom, $dateTo]);
    $patients = $stmt->fetchAll();
    
    foreach ($patients as $patient) {
        $age = $patient['dateOfBirth'] ? date_diff(date_create($patient['dateOfBirth']), date_create('today'))->y : 'N/A';
        fputcsv($output, [
            $patient['patientId'],
            $patient['name'],
            $patient['email'],
            $patient['phoneNumber'],
            $patient['dateOfBirth'],
            $age,
            $patient['bloodType'] ?: 'Unknown',
            $patient['address'],
            $patient['emergencyContactName'] . ' (' . $patient['emergencyContactPhone'] . ')',
            date('Y-m-d', strtotime($patient['dateCreated']))
        ]);
    }
    
} elseif ($reportType === 'doctors') {
    // Headers
    fputcsv($output, ['Doctors Performance Report', 'From: ' . $dateFrom, 'To: ' . $dateTo]);
    fputcsv($output, []);
    fputcsv($output, ['Doctor Name', 'Specialization', 'Total Appointments', 'Completed', 'Cancelled', 'Completion Rate (%)']);
    
    // Data
    $stmt = $pdo->prepare("
        SELECT 
            CONCAT(u.firstName, ' ', u.lastName) as doctorName,
            d.specialization,
            COUNT(a.appointmentId) as total_appointments,
            SUM(CASE WHEN a.status = 'completed' THEN 1 ELSE 0 END) as completed,
            SUM(CASE WHEN a.status = 'cancelled' THEN 1 ELSE 0 END) as cancelled,
            ROUND(SUM(CASE WHEN a.status = 'completed' THEN 1 ELSE 0 END) / NULLIF(COUNT(a.appointmentId), 0) * 100, 2) as completion_rate
        FROM doctors d
        JOIN staff s ON d.staffId = s.staffId
        JOIN users u ON s.userId = u.userId
        LEFT JOIN appointments a ON d.doctorId = a.doctorId AND DATE(a.dateTime) BETWEEN ? AND ?
        GROUP BY d.doctorId
        ORDER BY completion_rate DESC
    ");
    $stmt->execute([$dateFrom, $dateTo]);
    $doctors = $stmt->fetchAll();
    
    foreach ($doctors as $doctor) {
        fputcsv($output, [
            'Dr. ' . $doctor['doctorName'],
            $doctor['specialization'],
            $doctor['total_appointments'],
            $doctor['completed'],
            $doctor['cancelled'],
            $doctor['completion_rate'] . '%'
        ]);
    }
    
} elseif ($reportType === 'lab-tests') {
    // Headers
    fputcsv($output, ['Lab Tests Report', 'From: ' . $dateFrom, 'To: ' . $dateTo]);
    fputcsv($output, []);
    fputcsv($output, ['Order Date', 'Patient', 'Test Name', 'Test Type', 'Ordered By', 'Status', 'Results', 'Notes']);
    
    // Data
    $stmt = $pdo->prepare("
        SELECT 
            lt.orderedDate,
            CONCAT(u.firstName, ' ', u.lastName) as patientName,
            lt.testName,
            lt.testType,
            CONCAT(du.firstName, ' ', du.lastName) as orderedByName,
            lt.status,
            lt.results,
            lt.notes
        FROM lab_tests lt
        JOIN patients p ON lt.patientId = p.patientId
        JOIN users u ON p.userId = u.userId
        JOIN doctors d ON lt.orderedBy = d.doctorId
        JOIN staff s ON d.staffId = s.staffId
        JOIN users du ON s.userId = du.userId
        WHERE DATE(lt.orderedDate) BETWEEN ? AND ?
        ORDER BY lt.orderedDate DESC
    ");
    $stmt->execute([$dateFrom, $dateTo]);
    $tests = $stmt->fetchAll();
    
    foreach ($tests as $test) {
        fputcsv($output, [
            date('Y-m-d', strtotime($test['orderedDate'])),
            $test['patientName'],
            $test['testName'],
            $test['testType'],
            'Dr. ' . $test['orderedByName'],
            ucfirst($test['status']),
            $test['results'],
            $test['notes']
        ]);
    }
    
} elseif ($reportType === 'prescriptions') {
    // Headers
    fputcsv($output, ['Prescriptions Report', 'From: ' . $dateFrom, 'To: ' . $dateTo]);
    fputcsv($output, []);
    fputcsv($output, ['Date', 'Patient', 'Doctor', 'Medication', 'Dosage', 'Frequency', 'Status', 'Start Date', 'End Date']);
    
    // Data
    $stmt = $pdo->prepare("
        SELECT 
            p.createdAt,
            CONCAT(u.firstName, ' ', u.lastName) as patientName,
            CONCAT(du.firstName, ' ', du.lastName) as doctorName,
            p.medicationName,
            p.dosage,
            p.frequency,
            p.status,
            p.startDate,
            p.endDate
        FROM prescriptions p
        JOIN medical_records mr ON p.recordId = mr.recordId
        JOIN patients pt ON mr.patientId = pt.patientId
        JOIN users u ON pt.userId = u.userId
        JOIN doctors d ON p.prescribedBy = d.doctorId
        JOIN staff s ON d.staffId = s.staffId
        JOIN users du ON s.userId = du.userId
        WHERE DATE(p.createdAt) BETWEEN ? AND ?
        ORDER BY p.createdAt DESC
    ");
    $stmt->execute([$dateFrom, $dateTo]);
    $prescriptions = $stmt->fetchAll();
    
    foreach ($prescriptions as $prescription) {
        fputcsv($output, [
            date('Y-m-d', strtotime($prescription['createdAt'])),
            $prescription['patientName'],
            'Dr. ' . $prescription['doctorName'],
            $prescription['medicationName'],
            $prescription['dosage'],
            $prescription['frequency'],
            ucfirst($prescription['status']),
            $prescription['startDate'],
            $prescription['endDate'] ?: 'Ongoing'
        ]);
    }
}

fclose($output);
logAction($_SESSION['user_id'], 'EXPORT_REPORT', "Exported $reportType report from $dateFrom to $dateTo");
exit();
?>