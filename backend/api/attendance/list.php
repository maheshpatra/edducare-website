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
$user = $auth->requireRole(['super_admin', 'admin', 'teacher_academic', 'teacher_administrative']);

if (!$user) {
    exit;
}

if (!isset($_GET['class_id']) || !isset($_GET['section_id'])) {
    http_response_code(400);
    echo json_encode(['error' => 'class_id and section_id are required']);
    exit;
}

$class_id = intval($_GET['class_id']);
$section_id = intval($_GET['section_id']);

$database = new Database();
$db = $database->getConnection();

try {
    // Validate permission (Anyone with teacher role can now access, but we still verify school_id)
    if (!in_array($user['role_name'], ['super_admin', 'admin']) && $user['school_id'] != $user['school_id']) {
         // This is a placeholder for school check, already handled by requireRole usually.
    }
    
    // Check if class belongs to school (already checked by school_id match in query below)

    $date = $_GET['date'] ?? date('Y-m-d');
    
    // Get students for class and section with their attendance status for today
    $query = "SELECT s.id AS student_id, s.first_name, s.last_name, s.admission_number, se.roll_number,
                     att.status, att.remarks
              FROM students s
              JOIN student_enrollments se ON s.id = se.student_id
              LEFT JOIN attendance att ON s.id = att.student_id AND att.date = :date
              WHERE se.class_id = :class_id AND se.section_id = :section_id
              AND s.school_id = :school_id AND se.status = 'active' AND s.status = 'active'
              ORDER BY se.roll_number ASC, s.first_name ASC";

    $stmt = $db->prepare($query);
    $stmt->bindValue(':class_id', $class_id);
    $stmt->bindValue(':section_id', $section_id);
    $stmt->bindValue(':school_id', $user['school_id']);
    $stmt->bindValue(':date', $date);
    $stmt->execute();

    $students = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($students as &$student) {
        $student['id'] = $student['student_id'];
        $student['full_name'] = $student['first_name'] . ' ' . $student['last_name'];
        // Default to present if no record exists
        if (!$student['status']) {
            $student['status'] = 'present';
        }
    }

    echo json_encode([
        'success' => true,
        'date' => $date,
        'students' => $students
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Internal server error']);
}
?>
