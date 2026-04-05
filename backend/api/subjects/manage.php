<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE');
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
    switch ($_SERVER['REQUEST_METHOD']) {
        case 'GET':
            if (isset($_GET['action'])) {
                switch ($_GET['action']) {
                    case 'list':
                        handleGetSubjects($db, $user);
                        break;
                    case 'details':
                        handleGetSubjectDetails($db, $user);
                        break;
                    case 'assignments':
                        handleGetSubjectAssignments($db, $user);
                        break;
                    case 'teachers':
                        handleGetAvailableTeachers($db, $user);
                        break;
                    default:
                        handleGetSubjects($db, $user);
                }
            }
            else {
                handleGetSubjects($db, $user);
            }
            break;
        case 'POST':
            handleCreateSubject($db, $user);
            break;
        case 'PUT':
            handleUpdateSubject($db, $user);
            break;
        case 'DELETE':
            handleDeleteSubject($db, $user);
            break;
        default:
            Response::error('Method not allowed', 405);
    }
}
catch (Exception $e) {
    Response::error('Internal server error: ' . $e->getMessage(), 500);
}

function handleGetSubjects($db, $user)
{
    $schoolCondition = "";
    $params = [];

    if ($user['role_name'] !== 'super_admin') {
        $schoolCondition = "WHERE s.school_id = :school_id";
        $params[':school_id'] = $user['school_id'];
    }
    else if (isset($_GET['school_id'])) {
        $schoolCondition = "WHERE s.school_id = :school_id";
        $params[':school_id'] = $_GET['school_id'];
    }

    // Add grade level filter
    if (isset($_GET['grade_level'])) {
        $gradeCondition = $schoolCondition ? " AND EXISTS (SELECT 1 FROM class_subjects cs JOIN classes c ON cs.class_id = c.id WHERE cs.subject_id = s.id AND c.grade_level = :grade_level)"
            : "WHERE EXISTS (SELECT 1 FROM class_subjects cs JOIN classes c ON cs.class_id = c.id WHERE cs.subject_id = s.id AND c.grade_level = :grade_level)";
        $schoolCondition .= $gradeCondition;
        $params[':grade_level'] = $_GET['grade_level'];
    }

    $query = "SELECT s.*, 
                     sc.name as school_name, sc.code as school_code,
                     COUNT(DISTINCT cs.class_id) as assigned_classes,
                     COUNT(DISTINCT cs.teacher_id) as assigned_teachers,
                     COUNT(DISTINCT a.id) as total_assignments,
                     COUNT(DISTINCT es.id) as total_exams
              FROM subjects s
              LEFT JOIN schools sc ON s.school_id = sc.id
              LEFT JOIN class_subjects cs ON s.id = cs.subject_id
              LEFT JOIN assignments a ON s.id = a.subject_id
              LEFT JOIN exam_subjects es ON s.id = es.subject_id
              $schoolCondition
              GROUP BY s.id
              ORDER BY s.name ASC";

    $stmt = $db->prepare($query);
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->execute();

    $subjects = $stmt->fetchAll();

    // Get summary statistics
    $statsQuery = "SELECT 
                      COUNT(DISTINCT s.id) as total_subjects,
                      COUNT(DISTINCT cs.id) as total_assignments,
                      COUNT(DISTINCT cs.teacher_id) as unique_teachers
                   FROM subjects s
                   LEFT JOIN class_subjects cs ON s.id = cs.subject_id
                   " . str_replace('GROUP BY s.id ORDER BY s.name ASC', '', $schoolCondition);

    $statsStmt = $db->prepare($statsQuery);
    foreach ($params as $key => $value) {
        if ($key !== ':grade_level') {
            $statsStmt->bindValue($key, $value);
        }
    }
    $statsStmt->execute();
    $stats = $statsStmt->fetch();

    Response::success([
        'subjects' => $subjects,
        'statistics' => $stats
    ], 'Subjects retrieved successfully');
}

