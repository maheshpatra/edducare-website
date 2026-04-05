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
                ])
            ];
        }

        echo json_encode(['success' => true, 'data' => $config]);
    } 
    elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $data = !empty($_POST) ? $_POST : json_decode(file_get_contents("php://input"), true);
        
        $fields_json = $data['fields_json'] ?? null;
        
        if (!$fields_json) {
            echo json_encode(['success' => false, 'error' => 'No configuration provided']);
            exit;
        }

        // Check if config exists
        $check_query = "SELECT id FROM admission_configs WHERE school_id = :school_id";
        $check_stmt = $db->prepare($check_query);
        $check_stmt->execute([':school_id' => $school_id]);
        
        if ($check_stmt->fetch()) {
            $query = "UPDATE admission_configs SET fields_json = :fields_json WHERE school_id = :school_id";
        } else {
            $query = "INSERT INTO admission_configs (school_id, fields_json) VALUES (:school_id, :fields_json)";
        }

        $stmt = $db->prepare($query);
        $stmt->execute([
            ':school_id' => $school_id,
            ':fields_json' => is_string($fields_json) ? $fields_json : json_encode($fields_json)
        ]);

        echo json_encode(['success' => true, 'message' => 'Admission configuration updated successfully']);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Server error: ' . $e->getMessage()]);
}
?>
