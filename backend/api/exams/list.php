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
$user = $auth->requireRole(['school_admin', 'class_teacher', 'student']);

if (!$user) {
    exit;
}

$database = new Database();
$db = $database->getConnection();

try {
    $page   = isset($_GET['page'])  ? (int)$_GET['page']  : 1;
    $limit  = isset($_GET['limit']) ? (int)$_GET['limit'] : 20;
    $offset = ($page - 1) * $limit;

    $whereConditions = ["e.school_id = :school_id", "e.class_id IS NOT NULL"];
    $params = [':school_id' => $user['school_id']];

    // Role-based filtering
    if ($user['role'] === 'class_teacher') {
        $whereConditions[] = "e.created_by = :teacher_id";
        $params[':teacher_id'] = $user['id'];
    }

    if ($user['role'] === 'student') {
        // Filter exams to the student's enrolled class/section
        $whereConditions[] = "EXISTS (
            SELECT 1 FROM student_enrollments se
            WHERE se.student_id = :student_id
              AND se.class_id = e.class_id
              AND (e.section_id IS NULL OR se.section_id = e.section_id)
              AND se.status = 'active'
        )";
        $params[':student_id'] = $user['id'];
    }

    if (isset($_GET['class_id']) && $_GET['class_id'] !== '') {
        $whereConditions[] = "e.class_id = :class_id";
        $params[':class_id'] = (int)$_GET['class_id'];
    }

    if (isset($_GET['subject']) && $_GET['subject'] !== '') {
        $whereConditions[] = "e.subject LIKE :subject";
        $params[':subject'] = '%' . $_GET['subject'] . '%';
    }

    if (isset($_GET['exam_type']) && $_GET['exam_type'] !== '') {
        $whereConditions[] = "e.exam_type = :exam_type";
        $params[':exam_type'] = $_GET['exam_type'];
    }

    $whereClause = implode(' AND ', $whereConditions);

    // Check if exam_results has exam_id or exam_subject_id column
    $erColumns = $db->query("SHOW COLUMNS FROM exam_results")->fetchAll(PDO::FETCH_COLUMN);
    $hasExamId = in_array('exam_id', $erColumns);

    $resultCountExpr = $hasExamId
        ? "(SELECT COUNT(*) FROM exam_results er WHERE er.exam_id = e.id)"
        : "(SELECT COUNT(*) FROM exam_results er
               INNER JOIN exam_subjects es ON er.exam_subject_id = es.id
               WHERE es.exam_id = e.id)";

    $query = "SELECT
        e.*,
        c.name as class_name,
        c.grade_level,
        sec.name as section_name,
        CONCAT(u.first_name, ' ', u.last_name) as created_by_name,
        $resultCountExpr as result_count,
        (SELECT COUNT(DISTINCT se2.student_id) FROM student_enrollments se2
         WHERE se2.class_id = e.class_id
           AND (e.section_id IS NULL OR se2.section_id = e.section_id)
           AND se2.status = 'active') as eligible_students
    FROM exams e
    INNER JOIN classes c ON e.class_id = c.id
    LEFT JOIN sections sec ON e.section_id = sec.id
    LEFT JOIN users u ON e.created_by = u.id
    WHERE $whereClause
    ORDER BY e.exam_date DESC
    LIMIT :limit OFFSET :offset";

    $stmt = $db->prepare($query);
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->bindValue(':limit',  $limit,  PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $exams = $stmt->fetchAll();

    foreach ($exams as &$exam) {
        $exam['result_percentage'] = ($exam['eligible_students'] > 0)
            ? round(($exam['result_count'] / $exam['eligible_students']) * 100, 2)
            : 0;
    }

    // Count query — must also JOIN classes so WHERE clause is valid
    $countQuery = "SELECT COUNT(*) as total
                   FROM exams e
                   INNER JOIN classes c ON e.class_id = c.id
                   WHERE $whereClause";
    $countStmt = $db->prepare($countQuery);
    foreach ($params as $key => $value) {
        $countStmt->bindValue($key, $value);
    }
    $countStmt->execute();
    $total = $countStmt->fetch()['total'];

    echo json_encode([
        'success'    => true,
        'data'       => $exams,
        'pagination' => [
            'current_page' => $page,
            'per_page'     => $limit,
            'total'        => $total,
            'total_pages'  => ceil($total / $limit),
        ],
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Internal server error', 'detail' => $e->getMessage()]);
}
?>
