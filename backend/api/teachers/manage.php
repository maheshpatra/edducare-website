<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
require_once '../../middleware/auth.php';
require_once '../../config/database.php';
require_once '../../config/email.php';
require_once '../../includes/response.php';
require_once '../../includes/validator.php';

$auth = new AuthMiddleware();
$user = $auth->requireRole(['super_admin', 'admin']);

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
                    case 'list':
                        handleGetTeachers($db, $user);
                        break;
                    case 'details':
                        handleGetTeacherDetails($db, $user);
                        break;
                    case 'workload':
                        handleGetTeacherWorkload($db, $user);
                        break;
                    case 'performance':
                        handleGetTeacherPerformance($db, $user);
                        break;
                    case 'analytics':
                        handleGetTeacherAnalytics($db, $user);
                        break;
                    default:
                        handleGetTeachers($db, $user);
                }
            } else {
                handleGetTeachers($db, $user);
            }
            break;
        case 'POST':
            handleCreateTeacher($db, $user);
            break;
        case 'PUT':
            handleUpdateTeacher($db, $user);
            break;
        case 'DELETE':
            handleDeleteTeacher($db, $user);
            break;
        default:
            Response::error('Method not allowed', 405);
    }
} catch (Exception $e) {
    Response::error('Internal server error: ' . $e->getMessage(), 500);
}

function handleGetTeachers($db, $user)
{
    $schoolCondition = "";
    $params = [];

    if ($user['role_name'] !== 'super_admin') {
        $schoolCondition = "WHERE u.school_id = :school_id";
        $params[':school_id'] = $user['school_id'];
    } else if (isset($_GET['school_id'])) {
        $schoolCondition = "WHERE u.school_id = :school_id";
        $params[':school_id'] = $_GET['school_id'];
    }

    // Add role filter for teachers only
    $roleCondition = $schoolCondition ? " AND u.role_id IN (3, 4)" : "WHERE u.role_id IN (3, 4)";
    $schoolCondition .= $roleCondition;

    // Add additional filters
    $filters = [];

    if (isset($_GET['teacher_type'])) {
        $filters[] = "u.teacher_type = :teacher_type";
        $params[':teacher_type'] = $_GET['teacher_type'];
    }

    if (isset($_GET['is_active'])) {
        $filters[] = "u.is_active = :is_active";
        $params[':is_active'] = $_GET['is_active'];
    }

    if (isset($_GET['role_id'])) {
        $filters[] = "u.role_id = :role_id";
        $params[':role_id'] = $_GET['role_id'];
    }

    if (isset($_GET['search'])) {
        $filters[] = "(u.first_name LIKE :search OR u.last_name LIKE :search OR u.email LIKE :search OR u.employee_id LIKE :search)";
        $params[':search'] = '%' . $_GET['search'] . '%';
    }

    if (!empty($filters)) {
        $schoolCondition .= " AND " . implode(' AND ', $filters);
    }

    // Pagination
    $page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
    $limit = isset($_GET['limit']) ? min(100, max(10, intval($_GET['limit']))) : 20;
    $offset = ($page - 1) * $limit;

    $query = "SELECT u.*, 
                     CONCAT(u.first_name, ' ', u.last_name) as full_name,
                     r.name as role_name,
                     s.name as school_name, s.code as school_code,
                     COUNT(DISTINCT cs.id) as subject_assignments,
                     COUNT(DISTINCT c.id) as class_teacher_assignments,
                     COUNT(DISTINCT t.id) as timetable_periods,
                     -- Recent activity count
                     (SELECT COUNT(*) FROM activity_logs al 
                      WHERE al.user_id = u.id 
                      AND al.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)) as recent_activity_count
              FROM users u
              JOIN user_roles r ON u.role_id = r.id
              LEFT JOIN schools s ON u.school_id = s.id
              LEFT JOIN class_subjects cs ON u.id = cs.teacher_id
              LEFT JOIN classes c ON u.id = c.class_teacher_id
              LEFT JOIN timetables t ON u.id = t.teacher_id
              $schoolCondition
              GROUP BY u.id
              ORDER BY u.first_name ASC, u.last_name ASC
              LIMIT :limit OFFSET :offset";

    $stmt = $db->prepare($query);
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();

    $teachers = $stmt->fetchAll();

    // Get total count
    $countQuery = "SELECT COUNT(u.id) as total
                   FROM users u
                   JOIN user_roles r ON u.role_id = r.id
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
        'teachers' => $teachers,
        'pagination' => [
            'current_page' => $page,
            'per_page' => $limit,
            'total' => $totalCount,
            'total_pages' => ceil($totalCount / $limit)
        ]
    ], 'Teachers retrieved successfully');
}

