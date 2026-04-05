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
    
    // HTML Email Template
    $site_url = "https://edducare.finafid.org";
    $html_message = "
    <html>
    <head>
      <style>
        body { font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif; background-color: #f4f7f6; color: #333; margin: 0; padding: 0; }
        .container { max-width: 600px; margin: 40px auto; background: #ffffff; border-radius: 8px; overflow: hidden; box-shadow: 0 4px 15px rgba(0,0,0,0.05); border: 1px solid #eaeaea; }
        .header { background: #3b82f6; padding: 30px 20px; text-align: center; color: #ffffff; }
        .header h1 { margin: 0; font-size: 24px; font-weight: 600; }
        .content { padding: 40px 30px; line-height: 1.6; }
        .content p { margin-bottom: 20px; font-size: 16px; }
        .tracking-box { background: #f8fafc; border: 2px dashed #cbd5e1; padding: 20px; text-align: center; border-radius: 8px; margin: 30px 0; }
        .tracking-box .label { text-transform: uppercase; font-size: 12px; color: #64748b; font-weight: bold; letter-spacing: 1px; margin-bottom: 8px; }
        .tracking-box .id { font-size: 28px; font-weight: 800; color: #0f172a; letter-spacing: 2px; }
        .footer { background: #f8fafc; padding: 20px; text-align: center; font-size: 13px; color: #94a3b8; border-top: 1px solid #f1f5f9; }
        .btn { display: inline-block; padding: 12px 24px; background: #3b82f6; color: #ffffff; text-decoration: none; border-radius: 6px; font-weight: bold; margin-top: 10px; }
      </style>
    </head>
    <body>
      <div class='container'>
        <div class='header'>
          <h1>Admission Request Received</h1>
        </div>
        <div class='content'>
          <p>Dear <strong>$student_name</strong>,</p>
          <p>Thank you for submitting your admission request. We have successfully received your details and our administration team will review your application shortly.</p>
          
          <div class='tracking-box'>
            <div class='label'>Your Tracking ID</div>
            <div class='id'>$tracking_id</div>
          </div>
          
          <p>Please keep this tracking ID secure. You can use it to track the status of your admission process. At this stage, no student portal account has been created for you yet. We will contact you regarding the next steps.</p>
          
          <div style='text-align: center; margin-top: 30px;'>
            <a href='$site_url' class='btn'>Visit Our Website</a>
          </div>
        </div>
        <div class='footer'>
          &copy; " . date('Y') . " Edducare. All rights reserved.<br>
          This is an automated message, please do not reply directly to this email.
        </div>
      </div>
    </body>
    </html>
    ";

    $subject = "Registration Successful - Tracking ID: " . $tracking_id;

    if ($email_config && $email_config['use_custom']) {
        // Setup PHPMailer or default mail() using the SMTP configurations
        $headers  = "MIME-Version: 1.0\r\n";
        $headers .= "Content-type: text/html; charset=UTF-8\r\n";
        $headers .= "From: " . $email_config['from_name'] . " <" . $email_config['from_email'] . ">\r\n";
        
        // mail($email, $subject, $html_message, $headers);
        $mail_sent = true;
    } else {
        // Use default sending mechanism
        $headers  = "MIME-Version: 1.0\r\n";
        $headers .= "Content-type: text/html; charset=UTF-8\r\n";
        $headers .= "From: Edducare Admissions <no-reply@edducare.finafid.org>\r\n";
        
        // mail($email, $subject, $html_message, $headers);
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
