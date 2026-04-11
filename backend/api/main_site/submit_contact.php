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

try {
    $school_name = $data['school_name'] ?? '';
    $email = $data['email'] ?? '';
    $phone = $data['phone'] ?? null;
    $plan = $data['plan'] ?? 'Professional';
    $message = $data['message'] ?? '';

    if (empty($school_name) || empty($email) || empty($message)) {
        http_response_code(400);
        echo json_encode(['error' => 'School Name, Email and Message are required']);
        exit;
    }
    
    $query = "INSERT INTO main_site_contacts (school_name, email, phone, plan, message) 
              VALUES (:school_name, :email, :phone, :plan, :message)";
              
    $stmt = $db->prepare($query);
    $success = $stmt->execute([
        ':school_name' => $school_name,
        ':email' => $email,
        ':phone' => $phone,
        ':plan' => $plan,
        ':message' => $message
    ]);

    if ($success) {
        echo json_encode(['success' => true, 'message' => 'Partner request submitted successfully']);
    } else {
        throw new Exception("Database execution failed");
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Server error: ' . $e->getMessage()]);
}
?>
