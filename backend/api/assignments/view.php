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

if (!isset($_GET['id'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Assignment ID is required']);
    exit;
}

$database = new Database();
$db = $database->getConnection();

try {
    // Get assignment details
    $query = "SELECT 
        a.*,
        c.name as class_name,
        c.grade_level,
        s.name as section_name,
        CONCAT(u.first_name, ' ', u.last_name) as teacher_name,
        u.email as teacher_email
        FROM assignments a
        INNER JOIN classes c ON a.class_id = c.id
        LEFT JOIN sections s ON a.section_id = s.id
        INNER JOIN users u ON a.teacher_id = u.id
        WHERE a.id = :assignment_id AND c.school_id = :school_id";
    
    // Add teacher-specific filter if not school admin
    if ($user['role'] === 'class_teacher') {
        $query .= " AND a.teacher_id = :teacher_id";
    }
    
    $stmt = $db->prepare($query);
    $stmt->bindValue(':assignment_id', $_GET['id']);
    $stmt->bindValue(':school_id', $user['school_id']);
    
    if ($user['role'] === 'class_teacher') {
        $stmt->bindValue(':teacher_id', $user['id']);
    }
    
    $stmt->execute();
    
    if ($stmt->rowCount() === 0) {
        http_response_code(404);
        echo json_encode(['error' => 'Assignment not found']);
        exit;
    }
    
    $assignment = $stmt->fetch();
    
    // Add attachment information
    $assignment['has_attachment'] = !empty($assignment['attachment_path']);
    $assignment['attachment_url'] = $assignment['attachment_path'] ? 
        '/api/assignments/download.php?file=' . urlencode($assignment['attachment_path']) : null;
    
    // Get submission statistics
    $statsQuery = "SELECT 
        COUNT(*) as total_submissions,
        COUNT(CASE WHEN marks_obtained IS NOT NULL THEN 1 END) as graded_submissions,
        AVG(marks_obtained) as average_marks,
        MAX(marks_obtained) as highest_marks,
        MIN(marks_obtained) as lowest_marks
        FROM assignment_submissions 
        WHERE assignment_id = :assignment_id";
    
    $statsStmt = $db->prepare($statsQuery);
    $statsStmt->bindValue(':assignment_id', $_GET['id']);
    $statsStmt->execute();
    $stats = $statsStmt->fetch();
    
    // Get total eligible students
    $studentCountQuery = "SELECT COUNT(*) as total_students 
                         FROM students 
                         WHERE class_id = :class_id 
                         AND (:section_id IS NULL OR section_id = :section_id)
                         AND is_active = TRUE";
    
    $studentCountStmt = $db->prepare($studentCountQuery);
    $studentCountStmt->bindValue(':class_id', $assignment['class_id']);
    $studentCountStmt->bindValue(':section_id', $assignment['section_id']);
    $studentCountStmt->execute();
    $totalStudents = $studentCountStmt->fetch()['total_students'];
    
    // Calculate submission percentage
    $submissionPercentage = $totalStudents > 0 ? 
        round(($stats['total_submissions'] / $totalStudents) * 100, 2) : 0;
    
    // Get recent submissions (last 5)
    $recentSubmissionsQuery = "SELECT 
        asub.*,
        CONCAT(s.first_name, ' ', s.last_name) as student_name,
        s.roll_number
        FROM assignment_submissions asub
        INNER JOIN students s ON asub.student_id = s.id
        WHERE asub.assignment_id = :assignment_id
        ORDER BY asub.submitted_at DESC
        LIMIT 5";
    
    $recentStmt = $db->prepare($recentSubmissionsQuery);
    $recentStmt->bindValue(':assignment_id', $_GET['id']);
    $recentStmt->execute();
    $recentSubmissions = $recentStmt->fetchAll();
    
    // Process recent submissions to add file info
    foreach ($recentSubmissions as &$submission) {
        $submission['has_attachment'] = !empty($submission['file_path']);
        $submission['attachment_url'] = $submission['file_path'] ? 
            '/api/assignments/download_submission.php?file=' . urlencode($submission['file_path']) : null;
    }
    
    echo json_encode([
        'success' => true,
        'assignment' => $assignment,
        'statistics' => [
            'total_students' => $totalStudents,
            'total_submissions' => $stats['total_submissions'],
            'submission_percentage' => $submissionPercentage,
            'graded_submissions' => $stats['graded_submissions'],
            'average_marks' => $stats['average_marks'] ? round($stats['average_marks'], 2) : null,
            'highest_marks' => $stats['highest_marks'],
            'lowest_marks' => $stats['lowest_marks']
        ],
        'recent_submissions' => $recentSubmissions
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Internal server error']);
}
?>
