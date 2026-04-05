<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

require_once '../../middleware/auth.php';
require_once '../../config/database.php';
require_once '../../includes/response.php';


// ini_set('display_errors', 1);
// ini_set('display_startup_errors', 1);
// error_reporting(E_ALL);


$auth = new AuthMiddleware();
$user = $auth->requireRole(['super_admin', 'admin', 'school_admin', 'teacher_academic', 'teacher_administrative', 'accountant', 'librarian']);

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
                        handleGetClasses($db, $user);
                        break;
                    case 'details':
                        handleGetClassDetails($db, $user);
                        break;
                    case 'students':
                        handleGetClassStudents($db, $user);
                        break;
                    case 'subjects':
                        handleGetClassSubjects($db, $user);
                        break;
                    case 'sections':
                        handleGetClassSections($db, $user);
                        break;
                    default:
                        handleGetClasses($db, $user);
                }
            }
            else {
                handleGetClasses($db, $user);
            }
            break;
        case 'POST':
            if (isset($_GET['action'])) {
                switch ($_GET['action']) {
                    case 'create_section':
                        handleCreateSection($db, $user);
                        break;
                    case 'update_section':
                        handleUpdateSection($db, $user);
                        break;
                    case 'assign_subject':
                        handleAssignSubject($db, $user);
                        break;
                    default:
                        handleCreateClass($db, $user);
                }
            }
            else {
                handleCreateClass($db, $user);
            }
            break;
        case 'PUT':
            handleUpdateClass($db, $user);
            break;
        case 'DELETE':
            if (isset($_GET['action'])) {
                switch ($_GET['action']) {
                    case 'remove_subject':
                        handleRemoveSubject($db, $user);
                        break;
                    case 'delete_section':
                        handleDeleteSection($db, $user);
                        break;
                    default:
                        handleDeleteClass($db, $user);
                }
            }
            else {
                handleDeleteClass($db, $user);
            }
            break;
        default:
            Response::error('Method not allowed', 405);
    }
}
catch (Exception $e) {
    Response::error('Internal server error: ' . $e->getMessage(), 500);
}

function handleGetClasses($db, $user)
{
    $schoolCondition = "";
    $params = [];

    if ($user['role_name'] !== 'super_admin') {
        $schoolCondition = "WHERE c.school_id = :school_id";
        $params[':school_id'] = $user['school_id'];
    }
    else if (isset($_GET['school_id'])) {
        $schoolCondition = "WHERE c.school_id = :school_id";
        $params[':school_id'] = $_GET['school_id'];
    }

    // Add grade level filter
    if (isset($_GET['grade_level'])) {
        $gradeCondition = $schoolCondition ? " AND c.grade_level = :grade_level" : "WHERE c.grade_level = :grade_level";
        $schoolCondition .= $gradeCondition;
        $params[':grade_level'] = $_GET['grade_level'];
    }

    // Add teacher filter
    if (isset($_GET['teacher_id'])) {
        $teacherId = $_GET['teacher_id'];
        $teacherCondition = $schoolCondition ? " AND (c.class_teacher_id = :teacher_id OR EXISTS(SELECT 1 FROM sections s2 WHERE s2.class_id = c.id AND s2.teacher_id = :teacher_id2) OR EXISTS(SELECT 1 FROM class_subjects cs2 WHERE cs2.class_id = c.id AND cs2.teacher_id = :teacher_id3))" : "WHERE (c.class_teacher_id = :teacher_id OR EXISTS(SELECT 1 FROM sections s2 WHERE s2.class_id = c.id AND s2.teacher_id = :teacher_id2) OR EXISTS(SELECT 1 FROM class_subjects cs2 WHERE cs2.class_id = c.id AND cs2.teacher_id = :teacher_id3))";
        $schoolCondition .= $teacherCondition;
        $params[':teacher_id'] = $teacherId;
        $params[':teacher_id2'] = $teacherId;
        $params[':teacher_id3'] = $teacherId;
    }

    $query = "SELECT c.*, 
                     ay.name as academic_year_name, ay.is_current, ay.start_date, ay.end_date,
                     CONCAT(u.first_name, ' ', u.last_name) as class_teacher_name,
                     u.employee_id as teacher_employee_id, u.phone as teacher_phone,
                     s.name as school_name, s.code as school_code,
                     COUNT(DISTINCT se.student_id) as student_count,
                     COUNT(DISTINCT sec.id) as section_count,
                     COUNT(DISTINCT cs.subject_id) as subject_count
              FROM classes c
              LEFT JOIN academic_years ay ON c.academic_year_id = ay.id
              LEFT JOIN users u ON c.class_teacher_id = u.id
              LEFT JOIN schools s ON c.school_id = s.id
              LEFT JOIN student_enrollments se ON c.id = se.class_id AND se.status = 'active'
              LEFT JOIN sections sec ON c.id = sec.class_id
              LEFT JOIN class_subjects cs ON c.id = cs.class_id
              $schoolCondition
              GROUP BY c.id
              ORDER BY c.grade_level ASC, c.name ASC";

    $stmt = $db->prepare($query);
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->execute();

    $classes = $stmt->fetchAll();

    // Get summary statistics
    $statsQuery = "SELECT 
                      COUNT(DISTINCT c.id) as total_classes,
                      COUNT(DISTINCT se.student_id) as total_students,
                      COUNT(DISTINCT sec.id) as total_sections,
                      AVG(c.capacity) as avg_capacity
                   FROM classes c
                   LEFT JOIN student_enrollments se ON c.id = se.class_id AND se.status = 'active'
                   LEFT JOIN sections sec ON c.id = sec.class_id
                   " . str_replace('GROUP BY c.id ORDER BY c.grade_level ASC, c.name ASC', '', $schoolCondition);

    $statsStmt = $db->prepare($statsQuery);
    foreach ($params as $key => $value) {
        $statsStmt->bindValue($key, $value);
    }
    $statsStmt->execute();
    $stats = $statsStmt->fetch();

    Response::success([
        'classes' => $classes,
        'statistics' => $stats
    ], 'Classes retrieved successfully');
}

