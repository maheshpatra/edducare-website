<?php

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

require_once '../../middleware/auth.php';
require_once '../../config/database.php';
require_once '../../includes/response.php';
require_once '../../includes/validator.php';

$auth = new AuthMiddleware();
$user = $auth->requireRole(['super_admin', 'admin', 'teacher_academic', 'student']);

if (!$user) {
    exit;
}

$database = new Database();
$db = $database->getConnection();

try {
    switch ($_SERVER['REQUEST_METHOD']) {
        case 'GET':
            if (isset($_GET['action'])) {
                switch ($_GET['action']) {
                    case 'class_timetable':
                        handleGetClassTimetable($db, $user);
                        break;
                    case 'teacher_timetable':
                        handleGetTeacherTimetable($db, $user);
                        break;
                    case 'student_timetable':
                        handleGetStudentTimetable($db, $user);
                        break;
                    case 'today_schedule':
                        handleGetTodaySchedule($db, $user);
                        break;
                    case 'conflicts':
                        handleGetConflicts($db, $user);
                        break;
                    default:
                        handleGetTimetables($db, $user);
                }
            } else {
                handleGetTimetables($db, $user);
            }
            break;
        case 'POST':
            if (isset($_GET['action'])) {
                switch ($_GET['action']) {
                    case 'bulk_create':
                        handleBulkCreateTimetable($db, $user);
                        break;
                    case 'copy_timetable':
                        handleCopyTimetable($db, $user);
                        break;
                    default:
                        handleCreateTimetable($db, $user);
                }
            } else {
                handleCreateTimetable($db, $user);
            }
            break;
        case 'PUT':
            handleUpdateTimetable($db, $user);
            break;
        case 'DELETE':
            handleDeleteTimetable($db, $user);
            break;
        default:
            Response::error('Method not allowed', 405);
    }
} catch (Exception $e) {
    Response::error('Internal server error: ' . $e->getMessage(), 500);
}

function handleGetTimetables($db, $user)
{
    $schoolCondition = "";
    $params = [];

    if ($user['role_name'] !== 'super_admin') {
        $schoolCondition = "WHERE t.school_id = :school_id";
        $params[':school_id'] = $user['school_id'];
    } else if (isset($_GET['school_id'])) {
        $schoolCondition = "WHERE t.school_id = :school_id";
        $params[':school_id'] = $_GET['school_id'];
    }

    // Add filters
    $filters = [];

    if (isset($_GET['class_id'])) {
        $filters[] = "t.class_id = :class_id";
        $params[':class_id'] = $_GET['class_id'];
    }

    if (isset($_GET['section_id'])) {
        $filters[] = "t.section_id = :section_id";
        $params[':section_id'] = $_GET['section_id'];
    }

    if (isset($_GET['teacher_id'])) {
        $filters[] = "t.teacher_id = :teacher_id";
        $params[':teacher_id'] = $_GET['teacher_id'];
    }

    if (isset($_GET['day_of_week'])) {
        $filters[] = "t.day_of_week = :day_of_week";
        $params[':day_of_week'] = $_GET['day_of_week'];
    }

    if (isset($_GET['academic_year_id'])) {
        $filters[] = "t.academic_year_id = :academic_year_id";
        $params[':academic_year_id'] = $_GET['academic_year_id'];
    }

    if (!empty($filters)) {
        $schoolCondition .= ($schoolCondition ? " AND " : "WHERE ") . implode(' AND ', $filters);
    }

    // Pagination
    $page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
    $limit = isset($_GET['limit']) ? min(100, max(10, intval($_GET['limit']))) : 50;
    $offset = ($page - 1) * $limit;

    $query = "SELECT t.*, 
                     c.name as class_name, c.grade_level,
                     sec.name as section_name,
                     s.name as subject_name, s.code as subject_code,
                     CONCAT(u.first_name, ' ', u.last_name) as teacher_name,
                     u.employee_id,
                     ay.name as academic_year_name,
                     sch.name as school_name
              FROM timetables t
              JOIN classes c ON t.class_id = c.id
              LEFT JOIN sections sec ON t.section_id = sec.id
              JOIN subjects s ON t.subject_id = s.id
              JOIN users u ON t.teacher_id = u.id
              JOIN academic_years ay ON t.academic_year_id = ay.id
              JOIN schools sch ON t.school_id = sch.id
              $schoolCondition
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
                  t.start_time, c.grade_level, c.name
              LIMIT :limit OFFSET :offset";

    $stmt = $db->prepare($query);
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();

    $timetables = $stmt->fetchAll();

    // Get total count
    $countQuery = "SELECT COUNT(t.id) as total
                   FROM timetables t
                   JOIN classes c ON t.class_id = c.id
                   $schoolCondition";

    $countStmt = $db->prepare($countQuery);
    foreach ($params as $key => $value) {
        if ($key !== ':limit' && $key !== ':offset') {
            $countStmt->bindValue($key, $value);
        }
    }
    $countStmt->execute();

    $totalCount = $countStmt->fetch()['total'];

    Response::success([
        'timetables' => $timetables,
        'pagination' => [
            'current_page' => $page,
            'per_page' => $limit,
            'total' => $totalCount,
            'total_pages' => ceil($totalCount / $limit)
        ]
    ], 'Timetables retrieved successfully');
}

