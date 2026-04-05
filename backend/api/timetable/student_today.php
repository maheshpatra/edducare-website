<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

require_once '../../middleware/auth.php';
require_once '../../config/database.php';
require_once '../../includes/response.php';

$auth = new AuthMiddleware();
$user = $auth->requireRole(['super_admin', 'admin', 'teacher_academic', 'student']);

if (!$user) {
    exit;
}

$database = new Database();
$db = $database->getConnection();

try {
    $studentId = $_GET['student_id'] ?? null;
    $date = $_GET['date'] ?? date('Y-m-d');
    $dayOfWeek = strtolower(date('l', strtotime($date)));
    $currentTime = date('H:i:s');
    
    if (!$studentId) {
        Response::error('Student ID is required', 400);
    }
    
    // Validate access - students can only view their own schedule
    if ($user['user_type'] === 'student' && $user['id'] != $studentId) {
        Response::error('Access denied', 403);
    }
    
    // Get student's current enrollment
    $enrollmentQuery = "SELECT se.class_id, se.section_id, se.roll_number,
                               c.name as class_name, c.grade_level,
                               sec.name as section_name,
                               s.first_name, s.last_name, s.student_id as student_code
                        FROM student_enrollments se
                        JOIN classes c ON se.class_id = c.id
                        LEFT JOIN sections sec ON se.section_id = sec.id
                        JOIN students s ON se.student_id = s.id
                        WHERE se.student_id = :student_id AND se.status = 'active'
                        ORDER BY se.enrollment_date DESC
                        LIMIT 1";
    
    $enrollmentStmt = $db->prepare($enrollmentQuery);
    $enrollmentStmt->bindValue(':student_id', $studentId);
    $enrollmentStmt->execute();
    
    $enrollment = $enrollmentStmt->fetch();
    
    if (!$enrollment) {
        Response::error('Student enrollment not found', 404);
    }
    
    // Get today's schedule
    $scheduleQuery = "SELECT t.*, 
                             s.name as subject_name, s.code as subject_code,
                             CONCAT(u.first_name, ' ', u.last_name) as teacher_name,
                             u.employee_id,
                             u.phone as teacher_phone,
                             u.email as teacher_email,
                             CASE 
                                 WHEN t.end_time < :current_time THEN 'completed'
                                 WHEN t.start_time <= :current_time AND t.end_time >= :current_time THEN 'ongoing'
                                 ELSE 'upcoming'
                             END as status,
                             CASE 
                                 WHEN t.start_time <= :current_time AND t.end_time >= :current_time THEN 
                                     TIMESTAMPDIFF(MINUTE, :current_time, t.end_time)
                                 WHEN t.start_time > :current_time THEN 
                                     TIMESTAMPDIFF(MINUTE, :current_time, t.start_time)
                                 ELSE 0
                             END as minutes_remaining
                      FROM timetables t
                      JOIN subjects s ON t.subject_id = s.id
                      JOIN users u ON t.teacher_id = u.id
                      WHERE t.class_id = :class_id 
                      AND (t.section_id = :section_id OR t.section_id IS NULL)
                      AND t.day_of_week = :day_of_week
                      ORDER BY t.start_time";
    
    $scheduleStmt = $db->prepare($scheduleQuery);
    $scheduleStmt->bindValue(':class_id', $enrollment['class_id']);
    $scheduleStmt->bindValue(':section_id', $enrollment['section_id']);
    $scheduleStmt->bindValue(':day_of_week', $dayOfWeek);
    $scheduleStmt->bindValue(':current_time', $currentTime);
    $scheduleStmt->execute();
    
    $schedule = $scheduleStmt->fetchAll();
    
    // Separate by status
    $todaySchedule = [
        'completed' => [],
        'ongoing' => [],
        'upcoming' => []
    ];
    
    $currentPeriod = null;
    $nextPeriod = null;
    
    foreach ($schedule as $period) {
        $todaySchedule[$period['status']][] = $period;
        
        if ($period['status'] === 'ongoing') {
            $currentPeriod = $period;
        } else if ($period['status'] === 'upcoming' && !$nextPeriod) {
            $nextPeriod = $period;
        }
    }
    
    // Get assignments due today
    $assignmentsQuery = "SELECT a.id, a.title, a.due_date, a.max_marks,
                                s.name as subject_name,
                                CONCAT(u.first_name, ' ', u.last_name) as teacher_name,
                                asub.id as submission_id,
                                asub.submitted_at,
                                CASE WHEN asub.id IS NOT NULL THEN 'submitted' ELSE 'pending' END as submission_status
                         FROM assignments a
                         JOIN subjects s ON a.subject_id = s.id
                         JOIN users u ON a.teacher_id = u.id
                         LEFT JOIN assignment_submissions asub ON a.id = asub.assignment_id AND asub.student_id = :student_id
                         WHERE a.class_id = :class_id
                         AND (a.section_id = :section_id OR a.section_id IS NULL)
                         AND DATE(a.due_date) = :date
                         ORDER BY a.due_date ASC";
    
    $assignmentsStmt = $db->prepare($assignmentsQuery);
    $assignmentsStmt->bindValue(':student_id', $studentId);
    $assignmentsStmt->bindValue(':class_id', $enrollment['class_id']);
    $assignmentsStmt->bindValue(':section_id', $enrollment['section_id']);
    $assignmentsStmt->bindValue(':date', $date);
    $assignmentsStmt->execute();
    
    $assignmentsDue = $assignmentsStmt->fetchAll();
    
    // Get attendance for today
    $attendanceQuery = "SELECT a.status, a.remarks, a.date,
                               CONCAT(u.first_name, ' ', u.last_name) as marked_by
                        FROM attendance a
                        JOIN users u ON a.teacher_id = u.id
                        WHERE a.student_id = :student_id AND a.date = :date";
    
    $attendanceStmt = $db->prepare($attendanceQuery);
    $attendanceStmt->bindValue(':student_id', $studentId);
    $attendanceStmt->bindValue(':date', $date);
    $attendanceStmt->execute();
    
    $todayAttendance = $attendanceStmt->fetch();
    
    Response::success([
        'student_info' => [
            'student_id' => $studentId,
            'student_code' => $enrollment['student_code'],
            'name' => $enrollment['first_name'] . ' ' . $enrollment['last_name'],
            'class_name' => $enrollment['class_name'],
            'section_name' => $enrollment['section_name'],
            'roll_number' => $enrollment['roll_number'],
            'grade_level' => $enrollment['grade_level']
        ],
        'schedule_info' => [
            'date' => $date,
            'day_of_week' => ucfirst($dayOfWeek),
            'current_time' => $currentTime,
            'total_periods' => count($schedule)
        ],
        'today_schedule' => $todaySchedule,
        'current_period' => $currentPeriod,
        'next_period' => $nextPeriod,
        'assignments_due_today' => $assignmentsDue,
        'today_attendance' => $todayAttendance,
        'summary' => [
            'completed_periods' => count($todaySchedule['completed']),
            'ongoing_periods' => count($todaySchedule['ongoing']),
            'upcoming_periods' => count($todaySchedule['upcoming']),
            'assignments_due' => count($assignmentsDue),
            'attendance_marked' => $todayAttendance ? true : false
        ]
    ], 'Student today schedule retrieved successfully');
    
} catch (Exception $e) {
    Response::error('Internal server error: ' . $e->getMessage(), 500);
}
?>
