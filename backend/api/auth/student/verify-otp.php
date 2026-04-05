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

if (!isset($input['email']) || !isset($input['otp']) || !isset($input['school_code'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Email, OTP, and school code are required']);
    exit;
}

$database = new Database();
$db = $database->getConnection();

try {
    $email = filter_var($input['email'], FILTER_VALIDATE_EMAIL);
    $otp = trim($input['otp']);
    $school_code = trim($input['school_code']);
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
    
    // Verify OTP
    $emailService = new EmailService();
    $isValid = $emailService->verifyOTP($email, $otp, $purpose);
    
    if ($isValid) {
        // Log successful verification
        $student = $studentStmt->fetch();
        $logQuery = "INSERT INTO activity_logs (student_id, user_type, action, entity_type, entity_id, ip_address) 
                     VALUES (:student_id, 'student', 'otp_verified', 'student', :student_id, :ip_address)";
        $logStmt = $db->prepare($logQuery);
        $logStmt->bindValue(':student_id', $student['id']);
        $logStmt->bindValue(':ip_address', $_SERVER['REMOTE_ADDR'] ?? null);
        $logStmt->execute();
        
        echo json_encode([
            'success' => true,
            'message' => 'OTP verified successfully.',
            'student_id' => $student['id']
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