function handleGetClassTimetable($db, $user)
{
    $classId = $_GET['class_id'] ?? null;
    $sectionId = $_GET['section_id'] ?? null;

    if (!$classId) {
        Response::error('Class ID is required', 400);
    }

    $schoolCondition = "";
    $params = [':class_id' => $classId];

    if ($user['role_name'] !== 'super_admin') {
        $schoolCondition = "AND t.school_id = :school_id";
        $params[':school_id'] = $user['school_id'];
    }

    if ($sectionId) {
        $schoolCondition .= " AND t.section_id = :section_id";
        $params[':section_id'] = $sectionId;
    }

    $query = "SELECT t.*, 
                     s.name as subject_name, s.code as subject_code,
                     CONCAT(u.first_name, ' ', u.last_name) as teacher_name,
                     u.employee_id,
                     c.name as class_name, c.grade_level,
                     sec.name as section_name
              FROM timetables t
              JOIN subjects s ON t.subject_id = s.id
              JOIN users u ON t.teacher_id = u.id
              JOIN classes c ON t.class_id = c.id
              LEFT JOIN sections sec ON t.section_id = sec.id
              WHERE t.class_id = :class_id $schoolCondition
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
                  t.start_time, t.period_number";

    $stmt = $db->prepare($query);
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->execute();

    $timetables = $stmt->fetchAll();

    // Group by day of week
    $weeklySchedule = [
        'monday' => [],
        'tuesday' => [],
        'wednesday' => [],
        'thursday' => [],
        'friday' => [],
        'saturday' => [],
        'sunday' => []
    ];

    foreach ($timetables as $entry) {
        $weeklySchedule[$entry['day_of_week']][] = $entry;
    }

    Response::success([
        'class_timetable' => $weeklySchedule,
        'class_info' => [
            'class_id' => $classId,
            'section_id' => $sectionId,
            'class_name' => $timetables[0]['class_name'] ?? null,
            'section_name' => $timetables[0]['section_name'] ?? null
        ]
    ], 'Class timetable retrieved successfully');
}

function handleGetTeacherTimetable($db, $user)
{
    $teacherId = $_GET['teacher_id'] ?? null;

    if (!$teacherId) {
        Response::error('Teacher ID is required', 400);
    }

    $schoolCondition = "";
    $params = [':teacher_id' => $teacherId];

    if ($user['role_name'] !== 'super_admin') {
        $schoolCondition = "AND t.school_id = :school_id";
        $params[':school_id'] = $user['school_id'];
    }

    $query = "SELECT t.*, 
                     s.name as subject_name, s.code as subject_code,
                     c.name as class_name, c.grade_level,
                     sec.name as section_name,
                     CONCAT(u.first_name, ' ', u.last_name) as teacher_name,
                     u.employee_id
              FROM timetables t
              JOIN subjects s ON t.subject_id = s.id
              JOIN classes c ON t.class_id = c.id
              LEFT JOIN sections sec ON t.section_id = sec.id
              JOIN users u ON t.teacher_id = u.id
              WHERE t.teacher_id = :teacher_id $schoolCondition
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
                  t.start_time, t.period_number";

    $stmt = $db->prepare($query);
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->execute();

    $timetables = $stmt->fetchAll();

    // Group by day of week
    $weeklySchedule = [
        'monday' => [],
        'tuesday' => [],
        'wednesday' => [],
        'thursday' => [],
        'friday' => [],
        'saturday' => [],
        'sunday' => []
    ];

    foreach ($timetables as $entry) {
        $weeklySchedule[$entry['day_of_week']][] = $entry;
    }

    Response::success([
        'teacher_timetable' => $weeklySchedule,
        'teacher_info' => [
            'teacher_id' => $teacherId,
            'teacher_name' => $timetables[0]['teacher_name'] ?? null,
            'employee_id' => $timetables[0]['employee_id'] ?? null
        ]
    ], 'Teacher timetable retrieved successfully');
}

