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
$user = $auth->requireRole(['student']);

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
                        handleGetStudentSubjects($db, $user);
                        break;
                    case 'details':
                        handleGetSubjectDetails($db, $user);
                        break;
                    case 'assignments':
                        handleGetSubjectAssignments($db, $user);
                        break;
                    case 'schedule':
                        handleGetSubjectSchedule($db, $user);
                        break;
                    case 'grades':
                        handleGetSubjectGrades($db, $user);
                        break;
                    default:
                        handleGetStudentSubjects($db, $user);
                }
            } else {
                handleGetStudentSubjects($db, $user);
            }
            break;
        default:
            Response::error('Method not allowed', 405);
    }
} catch (Exception $e) {
    Response::error('Internal server error: ' . $e->getMessage(), 500);
}

function handleGetStudentSubjects($db, $user) {
    // Get current academic year
    $academicYearQuery = "SELECT id FROM academic_years WHERE school_id = :school_id AND is_current = 1";
    $academicYearStmt = $db->prepare($academicYearQuery);
    $academicYearStmt->bindValue(':school_id', $user['school_id']);
    $academicYearStmt->execute();
    $currentAcademicYear = $academicYearStmt->fetch();
    
    if (!$currentAcademicYear) {
        Response::error('No current academic year found', 404);
    }
    
    $query = "SELECT DISTINCT s.id, s.name, s.code, s.description,
                     c.name as class_name, c.grade_level, c.room_number,
                     sec.name as section_name,
                     CONCAT(teacher.first_name, ' ', teacher.last_name) as teacher_name,
                     teacher.email as teacher_email, teacher.phone as teacher_phone,
                     teacher.employee_id as teacher_employee_id,
                     cs.id as class_subject_id,
                     
                     -- Assignment statistics
                     COUNT(DISTINCT a.id) as total_assignments,
                     COUNT(DISTINCT asub.id) as submitted_assignments,
                     COUNT(DISTINCT CASE WHEN a.due_date >= CURDATE() THEN a.id END) as pending_assignments,
                     COUNT(DISTINCT CASE WHEN a.due_date < CURDATE() AND asub.id IS NULL THEN a.id END) as overdue_assignments,
                     
                     -- Exam statistics
                     COUNT(DISTINCT es.id) as total_exams,
                     COUNT(DISTINCT er.id) as completed_exams,
                     
                     -- Grade information
                  
                     -- Next class information
                     (SELECT CONCAT(t.day_of_week, ' ', TIME_FORMAT(t.start_time, '%H:%i'), '-', TIME_FORMAT(t.end_time, '%H:%i'))
                      FROM timetables t 
                      WHERE t.subject_id = s.id AND t.class_id = c.id AND t.section_id = sec.id
                      AND t.academic_year_id = :academic_year_id
                      AND (
                          (t.day_of_week = DAYNAME(CURDATE()) AND t.start_time > CURTIME()) OR
                          (t.day_of_week > DAYNAME(CURDATE()))
                      )
                      ORDER BY 
                          CASE t.day_of_week
                              WHEN 'Monday' THEN 1 WHEN 'Tuesday' THEN 2 WHEN 'Wednesday' THEN 3
                              WHEN 'Thursday' THEN 4 WHEN 'Friday' THEN 5 WHEN 'Saturday' THEN 6
                              WHEN 'Sunday' THEN 7
                          END,
                          t.start_time
                      LIMIT 1
                     ) as next_class
                     
              FROM student_enrollments se
              JOIN classes c ON se.class_id = c.id
              LEFT JOIN sections sec ON se.section_id = sec.id
              JOIN class_subjects cs ON c.id = cs.class_id
              JOIN subjects s ON cs.subject_id = s.id
              LEFT JOIN users teacher ON cs.teacher_id = teacher.id
              LEFT JOIN assignments a ON s.id = a.subject_id AND c.id = a.class_id 
                  AND (a.section_id IS NULL OR a.section_id = sec.id)
              LEFT JOIN assignment_submissions asub ON a.id = asub.assignment_id AND asub.student_id = :student_id
              LEFT JOIN exam_subjects es ON s.id = es.subject_id AND c.id = es.class_id
              LEFT JOIN exam_results er ON es.id = er.exam_subject_id AND er.student_id = :student_id
              
              WHERE se.student_id = :student_id 
                AND se.status = 'active'
                AND se.academic_year_id = :academic_year_id
                AND s.is_active = 1
              
              GROUP BY s.id, c.id, sec.id, cs.id
              ORDER BY s.name ASC";
    
    $stmt = $db->prepare($query);
    $stmt->bindValue(':student_id', $user['id']);
    $stmt->bindValue(':academic_year_id', $currentAcademicYear['id']);
    $stmt->execute();
    
    $subjects = $stmt->fetchAll();
    
    // Get overall statistics
    $statsQuery = "SELECT 
                  COUNT(DISTINCT s.id) as total_subjects,
                  COUNT(DISTINCT a.id) as total_assignments,
                  COUNT(DISTINCT asub.id) as submitted_assignments,
                  COUNT(DISTINCT CASE WHEN a.due_date >= CURDATE() THEN a.id END) as pending_assignments,
                  COUNT(DISTINCT es.id) as total_exams,
                  COUNT(DISTINCT er.id) as completed_exams
                  /* AVG(CASE WHEN er.marks IS NOT NULL THEN (er.marks / es.max_marks) * 100 END) as overall_average */
               FROM student_enrollments se
               JOIN classes c ON se.class_id = c.id
               LEFT JOIN sections sec ON se.section_id = sec.id
               JOIN class_subjects cs ON c.id = cs.class_id
               JOIN subjects s ON cs.subject_id = s.id
               LEFT JOIN assignments a ON s.id = a.subject_id AND c.id = a.class_id 
                   AND (a.section_id IS NULL OR a.section_id = sec.id)
               LEFT JOIN assignment_submissions asub ON a.id = asub.assignment_id AND asub.student_id = :student_id
               LEFT JOIN exam_subjects es ON s.id = es.subject_id AND c.id = es.class_id
               LEFT JOIN exam_results er ON es.id = er.exam_subject_id AND er.student_id = :student_id
               WHERE se.student_id = :student_id 
                 AND se.status = 'active'
                 AND se.academic_year_id = :academic_year_id
                 AND s.is_active = 1";

    
    $statsStmt = $db->prepare($statsQuery);
    $statsStmt->bindValue(':student_id', $user['id']);
    $statsStmt->bindValue(':academic_year_id', $currentAcademicYear['id']);
    $statsStmt->execute();
    $stats = $statsStmt->fetch();
    
    Response::success([
        'subjects' => $subjects,
        'statistics' => $stats,
        'academic_year_id' => $currentAcademicYear['id']
    ], 'Student subjects retrieved successfully');
}

