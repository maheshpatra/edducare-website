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
$user = $auth->requireRole(['school_admin', 'class_teacher']);

if (!$user) {
    exit;
}

$database = new Database();
$db = $database->getConnection();

try {
    $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
    $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 20;
    $offset = ($page - 1) * $limit;
    
    // Base query conditions
    $whereConditions = [];
    $params = [];
    
    // School-level filtering
    $whereConditions[] = "c.school_id = :school_id";
    $params[':school_id'] = $user['school_id'];
    
    // Role-based filtering
    if ($user['role'] === 'class_teacher') {
        $whereConditions[] = "a.teacher_id = :teacher_id";
        $params[':teacher_id'] = $user['id'];
    }
    
    // Additional filters
    if (isset($_GET['class_id'])) {
        $whereConditions[] = "a.class_id = :class_id";
        $params[':class_id'] = $_GET['class_id'];
    }
    
    if (isset($_GET['section_id'])) {
        $whereConditions[] = "a.section_id = :section_id";
        $params[':section_id'] = $_GET['section_id'];
    }
    
    if (isset($_GET['subject'])) {
        $whereConditions[] = "sub.name LIKE :subject";
        $params[':subject'] = '%' . $_GET['subject'] . '%';
    }
    
    if (isset($_GET['search'])) {
        $whereConditions[] = "(a.title LIKE :search OR a.description LIKE :search)";
        $params[':search'] = '%' . $_GET['search'] . '%';
    }
    
    $whereClause = implode(' AND ', $whereConditions);
    
    $query = "SELECT 
        a.id,
        a.title,
        a.description,
        sub.name as subject,
        a.due_date,
        a.max_marks,
        a.attachment as attachment_path,
        NULL as attachment_name,
        NULL as attachment_type,
        a.created_at,
        c.name as class_name,
        c.grade_level,
        s.name as section_name,
        CONCAT(u.first_name, ' ', u.last_name) as teacher_name,
        (SELECT COUNT(*) FROM assignment_submissions WHERE assignment_id = a.id) as submission_count,
        (SELECT COUNT(DISTINCT student_id) FROM student_enrollments WHERE class_id = a.class_id AND (a.section_id IS NULL OR section_id = a.section_id) AND status = 'active') as total_students
        FROM assignments a
        INNER JOIN classes c ON a.class_id = c.id
        INNER JOIN subjects sub ON a.subject_id = sub.id
        LEFT JOIN sections s ON a.section_id = s.id
        INNER JOIN users u ON a.teacher_id = u.id
        WHERE $whereClause
        ORDER BY a.created_at DESC
        LIMIT :limit OFFSET :offset";
    
    $stmt = $db->prepare($query);
    
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    
    $stmt->execute();
    $assignments = $stmt->fetchAll();
    
    // Process assignments to add attachment info
    foreach ($assignments as &$assignment) {
        $assignment['has_attachment'] = !empty($assignment['attachment_path']);
        $assignment['attachment_url'] = $assignment['attachment_path'] ? 
            '/api/assignments/download.php?file=' . urlencode($assignment['attachment_path']) : null;
        
        // Calculate submission percentage
        $assignment['submission_percentage'] = $assignment['total_students'] > 0 ? 
            round(($assignment['submission_count'] / $assignment['total_students']) * 100, 2) : 0;
    }
    
    // Get total count for pagination
    $countQuery = "SELECT COUNT(*) as total 
                   FROM assignments a
                   INNER JOIN classes c ON a.class_id = c.id
                   INNER JOIN subjects sub ON a.subject_id = sub.id
                   WHERE $whereClause";
    
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
        'data' => $assignments,
        'pagination' => [
            'current_page' => $page,
            'per_page' => $limit,
            'total' => $total,
            'total_pages' => ceil($total / $limit)
        ]
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'error' => 'Internal server error',
        'details' => $e->getMessage(),
        'query' => $query ?? null,
        'params' => $params ?? null
    ]);
}
?>