function handleGetStudentTimetable($db, $user)
{
    $studentId = $_GET['student_id'] ?? null;

    if (!$studentId) {
        Response::error('Student ID is required', 400);
    }

    // Get student's current enrollment
    $enrollmentQuery = "SELECT se.class_id, se.section_id, c.name as class_name, sec.name as section_name
                        FROM student_enrollments se
                        JOIN classes c ON se.class_id = c.id
                        LEFT JOIN sections sec ON se.section_id = sec.id
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

    $schoolCondition = "";
    $params = [
        ':class_id' => $enrollment['class_id'],
        ':section_id' => $enrollment['section_id']
    ];

    if ($user['role_name'] !== 'super_admin') {
        $schoolCondition = "AND t.school_id = :school_id";
        $params[':school_id'] = $user['school_id'];
    }

    $query = "SELECT t.*, 
                     s.name as subject_name, s.code as subject_code,
                     CONCAT(u.first_name, ' ', u.last_name) as teacher_name,
                     u.employee_id,
                     c.name as class_name, c.grade_level,
                     sec.name as section_name
              FROM timetables t
              JOIN subjects s ON t.subject_id = s.id
              JOIN users u ON t.teacher_id = u.id
              JOIN classes c ON t.class_id = c.id
              LEFT JOIN sections sec ON t.section_id = sec.id
              WHERE t.class_id = :class_id AND (t.section_id = :section_id OR t.section_id IS NULL) $schoolCondition
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
                  t.start_time, t.period_number";

    $stmt = $db->prepare($query);
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->execute();

    $timetables = $stmt->fetchAll();

    // Group by day of week
    $weeklySchedule = [
        'monday' => [],
        'tuesday' => [],
        'wednesday' => [],
        'thursday' => [],
        'friday' => [],
        'saturday' => [],
        'sunday' => []
    ];

    foreach ($timetables as $entry) {
        $weeklySchedule[$entry['day_of_week']][] = $entry;
    }

    Response::success([
        'student_timetable' => $weeklySchedule,
        'student_info' => [
            'student_id' => $studentId,
            'class_id' => $enrollment['class_id'],
            'section_id' => $enrollment['section_id'],
            'class_name' => $enrollment['class_name'],
            'section_name' => $enrollment['section_name']
        ]
    ], 'Student timetable retrieved successfully');
}

function handleGetTodaySchedule($db, $user)
{
    $userType = $_GET['user_type'] ?? null;
    $userId = $_GET['user_id'] ?? null;
    $date = $_GET['date'] ?? date('Y-m-d');
    $dayOfWeek = strtolower(date('l', strtotime($date)));

    if (!$userType || !$userId) {
        Response::error('User type and user ID are required', 400);
    }

    $currentTime = date('H:i:s');

    if ($userType === 'student') {
        // Get student's enrollment
        $enrollmentQuery = "SELECT se.class_id, se.section_id
                            FROM student_enrollments se
                            WHERE se.student_id = :user_id AND se.status = 'active'
                            ORDER BY se.enrollment_date DESC
                            LIMIT 1";

        $enrollmentStmt = $db->prepare($enrollmentQuery);
        $enrollmentStmt->bindValue(':user_id', $userId);
        $enrollmentStmt->execute();

        $enrollment = $enrollmentStmt->fetch();

        if (!$enrollment) {
            Response::error('Student enrollment not found', 404);
        }

        $scheduleQuery = "SELECT t.*, 
                                 s.name as subject_name, s.code as subject_code,
                                 CONCAT(u.first_name, ' ', u.last_name) as teacher_name,
                                 u.employee_id,
                                 CASE 
                                     WHEN t.end_time < :current_time THEN 'completed'
                                     WHEN t.start_time <= :current_time AND t.end_time >= :current_time THEN 'ongoing'
                                     ELSE 'upcoming'
                                 END as status
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

    } else if ($userType === 'teacher') {
        $scheduleQuery = "SELECT t.*, 
                                 s.name as subject_name, s.code as subject_code,
                                 c.name as class_name, c.grade_level,
                                 sec.name as section_name,
                                 CASE 
                                     WHEN t.end_time < :current_time THEN 'completed'
                                     WHEN t.start_time <= :current_time AND t.end_time >= :current_time THEN 'ongoing'
                                     ELSE 'upcoming'
                                 END as status
                          FROM timetables t
                          JOIN subjects s ON t.subject_id = s.id
                          JOIN classes c ON t.class_id = c.id
                          LEFT JOIN sections sec ON t.section_id = sec.id
                          WHERE t.teacher_id = :user_id 
                          AND t.day_of_week = :day_of_week
                          ORDER BY t.start_time";

        $scheduleStmt = $db->prepare($scheduleQuery);
        $scheduleStmt->bindValue(':user_id', $userId);
        $scheduleStmt->bindValue(':day_of_week', $dayOfWeek);
        $scheduleStmt->bindValue(':current_time', $currentTime);
        $scheduleStmt->execute();
    } else {
        Response::error('Invalid user type', 400);
    }

    $schedule = $scheduleStmt->fetchAll();

    // Separate by status
    $todaySchedule = [
        'completed' => [],
        'ongoing' => [],
        'upcoming' => []
    ];

    foreach ($schedule as $period) {
        $todaySchedule[$period['status']][] = $period;
    }

    Response::success([
        'today_schedule' => $todaySchedule,
        'date' => $date,
        'day_of_week' => $dayOfWeek,
        'current_time' => $currentTime,
        'user_type' => $userType,
        'user_id' => $userId
    ], 'Today\'s schedule retrieved successfully');
}

