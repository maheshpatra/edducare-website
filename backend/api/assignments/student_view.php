<?php
/**
 * Student Assignments View
 * GET /api/assignments/student_view
 * Returns assignments for the authenticated student's class/section,
 * enriched with the student's own submission status.
 */
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
$user = $auth->requireRole(['student']);

if (!$user) {
    exit;
}

$database = new Database();
$db = $database->getConnection();

try {
    $page      = isset($_GET['page'])  ? (int)$_GET['page']  : 1;
    $limit     = isset($_GET['limit']) ? (int)$_GET['limit'] : 50;
    $offset    = ($page - 1) * $limit;
    $studentId = (int)$user['id'];

    // ── Get student's active enrollment ─────────────────────
    // student_enrollments.student_id references students.id (enhanced schema)
    $enrollStmt = $db->prepare("
        SELECT se.class_id, se.section_id,
               c.name  as class_name,
               sec.name as section_name
        FROM   student_enrollments se
        JOIN   classes c   ON se.class_id   = c.id
        LEFT JOIN sections sec ON se.section_id = sec.id
        WHERE  se.student_id = :student_id
          AND  se.status = 'active'
        ORDER BY se.enrollment_date DESC
        LIMIT 1
    ");
    $enrollStmt->bindValue(':student_id', $studentId, PDO::PARAM_INT);
    $enrollStmt->execute();
    $enrollment = $enrollStmt->fetch();

    if (!$enrollment) {
        echo json_encode([
            'success'    => true,
            'data'       => [],
            'message'    => 'No active enrollment found for this student',
            'pagination' => ['current_page' => 1, 'per_page' => $limit, 'total' => 0, 'total_pages' => 0],
        ]);
        exit;
    }

    $classId   = (int)$enrollment['class_id'];
    $sectionId = (int)$enrollment['section_id'];

    // ── Build WHERE clause ───────────────────────────────────
    $whereConditions = [
        "a.class_id  = :class_id",
        "c.school_id = :school_id",
        "(a.section_id = :section_id OR a.section_id IS NULL)",
    ];
    $params = [
        ':class_id'   => $classId,
        ':section_id' => $sectionId,
        ':school_id'  => (int)$user['school_id'],
    ];

    if (!empty($_GET['search'])) {
        $whereConditions[] = "(a.title LIKE :search OR a.description LIKE :search)";
        $params[':search']  = '%' . $_GET['search'] . '%';
    }

    $whereClause = implode(' AND ', $whereConditions);

    // ── Main query ───────────────────────────────────────────
    $query = "
        SELECT
            a.id,
            a.title,
            a.description,
            a.due_date,
            a.max_marks,
            a.created_at,
            sub.id   AS subject_id,
            sub.name AS subject_name,
            sub.code AS subject_code,
            c.id     AS class_id,
            c.name   AS class_name,
            sec.name AS section_name,
            CONCAT(u.first_name, ' ', u.last_name) AS teacher_name,
            asm.id             AS submission_id,
            asm.submitted_at,
            asm.marks_obtained,
            asm.feedback,
            CASE
                WHEN asm.marks_obtained IS NOT NULL THEN 'graded'
                WHEN asm.id IS NOT NULL              THEN 'submitted'
                WHEN a.due_date < NOW()              THEN 'overdue'
                ELSE 'not_started'
            END AS status
        FROM assignments a
        INNER JOIN classes  c    ON a.class_id  = c.id
        LEFT  JOIN sections sec  ON a.section_id = sec.id
        LEFT  JOIN subjects sub  ON a.subject_id = sub.id
        INNER JOIN users    u    ON a.teacher_id = u.id
        LEFT  JOIN assignment_submissions asm
                   ON asm.assignment_id = a.id
                  AND asm.student_id    = :student_id
        WHERE $whereClause
        ORDER BY a.due_date ASC
        LIMIT :limit OFFSET :offset
    ";

    $stmt = $db->prepare($query);
    foreach ($params as $k => $v) {
        $stmt->bindValue($k, $v);
    }
    $stmt->bindValue(':student_id', $studentId, PDO::PARAM_INT);
    $stmt->bindValue(':limit',  $limit,  PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $assignments = $stmt->fetchAll();

    // Count
    $countQuery = "
        SELECT COUNT(*) AS total
        FROM   assignments a
        INNER JOIN classes c ON a.class_id = c.id
        WHERE  $whereClause
    ";
    $countStmt = $db->prepare($countQuery);
    foreach ($params as $k => $v) {
        $countStmt->bindValue($k, $v);
    }
    $countStmt->execute();
    $total = (int)$countStmt->fetch()['total'];

    echo json_encode([
        'success'    => true,
        'data'       => $assignments,
        'enrollment' => [
            'class_id'     => $classId,
            'section_id'   => $sectionId,
            'class_name'   => $enrollment['class_name'],
            'section_name' => $enrollment['section_name'],
        ],
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
