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
        $query = "SELECT * FROM email_configs WHERE school_id = :school_id LIMIT 1";
        $stmt = $db->prepare($query);
        $stmt->execute([':school_id' => $school_id]);
        $config = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$config) {
            $config = [
                'use_custom' => 0,
                'smtp_host' => '',
                'smtp_port' => 587,
                'smtp_user' => '',
                'smtp_pass' => '',
                'smtp_crypto' => 'tls',
                'from_email' => '',
                'from_name' => ''
            ];
        } else {
            // DO NOT output the raw password to frontend unless requested or maybe mask it?
            // Actually it will be needed to be populated on edit.
        }

        echo json_encode(['success' => true, 'data' => $config]);
    } 
    elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $data = !empty($_POST) ? $_POST : json_decode(file_get_contents("php://input"), true);
        
        $fields = [
            'use_custom', 'smtp_host', 'smtp_port', 'smtp_user', 'smtp_pass', 'smtp_crypto', 'from_email', 'from_name'
        ];
        
        $updateData = [];
        foreach ($fields as $field) {
            if (isset($data[$field])) {
                if ($field === 'use_custom') {
                    $updateData[$field] = filter_var($data[$field], FILTER_VALIDATE_BOOLEAN) ? 1 : 0;
                } else {
                    $updateData[$field] = $data[$field];
                }
            }
        }

        // Check if config exists
        $check_query = "SELECT id FROM email_configs WHERE school_id = :school_id";
        $check_stmt = $db->prepare($check_query);
        $check_stmt->execute([':school_id' => $school_id]);
        
        if ($check_stmt->fetch()) {
            if (empty($updateData)) {
                echo json_encode(['success' => true, 'message' => 'No changes made']);
                exit;
            }
            $set_part = [];
            foreach ($updateData as $k => $v) {
                $set_part[] = "$k = :$k";
            }
            $query = "UPDATE email_configs SET " . implode(', ', $set_part) . " WHERE school_id = :school_id";
        } else {
            $updateData['school_id'] = $school_id;
            $cols = implode(', ', array_keys($updateData));
            $params = ':' . implode(', :', array_keys($updateData));
            $query = "INSERT INTO email_configs ($cols) VALUES ($params)";
        }

        $stmt = $db->prepare($query);
        if (!isset($updateData['school_id'])) {
             $updateData['school_id'] = $school_id;
        }
        $stmt->execute($updateData);

        echo json_encode(['success' => true, 'message' => 'Email configuration updated successfully']);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Server error: ' . $e->getMessage()]);
}
?>