function handleGetConflicts($db, $user)
{
    $schoolCondition = "";
    $params = [];

    if ($user['role_name'] !== 'super_admin') {
        $schoolCondition = "WHERE t1.school_id = :school_id";
        $params[':school_id'] = $user['school_id'];
    } else if (isset($_GET['school_id'])) {
        $schoolCondition = "WHERE t1.school_id = :school_id";
        $params[':school_id'] = $_GET['school_id'];
    }

    // Teacher conflicts (same teacher, same time, different classes)
    $teacherConflictsQuery = "SELECT 
                                 t1.id as timetable1_id,
                                 t2.id as timetable2_id,
                                 t1.teacher_id,
                                 CONCAT(u.first_name, ' ', u.last_name) as teacher_name,
                                 t1.day_of_week,
                                 t1.start_time,
                                 t1.end_time,
                                 c1.name as class1_name,
                                 c2.name as class2_name,
                                 s1.name as subject1_name,
                                 s2.name as subject2_name,
                                 'teacher_conflict' as conflict_type
                              FROM timetables t1
                              JOIN timetables t2 ON t1.teacher_id = t2.teacher_id 
                                  AND t1.day_of_week = t2.day_of_week
                                  AND t1.id != t2.id
                                  AND ((t1.start_time <= t2.start_time AND t1.end_time > t2.start_time)
                                       OR (t1.start_time < t2.end_time AND t1.end_time >= t2.end_time)
                                       OR (t1.start_time >= t2.start_time AND t1.end_time <= t2.end_time))
                              JOIN users u ON t1.teacher_id = u.id
                              JOIN classes c1 ON t1.class_id = c1.id
                              JOIN classes c2 ON t2.class_id = c2.id
                              JOIN subjects s1 ON t1.subject_id = s1.id
                              JOIN subjects s2 ON t2.subject_id = s2.id
                              $schoolCondition";

    $teacherConflictsStmt = $db->prepare($teacherConflictsQuery);
    foreach ($params as $key => $value) {
        $teacherConflictsStmt->bindValue($key, $value);
    }
    $teacherConflictsStmt->execute();

    $teacherConflicts = $teacherConflictsStmt->fetchAll();

    // Class conflicts (same class/section, same time, different teachers)
    $classConflictsQuery = "SELECT 
                               t1.id as timetable1_id,
                               t2.id as timetable2_id,
                               t1.class_id,
                               t1.section_id,
                               c.name as class_name,
                               sec.name as section_name,
                               t1.day_of_week,
                               t1.start_time,
                               t1.end_time,
                               CONCAT(u1.first_name, ' ', u1.last_name) as teacher1_name,
                               CONCAT(u2.first_name, ' ', u2.last_name) as teacher2_name,
                               s1.name as subject1_name,
                               s2.name as subject2_name,
                               'class_conflict' as conflict_type
                            FROM timetables t1
                            JOIN timetables t2 ON t1.class_id = t2.class_id 
                                AND (t1.section_id = t2.section_id OR (t1.section_id IS NULL AND t2.section_id IS NULL))
                                AND t1.day_of_week = t2.day_of_week
                                AND t1.id != t2.id
                                AND ((t1.start_time <= t2.start_time AND t1.end_time > t2.start_time)
                                     OR (t1.start_time < t2.end_time AND t1.end_time >= t2.end_time)
                                     OR (t1.start_time >= t2.start_time AND t1.end_time <= t2.end_time))
                            JOIN classes c ON t1.class_id = c.id
                            LEFT JOIN sections sec ON t1.section_id = sec.id
                            JOIN users u1 ON t1.teacher_id = u1.id
                            JOIN users u2 ON t2.teacher_id = u2.id
                            JOIN subjects s1 ON t1.subject_id = s1.id
                            JOIN subjects s2 ON t2.subject_id = s2.id
                            $schoolCondition";

    $classConflictsStmt = $db->prepare($classConflictsQuery);
    foreach ($params as $key => $value) {
        $classConflictsStmt->bindValue($key, $value);
    }
    $classConflictsStmt->execute();

    $classConflicts = $classConflictsStmt->fetchAll();

    Response::success([
        'teacher_conflicts' => $teacherConflicts,
        'class_conflicts' => $classConflicts,
        'total_conflicts' => count($teacherConflicts) + count($classConflicts)
    ], 'Conflicts retrieved successfully');
}