function handleGetTeacherDetails($db, $user)
{
    $teacherId = $_GET['teacher_id'] ?? null;

    if (!$teacherId) {
        Response::error('Teacher ID is required', 400);
    }

    $schoolCondition = "";
    $params = [':teacher_id' => $teacherId];

    if ($user['role_name'] !== 'super_admin') {
        $schoolCondition = "AND u.school_id = :school_id";
        $params[':school_id'] = $user['school_id'];
    }

    // Get teacher details
    $query = "SELECT u.*, 
                     CONCAT(u.first_name, ' ', u.last_name) as full_name,
                     r.name as role_name,
                     s.name as school_name, s.code as school_code, s.address as school_address
              FROM users u
              JOIN user_roles r ON u.role_id = r.id
              JOIN schools s ON u.school_id = s.id
              WHERE u.id = :teacher_id AND u.role_id IN (3, 4) $schoolCondition";

    $stmt = $db->prepare($query);
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->execute();

    $teacher = $stmt->fetch();

    if (!$teacher) {
        Response::error('Teacher not found', 404);
    }

    // Get subject assignments
    $subjectsQuery = "SELECT cs.id as assignment_id, cs.class_id, cs.subject_id,
                             s.name as subject_name, s.code as subject_code,
                             c.name as class_name, c.grade_level,
                             COUNT(DISTINCT se.student_id) as student_count,
                             COUNT(DISTINCT a.id) as assignment_count
                      FROM class_subjects cs
                      JOIN subjects s ON cs.subject_id = s.id
                      JOIN classes c ON cs.class_id = c.id
                      LEFT JOIN student_enrollments se ON c.id = se.class_id AND se.status = 'active'
                      LEFT JOIN assignments a ON s.id = a.subject_id AND c.id = a.class_id AND a.teacher_id = :teacher_id
                      WHERE cs.teacher_id = :teacher_id
                      GROUP BY cs.id
                      ORDER BY c.grade_level ASC, s.name ASC";

    $subjectsStmt = $db->prepare($subjectsQuery);
    $subjectsStmt->bindValue(':teacher_id', $teacherId);
    $subjectsStmt->execute();

    $subjectAssignments = $subjectsStmt->fetchAll();

    // Get class teacher assignments
    $classesQuery = "SELECT c.*, ay.name as academic_year_name,
                            COUNT(DISTINCT se.student_id) as student_count,
                            COUNT(DISTINCT sec.id) as section_count
                     FROM classes c
                     LEFT JOIN academic_years ay ON c.academic_year_id = ay.id
                     LEFT JOIN student_enrollments se ON c.id = se.class_id AND se.status = 'active'
                     LEFT JOIN sections sec ON c.id = sec.class_id
                     WHERE c.class_teacher_id = :teacher_id
                     GROUP BY c.id
                     ORDER BY c.grade_level ASC";

    $classesStmt = $db->prepare($classesQuery);
    $classesStmt->bindValue(':teacher_id', $teacherId);
    $classesStmt->execute();

    $classTeacherAssignments = $classesStmt->fetchAll();

    // Get timetable
    $timetableQuery = "SELECT t.*, c.name as class_name, sec.name as section_name,
                              s.name as subject_name, ay.name as academic_year_name
                       FROM timetables t
                       JOIN classes c ON t.class_id = c.id
                       LEFT JOIN sections sec ON t.section_id = sec.id
                       LEFT JOIN subjects s ON t.subject_id = s.id
                       LEFT JOIN academic_years ay ON t.academic_year_id = ay.id
                       WHERE t.teacher_id = :teacher_id
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
                           t.start_time";

    $timetableStmt = $db->prepare($timetableQuery);
    $timetableStmt->bindValue(':teacher_id', $teacherId);
    $timetableStmt->execute();

    $timetable = $timetableStmt->fetchAll();

    // Get recent assignments created
    $recentAssignmentsQuery = "SELECT a.*, s.name as subject_name, c.name as class_name,
                                      COUNT(asub.id) as submission_count
                               FROM assignments a
                               JOIN subjects s ON a.subject_id = s.id
                               JOIN classes c ON a.class_id = c.id
                               LEFT JOIN assignment_submissions asub ON a.id = asub.assignment_id
                               WHERE a.teacher_id = :teacher_id
                               GROUP BY a.id
                               ORDER BY a.created_at DESC
                               LIMIT 10";

    $recentAssignmentsStmt = $db->prepare($recentAssignmentsQuery);
    $recentAssignmentsStmt->bindValue(':teacher_id', $teacherId);
    $recentAssignmentsStmt->execute();

    $recentAssignments = $recentAssignmentsStmt->fetchAll();

    // Get attendance marking activity (last 30 days)
    $attendanceQuery = "SELECT 
                           DATE(a.date) as attendance_date,
                           COUNT(a.id) as total_marked,
                           COUNT(CASE WHEN a.status = 'present' THEN 1 END) as present_count,
                           COUNT(CASE WHEN a.status = 'absent' THEN 1 END) as absent_count,
                           c.name as class_name,
                           sec.name as section_name
                        FROM attendance a
                        JOIN classes c ON a.class_id = c.id
                        LEFT JOIN sections sec ON a.section_id = sec.id
                        WHERE a.teacher_id = :teacher_id
                        AND a.date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
                        GROUP BY DATE(a.date), a.class_id, a.section_id
                        ORDER BY a.date DESC";

    $attendanceStmt = $db->prepare($attendanceQuery);
    $attendanceStmt->bindValue(':teacher_id', $teacherId);
    $attendanceStmt->execute();

    $recentAttendance = $attendanceStmt->fetchAll();

    // Get activity logs (last 30 days)
    $activityQuery = "SELECT al.*, 
                             CASE 
                                 WHEN al.entity_type = 'assignment' THEN (SELECT title FROM assignments WHERE id = al.entity_id)
                                 WHEN al.entity_type = 'student' THEN (SELECT CONCAT(first_name, ' ', last_name) FROM students WHERE id = al.entity_id)
                                 ELSE al.entity_type
                             END as entity_name
                      FROM activity_logs al
                      WHERE al.user_id = :teacher_id
                      AND al.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
                      ORDER BY al.created_at DESC
                      LIMIT 20";

    $activityStmt = $db->prepare($activityQuery);
    $activityStmt->bindValue(':teacher_id', $teacherId);
    $activityStmt->execute();

    $recentActivity = $activityStmt->fetchAll();

    $teacher['subject_assignments'] = $subjectAssignments;
    $teacher['class_teacher_assignments'] = $classTeacherAssignments;
    $teacher['timetable'] = $timetable;
    $teacher['recent_assignments'] = $recentAssignments;
    $teacher['recent_attendance'] = $recentAttendance;
    $teacher['recent_activity'] = $recentActivity;

    Response::success(['teacher' => $teacher], 'Teacher details retrieved successfully');
}

