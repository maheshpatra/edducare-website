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
if ($user['role'] === 'super_admin' && isset($_GET['school_id'])) {
    $school_id = (int)$_GET['school_id'];
}

$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 20;
$offset = ($page - 1) * $limit;
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$status = isset($_GET['status']) ? trim($_GET['status']) : '';

try {
    $where = "school_id = :school_id";
    $params = [':school_id' => $school_id];

    if ($search) {
        $where .= " AND (full_name LIKE :search OR email LIKE :search OR subject LIKE :search)";
        $params[':search'] = "%$search%";
    }
    
    if ($status) {
        $where .= " AND status = :status";
        $params[':status'] = $status;
    }

    $count_query = "SELECT COUNT(*) as total FROM contact_messages WHERE $where";
    $count_stmt = $db->prepare($count_query);
    $count_stmt->execute($params);
    $total_rows = $count_stmt->fetch(PDO::FETCH_ASSOC)['total'];

    $query = "SELECT * FROM contact_messages WHERE $where ORDER BY created_at DESC LIMIT :limit OFFSET :offset";
    $stmt = $db->prepare($query);
    foreach ($params as $key => &$val) {
        $stmt->bindParam($key, $val);
    }
    $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    
    $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'data' => $messages,
        'pagination' => [
            'current_page' => $page,
            'per_page' => $limit,
            'total' => $total_rows,
            'total_pages' => ceil($total_rows / $limit)
        ]
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Server error: ' . $e->getMessage()]);
}
?>
