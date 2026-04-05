<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

require_once '../../../config/database.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);

if (!isset($input['token']) || !isset($input['password'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Token and new password are required']);
    exit;
}

$database = new Database();
$db = $database->getConnection();

try {
    $token = trim($input['token']);
    $newPassword = $input['password'];
    
    // Validate password strength
    if (strlen($newPassword) < 8) {
        http_response_code(400);
        echo json_encode(['error' => 'Password must be at least 8 characters long']);
        exit;
    }
    
    // Verify token
    $tokenQuery = "SELECT prt.*, s.id as student_id, s.email 
                   FROM password_reset_tokens prt
                   JOIN students s ON prt.student_id = s.id
                   WHERE prt.token = :token AND prt.user_type = 'student' 
                   AND prt.expires_at > NOW() AND prt.is_used = FALSE";
    $tokenStmt = $db->prepare($tokenQuery);
    $tokenStmt->bindValue(':token', $token);
    $tokenStmt->execute();
    
    if ($tokenStmt->rowCount() === 0) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid or expired reset token']);
        exit;
    }
    
    $tokenData = $tokenStmt->fetch();
    
    // Hash new password
    $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
    
    // Update student password
    $updateQuery = "UPDATE students SET password_hash = :password WHERE id = :student_id";
    $updateStmt = $db->prepare($updateQuery);
    $updateStmt->bindValue(':password', $hashedPassword);
    $updateStmt->bindValue(':student_id', $tokenData['student_id']);
    $updateStmt->execute();
    
    // Mark token as used
    $markUsedQuery = "UPDATE password_reset_tokens SET is_used = TRUE WHERE id = :token_id";
    $markUsedStmt = $db->prepare($markUsedQuery);
    $markUsedStmt->bindValue(':token_id', $tokenData['id']);
    $markUsedStmt->execute();
    
    // Log activity
    $logQuery = "INSERT INTO activity_logs (student_id, user_type, action, entity_type, entity_id, ip_address) 
                 VALUES (:student_id, 'student', 'password_reset', 'student', :student_id, :ip_address)";
    $logStmt = $db->prepare($logQuery);
    $logStmt->bindValue(':student_id', $tokenData['student_id']);
    $logStmt->bindValue(':ip_address', $_SERVER['REMOTE_ADDR'] ?? null);
    $logStmt->execute();
    
    echo json_encode([
        'success' => true,
        'message' => 'Password has been reset successfully. You can now login with your new password.'
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Password reset failed: ' . $e->getMessage()
    ]);
}
?>