function handleGetClassDetails($db, $user)
{
    $classId = $_GET['class_id'] ?? null;

    if (!$classId) {
        Response::error('Class ID is required', 400);
    }

    $schoolCondition = "";
    $params = [':class_id' => $classId];

    if ($user['role_name'] !== 'super_admin') {
        $schoolCondition = "AND c.school_id = :school_id";
        $params[':school_id'] = $user['school_id'];
    }

    // Get class details
    $query = "SELECT c.*, 
                     ay.name as academic_year_name, ay.start_date, ay.end_date, ay.is_current,
                     CONCAT(u.first_name, ' ', u.last_name) as class_teacher_name,
                     u.email as teacher_email, u.phone as teacher_phone, u.employee_id,
                     s.name as school_name, s.code as school_code, s.address as school_address
              FROM classes c
              LEFT JOIN academic_years ay ON c.academic_year_id = ay.id
              LEFT JOIN users u ON c.class_teacher_id = u.id
              LEFT JOIN schools s ON c.school_id = s.id
              WHERE c.id = :class_id $schoolCondition";

    $stmt = $db->prepare($query);
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->execute();

    $class = $stmt->fetch();

    if (!$class) {
        Response::error('Class not found', 404);
    }

    // Get sections with detailed info
    $sectionsQuery = "SELECT sec.*, 
                             CONCAT(u.first_name, ' ', u.last_name) as section_teacher_name,
                             u.email as teacher_email, u.phone as teacher_phone,
                             COUNT(se.student_id) as enrolled_students,
                             COUNT(CASE WHEN s.gender = 'male' THEN 1 END) as male_students,
                             COUNT(CASE WHEN s.gender = 'female' THEN 1 END) as female_students
                      FROM sections sec
                      LEFT JOIN users u ON sec.teacher_id = u.id
                      LEFT JOIN student_enrollments se ON sec.id = se.section_id AND se.status = 'active'
                      LEFT JOIN students s ON se.student_id = s.id
                      WHERE sec.class_id = :class_id
                      GROUP BY sec.id
                      ORDER BY sec.name";

    $sectionsStmt = $db->prepare($sectionsQuery);
    $sectionsStmt->bindValue(':class_id', $classId);
    $sectionsStmt->execute();

    $sections = $sectionsStmt->fetchAll();

    // Get subjects with teacher details
    $subjectsQuery = "SELECT sub.*, cs.id as class_subject_id,
                             CONCAT(u.first_name, ' ', u.last_name) as teacher_name,
                             u.email as teacher_email, u.phone as teacher_phone, u.employee_id,
                             COUNT(DISTINCT a.id) as assignment_count
                      FROM class_subjects cs
                      JOIN subjects sub ON cs.subject_id = sub.id
                      LEFT JOIN users u ON cs.teacher_id = u.id
                      LEFT JOIN assignments a ON sub.id = a.subject_id AND a.class_id = :class_id
                      WHERE cs.class_id = :class_id
                      GROUP BY cs.id
                      ORDER BY sub.name";

    $subjectsStmt = $db->prepare($subjectsQuery);
    $subjectsStmt->bindValue(':class_id', $classId);
    $subjectsStmt->execute();

    $subjects = $subjectsStmt->fetchAll();

    // Get recent attendance summary
    $attendanceQuery = "SELECT 
                           DATE(a.date) as attendance_date,
                           COUNT(a.id) as total_marked,
                           COUNT(CASE WHEN a.status = 'present' THEN 1 END) as present_count,
                           COUNT(CASE WHEN a.status = 'absent' THEN 1 END) as absent_count,
                           COUNT(CASE WHEN a.status = 'late' THEN 1 END) as late_count
                        FROM attendance a
                        WHERE a.class_id = :class_id
                        AND a.date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
                        GROUP BY DATE(a.date)
                        ORDER BY a.date DESC";

    $attendanceStmt = $db->prepare($attendanceQuery);
    $attendanceStmt->bindValue(':class_id', $classId);
    $attendanceStmt->execute();

    $recentAttendance = $attendanceStmt->fetchAll();

    // Get recent assignments
    $assignmentsQuery = "SELECT a.*, sub.name as subject_name,
                                CONCAT(u.first_name, ' ', u.last_name) as teacher_name,
                                COUNT(asub.id) as submission_count
                         FROM assignments a
                         JOIN subjects sub ON a.subject_id = sub.id
                         LEFT JOIN users u ON a.teacher_id = u.id
                         LEFT JOIN assignment_submissions asub ON a.id = asub.assignment_id
                         WHERE a.class_id = :class_id
                         GROUP BY a.id
                         ORDER BY a.created_at DESC
                         LIMIT 5";

    $assignmentsStmt = $db->prepare($assignmentsQuery);
    $assignmentsStmt->bindValue(':class_id', $classId);
    $assignmentsStmt->execute();

    $recentAssignments = $assignmentsStmt->fetchAll();

    $class['sections'] = $sections;
    $class['subjects'] = $subjects;
    $class['recent_attendance'] = $recentAttendance;
    $class['recent_assignments'] = $recentAssignments;

    Response::success(['class' => $class], 'Class details retrieved successfully');
}

