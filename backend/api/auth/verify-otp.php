<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

require_once '../../config/database.php';
require_once '../../config/email.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);

if (!isset($input['email']) || !isset($input['otp'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Email and OTP are required']);
    exit;
}

$database = new Database();
$db = $database->getConnection();

try {
    $email = filter_var($input['email'], FILTER_VALIDATE_EMAIL);
    $otp = trim($input['otp']);
    $purpose = isset($input['purpose']) ? $input['purpose'] : 'verification';
    
    if (!$email) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid email format']);
        exit;
    }
    
    // Validate OTP format (6 digits)
    if (!preg_match('/^\d{6}$/', $otp)) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid OTP format']);
        exit;
    }
    
    // Check if user exists (users table only)
    $userQuery = "SELECT id, email, first_name, last_name FROM users WHERE email = :email AND is_active = 1";
    $userStmt = $db->prepare($userQuery);
    $userStmt->bindValue(':email', $email);
    $userStmt->execute();
    
    if ($userStmt->rowCount() === 0) {
        http_response_code(404);
        echo json_encode(['error' => 'User not found']);
        exit;
    }
    
    // Verify OTP
    $emailService = new EmailService();
    $isValid = $emailService->verifyOTP($email, $otp, $purpose);
    
    if ($isValid) {
        // Log successful verification
        $user = $userStmt->fetch();
        $logQuery = "INSERT INTO activity_logs (user_id, user_type, action, entity_type, entity_id, ip_address) 
                     VALUES (:user_id, 'user', 'otp_verified', 'user', :user_id, :ip_address)";
        $logStmt = $db->prepare($logQuery);
        $logStmt->bindValue(':user_id', $user['id']);
        $logStmt->bindValue(':ip_address', $_SERVER['REMOTE_ADDR'] ?? null);
        $logStmt->execute();
        
        echo json_encode([
            'success' => true,
            'message' => 'OTP verified successfully.',
            'user_id' => $user['id']
        ]);
    } else {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => 'Invalid or expired OTP'
        ]);
    }
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'OTP verification failed: ' . $e->getMessage()
    ]);
}
?>
