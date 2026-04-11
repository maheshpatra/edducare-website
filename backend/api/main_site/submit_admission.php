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

// Ensure uploads directory exists
$upload_dir = '../../uploads/';
if (!is_dir($upload_dir)) {
    mkdir($upload_dir, 0777, true);
}

$data = json_decode(file_get_contents("php://input"), true);
$school_id = isset($data['school_id']) ? (int)$data['school_id'] : null;

if (!$school_id) {
    http_response_code(400);
    echo json_encode(['error' => 'School ID is required']);
    exit;
}

// Force schema update
$queries = [
    "ALTER TABLE admission_requests ADD utr_number VARCHAR(100) NULL",
    "ALTER TABLE admission_requests ADD payment_method VARCHAR(50) NULL",
    "ALTER TABLE admission_requests ADD payment_status ENUM('pending', 'verified', 'rejected') DEFAULT 'pending'"
];

foreach ($queries as $q) {
    try {
        $db->exec($q);
    } catch (Exception $e) {
        // This will often fail with "Duplicate column name" if already present, which is fine
        file_put_contents('../../uploads/debug_admission.log', date('Y-m-d H:i:s') . " - Migration Query: $q - Code: " . $e->getCode() . " - Result: " . $e->getMessage() . "\n", FILE_APPEND);
    }
}