function handleGetTeacherWorkload($db, $user)
{
    $teacherId = $_GET['teacher_id'] ?? null;

    if (!$teacherId) {
        Response::error('Teacher ID is required', 400);
    }

    $schoolCondition = "";
    $params = [':teacher_id' => $teacherId];

    if ($user['role_name'] !== 'super_admin') {
        $schoolCondition = "AND u.school_id = :school_id";
        $params[':school_id'] = $user['school_id'];
    }

    // Validate teacher
    $teacherQuery = "SELECT id FROM users u WHERE id = :teacher_id AND role_id IN (3, 4) $schoolCondition";
    $teacherStmt = $db->prepare($teacherQuery);
    foreach ($params as $key => $value) {
        $teacherStmt->bindValue($key, $value);
    }
    $teacherStmt->execute();

    if (!$teacherStmt->fetch()) {
        Response::error('Teacher not found or access denied', 404);
    }

    // Calculate workload metrics
    $workloadQuery = "SELECT 
                         -- Subject teaching load
                         (SELECT COUNT(*) FROM class_subjects WHERE teacher_id = :teacher_id1) as subject_assignments,
                         (SELECT COUNT(DISTINCT class_id) FROM class_subjects WHERE teacher_id = :teacher_id2) as classes_taught,
                         (SELECT COUNT(DISTINCT subject_id) FROM class_subjects WHERE teacher_id = :teacher_id3) as subjects_taught,
                         
                         -- Class teacher responsibilities
                         (SELECT COUNT(*) FROM classes WHERE class_teacher_id = :teacher_id4) as class_teacher_assignments,
                         
                         -- Timetable periods per week
                         (SELECT COUNT(*) FROM timetables WHERE teacher_id = :teacher_id5) as weekly_periods,
                         
                         -- Student count
                         (SELECT COUNT(DISTINCT se.student_id) 
                          FROM class_subjects cs 
                          JOIN student_enrollments se ON cs.class_id = se.class_id AND se.status = 'active'
                          WHERE cs.teacher_id = :teacher_id6) as total_students_taught,
                         
                         -- Assignment workload (last 30 days)
                         (SELECT COUNT(*) FROM assignments 
                          WHERE teacher_id = :teacher_id7 
                          AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)) as recent_assignments_created,
                         
                         -- Attendance marking (last 30 days)
                         (SELECT COUNT(DISTINCT DATE(date)) FROM attendance 
                          WHERE teacher_id = :teacher_id8 
                          AND date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)) as attendance_days_marked";

    $workloadStmt = $db->prepare($workloadQuery);
    for ($i = 1; $i <= 8; $i++) {
        $workloadStmt->bindValue(":teacher_id$i", $teacherId);
    }
    $workloadStmt->execute();

    $workload = $workloadStmt->fetch();

    // Get detailed timetable distribution
    $timetableDistQuery = "SELECT 
                              t.day_of_week,
                              COUNT(*) as period_count,
                              GROUP_CONCAT(CONCAT(t.start_time, '-', t.end_time) ORDER BY t.start_time) as time_slots,
                              GROUP_CONCAT(DISTINCT s.name ORDER BY s.name) as subjects
                           FROM timetables t
                           LEFT JOIN subjects s ON t.subject_id = s.id
                           WHERE t.teacher_id = :teacher_id
                           GROUP BY t.day_of_week
                           ORDER BY 
                               CASE t.day_of_week 
                                   WHEN 'monday' THEN 1
                                   WHEN 'tuesday' THEN 2
                                   WHEN 'wednesday' THEN 3
                                   WHEN 'thursday' THEN 4
                                   WHEN 'friday' THEN 5
                                   WHEN 'saturday' THEN 6
                                   WHEN 'sunday' THEN 7
                               END";

    $timetableDistStmt = $db->prepare($timetableDistQuery);
    $timetableDistStmt->bindValue(':teacher_id', $teacherId);
    $timetableDistStmt->execute();

    $timetableDistribution = $timetableDistStmt->fetchAll();

    // Get class-wise student distribution
    $studentDistQuery = "SELECT 
                            c.name as class_name,
                            c.grade_level,
                            s.name as subject_name,
                            COUNT(DISTINCT se.student_id) as student_count
                         FROM class_subjects cs
                         JOIN classes c ON cs.class_id = c.id
                         JOIN subjects s ON cs.subject_id = s.id
                         LEFT JOIN student_enrollments se ON c.id = se.class_id AND se.status = 'active'
                         WHERE cs.teacher_id = :teacher_id
                         GROUP BY cs.id
                         ORDER BY c.grade_level ASC, s.name ASC";

    $studentDistStmt = $db->prepare($studentDistQuery);
    $studentDistStmt->bindValue(':teacher_id', $teacherId);
    $studentDistStmt->execute();

    $studentDistribution = $studentDistStmt->fetchAll();

    Response::success([
        'workload_summary' => $workload,
        'timetable_distribution' => $timetableDistribution,
        'student_distribution' => $studentDistribution
    ], 'Teacher workload retrieved successfully');
}