function handleGetSubjectDetails($db, $user)
{
    $subjectId = $_GET['subject_id'] ?? null;

    if (!$subjectId) {
        Response::error('Subject ID is required', 400);
    }

    $schoolCondition = "";
    $params = [':subject_id' => $subjectId];

    if ($user['role_name'] !== 'super_admin') {
        $schoolCondition = "AND s.school_id = :school_id";
        $params[':school_id'] = $user['school_id'];
    }

    // Get subject details
    $query = "SELECT s.*, 
                     sc.name as school_name, sc.code as school_code, sc.address as school_address
              FROM subjects s
              JOIN schools sc ON s.school_id = sc.id
              WHERE s.id = :subject_id $schoolCondition";

    $stmt = $db->prepare($query);
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->execute();

    $subject = $stmt->fetch();

    if (!$subject) {
        Response::error('Subject not found', 404);
    }

    // Get class assignments with teacher details
    $assignmentsQuery = "SELECT cs.id as assignment_id, cs.class_id, cs.teacher_id,
                                c.name as class_name, c.grade_level, c.room_number,
                                sec.name as section_name,
                                CONCAT(u.first_name, ' ', u.last_name) as teacher_name,
                                u.email as teacher_email, u.phone as teacher_phone, u.employee_id,
                                COUNT(DISTINCT se.student_id) as student_count
                         FROM class_subjects cs
                         JOIN classes c ON cs.class_id = c.id
                         LEFT JOIN sections sec ON c.id = sec.class_id
                         LEFT JOIN users u ON cs.teacher_id = u.id
                         LEFT JOIN student_enrollments se ON c.id = se.class_id AND se.status = 'active'
                         WHERE cs.subject_id = :subject_id
                         GROUP BY cs.id, sec.id
                         ORDER BY c.grade_level ASC, c.name ASC, sec.name ASC";

    $assignmentsStmt = $db->prepare($assignmentsQuery);
    $assignmentsStmt->bindValue(':subject_id', $subjectId);
    $assignmentsStmt->execute();

    $classAssignments = $assignmentsStmt->fetchAll();

    // Get recent assignments
    $recentAssignmentsQuery = "SELECT a.*, c.name as class_name, sec.name as section_name,
                                      CONCAT(u.first_name, ' ', u.last_name) as teacher_name,
                                      COUNT(asub.id) as submission_count
                               FROM assignments a
                               JOIN classes c ON a.class_id = c.id
                               LEFT JOIN sections sec ON a.section_id = sec.id
                               LEFT JOIN users u ON a.teacher_id = u.id
                               LEFT JOIN assignment_submissions asub ON a.id = asub.assignment_id
                               WHERE a.subject_id = :subject_id
                               GROUP BY a.id
                               ORDER BY a.created_at DESC
                               LIMIT 10";

    $recentAssignmentsStmt = $db->prepare($recentAssignmentsQuery);
    $recentAssignmentsStmt->bindValue(':subject_id', $subjectId);
    $recentAssignmentsStmt->execute();

    $recentAssignments = $recentAssignmentsStmt->fetchAll();

    // Get exam subjects
    $examSubjectsQuery = "SELECT es.*, e.name as exam_name, e.type as exam_type, e.start_date, e.end_date,
                                 c.name as class_name, c.grade_level,
                                 COUNT(er.id) as result_count
                          FROM exam_subjects es
                          JOIN exams e ON es.exam_id = e.id
                          JOIN classes c ON es.class_id = c.id
                          LEFT JOIN exam_results er ON es.id = er.exam_subject_id
                          WHERE es.subject_id = :subject_id
                          GROUP BY es.id
                          ORDER BY e.start_date DESC";

    $examSubjectsStmt = $db->prepare($examSubjectsQuery);
    $examSubjectsStmt->bindValue(':subject_id', $subjectId);
    $examSubjectsStmt->execute();

    $examSubjects = $examSubjectsStmt->fetchAll();

    // Get timetable entries
    $timetableQuery = "SELECT t.*, c.name as class_name, sec.name as section_name,
                              CONCAT(u.first_name, ' ', u.last_name) as teacher_name,
                              ay.name as academic_year_name
                       FROM timetables t
                       JOIN classes c ON t.class_id = c.id
                       LEFT JOIN sections sec ON t.section_id = sec.id
                       LEFT JOIN users u ON t.teacher_id = u.id
                       LEFT JOIN academic_years ay ON t.academic_year_id = ay.id
                       WHERE t.subject_id = :subject_id
                       ORDER BY t.day_of_week, t.start_time";

    $timetableStmt = $db->prepare($timetableQuery);
    $timetableStmt->bindValue(':subject_id', $subjectId);
    $timetableStmt->execute();

    $timetable = $timetableStmt->fetchAll();

    $subject['class_assignments'] = $classAssignments;
    $subject['recent_assignments'] = $recentAssignments;
    $subject['exam_subjects'] = $examSubjects;
    $subject['timetable'] = $timetable;

    Response::success(['subject' => $subject], 'Subject details retrieved successfully');
}

