<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

require_once '../../middleware/auth.php';
require_once '../../config/file_upload.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
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
    } else {
        $input = $_POST;
    }
    
    // Validate required fields
    $required = ['class_id', 'subject_id', 'title', 'description'];
    foreach ($required as $field) {
        if (!isset($input[$field]) || empty($input[$field])) {
            http_response_code(400);
            echo json_encode(['error' => "Field '$field' is required"]);
            exit;
        }
    }
    
    // Verify teacher is assigned to this class
    $verifyQuery = "SELECT ta.id FROM teacher_assignments ta
                   INNER JOIN classes c ON ta.class_id = c.id
                   WHERE ta.teacher_id = :teacher_id AND ta.class_id = :class_id 
                   AND c.school_id = :school_id";
    $verifyStmt = $db->prepare($verifyQuery);
    $verifyStmt->bindValue(':teacher_id', $user['id']);
    $verifyStmt->bindValue(':class_id', $input['class_id']);
    $verifyStmt->bindValue(':school_id', $user['school_id']);
    $verifyStmt->execute();
    
    if ($verifyStmt->rowCount() === 0) {
        http_response_code(403);
        echo json_encode(['error' => 'You are not assigned to this class']);
        exit;
    }
    
    $db->beginTransaction();
    
    // Handle file upload if present
    $attachmentPath = null;
    $attachmentName = null;
    $attachmentType = null;
    
    if (isset($_FILES['attachment']) && $_FILES['attachment']['error'] === UPLOAD_ERR_OK) {
        try {
            $uploadResult = $fileUpload->uploadFile($_FILES['attachment'], 'assignments');
            $attachmentPath = $uploadResult['relative_path'];
            $attachmentName = $uploadResult['original_name'];
            $attachmentType = $uploadResult['type'];
        } catch (Exception $e) {
            http_response_code(400);
            echo json_encode(['error' => 'File upload failed: ' . $e->getMessage()]);
            exit;
        }
    }
    
    // Insert assignment
    $insertQuery = "INSERT INTO assignments (
        teacher_id, class_id, section_id, subject_id, title, description, 
        due_date, max_marks, attachment
    ) VALUES (
        :teacher_id, :class_id, :section_id, :subject_id, :title, :description,
        :due_date, :max_marks, :attachment
    )";
    
    $insertStmt = $db->prepare($insertQuery);
    $insertStmt->bindValue(':teacher_id', $user['id']);
    $insertStmt->bindValue(':class_id', $input['class_id']);
    $insertStmt->bindValue(':section_id', $input['section_id'] ?? null);
    $insertStmt->bindValue(':subject_id', $input['subject_id']);
    $insertStmt->bindValue(':title', $input['title']);
    $insertStmt->bindValue(':description', $input['description']);
    $insertStmt->bindValue(':due_date', $input['due_date'] ?? null);
    $insertStmt->bindValue(':max_marks', $input['max_marks'] ?? 100);
    $insertStmt->bindValue(':attachment', $attachmentPath);
    
    $insertStmt->execute();
    $assignmentId = $db->lastInsertId();
    
    // Log activity
    $logQuery = "INSERT INTO activity_logs (user_id, school_id, action, table_name, record_id, new_values, ip_address, user_agent) 
                 VALUES (:user_id, :school_id, 'create_assignment', 'assignments', :record_id, :new_values, :ip, :user_agent)";
    $logStmt = $db->prepare($logQuery);
    $logStmt->bindValue(':user_id', $user['id']);
    $logStmt->bindValue(':school_id', $user['school_id']);
    $logStmt->bindValue(':record_id', $assignmentId);
    $logStmt->bindValue(':new_values', json_encode($input));
    $logStmt->bindValue(':ip', $_SERVER['REMOTE_ADDR']);
    $logStmt->bindValue(':user_agent', $_SERVER['HTTP_USER_AGENT']);
    $logStmt->execute();
    
    $db->commit();
    
    echo json_encode([
        'success' => true,
        'message' => 'Assignment created successfully',
        'assignment_id' => $assignmentId,
        'attachment' => $attachmentPath ? [
            'name' => $attachmentName,
            'type' => $attachmentType,
            'path' => $attachmentPath
        ] : null
    ]);
    
} catch (Exception $e) {
    if (isset($db) && $db->inTransaction()) {
        $db->rollBack();
    }
    
    // Clean up uploaded file if database operation failed
    if (isset($attachmentPath)) {
        $fileUpload->deleteFile($attachmentPath);
    }
    
    http_response_code(500);
    echo json_encode(['error' => 'Internal server error']);
}
?>
