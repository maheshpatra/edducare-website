<?php

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

require_once '../../middleware/auth.php';
require_once '../../config/database.php';
require_once '../../includes/response.php';

$auth = new AuthMiddleware();
$user = $auth->requireRole(['teacher_academic', 'teacher_administrative']);

if (!$user) {
    exit;
}

$database = new Database();
$db = $database->getConnection();

try {
    $teacherId = $user['id'];
    $date = date('Y-m-d');
    $dayOfWeek = strtolower(date('l', strtotime($date)));
    $currentTime = date('H:i:s');

    // 1. Get today's schedule from timetable
    $scheduleQuery = "SELECT t.*, 
                             s.name as subject_name, s.code as subject_code,
                             c.name as class_name, c.grade_level,
                             sec.name as section_name,
                             CASE 
                                 WHEN t.end_time < :current_time THEN 'completed'
                                 WHEN t.start_time <= :current_time AND t.end_time >= :current_time THEN 'ongoing'
                                 ELSE 'upcoming'
                             END as period_status
                      FROM timetables t
                      JOIN subjects s ON t.subject_id = s.id
                      JOIN classes c ON t.class_id = c.id
                      LEFT JOIN sections sec ON t.section_id = sec.id
                      WHERE t.teacher_id = :teacher_id 
                      AND t.day_of_week = :day_of_week
                      ORDER BY t.start_time";

    $scheduleStmt = $db->prepare($scheduleQuery);
    $scheduleStmt->bindValue(':teacher_id', $teacherId);
    $scheduleStmt->bindValue(':day_of_week', $dayOfWeek);
    $scheduleStmt->bindValue(':current_time', $currentTime);
    $scheduleStmt->execute();
    $schedule = $scheduleStmt->fetchAll(PDO::FETCH_ASSOC);

    // 2. Get attendance list - if teacher can mark for anyone, show all sections in school
    // but prioritize their own.
    $attendanceClassesQuery = "SELECT DISTINCT 
                                    c.id as class_id, c.name as class_name,
                                    sec.id as section_id, sec.name as section_name,
                                    'Manual/Full Day' as subject_name,
                                    '00:00:00' as start_time, '23:59:59' as end_time,
                                    CASE 
                                        WHEN sec.teacher_id = :t1 OR c.class_teacher_id = :t2 THEN 1
                                        ELSE 0
                                    END as is_assigned
                               FROM sections sec
                               JOIN classes c ON sec.class_id = c.id
                               WHERE c.school_id = :school_id
                               ORDER BY is_assigned DESC, c.grade_level ASC, c.name ASC, sec.name ASC";
    
    $attendanceClassesStmt = $db->prepare($attendanceClassesQuery);
    $attendanceClassesStmt->bindValue(':t1', $teacherId);
    $attendanceClassesStmt->bindValue(':t2', $teacherId);
    $attendanceClassesStmt->bindValue(':school_id', $user['school_id']);
    $attendanceClassesStmt->execute();
    $allSections = $attendanceClassesStmt->fetchAll(PDO::FETCH_ASSOC);

    // 3. Total stats
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

    // 4. Attendance percentage for the last 30 days
    $attendanceQuery = "SELECT 
                            COUNT(*) as total_records,
                            SUM(CASE WHEN status = 'present' THEN 1 ELSE 0 END) as present_count
                        FROM attendance
                        WHERE teacher_id = :teacher_id
                        AND date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)";
    $attendanceStmt = $db->prepare($attendanceQuery);
    $attendanceStmt->bindValue(':teacher_id', $teacherId);
    $attendanceStmt->execute();
    $attendanceData = $attendanceStmt->fetch(PDO::FETCH_ASSOC);
    
    $attendancePercentage = 0;
    if ($attendanceData && $attendanceData['total_records'] > 0) {
        $attendancePercentage = round(($attendanceData['present_count'] / $attendanceData['total_records']) * 100);
    }

    Response::success([
        'today_schedule' => $schedule,
        'attendance_list' => $allSections,
        'stats' => [
            'total_classes' => $totalClassesCount,
            'classes_today' => count($schedule),
            'attendance_percentage' => $attendancePercentage
        ],
        'date' => $date,
        'day_of_week' => $dayOfWeek
    ]);

} catch (Exception $e) {
    Response::error('Internal server error: ' . $e->getMessage(), 500);
}
