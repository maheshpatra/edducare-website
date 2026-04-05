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
$user = $auth->requireRole(['school_admin', 'class_teacher']);

if (!$user) {
    exit;
}

if (!isset($_GET['assignment_id'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Assignment ID is required']);
    exit;
}

$database = new Database();
$db = $database->getConnection();

try {
    // Verify assignment access
    $verifyQuery = "SELECT a.id FROM assignments a
                   INNER JOIN classes c ON a.class_id = c.id
                   WHERE a.id = :assignment_id AND c.school_id = :school_id";
    
    if ($user['role'] === 'class_teacher') {
        $verifyQuery .= " AND a.teacher_id = :teacher_id";
    }
    
    $verifyStmt = $db->prepare($verifyQuery);
    $verifyStmt->bindValue(':assignment_id', $_GET['assignment_id']);
    $verifyStmt->bindValue(':school_id', $user['school_id']);
    
    if ($user['role'] === 'class_teacher') {
        $verifyStmt->bindValue(':teacher_id', $user['id']);
    }
    
    $verifyStmt->execute();
    
    if ($verifyStmt->rowCount() === 0) {
        http_response_code(404);
        echo json_encode(['error' => 'Assignment not found or access denied']);
        exit;
    }
    
    // Get submissions
    $query = "SELECT 
        asub.*,
        CONCAT(s.first_name, ' ', s.last_name) as student_name,
        s.roll_number,
        s.student_id,
        CONCAT(grader.first_name, ' ', grader.last_name) as graded_by_name
        FROM assignment_submissions asub
        INNER JOIN students s ON asub.student_id = s.id
        LEFT JOIN users grader ON asub.graded_by = grader.id
        WHERE asub.assignment_id = :assignment_id
        ORDER BY asub.submitted_at DESC";
    
    $stmt = $db->prepare($query);
    $stmt->bindValue(':assignment_id', $_GET['assignment_id']);
    $stmt->execute();
    $submissions = $stmt->fetchAll();
    
    // Process submissions to add file info
    foreach ($submissions as &$submission) {
        $submission['has_attachment'] = !empty($submission['file_path']);
        $submission['attachment_url'] = $submission['file_path'] ? 
            '/api/assignments/download_submission.php?file=' . urlencode($submission['file_path']) : null;
        $submission['is_graded'] = !is_null($submission['marks_obtained']);
        $submission['is_late'] = false; // You can implement late submission logic here
    }
    
    echo json_encode([
        'success' => true,
        'data' => $submissions,
        'total_submissions' => count($submissions)
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Internal server error']);
}
?>
