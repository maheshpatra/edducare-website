<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: DELETE');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

require_once '../../middleware/auth.php';
require_once '../../config/file_upload.php';

if ($_SERVER['REQUEST_METHOD'] !== 'DELETE') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$auth = new AuthMiddleware();
$user = $auth->requireRole(['class_teacher']);

if (!$user) {
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);

if (!isset($input['assignment_id'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Assignment ID is required']);
    exit;
}

$database = new Database();
$db = $database->getConnection();
$fileUpload = new FileUpload();

try {
    // Verify assignment belongs to the teacher
    $verifyQuery = "SELECT a.*, c.school_id FROM assignments a
                   INNER JOIN classes c ON a.class_id = c.id
                   WHERE a.id = :assignment_id AND a.teacher_id = :teacher_id 
                   AND c.school_id = :school_id";
    $verifyStmt = $db->prepare($verifyQuery);
    $verifyStmt->bindValue(':assignment_id', $input['assignment_id']);
    $verifyStmt->bindValue(':teacher_id', $user['id']);
    $verifyStmt->bindValue(':school_id', $user['school_id']);
    $verifyStmt->execute();
    
    if ($verifyStmt->rowCount() === 0) {
        http_response_code(403);
        echo json_encode(['error' => 'Assignment not found or access denied']);
        exit;
    }
    
    $assignment = $verifyStmt->fetch();
    
    // Check if there are any submissions
    $submissionQuery = "SELECT COUNT(*) as submission_count FROM assignment_submissions WHERE assignment_id = :assignment_id";
    $submissionStmt = $db->prepare($submissionQuery);
    $submissionStmt->bindValue(':assignment_id', $input['assignment_id']);
    $submissionStmt->execute();
    $submissionCount = $submissionStmt->fetch()['submission_count'];
    
    if ($submissionCount > 0 && !isset($input['force_delete'])) {
        http_response_code(400);
        echo json_encode([
            'error' => 'Cannot delete assignment with submissions',
            'message' => "This assignment has $submissionCount submissions. Use force_delete=true to delete anyway.",
            'submission_count' => $submissionCount
        ]);
        exit;
    }
    
    $db->beginTransaction();
    
    // Delete assignment submissions and their files
    if ($submissionCount > 0) {
        $getSubmissionsQuery = "SELECT file_path FROM assignment_submissions WHERE assignment_id = :assignment_id AND file_path IS NOT NULL";
        $getSubmissionsStmt = $db->prepare($getSubmissionsQuery);
        $getSubmissionsStmt->bindValue(':assignment_id', $input['assignment_id']);
        $getSubmissionsStmt->execute();
        
        while ($submission = $getSubmissionsStmt->fetch()) {
            $fileUpload->deleteFile($submission['file_path']);
        }
        
        $deleteSubmissionsQuery = "DELETE FROM assignment_submissions WHERE assignment_id = :assignment_id";
        $deleteSubmissionsStmt = $db->prepare($deleteSubmissionsQuery);
        $deleteSubmissionsStmt->bindValue(':assignment_id', $input['assignment_id']);
        $deleteSubmissionsStmt->execute();
    }
    
    // Delete assignment attachment
    if ($assignment['attachment']) {
        $fileUpload->deleteFile($assignment['attachment']);
    }
    
    // Delete assignment
    $deleteQuery = "DELETE FROM assignments WHERE id = :assignment_id";
    $deleteStmt = $db->prepare($deleteQuery);
    $deleteStmt->bindValue(':assignment_id', $input['assignment_id']);
    $deleteStmt->execute();
    
    // Log activity
    $logQuery = "INSERT INTO activity_logs (user_id, school_id, action, table_name, record_id, old_values, ip_address, user_agent) 
                 VALUES (:user_id, :school_id, 'delete_assignment', 'assignments', :record_id, :old_values, :ip, :user_agent)";
    $logStmt = $db->prepare($logQuery);
    $logStmt->bindValue(':user_id', $user['id']);
    $logStmt->bindValue(':school_id', $user['school_id']);
    $logStmt->bindValue(':record_id', $input['assignment_id']);
    $logStmt->bindValue(':old_values', json_encode($assignment));
    $logStmt->bindValue(':ip', $_SERVER['REMOTE_ADDR']);
    $logStmt->bindValue(':user_agent', $_SERVER['HTTP_USER_AGENT']);
    $logStmt->execute();
    
    $db->commit();
    
    echo json_encode([
        'success' => true,
        'message' => 'Assignment deleted successfully',
        'submissions_deleted' => $submissionCount
    ]);
    
} catch (Exception $e) {
    if (isset($db) && $db->inTransaction()) {
        $db->rollBack();
    }
    
    http_response_code(500);
    echo json_encode(['error' => 'Internal server error']);
}
?>