function handleGetTeacherPerformance($db, $user)
{
    $teacherId = $_GET['teacher_id'] ?? null;

    if (!$teacherId) {
        Response::error('Teacher ID is required', 400);
    }

    $schoolCondition = "";
    $params = [':teacher_id' => $teacherId];

    if ($user['role_name'] !== 'super_admin') {
        $schoolCondition = "AND u.school_id = :school_id";
        $params[':school_id'] = $user['school_id'];
    }

    // Validate teacher
    $teacherQuery = "SELECT CONCAT(first_name, ' ', last_name) as name FROM users u WHERE id = :teacher_id AND role_id IN (3, 4) $schoolCondition";
    $teacherStmt = $db->prepare($teacherQuery);
    foreach ($params as $key => $value) {
        $teacherStmt->bindValue($key, $value);
    }
    $teacherStmt->execute();

    $teacher = $teacherStmt->fetch();
    if (!$teacher) {
        Response::error('Teacher not found or access denied', 404);
    }

    // Performance metrics for last 3 months
    $performanceQuery = "SELECT 
                            -- Assignment metrics
                            COUNT(DISTINCT a.id) as assignments_created,
                            AVG(CASE WHEN asub.marks_obtained IS NOT NULL THEN asub.marks_obtained END) as avg_marks_given,
                            COUNT(DISTINCT asub.id) as assignments_graded,
                            
                            -- Attendance metrics
                            COUNT(DISTINCT att.date) as attendance_days_marked,
                            COUNT(DISTINCT att.student_id) as students_attendance_marked,
                            
                            -- Activity metrics
                            COUNT(DISTINCT al.id) as total_activities,
                            COUNT(DISTINCT DATE(al.created_at)) as active_days
                         FROM users u
                         LEFT JOIN assignments a ON u.id = a.teacher_id AND a.created_at >= DATE_SUB(NOW(), INTERVAL 3 MONTH)
                         LEFT JOIN assignment_submissions asub ON a.id = asub.assignment_id AND asub.graded_at >= DATE_SUB(NOW(), INTERVAL 3 MONTH)
                         LEFT JOIN attendance att ON u.id = att.teacher_id AND att.date >= DATE_SUB(CURDATE(), INTERVAL 3 MONTH)
                         LEFT JOIN activity_logs al ON u.id = al.user_id AND al.created_at >= DATE_SUB(NOW(), INTERVAL 3 MONTH)
                         WHERE u.id = :teacher_id";

    $performanceStmt = $db->prepare($performanceQuery);
    $performanceStmt->bindValue(':teacher_id', $teacherId);
    $performanceStmt->execute();

    $performance = $performanceStmt->fetch();

    // Monthly breakdown
    $monthlyQuery = "SELECT 
                        DATE_FORMAT(created_at, '%Y-%m') as month,
                        COUNT(CASE WHEN entity_type = 'assignment' AND action = 'create' THEN 1 END) as assignments_created,
                        COUNT(CASE WHEN entity_type = 'attendance' AND action = 'mark' THEN 1 END) as attendance_marked,
                        COUNT(CASE WHEN action = 'login' THEN 1 END) as login_count
                     FROM activity_logs
                     WHERE user_id = :teacher_id
                     AND created_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
                     GROUP BY DATE_FORMAT(created_at, '%Y-%m')
                     ORDER BY month DESC";

    $monthlyStmt = $db->prepare($monthlyQuery);
    $monthlyStmt->bindValue(':teacher_id', $teacherId);
    $monthlyStmt->execute();

    $monthlyBreakdown = $monthlyStmt->fetchAll();

    // Student feedback/performance in teacher's subjects
    $studentPerformanceQuery = "SELECT 
                                   s.name as subject_name,
                                   c.name as class_name,
                                   COUNT(DISTINCT se.student_id) as total_students,
                                   AVG(CASE WHEN asub.marks_obtained IS NOT NULL THEN (asub.marks_obtained / a.max_marks) * 100 END) as avg_percentage,
                                   COUNT(DISTINCT a.id) as total_assignments
                                FROM class_subjects cs
                                JOIN subjects s ON cs.subject_id = s.id
                                JOIN classes c ON cs.class_id = c.id
                                LEFT JOIN student_enrollments se ON c.id = se.class_id AND se.status = 'active'
                                LEFT JOIN assignments a ON s.id = a.subject_id AND c.id = a.class_id AND a.teacher_id = :teacher_id
                                LEFT JOIN assignment_submissions asub ON a.id = asub.assignment_id AND asub.marks_obtained IS NOT NULL
                                WHERE cs.teacher_id = :teacher_id
                                GROUP BY cs.id
                                ORDER BY avg_percentage DESC";

    $studentPerfStmt = $db->prepare($studentPerformanceQuery);
    $studentPerfStmt->bindValue(':teacher_id', $teacherId);
    $studentPerfStmt->execute();

    $studentPerformance = $studentPerfStmt->fetchAll();

    Response::success([
        'teacher_name' => $teacher['name'],
        'performance_summary' => $performance,
        'monthly_breakdown' => $monthlyBreakdown,
        'student_performance' => $studentPerformance,
        'period' => 'Last 3 months'
    ], 'Teacher performance retrieved successfully');
}

