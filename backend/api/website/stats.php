<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, DELETE, OPTIONS');
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
        $query = "SELECT * FROM school_stats 
                  WHERE school_id = :school_id 
                  ORDER BY sort_order ASC, label ASC";
        $stmt = $db->prepare($query);
        $stmt->execute([':school_id' => $school_id]);
        $stats = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode(['success' => true, 'data' => $stats]);
    } 
    elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $data = json_decode(file_get_contents("php://input"), true);
        
        $id = $data['id'] ?? null;
        $label = $data['label'] ?? '';
        $value = $data['value'] ?? '';
        $icon = $data['icon'] ?? 'Users';
        $sort_order = $data['sort_order'] ?? 0;

        if (!$label || !$value) {
            http_response_code(400);
            echo json_encode(['error' => 'Label and value are required']);
            exit;
        }

        if ($id) {
            $query = "UPDATE school_stats SET label = :label, value = :value, 
                      icon = :icon, sort_order = :sort_order 
                      WHERE id = :id AND school_id = :school_id";
            $stmt = $db->prepare($query);
            $stmt->execute([
                ':id' => $id,
                ':school_id' => $school_id,
                ':label' => $label,
                ':value' => $value,
                ':icon' => $icon,
                ':sort_order' => $sort_order
            ]);
        } else {
            $query = "INSERT INTO school_stats (school_id, label, value, icon, sort_order) 
                      VALUES (:school_id, :label, :value, :icon, :sort_order)";
            $stmt = $db->prepare($query);
            $stmt->execute([
                ':school_id' => $school_id,
                ':label' => $label,
                ':value' => $value,
                ':icon' => $icon,
                ':sort_order' => $sort_order
            ]);
        }
        
        echo json_encode(['success' => true, 'message' => 'Stat saved successfully']);
    } 
    elseif ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
        $id = $_GET['id'] ?? null;
        if (!$id) {
            http_response_code(400);
            echo json_encode(['error' => 'ID is required']);
            exit;
        }

        $query = "DELETE FROM school_stats WHERE id = :id AND school_id = :school_id";
        $stmt = $db->prepare($query);
        $stmt->execute([':id' => (int)$id, ':school_id' => $school_id]);
        
        if ($stmt->rowCount() > 0) {
            echo json_encode(['success' => true, 'message' => 'Stat deleted']);
        } else {
            echo json_encode(['error' => 'Stat not found or access denied']);
        }
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Server error: ' . $e->getMessage()]);
}
?>