function handleCreateTimetable($db, $user)
{
    $input = json_decode(file_get_contents('php://input'), true);

    // Validate required fields
    $required = ['class_id', 'subject_id', 'teacher_id', 'day_of_week', 'start_time', 'end_time'];
    foreach ($required as $field) {
        if (!isset($input[$field]) || empty($input[$field])) {
            Response::error("Field '$field' is required", 400);
        }
    }

    $schoolId = $user['role_name'] === 'super_admin' ? ($input['school_id'] ?? null) : $user['school_id'];

    if (!$schoolId) {
        Response::error('School ID is required', 400);
    }

    // Validate day of week
    $validDays = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];
    if (!in_array(strtolower($input['day_of_week']), $validDays)) {
        Response::error('Invalid day of week', 400);
    }

    // Validate time format
    if (
        !preg_match('/^([01]?[0-9]|2[0-3]):[0-5][0-9]:[0-5][0-9]$/', $input['start_time']) ||
        !preg_match('/^([01]?[0-9]|2[0-3]):[0-5][0-9]:[0-5][0-9]$/', $input['end_time'])
    ) {
        Response::error('Invalid time format. Use HH:MM:SS', 400);
    }

    if ($input['start_time'] >= $input['end_time']) {
        Response::error('Start time must be before end time', 400);
    }

    // Get academic year
    $academicYearQuery = "SELECT id FROM academic_years WHERE school_id = :school_id AND is_current = 1";
    $academicYearStmt = $db->prepare($academicYearQuery);
    $academicYearStmt->bindValue(':school_id', $schoolId);
    $academicYearStmt->execute();
    $academicYear = $academicYearStmt->fetch();

    if (!$academicYear) {
        Response::error('No current academic year found', 400);
    }

    // Check for conflicts
    $conflicts = checkTimetableConflicts($db, $input, $schoolId);
    if (!empty($conflicts)) {
        Response::error('Timetable conflicts detected: ' . implode(', ', $conflicts), 400);
    }

    $db->beginTransaction();

    try {
        $insertQuery = "INSERT INTO timetables (
            school_id, class_id, section_id, subject_id, teacher_id, academic_year_id,
            day_of_week, period_number, start_time, end_time, room
        ) VALUES (
            :school_id, :class_id, :section_id, :subject_id, :teacher_id, :academic_year_id,
            :day_of_week, :period_number, :start_time, :end_time, :room
        )";

        $insertStmt = $db->prepare($insertQuery);
        $insertStmt->bindValue(':school_id', $schoolId);
        $insertStmt->bindValue(':class_id', $input['class_id']);
        $insertStmt->bindValue(':section_id', $input['section_id']);
        $insertStmt->bindValue(':subject_id', $input['subject_id']);
        $insertStmt->bindValue(':teacher_id', $input['teacher_id']);
        $insertStmt->bindValue(':academic_year_id', $academicYear['id']);
        $insertStmt->bindValue(':day_of_week', strtolower($input['day_of_week']));
        $insertStmt->bindValue(':period_number', $input['period_number']);
        $insertStmt->bindValue(':start_time', $input['start_time']);
        $insertStmt->bindValue(':end_time', $input['end_time']);
        $insertStmt->bindValue(':room', $input['room']);

        $insertStmt->execute();
        $timetableId = $db->lastInsertId();

        // Log activity
        $logQuery = "INSERT INTO activity_logs (user_id, user_type, school_id, action, entity_type, entity_id, ip_address) 
                     VALUES (:user_id, 'user', :school_id, 'create_timetable', 'timetable', :entity_id, :ip_address)";
        $logStmt = $db->prepare($logQuery);
        $logStmt->bindValue(':user_id', $user['id']);
        $logStmt->bindValue(':school_id', $schoolId);
        $logStmt->bindValue(':entity_id', $timetableId);
        $logStmt->bindValue(':ip_address', $_SERVER['REMOTE_ADDR']);
        $logStmt->execute();

        $db->commit();

        Response::success([
            'timetable_id' => $timetableId
        ], 'Timetable entry created successfully');

    } catch (Exception $e) {
        $db->rollBack();
        http_response_code(506);
        echo json_encode(['error' => $e->getMessage()]);
        exit;
    }
}

