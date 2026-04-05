<?php
require_once 'includes/config.php';

echo "<!DOCTYPE html>
<html>
<head>
    <title>Database Check</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        .success { color: green; }
        .error { color: red; }
        .warning { color: orange; }
        table { border-collapse: collapse; width: 100%; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        th { background: #f8f9fa; }
    </style>
</head>
<body>
<h1>Database Structure Check</h1>";

// Check if medical_records table exists
$result = $pdo->query("SHOW TABLES LIKE 'medical_records'");
if ($result->rowCount() > 0) {
    echo "<p class='success'>✓ medical_records table exists</p>";
    
    // Show structure
    echo "<h2>medical_records table structure:</h2>";
    $columns = $pdo->query("DESCRIBE medical_records");
    echo "<table>";
    echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th></tr>";
    foreach ($columns as $col) {
        echo "<tr>";
        echo "<td>" . $col['Field'] . "</td>";
        echo "<td>" . $col['Type'] . "</td>";
        echo "<td>" . $col['Null'] . "</td>";
        echo "<td>" . $col['Key'] . "</td>";
        echo "<td>" . $col['Default'] . "</td>";
        echo "</tr>";
    }
    echo "</table>";
    
    // Check if there are any records
    $count = $pdo->query("SELECT COUNT(*) FROM medical_records")->fetchColumn();
    echo "<p>Total records: " . $count . "</p>";
    
} else {
    echo "<p class='error'>✗ medical_records table does NOT exist!</p>";
    echo "<p>Please run the following SQL to create it:</p>";
    echo "<pre>
CREATE TABLE IF NOT EXISTS `medical_records` (
  `recordId` int(11) NOT NULL AUTO_INCREMENT,
  `patientId` int(11) NOT NULL,
  `doctorId` int(11) NOT NULL,
  `appointmentId` int(11) DEFAULT NULL,
  `creationDate` datetime DEFAULT current_timestamp(),
  `diagnosis` text DEFAULT NULL,
  `treatmentNotes` text DEFAULT NULL,
  `prescriptions` text DEFAULT NULL,
  `followUpDate` date DEFAULT NULL,
  `isConfidential` tinyint(1) DEFAULT 0,
  PRIMARY KEY (`recordId`),
  KEY `idx_patientId` (`patientId`),
  KEY `idx_doctorId` (`doctorId`),
  KEY `idx_appointmentId` (`appointmentId`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
    </pre>";
}

// Check other related tables
echo "<h2>Other Tables:</h2>";
$tables = ['patients', 'doctors', 'appointments', 'users'];
echo "<table>";
echo "<tr><th>Table</th><th>Exists?</th><th>Records</th></tr>";
foreach ($tables as $table) {
    $exists = $pdo->query("SHOW TABLES LIKE '$table'")->rowCount() > 0;
    $count = $exists ? $pdo->query("SELECT COUNT(*) FROM $table")->fetchColumn() : 0;
    $status = $exists ? "<span class='success'>✓</span>" : "<span class='error'>✗</span>";
    echo "<tr><td>$table</td><td>$status</td><td>$count</td></tr>";
}
echo "</table>";

echo "</body></html>";
?>