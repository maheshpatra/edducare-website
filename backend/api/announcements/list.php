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
$user = $auth->requireAuth();

if (!$user) {
    exit;
}

$database = new Database();
$db = $database->getConnection();

try {
    $page  = isset($_GET['page'])  ? max(1, (int)$_GET['page'])  : 1;
    $limit = isset($_GET['limit']) ? min(50, (int)$_GET['limit']) : 20;
    $offset = ($page - 1) * $limit;

    $whereConditions = ["a.school_id = :school_id", "a.is_active = 1"];
    $params = [':school_id' => $user['school_id']];

    // Non-admins only see announcements relevant to their role
    if ($user['role'] !== 'school_admin' && $user['role'] !== 'super_admin') {
        // Not expired
        $whereConditions[] = "(a.expires_at IS NULL OR a.expires_at > NOW())";
    }

    // Students see 'all', 'students', and 'parents' audience announcements
    if ($user['role'] === 'student') {
        $whereConditions[] = "(a.target_audience IN ('all', 'students', 'parents')
                               OR (a.target_audience = 'specific_class'
                                   AND EXISTS (
                                     SELECT 1 FROM student_enrollments se
                                     WHERE se.student_id = :student_id
                                       AND se.class_id = a.class_id
                                       AND (a.section_id IS NULL OR se.section_id = a.section_id)
                                       AND se.status = 'active'
                                   )))";
        $params[':student_id'] = $user['id'];
    }

    // Optional filter by priority
    if (!empty($_GET['priority'])) {
        $whereConditions[] = "a.priority = :priority";
        $params[':priority'] = $_GET['priority'];
    }

    // Optional search
    if (!empty($_GET['search'])) {
        $whereConditions[] = "(a.title LIKE :search OR a.content LIKE :search)";
        $params[':search'] = '%' . $_GET['search'] . '%';
    }

    $whereClause = implode(' AND ', $whereConditions);

    $query = "SELECT
        a.id,
        a.title,
        a.content,
        a.target_audience,
        a.priority,
        a.attachment,
        a.is_active,
        a.expires_at,
        a.created_at,
        a.class_id,
        a.section_id,
        CONCAT(u.first_name, ' ', u.last_name) AS created_by_name,
        u.profile_image AS created_by_avatar
    FROM announcements a
    LEFT JOIN users u ON a.created_by = u.id
    WHERE $whereClause
    ORDER BY
        FIELD(a.priority, 'urgent', 'high', 'medium', 'low'),
        a.created_at DESC
    LIMIT :limit OFFSET :offset";

    $stmt = $db->prepare($query);
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->bindValue(':limit',  $limit,  PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $announcements = $stmt->fetchAll();

    // Count
    $countQuery = "SELECT COUNT(*) AS total FROM announcements a WHERE $whereClause";
    $countStmt  = $db->prepare($countQuery);
    foreach ($params as $key => $value) {
        $countStmt->bindValue($key, $value);
    }
    $countStmt->execute();
    $total = (int)$countStmt->fetch()['total'];

    echo json_encode([
        'success'    => true,
        'data'       => $announcements,
        'pagination' => [
            'current_page' => $page,
            'per_page'     => $limit,
            'total'        => $total,
            'total_pages'  => (int)ceil($total / $limit),
        ],
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Internal server error', 'detail' => $e->getMessage()]);
}
?>
