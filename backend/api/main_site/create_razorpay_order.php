<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') exit;

require_once '../../config/database.php';

$database = new Database();
$db = $database->getConnection();

$data = json_decode(file_get_contents("php://input"), true);
$school_id = $data['school_id'] ?? null;
$amount = $data['amount'] ?? 0; // In Rupees

if (!$school_id || !$amount) {
    echo json_encode(['success' => false, 'error' => 'Missing required data']);
    exit;
}

try {
    // Fetch Razorpay credentials
    $query = "SELECT config_json, mode FROM school_payment_gateways WHERE school_id = :school_id AND gateway_name = 'razorpay' AND is_active = 1";
    $stmt = $db->prepare($query);
    $stmt->execute([':school_id' => $school_id]);
    $gateway = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$gateway) {
        echo json_encode(['success' => false, 'error' => 'Razorpay not configured correctly']);
        exit;
    }

    $config = json_decode($gateway['config_json'], true);
    $mode = $gateway['mode'];

    // Support new environment-specific keys, fallback to old generic keys if not found
    if ($mode === 'sandbox') {
        $key_id = $config['sandbox_key_id'] ?? $config['key_id'] ?? '';
        $key_secret = $config['sandbox_key_secret'] ?? $config['key_secret'] ?? '';
    } else {
        $key_id = $config['live_key_id'] ?? $config['key_id'] ?? '';
        $key_secret = $config['live_key_secret'] ?? $config['key_secret'] ?? '';
    }

    if (!$key_id || !$key_secret) {
        echo json_encode(['success' => false, 'error' => 'Credentials missing']);
        exit;
    }

    // Amount in Paise
    $amount_paise = $amount * 100;
    $receipt = "rcpt_" . uniqid();

    // Call Razorpay API via cURL
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, 'https://api.razorpay.com/v1/orders');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
        'amount' => $amount_paise,
        'currency' => 'INR',
        'receipt' => $receipt
    ]));
    curl_setopt($ch, CURLOPT_USERPWD, $key_id . ':' . $key_secret);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);

    $result = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($http_code !== 200) {
        echo json_encode(['success' => false, 'error' => 'Razorpay Order Creation Failed', 'details' => json_decode($result, true)]);
        exit;
    }

    echo json_encode(['success' => true, 'order' => json_decode($result, true)]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