function handleGetTeacherAnalytics($db, $user)
{
    $schoolCondition = "";
    $params = [];

    if ($user['role_name'] !== 'super_admin') {
        $schoolCondition = "WHERE u.school_id = :school_id";
        $params[':school_id'] = $user['school_id'];
    } else if (isset($_GET['school_id'])) {
        $schoolCondition = "WHERE u.school_id = :school_id";
        $params[':school_id'] = $_GET['school_id'];
    }

    $teacherCondition = $schoolCondition ? " AND u.role_id IN (3, 4)" : "WHERE u.role_id IN (3, 4)";
    $schoolCondition .= $teacherCondition;

    // Overall teacher statistics
    $statsQuery = "SELECT 
                      COUNT(u.id) as total_teachers,
                      COUNT(CASE WHEN u.teacher_type = 'academic' THEN 1 END) as academic_teachers,
                      COUNT(CASE WHEN u.teacher_type = 'administrative' THEN 1 END) as administrative_teachers,
                      COUNT(CASE WHEN u.is_active = 1 THEN 1 END) as active_teachers,
                      COUNT(CASE WHEN u.is_active = 0 THEN 1 END) as inactive_teachers,
                      AVG(u.experience_years) as avg_experience,
                      COUNT(CASE WHEN u.role_id = 3 THEN 1 END) as teacher_academic_count,
                      COUNT(CASE WHEN u.role_id = 4 THEN 1 END) as teacher_administrative_count
                   FROM users u
                   $schoolCondition";

    $statsStmt = $db->prepare($statsQuery);
    foreach ($params as $key => $value) {
        $statsStmt->bindValue($key, $value);
    }
    $statsStmt->execute();

    $overallStats = $statsStmt->fetch();

    // Experience distribution
    $experienceQuery = "SELECT 
                           CASE 
                               WHEN u.experience_years < 2 THEN 'Fresher (0-2 years)'
                               WHEN u.experience_years < 5 THEN 'Junior (2-5 years)'
                               WHEN u.experience_years < 10 THEN 'Mid-level (5-10 years)'
                               WHEN u.experience_years < 15 THEN 'Senior (10-15 years)'
                               ELSE 'Expert (15+ years)'
                           END as experience_category,
                           COUNT(*) as teacher_count
                        FROM users u
                        $schoolCondition
                        GROUP BY experience_category
                        ORDER BY MIN(u.experience_years)";

    $experienceStmt = $db->prepare($experienceQuery);
    foreach ($params as $key => $value) {
        $experienceStmt->bindValue($key, $value);
    }
    $experienceStmt->execute();

    $experienceDistribution = $experienceStmt->fetchAll();

    // Workload distribution
    $workloadQuery = "SELECT 
                         u.id,
                         CONCAT(u.first_name, ' ', u.last_name) as teacher_name,
                         u.employee_id,
                         COUNT(DISTINCT cs.teacher_id) as subject_assignments,
                         COUNT(DISTINCT c.id) as class_teacher_assignments,
                         COUNT(DISTINCT t.id) as weekly_periods,
                         COUNT(DISTINCT se.student_id) as total_students
                      FROM users u
                      LEFT JOIN class_subjects cs ON u.id = cs.teacher_id
                      LEFT JOIN classes c ON u.id = c.class_teacher_id
                      LEFT JOIN timetables t ON u.id = t.teacher_id
                      LEFT JOIN class_subjects cs2 ON u.id = cs2.teacher_id
                      LEFT JOIN student_enrollments se ON cs2.class_id = se.class_id AND se.status = 'active'
                      $schoolCondition
                      GROUP BY u.id
                      ORDER BY total_students DESC, weekly_periods DESC";

    $workloadStmt = $db->prepare($workloadQuery);
    foreach ($params as $key => $value) {
        $workloadStmt->bindValue($key, $value);
    }
    $workloadStmt->execute();

    $workloadDistribution = $workloadStmt->fetchAll();

    // Recent activity summary (last 30 days)
    $activityQuery = "SELECT 
                         COUNT(CASE WHEN al.action = 'create_assignment' THEN 1 END) as assignments_created,
                         COUNT(CASE WHEN al.action = 'mark_attendance' THEN 1 END) as attendance_marked,
                         COUNT(CASE WHEN al.action = 'grade_assignment' THEN 1 END) as assignments_graded,
                         COUNT(CASE WHEN al.action = 'login' THEN 1 END) as login_count,
                         COUNT(DISTINCT al.user_id) as active_teachers
                      FROM activity_logs al
                      JOIN users u ON al.user_id = u.id
                      WHERE al.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
                      AND u.role_id IN (3, 4)
                      " . str_replace('u.', 'u.', str_replace('WHERE', 'AND', $schoolCondition));

    $activityStmt = $db->prepare($activityQuery);
    foreach ($params as $key => $value) {
        if (strpos($key, 'school_id') !== false) {
            $activityStmt->bindValue($key, $value);
        }
    }
    $activityStmt->execute();

    $recentActivity = $activityStmt->fetch();

    // Subject-wise teacher distribution
    $subjectDistQuery = "SELECT 
                            s.name as subject_name,
                            s.code as subject_code,
                            COUNT(DISTINCT cs.teacher_id) as teacher_count,
                            COUNT(DISTINCT cs.class_id) as class_count
                         FROM subjects s
                         LEFT JOIN class_subjects cs ON s.id = cs.subject_id
                         LEFT JOIN users u ON cs.teacher_id = u.id
                         " . str_replace('u.', 's.', str_replace('u.role_id IN (3, 4)', '1=1', $schoolCondition)) . "
                         GROUP BY s.id
                         HAVING teacher_count > 0
                         ORDER BY teacher_count DESC, s.name ASC";

    $subjectDistStmt = $db->prepare($subjectDistQuery);
    foreach ($params as $key => $value) {
        if (strpos($key, 'school_id') !== false) {
            $subjectDistStmt->bindValue($key, $value);
        }
    }
    $subjectDistStmt->execute();

    $subjectDistribution = $subjectDistStmt->fetchAll();

    Response::success([
        'overall_statistics' => $overallStats,
        'experience_distribution' => $experienceDistribution,
        'workload_distribution' => $workloadDistribution,
        'recent_activity' => $recentActivity,
        'subject_distribution' => $subjectDistribution
    ], 'Teacher analytics retrieved successfully');
}

