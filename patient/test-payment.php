<?php
require_once '../includes/config.php';
session_start();

$billId = 8;

echo "<h1>Payment Test</h1>";

// Check current bill status
$stmt = $pdo->prepare("SELECT * FROM bills WHERE billId = ?");
$stmt->execute([$billId]);
$bill = $stmt->fetch();

echo "<h2>Current Bill Status:</h2>";
echo "<pre>";
print_r($bill);
echo "</pre>";

echo "<h2>Attempting to update bill...</h2>";

// Try to update the bill
$updateStmt = $pdo->prepare("
    UPDATE bills 
    SET status = 'paid', paidAt = NOW() 
    WHERE billId = ? AND status = 'unpaid'
");
$updateStmt->execute([$billId]);

$rowsAffected = $updateStmt->rowCount();
echo "Rows affected: " . $rowsAffected . "<br>";

if ($rowsAffected > 0) {
    echo "<span style='color:green'>✓ Bill #$billId has been marked as PAID!</span><br>";
} else {
    echo "<span style='color:red'>✗ Update failed. Bill may already be paid or doesn't exist.</span><br>";
}

// Check updated status
$stmt = $pdo->prepare("SELECT * FROM bills WHERE billId = ?");
$stmt->execute([$billId]);
$updatedBill = $stmt->fetch();

echo "<h2>Updated Bill Status:</h2>";
echo "<pre>";
print_r($updatedBill);
echo "</pre>";
?>