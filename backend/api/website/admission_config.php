<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit;
}

require_once '../../middleware/auth.php';

$auth = new AuthMiddleware();
$user = $auth->requireRole(['super_admin', 'school_admin']);

if (!$user) {
    exit;
}

$database = new Database();
$db = $database->getConnection();

$school_id = $user['school_id'];
if ($user['role'] === 'super_admin' && isset($_GET['school_id'])) {
    $school_id = (int)$_GET['school_id'];
}

try {
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
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
                ]),
                'is_admission_fee_enabled' => 0,
                'admission_fee_amount' => 0.00,
                'qr_code' => null
            ];
        }

        echo json_encode(['success' => true, 'data' => $config]);
    } 
    elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $data = !empty($_POST) ? $_POST : json_decode(file_get_contents("php://input"), true);
        
        $fields_json = $data['fields_json'] ?? null;
        $is_admission_fee_enabled = isset($data['is_admission_fee_enabled']) ? (int)$data['is_admission_fee_enabled'] : null;
        $admission_fee_amount = isset($data['admission_fee_amount']) ? (float)$data['admission_fee_amount'] : null;
        
        // Handle QR Code Upload
        $qr_path = null;
        if (isset($_FILES['qr_code']) && $_FILES['qr_code']['error'] === UPLOAD_ERR_OK) {
            $upload_dir = __DIR__ . '/../../uploads/schools/';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }
            $ext = pathinfo($_FILES['qr_code']['name'], PATHINFO_EXTENSION);
            $filename = 'qr_' . $school_id . '_' . uniqid() . '.' . $ext;
            if (move_uploaded_file($_FILES['qr_code']['tmp_name'], $upload_dir . $filename)) {
                $qr_path = 'schools/' . $filename;
            }
        }

        // Check if config exists
        $check_query = "SELECT id FROM admission_configs WHERE school_id = :school_id";
        $check_stmt = $db->prepare($check_query);
        $check_stmt->execute([':school_id' => $school_id]);
        
        $exists = $check_stmt->fetch();
        
        $updateFields = [];
        $params = [':school_id' => $school_id];
        
        if ($fields_json !== null) {
            $updateFields[] = "fields_json = :fields_json";
            $params[':fields_json'] = is_string($fields_json) ? $fields_json : json_encode($fields_json);
        }
        if ($is_admission_fee_enabled !== null) {
            $updateFields[] = "is_admission_fee_enabled = :is_admission_fee_enabled";
            $params[':is_admission_fee_enabled'] = $is_admission_fee_enabled;
        }
        if ($admission_fee_amount !== null) {
            $updateFields[] = "admission_fee_amount = :admission_fee_amount";
            $params[':admission_fee_amount'] = $admission_fee_amount;
        }
        if ($qr_path !== null) {
            $updateFields[] = "qr_code = :qr_code";
            $params[':qr_code'] = $qr_path;
        }

        if (empty($updateFields)) {
            echo json_encode(['success' => false, 'error' => 'No fields to update']);
            exit;
        }

        if ($exists) {
            $query = "UPDATE admission_configs SET " . implode(', ', $updateFields) . " WHERE school_id = :school_id";
        } else {
            $cols = "school_id, " . implode(', ', array_map(function($f) { return explode(' = ', $f)[0]; }, $updateFields));
            $pNames = ":school_id, " . implode(', ', array_map(function($f) { return explode(' = ', $f)[1]; }, $updateFields));
            $query = "INSERT INTO admission_configs ($cols) VALUES ($pNames)";
        }

        $stmt = $db->prepare($query);
        $stmt->execute($params);

        echo json_encode(['success' => true, 'message' => 'Admission configuration updated successfully', 'qr_code' => $qr_path]);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Server error: ' . $e->getMessage()]);
}
?>
