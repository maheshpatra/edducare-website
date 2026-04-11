<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS')
    exit;

require_once '../../config/database.php';

$database = new Database();
$db = $database->getConnection();

$data = json_decode(file_get_contents("php://input"), true);
$protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? "https" : "http";
$host = $_SERVER['HTTP_HOST'];
$school_id = $data['school_id'] ?? null;
$amount = $data['amount'] ?? 0;
// Format amount as string with 2 decimal places (PayU requires precise formatting)
$amount = number_format((float) $amount, 2, '.', '');
$firstname = $data['firstname'] ?? 'Student';
$email = $data['email'] ?? 'test@example.com';
$phone = $data['phone'] ?? '1234567890';
$productinfo = $data['productinfo'] ?? 'Admission Fee';
$udf1 = $data['udf1'] ?? '';

if (!$school_id || !$amount) {
    echo json_encode(['success' => false, 'error' => 'Missing required data']);
    exit;
}

try {
    // Fetch PayU credentials
    $query = "SELECT config_json, mode FROM school_payment_gateways WHERE school_id = :school_id AND gateway_name = 'payu' AND is_active = 1";
    $stmt = $db->prepare($query);
    $stmt->execute([':school_id' => $school_id]);
    $gateway = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$gateway) {
        echo json_encode(['success' => false, 'error' => 'PayU not configured correctly']);
        exit;
    }

    $config = json_decode($gateway['config_json'], true);
    $mode = $gateway['mode'];
    
    // Support new environment-specific keys, fallback to old generic keys if not found
    if ($mode === 'sandbox') {
        $merchant_key = $config['sandbox_key'] ?? $config['merchant_key'] ?? '';
        $salt = $config['sandbox_salt'] ?? $config['merchant_salt'] ?? '';
    } else {
        $merchant_key = $config['live_key'] ?? $config['merchant_key'] ?? '';
        $salt = $config['live_salt'] ?? $config['merchant_salt'] ?? '';
    }

    if (!$merchant_key || !$salt) {
        echo json_encode(['success' => false, 'error' => 'PayU credentials missing (key/salt)']);
        exit;
    }

    $txnid = 'TXN_' . uniqid();
    
    // Exact PayU Hash Sequence: 
    // key|txnid|amount|productinfo|firstname|email|udf1|udf2|udf3|udf4|udf5|udf6|udf7|udf8|udf9|udf10|salt
    $hash_array = [
        $merchant_key,
        $txnid,
        $amount,
        $productinfo,
        $firstname,
        $email,
        $udf1,
        '', // udf2
        '', // udf3
        '', // udf4
        '', // udf5
        '', // udf6
        '', // udf7
        '', // udf8
        '', // udf9
        '', // udf10
        $salt
    ];
    
    $hashString = implode('|', $hash_array);
    $hash = strtolower(hash('sha512', $hashString));

    $action = ($gateway['mode'] === 'sandbox') ? 'https://test.payu.in/_payment' : 'https://secure.payu.in/_payment';

    echo json_encode([
        'success' => true,
        'payment_params' => [
            'key' => $merchant_key,
            'txnid' => $txnid,
            'amount' => $amount,
            'firstname' => $firstname,
            'email' => $email,
            'phone' => $phone,
            'productinfo' => $productinfo,
            'surl' => $protocol . "://" . $host . "/api/public/payu_callback.php",
            'furl' => $protocol . "://" . $host . "/api/public/payu_callback.php",
            'hash' => $hash,
            'udf1' => $udf1,
            'service_provider' => 'payu_paisa',
            'action' => $action
        ]
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>