function handleGetClassStudents($db, $user)
{
    $classId = $_GET['class_id'] ?? null;
    $sectionId = $_GET['section_id'] ?? null;

    if (!$classId) {
        Response::error('Class ID is required', 400);
    }

    $schoolCondition = "";
    $params = [':class_id' => $classId];

    if ($user['role_name'] !== 'super_admin') {
        $schoolCondition = "AND c.school_id = :school_id";
        $params[':school_id'] = $user['school_id'];
    }

    $sectionCondition = "";
    if ($sectionId) {
        $sectionCondition = "AND se.section_id = :section_id";
        $params[':section_id'] = $sectionId;
    }

    $query = "SELECT s.*, se.roll_number, se.enrollment_date, se.status as enrollment_status,
                     sec.name as section_name,
                     c.name as class_name, c.grade_level,
                     CONCAT(s.first_name, ' ', COALESCE(s.middle_name, ''), ' ', s.last_name) as full_name,
                     s.father_name, s.father_phone, s.mother_name, s.mother_phone,
                     -- Recent attendance percentage
                     (SELECT ROUND(
                        (COUNT(CASE WHEN att.status = 'present' THEN 1 END) * 100.0 / COUNT(att.id)), 2
                     ) FROM attendance att 
                      WHERE att.student_id = s.id 
                      AND att.date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)) as attendance_percentage
              FROM students s
              JOIN student_enrollments se ON s.id = se.student_id
              JOIN classes c ON se.class_id = c.id
              JOIN sections sec ON se.section_id = sec.id
              WHERE se.class_id = :class_id 
              AND se.status = 'active'
              $schoolCondition
              $sectionCondition
              ORDER BY sec.name ASC, se.roll_number ASC, s.first_name ASC";

    $stmt = $db->prepare($query);
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->execute();

    $students = $stmt->fetchAll();

    // Get student statistics
    $statsQuery = "SELECT 
                      COUNT(s.id) as total_students,
                      COUNT(CASE WHEN s.gender = 'male' THEN 1 END) as male_count,
                      COUNT(CASE WHEN s.gender = 'female' THEN 1 END) as female_count,
                      COUNT(CASE WHEN s.caste = 'GENERAL' THEN 1 END) as general_count,
                      COUNT(CASE WHEN s.caste = 'OBC' THEN 1 END) as obc_count,
                      COUNT(CASE WHEN s.caste = 'SC' THEN 1 END) as sc_count,
                      COUNT(CASE WHEN s.caste = 'ST' THEN 1 END) as st_count
                   FROM students s
                   JOIN student_enrollments se ON s.id = se.student_id
                   JOIN classes c ON se.class_id = c.id
                   WHERE se.class_id = :class_id 
                   AND se.status = 'active'
                   $schoolCondition
                   $sectionCondition";

    $statsStmt = $db->prepare($statsQuery);
    foreach ($params as $key => $value) {
        $statsStmt->bindValue($key, $value);
    }
    $statsStmt->execute();

    $statistics = $statsStmt->fetch();

    Response::success([
        'students' => $students,
        'statistics' => $statistics
    ], 'Class students retrieved successfully');
}

