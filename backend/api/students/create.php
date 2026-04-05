<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

require_once '../../middleware/auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$auth = new AuthMiddleware();
$user = $auth->requireRole(['school_admin']);

if (!$user) {
    exit;
}

$isJson = isset($_SERVER["CONTENT_TYPE"]) && strpos($_SERVER["CONTENT_TYPE"], "application/json") !== false;
if ($isJson) {
    $input = json_decode(file_get_contents('php://input'), true);
} else {
    $input = $_POST;
}

// Validate required fields
$required = ['first_name', 'last_name', 'date_of_birth', 'gender', 'caste', 'class_id', 'section_id'];
foreach ($required as $field) {
    if (!isset($input[$field]) || empty($input[$field])) {
        http_response_code(400);
        echo json_encode(['error' => "Field '$field' is required"]);
        exit;
    }
}

$database = new Database();
$db = $database->getConnection();

try {
    $db->beginTransaction();

    // Auto-generate student_id if not provided
    if (empty($input['student_id'])) {
        $schoolQuery = "SELECT code FROM schools WHERE id = :school_id";
        $schoolStmt = $db->prepare($schoolQuery);
        $schoolStmt->bindValue(':school_id', $user['school_id']);
        $schoolStmt->execute();
        $schoolData = $schoolStmt->fetch();
        $codePrefix = $schoolData ? $schoolData['code'] : 'ADM';

        $idQuery = "SELECT COALESCE(MAX(id), 0) + 1 as next_id FROM students WHERE school_id = :school_id";
        $idStmt = $db->prepare($idQuery);
        $idStmt->bindValue(':school_id', $user['school_id']);
        $idStmt->execute();
        $nextId = $idStmt->fetch()['next_id'];

        $input['student_id'] = $codePrefix . date('y') . str_pad($nextId, 4, '0', STR_PAD_LEFT);
    }
    
    // Check if student ID already exists in the school
    $checkQuery = "SELECT id FROM students WHERE school_id = :school_id AND student_id = :student_id";
    $checkStmt = $db->prepare($checkQuery);
    $checkStmt->bindValue(':school_id', $user['school_id']);
    $checkStmt->bindValue(':student_id', $input['student_id']);
    $checkStmt->execute();
    
    if ($checkStmt->rowCount() > 0) {
        http_response_code(400);
        echo json_encode(['error' => 'Student ID already exists']);
        exit;
    }
    
    // Validate caste enum
    $validCastes = ['ST', 'SC', 'OBC', 'GENERAL'];
    if (!in_array($input['caste'], $validCastes)) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid caste value']);
        exit;
    }
    
    // Handle profile image upload
    $profileImagePath = null;
    if (isset($_FILES['profile_image']) && $_FILES['profile_image']['error'] === UPLOAD_ERR_OK) {
        $uploadDir = '../../uploads/student_profiles/';
        if (!file_exists($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }
        $extension = pathinfo($_FILES['profile_image']['name'], PATHINFO_EXTENSION);
        $fileName = 'student_' . $input['student_id'] . '_' . time() . '.' . $extension;
        if (move_uploaded_file($_FILES['profile_image']['tmp_name'], $uploadDir . $fileName)) {
            $profileImagePath = 'uploads/student_profiles/' . $fileName;
        }
    }
    
    $query = "INSERT INTO students (
        school_id, student_id, first_name, last_name, email, phone, 
        date_of_birth, gender, caste, religion, blood_group, address,
        parent_name, parent_phone, parent_email, parent_occupation, 
        emergency_contact, class_id, section_id, roll_number, admission_date, profile_image
    ) VALUES (
        :school_id, :student_id, :first_name, :last_name, :email, :phone,
        :date_of_birth, :gender, :caste, :religion, :blood_group, :address,
        :parent_name, :parent_phone, :parent_email, :parent_occupation,
        :emergency_contact, :class_id, :section_id, :roll_number, :admission_date, :profile_image
    )";
    
    $stmt = $db->prepare($query);
    
    $stmt->bindValue(':school_id', $user['school_id']);
    $stmt->bindValue(':student_id', $input['student_id']);
    $stmt->bindValue(':first_name', $input['first_name']);
    $stmt->bindValue(':last_name', $input['last_name']);
    $stmt->bindValue(':email', $input['email'] ?? null);
    $stmt->bindValue(':phone', $input['phone'] ?? null);
    $stmt->bindValue(':date_of_birth', $input['date_of_birth']);
    $stmt->bindValue(':gender', $input['gender']);
    $stmt->bindValue(':caste', $input['caste']);
    $stmt->bindValue(':religion', $input['religion'] ?? null);
    $stmt->bindValue(':blood_group', $input['blood_group'] ?? null);
    $stmt->bindValue(':address', $input['address'] ?? null);
    $stmt->bindValue(':parent_name', $input['parent_name'] ?? null);
    $stmt->bindValue(':parent_phone', $input['parent_phone'] ?? null);
    $stmt->bindValue(':parent_email', $input['parent_email'] ?? null);
    $stmt->bindValue(':parent_occupation', $input['parent_occupation'] ?? null);
    $stmt->bindValue(':emergency_contact', $input['emergency_contact'] ?? null);
    $stmt->bindValue(':class_id', $input['class_id']);
    $stmt->bindValue(':section_id', $input['section_id']);
    $stmt->bindValue(':roll_number', $input['roll_number'] ?? null);
    $stmt->bindValue(':admission_date', $input['admission_date'] ?? date('Y-m-d'));
    $stmt->bindValue(':profile_image', $profileImagePath);
    
    $stmt->execute();
    $studentId = $db->lastInsertId();
    
    // Log activity
    $logQuery = "INSERT INTO activity_logs (user_id, school_id, action, table_name, record_id, new_values, ip_address, user_agent) 
                 VALUES (:user_id, :school_id, 'create', 'students', :record_id, :new_values, :ip, :user_agent)";
    $logStmt = $db->prepare($logQuery);
    $logStmt->bindValue(':user_id', $user['id']);
    $logStmt->bindValue(':school_id', $user['school_id']);
    $logStmt->bindValue(':record_id', $studentId);
    $logStmt->bindValue(':new_values', json_encode($input));
    $logStmt->bindValue(':ip', $_SERVER['REMOTE_ADDR']);
    $logStmt->bindValue(':user_agent', $_SERVER['HTTP_USER_AGENT']);
    $logStmt->execute();
    
    $db->commit();
    
    echo json_encode([
        'success' => true,
        'message' => 'Student created successfully',
        'student_id' => $studentId
    ]);
    
} catch (Exception $e) {
    $db->rollBack();
    http_response_code(500);
    echo json_encode(['error' => 'Internal server error']);
}
?>
