<?php
require_once '../includes/config.php';
require_once '../includes/auth.php';
checkRole('patient');

$userId = $_SESSION['user_id'];

// Get patient
$stmt = $pdo->prepare("SELECT patientId FROM patients WHERE userId = ?");
$stmt->execute([$userId]);
$patient = $stmt->fetch();

if (!$patient) {
    die("❌ Patient not found");
}

$patientId = $patient['patientId'];

// Get unpaid bills
$stmt = $pdo->prepare("SELECT * FROM bills WHERE patientId = ? AND status = 'unpaid'");
$stmt->execute([$patientId]);
$unpaidBills = $stmt->fetchAll();

$debug = "";

// ================= PAYMENT =================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $debug .= "<br>POST RECEIVED";

    $billId = $_POST['bill_id'] ?? '';
    $method = $_POST['payment_method'] ?? '';

    $debug .= "<br>Bill ID: " . $billId;
    $debug .= "<br>Method: " . $method;

    if (!$billId) {
        die("❌ bill_id not coming from form");
    }

    try {
        // Check bill
        $check = $pdo->prepare("SELECT * FROM bills WHERE billId = ?");
        $check->execute([$billId]);
        $bill = $check->fetch();

        if (!$bill) {
            die("❌ Bill NOT FOUND in DB");
        }

        $debug .= "<br>Bill Found. Status: " . $bill['status'];

        // FORCE UPDATE (no patientId restriction for debug)
        $stmt = $pdo->prepare("
            UPDATE bills 
            SET status = 'paid', paidAt = NOW()
            WHERE billId = ?
        ");

        $stmt->execute([$billId]);

        $debug .= "<br>Rows Updated: " . $stmt->rowCount();

        if ($stmt->rowCount() == 0) {
            die("❌ UPDATE FAILED (rowCount = 0)");
        }

        header("Location: make-payment.php?success=1");
        exit();

    } catch (Exception $e) {
        die("❌ ERROR: " . $e->getMessage());
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Payment Debug</title>
</head>
<body>

<h2>Payment Page</h2>

<?php if(isset($_GET['success'])): ?>
    <h3 style="color:green;">✅ PAYMENT SUCCESSFUL</h3>
<?php endif; ?>

<!-- DEBUG OUTPUT -->
<div style="color:red;">
    <?php echo $debug; ?>
</div>

<!-- BILLS -->
<?php foreach ($unpaidBills as $bill): ?>
    <div style="border:1px solid black; padding:10px; margin:10px;">
        Bill ID: <?php echo $bill['billId']; ?> |
        Amount: <?php echo $bill['totalAmount']; ?>

        <form method="POST">
            <input type="hidden" name="bill_id" value="<?php echo $bill['billId']; ?>">
            <input type="hidden" name="payment_method" value="cash">

            <button type="submit">PAY (TEST)</button>
        </form>
    </div>
<?php endforeach; ?>

</body>
</html>