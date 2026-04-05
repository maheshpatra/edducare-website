<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;


require_once __DIR__ . '/../src/PHPMailer.php';
require_once __DIR__ . '/../src/SMTP.php';
require_once __DIR__ . '/../src/Exception.php';


class EmailService {
    private $mailer;
    private $fromEmail;
    private $fromName;
    private $db;
    
    public function __construct() {
        $this->mailer = new PHPMailer(true);
        $this->fromEmail = 'edducare@finafid.org';
        $this->fromName = 'Edducare Technology';
        
        // SMTP Configuration
        $this->mailer->isSMTP();
        $this->mailer->Host = 'smtp.hostinger.com'; // Change to your SMTP server
        $this->mailer->SMTPAuth = true;
        $this->mailer->Username = 'edducare@finafid.org'; // Change to your email
        $this->mailer->Password = 'Edducare@9009#'; // Change to your app password
        $this->mailer->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
        $this->mailer->Port = 465;
        
        $this->mailer->setFrom($this->fromEmail, $this->fromName);
        
        // Initialize database connection
        $database = new Database();
        $this->db = $database->getConnection();
    }
    
    public function sendWelcomeEmail($userType, $userData, $schoolData, $password) {
        try {
            $template = $this->getWelcomeTemplate($userType, $userData, $schoolData, $password);
            
            $this->mailer->clearAddresses();
            $this->mailer->addAddress($userData['email'], $userData['first_name'] . ' ' . $userData['last_name']);
            
            $this->mailer->isHTML(true);
            $this->mailer->Subject = $template['subject'];
            $this->mailer->Body = $template['body'];
            $this->mailer->AltBody = $template['alt_body'];
            
            $result = $this->mailer->send();
            
            // Log email sent
            $this->logEmail($userData['email'], $template['subject'], 'welcome', $result ? 'sent' : 'failed');
            
            return $result;
        } catch (Exception $e) {
            $this->logEmail($userData['email'], 'Welcome Email', 'welcome', 'failed', $e->getMessage());
            return false;
        }
    }

    public function sendSchoolWelcomeEmail($schoolData, $password) {
        try {
            $subject = "Welcome to Edducare! School Registered: " . $schoolData['name'];
            
            $loginUrl = 'https://edducare.finafid.org/login';
            $body = "
                <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; border: 1px solid #eee; border-radius: 12px; overflow: hidden; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1);'>
                    <div style='background: linear-gradient(135deg, #4f46e5 0%, #7c3aed 100%); padding: 40px 30px; text-align: center; color: white;'>
                        <h1 style='margin: 0; font-size: 28px;'>Welcome to Edducare!</h1>
                        <p style='opacity: 0.9; font-size: 16px; margin-top: 10px;'>Your Premium Digital Campus Experience</p>
                    </div>
                    <div style='padding: 40px 30px; color: #1f2937; line-height: 1.6;'>
                        <h2 style='color: #4f46e5; margin-top: 0;'>Congratulations, {$schoolData['name']}!</h2>
                        <p>Your school has been successfully registered. We have created a <strong>Principal / Administrator</strong> account for you to begin your setup.</p>
                        
                        <div style='background: #fdf2f8; border: 1px solid #fbcfe8; padding: 25px; border-radius: 12px; margin: 30px 0;'>
                            <h3 style='margin-top: 0; color: #be185d; font-size: 16px; text-transform: uppercase; letter-spacing: 0.05em;'>🔐 Your Login Credentials</h3>
                            <p style='margin: 12px 0; font-size: 15px;'><strong>Login URL:</strong> <a href='{$loginUrl}' style='color: #4f46e5;'>Click Here to Login</a></p>
                            <p style='margin: 12px 0; font-size: 15px;'><strong>Username:</strong> <span style='font-family: monospace; background: #fff; padding: 4px 8px; border: 1px solid #e5e7eb; border-radius: 6px;'>{$schoolData['email']}</span></p>
                            <p style='margin: 12px 0; font-size: 15px;'><strong>Temporary Password:</strong> <span style='font-family: monospace; background: #fff; padding: 4px 8px; border: 1px solid #e5e7eb; border-radius: 6px; font-weight: bold; color: #4f46e5;'>{$password}</span></p>
                            <p style='margin: 15px 0 0 0; font-size: 12px; color: #9d174d;'><i>* Please change your password immediately after your first login for security.</i></p>
                        </div>

                        <div style='background: #f8fafc; padding: 20px; border-radius: 8px; margin-bottom: 30px; border: 1px solid #e2e8f0;'>
                            <p style='margin: 5px 0; font-size: 14px;'><strong>School Code:</strong> <span style='font-weight: bold;'>{$schoolData['code']}</span></p>
                            <p style='margin: 5px 0; font-size: 14px;'><strong>Registration ID:</strong> #{$schoolData['id']}</p>
                        </div>
                        
                        <p>You can now use these credentials to set up your staff, students, and academic configurations.</p>
                        
                        <div style='text-align: center; margin: 40px 0;'>
                            <a href='{$loginUrl}' style='background: #4f46e5; color: white; padding: 16px 32px; text-decoration: none; border-radius: 10px; font-weight: bold; font-size: 16px; display: inline-block; box-shadow: 0 4px 14px 0 rgba(79, 70, 229, 0.39);'>🚀 Access Your Dashboard</a>
                        </div>
                    </div>
                    <div style='background: #f9fafb; padding: 25px; text-align: center; color: #6b7280; font-size: 11px; border-top: 1px solid #f3f4f6;'>
                        <p style='margin: 0;'>This is an automated message from Edducare ERP System.</p>
                        <p style='margin: 5px 0;'>&copy; " . date('Y') . " Edducare Technology. All rights reserved.</p>
                    </div>
                </div>
            ";

            $this->mailer->clearAddresses();
            $this->mailer->addAddress($schoolData['email'], $schoolData['name']);
            $this->mailer->isHTML(true);
            $this->mailer->Subject = $subject;
            $this->mailer->Body = $body;
            $this->mailer->AltBody = "Welcome to Edducare!\n\nEmail: {$schoolData['email']}\nPassword: {$password}\nURL: {$loginUrl}";
            
            $result = $this->mailer->send();
            $this->logEmail($schoolData['email'], $subject, 'school_welcome', $result ? 'sent' : 'failed');
            return $result;
        } catch (Exception $e) {
            $this->logEmail($schoolData['email'], 'School Registration', 'school_welcome', 'failed', $e->getMessage());
            return false;
        }
    }
    
