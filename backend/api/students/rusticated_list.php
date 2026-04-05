<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit;
}

require_once '../../middleware/auth.php';
require_once '../../config/database.php';

$auth = new AuthMiddleware();
$user = $auth->requireRole(['school_admin', 'admin']);

if (!$user) {
    exit;
}

$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 20;
$class_id = isset($_GET['class_id']) ? (int)$_GET['class_id'] : null;
$section_id = isset($_GET['section_id']) ? (int)$_GET['section_id'] : null;
$search = isset($_GET['search']) ? $_GET['search'] : '';

$database = new Database();
$db = $database->getConnection();

try {
    $offset = ($page - 1) * $limit;
    $whereConditions = ["s.school_id = :school_id", "(s.status = 'rusticated' OR s.is_active = 0)"];
    $params = [':school_id' => $user['school_id']];

    if (!empty($search)) {
        $whereConditions[] = "(s.first_name LIKE :search OR s.last_name LIKE :search OR s.student_id LIKE :search OR s.admission_number LIKE :search)";
        $params[':search'] = "%{$search}%";
    }

    if ($class_id) {
        $whereConditions[] = "se.class_id = :class_id";
        $params[':class_id'] = $class_id;
    }

    if ($section_id) {
        $whereConditions[] = "se.section_id = :section_id";
        $params[':section_id'] = $section_id;
    }

    $whereClause = implode(' AND ', $whereConditions);

    // Using GROUP BY s.id to prevent duplication
    $query = "SELECT s.*, 
                     c.name as class_name, 
                     sec.name as section_name,
                     se.roll_number
              FROM students s
              LEFT JOIN student_enrollments se ON s.id = se.student_id
              LEFT JOIN classes c ON se.class_id = c.id
              LEFT JOIN sections sec ON se.section_id = sec.id
              WHERE $whereClause
              GROUP BY s.id
              ORDER BY s.updated_at DESC
              LIMIT :limit OFFSET :offset";

    $stmt = $db->prepare($query);
    $stmt->bindValue(':school_id', $user['school_id']);
    if (!empty($search)) $stmt->bindValue(':search', $params[':search']);
    if ($class_id) $stmt->bindValue(':class_id', $class_id, PDO::PARAM_INT);
    if ($section_id) $stmt->bindValue(':section_id', $section_id, PDO::PARAM_INT);
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();

    $students = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Count for pagination
    $countQuery = "SELECT COUNT(DISTINCT s.id) FROM students s 
                   LEFT JOIN student_enrollments se ON s.id = se.student_id 
                   WHERE $whereClause";
    $countStmt = $db->prepare($countQuery);
    $countStmt->bindValue(':school_id', $user['school_id']);
    if (!empty($search)) $countStmt->bindValue(':search', $params[':search']);
    if ($class_id) $countStmt->bindValue(':class_id', $class_id, PDO::PARAM_INT);
    if ($section_id) $countStmt->bindValue(':section_id', $section_id, PDO::PARAM_INT);
    $countStmt->execute();
    $total = $countStmt->fetchColumn();

    echo json_encode([
        'success' => true,
        'data' => $students,
        'pagination' => [
            'total' => (int)$total,
            'page' => $page,
            'limit' => $limit,
            'total_pages' => ceil($total / $limit)
        ]
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Server error: ' . $e->getMessage()]);
}
?>