function handleBulkCreateTimetable($db, $user)
{
    $input = json_decode(file_get_contents('php://input'), true);

    if (!isset($input['entries']) || !is_array($input['entries'])) {
        Response::error('Entries array is required', 400);
    }

    $schoolId = $user['role_name'] === 'super_admin' ? ($input['school_id'] ?? null) : $user['school_id'];

    if (!$schoolId) {
        Response::error('School ID is required', 400);
    }

    // Get academic year
    $academicYearQuery = "SELECT id FROM academic_years WHERE school_id = :school_id AND is_current = 1";
    $academicYearStmt = $db->prepare($academicYearQuery);
    $academicYearStmt->bindValue(':school_id', $schoolId);
    $academicYearStmt->execute();
    $academicYear = $academicYearStmt->fetch();

    if (!$academicYear) {
        Response::error('No current academic year found', 400);
    }

    $db->beginTransaction();

    try {
        $successCount = 0;
        $errors = [];

        foreach ($input['entries'] as $index => $entry) {
            // Validate required fields
            $required = ['class_id', 'subject_id', 'teacher_id', 'day_of_week', 'start_time', 'end_time'];
            $hasError = false;

            foreach ($required as $field) {
                if (!isset($entry[$field]) || empty($entry[$field])) {
                    $errors[] = "Entry $index: Field '$field' is required";
                    $hasError = true;
                }
            }

            if ($hasError) {
                continue;
            }

            // Check for conflicts
            $conflicts = checkTimetableConflicts($db, $entry, $schoolId);
            if (!empty($conflicts)) {
                $errors[] = "Entry $index: Conflicts - " . implode(', ', $conflicts);
                continue;
            }

            $insertQuery = "INSERT INTO timetables (
                school_id, class_id, section_id, subject_id, teacher_id, academic_year_id,
                day_of_week, period_number, start_time, end_time, room
            ) VALUES (
                :school_id, :class_id, :section_id, :subject_id, :teacher_id, :academic_year_id,
                :day_of_week, :period_number, :start_time, :end_time, :room
            )";

            $classId = $input['class_id'];
            $sectionId = $input['section_id'];
            $subjectId = $input['subject_id'];
            $teacherId = $input['teacher_id'];
            $dayOfWeek = strtolower($input['day_of_week']);
            $periodNumber = $input['period_number'];
            $startTime = $input['start_time'];
            $endTime = $input['end_time'];
            $room = $input['room'] ?? null;

            $insertStmt = $db->prepare($insertQuery);
            $insertStmt->bindValue(':school_id', $schoolId);
            $insertStmt->bindValue(':class_id', $classId);
            $insertStmt->bindValue(':section_id', $sectionId);
            $insertStmt->bindValue(':subject_id', $subjectId);
            $insertStmt->bindValue(':teacher_id', $teacherId);
            $insertStmt->bindValue(':academic_year_id', $academicYear['id']);
            $insertStmt->bindValue(':day_of_week', $dayOfWeek);
            $insertStmt->bindValue(':period_number', $periodNumber);
            $insertStmt->bindValue(':start_time', $startTime);
            $insertStmt->bindValue(':end_time', $endTime);
            $insertStmt->bindValue(':room', $room);

            $insertStmt->execute();
            $successCount++;
        }

        // Log activity
        $logQuery = "INSERT INTO activity_logs (user_id, user_type, school_id, action, entity_type, entity_id, ip_address) 
                     VALUES (:user_id, 'user', :school_id, 'bulk_create_timetable', 'timetable', :entity_id, :ip_address)";
        $logStmt = $db->prepare($logQuery);
        $logStmt->bindValue(':user_id', $user['id']);
        $logStmt->bindValue(':school_id', $schoolId);
        $logStmt->bindValue(':entity_id', $successCount);
        $logStmt->bindValue(':ip_address', $_SERVER['REMOTE_ADDR'] ?? null);
        $logStmt->execute();

        $db->commit();

        Response::success([
            'success_count' => $successCount,
            'total_entries' => count($input['entries']),
            'errors' => $errors
        ], "Bulk timetable creation completed. $successCount entries created successfully");

    } catch (Exception $e) {
        $db->rollBack();
        throw $e;
    }
}