function handleGetSubjectDetails($db, $user) {
    $subjectId = $_GET['subject_id'] ?? null;
    
    if (!$subjectId) {
        Response::error('Subject ID is required', 400);
    }
    
    // Verify student is enrolled in this subject
    $enrollmentQuery = "SELECT cs.id, s.*, c.name as class_name, c.grade_level,
                               sec.name as section_name,
                               CONCAT(teacher.first_name, ' ', teacher.last_name) as teacher_name,
                               teacher.email as teacher_email, teacher.phone as teacher_phone
                        FROM student_enrollments se
                        JOIN classes c ON se.class_id = c.id
                        LEFT JOIN sections sec ON se.section_id = sec.id
                        JOIN class_subjects cs ON c.id = cs.class_id
                        JOIN subjects s ON cs.subject_id = s.id
                        LEFT JOIN users teacher ON cs.teacher_id = teacher.id
                        WHERE se.student_id = :student_id 
                          AND s.id = :subject_id
                          AND se.status = 'active'
                          AND s.is_active = 1";
    
    $enrollmentStmt = $db->prepare($enrollmentQuery);
    $enrollmentStmt->bindValue(':student_id', $user['id']);
    $enrollmentStmt->bindValue(':subject_id', $subjectId);
    $enrollmentStmt->execute();
    
    $subject = $enrollmentStmt->fetch();
    
    if (!$subject) {
        Response::error('Subject not found or you are not enrolled in this subject', 404);
    }
    
    // Get recent assignments
    $assignmentsQuery = "SELECT a.*, 
                                asub.id as submission_id, asub.submitted_at, asub.grade, asub.feedback,
                                CASE 
                                    WHEN asub.id IS NOT NULL THEN 'submitted'
                                    WHEN a.due_date < CURDATE() THEN 'overdue'
                                    ELSE 'pending'
                                END as status
                         FROM assignments a
                         LEFT JOIN assignment_submissions asub ON a.id = asub.assignment_id AND asub.student_id = :student_id
                         WHERE a.subject_id = :subject_id
                           AND a.class_id = (SELECT class_id FROM student_enrollments WHERE student_id = :student_id AND status = 'active')
                         ORDER BY a.due_date DESC
                         LIMIT 10";
    
    $assignmentsStmt = $db->prepare($assignmentsQuery);
    $assignmentsStmt->bindValue(':student_id', $user['id']);
    $assignmentsStmt->bindValue(':subject_id', $subjectId);
    $assignmentsStmt->execute();
    
    $assignments = $assignmentsStmt->fetchAll();
    
    // Get exam results
    $examsQuery = "SELECT es.*, e.name as exam_name, e.type as exam_type, e.start_date, e.end_date,
                          er.marks, er.grade, er.remarks, er.exam_date,
                          (er.marks / es.max_marks * 100) as percentage
                   FROM exam_subjects es
                   JOIN exams e ON es.exam_id = e.id
                   LEFT JOIN exam_results er ON es.id = er.exam_subject_id AND er.student_id = :student_id
                   WHERE es.subject_id = :subject_id
                     AND es.class_id = (SELECT class_id FROM student_enrollments WHERE student_id = :student_id AND status = 'active')
                   ORDER BY e.start_date DESC";
    
    $examsStmt = $db->prepare($examsQuery);
    $examsStmt->bindValue(':student_id', $user['id']);
    $examsStmt->bindValue(':subject_id', $subjectId);
    $examsStmt->execute();
    
    $exams = $examsStmt->fetchAll();
    
    $subject['assignments'] = $assignments;
    $subject['exams'] = $exams;
    
    Response::success(['subject' => $subject], 'Subject details retrieved successfully');
}