function handleGetClassSubjects($db, $user)
{
    $classId = $_GET['class_id'] ?? null;

    if (!$classId) {
        Response::error('Class ID is required', 400);
    }

    $schoolCondition = "";
    $params = [':class_id' => $classId];

    if ($user['role_name'] !== 'super_admin') {
        $schoolCondition = "AND c.school_id = :school_id";
        $params[':school_id'] = $user['school_id'];
    }

    $query = "SELECT sub.*, cs.id as class_subject_id,
                     CONCAT(u.first_name, ' ', u.last_name) as teacher_name,
                     u.email as teacher_email, u.phone as teacher_phone, u.employee_id,
                     c.name as class_name, c.grade_level,
                     COUNT(DISTINCT a.id) as assignment_count,
                     COUNT(DISTINCT es.id) as exam_count
              FROM class_subjects cs
              JOIN subjects sub ON cs.subject_id = sub.id
              JOIN classes c ON cs.class_id = c.id
              LEFT JOIN users u ON cs.teacher_id = u.id
              LEFT JOIN assignments a ON sub.id = a.subject_id AND a.class_id = c.id
              LEFT JOIN exam_subjects es ON sub.id = es.subject_id AND es.class_id = c.id
              WHERE cs.class_id = :class_id
              $schoolCondition
              GROUP BY cs.id
              ORDER BY sub.name";

    $stmt = $db->prepare($query);
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->execute();

    $subjects = $stmt->fetchAll();

    Response::success(['subjects' => $subjects], 'Class subjects retrieved successfully');
}

function handleGetClassSections($db, $user)
{
    $classId = $_GET['class_id'] ?? null;

    if (!$classId) {
        Response::error('Class ID is required', 400);
    }

    $schoolCondition = "";
    $params = [':class_id' => $classId];

    if ($user['role_name'] !== 'super_admin') {
        $schoolCondition = "AND c.school_id = :school_id";
        $params[':school_id'] = $user['school_id'];
    }

    $query = "SELECT sec.*, 
                     CONCAT(u.first_name, ' ', u.last_name) as teacher_name,
                     u.email as teacher_email, u.phone as teacher_phone,
                     COUNT(se.student_id) as enrolled_students,
                     c.name as class_name, c.grade_level
              FROM sections sec
              JOIN classes c ON sec.class_id = c.id
              LEFT JOIN users u ON sec.teacher_id = u.id
              LEFT JOIN student_enrollments se ON sec.id = se.section_id AND se.status = 'active'
              WHERE sec.class_id = :class_id
              $schoolCondition
              GROUP BY sec.id
              ORDER BY sec.name";

    $stmt = $db->prepare($query);
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->execute();

    $sections = $stmt->fetchAll();

    Response::success(['sections' => $sections], 'Class sections retrieved successfully');
}

