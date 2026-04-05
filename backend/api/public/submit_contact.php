<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit;
}

require_once '../../config/database.php';

$database = new Database();
$db = $database->getConnection();

$data = json_decode(file_get_contents("php://input"), true);
$school_id = isset($data['school_id']) ? (int)$data['school_id'] : null;

if (!$school_id) {
    http_response_code(400);
    echo json_encode(['error' => 'School ID is required']);
    exit;
}

try {
    $full_name = $data['full_name'] ?? '';
    $email = $data['email'] ?? '';
    $phone = $data['phone'] ?? null;
    $subject = $data['subject'] ?? 'General Inquiry';
    $message = $data['message'] ?? '';

    if (empty($full_name) || empty($email) || empty($message)) {
        http_response_code(400);
        echo json_encode(['error' => 'Name, Email and Message are required']);
        exit;
    }
    
    $query = "INSERT INTO contact_messages (school_id, full_name, email, phone, subject, message) 
              VALUES (:school_id, :full_name, :email, :phone, :subject, :message)";
              
    $stmt = $db->prepare($query);
    $success = $stmt->execute([
        ':school_id' => $school_id,
        ':full_name' => $full_name,
        ':email' => $email,
        ':phone' => $phone,
        ':subject' => $subject,
        ':message' => $message
    ]);

    if ($success) {
        echo json_encode(['success' => true, 'message' => 'Message sent successfully']);
    } else {
        throw new Exception("Database execution failed");
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Server error: ' . $e->getMessage()]);
}
?>
