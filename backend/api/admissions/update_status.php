<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once '../../middleware/auth.php';
require_once '../../config/database.php';
require_once '../../includes/mailer.php';
require_once '../../config/config.php';

$auth = new AuthMiddleware();
$user = $auth->requireRole(['super_admin', 'school_admin']);

if (!$user) {
    exit;
}

$database = new Database();
$db = $database->getConnection();

$input = json_decode(file_get_contents('php://input'), true);

$id = $input['id'] ?? null;
if (!$id) {
    http_response_code(400);
    echo json_encode(['error' => 'Admission request ID is required']);
    exit;
}

// Verify the request belongs to this school
$check = $db->prepare("SELECT * FROM admission_requests WHERE id = :id AND school_id = :school_id");
$check->execute([':id' => $id, ':school_id' => $user['school_id']]);
$request = $check->fetch(PDO::FETCH_ASSOC);

if (!$request) {
    http_response_code(404);
    echo json_encode(['error' => 'Admission request not found']);
    exit;
}

try {
    $updates = [];
    $params = [':id' => $id];

    // Update payment_status
    if (isset($input['payment_status'])) {
        $allowed = ['pending', 'verified', 'rejected'];
        if (!in_array($input['payment_status'], $allowed)) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid payment status']);
            exit;
        }
        $updates[] = "payment_status = :payment_status";
        $params[':payment_status'] = $input['payment_status'];
    }

    // Update application status
    if (isset($input['status'])) {
        $allowed = ['pending', 'contacted', 'approved', 'rejected'];
        if (!in_array($input['status'], $allowed)) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid status']);
            exit;
        }
        $updates[] = "status = :status";
        $params[':status'] = $input['status'];
    }

    if (empty($updates)) {
        http_response_code(400);
        echo json_encode(['error' => 'No fields to update']);
        exit;
    }

    $query = "UPDATE admission_requests SET " . implode(', ', $updates) . " WHERE id = :id";
    $stmt = $db->prepare($query);
    $stmt->execute($params);

    // If payment was verified, send invoice email
    if (isset($input['payment_status']) && $input['payment_status'] === 'verified') {
        sendPaymentConfirmationEmail($db, $request, $user);
    }

    // If application was approved, send approval email
    if (isset($input['status']) && $input['status'] === 'approved') {
        sendApprovalEmail($db, $request, $user);
    }

    echo json_encode(['success' => true, 'message' => 'Updated successfully']);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Server error: ' . $e->getMessage()]);
}

