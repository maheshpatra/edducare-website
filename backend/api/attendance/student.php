<?php
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
$user = $auth->requireRole(['school_admin', 'teacher_academic', 'student']);

if (!$user) {
    exit;
}

// Input: student_id is optional for students
$studentId = isset($_GET['student_id']) ? filter_var($_GET['student_id'], FILTER_VALIDATE_INT) : null;
$startDate = $_GET['start_date'] ?? date('Y-m-01');
$endDate = $_GET['end_date'] ?? date('Y-m-t');

// Validate dates
if (!preg_match("/^\d{4}-\d{2}-\d{2}$/", $startDate) || !preg_match("/^\d{4}-\d{2}-\d{2}$/", $endDate)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid date format. Dates must be YYYY-MM-DD.']);
    exit;
}

// Determine correct student ID
if ($user['role'] === 'student') {
    $studentId = $user['id']; // override any provided ID
} elseif (!$studentId) {
    http_response_code(400);
    echo json_encode(['error' => 'student_id is required for teachers and admins.']);
    exit;
}

$db = (new Database())->getConnection();

try {
    // Verify access to the student's data
    $checkAccess = $db->prepare("SELECT id FROM students WHERE id = :student_id AND school_id = :school_id");
    $checkAccess->bindValue(':student_id', $studentId, PDO::PARAM_INT);
    $checkAccess->bindValue(':school_id', $user['school_id'], PDO::PARAM_INT);
    $checkAccess->execute();

    if (!$checkAccess->fetch()) {
        http_response_code(403);
        echo json_encode(['error' => 'Access denied. Student not found or not in your school.']);
        exit;
    }

    // Get detailed attendance records
    $query = "
        SELECT 
            a.date, a.status, a.remarks,
            c.name AS class_name,
            s.name AS section_name,
            CONCAT(t.first_name, ' ', t.last_name) AS marked_by
        FROM attendance a
        INNER JOIN classes c ON a.class_id = c.id
        LEFT JOIN sections s ON a.section_id = s.id
        LEFT JOIN users t ON a.teacher_id = t.id
        WHERE a.student_id = :student_id
        AND a.date BETWEEN :start_date AND :end_date
        ORDER BY a.date DESC
    ";

    $stmt = $db->prepare($query);
    $stmt->bindValue(':student_id', $studentId, PDO::PARAM_INT);
    $stmt->bindValue(':start_date', $startDate);
    $stmt->bindValue(':end_date', $endDate);
    $stmt->execute();

    $attendanceRecords = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get summary
    $summaryQuery = "
        SELECT 
            COUNT(CASE WHEN status = 'present' THEN 1 END) AS present_days,
            COUNT(CASE WHEN status = 'absent' THEN 1 END) AS absent_days,
            COUNT(CASE WHEN status = 'late' THEN 1 END) AS late_days,
            COUNT(*) AS total_days,
            COALESCE(
                ROUND(
                    (COUNT(CASE WHEN status = 'present' THEN 1 END) / NULLIF(COUNT(*), 0)) * 100,
                    2
                ), 0
            ) AS attendance_percentage
        FROM attendance
        WHERE student_id = :student_id
        AND date BETWEEN :start_date AND :end_date
    ";

    $summaryStmt = $db->prepare($summaryQuery);
    $summaryStmt->bindValue(':student_id', $studentId, PDO::PARAM_INT);
    $summaryStmt->bindValue(':start_date', $startDate);
    $summaryStmt->bindValue(':end_date', $endDate);
    $summaryStmt->execute();

    $summary = $summaryStmt->fetch(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'data' => $attendanceRecords,
        'summary' => $summary ?: [
            'present_days' => 0,
            'absent_days' => 0,
            'late_days' => 0,
            'total_days' => 0,
            'attendance_percentage' => 0
        ],
        'period' => [
            'start_date' => $startDate,
            'end_date' => $endDate
        ]
    ]);

} catch (Exception $e) {
    error_log("Error in student.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Internal server error']);
}
