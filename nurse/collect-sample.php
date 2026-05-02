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

$stmt = $pdo->prepare("UPDATE lab_tests SET status = 'in-progress', performedDate = NOW() WHERE testId = ? AND status = 'ordered'");
$stmt->execute([$testId]);

if ($stmt->rowCount() > 0) {
    $_SESSION['success'] = "Sample collected successfully! Test is now in progress.";
    logAction($_SESSION['user_id'], 'COLLECT_SAMPLE', "Collected sample for test $testId");
} else {
    $_SESSION['error'] = "Failed to collect sample. Test may already be processed.";
}

header("Location: lab-tests.php");
exit();
?>