function handleGetSubjectAssignments($db, $user)
{
    $subjectId = $_GET['subject_id'] ?? null;

    if (!$subjectId) {
        Response::error('Subject ID is required', 400);
    }

    $schoolCondition = "";
    $params = [':subject_id' => $subjectId];

    if ($user['role_name'] !== 'super_admin') {
        $schoolCondition = "AND s.school_id = :school_id";
        $params[':school_id'] = $user['school_id'];
    }

    // Validate subject belongs to user's school
    $subjectQuery = "SELECT id FROM subjects s WHERE id = :subject_id $schoolCondition";
    $subjectStmt = $db->prepare($subjectQuery);
    foreach ($params as $key => $value) {
        $subjectStmt->bindValue($key, $value);
    }
    $subjectStmt->execute();

    if (!$subjectStmt->fetch()) {
        Response::error('Subject not found or access denied', 404);
    }

    $query = "SELECT cs.id as assignment_id, cs.class_id, cs.teacher_id,
                     c.name as class_name, c.grade_level, c.capacity,
                     CONCAT(u.first_name, ' ', u.last_name) as teacher_name,
                     u.email as teacher_email, u.phone as teacher_phone, u.employee_id,
                     COUNT(DISTINCT se.student_id) as enrolled_students,
                     COUNT(DISTINCT a.id) as assignment_count,
                     COUNT(DISTINCT es.id) as exam_count
              FROM class_subjects cs
              JOIN classes c ON cs.class_id = c.id
              LEFT JOIN users u ON cs.teacher_id = u.id
              LEFT JOIN student_enrollments se ON c.id = se.class_id AND se.status = 'active'
              LEFT JOIN assignments a ON cs.subject_id = a.subject_id AND cs.class_id = a.class_id
              LEFT JOIN exam_subjects es ON cs.subject_id = es.subject_id AND cs.class_id = es.class_id
              WHERE cs.subject_id = :subject_id
              GROUP BY cs.id
              ORDER BY c.grade_level ASC, c.name ASC";

    $stmt = $db->prepare($query);
    $stmt->bindValue(':subject_id', $subjectId);
    $stmt->execute();

    $assignments = $stmt->fetchAll();

    Response::success(['assignments' => $assignments], 'Subject assignments retrieved successfully');
}

function handleGetAvailableTeachers($db, $user)
{
    $schoolCondition = "";
    $params = [];

    if ($user['role_name'] !== 'super_admin') {
        $schoolCondition = "WHERE u.school_id = :school_id";
        $params[':school_id'] = $user['school_id'];
    }
    else if (isset($_GET['school_id'])) {
        $schoolCondition = "WHERE u.school_id = :school_id";
        $params[':school_id'] = $_GET['school_id'];
    }

    $teacherCondition = $schoolCondition ? " AND u.role_id IN (3, 4, 5, 6) AND u.is_active = 1" : "WHERE u.role_id IN (3, 4, 5, 6) AND u.is_active = 1";
    $schoolCondition .= $teacherCondition;

    $query = "SELECT u.id, u.employee_id, u.first_name, u.last_name, u.email, u.phone,
                     u.qualification, u.experience_years, u.teacher_type,
                     CONCAT(u.first_name, ' ', u.last_name) as full_name,
                     r.name as role_name,
                     COUNT(DISTINCT cs.id) as subject_assignments,
                     COUNT(DISTINCT c.id) as class_teacher_assignments
              FROM users u
              JOIN user_roles r ON u.role_id = r.id
              LEFT JOIN class_subjects cs ON u.id = cs.teacher_id
              LEFT JOIN classes c ON u.id = c.class_teacher_id
              $schoolCondition
              GROUP BY u.id
              ORDER BY u.first_name ASC, u.last_name ASC";

    $stmt = $db->prepare($query);
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->execute();

    $teachers = $stmt->fetchAll();

    Response::success(['teachers' => $teachers], 'Available teachers retrieved successfully');
}

