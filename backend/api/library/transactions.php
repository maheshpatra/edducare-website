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
$user = $auth->requireRole(['super_admin', 'school_admin', 'librarian', 'teacher_academic', 'teacher_administrative', 'student']);
if (!$user) exit;

$database = new Database();
$db = $database->getConnection();

try {
    $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
    $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 20;
    $offset = ($page - 1) * $limit;
    
    $whereConditions = ["lb.school_id = :school_id"];
    $params = [':school_id' => $user['school_id']];
    
    // Role-based filtering
    if ($user['role'] === 'student') {
        $whereConditions[] = "bi.student_id = :user_id";
        $params[':user_id'] = $user['id'];
    }
    
    if (isset($_GET['student_id'])) {
        $whereConditions[] = "bi.student_id = :student_id";
        $params[':student_id'] = $_GET['student_id'];
    }
    
    if (isset($_GET['status'])) {
        $whereConditions[] = "bi.status = :status";
        $params[':status'] = $_GET['status'];
    }
    
    if (isset($_GET['overdue']) && $_GET['overdue'] === 'true') {
        $whereConditions[] = "bi.status = 'issued' AND bi.due_date < CURDATE()";
    }
    
    $whereClause = implode(' AND ', $whereConditions);
    
    $query = "SELECT 
        bi.*,
        lb.title as book_title, lb.author, lb.isbn,
        CONCAT(st.first_name, ' ', st.last_name) as student_name,
        se.roll_number,
        c.name as class_name,
        sec.name as section_name,
        CONCAT(issued.first_name, ' ', issued.last_name) as issued_by_name,
        CASE 
            WHEN bi.status = 'issued' AND bi.due_date < CURDATE() THEN 'overdue'
            ELSE bi.status
        END as display_status,
        CASE 
            WHEN bi.status = 'issued' AND bi.due_date < CURDATE() THEN DATEDIFF(CURDATE(), bi.due_date)
            ELSE 0
        END as overdue_days
        FROM book_issues bi
        INNER JOIN library_books lb ON bi.book_id = lb.id
        LEFT JOIN students st ON bi.student_id = st.id
        LEFT JOIN student_enrollments se ON bi.student_id = se.student_id AND se.status = 'active'
        LEFT JOIN classes c ON se.class_id = c.id
        LEFT JOIN sections sec ON se.section_id = sec.id
        LEFT JOIN users issued ON bi.issued_by = issued.id
        WHERE $whereClause
        ORDER BY bi.issue_date DESC
        LIMIT :limit OFFSET :offset";
    
    $stmt = $db->prepare($query);
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    
    $transactions = $stmt->fetchAll();
    
    // Get total count
    $countQuery = "SELECT COUNT(*) as total 
                   FROM book_issues bi
                   INNER JOIN library_books lb ON bi.book_id = lb.id
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
        'data' => $transactions,
        'pagination' => [
            'current_page' => $page,
            'per_page' => $limit,
            'total' => $total,
            'total_pages' => ceil($total / $limit)
        ]
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Internal server error', 'details' => $e->getMessage()]);
}
?>
