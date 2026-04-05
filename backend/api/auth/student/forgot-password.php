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
    
    if (!$email) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid email format']);
        exit;
    }
    
    // Check if student exists with school code validation
    $studentQuery = "SELECT s.id, s.email, s.first_name, s.last_name, sc.code as school_code
                     FROM students s 
                     JOIN schools sc ON s.school_id = sc.id 
                     WHERE s.email = :email AND sc.code = :school_code AND s.status = 'active'";
    $studentStmt = $db->prepare($studentQuery);
    $studentStmt->bindValue(':email', $email);
    $studentStmt->bindValue(':school_code', $school_code);
    $studentStmt->execute();
    
    if ($studentStmt->rowCount() === 0) {
        // Don't reveal if email exists or not for security
        echo json_encode([
            'success' => true,
            'message' => 'If the email exists in our system, you will receive a password reset link.'
        ]);
        exit;
    }
    
    $student = $studentStmt->fetch();
    
    // Check rate limiting (max 3 reset requests per hour)
    $rateLimitQuery = "SELECT COUNT(*) as count FROM password_reset_tokens 
                       WHERE email = :email AND user_type = 'student' AND created_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)";
    $rateLimitStmt = $db->prepare($rateLimitQuery);
    $rateLimitStmt->bindValue(':email', $email);
    $rateLimitStmt->execute();
    $rateLimitResult = $rateLimitStmt->fetch();
    
    if ($rateLimitResult['count'] >= 3) {
        http_response_code(429);
        echo json_encode(['error' => 'Too many reset requests. Please try again after 1 hour.']);
        exit;
    }
    
    // Generate reset token
    $resetToken = bin2hex(random_bytes(32));
    
    // Store reset token
    $tokenQuery = "INSERT INTO password_reset_tokens (student_id, email, token, user_type, expires_at) 
                   VALUES (:student_id, :email, :token, 'student', DATE_ADD(NOW(), INTERVAL 1 HOUR))";
    $tokenStmt = $db->prepare($tokenQuery);
    $tokenStmt->bindValue(':student_id', $student['id']);
    $tokenStmt->bindValue(':email', $email);
    $tokenStmt->bindValue(':token', $resetToken);
    
    if (!$tokenStmt->execute()) {
        throw new Exception('Token generation failed');
    }
    
    // Send reset email
    $emailService = new EmailService();
    $emailSent = $emailService->sendPasswordResetEmail($email, $resetToken);
    
    echo json_encode([
        'success' => true,
        'message' => 'Password reset link has been sent to your email.'
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Password reset failed: ' . $e->getMessage()
    ]);
}
?>
