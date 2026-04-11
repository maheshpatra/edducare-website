<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

require_once '../../config/database.php';

$database = new Database();
$db = $database->getConnection();

$school_id = isset($_GET['school_id']) ? (int)$_GET['school_id'] : null;

if (!$school_id) {
    http_response_code(400);
    echo json_encode(['error' => 'School ID is required']);
    exit;
}

try {
    $query = "SELECT c.id, c.name, c.grade_level, c.room_number, c.capacity,
                     (SELECT COUNT(*) FROM student_enrollments WHERE class_id = c.id) as current_students
              FROM classes c
              WHERE c.school_id = :school_id
              ORDER BY c.grade_level ASC, c.name ASC";
    $stmt = $db->prepare($query);
    $stmt->execute([':school_id' => $school_id]);
    $classes = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['success' => true, 'data' => $classes]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Server error: ' . $e->getMessage()]);
}
?>