function handleCreateTeacher($db, $user)
{
    $isJson = isset($_SERVER["CONTENT_TYPE"]) && strpos($_SERVER["CONTENT_TYPE"], "application/json") !== false;
    if ($isJson) {
        $input = json_decode(file_get_contents('php://input'), true);
    } else {
        $input = $_POST;
    }

    // Validate required fields
    $required = ['first_name', 'last_name', 'email', 'phone', 'role_id'];
    foreach ($required as $field) {
        if (!isset($input[$field]) || empty($input[$field])) {
            Response::error("Field '$field' is required", 400);
        }
    }

    $schoolId = $user['role_name'] === 'super_admin' ? ($input['school_id'] ?? null) : $user['school_id'];

    if (!$schoolId) {
        Response::error('School ID is required', 400);
    }

    // Validate role is teacher role
    if (!in_array($input['role_id'], [3, 4, 5, 6])) {
        Response::error('Invalid role for teacher', 400);
    }

    // Validate email format
    if (!filter_var($input['email'], FILTER_VALIDATE_EMAIL)) {
        Response::error('Invalid email format', 400);
    }

    // Check for duplicate email
    $emailQuery = "SELECT id FROM users WHERE email = :email";
    $emailStmt = $db->prepare($emailQuery);
    $emailStmt->bindValue(':email', $input['email']);
    $emailStmt->execute();

    if ($emailStmt->fetch()) {
        Response::error('Email already exists', 400);
    }

    $db->beginTransaction();

    try {
        // Get school details for employee ID generation
        $schoolQuery = "SELECT code, name FROM schools WHERE id = :school_id";
        $schoolStmt = $db->prepare($schoolQuery);
        $schoolStmt->bindValue(':school_id', $schoolId);
        $schoolStmt->execute();
        $schoolData = $schoolStmt->fetch();

        if (!$schoolData) {
            throw new Exception('School not found');
        }

        // Generate employee ID
        $empIdQuery = "SELECT COUNT(*) + 1 as next_id FROM users WHERE school_id = :school_id AND role_id IN (3, 4, 5,6)";
        $empIdStmt = $db->prepare($empIdQuery);
        $empIdStmt->bindValue(':school_id', $schoolId);
        $empIdStmt->execute();
        $nextId = $empIdStmt->fetch()['next_id'];

        $employeeId = $schoolData['code'] . 'T' . str_pad($nextId, 3, '0', STR_PAD_LEFT);

        // Generate username automatically
        $baseUsername = strtolower($input['first_name'] . '.' . $input['last_name']);
        $baseUsername = preg_replace('/[^a-z0-9.]/', '', $baseUsername); // Remove special characters

        // Check for duplicate username and make it unique
        $username = $baseUsername;
        $counter = 1;

        do {
            $usernameQuery = "SELECT id FROM users WHERE username = :username";
            $usernameStmt = $db->prepare($usernameQuery);
            $usernameStmt->bindValue(':username', $username);
            $usernameStmt->execute();

            if ($usernameStmt->fetch()) {
                $username = $baseUsername . $counter;
                $counter++;
            } else {
                break;
            }
        } while ($counter < 100); // Prevent infinite loop

        // Handle profile image upload
        $profileImagePath = null;
        if (isset($_FILES['profile_image']) && $_FILES['profile_image']['error'] === UPLOAD_ERR_OK) {
            $uploadDir = '../../uploads/teacher_profiles/';
            if (!file_exists($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }
            $extension = pathinfo($_FILES['profile_image']['name'], PATHINFO_EXTENSION);
            $fileName = 'teacher_' . $employeeId . '_' . time() . '.' . $extension;
            if (move_uploaded_file($_FILES['profile_image']['tmp_name'], $uploadDir . $fileName)) {
                $profileImagePath = 'uploads/teacher_profiles/' . $fileName;
            }
        }

        // Generate password
        $password = $input['password'] ?? bin2hex(random_bytes(4));
        $passwordHash = password_hash($password, PASSWORD_DEFAULT);

        // Insert user
        $insertQuery = "INSERT INTO users (
            school_id, role_id, username, email, password_hash, first_name, last_name, phone,
            employee_id, teacher_type, qualification, experience_years, joining_date, 
            salary, address, date_of_birth, gender, is_active, email_verified, profile_image
        ) VALUES (
            :school_id, :role_id, :username, :email, :password_hash, :first_name, :last_name, :phone,
            :employee_id, :teacher_type, :qualification, :experience_years, :joining_date,
            :salary, :address, :date_of_birth, :gender, :is_active, :email_verified, :profile_image
        )";
        $teacher_type = $input['teacher_type'] ?? 'academic';
        $gender = $input['gender'] ?? null;
        $qualification = $input['qualification'] ?? null;
        $experience_years = $input['experience_years'] ?? 0;
        $joining_date = $input['joining_date'] ?? date('Y-m-d');
        $salary = $input['salary'] ?? null;
        $address = $input['address'] ?? null;
        $is_active = $input['is_active'] ?? 1;
        $email_verified = $input['email_verified'] ?? 0;
        $dob = $input['date_of_birth'] ?? date('Y-m-d');
        
        $insertStmt = $db->prepare($insertQuery);
        $insertStmt->bindValue(':school_id', $schoolId);
        $insertStmt->bindValue(':role_id', $input['role_id']);
        $insertStmt->bindValue(':username', $username);
        $insertStmt->bindValue(':email', $input['email']);
        $insertStmt->bindValue(':password_hash', $passwordHash);
        $insertStmt->bindValue(':first_name', $input['first_name']);
        $insertStmt->bindValue(':last_name', $input['last_name']);
        $insertStmt->bindValue(':phone', $input['phone']);
        $insertStmt->bindValue(':employee_id', $employeeId);
        $insertStmt->bindValue(':date_of_birth', $dob);
        $insertStmt->bindValue(':gender', $gender);
        $insertStmt->bindValue(':teacher_type', $teacher_type);
        $insertStmt->bindValue(':qualification', $qualification);
        $insertStmt->bindValue(':experience_years', $experience_years);
        $insertStmt->bindValue(':joining_date', $joining_date);
        $insertStmt->bindValue(':salary', $salary);
        $insertStmt->bindValue(':address', $address);
        $insertStmt->bindValue(':is_active', $is_active);
        $insertStmt->bindValue(':email_verified', $email_verified);
        $insertStmt->bindValue(':profile_image', $profileImagePath);

        $insertStmt->execute();
        $teacherId = $db->lastInsertId();

        // Auto-assign subjects if provided
        if (isset($input['subject_assignments']) && is_array($input['subject_assignments'])) {
            $assignQuery = "INSERT INTO class_subjects (class_id, subject_id, teacher_id) VALUES (:class_id, :subject_id, :teacher_id)";
            $assignStmt = $db->prepare($assignQuery);

            foreach ($input['subject_assignments'] as $assignment) {
                if (isset($assignment['class_id']) && isset($assignment['subject_id'])) {
                    $assignStmt->bindValue(':class_id', $assignment['class_id']);
                    $assignStmt->bindValue(':subject_id', $assignment['subject_id']);
                    $assignStmt->bindValue(':teacher_id', $teacherId);
                    $assignStmt->execute();
                }
            }
        }

        // Log activity
        $logQuery = "INSERT INTO activity_logs (user_id, user_type, school_id, action, entity_type, entity_id, ip_address) 
                     VALUES (:user_id, 'user', :school_id, 'create_teacher', 'user', :entity_id, :ip_address)";
        $logStmt = $db->prepare($logQuery);
        $logStmt->bindValue(':user_id', $user['id']);
        $logStmt->bindValue(':school_id', $schoolId);
        $logStmt->bindValue(':entity_id', $teacherId);
        $logStmt->bindValue(':ip_address', $_SERVER['REMOTE_ADDR']);
        $logStmt->execute();

        $db->commit();

        // Send welcome email
        $emailSent = false;
        try {
            $emailService = new EmailService();
            $teacherData = [
                'first_name' => $input['first_name'],
                'last_name' => $input['last_name'],
                'email' => $input['email'],
                'username' => $username,
                'employee_id' => $employeeId
            ];

            $emailSent = $emailService->sendWelcomeEmail('teacher', $teacherData, $schoolData, $password);
        } catch (Exception $e) {
            // Email failed but teacher was created successfully
            error_log('Failed to send welcome email to teacher: ' . $e->getMessage());
        }

        Response::success([
            'teacher_id' => $teacherId,
            'employee_id' => $employeeId,
            'username' => $username,
            'password' => $password,
            'email_sent' => $emailSent
        ], 'Teacher created successfully');

    } catch (Exception $e) {
        $db->rollBack();
        throw $e;
    }
}

