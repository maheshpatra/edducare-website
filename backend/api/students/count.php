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
$user = $auth->requireRole(['super_admin', 'school_admin', 'class_teacher', 'payment_teacher']);

if (!$user) {
    exit;
}

$database = new Database();
$db = $database->getConnection();

try {
    $schoolCondition = "";
    $params = [];
    
    if ($user['role_name'] !== 'super_admin') {
        $schoolCondition = "WHERE school_id = :school_id AND status = 'active'";
        $params[':school_id'] = $user['school_id'];
    } else if (isset($_GET['school_id'])) {
        $schoolCondition = "WHERE school_id = :school_id AND status = 'active'";
        $params[':school_id'] = $_GET['school_id'];
    } else {
        $schoolCondition = "WHERE status = 'active'";
    }
    
    $query = "SELECT COUNT(*) as total FROM students $schoolCondition";
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