function handleCreateClass($db, $user)
{
    $input = json_decode(file_get_contents('php://input'), true);

    $required = ['name', 'grade_level', 'academic_year_id'];
    foreach ($required as $field) {
        if (!isset($input[$field]) || empty($input[$field])) {
            Response::error("Field '$field' is required", 400);
        }
    }

    $schoolId = $user['role_name'] === 'super_admin' ? ($input['school_id'] ?? null) : $user['school_id'];

    if (!$schoolId) {
        Response::error('School ID is required', 400);
    }

    // Validate academic year belongs to school
    $ayQuery = "SELECT id FROM academic_years WHERE id = :academic_year_id AND school_id = :school_id";
    $ayStmt = $db->prepare($ayQuery);
    $ayStmt->bindValue(':academic_year_id', $input['academic_year_id']);
    $ayStmt->bindValue(':school_id', $schoolId);
    $ayStmt->execute();

    if (!$ayStmt->fetch()) {
        Response::error('Invalid academic year for this school', 400);
    }

    // Validate class teacher if provided
    if (isset($input['class_teacher_id']) && !empty($input['class_teacher_id'])) {
        $teacherQuery = "SELECT id FROM users WHERE id = :teacher_id AND school_id = :school_id AND role_id IN (3, 4, 5, 6)";
        $teacherStmt = $db->prepare($teacherQuery);
        $teacherStmt->bindValue(':teacher_id', $input['class_teacher_id']);
        $teacherStmt->bindValue(':school_id', $schoolId);
        $teacherStmt->execute();

        if (!$teacherStmt->fetch()) {
            Response::error('Invalid teacher for this school', 400);
        }
    }

    $db->beginTransaction();

    try {
        // Check for duplicate class name in same academic year
        $duplicateQuery = "SELECT id FROM classes WHERE school_id = :school_id AND name = :name AND academic_year_id = :academic_year_id";
        $duplicateStmt = $db->prepare($duplicateQuery);
        $duplicateStmt->bindValue(':school_id', $schoolId);
        $duplicateStmt->bindValue(':name', $input['name']);
        $duplicateStmt->bindValue(':academic_year_id', $input['academic_year_id']);
        $duplicateStmt->execute();

        if ($duplicateStmt->fetch()) {
            Response::error('Class with this name already exists in the academic year', 400);
        }

        // Insert class
        $query = "INSERT INTO classes (school_id, name, grade_level, academic_year_id, class_teacher_id, room_number, capacity) 
                 VALUES (:school_id, :name, :grade_level, :academic_year_id, :class_teacher_id, :room_number, :capacity)";

        $stmt = $db->prepare($query);
        $stmt->bindValue(':school_id', $schoolId);
        $stmt->bindValue(':name', $input['name']);
        $stmt->bindValue(':grade_level', $input['grade_level']);
        $stmt->bindValue(':academic_year_id', $input['academic_year_id']);
        $stmt->bindValue(':class_teacher_id', $input['class_teacher_id']);
        $stmt->bindValue(':room_number', $input['room_number']);
        $stmt->bindValue(':capacity', $input['capacity']);

        $stmt->execute();
        $classId = $db->lastInsertId();

        // Create default sections if provided
        if (isset($input['sections']) && is_array($input['sections'])) {
            $sectionQuery = "INSERT INTO sections (class_id, name, teacher_id, capacity) VALUES (:class_id, :name, :teacher_id, :capacity)";
            $sectionStmt = $db->prepare($sectionQuery);

            foreach ($input['sections'] as $section) {
                $sectionStmt->bindValue(':class_id', $classId);
                $sectionStmt->bindValue(':name', $section['name']);
                $sectionStmt->bindValue(':teacher_id', $section['teacher_id']);
                $sectionStmt->bindValue(':capacity', $section['capacity']);
                $sectionStmt->execute();
            }
        }
        else {
            // Create default section 'A' with the provided class_teacher_id (if any)
            $defaultSectionQuery = "INSERT INTO sections (class_id, name, teacher_id, capacity) VALUES (:class_id, 'A', :teacher_id, :capacity)";
            $defaultSectionStmt = $db->prepare($defaultSectionQuery);
            $defaultSectionStmt->bindValue(':class_id', $classId);
            $defaultSectionStmt->bindValue(':teacher_id', $input['class_teacher_id'] ?? null);
            $defaultSectionStmt->bindValue(':capacity', $input['capacity']);
            $defaultSectionStmt->execute();
        }

        // Log activity
        $logQuery = "INSERT INTO activity_logs (user_id, user_type, school_id, action, entity_type, entity_id, ip_address) 
                     VALUES (:user_id, 'user', :school_id, 'create_class', 'class', :entity_id, :ip_address)";
        $logStmt = $db->prepare($logQuery);
        $logStmt->bindValue(':user_id', $user['id']);
        $logStmt->bindValue(':school_id', $schoolId);
        $logStmt->bindValue(':entity_id', $classId);
        $logStmt->bindValue(':ip_address', $_SERVER['REMOTE_ADDR']);
        $logStmt->execute();

        $db->commit();

        Response::success(['class_id' => $classId], 'Class created successfully');

    }
    catch (Exception $e) {
        $db->rollBack();
        throw $e;
    }
}

