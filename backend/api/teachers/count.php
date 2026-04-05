<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

require_once '../../middleware/auth.php';
require_once '../../config/database.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$auth = new AuthMiddleware();
$user = $auth->requireRole(['super_admin', 'school_admin', 'admin']);

if (!$user) {
    exit;
}

$database = new Database();
$db = $database->getConnection();

try {
    $schoolCondition = "";
    $params = [];
    
    if ($user['role_name'] !== 'super_admin') {
        $schoolCondition = "WHERE school_id = :school_id";
        $params[':school_id'] = $user['school_id'];
    } else if (isset($_GET['school_id'])) {
        $schoolCondition = "WHERE school_id = :school_id";
        $params[':school_id'] = $_GET['school_id'];
    }
    
    // Add role filter for teachers only (role_id 3-4)
    $roleCondition = $schoolCondition ? " AND role_id IN (3, 4) AND is_active = 1" : "WHERE role_id IN (3, 4) AND is_active = 1";
    $schoolCondition .= $roleCondition;
    
    $query = "SELECT COUNT(*) as total FROM users $schoolCondition";
    $stmt = $db->prepare($query);
    
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'count' => (int)$result['total']
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Internal server error', 'details' => $e->getMessage()]);
}
?>
