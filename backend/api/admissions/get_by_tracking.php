<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
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
$tracking_id = isset($_GET['tracking_id']) ? trim($_GET['tracking_id']) : null;

if (!$tracking_id) {
    http_response_code(400);
    echo json_encode(['error' => 'Tracking ID is required']);
    exit;
}

try {
    $query = "SELECT * FROM admission_requests WHERE tracking_id = :tracking_id AND school_id = :school_id LIMIT 1";
    $stmt = $db->prepare($query);
    $stmt->execute([':tracking_id' => $tracking_id, ':school_id' => $school_id]);
    $request = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$request) {
        http_response_code(404);
        echo json_encode(['error' => 'Admission requested not found with this Tracking ID']);
        exit;
    }

    echo json_encode([
        'success' => true,
        'data' => $request
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Server error: ' . $e->getMessage()]);
}
?>
