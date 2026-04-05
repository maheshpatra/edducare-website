<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

require_once '../../middleware/auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$auth = new AuthMiddleware();
$user = $auth->requireRole(['admin', 'school_admin', 'teacher_academic']);

if (!$user) {
    exit;
}

$database = new Database();
$db = $database->getConnection();

try {
    $currentYear = date('Y');
    $currentMonth = date('m');
    $defaultStartDate = date('Y-m-01');
    $defaultEndDate = date('Y-m-t'); // Last day of current month

    $startDate = $_GET['start_date'];
    $endDate = $_GET['end_date'];
    $classId = $_GET['class_id'];
    $sectionId = $_GET['section_id'];

    if (!preg_match("/^\d{4}-\d{2}-\d{2}$/", $startDate) || !preg_match("/^\d{4}-\d{2}-\d{2}$/", $endDate)) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid date format. Dates must be YYYY-MM-DD.']);
        exit;
    }

    $whereConditions = ["a.date BETWEEN :start_date AND :end_date"];
    $params = [
        ':start_date' => $startDate,
        ':end_date' => $endDate
    ];

    // School-level filtering
    $whereConditions[] = "s.school_id = :school_id";
    $params[':school_id'] = $user['school_id'];

    // Role-based filtering: teacher_academic can only see reports for their classes
    if ($user['role'] === 'teacher_academic') {
        $whereConditions[] = "a.teacher_id = :teacher_id";
        $params[':teacher_id'] = $user['id'];
    }
    

     if ($classId !== null) {
        $parsedClassId = filter_var($classId, FILTER_VALIDATE_INT);
        if ($parsedClassId === false) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid class_id. Must be an integer.']);
            exit;
        }
        $whereConditions[] = "a.class_id = :class_id";
        $params[':class_id'] = $parsedClassId;
    }
    
    if ($sectionId !== null) {
        $parsedSectionId = filter_var($sectionId, FILTER_VALIDATE_INT);
        if ($parsedSectionId === false) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid section_id. Must be an integer.']);
            exit;
        }
        $whereConditions[] = "a.section_id = :section_id";
        $params[':section_id'] = $parsedSectionId;
    }
    $whereClause = implode(' AND ', $whereConditions);


    // Get detailed attendance report per student
   $query = "SELECT 
        s.first_name, s.last_name, s.email,
        se.roll_number, s.student_id AS student_unique_id, -- Renamed to avoid confusion with students.id
        c.name as class_name, c.grade_level,
        sec.name as section_name,
        COUNT(CASE WHEN a.status = 'present' THEN 1 END) as present_days,
        COUNT(CASE WHEN a.status = 'absent' THEN 1 END) as absent_days,
        COUNT(CASE WHEN a.status = 'late' THEN 1 END) as late_days,
        COUNT(*) as total_days,
        COALESCE(ROUND((COUNT(CASE WHEN a.status = 'present' THEN 1 END) / NULLIF(COUNT(*), 0)) * 100, 2), 0) as attendance_percentage
        FROM attendance a
        INNER JOIN student_enrollments se ON a.student_id = se.student_id
        INNER JOIN students s ON se.student_id = s.id -- Corrected join to students table
        INNER JOIN classes c ON a.class_id = c.id
        LEFT JOIN sections sec ON a.section_id = sec.id
        WHERE $whereClause
        GROUP BY s.id, s.first_name, s.last_name, s.email, se.roll_number, s.student_id, c.name, c.grade_level, sec.name
        ORDER BY c.grade_level, sec.name, s.first_name";
    
    $stmt = $db->prepare($query);
    foreach ($params as $key => &$value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->execute();
    $attendanceReport = $stmt->fetchAll(PDO::FETCH_ASSOC);


    
     $summaryQuery = "SELECT 
        COUNT(DISTINCT se.student_id) as total_students_in_report,
        COUNT(CASE WHEN a.status = 'present' THEN 1 END) as total_present_marks,
        COUNT(CASE WHEN a.status = 'absent' THEN 1 END) as total_absent_marks,
        COUNT(CASE WHEN a.status = 'late' THEN 1 END) as total_late_marks,
        COUNT(*) as total_attendance_marks,
        COALESCE(ROUND(AVG(CASE WHEN a.status = 'present' THEN 100 ELSE 0 END), 2), 0) as overall_percentage_of_present_marks
        FROM attendance a
        INNER JOIN student_enrollments se ON a.student_id = se.student_id
        WHERE $whereClause";
    
    $summaryStmt = $db->prepare($summaryQuery);
    foreach ($params as $key => &$value) {
        $summaryStmt->bindValue($key, $value);
    }
    $summaryStmt->execute();
    $summary = $summaryStmt->fetch(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'data' => $attendanceReport,
        'summary' => $summary ?: ['total_students_in_report' => 0, 'total_present_marks' => 0, 'total_absent_marks' => 0, 'total_late_marks' => 0, 'total_attendance_marks' => 0, 'overall_percentage_of_present_marks' => 0],
        'period' => [
            'start_date' => $startDate,
            'end_date' => $endDate
        ]
    ]);

} catch (PDOException $e) {
    error_log("Database error in attendance-report.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
} catch (Exception $e) {
    error_log("General error in attendance-report.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'An unexpected error occurred.']);
}
?>