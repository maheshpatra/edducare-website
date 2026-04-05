<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

require_once '../../../config/database.php';
require_once '../../../config/email.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);

if (!isset($input['email']) || !isset($input['school_code'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Email and school code are required']);
    exit;
}

$database = new Database();
$db = $database->getConnection();

try {
    $email = filter_var($input['email'], FILTER_VALIDATE_EMAIL);
    $school_code = trim($input['school_code']);
    $purpose = isset($input['purpose']) ? $input['purpose'] : 'verification';
    
    if (!$email) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid email format']);
        exit;
    }
    
    // Validate purpose
    $validPurposes = ['verification', 'password_reset', 'login'];
    if (!in_array($purpose, $validPurposes)) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid OTP purpose']);
        exit;
    }
    
    // Check if student exists with school code validation
    $studentQuery = "SELECT s.id, s.email, s.first_name, s.last_name 
                     FROM students s 
                     JOIN schools sc ON s.school_id = sc.id 
                     WHERE s.email = :email AND sc.code = :school_code AND s.status = 'active'";
    $studentStmt = $db->prepare($studentQuery);
    $studentStmt->bindValue(':email', $email);
    $studentStmt->bindValue(':school_code', $school_code);
    $studentStmt->execute();
    
    if ($studentStmt->rowCount() === 0) {
        http_response_code(404);
        echo json_encode(['error' => 'Student not found or invalid school code']);
        exit;
    }
    
    // Check rate limiting (max 5 OTP requests per hour)
    $rateLimitQuery = "SELECT COUNT(*) as count FROM email_otps 
                       WHERE email = :email AND purpose = :purpose AND created_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)";
    $rateLimitStmt = $db->prepare($rateLimitQuery);
    $rateLimitStmt->bindValue(':email', $email);
    $rateLimitStmt->bindValue(':purpose', $purpose);
    $rateLimitStmt->execute();
    $rateLimitResult = $rateLimitStmt->fetch();
    
    if ($rateLimitResult['count'] >= 5) {
        http_response_code(429);
        echo json_encode(['error' => 'Too many OTP requests. Please try again after 1 hour.']);
        exit;
    }
    
    // Generate 6-digit OTP
    $otp = sprintf('%06d', mt_rand(100000, 999999));
    
    // Send OTP email
    $emailService = new EmailService();
    $emailSent = $emailService->sendOTP($email, $otp, $purpose);
    
    if ($emailSent) {
        echo json_encode([
            'success' => true,
            'message' => 'OTP has been sent to your email address.',
            'expires_in' => 600 // 10 minutes
        ]);
    } else {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'error' => 'Failed to send OTP. Please try again.'
        ]);
    }
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'OTP sending failed: ' . $e->getMessage()
    ]);
}
?>