function handleCreateSection($db, $user)
{
    $input = json_decode(file_get_contents('php://input'), true);

    $required = ['class_id', 'name', 'teacher_id'];
    foreach ($required as $field) {
        if (!isset($input[$field]) || empty($input[$field])) {
            Response::error("Field '$field' is required", 400);
        }
    }

    // Validate class belongs to user's school
    $schoolCondition = "";
    $params = [':class_id' => $input['class_id']];

    if ($user['role_name'] !== 'super_admin') {
        $schoolCondition = "AND school_id = :school_id";
        $params[':school_id'] = $user['school_id'];
    }

    $classQuery = "SELECT id FROM classes WHERE id = :class_id $schoolCondition";
    $classStmt = $db->prepare($classQuery);
    foreach ($params as $key => $value) {
        $classStmt->bindValue($key, $value);
    }
    $classStmt->execute();

    if (!$classStmt->fetch()) {
        Response::error('Class not found or access denied', 404);
    }

    // Check for duplicate section name
    $duplicateQuery = "SELECT id FROM sections WHERE class_id = :class_id AND name = :name";
    $duplicateStmt = $db->prepare($duplicateQuery);
    $duplicateStmt->bindValue(':class_id', $input['class_id']);
    $duplicateStmt->bindValue(':name', $input['name']);
    $duplicateStmt->execute();

    if ($duplicateStmt->fetch()) {
        Response::error('Section with this name already exists in the class', 400);
    }

    // Insert section
    $query = "INSERT INTO sections (class_id, name, teacher_id, capacity) 
             VALUES (:class_id, :name, :teacher_id, :capacity)";

    $stmt = $db->prepare($query);
    $stmt->bindValue(':class_id', $input['class_id']);
    $stmt->bindValue(':name', $input['name']);
    $stmt->bindValue(':teacher_id', $input['teacher_id']);
    $stmt->bindValue(':capacity', $input['capacity']);

    $stmt->execute();
    $sectionId = $db->lastInsertId();

    Response::success(['section_id' => $sectionId], 'Section created successfully');
}

function handleAssignSubject($db, $user)
{
    $input = json_decode(file_get_contents('php://input'), true);

    $required = ['class_id', 'subject_id', 'teacher_id'];
    foreach ($required as $field) {
        if (!isset($input[$field]) || empty($input[$field])) {
            Response::error("Field '$field' is required", 400);
        }
    }

    // Validate all entities belong to user's school
    $schoolCondition = "";
    $params = [
        ':class_id' => $input['class_id'],
        ':subject_id' => $input['subject_id'],
        ':teacher_id' => $input['teacher_id']
    ];

    if ($user['role_name'] !== 'super_admin') {
        $schoolCondition = "AND c.school_id = :school_id AND s.school_id = :school_id2 AND u.school_id = :school_id3";
        $params[':school_id'] = $user['school_id'];
        $params[':school_id2'] = $user['school_id'];
        $params[':school_id3'] = $user['school_id'];
    }

    $validateQuery = "SELECT c.id as class_id, s.id as subject_id, u.id as teacher_id
                     FROM classes c, subjects s, users u
                     WHERE c.id = :class_id AND s.id = :subject_id AND u.id = :teacher_id
                     $schoolCondition";

    $validateStmt = $db->prepare($validateQuery);
    foreach ($params as $key => $value) {
        $validateStmt->bindValue($key, $value);
    }
    $validateStmt->execute();

    if (!$validateStmt->fetch()) {
        Response::error('Invalid class, subject, or teacher', 400);
    }

    // Check if assignment already exists
    $existsQuery = "SELECT id FROM class_subjects WHERE class_id = :class_id AND subject_id = :subject_id";
    $existsStmt = $db->prepare($existsQuery);
    $existsStmt->bindValue(':class_id', $input['class_id']);
    $existsStmt->bindValue(':subject_id', $input['subject_id']);
    $existsStmt->execute();

    if ($existsStmt->fetch()) {
        Response::error('Subject already assigned to this class', 400);
    }

    // Insert assignment
    $query = "INSERT INTO class_subjects (class_id, subject_id, teacher_id) 
             VALUES (:class_id, :subject_id, :teacher_id)";

    $stmt = $db->prepare($query);
    $stmt->bindValue(':class_id', $input['class_id']);
    $stmt->bindValue(':subject_id', $input['subject_id']);
    $stmt->bindValue(':teacher_id', $input['teacher_id']);

    $stmt->execute();
    $assignmentId = $db->lastInsertId();

    Response::success(['assignment_id' => $assignmentId], 'Subject assigned successfully');
}

