<?php
require_once '../../middleware/auth.php';
require_once '../../config/file_upload.php';

$auth = new AuthMiddleware();
$user = $auth->requireRole(['school_admin', 'class_teacher']);

if (!$user) {
    exit;
}

if (!isset($_GET['file'])) {
    http_response_code(400);
    echo json_encode(['error' => 'File parameter is required']);
    exit;
}

$database = new Database();
$db = $database->getConnection();
$fileUpload = new FileUpload();

try {
    $filePath = $_GET['file'];
    
    // Verify file belongs to an assignment in the user's school
    $verifyQuery = "SELECT a.attachment_name, c.school_id 
                   FROM assignments a
                   INNER JOIN classes c ON a.class_id = c.id
                   WHERE a.attachment_path = :file_path AND c.school_id = :school_id";
    
    // Add teacher-specific filter if not school admin
    if ($user['role'] === 'class_teacher') {
        $verifyQuery .= " AND a.teacher_id = :teacher_id";
    }
    
    $verifyStmt = $db->prepare($verifyQuery);
    $verifyStmt->bindValue(':file_path', $filePath);
    $verifyStmt->bindValue(':school_id', $user['school_id']);
    
    if ($user['role'] === 'class_teacher') {
        $verifyStmt->bindValue(':teacher_id', $user['id']);
    }
    
    $verifyStmt->execute();
    
    if ($verifyStmt->rowCount() === 0) {
        http_response_code(404);
        echo json_encode(['error' => 'File not found or access denied']);
        exit;
    }
    
    $fileInfo = $verifyStmt->fetch();
    
    // Log file access
    $logQuery = "INSERT INTO activity_logs (user_id, school_id, action, new_values, ip_address, user_agent) 
                 VALUES (:user_id, :school_id, 'download_assignment_file', :new_values, :ip, :user_agent)";
    $logStmt = $db->prepare($logQuery);
    $logStmt->bindValue(':user_id', $user['id']);
    $logStmt->bindValue(':school_id', $user['school_id']);
    $logStmt->bindValue(':new_values', json_encode(['file' => $filePath]));
    $logStmt->bindValue(':ip', $_SERVER['REMOTE_ADDR']);
    $logStmt->bindValue(':user_agent', $_SERVER['HTTP_USER_AGENT']);
    $logStmt->execute();
    
    // Serve the file
    $fileUpload->serveFile($filePath, $fileInfo['attachment_name']);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Internal server error']);
}
?>