function sendPaymentConfirmationEmail($db, $request, $user) {
    if (empty($request['email'])) return;

    $email_query = "SELECT * FROM email_configs WHERE school_id = :school_id LIMIT 1";
    $email_stmt = $db->prepare($email_query);
    $email_stmt->execute([':school_id' => $user['school_id']]);
    $email_config = $email_stmt->fetch(PDO::FETCH_ASSOC);

    $student_name = $request['student_name'];
    $tracking_id = $request['tracking_id'];
    $utr = $request['utr_number'] ?? 'N/A';
    $method = ucfirst(str_replace('_', ' ', $request['payment_method'] ?? 'Online'));
    $date = date('d M Y, h:i A');

    $html = "
    <!DOCTYPE html>
    <html>
    <head>
      <meta name='viewport' content='width=device-width, initial-scale=1.0'>
      <style>
        body { font-family: 'Inter', -apple-system, sans-serif; background: #f1f5f9; margin: 0; padding: 0; }
        .wrapper { padding: 40px 0; }
        .container { max-width: 600px; margin: 0 auto; background: #fff; border-radius: 20px; overflow: hidden; box-shadow: 0 10px 40px rgba(0,0,0,0.08); }
        .header { background: linear-gradient(135deg, #10b981, #059669); padding: 36px 32px; text-align: center; color: #fff; }
        .header h1 { margin: 0 0 6px; font-size: 24px; font-weight: 800; }
        .header p { margin: 0; font-size: 14px; opacity: 0.9; }
        .content { padding: 36px 32px; }
        .invoice-box { background: #f0fdf4; border: 2px solid #bbf7d0; border-radius: 16px; padding: 24px; margin: 24px 0; }
        .invoice-row { display: flex; justify-content: space-between; padding: 10px 0; border-bottom: 1px solid #dcfce7; font-size: 14px; }
        .invoice-row:last-child { border-bottom: none; font-weight: 700; font-size: 16px; color: #059669; }
        .invoice-row span:first-child { color: #64748b; }
        .badge { display: inline-block; background: #10b981; color: #fff; padding: 6px 16px; border-radius: 20px; font-size: 12px; font-weight: 800; letter-spacing: 0.5px; }
        .footer { background: #f8fafc; padding: 24px 32px; text-align: center; font-size: 12px; color: #94a3b8; border-top: 1px solid #f1f5f9; }
        .btn { display: inline-block; padding: 14px 28px; background: #10b981; color: #fff !important; text-decoration: none; border-radius: 12px; font-weight: 700; font-size: 14px; }
      </style>
    </head>
    <body>
      <div class='wrapper'>
        <div class='container'>
          <div class='header'>
            <h1>✅ Payment Confirmed</h1>
            <p>Your admission fee payment has been verified</p>
          </div>
          <div class='content'>
            <p style='font-size:15px; color:#475569; line-height:1.7;'>Dear <strong>$student_name</strong>,</p>
            <p style='font-size:15px; color:#475569; line-height:1.7;'>We're pleased to confirm that your admission fee payment has been successfully verified. Below are your payment details:</p>
            
            <div class='invoice-box'>
              <div style='text-align:center; margin-bottom:16px;'>
                <span class='badge'>PAYMENT VERIFIED</span>
              </div>
              <div class='invoice-row'><span>Tracking ID</span><span><strong>$tracking_id</strong></span></div>
              <div class='invoice-row'><span>Payment Method</span><span>$method</span></div>
              <div class='invoice-row'><span>Transaction ID</span><span style='font-family:monospace'>$utr</span></div>
              <div class='invoice-row'><span>Verification Date</span><span>$date</span></div>
              <div class='invoice-row'><span>Status</span><span>✅ VERIFIED</span></div>
            </div>
            
            <p style='font-size:14px; color:#64748b; line-height:1.7;'>Our admissions team will now review your application and contact you regarding the next steps. Please keep your tracking ID safe for future reference.</p>
          </div>
          <div class='footer'>
            <strong>&copy; " . date('Y') . " Edducare School ERP</strong><br>
            This is an automated payment confirmation.
          </div>
        </div>
      </div>
    </body>
    </html>";

    $final_config = [
        'from_name' => FROM_NAME,
        'from_email' => FROM_EMAIL,
        'smtp_host' => SMTP_HOST,
        'smtp_port' => SMTP_PORT,
        'smtp_user' => SMTP_USERNAME,
        'smtp_pass' => SMTP_PASSWORD
    ];

    if ($email_config && $email_config['use_custom']) {
        foreach (['from_name', 'from_email', 'smtp_host', 'smtp_port', 'smtp_user', 'smtp_pass'] as $key) {
            if (!empty($email_config[$key])) $final_config[$key] = $email_config[$key];
        }
    }

    CustomMailer::send($request['email'], "Payment Confirmed - $tracking_id", $html, [
        'host' => $final_config['smtp_host'],
        'port' => $final_config['smtp_port'],
        'user' => $final_config['smtp_user'],
        'pass' => $final_config['smtp_pass'],
        'from_name' => $final_config['from_name'],
        'from_email' => $final_config['from_email']
    ]);
}

function sendApprovalEmail($db, $request, $user) {
    if (empty($request['email'])) return;

    $email_query = "SELECT * FROM email_configs WHERE school_id = :school_id LIMIT 1";
    $email_stmt = $db->prepare($email_query);
    $email_stmt->execute([':school_id' => $user['school_id']]);
    $email_config = $email_stmt->fetch(PDO::FETCH_ASSOC);

    $student_name = $request['student_name'];
    $tracking_id = $request['tracking_id'];
    $desired_class = $request['desired_class'] ?? 'the requested class';

    $html = "
    <!DOCTYPE html>
    <html>
    <head>
      <meta name='viewport' content='width=device-width, initial-scale=1.0'>
      <style>
        body { font-family: 'Inter', -apple-system, sans-serif; background: #f1f5f9; margin: 0; padding: 0; }
        .wrapper { padding: 40px 0; }
        .container { max-width: 600px; margin: 0 auto; background: #fff; border-radius: 20px; overflow: hidden; box-shadow: 0 10px 40px rgba(0,0,0,0.08); }
        .header { background: linear-gradient(135deg, #3b82f6, #1d4ed8); padding: 36px 32px; text-align: center; color: #fff; }
        .header h1 { margin: 0 0 6px; font-size: 24px; font-weight: 800; }
        .header p { margin: 0; font-size: 14px; opacity: 0.9; }
        .content { padding: 36px 32px; }
        .welcome-box { background: #eff6ff; border: 2px solid #bfdbfe; border-radius: 16px; padding: 28px; margin: 24px 0; text-align: center; }
        .welcome-box h2 { color: #1e40af; font-size: 20px; margin: 0 0 8px; }
        .welcome-box p { color: #3b82f6; font-size: 14px; margin: 0; }
        .footer { background: #f8fafc; padding: 24px 32px; text-align: center; font-size: 12px; color: #94a3b8; border-top: 1px solid #f1f5f9; }
      </style>
    </head>
    <body>
      <div class='wrapper'>
        <div class='container'>
          <div class='header'>
            <h1>🎉 Admission Approved!</h1>
            <p>Congratulations on your acceptance</p>
          </div>
          <div class='content'>
            <p style='font-size:15px; color:#475569; line-height:1.7;'>Dear <strong>$student_name</strong>,</p>
            <p style='font-size:15px; color:#475569; line-height:1.7;'>We are delighted to inform you that your admission application (Ref: <strong>$tracking_id</strong>) has been <strong style='color:#10b981'>APPROVED</strong>!</p>
            
            <div class='welcome-box'>
              <h2>Welcome to Our School Family!</h2>
              <p>Class: $desired_class</p>
            </div>
            
            <p style='font-size:14px; color:#64748b; line-height:1.7;'>Please visit the school office with the required documents to complete the enrollment process. Our staff will guide you through the remaining formalities.</p>
            <p style='font-size:14px; color:#64748b; line-height:1.7;'>We look forward to a wonderful academic journey together!</p>
          </div>
          <div class='footer'>
            <strong>&copy; " . date('Y') . " Edducare School ERP</strong><br>
            This is an automated admission notification.
          </div>
        </div>
      </div>
    </body>
    </html>";

    $final_config = [
        'from_name' => FROM_NAME,
        'from_email' => FROM_EMAIL,
        'smtp_host' => SMTP_HOST,
        'smtp_port' => SMTP_PORT,
        'smtp_user' => SMTP_USERNAME,
        'smtp_pass' => SMTP_PASSWORD
    ];

    if ($email_config && $email_config['use_custom']) {
        foreach (['from_name', 'from_email', 'smtp_host', 'smtp_port', 'smtp_user', 'smtp_pass'] as $key) {
            if (!empty($email_config[$key])) $final_config[$key] = $email_config[$key];
        }
    }

    CustomMailer::send($request['email'], "Admission Approved - $tracking_id", $html, [
        'host' => $final_config['smtp_host'],
        'port' => $final_config['smtp_port'],
        'user' => $final_config['smtp_user'],
        'pass' => $final_config['smtp_pass'],
        'from_name' => $final_config['from_name'],
        'from_email' => $final_config['from_email']
    ]);
}
?>