function handleUpdateClass($db, $user)
{
    $input = json_decode(file_get_contents('php://input'), true);
    $classId = $_GET['class_id'] ?? $input['class_id'] ?? null;

    if (!$classId) {
        Response::error('Class ID is required', 400);
    }

    // Validate class belongs to user's school
    $schoolCondition = "";
    $params = [':class_id' => $classId];

    if ($user['role_name'] !== 'super_admin') {
        $schoolCondition = "AND school_id = :school_id";
        $params[':school_id'] = $user['school_id'];
    }

    $classQuery = "SELECT * FROM classes WHERE id = :class_id $schoolCondition";
    $classStmt = $db->prepare($classQuery);
    foreach ($params as $key => $value) {
        $classStmt->bindValue($key, $value);
    }
    $classStmt->execute();

    $existingClass = $classStmt->fetch();
    if (!$existingClass) {
        Response::error('Class not found or access denied', 404);
    }

    // Build update query dynamically
    $updateFields = [];
    $updateParams = [':class_id' => $classId];

    $allowedFields = ['name', 'grade_level', 'class_teacher_id', 'room_number', 'capacity'];

    foreach ($allowedFields as $field) {
        if (isset($input[$field])) {
            $updateFields[] = "$field = :$field";
            $updateParams[":$field"] = $input[$field];
        }
    }

    if (empty($updateFields)) {
        Response::error('No valid fields to update', 400);
    }

    $updateQuery = "UPDATE classes SET " . implode(', ', $updateFields) . " WHERE id = :class_id";

    $updateStmt = $db->prepare($updateQuery);
    foreach ($updateParams as $key => $value) {
        $updateStmt->bindValue($key, $value);
    }

    $updateStmt->execute();

    Response::success(['class_id' => $classId], 'Class updated successfully');
}

function handleDeleteClass($db, $user)
{
    $classId = $_GET['class_id'] ?? null;

    if (!$classId) {
        Response::error('Class ID is required', 400);
    }

    // Validate class belongs to user's school
    $schoolCondition = "";
    $params = [':class_id' => $classId];

    if ($user['role_name'] !== 'super_admin') {
        $schoolCondition = "AND school_id = :school_id";
        $params[':school_id'] = $user['school_id'];
    }

    $classQuery = "SELECT id FROM classes WHERE id = :class_id $schoolCondition";
    $classStmt = $db->prepare($classQuery);
    foreach ($params as $key => $value) {
        $classStmt->bindValue($key, $value);
    }
    $classStmt->execute();

    if (!$classStmt->fetch()) {
        Response::error('Class not found or access denied', 404);
    }

    // Check if class has active enrollments
    $enrollmentQuery = "SELECT COUNT(*) as count FROM student_enrollments WHERE class_id = :class_id AND status = 'active'";
    $enrollmentStmt = $db->prepare($enrollmentQuery);
    $enrollmentStmt->bindValue(':class_id', $classId);
    $enrollmentStmt->execute();

    $enrollmentCount = $enrollmentStmt->fetch()['count'];

    if ($enrollmentCount > 0) {
        Response::error('Cannot delete class with active student enrollments', 400);
    }

    $db->beginTransaction();

    try {
        // Delete related records
        $deleteQueries = [
            "DELETE FROM class_subjects WHERE class_id = :class_id",
            "DELETE FROM sections WHERE class_id = :class_id",
            "DELETE FROM timetables WHERE class_id = :class_id",
            "DELETE FROM assignments WHERE class_id = :class_id",
            "DELETE FROM classes WHERE id = :class_id"
        ];

        foreach ($deleteQueries as $query) {
            $stmt = $db->prepare($query);
            $stmt->bindValue(':class_id', $classId);
            $stmt->execute();
        }

        $db->commit();

        Response::success(null, 'Class deleted successfully');

    }
    catch (Exception $e) {
        $db->rollBack();
        throw $e;
    }
}

function handleRemoveSubject($db, $user)
{
    $classId = $_GET['class_id'] ?? null;
    $subjectId = $_GET['subject_id'] ?? null;

    if (!$classId || !$subjectId) {
        Response::error('Class ID and Subject ID are required', 400);
    }

    // Validate assignment exists and belongs to user's school
    $schoolCondition = "";
    $params = [':class_id' => $classId, ':subject_id' => $subjectId];

    if ($user['role_name'] !== 'super_admin') {
        $schoolCondition = "AND c.school_id = :school_id";
        $params[':school_id'] = $user['school_id'];
    }

    $validateQuery = "SELECT cs.id FROM class_subjects cs 
                     JOIN classes c ON cs.class_id = c.id 
                     WHERE cs.class_id = :class_id AND cs.subject_id = :subject_id $schoolCondition";

    $validateStmt = $db->prepare($validateQuery);
    foreach ($params as $key => $value) {
        $validateStmt->bindValue($key, $value);
    }
    $validateStmt->execute();

    if (!$validateStmt->fetch()) {
        Response::error('Subject assignment not found or access denied', 404);
    }

    // Delete assignment
    $deleteQuery = "DELETE FROM class_subjects WHERE class_id = :class_id AND subject_id = :subject_id";
    $deleteStmt = $db->prepare($deleteQuery);
    $deleteStmt->bindValue(':class_id', $classId);
    $deleteStmt->bindValue(':subject_id', $subjectId);
    $deleteStmt->execute();

    Response::success(null, 'Subject removed from class successfully');
}