function handleCreateSubject($db, $user)
{
    $input = json_decode(file_get_contents('php://input'), true);

    $required = ['name', 'code'];
    foreach ($required as $field) {
        if (!isset($input[$field]) || empty($input[$field])) {
            Response::error("Field '$field' is required", 400);
        }
    }

    $schoolId = $user['role_name'] === 'super_admin' ? ($input['school_id']) : $user['school_id'];

    if (!$schoolId) {
        Response::error('School ID is required', 400);
    }

    // Check for duplicate subject code in the same school
    $duplicateQuery = "SELECT id FROM subjects WHERE school_id = :school_id AND code = :code";
    $duplicateStmt = $db->prepare($duplicateQuery);
    $duplicateStmt->bindValue(':school_id', $schoolId);
    $duplicateStmt->bindValue(':code', $input['code']);
    $duplicateStmt->execute();

    if ($duplicateStmt->fetch()) {
        Response::error('Subject with this code already exists in the school', 400);
    }

    $db->beginTransaction();

    try {
        // Insert subject
        $query = "INSERT INTO subjects (school_id, name, code, description, is_active) 
                 VALUES (:school_id, :name, :code, :description, :is_active)";

        $stmt = $db->prepare($query);
        $stmt->bindValue(':school_id', $schoolId);
        $stmt->bindValue(':name', $input['name']);
        $stmt->bindValue(':code', $input['code']);
        $stmt->bindValue(':description', $input['description']);
        $stmt->bindValue(':is_active', $input['is_active']);

        $stmt->execute();
        $subjectId = $db->lastInsertId();

        // Auto-assign to classes if provided
        if (isset($input['class_assignments']) && is_array($input['class_assignments'])) {
            $assignQuery = "INSERT INTO class_subjects (class_id, subject_id, teacher_id) VALUES (:class_id, :subject_id, :teacher_id)";
            $assignStmt = $db->prepare($assignQuery);

            foreach ($input['class_assignments'] as $assignment) {
                if (isset($assignment['class_id'])) {
                    $assignStmt->bindValue(':class_id', $assignment['class_id']);
                    $assignStmt->bindValue(':subject_id', $subjectId);
                    $assignStmt->bindValue(':teacher_id', $assignment['teacher_id']);
                    $assignStmt->execute();
                }
            }
        }

        // Log activity
        $logQuery = "INSERT INTO activity_logs (user_id, user_type, school_id, action, entity_type, entity_id, ip_address) 
                     VALUES (:user_id, 'user', :school_id, 'create_subject', 'subject', :entity_id, :ip_address)";
        $logStmt = $db->prepare($logQuery);
        $logStmt->bindValue(':user_id', $user['id']);
        $logStmt->bindValue(':school_id', $schoolId);
        $logStmt->bindValue(':entity_id', $subjectId);
        $logStmt->bindValue(':ip_address', $_SERVER['REMOTE_ADDR']);
        $logStmt->execute();

        $db->commit();

        Response::success(['subject_id' => $subjectId], 'Subject created successfully');

    }
    catch (Exception $e) {
        $db->rollBack();
        throw $e;
    }
}

function handleUpdateSubject($db, $user)
{
    $input = json_decode(file_get_contents('php://input'), true);
    $subjectId = $_GET['subject_id'] ?? $input['subject_id'] ?? null;

    if (!$subjectId) {
        Response::error('Subject ID is required', 400);
    }

    // Validate subject belongs to user's school
    $schoolCondition = "";
    $params = [':subject_id' => $subjectId];

    if ($user['role_name'] !== 'super_admin') {
        $schoolCondition = "AND school_id = :school_id";
        $params[':school_id'] = $user['school_id'];
    }

    $subjectQuery = "SELECT * FROM subjects WHERE id = :subject_id $schoolCondition";
    $subjectStmt = $db->prepare($subjectQuery);
    foreach ($params as $key => $value) {
        $subjectStmt->bindValue($key, $value);
    }
    $subjectStmt->execute();

    $existingSubject = $subjectStmt->fetch();
    if (!$existingSubject) {
        Response::error('Subject not found or access denied', 404);
    }

    // Build update query dynamically
    $updateFields = [];
    $updateParams = [':subject_id' => $subjectId];

    $allowedFields = ['name', 'code', 'description', 'is_active'];

    foreach ($allowedFields as $field) {
        if (isset($input[$field])) {
            // Check for duplicate code if updating code
            if ($field === 'code' && $input[$field] !== $existingSubject['code']) {
                $duplicateQuery = "SELECT id FROM subjects WHERE school_id = :school_id AND code = :code AND id != :subject_id";
                $duplicateStmt = $db->prepare($duplicateQuery);
                $duplicateStmt->bindValue(':school_id', $existingSubject['school_id']);
                $duplicateStmt->bindValue(':code', $input[$field]);
                $duplicateStmt->bindValue(':subject_id', $subjectId);
                $duplicateStmt->execute();

                if ($duplicateStmt->fetch()) {
                    Response::error('Subject with this code already exists in the school', 400);
                }
            }

            $updateFields[] = "$field = :$field";
            $updateParams[":$field"] = $input[$field];
        }
    }

    if (empty($updateFields)) {
        Response::error('No valid fields to update', 400);
    }

    $updateQuery = "UPDATE subjects SET " . implode(', ', $updateFields) . " WHERE id = :subject_id";

    $updateStmt = $db->prepare($updateQuery);
    foreach ($updateParams as $key => $value) {
        $updateStmt->bindValue($key, $value);
    }

    $updateStmt->execute();

    Response::success(['subject_id' => $subjectId], 'Subject updated successfully');
}

