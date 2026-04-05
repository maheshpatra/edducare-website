<?php
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
$user = $auth->requireRole(['school_admin', 'class_teacher']);

if (!$user) {
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);

$required = ['class_id', 'subject', 'exam_name', 'exam_date', 'exam_type', 'total_marks'];
foreach ($required as $field) {
    if (!isset($input[$field])) {
        http_response_code(400);
        echo json_encode(['error' => "Field '$field' is required"]);
        exit;
    }
}

$database = new Database();
$db = $database->getConnection();

try {
    // Get current academic year
    $ayQuery = "SELECT id FROM academic_years WHERE school_id = :school_id AND is_current = 1";
    $ayStmt = $db->prepare($ayQuery);
    $ayStmt->bindValue(':school_id', $user['school_id']);
    $ayStmt->execute();
    $academicYear = $ayStmt->fetch();

    if (!$academicYear) {
        http_response_code(400);
        echo json_encode(['error' => 'Current academic year not found for this school']);
        exit;
    }

    $query = "INSERT INTO exams (
                school_id, class_id, section_id, subject, name, exam_type, 
                exam_date, start_time, end_time, total_marks, passing_marks, 
                instructions, academic_year_id, created_by
              )
              VALUES (
                :school_id, :class_id, :section_id, :subject, :exam_name, :exam_type, 
                :exam_date, :start_time, :end_time, :total_marks, :passing_marks, 
                :instructions, :academic_year_id, :created_by
              )";

    $stmt = $db->prepare($query);
    $stmt->bindValue(':school_id', $user['school_id']);
    $stmt->bindValue(':class_id', $input['class_id']);
    $stmt->bindValue(':section_id', $input['section_id'] ?? null);
    $stmt->bindValue(':subject', $input['subject']);
    $stmt->bindValue(':exam_name', $input['exam_name']);
    $stmt->bindValue(':exam_type', $input['exam_type']);
    $stmt->bindValue(':exam_date', $input['exam_date']);
    $stmt->bindValue(':start_time', $input['start_time'] ?? null);
    $stmt->bindValue(':end_time', $input['end_time'] ?? null);
    $stmt->bindValue(':total_marks', $input['total_marks']);
    $stmt->bindValue(':passing_marks', $input['passing_marks'] ?? null);
    $stmt->bindValue(':instructions', $input['instructions'] ?? null);
    $stmt->bindValue(':academic_year_id', $academicYear['id']);
    $stmt->bindValue(':created_by', $user['id']);

    $stmt->execute();
    $examId = $db->lastInsertId();

    echo json_encode([
        'success' => true,
        'message' => 'Exam created successfully',
        'exam_id' => $examId
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'error' => 'Internal server error',
        'exception' => $e->getMessage()
    ]);
}
?>