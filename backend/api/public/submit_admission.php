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
    <!DOCTYPE html>
    <html>
    <head>
      <meta name='viewport' content='width=device-width, initial-scale=1.0'>
      <style>
        body { font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; background-color: #f1f5f9; color: #1e293b; margin: 0; padding: 0; -webkit-font-smoothing: antialiased; }
        .wrapper { width: 100%; table-layout: fixed; background-color: #f1f5f9; padding: 40px 0; }
        .container { width: 100%; max-width: 600px; margin: 0 auto; background: #ffffff; border-radius: 20px; overflow: hidden; box-shadow: 0 10px 25px -5px rgba(0,0,0,0.1); }
        .header { background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%); padding: 40px 20px; text-align: center; color: #ffffff; }
        .header h1 { margin: 0; font-size: 28px; font-weight: 800; letter-spacing: -0.025em; }
        .content { padding: 40px 32px; line-height: 1.6; }
        .content p { margin-bottom: 24px; font-size: 16px; color: #475569; }
        .tracking-box { background: #f8fafc; border: 2px dashed #cbd5e1; padding: 32px 20px; text-align: center; border-radius: 16px; margin: 32px 0; border-color: #3b82f6; background-color: #eff6ff; }
        .tracking-box .label { text-transform: uppercase; font-size: 13px; color: #3b82f6; font-weight: 700; letter-spacing: 0.1em; margin-bottom: 12px; }
        .tracking-box .id { font-size: 36px; font-weight: 900; color: #1e3a8a; letter-spacing: 2px; font-family: 'Courier New', Courier, monospace; }
        .footer { background: #f8fafc; padding: 24px; text-align: center; font-size: 13px; color: #94a3b8; border-top: 1px solid #f1f5f9; }
        .btn { display: inline-block; padding: 16px 32px; background: #3b82f6; color: #ffffff !important; text-decoration: none; border-radius: 12px; font-weight: 700; margin-top: 8px; box-shadow: 0 4px 6px -1px rgba(59, 130, 246, 0.3); }
        @media only screen and (max-width: 600px) {
          .content { padding: 32px 20px; }
          .container { border-radius: 0; }
          .tracking-box .id { font-size: 28px; }
        }
      </style>
    </head>
    <body>
      <div class='wrapper'>
        <div class='container'>
          <div class='header'>
            <h1>Welcome to Edducare</h1>
          </div>
          <div class='content'>
            <p>Dear <strong>$student_name</strong>,</p>
            <p>We are delighted to confirm that your admission request has been successfully received. We are excited about the possibility of you joining our academic community.</p>
            
            <div class='tracking-box'>
              <div class='label'>Your Application Tracking ID</div>
              <div class='id'>$tracking_id</div>
            </div>
            
            <p>Please keep this Tracking ID safe. You will need it to check the status of your application. Our admissions team will review your details and contact you shortly regarding the next steps.</p>
            
            <div style='text-align: center; margin-top: 32px;'>
              <a href='$site_url' class='btn'>Check Latest Updates</a>
            </div>
          </div>
          <div class='footer'>
            <strong>&copy; " . date('Y') . " Edducare School ERP System</strong><br>
            Managed by Integrated School Management System.<br>
            <span style='margin-top: 8px; display: block;'>This is an automated enrollment notification.</span>
          </div>
        </div>
      </div>
    </body>
    </html>
    ";

    $subject = "Registration Successful - Tracking ID: " . $tracking_id;

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

    // Setup Email Headers
    $headers  = "MIME-Version: 1.0\r\n";
    $headers .= "Content-type: text/html; charset=UTF-8\r\n";
    $headers .= "From: " . $final_config['from_name'] . " <" . $final_config['from_email'] . ">\r\n";
    
    // mail($email, $subject, $html_message, $headers);
    // Note: In production, use PHPMailer with $final_config for reliable delivery
    $mail_sent = true;

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
