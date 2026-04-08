<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

require_once '../../config/database.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$rollNumber = $_GET['roll_number'] ?? '';
$schoolId = $_GET['school_id'] ?? '';

if (empty($rollNumber) || empty($schoolId)) {
    http_response_code(400);
    echo json_encode(['error' => 'Roll number and School ID are required']);
    exit;
}

$database = new Database();
$db = $database->getConnection();

try {
    // 1. Find the student by roll number in current academic year
    $enrollmentQuery = "SELECT s.id as student_id, s.first_name, s.last_name, s.admission_number,
                               c.name as class_name, c.grade_level, sec.name as section_name,
                               ay.name as academic_year, se.roll_number,
                               se.class_id, se.section_id
                        FROM student_enrollments se
                        JOIN students s ON se.student_id = s.id
                        JOIN classes c ON se.class_id = c.id
                        LEFT JOIN sections sec ON se.section_id = sec.id
                        JOIN academic_years ay ON se.academic_year_id = ay.id
                        WHERE se.roll_number = :roll_number 
                          AND s.school_id = :school_id
                          AND se.status = 'active'
                          AND ay.is_current = 1
                        LIMIT 1";
    
    $stmt = $db->prepare($enrollmentQuery);
    $stmt->bindValue(':roll_number', $rollNumber);
    $stmt->bindValue(':school_id', (int)$schoolId);
    $stmt->execute();
    
    $student = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$student) {
        http_response_code(404);
        echo json_encode(['error' => 'No student found with the provided Roll Number. Please check and try again.']);
        exit;
    }

    $student_id = $student['student_id'];
    $class_id = $student['class_id'];
    $section_id = $student['section_id'];

    // 2. Find all exams for this class/section
    $examsQuery = "SELECT DISTINCT e.id as exam_id, e.name as exam_name, 
                          e.exam_type, e.exam_date, e.total_marks as exam_total_marks
                   FROM exams e
                   WHERE e.school_id = :school_id 
                     AND e.class_id = :class_id
                     AND (e.section_id IS NULL OR e.section_id = :section_id)
                   ORDER BY e.exam_date DESC";
    
    $stmt = $db->prepare($examsQuery);
    $stmt->bindValue(':school_id', (int)$schoolId);
    $stmt->bindValue(':class_id', $class_id);
    $stmt->bindValue(':section_id', $section_id);
    $stmt->execute();
    $exams = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($exams)) {
        echo json_encode([
            'success' => true,
            'message' => 'No exams found for this student\'s class.',
            'student_info' => buildStudentInfo($student),
            'exams' => []
        ]);
        exit;
    }

    // 3. Dynamically detect exam_results columns
    $erColumns = $db->query("SHOW COLUMNS FROM exam_results")->fetchAll(PDO::FETCH_COLUMN);
    $hasExamSubjectId = in_array('exam_subject_id', $erColumns);
    $marksColumn = in_array('marks_obtained', $erColumns) ? 'marks_obtained' : 'marks';

    $allExamResults = [];

    foreach ($exams as $exam) {
        $examId = $exam['exam_id'];

        if ($hasExamSubjectId) {
            // Schema: exam_results → exam_subjects → exams + subjects
            $resultsQuery = "SELECT sub.name as subject_name, er.{$marksColumn} as marks_obtained,
                                    es.max_marks, es.pass_marks,
                                    er.grade, er.remarks
                             FROM exam_results er
                             JOIN exam_subjects es ON er.exam_subject_id = es.id
                             JOIN subjects sub ON es.subject_id = sub.id
                             WHERE es.exam_id = :exam_id
                               AND er.student_id = :student_id
                             ORDER BY sub.name ASC";
        } else {
            // Flat schema: exam_results has exam_id directly
            $resultsQuery = "SELECT e.subject as subject_name, er.{$marksColumn} as marks_obtained,
                                    e.total_marks as max_marks, 
                                    COALESCE(e.passing_marks, FLOOR(e.total_marks * 0.33)) as pass_marks,
                                    NULL as grade, NULL as remarks
                             FROM exam_results er
                             JOIN exams e ON er.exam_id = e.id
                             WHERE er.exam_id = :exam_id
                               AND er.student_id = :student_id";
        }

        $stmt = $db->prepare($resultsQuery);
        $stmt->bindValue(':exam_id', $examId);
        $stmt->bindValue(':student_id', $student_id);
        $stmt->execute();
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (empty($results)) {
            continue; // Skip exams with no results for this student
        }

        $totalObtained = 0;
        $totalMax = 0;
        $overallStatus = 'Pass';
        $subjects = [];

        foreach ($results as $row) {
            $obtained = (float)$row['marks_obtained'];
            $max = (float)$row['max_marks'];
            $pass = (float)($row['pass_marks'] ?? floor($max * 0.33));
            $subjectStatus = ($obtained >= $pass) ? 'Pass' : 'Fail';

            if ($subjectStatus === 'Fail') {
                $overallStatus = 'Fail';
            }

            $subjects[] = [
                'name' => $row['subject_name'],
                'marks' => $obtained,
                'max' => $max,
                'pass_marks' => $pass,
                'grade' => $row['grade'] ?? calculateGrade(($max > 0 ? ($obtained / $max * 100) : 0)),
                'status' => $subjectStatus
            ];

            $totalObtained += $obtained;
            $totalMax += $max;
        }

        $percentage = $totalMax > 0 ? round(($totalObtained / $totalMax) * 100, 2) : 0;

        $allExamResults[] = [
            'exam_name' => $exam['exam_name'],
            'exam_type' => $exam['exam_type'],
            'exam_date' => $exam['exam_date'],
            'subjects' => $subjects,
            'total_marks' => $totalObtained,
            'max_marks' => $totalMax,
            'percentage' => $percentage . '%',
            'grade' => calculateGrade($percentage),
            'status' => $overallStatus
        ];
    }

    if (empty($allExamResults)) {
        echo json_encode([
            'success' => true,
            'message' => 'No exam results have been published for this student yet.',
            'student_info' => buildStudentInfo($student),
            'exams' => []
        ]);
        exit;
    }

    // Return current (latest) + all exams
    echo json_encode([
        'success' => true,
        'student_info' => buildStudentInfo($student),
        'current_result' => $allExamResults[0],
        'all_exams' => $allExamResults
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Internal server error: ' . $e->getMessage()]);
}

function buildStudentInfo($student) {
    return [
        'student_name' => "{$student['first_name']} {$student['last_name']}",
        'class' => "{$student['class_name']}" . ($student['section_name'] ? " - {$student['section_name']}" : ""),
        'academic_year' => $student['academic_year'],
        'roll_number' => $student['roll_number'],
        'admission_number' => $student['admission_number']
    ];
}

function calculateGrade($percentage) {
    if ($percentage >= 90) return 'A+';
    if ($percentage >= 80) return 'A';
    if ($percentage >= 70) return 'B+';
    if ($percentage >= 60) return 'B';
    if ($percentage >= 50) return 'C';
    if ($percentage >= 40) return 'D';
    if ($percentage >= 33) return 'E';
    return 'F';
}
