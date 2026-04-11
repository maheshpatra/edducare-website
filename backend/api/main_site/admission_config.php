<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

require_once '../../config/database.php';

$database = new Database();
$db = $database->getConnection();

$school_id = isset($_GET['school_id']) ? (int)$_GET['school_id'] : null;

if (!$school_id) {
    echo json_encode(['success' => false, 'error' => 'School ID is required']);
    exit;
}

try {
    $query = "SELECT * FROM admission_configs WHERE school_id = :school_id LIMIT 1";
    $stmt = $db->prepare($query);
    $stmt->execute([':school_id' => $school_id]);
    $config = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$config) {
        $config = [
            'fields_json' => json_encode([
                'student_name' => ['enabled' => true, 'required' => true],
                'guardian_name' => ['enabled' => true, 'required' => false],
                'email' => ['enabled' => true, 'required' => true],
                'phone' => ['enabled' => true, 'required' => true],
                'desired_class' => ['enabled' => true, 'required' => true],
                'dob' => ['enabled' => false, 'required' => false],
                'gender' => ['enabled' => false, 'required' => false],
                'address' => ['enabled' => false, 'required' => false],
                'previous_school' => ['enabled' => false, 'required' => false]
            ])
        ];
    }
    
    // Parse the JSON for the frontend
    $config['fields'] = is_string($config['fields_json']) ? json_decode($config['fields_json'], true) : $config['fields_json'];

    // Fetch active payment gateways
    $gw_query = "SELECT gateway_name, mode, config_json FROM school_payment_gateways WHERE school_id = :school_id AND is_active = 1";
    $gw_stmt = $db->prepare($gw_query);
    $gw_stmt->execute([':school_id' => $school_id]);
    $gateways = $gw_stmt->fetchAll(PDO::FETCH_ASSOC);

    $safe_gateways = [];
    foreach ($gateways as $gw) {
        $json = json_decode($gw['config_json'], true);
        $safe_conf = [];
        if ($gw['gateway_name'] === 'razorpay') {
            $safe_conf = ['key_id' => $json['key_id'] ?? ''];
        } else if ($gw['gateway_name'] === 'payu') {
            $safe_conf = ['merchant_key' => $json['merchant_key'] ?? ''];
        } else if ($gw['gateway_name'] === 'upi_qr') {
            $safe_conf = ['qr_path' => $json['qr_path'] ?? ''];
        }
        
        $safe_gateways[] = [
            'name' => $gw['gateway_name'],
            'mode' => $gw['mode'],
            'config' => $safe_conf
        ];
    }
    $config['payment_methods'] = $safe_gateways;

    echo json_encode(['success' => true, 'data' => $config]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Server error: ' . $e->getMessage()]);
}
?>