function handleGetSubjectAssignments($db, $user) {
    $subjectId = $_GET['subject_id'] ?? null;
    $status = $_GET['status'] ?? 'all'; // all, pending, submitted, overdue
    
    if (!$subjectId) {
        Response::error('Subject ID is required', 400);
    }
    
    // Build status condition
    $statusCondition = "";
    switch ($status) {
        case 'pending':
            $statusCondition = "AND asub.id IS NULL AND a.due_date >= CURDATE()";
            break;
        case 'submitted':
            $statusCondition = "AND asub.id IS NOT NULL";
            break;
        case 'overdue':
            $statusCondition = "AND asub.id IS NULL AND a.due_date < CURDATE()";
            break;
    }
    
    $query = "SELECT a.*, 
                     asub.id as submission_id, asub.submitted_at, asub.grade, asub.feedback, asub.file_path,
                     CASE 
                         WHEN asub.id IS NOT NULL THEN 'submitted'
                         WHEN a.due_date < CURDATE() THEN 'overdue'
                         ELSE 'pending'
                     END as status,
                     DATEDIFF(a.due_date, CURDATE()) as days_remaining
              FROM assignments a
              LEFT JOIN assignment_submissions asub ON a.id = asub.assignment_id AND asub.student_id = :student_id
              WHERE a.subject_id = :subject_id
                AND a.class_id = (SELECT class_id FROM student_enrollments WHERE student_id = :student_id AND status = 'active')
                $statusCondition
              ORDER BY a.due_date ASC";
    
    $stmt = $db->prepare($query);
    $stmt->bindValue(':student_id', $user['id']);
    $stmt->bindValue(':subject_id', $subjectId);
    $stmt->execute();
    
    $assignments = $stmt->fetchAll();
    
    Response::success(['assignments' => $assignments], 'Subject assignments retrieved successfully');
}

