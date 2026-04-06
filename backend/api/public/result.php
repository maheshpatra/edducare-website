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
    // 1. Find the student enrollment for the current academic year
    $enrollmentQuery = "SELECT s.id as student_id, s.first_name, s.last_name, s.admission_number,
                               c.name as class_name, c.grade_level, sec.name as section_name,
                               ay.name as academic_year, se.roll_number
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
        echo json_encode(['error' => 'No student found with the provided Roll Number.']);
        exit;
    }

    $student_id = $student['student_id'];

    // 2. Fetch exam results for this student
    // We'll aggregate them by exam. For now, let's fetch the most recent completed exam's detailed transcript.
    // Or just all results. Let's start with a comprehensive list.
    
    $resultsQuery = "SELECT e.id as exam_id, e.name as exam_name, e.type as exam_type,
                            sub.name as subject_name, er.marks, es.max_marks, es.pass_marks,
                            er.grade, er.remarks, er.exam_date
                     FROM exam_results er
                     JOIN exam_subjects es ON er.exam_subject_id = es.id
                     JOIN exams e ON es.exam_id = e.id
                     JOIN subjects sub ON es.subject_id = sub.id
                     WHERE er.student_id = :student_id
                     ORDER BY e.exam_date DESC, sub.name ASC";
    
    $stmt = $db->prepare($resultsQuery);
    $stmt->bindValue(':student_id', $student_id);
    $stmt->execute();
    
    $allResults = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($allResults)) {
        echo json_encode([
            'success' => true,
            'message' => 'No exam records found for this student.',
            'student_info' => [
                'student_name' => "{$student['first_name']} {$student['last_name']}",
                'class' => "{$student['class_name']} - {$student['section_name']}",
                'academic_year' => $student['academic_year'],
                'roll_number' => $student['roll_number']
            ],
            'results' => []
        ]);
        exit;
    }

    // Grouping results by exam
    $exams = [];
    foreach ($allResults as $row) {
        $examId = $row['exam_id'];
        if (!isset($exams[$examId])) {
            $exams[$examId] = [
                'exam_name' => $row['exam_name'],
                'exam_type' => $row['exam_type'],
                'exam_date' => $row['exam_date'],
                'subjects' => [],
                'total_marks' => 0,
                'max_marks' => 0,
                'status' => 'Pass' // Default status
            ];
        }
        
        $obtained = (float)$row['marks'];
        $max = (float)$row['max_marks'];
        $pass = (float)$row['pass_marks'];

        $exams[$examId]['subjects'][] = [
            'name' => $row['subject_name'],
            'marks' => $obtained,
            'max' => $max,
            'grade' => $row['grade'],
            'status' => ($obtained >= $pass) ? 'Pass' : 'Fail'
        ];
        
        $exams[$examId]['total_marks'] += $obtained;
        $exams[$examId]['max_marks'] += $max;
        
        if ($obtained < $pass) {
            $exams[$examId]['status'] = 'Fail';
        }
    }

    // Process final stats for each exam
    foreach ($exams as $id => &$e) {
        $e['percentage'] = $e['max_marks'] > 0 ? round(($e['total_marks'] / $e['max_marks']) * 100, 2) . '%' : '0%';
        $e['grade'] = calculateGrade($e['total_marks'] / $e['max_marks'] * 100);
    }
    
    // Convert to indexed array and take the latest one (or return all)
    $examsList = array_values($exams);
    
    // For compatibility with the current frontend mock, we'll return the latest exam as the main result
    // plus a list of all exams if they want to choose? Actually, the frontend is simple, let's just give the latest.
    $latestResult = $examsList[0];

    echo json_encode([
        'success' => true,
        'student_info' => [
            'student_name' => "{$student['first_name']} {$student['last_name']}",
            'class' => "{$student['class_name']}" . ($student['section_name'] ? " - {$student['section_name']}" : ""),
            'academic_year' => $student['academic_year'],
            'roll_number' => $student['roll_number']
        ],
        'current_result' => $latestResult,
        'all_exams' => $examsList
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Internal server error: ' . $e->getMessage()]);
}

function calculateGrade($percentage) {
    if ($percentage >= 90) return 'A+';
    if ($percentage >= 80) return 'A';
    if ($percentage >= 70) return 'B';
    if ($percentage >= 60) return 'C';
    if ($percentage >= 50) return 'D';
    if ($percentage >= 40) return 'E';
    return 'F';
}
