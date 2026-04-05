<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: PUT, POST');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

require_once '../../middleware/auth.php';
require_once '../../config/file_upload.php';

if (!in_array($_SERVER['REQUEST_METHOD'], ['PUT', 'POST'])) {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$auth = new AuthMiddleware();
$user = $auth->requireRole(['class_teacher']);

if (!$user) {
    exit;
}

$database = new Database();
$db = $database->getConnection();
$fileUpload = new FileUpload();

try {
    // Handle both JSON and form data
    $input = [];
    if (isset($_POST['data'])) {
        $input = json_decode($_POST['data'], true);
    } else if ($_SERVER['REQUEST_METHOD'] === 'PUT') {
        $input = json_decode(file_get_contents('php://input'), true);
    } else {
        $input = $_POST;
    }
    
    if (!isset($input['assignment_id'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Assignment ID is required']);
        exit;
    }
    
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
    
    $currentAssignment = $verifyStmt->fetch();
    
    $db->beginTransaction();
    
    // Prepare update fields
    $updateFields = [];
    $params = [':assignment_id' => $input['assignment_id']];
    
    if (isset($input['title'])) {
        $updateFields[] = "title = :title";
        $params[':title'] = $input['title'];
    }
    
    if (isset($input['description'])) {
        $updateFields[] = "description = :description";
        $params[':description'] = $input['description'];
    }
    
    if (isset($input['subject_id'])) {
        $updateFields[] = "subject_id = :subject_id";
        $params[':subject_id'] = $input['subject_id'];
    }
    
    if (isset($input['due_date'])) {
        $updateFields[] = "due_date = :due_date";
        $params[':due_date'] = $input['due_date'];
    }
    
    if (isset($input['max_marks'])) {
        $updateFields[] = "max_marks = :max_marks";
        $params[':max_marks'] = $input['max_marks'];
    }
    
    if (isset($input['section_id'])) {
        $updateFields[] = "section_id = :section_id";
        $params[':section_id'] = $input['section_id'];
    }
    
    // Handle file upload if present
    if (isset($_FILES['attachment']) && $_FILES['attachment']['error'] === UPLOAD_ERR_OK) {
        try {
            $uploadResult = $fileUpload->uploadFile($_FILES['attachment'], 'assignments');
            
            // Delete old attachment if exists
            if ($currentAssignment['attachment_path']) {
                $fileUpload->deleteFile($currentAssignment['attachment_path']);
            }
            
            $updateFields[] = "attachment = :attachment";
            $params[':attachment'] = $uploadResult['relative_path'];
            
        } catch (Exception $e) {
            http_response_code(400);
            echo json_encode(['error' => 'File upload failed: ' . $e->getMessage()]);
            exit;
        }
    }
    
    // Handle attachment removal
    if (isset($input['remove_attachment']) && $input['remove_attachment'] === true) {
        if ($currentAssignment['attachment_path']) {
            $fileUpload->deleteFile($currentAssignment['attachment_path']);
        }
        
        $updateFields[] = "attachment = NULL";
    }
    
    if (empty($updateFields)) {
        http_response_code(400);
        echo json_encode(['error' => 'No fields to update']);
        exit;
    }
    
    // Update assignment
    $updateQuery = "UPDATE assignments SET " . implode(', ', $updateFields) . " WHERE id = :assignment_id";
    $updateStmt = $db->prepare($updateQuery);
    
    foreach ($params as $key => $value) {
        $updateStmt->bindValue($key, $value);
    }
    
    $updateStmt->execute();
    
    // Log activity
    $logQuery = "INSERT INTO activity_logs (user_id, school_id, action, table_name, record_id, old_values, new_values, ip_address, user_agent) 
                 VALUES (:user_id, :school_id, 'update_assignment', 'assignments', :record_id, :old_values, :new_values, :ip, :user_agent)";
    $logStmt = $db->prepare($logQuery);
    $logStmt->bindValue(':user_id', $user['id']);
    $logStmt->bindValue(':school_id', $user['school_id']);
    $logStmt->bindValue(':record_id', $input['assignment_id']);
    $logStmt->bindValue(':old_values', json_encode($currentAssignment));
    $logStmt->bindValue(':new_values', json_encode($input));
    $logStmt->bindValue(':ip', $_SERVER['REMOTE_ADDR']);
    $logStmt->bindValue(':user_agent', $_SERVER['HTTP_USER_AGENT']);
    $logStmt->execute();
    
    $db->commit();
    
    echo json_encode([
        'success' => true,
        'message' => 'Assignment updated successfully'
    ]);
    
} catch (Exception $e) {
    if (isset($db) && $db->inTransaction()) {
        $db->rollBack();
    }
    
    http_response_code(500);
    echo json_encode(['error' => 'Internal server error']);
}
?>
