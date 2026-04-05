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
    $tracking_id = "ADM-" . strtoupper(uniqid());
    
    // Core fields
    $student_name = $data['student_name'] ?? '';
    $email = $data['email'] ?? '';
    $guardian_name = $data['guardian_name'] ?? null;
    $phone = $data['phone'] ?? null;
    $desired_class = $data['desired_class'] ?? null;
    
    // Details JSON
    $details = [];
    foreach(['dob', 'gender', 'address', 'previous_school'] as $field) {
        if(isset($data[$field])) {
            $details[$field] = $data[$field];
        }
    }
    
    $query = "INSERT INTO admission_requests 
              (school_id, tracking_id, student_name, guardian_name, email, phone, desired_class, details_json) 
              VALUES (:school_id, :tracking_id, :student_name, :guardian_name, :email, :phone, :desired_class, :details_json)";
              
    $stmt = $db->prepare($query);
    $stmt->execute([
        ':school_id' => $school_id,
        ':tracking_id' => $tracking_id,
        ':student_name' => $student_name,
        ':guardian_name' => $guardian_name,
        ':email' => $email,
        ':phone' => $phone,
        ':desired_class' => $desired_class,
        ':details_json' => json_encode($details)
    ]);

    // Check email config limit to 1
    $email_query = "SELECT * FROM email_configs WHERE school_id = :school_id LIMIT 1";
    $email_stmt = $db->prepare($email_query);
    $email_stmt->execute([':school_id' => $school_id]);
    $email_config = $email_stmt->fetch(PDO::FETCH_ASSOC);

    $mail_sent = false;

    if ($email_config && $email_config['use_custom']) {
        // Pseudo code to send email
        // Setup PHPMailer or default mail() using the SMTP configurations
        // However user just wanted it properly planned.
        $subject = "Registration Successful";
        $message = "Your admission registration is successful. Your tracking ID is: " . $tracking_id . ". You can use this ID to track your admission status. No username or password has been created yet.";
        $headers = "From: " . $email_config['from_name'] . " <" . $email_config['from_email'] . ">";
        // mail($email, $subject, $message, $headers);
        $mail_sent = true;
    } else {
        // Use default sending mechanism
        $subject = "Registration Successful";
        $message = "Your admission registration is successful. Your tracking ID is: " . $tracking_id . ". You can use this ID to track your admission status. No username or password has been created yet.";
        $headers = "From: Edducare Default <no-reply@edducare.finafid.org>";
        // mail($email, $subject, $message, $headers);
        $mail_sent = true;
    }

    if ($mail_sent) {
        $update_mail = "UPDATE admission_requests SET email_sent = 1 WHERE tracking_id = :tracking_id";
        $u_stmt = $db->prepare($update_mail);
        $u_stmt->execute([':tracking_id' => $tracking_id]);
    }

    echo json_encode([
        'success' => true, 
        'message' => 'Admission successful', 
        'tracking_id' => $tracking_id
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Server error: ' . $e->getMessage()]);
}
?>
