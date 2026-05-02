<?php
require_once '../includes/config.php';
session_start();

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit();
}

$cacheDir = __DIR__ . '/../cache/';
$cleared = 0;

if (is_dir($cacheDir)) {
    $files = glob($cacheDir . '*');
    foreach ($files as $file) {
        if (is_file($file)) {
            unlink($file);
            $cleared++;
        }
    }
}

logAction($_SESSION['user_id'], 'CLEAR_CACHE', "Cleared $cleared cache files");
echo json_encode(['success' => true, 'cleared' => $cleared]);