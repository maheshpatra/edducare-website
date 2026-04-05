<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

require_once '../../middleware/auth.php';
require_once '../../config/database.php';
require_once '../../includes/response.php';

$auth = new AuthMiddleware();
$user = $auth->requireRole(['super_admin', 'admin', 'school_admin', 'teacher_academic', 'teacher_administrative', 'accountant', 'librarian']);

if (!$user) {
    exit;
}

$database = new Database();
$db = $database->getConnection();

try {
    $teacherId = $_GET['teacher_id'] ?? $user['id'];
    $date = $_GET['date'] ?? date('Y-m-d');
    $dayOfWeek = strtolower(date('l', strtotime($date)));
    $currentTime = date('H:i:s');

    // Basic role check if teacher_id is provided and it's not the user
    if (in_array($user['role_name'], ['teacher_academic', 'teacher_administrative', 'accountant', 'librarian']) && $user['id'] != $teacherId) {
        Response::error('Access denied to other teachers schedules', 403);
    }

    // Get teacher info
    $teacherQuery = "SELECT u.id, u.employee_id, u.first_name, u.last_name, u.email, u.phone,
                            ur.name as role_name, s.name as school_name
                     FROM users u
                     JOIN user_roles ur ON u.role_id = ur.id
                     JOIN schools s ON u.school_id = s.id
                     WHERE u.id = :teacher_id AND u.role_id IN (3, 4, 5, 6)";

    $teacherStmt = $db->prepare($teacherQuery);
    $teacherStmt->bindValue(':teacher_id', $teacherId);
    $teacherStmt->execute();

    $teacher = $teacherStmt->fetch();

    if (!$teacher) {
        Response::error('Teacher not found', 404);
    }

    // Get today's schedule
    $scheduleQuery = "SELECT t.*, 
                             s.name as subject_name, s.code as subject_code,
                             c.name as class_name, c.grade_level,
                             sec.name as section_name,
                             COUNT(DISTINCT se.student_id) as student_count,
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
                      JOIN classes c ON t.class_id = c.id
                      LEFT JOIN sections sec ON t.section_id = sec.id
                      LEFT JOIN student_enrollments se ON c.id = se.class_id 
                          AND (t.section_id IS NULL OR se.section_id = t.section_id) 
                          AND se.status = 'active'
                      WHERE t.teacher_id = :teacher_id 
                      AND t.day_of_week = :day_of_week
                      GROUP BY t.id
                      ORDER BY t.start_time";

    $scheduleStmt = $db->prepare($scheduleQuery);
    $scheduleStmt->bindValue(':teacher_id', $teacherId);
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
        }
        else if ($period['status'] === 'upcoming' && !$nextPeriod) {
            $nextPeriod = $period;
        }
    }

    // 2. Get attendance list with status
    $attendanceClassesQuery = "SELECT DISTINCT 
                                    c.id as class_id, c.name as class_name,
                                    sec.id as section_id, sec.name as section_name,
                                    'Manual/Full Day' as subject_name,
                                    '00:00:00' as start_time, '23:59:59' as end_time,
                                    CASE 
                                        WHEN sec.teacher_id = :t1 OR c.class_teacher_id = :t2 THEN 1
                                        ELSE 0
                                    END as is_assigned,
                                    (SELECT COUNT(*) FROM attendance att 
                                     WHERE att.class_id = c.id 
                                       AND att.section_id = sec.id 
                                       AND att.date = :current_date) as attendance_marked_count
                               FROM sections sec
                               JOIN classes c ON sec.class_id = c.id
                               WHERE c.school_id = :school_id
                               ORDER BY is_assigned DESC, c.grade_level ASC, c.name ASC, sec.name ASC";
    
    $attendanceClassesStmt = $db->prepare($attendanceClassesQuery);
    $attendanceClassesStmt->bindValue(':t1', $teacherId);
    $attendanceClassesStmt->bindValue(':t2', $teacherId);
    $attendanceClassesStmt->bindValue(':current_date', $date);
    $attendanceClassesStmt->bindValue(':school_id', $user['school_id']);
    $attendanceClassesStmt->execute();
    $allSections = $attendanceClassesStmt->fetchAll(PDO::FETCH_ASSOC);

    // Calculate how many were marked
    $attendanceCompletedCount = 0;
    foreach ($allSections as &$section) {
        $section['attendance_status'] = $section['attendance_marked_count'] > 0 ? 'marked' : 'pending';
        if ($section['attendance_status'] === 'marked') {
            $attendanceCompletedCount++;
        }
    }

    // 3. Stats logic
    $totalClassesQuery = "SELECT COUNT(DISTINCT CONCAT(class_id, '-', IFNULL(section_id, '0'))) as total
                          FROM (
                            SELECT class_id, section_id FROM timetables WHERE teacher_id = :t1
                            UNION
                            SELECT class_id, id as section_id FROM sections WHERE teacher_id = :t2
                            UNION
                            SELECT class_id, NULL as section_id FROM class_subjects WHERE teacher_id = :t3
                          ) as combined";
    $totalClassesStmt = $db->prepare($totalClassesQuery);
    $totalClassesStmt->bindValue(':t1', $teacherId);
    $totalClassesStmt->bindValue(':t2', $teacherId);
    $totalClassesStmt->bindValue(':t3', $teacherId);
    $totalClassesStmt->execute();
    $totalRow = $totalClassesStmt->fetch(PDO::FETCH_ASSOC);
    $totalClassesCount = $totalRow ? (int)$totalRow['total'] : 0;

    Response::success([
        'teacher_info' => [
            'teacher_id' => $teacherId,
            'name' => $teacher['first_name'] . ' ' . $teacher['last_name'],
            'email' => $teacher['email'],
            'phone' => $teacher['phone'],
            'role' => $teacher['role_name'],
            'school_name' => $teacher['school_name']
        ],
        'schedule_info' => [
            'date' => $date,
            'day_of_week' => ucfirst($dayOfWeek),
            'current_time' => $currentTime
        ],
        'today_schedule' => $schedule,
        'attendance_list' => $allSections,
        'stats' => [
            'total_classes' => $totalClassesCount,
            'classes_today' => count($schedule),
            'attendance_completed' => $attendanceCompletedCount,
            'attendance_percentage' => $totalClassesCount > 0 ? round(($attendanceCompletedCount / $totalClassesCount) * 100) : 0
        ],
        'summary' => [
            'completed_periods' => count($todaySchedule['completed']),
            'ongoing_periods' => count($todaySchedule['ongoing']),
            'upcoming_periods' => count($todaySchedule['upcoming']),
            'classes_for_attendance' => count($allSections),
            'attendance_completed' => $attendanceCompletedCount
        ]
    ], 'Teacher today schedule retrieved successfully');

}
catch (Exception $e) {
    Response::error('Internal server error: ' . $e->getMessage(), 500);
}
?>