function handleGetSubjectSchedule($db, $user) {
    $subjectId = $_GET['subject_id'] ?? null;
    
    if (!$subjectId) {
        Response::error('Subject ID is required', 400);
    }
    
    $query = "SELECT t.*, s.name as subject_name, s.code as subject_code,
                     c.name as class_name, sec.name as section_name, c.room_number,
                     CONCAT(teacher.first_name, ' ', teacher.last_name) as teacher_name
              FROM timetables t
              JOIN subjects s ON t.subject_id = s.id
              JOIN classes c ON t.class_id = c.id
              LEFT JOIN sections sec ON t.section_id = sec.id
              LEFT JOIN users teacher ON t.teacher_id = teacher.id
              JOIN student_enrollments se ON c.id = se.class_id AND (sec.id IS NULL OR se.section_id = sec.id)
              WHERE se.student_id = :student_id 
                AND s.id = :subject_id
                AND se.status = 'active'
              ORDER BY 
                  CASE t.day_of_week
                      WHEN 'Monday' THEN 1 WHEN 'Tuesday' THEN 2 WHEN 'Wednesday' THEN 3
                      WHEN 'Thursday' THEN 4 WHEN 'Friday' THEN 5 WHEN 'Saturday' THEN 6
                      WHEN 'Sunday' THEN 7
                  END,
                  t.start_time";
    
    $stmt = $db->prepare($query);
    $stmt->bindValue(':student_id', $user['id']);
    $stmt->bindValue(':subject_id', $subjectId);
    $stmt->execute();
    
    $schedule = $stmt->fetchAll();
    
    Response::success(['schedule' => $schedule], 'Subject schedule retrieved successfully');
}

function handleGetSubjectGrades($db, $user) {
    $subjectId = $_GET['subject_id'] ?? null;
    
    if (!$subjectId) {
        Response::error('Subject ID is required', 400);
    }
    
    // Get assignment grades
    $assignmentGradesQuery = "SELECT a.title, a.max_marks, a.due_date,
                                     asub.grade as marks, asub.feedback, asub.submitted_at,
                                     (asub.grade / a.max_marks * 100) as percentage,
                                     'assignment' as type
                              FROM assignments a
                              LEFT JOIN assignment_submissions asub ON a.id = asub.assignment_id AND asub.student_id = :student_id
                              WHERE a.subject_id = :subject_id
                                AND a.class_id = (SELECT class_id FROM student_enrollments WHERE student_id = :student_id AND status = 'active')
                                AND asub.grade IS NOT NULL
                              ORDER BY a.due_date DESC";
    
    $assignmentGradesStmt = $db->prepare($assignmentGradesQuery);
    $assignmentGradesStmt->bindValue(':student_id', $user['id']);
    $assignmentGradesStmt->bindValue(':subject_id', $subjectId);
    $assignmentGradesStmt->execute();
    
    $assignmentGrades = $assignmentGradesStmt->fetchAll();
    
    // Get exam grades
    $examGradesQuery = "SELECT e.name as title, es.max_marks, e.start_date as due_date,
                               er.marks, er.remarks as feedback, er.exam_date as submitted_at,
                               (er.marks / es.max_marks * 100) as percentage,
                               'exam' as type, e.type as exam_type
                        FROM exam_subjects es
                        JOIN exams e ON es.exam_id = e.id
                        LEFT JOIN exam_results er ON es.id = er.exam_subject_id AND er.student_id = :student_id
                        WHERE es.subject_id = :subject_id
                          AND es.class_id = (SELECT class_id FROM student_enrollments WHERE student_id = :student_id AND status = 'active')
                          AND er.marks IS NOT NULL
                        ORDER BY e.start_date DESC";
    
    $examGradesStmt = $db->prepare($examGradesQuery);
    $examGradesStmt->bindValue(':student_id', $user['id']);
    $examGradesStmt->bindValue(':subject_id', $subjectId);
    $examGradesStmt->execute();
    
    $examGrades = $examGradesStmt->fetchAll();
    
    // Calculate overall statistics
    $allGrades = array_merge($assignmentGrades, $examGrades);
    $totalMarks = array_sum(array_column($allGrades, 'marks'));
    $totalMaxMarks = array_sum(array_column($allGrades, 'max_marks'));
    $overallPercentage = $totalMaxMarks > 0 ? ($totalMarks / $totalMaxMarks * 100) : 0;
    
    $statistics = [
        'total_assessments' => count($allGrades),
        'assignment_count' => count($assignmentGrades),
        'exam_count' => count($examGrades),
        'total_marks' => $totalMarks,
        'total_max_marks' => $totalMaxMarks,
        'overall_percentage' => round($overallPercentage, 2),
        'average_percentage' => count($allGrades) > 0 ? round(array_sum(array_column($allGrades, 'percentage')) / count($allGrades), 2) : 0
    ];
    
    Response::success([
        'assignment_grades' => $assignmentGrades,
        'exam_grades' => $examGrades,
        'all_grades' => $allGrades,
        'statistics' => $statistics
    ], 'Subject grades retrieved successfully');
}
?>