function handleUpdateTeacher($db, $user)
{
    $input = json_decode(file_get_contents('php://input'), true);
    $teacherId = $_GET['teacher_id'] ?? $input['teacher_id'] ?? null;

    if (!$teacherId) {
        Response::error('Teacher ID is required', 400);
    }

    // Validate teacher belongs to user's school
    $schoolCondition = "";
    $params = [':teacher_id' => $teacherId];

    if ($user['role_name'] !== 'super_admin') {
        $schoolCondition = "AND school_id = :school_id";
        $params[':school_id'] = $user['school_id'];
    }

    $teacherQuery = "SELECT * FROM users WHERE id = :teacher_id AND role_id IN (3, 4) $schoolCondition";
    $teacherStmt = $db->prepare($teacherQuery);
    foreach ($params as $key => $value) {
        $teacherStmt->bindValue($key, $value);
    }
    $teacherStmt->execute();

    $existingTeacher = $teacherStmt->fetch();
    if (!$existingTeacher) {
        Response::error('Teacher not found or access denied', 404);
    }

    // Build update query dynamically
    $updateFields = [];
    $updateParams = [':teacher_id' => $teacherId];

    $allowedFields = [
        'first_name',
        'last_name',
        'email',
        'phone',
        'teacher_type',
        'qualification',
        'experience_years',
        'salary',
        'address',
        'date_of_birth',
        'gender',
        'is_active',
        'role_id'
    ];

    foreach ($allowedFields as $field) {
        if (isset($input[$field])) {
            // Validate role if updating
            if ($field === 'role_id' && !in_array($input[$field], [3, 4])) {
                Response::error('Invalid role for teacher', 400);
            }

            // Check for duplicate email if updating email
            if ($field === 'email' && $input[$field] !== $existingTeacher['email']) {
                if (!filter_var($input[$field], FILTER_VALIDATE_EMAIL)) {
                    Response::error('Invalid email format', 400);
                }

                $emailQuery = "SELECT id FROM users WHERE email = :email AND id != :teacher_id";
                $emailStmt = $db->prepare($emailQuery);
                $emailStmt->bindValue(':email', $input[$field]);
                $emailStmt->bindValue(':teacher_id', $teacherId);
                $emailStmt->execute();

                if ($emailStmt->fetch()) {
                    Response::error('Email already exists', 400);
                }
            }

            $updateFields[] = "$field = :$field";
            $updateParams[":$field"] = $input[$field];
        }
    }

    // Handle password update separately
    if (isset($input['password']) && !empty($input['password'])) {
        $updateFields[] = "password_hash = :password_hash";
        $updateParams[":password_hash"] = password_hash($input['password'], PASSWORD_DEFAULT);
    }

    if (empty($updateFields)) {
        Response::error('No valid fields to update', 400);
    }

    $updateFields[] = "updated_at = NOW()";
    $updateQuery = "UPDATE users SET " . implode(', ', $updateFields) . " WHERE id = :teacher_id";

    $updateStmt = $db->prepare($updateQuery);
    foreach ($updateParams as $key => $value) {
        $updateStmt->bindValue($key, $value);
    }

    $updateStmt->execute();

    // Log activity
    $logQuery = "INSERT INTO activity_logs (user_id, user_type, school_id, action, entity_type, entity_id, ip_address) 
                 VALUES (:user_id, 'user', :school_id, 'update_teacher', 'user', :entity_id, :ip_address)";
    $logStmt = $db->prepare($logQuery);
    $logStmt->bindValue(':user_id', $user['id']);
    $logStmt->bindValue(':school_id', $existingTeacher['school_id']);
    $logStmt->bindValue(':entity_id', $teacherId);
    $logStmt->bindValue(':ip_address', $_SERVER['REMOTE_ADDR'] ?? null);
    $logStmt->execute();

    Response::success(['teacher_id' => $teacherId], 'Teacher updated successfully');
}

