<?php
require_once '../includes/config.php';
require_once '../includes/auth.php';
checkRole('doctor');

$billId = (int)($_GET['bill_id'] ?? 0);
$status = $_GET['status'] ?? '';

if (!$billId || !in_array($status, ['paid', 'cancelled'])) { 
    $_SESSION['error'] = "Invalid request."; 
    header("Location: patients.php"); 
    exit(); 
}

try {
    $stmt = $pdo->prepare("SELECT b.*, p.userId as patientUserId FROM bills b JOIN patients p ON b.patientId = p.patientId WHERE b.billId = ?");
    $stmt->execute([$billId]);
    $bill = $stmt->fetch();
    
    if (!$bill) { 
        $_SESSION['error'] = "Bill not found."; 
        header("Location: patients.php"); 
        exit(); 
    }

    if ($status == 'paid') {
        $updateStmt = $pdo->prepare("UPDATE bills SET status = 'paid', paidAt = NOW() WHERE billId = ?");
        $updateStmt->execute([$billId]);
        createNotification($bill['patientUserId'], 'billing', 'Payment Confirmed', 
            "Your bill #" . str_pad($billId, 6, '0', STR_PAD_LEFT) . " has been marked as paid.");
        logAction($_SESSION['user_id'], 'MARK_BILL_PAID', "Marked bill #$billId as paid");
        $_SESSION['success'] = "Bill marked as paid.";
    } elseif ($status == 'cancelled') {
        $updateStmt = $pdo->prepare("UPDATE bills SET status = 'cancelled' WHERE billId = ?");
        $updateStmt->execute([$billId]);
        createNotification($bill['patientUserId'], 'billing', 'Bill Cancelled', 
            "Your bill #" . str_pad($billId, 6, '0', STR_PAD_LEFT) . " has been cancelled.");
        logAction($_SESSION['user_id'], 'CANCEL_BILL', "Cancelled bill #$billId");
        $_SESSION['success'] = "Bill cancelled.";
    }
    
    header("Location: view-bill.php?bill_id={$billId}");
    exit();
} catch (Exception $e) {
    $_SESSION['error'] = "Failed: " . $e->getMessage();
    header("Location: view-bill.php?bill_id={$billId}");
    exit();
}
?>