function handleUpdateTimetable($db, $user)
{
    $input = json_decode(file_get_contents('php://input'), true);
    $timetableId = $_GET['timetable_id'] ?? $input['timetable_id'] ?? null;

    if (!$timetableId) {
        Response::error('Timetable ID is required', 400);
    }

    // Validate timetable belongs to user's school
    $schoolCondition = "";
    $params = [':timetable_id' => $timetableId];

    if ($user['role_name'] !== 'super_admin') {
        $schoolCondition = "AND school_id = :school_id";
        $params[':school_id'] = $user['school_id'];
    }

    $timetableQuery = "SELECT * FROM timetables WHERE id = :timetable_id $schoolCondition";
    $timetableStmt = $db->prepare($timetableQuery);
    foreach ($params as $key => $value) {
        $timetableStmt->bindValue($key, $value);
    }
    $timetableStmt->execute();

    $existingTimetable = $timetableStmt->fetch();
    if (!$existingTimetable) {
        Response::error('Timetable entry not found or access denied', 404);
    }

    // Build update query dynamically
    $updateFields = [];
    $updateParams = [':timetable_id' => $timetableId];

    $allowedFields = [
        'class_id',
        'section_id',
        'subject_id',
        'teacher_id',
        'day_of_week',
        'period_number',
        'start_time',
        'end_time',
        'room'
    ];

    foreach ($allowedFields as $field) {
        if (isset($input[$field])) {
            // Validate day of week
            if ($field === 'day_of_week') {
                $validDays = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];
                if (!in_array(strtolower($input[$field]), $validDays)) {
                    Response::error('Invalid day of week', 400);
                }
                $input[$field] = strtolower($input[$field]);
            }

            // Validate time format
            if (in_array($field, ['start_time', 'end_time'])) {
                if (!preg_match('/^([01]?[0-9]|2[0-3]):[0-5][0-9]:[0-5][0-9]$/', $input[$field])) {
                    Response::error("Invalid $field format. Use HH:MM:SS", 400);
                }
            }

            $updateFields[] = "$field = :$field";
            $updateParams[":$field"] = $input[$field];
        }
    }

    if (empty($updateFields)) {
        Response::error('No valid fields to update', 400);
    }

    // Check for conflicts with updated data
    $updatedEntry = array_merge($existingTimetable, $input);
    if (isset($input['start_time']) && isset($input['end_time']) && $input['start_time'] >= $input['end_time']) {
        Response::error('Start time must be before end time', 400);
    }

    $conflicts = checkTimetableConflicts($db, $updatedEntry, $existingTimetable['school_id'], $timetableId);
    if (!empty($conflicts)) {
        Response::error('Timetable conflicts detected: ' . implode(', ', $conflicts), 400);
    }

    $updateQuery = "UPDATE timetables SET " . implode(', ', $updateFields) . " WHERE id = :timetable_id";

    $updateStmt = $db->prepare($updateQuery);
    foreach ($updateParams as $key => $value) {
        $updateStmt->bindValue($key, $value);
    }

    $updateStmt->execute();

    // Log activity
    $logQuery = "INSERT INTO activity_logs (user_id, user_type, school_id, action, entity_type, entity_id, ip_address) 
                 VALUES (:user_id, 'user', :school_id, 'update_timetable', 'timetable', :entity_id, :ip_address)";

    $userId = $user['id'];
    $entityId = $timetableId;
    $ip = $_SERVER['REMOTE_ADDR'] ?? null;
    $schoolid = $existingTimetable['school_id'];
    $logStmt = $db->prepare($logQuery);
    $logStmt->bindValue(':user_id', $userId);
    $logStmt->bindValue(':school_id', $schoolid);
    $logStmt->bindValue(':entity_id', $entityId);
    $logStmt->bindValue(':ip_address', $ip);
    $logStmt->execute();

    Response::success(['timetable_id' => $timetableId], 'Timetable entry updated successfully');
}

