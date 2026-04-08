<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') exit;

require_once '../../config/config.php';
require_once '../../config/database.php';
require_once '../../middleware/auth.php';

$auth = new AuthMiddleware();
$user = $auth->requireRole(['super_admin', 'school_admin']);

if (!$user) {
    exit;
}

$school_id = $user['school_id'];
$database = new Database();
$db = $database->getConnection();

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    try {
        $query = "SELECT * FROM school_payment_gateways WHERE school_id = :school_id";
        $stmt = $db->prepare($query);
        $stmt->execute([':school_id' => $school_id]);
        $gateways = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Standard list of supported gateways
        $supported = ['razorpay', 'payu', 'upi_qr'];
        $results = [];

        foreach ($supported as $name) {
            $found = false;
            foreach ($gateways as $g) {
                if ($g['gateway_name'] === $name) {
                    $g['config'] = json_decode($g['config_json'], true);
                    unset($g['config_json']);
                    $results[$name] = $g;
                    $found = true;
                    break;
                }
            }
            if (!$found) {
                $results[$name] = [
                    'school_id' => $school_id,
                    'gateway_name' => $name,
                    'is_active' => 0,
                    'mode' => ($name === 'upi_qr' ? 'live' : 'sandbox'),
                    'config' => []
                ];
            }
        }

        echo json_encode(['success' => true, 'data' => $results]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => $e->getMessage()]);
    }
}

if ($method === 'POST') {
    // Handle both JSON and FormData
    $is_form_data = strpos($_SERVER['CONTENT_TYPE'] ?? '', 'multipart/form-data') !== false;
    
    if ($is_form_data) {
        $gateway_name = $_POST['gateway_name'] ?? null;
        $is_active = isset($_POST['is_active']) ? (int)$_POST['is_active'] : 0;
        $mode = $_POST['mode'] ?? 'sandbox';
        $config = json_decode($_POST['config'] ?? '{}', true);
    } else {
        $data = json_decode(file_get_contents("php://input"), true);
        $gateway_name = $data['gateway_name'] ?? null;
        $is_active = $data['is_active'] ? 1 : 0;
        $mode = $data['mode'] ?? 'sandbox';
        $config = $data['config'] ?? [];
    }
    
    if (!$gateway_name) {
        echo json_encode(['error' => 'Gateway name required']);
        exit;
    }

    // Handle File Upload for upi_qr
    if ($gateway_name === 'upi_qr' && isset($_FILES['qr_code'])) {
        $upload_dir = '../../uploads/';
        if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);
        
        $file_ext = pathinfo($_FILES['qr_code']['name'], PATHINFO_EXTENSION);
        $file_name = 'upi_qr_' . $school_id . '_' . time() . '.' . $file_ext;
        $target_file = $upload_dir . $file_name;
        
        if (move_uploaded_file($_FILES['qr_code']['tmp_name'], $target_file)) {
            $config['qr_path'] = $file_name;
        }
    }

    try {
        $query = "INSERT INTO school_payment_gateways (school_id, gateway_name, is_active, mode, config_json) 
                  VALUES (:school_id, :name, :active, :mode, :config)
                  ON DUPLICATE KEY UPDATE 
                  is_active = :active, mode = :mode, config_json = :config";
        
        $stmt = $db->prepare($query);
        $stmt->execute([
            ':school_id' => $school_id,
            ':name' => $gateway_name,
            ':active' => $is_active,
            ':mode' => $mode,
            ':config' => json_encode($config)
        ]);

        $response = ['success' => true, 'message' => 'Payment gateway updated'];
        if (isset($config['qr_path'])) $response['qr_path'] = $config['qr_path'];
        
        echo json_encode($response);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => $e->getMessage()]);
    }
}
?>
