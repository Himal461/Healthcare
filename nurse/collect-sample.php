<?php
require_once '../includes/config.php';
require_once '../includes/auth.php';
checkRole('nurse');

$testId = $_GET['test_id'] ?? 0;

if (!$testId) {
    $_SESSION['error'] = "Invalid test ID.";
    header("Location: lab-tests.php");
    exit();
}

try {
    $stmt = $pdo->prepare("UPDATE lab_tests SET status = 'in-progress' WHERE testId = ? AND status = 'ordered'");
    $stmt->execute([$testId]);
    
    $_SESSION['success'] = "Sample collected successfully!";
    logAction($_SESSION['user_id'], 'COLLECT_SAMPLE', "Collected sample for test ID: $testId");
    
} catch (Exception $e) {
    $_SESSION['error'] = "Failed to update test status: " . $e->getMessage();
}

header("Location: lab-tests.php");
exit();
?>