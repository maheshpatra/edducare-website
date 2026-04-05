<?php

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

require_once '../../middleware/auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$auth = new AuthMiddleware();
$user = $auth->requireRole(['teacher_academic']);

if (!$user) {
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);

// Validate required fields
if (!isset($input['attendance_data']) || !is_array($input['attendance_data'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Attendance data is required']);
    exit;
}

$database = new Database();
$db = $database->getConnection();

try {
    $db->beginTransaction();

    $date = $input['date'] ?? date('Y-m-d');
    $successCount = 0;
    $errors = [];

    foreach ($input['attendance_data'] as $attendance) {
        if (!isset($attendance['student_id']) || !isset($attendance['status'])) {
            $errors[] = "Missing student_id or status for attendance record";
            continue;
        }

        // Verify student belongs to the school (Anyone with teacher role in the same school can now mark)
        $verifyQuery = "SELECT se.id, se.class_id, se.section_id 
                        FROM student_enrollments se
                        INNER JOIN students s ON se.student_id = s.id
                        WHERE se.student_id = :student_id
                          AND s.school_id = :school_id";

        $verifyStmt = $db->prepare($verifyQuery);
        $verifyStmt->bindValue(':student_id', $attendance['student_id']);
        $verifyStmt->bindValue(':school_id', $user['school_id']);
        $verifyStmt->execute();

        if ($verifyStmt->rowCount() === 0) {
            $errors[] = "Student ID {$attendance['student_id']} not found or not assigned to you";
            continue;
        }

        $student = $verifyStmt->fetch();

        $remarks = $attendance['remarks'] ?? null;

        // Insert or update attendance
        $attendanceQuery = "INSERT INTO attendance 
                            (student_id, class_id, section_id, teacher_id, date, status, remarks)
                            VALUES 
                            (:student_id, :class_id, :section_id, :teacher_id, :date, :status, :remarks)
                            ON DUPLICATE KEY UPDATE 
                                status = VALUES(status), 
                                remarks = VALUES(remarks)";

        $attendanceStmt = $db->prepare($attendanceQuery);
        $attendanceStmt->bindValue(':student_id', $attendance['student_id']);
        $attendanceStmt->bindValue(':class_id', $student['class_id']);
        $attendanceStmt->bindValue(':section_id', $student['section_id']);
        $attendanceStmt->bindValue(':teacher_id', $user['id']);
        $attendanceStmt->bindValue(':date', $date);
        $attendanceStmt->bindValue(':status', $attendance['status']);
        $attendanceStmt->bindValue(':remarks', $remarks);

        if ($attendanceStmt->execute()) {
            $successCount++;
        } else {
            $errors[] = "Failed to mark attendance for student ID {$attendance['student_id']}";
        }
    }

    // Log activity
    $logQuery = "INSERT INTO activity_logs 
                 (user_id, school_id, action, entity_type, new_values, ip_address, user_agent) 
                 VALUES 
                 (:user_id, :school_id, 'bulk_attendance', 'attendance', :new_values, :ip, :user_agent)";

    $logStmt = $db->prepare($logQuery);
    $logStmt->bindValue(':user_id', $user['id']);
    $logStmt->bindValue(':school_id', $user['school_id']);
    $countData = json_encode(['date' => $date, 'count' => $successCount]);
    $logStmt->bindValue(':new_values', $countData);
    $logStmt->bindValue(':ip', $_SERVER['REMOTE_ADDR']);
    $logStmt->bindValue(':user_agent', $_SERVER['HTTP_USER_AGENT']);
    $logStmt->execute();

    $db->commit();

    echo json_encode([
        'success' => true,
        'message' => "Attendance marked for $successCount students",
        'success_count' => $successCount,
        'errors' => $errors
    ]);
} catch (Exception $e) {
    $db->rollBack();
    http_response_code(500);
    echo json_encode([
        'error' => 'Internal server error',
        'exception' => $e->getMessage(),
        'trace' => $e->getTraceAsString()
    ]);
}
?>