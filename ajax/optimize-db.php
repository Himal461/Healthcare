<?php
require_once '../includes/config.php';
session_start();

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit();
}

try {
    // Get all tables
    $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
    $optimized = [];
    
    foreach ($tables as $table) {
        $pdo->exec("OPTIMIZE TABLE `$table`");
        $optimized[] = $table;
    }
    
    logAction($_SESSION['user_id'], 'OPTIMIZE_DATABASE', "Optimized tables: " . implode(', ', $optimized));
    
    echo json_encode(['success' => true, 'tables' => $optimized]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>