try {
    // Debug logging
    file_put_contents('../../uploads/debug_admission.log', date('Y-m-d H:i:s') . " - Request: " . json_encode($data) . "\n", FILE_APPEND);
    
    $tracking_id = "ADM-" . strtoupper(uniqid());
    
    // Core fields
    $student_name = $data['student_name'] ?? '';
    $email = $data['email'] ?? '';
    $guardian_name = $data['guardian_name'] ?? null;
    $phone = $data['phone'] ?? null;
    $desired_class = $data['desired_class'] ?? null;
    $utr_number = $data['utr_number'] ?? null;
    $payment_method = $data['payment_method'] ?? null;
    
    // Details JSON
    $details = [];
    foreach(['dob', 'gender', 'address', 'previous_school'] as $field) {
        if(isset($data[$field])) {
            $details[$field] = $data[$field];
        }
    }
    
    $query = "INSERT INTO admission_requests 
              (school_id, tracking_id, student_name, guardian_name, email, phone, desired_class, details_json, utr_number, payment_method, payment_status) 
              VALUES (:school_id, :tracking_id, :student_name, :guardian_name, :email, :phone, :desired_class, :details_json, :utr_number, :payment_method, :payment_status)";
              
    // Logic: Razorpay submits AFTER success, so it's verified. 
    // PayU submits BEFORE success, so it's pending until our callback updates it.
    $payment_status = ($payment_method === 'razorpay') ? 'verified' : 'pending';

    $stmt = $db->prepare($query);
    $stmt->execute([
        ':school_id' => $school_id,
        ':tracking_id' => $tracking_id,
        ':student_name' => $student_name,
        ':guardian_name' => $guardian_name,
        ':email' => $email,
        ':phone' => $phone,
        ':desired_class' => $desired_class,
        ':details_json' => json_encode($details),
        ':utr_number' => $utr_number,
        ':payment_method' => $payment_method,
        ':payment_status' => $payment_status
    ]);

    // Check email config limit to 1
    $email_query = "SELECT * FROM email_configs WHERE school_id = :school_id LIMIT 1";
    $email_stmt = $db->prepare($email_query);
    $email_stmt->execute([':school_id' => $school_id]);
    $email_config = $email_stmt->fetch(PDO::FETCH_ASSOC);

    $mail_sent = false;
    
    // Build payment receipt section for online payments
    $payment_section = '';
    if ($payment_status === 'verified' && $utr_number) {
        $method_display = ucfirst(str_replace('_', ' ', $payment_method ?? 'Online'));
        $payment_section = "
            <div style='background:#f0fdf4; border:2px solid #bbf7d0; border-radius:16px; padding:24px; margin:24px 0;'>
              <div style='text-align:center; margin-bottom:16px;'>
                <span style='display:inline-block; background:#10b981; color:#fff; padding:6px 16px; border-radius:20px; font-size:12px; font-weight:800;'>✅ PAYMENT VERIFIED</span>
              </div>
              <div style='border-bottom:1px solid #dcfce7; padding:10px 0; display:flex; justify-content:space-between; font-size:14px;'>
                <span style='color:#64748b'>Payment Method</span>
                <span style='font-weight:700'>$method_display</span>
              </div>
              <div style='border-bottom:1px solid #dcfce7; padding:10px 0; display:flex; justify-content:space-between; font-size:14px;'>
                <span style='color:#64748b'>Transaction ID</span>
                <span style='font-weight:700; font-family:monospace'>$utr_number</span>
              </div>
              <div style='padding:10px 0; display:flex; justify-content:space-between; font-size:14px;'>
                <span style='color:#64748b'>Status</span>
                <span style='font-weight:700; color:#10b981'>Verified</span>
              </div>
            </div>
        ";
    } elseif ($payment_method === 'upi_qr' && $utr_number) {
        $payment_section = "
            <div style='background:#fffbeb; border:2px solid #fde68a; border-radius:16px; padding:24px; margin:24px 0;'>
              <div style='text-align:center; margin-bottom:12px;'>
                <span style='display:inline-block; background:#f59e0b; color:#fff; padding:6px 16px; border-radius:20px; font-size:12px; font-weight:800;'>⏳ PAYMENT UNDER REVIEW</span>
              </div>
              <p style='text-align:center; font-size:13px; color:#92400e; margin:0;'>Your UPI payment (UTR: <strong>$utr_number</strong>) is being verified by the school administration.</p>
            </div>
        ";
    }

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
        .header h1 { margin: 0 0 6px; font-size: 26px; font-weight: 800; letter-spacing: -0.025em; }
        .header p { margin: 0; font-size: 14px; opacity: 0.9; }
        .content { padding: 40px 32px; line-height: 1.6; }
        .content p { margin-bottom: 20px; font-size: 15px; color: #475569; }
        .tracking-box { background: #eff6ff; border: 2px dashed #3b82f6; padding: 28px 20px; text-align: center; border-radius: 16px; margin: 28px 0; }
        .tracking-box .label { text-transform: uppercase; font-size: 12px; color: #3b82f6; font-weight: 700; letter-spacing: 0.1em; margin-bottom: 10px; }
        .tracking-box .id { font-size: 32px; font-weight: 900; color: #1e3a8a; letter-spacing: 2px; font-family: 'Courier New', Courier, monospace; }
        .footer { background: #f8fafc; padding: 24px 32px; text-align: center; font-size: 12px; color: #94a3b8; border-top: 1px solid #f1f5f9; }
        .btn { display: inline-block; padding: 14px 28px; background: #3b82f6; color: #ffffff !important; text-decoration: none; border-radius: 12px; font-weight: 700; font-size: 14px; box-shadow: 0 4px 6px -1px rgba(59, 130, 246, 0.3); }
        @media only screen and (max-width: 600px) {
          .content { padding: 28px 20px; }
          .container { border-radius: 0; }
          .tracking-box .id { font-size: 24px; }
        }
      </style>
    </head>
    <body>
      <div class='wrapper'>
        <div class='container'>
          <div class='header'>
            <h1>Application Received!</h1>
            <p>Your admission request has been submitted successfully</p>
          </div>
          <div class='content'>
            <p>Dear <strong>$student_name</strong>,</p>
            <p>We are delighted to confirm that your admission request has been successfully received. We are excited about the possibility of you joining our academic community.</p>
            
            <div class='tracking-box'>
              <div class='label'>Your Application Tracking ID</div>
              <div class='id'>$tracking_id</div>
            </div>

            $payment_section
            
            <p>Please keep this Tracking ID safe. You will need it to check the status of your application. Our admissions team will review your details and contact you shortly.</p>
            
            <div style='text-align: center; margin-top: 28px;'>
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
    
    $mail_sent = CustomMailer::send($email, $subject, $html_message, [
        'host' => $final_config['smtp_host'],
        'port' => $final_config['smtp_port'],
        'user' => $final_config['smtp_user'],
        'pass' => $final_config['smtp_pass'],
        'from_name' => $final_config['from_name'],
        'from_email' => $final_config['from_email']
    ]);

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
    file_put_contents('../../uploads/debug_admission.log', date('Y-m-d H:i:s') . " - ERROR: " . $e->getMessage() . "\n", FILE_APPEND);
    http_response_code(500);
    echo json_encode(['error' => 'Server error: ' . $e->getMessage()]);
}
?>
