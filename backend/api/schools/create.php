<?php
header('Access-Control-Allow-Origin: *');
header('Content-Type: application/json');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Access-Control-Allow-Headers,Content-Type,Access-Control-Allow-Methods,Authorization,X-Requested-With');

require_once '../../includes/auth.php';
require_once '../../includes/response.php';
require_once '../../includes/validator.php';
require_once '../../config/database.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    Response::error('Method not allowed', 405);
}

// Authenticate user
$headers = getallheaders();
$token = null;

if (isset($headers['Authorization'])) {
    $authHeader = $headers['Authorization'];
    if (preg_match('/Bearer\s+(.*)$/i', $authHeader, $matches)) {
        $token = $matches[1];
    }
}

if (!$token) {
    Response::unauthorized('Token required');
}

$auth = new Auth();
$user = $auth->getCurrentUser($token);

if (!$user || $user['role_name'] !== 'super_admin') {
    Response::forbidden('Only super admin can create schools');
}

$input = json_decode(file_get_contents('php://input'), true);

$validator = new Validator();
$isValid = $validator->validate($input, [
    'name' => 'required|max:255',
    'code' => 'required|max:50',
    'email' => 'required|email',
    'package_id' => 'required|numeric',
    'admin_first_name' => 'required|max:100',
    'admin_last_name' => 'required|max:100',
    'admin_email' => 'required|email',
    'admin_username' => 'required|max:100'
]);

if (!$isValid) {
    Response::validationError($validator->getErrors());
}

try {
    $db = new Database();
    $conn = $db->getConnection();
    
    $conn->beginTransaction();

    // Check if school code already exists
    $checkQuery = "SELECT id FROM schools WHERE code = :code";
    $checkStmt = $conn->prepare($checkQuery);
    $checkStmt->bindValue(':code', $input['code']);
    $checkStmt->execute();
    
    if ($checkStmt->fetch()) {
        Response::error('School code already exists');
    }

    // Create school
    $schoolQuery = "INSERT INTO schools (name, code, address, phone, email, website, package_id, package_expires_at) 
                   VALUES (:name, :code, :address, :phone, :email, :website, :package_id, DATE_ADD(NOW(), INTERVAL 12 MONTH))";
    
    $schoolStmt = $conn->prepare($schoolQuery);
    $schoolStmt->bindValue(':name', $input['name']);
    $schoolStmt->bindValue(':code', $input['code']);
    $schoolStmt->bindValue(':address', $input['address'] ?? null);
    $schoolStmt->bindValue(':phone', $input['phone'] ?? null);
    $schoolStmt->bindValue(':email', $input['email']);
    $schoolStmt->bindValue(':website', $input['website'] ?? null);
    $schoolStmt->bindValue(':package_id', $input['package_id']);
    $schoolStmt->execute();

    $schoolId = $conn->lastInsertId();

    // Create school admin user
    $adminPassword = password_hash($input['admin_password'] ?? 'admin123', PASSWORD_DEFAULT);
    
    $adminQuery = "INSERT INTO users (school_id, role_id, username, email, password_hash, first_name, last_name, email_verified) 
                  VALUES (:school_id, 2, :username, :email, :password_hash, :first_name, :last_name, 1)";
    
    $adminStmt = $conn->prepare($adminQuery);
    $adminStmt->bindValue(':school_id', $schoolId);
    $adminStmt->bindValue(':username', $input['admin_username']);
    $adminStmt->bindValue(':email', $input['admin_email']);
    $adminStmt->bindValue(':password_hash', $adminPassword);
    $adminStmt->bindValue(':first_name', $input['admin_first_name']);
    $adminStmt->bindValue(':last_name', $input['admin_last_name']);
    $adminStmt->execute();

    // Create default academic year
    $currentYear = date('Y');
    $nextYear = $currentYear + 1;
    $academicYearQuery = "INSERT INTO academic_years (school_id, name, start_date, end_date, is_current) 
                         VALUES (:school_id, :name, :start_date, :end_date, 1)";
    
    $academicYearStmt = $conn->prepare($academicYearQuery);
    $academicYearName = $currentYear . '-' . $nextYear;
    $startDate = $currentYear . '-04-01';
    $endDate = $nextYear . '-03-31';
    
    $academicYearStmt->bindValue(':school_id', $schoolId);
    $academicYearStmt->bindValue(':name', $academicYearName);
    $academicYearStmt->bindValue(':start_date', $startDate);
    $academicYearStmt->bindValue(':end_date', $endDate);
    $academicYearStmt->execute();

    $conn->commit();

    Response::success([
        'school_id' => $schoolId,
        'admin_credentials' => [
            'username' => $input['admin_username'],
            'password' => $input['admin_password'] ?? 'admin123'
        ]
    ], 'School created successfully');

} catch (Exception $e) {
    $conn->rollback();
    Response::error('Failed to create school: ' . $e->getMessage());
}
?>
