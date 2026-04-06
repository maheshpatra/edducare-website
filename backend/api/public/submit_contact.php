<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit;
}

require_once '../../config/config.php';
require_once '../../config/database.php';
require_once '../../includes/mailer.php';

// Enable error reporting for debugging
ini_set('display_errors', 1);
error_reporting(E_ALL);

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
        // Find email config for notifications
        $email_query = "SELECT * FROM email_configs WHERE school_id = :school_id LIMIT 1";
        $email_stmt = $db->prepare($email_query);
        $email_stmt->execute([':school_id' => $school_id]);
        $email_config = $email_stmt->fetch(PDO::FETCH_ASSOC);

        $final_config = [
            'from_name' => FROM_NAME,
            'from_email' => FROM_EMAIL,
            'smtp_host' => SMTP_HOST,
            'smtp_port' => SMTP_PORT,
            'smtp_user' => SMTP_USERNAME,
            'smtp_pass' => SMTP_PASSWORD
        ];

        if ($email_config && $email_config['use_custom']) {
            foreach(['from_name', 'from_email', 'smtp_host', 'smtp_port', 'smtp_user', 'smtp_pass'] as $key) {
                if(!empty($email_config[$key])) $final_config[$key] = $email_config[$key];
            }
        }

        // Notification Message
        $notif_subject = "New Website Inquiry: " . $subject;
        $notif_body = "<h1>New Website Message</h1>" .
                      "<p><strong>From:</strong> $full_name ($email)</p>" .
                      "<p><strong>Subject:</strong> $subject</p>" .
                      "<p><strong>Message:</strong></p><p>$message</p>";

        // Send to School Email
        CustomMailer::send($final_config['from_email'], $notif_subject, $notif_body, [
            'host' => $final_config['smtp_host'],
            'port' => $final_config['smtp_port'],
            'user' => $final_config['smtp_user'],
            'pass' => $final_config['smtp_pass'],
            'from_name' => "Edducare Portal",
            'from_email' => $final_config['from_email']
        ]);

        echo json_encode(['success' => true, 'message' => 'Message sent successfully']);
    } else {
        throw new Exception("Database execution failed");
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Server error: ' . $e->getMessage()]);
}
?>
