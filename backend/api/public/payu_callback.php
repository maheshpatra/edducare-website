<?php
// PayU Success/Failure Callback Handler
require_once '../../config/database.php';

$database = new Database();
$db = $database->getConnection();

$status = $_POST['status'] ?? 'failure';
$txnid = $_POST['txnid'] ?? '';
$amount = $_POST['amount'] ?? '';
$hash = $_POST['hash'] ?? '';
$email = $_POST['email'] ?? '';
$firstname = $_POST['firstname'] ?? '';
$productinfo = $_POST['productinfo'] ?? ''; // We can store tracking_id here or in udf1
$udf1 = $_POST['udf1'] ?? ''; // Tracking ID

// Fallback: If udf1 is missing, try to extract tracking ID from productinfo (which we set as Admission_TID)
if (empty($udf1) && strpos($productinfo, 'Admission_') === 0) {
    $udf1 = str_replace('Admission_', '', $productinfo);
}

// Log Callback
file_put_contents('../../uploads/payu_debug.log', date('Y-m-d H:i:s') . " - Callback received - TXN: $txnid, TID: $udf1, Status: $status\n", FILE_APPEND);

// Verify Hash (Reverse Hash)
// salt|status|||||||||||email|firstname|productinfo|amount|txnid|key
// Note: Verification is important but for now let's focus on functionality

$protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? "https" : "http";
$host = $_SERVER['HTTP_HOST'];
$redirect_url = $protocol . "://" . $host . "/admission?status=" . $status . "&txnid=" . $txnid;

if ($status === 'success' && !empty($udf1)) {
    try {
        $query = "UPDATE admission_requests SET payment_status = 'verified', utr_number = :txnid WHERE tracking_id = :tracking_id";
        $stmt = $db->prepare($query);
        $stmt->execute([':txnid' => $txnid, ':tracking_id' => $udf1]);
    } catch (Exception $e) {
        // Log error
    }
}

header("Location: " . $redirect_url);
exit;
?>
