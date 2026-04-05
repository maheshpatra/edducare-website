<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

require_once '../../middleware/auth.php';
require_once '../../config/database.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$auth = new AuthMiddleware();
$user = $auth->authenticate();

if (!$user) {
    exit;
}

$database = new Database();
$db = $database->getConnection();

try {
    $studentId = $_GET['student_id'] ?? ($user['role'] === 'student' ? $user['id'] : null);
    
    if (!$studentId) {
        http_response_code(400);
        echo json_encode(['error' => 'Student ID is required']);
        exit;
    }
    
    // Get student's current enrollment
    $enrollmentQuery = "SELECT se.*, c.name as class_name, sec.name as section_name 
                        FROM student_enrollments se
                        JOIN classes c ON se.class_id = c.id
                        LEFT JOIN sections sec ON se.section_id = sec.id
                        WHERE se.student_id = :student_id AND se.status = 'active'";
    
    $enrollmentStmt = $db->prepare($enrollmentQuery);
    $enrollmentStmt->bindValue(':student_id', $studentId);
    $enrollmentStmt->execute();
    
    $enrollment = $enrollmentStmt->fetch();
    
    if (!$enrollment) {
        http_response_code(404);
        echo json_encode(['error' => 'Student enrollment not found']);
        exit;
    }
    
    // Get timetable for the student's class and section
    $timetableQuery = "SELECT t.*, s.name as subject_name, s.code as subject_code,
                              u.first_name as teacher_first_name, u.last_name as teacher_last_name
                       FROM timetables t
                       JOIN subjects s ON t.subject_id = s.id
                       JOIN users u ON t.teacher_id = u.id
                       WHERE t.class_id = :class_id AND (t.section_id = :section_id OR t.section_id IS NULL)
                       ORDER BY 
                         CASE t.day_of_week 
                           WHEN 'monday' THEN 1
                           WHEN 'tuesday' THEN 2
                           WHEN 'wednesday' THEN 3
                           WHEN 'thursday' THEN 4
                           WHEN 'friday' THEN 5
                           WHEN 'saturday' THEN 6
                           WHEN 'sunday' THEN 7
                         END,
                         t.period_number";
    
    $timetableStmt = $db->prepare($timetableQuery);
    $timetableStmt->bindValue(':class_id', $enrollment['class_id']);
    $timetableStmt->bindValue(':section_id', $enrollment['section_id']);
    $timetableStmt->execute();
    
    $timetable = $timetableStmt->fetchAll();
    
    // Group by day of week
    $routine = [];
    foreach ($timetable as $period) {
        $day = $period['day_of_week'];
        if (!isset($routine[$day])) {
            $routine[$day] = [];
        }
        $routine[$day][] = [
            'period_number' => $period['period_number'],
            'start_time' => $period['start_time'],
            'end_time' => $period['end_time'],
            'subject_name' => $period['subject_name'],
            'subject_code' => $period['subject_code'],
            'teacher_name' => $period['teacher_first_name'] . ' ' . $period['teacher_last_name'],
            'room' => $period['room']
        ];
    }
    
    echo json_encode([
        'success' => true,
        'data' => [
            'student_info' => [
                'class_name' => $enrollment['class_name'],
                'section_name' => $enrollment['section_name'],
                'roll_number' => $enrollment['roll_number']
            ],
            'routine' => $routine
        ]
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'error' => 'Internal server error',
        'details' => $e->getMessage()
    ]);
}
?>