    public function sendOTP($email, $otp, $purpose = 'verification') {
        
        try {
            $template = $this->getOTPTemplate($otp, $purpose);
            
            $this->mailer->clearAddresses();
            $this->mailer->addAddress($email);
            
            $this->mailer->isHTML(true);
            $this->mailer->Subject = $template['subject'];
            $this->mailer->Body = $template['body'];
            $this->mailer->AltBody = $template['alt_body'];
            
            $result = $this->mailer->send();
            
            // Store OTP in database
            $this->storeOTP($email, $otp, $purpose);
            
            $this->logEmail($email, $template['subject'], 'otp', $result ? 'sent' : 'failed');
            
            return $result;
        } catch (Exception $e) {
            $this->logEmail($email, 'OTP Email', 'otp', 'failed', $e->getMessage());
            return false;
        }
    }
    
    public function sendPasswordResetEmail($email, $resetToken) {
        try {
            $template = $this->getPasswordResetTemplate($resetToken);
            
            $this->mailer->clearAddresses();
            $this->mailer->addAddress($email);
            
            $this->mailer->isHTML(true);
            $this->mailer->Subject = $template['subject'];
            $this->mailer->Body = $template['body'];
            $this->mailer->AltBody = $template['alt_body'];
            
            $result = $this->mailer->send();
            
            $this->logEmail($email, $template['subject'], 'password_reset', $result ? 'sent' : 'failed');
            
            return $result;
        } catch (Exception $e) {
            $this->logEmail($email, 'Password Reset', 'password_reset', 'failed', $e->getMessage());
            return false;
        }
    }
    
