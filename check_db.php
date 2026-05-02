<?php
require_once 'includes/config.php';
echo "<h1>Database Check</h1>";
$tables = ['users','patients','staff','doctors','nurses','administrators','appointments','doctor_availability','medical_records','vitals','prescriptions','lab_tests','bills','bill_charges','departments','notifications','audit_log'];
echo "<table border='1'><tr><th>Table</th><th>Exists</th><th>Records</th></tr>";
foreach ($tables as $t) {
    $exists = $pdo->query("SHOW TABLES LIKE '$t'")->rowCount() > 0;
    $count = $exists ? $pdo->query("SELECT COUNT(*) FROM $t")->fetchColumn() : 0;
    echo "<tr><td>$t</td><td>".($exists?'✓':'✗')."</td><td>$count</td></tr>";
}
echo "</table>";