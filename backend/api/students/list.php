<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

require_once '../../middleware/auth.php';

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
    $page = isset($_GET['page']) ? (int) $_GET['page'] : 1;
    $limit = isset($_GET['limit']) ? (int) $_GET['limit'] : 20;
    $offset = ($page - 1) * $limit;

    $schoolCondition = "";
    $params = [];

    if ($user['role'] !== 'super_admin') {
        $schoolCondition = "WHERE s.school_id = :school_id";
        $params[':school_id'] = $user['school_id'];
    } else if (isset($_GET['school_id'])) {
        $schoolCondition = "WHERE s.school_id = :school_id";
        $params[':school_id'] = $_GET['school_id'];
    }

    // Add filters
    $filters = [];
    if (isset($_GET['class_id'])) {
        $filters[] = "se.class_id = :class_id";
        $params[':class_id'] = $_GET['class_id'];
    }

    if (isset($_GET['section_id'])) {
        $filters[] = "se.section_id = :section_id";
        $params[':section_id'] = $_GET['section_id'];
    }

    if (isset($_GET['caste'])) {
        $filters[] = "s.caste = :caste";
        $params[':caste'] = $_GET['caste'];
    }

    if (isset($_GET['search'])) {
        $filters[] = "(s.first_name LIKE :search OR s.last_name LIKE :search OR s.student_id LIKE :search)";
        $params[':search'] = '%' . $_GET['search'] . '%';
    }

    if (!empty($filters)) {
        if (empty($schoolCondition)) {
            $schoolCondition = "WHERE " . implode(' AND ', $filters);
        } else {
            $schoolCondition .= " AND " . implode(' AND ', $filters);
        }
    }

    $query = "SELECT s.*, c.name as class_name, sec.name as section_name, sch.name as school_name,
                     se.class_id, se.section_id, se.roll_number
              FROM students s
              LEFT JOIN student_enrollments se ON s.id = se.student_id AND se.status = 'active'
              LEFT JOIN classes c ON se.class_id = c.id
              LEFT JOIN sections sec ON se.section_id = sec.id
              LEFT JOIN schools sch ON s.school_id = sch.id
              $schoolCondition
              ORDER BY s.first_name, s.last_name
              LIMIT :limit OFFSET :offset";

    $stmt = $db->prepare($query);

    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);

    $stmt->execute();
    $students = $stmt->fetchAll();

    // Get total count
    $countQuery = "SELECT COUNT(*) as total FROM students s 
                   LEFT JOIN student_enrollments se ON s.id = se.student_id AND se.status = 'active'
                   LEFT JOIN schools sch ON s.school_id = sch.id 
                   $schoolCondition";
    $countStmt = $db->prepare($countQuery);

    foreach ($params as $key => $value) {
        if ($key !== ':limit' && $key !== ':offset') {
            $countStmt->bindValue($key, $value);
        }
    }

    $countStmt->execute();
    $total = $countStmt->fetch()['total'];

    echo json_encode([
        'success' => true,
        'data' => $students,
        'pagination' => [
            'current_page' => $page,
            'per_page' => $limit,
            'total' => $total,
            'total_pages' => ceil($total / $limit)
        ]
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Internal server error', 'details' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
}
?>