function handleDeleteTeacher($db, $user)
{
    $teacherId = $_GET['teacher_id'] ?? null;

    if (!$teacherId) {
        Response::error('Teacher ID is required', 400);
    }

    // Validate teacher belongs to user's school
    $schoolCondition = "";
    $params = [':teacher_id' => $teacherId];

    if ($user['role_name'] !== 'super_admin') {
        $schoolCondition = "AND school_id = :school_id";
        $params[':school_id'] = $user['school_id'];
    }

    $teacherQuery = "SELECT id, school_id FROM users WHERE id = :teacher_id AND role_id IN (3, 4) $schoolCondition";
    $teacherStmt = $db->prepare($teacherQuery);
    foreach ($params as $key => $value) {
        $teacherStmt->bindValue($key, $value);
    }
    $teacherStmt->execute();

    $teacher = $teacherStmt->fetch();
    if (!$teacher) {
        Response::error('Teacher not found or access denied', 404);
    }

    // Check if teacher has active assignments
    $assignmentQuery = "SELECT 
                           (SELECT COUNT(*) FROM class_subjects WHERE teacher_id = :teacher_id1) as subject_assignments,
                           (SELECT COUNT(*) FROM classes WHERE class_teacher_id = :teacher_id2) as class_assignments,
                           (SELECT COUNT(*) FROM assignments WHERE teacher_id = :teacher_id3) as assignment_count,
                           (SELECT COUNT(*) FROM timetables WHERE teacher_id = :teacher_id4) as timetable_count";
    $assignmentStmt = $db->prepare($assignmentQuery);
    $assignmentStmt->bindValue(':teacher_id1', $teacherId);
    $assignmentStmt->bindValue(':teacher_id2', $teacherId);
    $assignmentStmt->bindValue(':teacher_id3', $teacherId);
    $assignmentStmt->bindValue(':teacher_id4', $teacherId);
    $assignmentStmt->execute();

    $assignments = $assignmentStmt->fetch();

    if (
        $assignments['subject_assignments'] > 0 || $assignments['class_assignments'] > 0 ||
        $assignments['assignment_count'] > 0 || $assignments['timetable_count'] > 0
    ) {
        // Soft delete - mark as inactive instead of hard delete
        $updateQuery = "UPDATE users SET is_active = 0, updated_at = NOW() WHERE id = :teacher_id";
        $updateStmt = $db->prepare($updateQuery);
        $updateStmt->bindValue(':teacher_id', $teacherId);
        $updateStmt->execute();

        // Log activity
        $logQuery = "INSERT INTO activity_logs (user_id, user_type, school_id, action, entity_type, entity_id, ip_address) 
                     VALUES (:user_id, 'user', :school_id, 'deactivate_teacher', 'user', :entity_id, :ip_address)";
        $logStmt = $db->prepare($logQuery);
        $logStmt->bindValue(':user_id', $user['id']);
        $logStmt->bindValue(':school_id', $teacher['school_id']);
        $logStmt->bindValue(':entity_id', $teacherId);
        $logStmt->bindValue(':ip_address', $_SERVER['REMOTE_ADDR'] ?? null);
        $logStmt->execute();

        Response::success(null, 'Teacher marked as inactive due to existing assignments');
    } else {
        $db->beginTransaction();

        try {
            // Delete related records
            $deleteQueries = [
                "DELETE FROM auth_tokens WHERE user_id = :teacher_id",
                "DELETE FROM users WHERE id = :teacher_id"
            ];

            foreach ($deleteQueries as $query) {
                $stmt = $db->prepare($query);
                $stmt->bindValue(':teacher_id', $teacherId);
                $stmt->execute();
            }

            // Log activity
            $logQuery = "INSERT INTO activity_logs (user_id, user_type, school_id, action, entity_type, entity_id, ip_address) 
                         VALUES (:user_id, 'user', :school_id, 'delete_teacher', 'user', :entity_id, :ip_address)";
            $logStmt = $db->prepare($logQuery);
            $logStmt->bindValue(':user_id', $user['id']);
            $logStmt->bindValue(':school_id', $teacher['school_id']);
            $logStmt->bindValue(':entity_id', $teacherId);
            $logStmt->bindValue(':ip_address', $_SERVER['REMOTE_ADDR'] ?? null);
            $logStmt->execute();

            $db->commit();

            Response::success(null, 'Teacher deleted successfully');

        } catch (Exception $e) {
            $db->rollBack();
            throw $e;
        }
    }
}

function generateRandomPassword($length = 8)
{
    $characters = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
    $password = '';

    for ($i = 0; $i < $length; $i++) {
        $password .= $characters[rand(0, strlen($characters) - 1)];
    }

    return $password;
}
?>