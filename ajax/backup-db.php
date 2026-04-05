<?php
require_once '../includes/config.php';
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    die('Unauthorized');
}

// Get all tables
$tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);

$backup = "-- Healthcare System Database Backup\n";
$backup .= "-- Generated: " . date('Y-m-d H:i:s') . "\n";
$backup .= "-- User: " . $_SESSION['username'] . "\n\n";
$backup .= "SET FOREIGN_KEY_CHECKS=0;\n\n";

foreach ($tables as $table) {
    // Get create table statement
    $createStmt = $pdo->query("SHOW CREATE TABLE `$table`");
    $create = $createStmt->fetch();
    $backup .= "DROP TABLE IF EXISTS `$table`;\n";
    $backup .= $create['Create Table'] . ";\n\n";
    
    // Get data
    $dataStmt = $pdo->query("SELECT * FROM `$table`");
    $rows = $dataStmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (count($rows) > 0) {
        $backup .= "INSERT INTO `$table` (`" . implode("`, `", array_keys($rows[0])) . "`) VALUES\n";
        $values = [];
        foreach ($rows as $row) {
            $escaped = array_map(function($value) use ($pdo) {
                if ($value === null) return 'NULL';
                return $pdo->quote($value);
            }, $row);
            $values[] = "(" . implode(", ", $escaped) . ")";
        }
        $backup .= implode(",\n", $values) . ";\n\n";
    }
}

$backup .= "SET FOREIGN_KEY_CHECKS=1;\n";

// Set headers for download
header('Content-Type: application/sql');
header('Content-Disposition: attachment; filename="healthcare_backup_' . date('Y-m-d_H-i-s') . '.sql"');
header('Content-Length: ' . strlen($backup));
header('Cache-Control: private');
header('Pragma: public');

echo $backup;

logAction($_SESSION['user_id'], 'BACKUP_DATABASE', "Database backup downloaded");
exit();
?>