function handleDeleteSubject($db, $user)
{
    $subjectId = $_GET['subject_id'] ?? null;

    if (!$subjectId) {
        Response::error('Subject ID is required', 400);
    }

    // Validate subject belongs to user's school
    $schoolCondition = "";
    $params = [':subject_id' => $subjectId];

    if ($user['role_name'] !== 'super_admin') {
        $schoolCondition = "AND school_id = :school_id";
        $params[':school_id'] = $user['school_id'];
    }

    $subjectQuery = "SELECT id FROM subjects WHERE id = :subject_id $schoolCondition";
    $subjectStmt = $db->prepare($subjectQuery);
    foreach ($params as $key => $value) {
        $subjectStmt->bindValue($key, $value);
    }
    $subjectStmt->execute();

    if (!$subjectStmt->fetch()) {
        Response::error('Subject not found or access denied', 404);
    }

    // Check if subject has active assignments
    $assignmentQuery = "SELECT COUNT(*) as count FROM class_subjects WHERE subject_id = :subject_id";
    $assignmentStmt = $db->prepare($assignmentQuery);
    $assignmentStmt->bindValue(':subject_id', $subjectId);
    $assignmentStmt->execute();

    $assignmentCount = $assignmentStmt->fetch()['count'];

    // Check if subject has assignments or exams
    $contentQuery = "SELECT 
                        (SELECT COUNT(*) FROM assignments WHERE subject_id = :subject_id1) as assignment_count,
                        (SELECT COUNT(*) FROM exam_subjects WHERE subject_id = :subject_id2) as exam_count";
    $contentStmt = $db->prepare($contentQuery);
    $contentStmt->bindValue(':subject_id1', $subjectId);
    $contentStmt->bindValue(':subject_id2', $subjectId);
    $contentStmt->execute();

    $contentCounts = $contentStmt->fetch();

    if ($assignmentCount > 0 || $contentCounts['assignment_count'] > 0 || $contentCounts['exam_count'] > 0) {
        // Soft delete - mark as inactive instead of hard delete
        $updateQuery = "UPDATE subjects SET is_active = 0, updated_at = NOW() WHERE id = :subject_id";
        $updateStmt = $db->prepare($updateQuery);
        $updateStmt->bindValue(':subject_id', $subjectId);
        $updateStmt->execute();

        Response::success(null, 'Subject marked as inactive due to existing assignments or exams');
    }
    else {
        $db->beginTransaction();

        try {
            // Delete related records
            $deleteQueries = [
                "DELETE FROM class_subjects WHERE subject_id = :subject_id",
                "DELETE FROM timetables WHERE subject_id = :subject_id",
                "DELETE FROM subjects WHERE id = :subject_id"
            ];

            foreach ($deleteQueries as $query) {
                $stmt = $db->prepare($query);
                $stmt->bindValue(':subject_id', $subjectId);
                $stmt->execute();
            }

            $db->commit();

            Response::success(null, 'Subject deleted successfully');

        }
        catch (Exception $e) {
            $db->rollBack();
            throw $e;
        }
    }
}
?>