    private function getWelcomeTemplate($userType, $userData, $schoolData, $password) {
        $userTypeText = ucfirst(str_replace('_', ' ', $userType));
        $loginUrl = 'https://edducare.org/login'; // Change to your domain
        
        $subject = "Welcome to {$schoolData['name']} - Your Account Details";
        
        $body = "
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset='UTF-8'>
            <meta name='viewport' content='width=device-width, initial-scale=1.0'>
            <title>Welcome to Edducare Technology</title>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; margin: 0; padding: 0; background-color: #f4f4f4; }
                .container { max-width: 600px; margin: 0 auto; background-color: #ffffff; }
                .header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 30px; text-align: center; }
                .header h1 { margin: 0; font-size: 28px; }
                .content { padding: 40px 30px; }
                .welcome-box { background-color: #f8f9ff; border-left: 4px solid #667eea; padding: 20px; margin: 20px 0; }
                .credentials-box { background-color: #fff3cd; border: 1px solid #ffeaa7; border-radius: 8px; padding: 20px; margin: 20px 0; }
                .credentials-box h3 { color: #856404; margin-top: 0; }
                .credential-item { margin: 10px 0; }
                .credential-label { font-weight: bold; color: #495057; }
                .credential-value { background-color: #e9ecef; padding: 8px 12px; border-radius: 4px; font-family: monospace; margin-left: 10px; }
                .login-button { display: inline-block; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 15px 30px; text-decoration: none; border-radius: 8px; margin: 20px 0; font-weight: bold; }
                .login-button:hover { opacity: 0.9; }
                .security-notice { background-color: #d1ecf1; border: 1px solid #bee5eb; border-radius: 8px; padding: 15px; margin: 20px 0; }
                .footer { background-color: #f8f9fa; padding: 20px; text-align: center; color: #6c757d; font-size: 14px; }
                .school-info { background-color: #e8f5e8; border-radius: 8px; padding: 15px; margin: 20px 0; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h1>🎒 Welcome to Edducare Technology</h1>
                    <p>Your Digital Education Management System</p>
                </div>
                
                <div class='content'>
                    <div class='welcome-box'>
                        <h2>Welcome, {$userData['first_name']} {$userData['last_name']}!</h2>
                        <p>We're excited to have you join <strong>{$schoolData['name']}</strong> as a <strong>{$userTypeText}</strong>.</p>
                    </div>
                    
                    <div class='school-info'>
                        <h3>🏢 School Information</h3>
                        <p><strong>School:</strong> {$schoolData['name']}</p>
                        <p><strong>Code:</strong> {$schoolData['code']}</p>
                        " . (!empty($schoolData['address']) ? "<p><strong>Address:</strong> {$schoolData['address']}</p>" : "") . "
                        " . (!empty($schoolData['phone']) ? "<p><strong>Phone:</strong> {$schoolData['phone']}</p>" : "") . "
                    </div>
                    
                    <div class='credentials-box'>
                        <h3>🧑‍💻 Your Login Credentials</h3>
                        <div class='credential-item'>
                            <span class='credential-label'>Username:</span>
                            <span class='credential-value'>{$userData['username']}</span>
                        </div>
                        <div class='credential-item'>
                            <span class='credential-label'>Email:</span>
                            <span class='credential-value'>{$userData['email']}</span>
                        </div>
                        <div class='credential-item'>
                            <span class='credential-label'>Temporary Password:</span>
                            <span class='credential-value'>{$password}</span>
                        </div>
                    </div>
                    
                    <div style='text-align: center;'>
                        <a href='{$loginUrl}' class='login-button'>🚀 Login to Your Account</a>
                    </div>
                    
                    <div class='security-notice'>
                        <h4>🕵🏻‍♂️ Important Security Information</h4>
                        <ul>
                            <li><strong>Change your password immediately</strong> after your first login</li>
                            <li>Keep your login credentials secure and don't share them with anyone</li>
                            <li>If you face any issues logging in, contact your school administrator</li>
                            <li>This is a temporary password that expires in 7 days</li>
                        </ul>
                    </div>
                    
                    <div style='margin-top: 30px;'>
                        <h3>🛂 What's Next?</h3>
                        <ul>
                            <li>Log in to your account using the credentials above</li>
                            <li>Complete your profile information</li>
                            <li>Explore the dashboard and available features</li>
                            <li>Contact support if you need any assistance</li>
                        </ul>
                    </div>
                </div>
                
                <div class='footer'>
                    <p>This email was sent from the Edducare Technology</p>
                    <p>If you didn't expect this email, please contact your school administrator immediately.</p>
                    <p>&copy; " . date('Y') . " Edducare Technology. All rights reserved.</p>
                </div>
            </div>
        </body>
        </html>";
        
        $altBody = "Welcome to {$schoolData['name']}!\n\n";
        $altBody .= "Your account has been created as a {$userTypeText}.\n\n";
        $altBody .= "Login Credentials:\n";
        $altBody .= "Username: {$userData['username']}\n";
        $altBody .= "Email: {$userData['email']}\n";
        $altBody .= "Temporary Password: {$password}\n\n";
        $altBody .= "Please login at: {$loginUrl}\n\n";
        $altBody .= "IMPORTANT: Change your password immediately after first login.\n\n";
        $altBody .= "If you have any questions, contact your school administrator.";
        
        return [
            'subject' => $subject,
            'body' => $body,
            'alt_body' => $altBody
        ];
    }
    
    private function getOTPTemplate($otp, $purpose) {
        $purposeText = ucfirst(str_replace('_', ' ', $purpose));
        $subject = "Your OTP for {$purposeText} - School ERP";
        
        $body = "
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset='UTF-8'>
            <meta name='viewport' content='width=device-width, initial-scale=1.0'>
            <title>OTP Verification</title>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; margin: 0; padding: 0; background-color: #f4f4f4; }
                .container { max-width: 600px; margin: 0 auto; background-color: #ffffff; }
                .header { background: linear-gradient(135deg, #28a745 0%, #20c997 100%); color: white; padding: 30px; text-align: center; }
                .header h1 { margin: 0; font-size: 28px; }
                .content { padding: 40px 30px; text-align: center; }
                .otp-box { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 30px; border-radius: 15px; margin: 30px 0; }
                .otp-code { font-size: 48px; font-weight: bold; letter-spacing: 8px; margin: 20px 0; font-family: monospace; }
                .timer-box { background-color: #fff3cd; border: 1px solid #ffeaa7; border-radius: 8px; padding: 15px; margin: 20px 0; }
                .security-tips { background-color: #d1ecf1; border-radius: 8px; padding: 20px; margin: 20px 0; text-align: left; }
                .footer { background-color: #f8f9fa; padding: 20px; text-align: center; color: #6c757d; font-size: 14px; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h1>🔐 OTP Verification</h1>
                    <p>Secure Access Code</p>
                </div>
                
                <div class='content'>
                    <h2>Your One-Time Password</h2>
                    <p>Use this OTP to complete your {$purposeText}:</p>
                    
                    <div class='otp-box'>
                        <div class='otp-code'>{$otp}</div>
                        <p style='margin: 0; font-size: 16px;'>Enter this code to proceed</p>
                    </div>
                    
                    <div class='timer-box'>
                        <p><strong>⏰ This OTP is valid for 10 minutes only</strong></p>
                        <p>Please complete your verification before it expires.</p>
                    </div>
                    
                    <div class='security-tips'>
                        <h4>🛡️ Security Tips</h4>
                        <ul>
                            <li>Never share this OTP with anyone</li>
                            <li>Our team will never ask for your OTP over phone or email</li>
                            <li>If you didn't request this OTP, please ignore this email</li>
                            <li>Contact support if you suspect any suspicious activity</li>
                        </ul>
                    </div>
                </div>
                
                <div class='footer'>
                    <p>This OTP was generated for your School ERP account</p>
                    <p>If you didn't request this, please contact support immediately.</p>
                    <p>&copy; " . date('Y') . " Edducare Technology. All rights reserved.</p>
                </div>
            </div>
        </body>
        </html>";
        
        $altBody = "Your OTP for {$purposeText}: {$otp}\n\n";
        $altBody .= "This OTP is valid for 10 minutes only.\n";
        $altBody .= "Never share this OTP with anyone.\n\n";
        $altBody .= "If you didn't request this, please ignore this email.";
        
        return [
            'subject' => $subject,
            'body' => $body,
            'alt_body' => $altBody
        ];
    }
    
    private function getPasswordResetTemplate($resetToken) {
        $resetUrl = "https://edducare.finafid.org/reset-password?token={$resetToken}"; // Change to your domain
        $subject = "Reset Your Password - Edducare Technology";
        
        $body = "
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset='UTF-8'>
            <meta name='viewport' content='width=device-width, initial-scale=1.0'>
            <title>Password Reset</title>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; margin: 0; padding: 0; background-color: #f4f4f4; }
                .container { max-width: 600px; margin: 0 auto; background-color: #ffffff; }
                .header { background: linear-gradient(135deg, #dc3545 0%, #fd7e14 100%); color: white; padding: 30px; text-align: center; }
                .header h1 { margin: 0; font-size: 28px; }
                .content { padding: 40px 30px; }
                .reset-box { background-color: #f8f9ff; border-left: 4px solid #dc3545; padding: 20px; margin: 20px 0; }
                .reset-button { display: inline-block; background: linear-gradient(135deg, #dc3545 0%, #fd7e14 100%); color: white; padding: 15px 30px; text-decoration: none; border-radius: 8px; margin: 20px 0; font-weight: bold; }
                .reset-button:hover { opacity: 0.9; }
                .security-notice { background-color: #fff3cd; border: 1px solid #ffeaa7; border-radius: 8px; padding: 15px; margin: 20px 0; }
                .footer { background-color: #f8f9fa; padding: 20px; text-align: center; color: #6c757d; font-size: 14px; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h1>🔑 Password Reset</h1>
                    <p>Secure Password Recovery</p>
                </div>
                
                <div class='content'>
                    <div class='reset-box'>
                        <h2>Reset Your Password</h2>
                        <p>We received a request to reset your password for your School ERP account.</p>
                        <p>Click the button below to create a new password:</p>
                    </div>
                    
                    <div style='text-align: center;'>
                        <a href='{$resetUrl}' class='reset-button'>🔐 Reset Password</a>
                    </div>
                    
                    <div class='security-notice'>
                        <h4>🛡️ Security Information</h4>
                        <ul>
                            <li>This password reset link is valid for <strong>1 hour only</strong></li>
                            <li>If you didn't request this reset, please ignore this email</li>
                            <li>Your current password will remain unchanged until you create a new one</li>
                            <li>For security reasons, this link can only be used once</li>
                        </ul>
                    </div>
                    
                    <p><strong>Alternative:</strong> If the button doesn't work, copy and paste this link into your browser:</p>
                    <p style='word-break: break-all; background-color: #e9ecef; padding: 10px; border-radius: 4px; font-family: monospace;'>{$resetUrl}</p>
                </div>
                
                <div class='footer'>
                    <p>This email was sent from the Edducare Technology</p>
                    <p>If you didn't request this password reset, please contact support immediately.</p>
                    <p>&copy; " . date('Y') . " Edducare Technology. All rights reserved.</p>
                </div>
            </div>
        </body>
        </html>";
        
        $altBody = "Password Reset Request\n\n";
        $altBody .= "We received a request to reset your password.\n\n";
        $altBody .= "Reset your password by visiting: {$resetUrl}\n\n";
        $altBody .= "This link is valid for 1 hour only.\n";
        $altBody .= "If you didn't request this, please ignore this email.\n\n";
        $altBody .= "Contact support if you need assistance.";
        
        return [
            'subject' => $subject,
            'body' => $body,
            'alt_body' => $altBody
        ];
    }
    
    private function storeOTP($email, $otp, $purpose) {
        $query = "INSERT INTO email_otps (email, otp_code, purpose, expires_at) 
                  VALUES (:email, :otp, :purpose, DATE_ADD(NOW(), INTERVAL 10 MINUTE))
                  ON DUPLICATE KEY UPDATE 
                  otp_code = VALUES(otp_code), 
                  expires_at = VALUES(expires_at), 
                  is_used = FALSE, 
                  created_at = NOW()";
        
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':email', $email);
        $stmt->bindParam(':otp', $otp);
        $stmt->bindParam(':purpose', $purpose);
        $stmt->execute();
    }
    
    public function verifyOTP($email, $otp, $purpose) {
        $query = "SELECT id FROM email_otps 
                  WHERE email = :email AND otp_code = :otp AND purpose = :purpose 
                  AND expires_at > NOW() AND is_used = FALSE";
        
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':email', $email);
        $stmt->bindParam(':otp', $otp);
        $stmt->bindParam(':purpose', $purpose);
        $stmt->execute();
        
        if ($stmt->rowCount() > 0) {
            // Mark OTP as used
            $updateQuery = "UPDATE email_otps SET is_used = TRUE WHERE email = :email AND otp_code = :otp AND purpose = :purpose";
            $updateStmt = $this->db->prepare($updateQuery);
            $updateStmt->bindParam(':email', $email);
            $updateStmt->bindParam(':otp', $otp);
            $updateStmt->bindParam(':purpose', $purpose);
            $updateStmt->execute();
            
            return true;
        }
        
        return false;
    }
    
    private function logEmail($email, $subject, $type, $status, $error = null) {
        $query = "INSERT INTO email_logs (email, subject, type, status, error_message) 
                  VALUES (:email, :subject, :type, :status, :error)";
        
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':email', $email);
        $stmt->bindParam(':subject', $subject);
        $stmt->bindParam(':type', $type);
        $stmt->bindParam(':status', $status);
        $stmt->bindParam(':error', $error);
        $stmt->execute();
    }
}
?>