function handleDeleteSection($db, $user)
{
    $sectionId = $_GET['section_id'] ?? null;

    if (!$sectionId) {
        Response::error('Section ID is required', 400);
    }

    // Validate section belongs to user's school
    $schoolCondition = "";
    $params = [':section_id' => $sectionId];

    if ($user['role_name'] !== 'super_admin') {
        $schoolCondition = "AND c.school_id = :school_id";
        $params[':school_id'] = $user['school_id'];
    }

    $sectionQuery = "SELECT s.id FROM sections s 
                    JOIN classes c ON s.class_id = c.id 
                    WHERE s.id = :section_id $schoolCondition";

    $sectionStmt = $db->prepare($sectionQuery);
    foreach ($params as $key => $value) {
        $sectionStmt->bindValue($key, $value);
    }
    $sectionStmt->execute();

    if (!$sectionStmt->fetch()) {
        Response::error('Section not found or access denied', 404);
    }

    // Check if section has active enrollments
    $enrollmentQuery = "SELECT COUNT(*) as count FROM student_enrollments WHERE section_id = :section_id AND status = 'active'";
    $enrollmentStmt = $db->prepare($enrollmentQuery);
    $enrollmentStmt->bindValue(':section_id', $sectionId);
    $enrollmentStmt->execute();

    $enrollmentCount = $enrollmentStmt->fetch()['count'];

    if ($enrollmentCount > 0) {
        Response::error('Cannot delete section with active student enrollments', 400);
    }

    // Delete section
    $deleteQuery = "DELETE FROM sections WHERE id = :section_id";
    $deleteStmt = $db->prepare($deleteQuery);
    $deleteStmt->bindValue(':section_id', $sectionId);
    $deleteStmt->execute();

    Response::success(null, 'Section deleted successfully');
}

function handleUpdateSection($db, $user)
{
    $input = json_decode(file_get_contents('php://input'), true);

    if (!isset($input['section_id'])) {
        Response::error('Section ID is required', 400);
    }

    $sectionId = $input['section_id'];

    // Validate section exists and belongs to user's school
    $schoolCondition = "";
    $params = [':section_id' => $sectionId];

    if ($user['role_name'] !== 'super_admin') {
        $schoolCondition = "AND c.school_id = :school_id";
        $params[':school_id'] = $user['school_id'];
    }

    $sectionQuery = "SELECT s.* FROM sections s 
                    JOIN classes c ON s.class_id = c.id 
                    WHERE s.id = :section_id $schoolCondition";

    $sectionStmt = $db->prepare($sectionQuery);
    foreach ($params as $key => $value) {
        $sectionStmt->bindValue($key, $value);
    }
    $sectionStmt->execute();

    $existingSection = $sectionStmt->fetch();
    if (!$existingSection) {
        Response::error('Section not found or access denied', 404);
    }

    // Build update query dynamically
    $updateFields = [];
    $updateParams = [':section_id' => $sectionId];

    $allowedFields = ['name', 'teacher_id', 'capacity', 'class_id'];

    foreach ($allowedFields as $field) {
        if (isset($input[$field])) {
            $updateFields[] = "$field = :$field";
            $updateParams[":$field"] = $input[$field];
        }
    }

    if (empty($updateFields)) {
        Response::error('No valid fields to update', 400);
    }

    $updateQuery = "UPDATE sections SET " . implode(', ', $updateFields) . " WHERE id = :section_id";

    $updateStmt = $db->prepare($updateQuery);
    foreach ($updateParams as $key => $value) {
        $updateStmt->bindValue($key, $value);
    }

    $updateStmt->execute();

    // Log activity
    $logQuery = "INSERT INTO activity_logs (user_id, user_type, school_id, action, entity_type, entity_id, ip_address) 
                 VALUES (:user_id, 'user', :school_id, 'update_section', 'section', :entity_id, :ip_address)";
    $logStmt = $db->prepare($logQuery);
    $logStmt->bindValue(':user_id', $user['id']);
    $logStmt->bindValue(':school_id', $user['school_id']);
    $logStmt->bindValue(':entity_id', $sectionId);
    $logStmt->bindValue(':ip_address', $_SERVER['REMOTE_ADDR']);
    $logStmt->execute();

    Response::success(['section_id' => $sectionId], 'Section updated successfully');
}
?>