function handleDeleteTimetable($db, $user)
{
    $timetableId = $_GET['timetable_id'] ?? null;

    if (!$timetableId) {
        Response::error('Timetable ID is required', 400);
    }

    // Validate timetable belongs to user's school
    $schoolCondition = "";
    $params = [':timetable_id' => $timetableId];

    if ($user['role_name'] !== 'super_admin') {
        $schoolCondition = "AND school_id = :school_id";
        $params[':school_id'] = $user['school_id'];
    }

    $timetableQuery = "SELECT id, school_id FROM timetables WHERE id = :timetable_id $schoolCondition";
    $timetableStmt = $db->prepare($timetableQuery);
    foreach ($params as $key => $value) {
        $timetableStmt->bindValue($key, $value);
    }
    $timetableStmt->execute();

    $timetable = $timetableStmt->fetch();
    if (!$timetable) {
        Response::error('Timetable entry not found or access denied', 404);
    }

    $deleteQuery = "DELETE FROM timetables WHERE id = :timetable_id";
    $deleteStmt = $db->prepare($deleteQuery);
    $deleteStmt->bindValue(':timetable_id', $timetableId);
    $deleteStmt->execute();

    // Log activity
    $logQuery = "INSERT INTO activity_logs (user_id, user_type, school_id, action, entity_type, entity_id, ip_address) 
                 VALUES (:user_id, 'user', :school_id, 'delete_timetable', 'timetable', :entity_id, :ip_address)";
    $logStmt = $db->prepare($logQuery);
    $logStmt->bindValue(':user_id', $user['id']);
    $logStmt->bindValue(':school_id', $timetable['school_id']);
    $logStmt->bindValue(':entity_id', $timetableId);
    $logStmt->bindValue(':ip_address', $_SERVER['REMOTE_ADDR'] ?? null);
    $logStmt->execute();

    Response::success(null, 'Timetable entry deleted successfully');
}

function checkTimetableConflicts($db, $entry, $schoolId, $excludeId = null)
{
    $conflicts = [];

    $excludeCondition = $excludeId ? "AND t.id != :exclude_id" : "";

    // Check teacher conflicts
    $teacherConflictQuery = "SELECT COUNT(*) as conflict_count
                            FROM timetables t
                            WHERE t.school_id = :school_id
                            AND t.teacher_id = :teacher_id
                            AND t.day_of_week = :day_of_week
                            AND ((t.start_time <= :start_time AND t.end_time > :start_time)
                                 OR (t.start_time < :end_time AND t.end_time >= :end_time)
                                 OR (t.start_time >= :start_time AND t.end_time <= :end_time))
                            $excludeCondition";

    $teacherConflictStmt = $db->prepare($teacherConflictQuery);
    $teacherConflictStmt->bindValue(':school_id', $schoolId);
    $teacherConflictStmt->bindValue(':teacher_id', $entry['teacher_id']);
    $dayOfWeek = strtolower($entry['day_of_week']);
    $teacherConflictStmt->bindValue(':day_of_week', $dayOfWeek);
    $teacherConflictStmt->bindValue(':start_time', $entry['start_time']);
    $teacherConflictStmt->bindValue(':end_time', $entry['end_time']);
    if ($excludeId) {
        $teacherConflictStmt->bindValue(':exclude_id', $excludeId);
    }
    $teacherConflictStmt->execute();

    if ($teacherConflictStmt->fetch()['conflict_count'] > 0) {
        $conflicts[] = 'Teacher has another class at this time';
    }

    // Check class conflicts
    $classConflictQuery = "SELECT COUNT(*) as conflict_count
                          FROM timetables t
                          WHERE t.school_id = :school_id
                          AND t.class_id = :class_id
                          AND (t.section_id = :section_id OR (t.section_id IS NULL AND :section_id IS NULL))
                          AND t.day_of_week = :day_of_week
                          AND ((t.start_time <= :start_time AND t.end_time > :start_time)
                               OR (t.start_time < :end_time AND t.end_time >= :end_time)
                               OR (t.start_time >= :start_time AND t.end_time <= :end_time))
                          $excludeCondition";

    $classConflictStmt = $db->prepare($classConflictQuery);
    $classId = $entry['class_id'];
    $sectionId = $entry['section_id'];
    $dayOfWeek = strtolower($entry['day_of_week']);
    $startTime = $entry['start_time'];
    $endTime = $entry['end_time'];

    $classConflictStmt = $db->prepare($classConflictQuery);
    $classConflictStmt->bindValue(':school_id', $schoolId);
    $classConflictStmt->bindValue(':class_id', $classId);
    $classConflictStmt->bindValue(':section_id', $sectionId);
    $classConflictStmt->bindValue(':day_of_week', $dayOfWeek);
    $classConflictStmt->bindValue(':start_time', $startTime);
    $classConflictStmt->bindValue(':end_time', $endTime);
    if ($excludeId) {
        $classConflictStmt->bindValue(':exclude_id', $excludeId);
    }
    $classConflictStmt->execute();

    if ($classConflictStmt->fetch()['conflict_count'] > 0) {
        $conflicts[] = 'Class already has another subject at this time';
    }

    return $conflicts;
}
?>