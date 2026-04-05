<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE');
header('Access-Control-Allow-Headers: Content-Type, Authorization');


require_once '../../config/database.php';
require_once '../../config/email.php';
require_once '../../middleware/auth.php';

// Authentication check
$auth = new AuthMiddleware();
$user = $auth->authenticate();

if (!$user) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$database = new Database();
$db = $database->getConnection();
$method = $_SERVER['REQUEST_METHOD'];

try {
    switch ($method) {
        case 'GET':
            handleGetStudents($db, $user);
            break;
        case 'POST':
            handleCreateStudent($db, $user);
            break;
        case 'PUT':
            handleUpdateStudent($db, $user);
            break;
        case 'DELETE':
            handleDeleteStudent($db, $user);
            break;
        default:
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
    }
}
catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Server error: ' . $e->getMessage()]);
}

function handleGetStudents($db, $user)
{
    $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
    $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 20;
    $search = isset($_GET['search']) ? trim($_GET['search']) : '';
    $class_id = isset($_GET['class_id']) ? (int)$_GET['class_id'] : null;
    $section_id = isset($_GET['section_id']) ? (int)$_GET['section_id'] : null;
    $caste = isset($_GET['caste']) ? $_GET['caste'] : null;
    $status = isset($_GET['status']) ? $_GET['status'] : 'all';

    $offset = ($page - 1) * $limit;

    // Build WHERE clause
    $whereConditions = ["s.school_id = :school_id"];
    $params = [':school_id' => $user['school_id']];

    if (!empty($search)) {
        $whereConditions[] = "(s.first_name LIKE :search OR s.last_name LIKE :search OR s.student_id LIKE :search OR s.admission_number LIKE :search OR s.email LIKE :search)";
        $params[':search'] = "%{$search}%";
    }

    if ($class_id) {
        $whereConditions[] = "se.class_id = :class_id";
        $params[':class_id'] = $class_id;
    }

    if ($section_id) {
        $whereConditions[] = "se.section_id = :section_id";
        $params[':section_id'] = $section_id;
    }

    if ($caste) {
        $whereConditions[] = "s.caste = :caste";
        $params[':caste'] = $caste;
    }

    // By default show both active and rusticated, only exclude deleted
    if ($status && $status !== 'all') {
        $whereConditions[] = "s.status = :status";
        $params[':status'] = $status;
    } else {
        $whereConditions[] = "s.status != 'deleted'";
    }

    $whereClause = implode(' AND ', $whereConditions);

    // Get students with enrollment details
    $query = "SELECT s.*, 
                     c.name as class_name, 
                     sec.name as section_name,
                     se.roll_number,
                     se.enrollment_date,
                     ay.name as academic_year
              FROM students s
              LEFT JOIN student_enrollments se ON s.id = se.student_id AND se.status = 'active'
              LEFT JOIN classes c ON se.class_id = c.id
              LEFT JOIN sections sec ON se.section_id = sec.id
              LEFT JOIN academic_years ay ON se.academic_year_id = ay.id
              WHERE {$whereClause}
              ORDER BY s.first_name, s.last_name
              LIMIT :limit OFFSET :offset";

    $stmt = $db->prepare($query);
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();

    $students = $stmt->fetchAll();

    // Get total count
    $countQuery = "SELECT COUNT(DISTINCT s.id) as total 
                   FROM students s
                   LEFT JOIN student_enrollments se ON s.id = se.student_id AND se.status = 'active'
                   WHERE {$whereClause}";
    $countStmt = $db->prepare($countQuery);
    foreach ($params as $key => $value) {
        $countStmt->bindValue($key, $value);
    }
    $countStmt->execute();
    $total = $countStmt->fetch()['total'];

    // Get statistics
    $statsQuery = "SELECT 
                      COUNT(*) as total_students,
                      SUM(CASE WHEN s.gender = 'male' THEN 1 ELSE 0 END) as male_count,
                      SUM(CASE WHEN s.gender = 'female' THEN 1 ELSE 0 END) as female_count,
                      SUM(CASE WHEN s.caste = 'SC' THEN 1 ELSE 0 END) as sc_count,
                      SUM(CASE WHEN s.caste = 'ST' THEN 1 ELSE 0 END) as st_count,
                      SUM(CASE WHEN s.caste = 'OBC' THEN 1 ELSE 0 END) as obc_count,
                      SUM(CASE WHEN s.caste = 'GENERAL' THEN 1 ELSE 0 END) as general_count
                   FROM students s 
                   WHERE s.school_id = :school_id AND s.status = 'active'";
    $statsStmt = $db->prepare($statsQuery);
    $statsStmt->bindValue(':school_id', $user['school_id']);
    $statsStmt->execute();
    $stats = $statsStmt->fetch();

    echo json_encode([
        'success' => true,
        'data' => $students,
        'pagination' => [
            'current_page' => $page,
            'total_pages' => ceil($total / $limit),
            'total_records' => (int)$total,
            'per_page' => $limit
        ],
        'statistics' => $stats
    ]);
}

function handleCreateStudent($db, $user)
{
    $isJson = isset($_SERVER["CONTENT_TYPE"]) && strpos($_SERVER["CONTENT_TYPE"], "application/json") !== false;
    if ($isJson) {
        $input = json_decode(file_get_contents('php://input'), true);
    }
    else {
        $input = $_POST;
    }

    // Required fields validation
    $required_fields = [
        'first_name',
        'last_name',
        'date_of_birth',
        'gender',
        'father_name',
        'father_phone',
        'admission_date'
    ];

    foreach ($required_fields as $field) {
        if (!isset($input[$field]) || empty(trim($input[$field]))) {
            http_response_code(400);
            echo json_encode(['error' => ucfirst(str_replace('_', ' ', $field)) . ' is required']);
            return;
        }
    }

    // Handle profile image upload
    $profileImagePath = null;
    if (isset($_FILES['profile_image']) && $_FILES['profile_image']['error'] === UPLOAD_ERR_OK) {
        $uploadDir = '../../uploads/student_profiles/';
        if (!file_exists($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }
        $extension = pathinfo($_FILES['profile_image']['name'], PATHINFO_EXTENSION);
        $fileName = 'stu_' . time() . '_' . rand(100, 999) . '.' . $extension;
        if (move_uploaded_file($_FILES['profile_image']['tmp_name'], $uploadDir . $fileName)) {
            $profileImagePath = 'uploads/student_profiles/' . $fileName;
        }
    }

    // Fetch school data (including type and code prefix)
    $schoolQuery = "SELECT code, school_type FROM schools WHERE id = :school_id";
    $schoolStmt = $db->prepare($schoolQuery);
    $schoolStmt->bindValue(':school_id', $user['school_id']);
    $schoolStmt->execute();
    $schoolData = $schoolStmt->fetch();
    $codePrefix = $schoolData ? $schoolData['code'] : 'ADM';
    $schoolType = $schoolData ? $schoolData['school_type'] : 'General';

    // Generate unique student ID and admission number
    $currentYear = date('Y');
    $shortYear = date('y');
    $pattern = "{$codePrefix}{$shortYear}%";

    $studentIdQuery = "SELECT MAX(CAST(SUBSTRING(student_id, " . (strlen($codePrefix) + 3) . ") AS UNSIGNED)) as max_id 
                   FROM students 
                   WHERE school_id = :school_id AND student_id LIKE :pattern";
    $stmt = $db->prepare($studentIdQuery);
    $stmt->bindValue(':school_id', $user['school_id']);
    $stmt->bindValue(':pattern', $pattern);
    $stmt->execute();
    $result = $stmt->fetch();

    $nextId = ($result['max_id'] ?? 0) + 1;
    $studentId = sprintf("%s%s%03d", $codePrefix, $shortYear, $nextId);
    $admissionNumber = sprintf("%sAD%s%04d", $codePrefix, $shortYear, $nextId);
    $username = !empty($input['email']) ? strtolower(trim($input['email'])) : strtolower($studentId);
    $password = $studentId . '@' . $shortYear;
    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

    // Check if email already exists
    if (!empty($input['email'])) {
        $emailCheckQuery = "SELECT id FROM students WHERE email = :email AND school_id = :school_id";
        $emailStmt = $db->prepare($emailCheckQuery);
        $emailStmt->bindValue(':email', $input['email']);
        $emailStmt->bindValue(':school_id', $user['school_id']);
        $emailStmt->execute();

        if ($emailStmt->rowCount() > 0) {
            http_response_code(400);
            echo json_encode(['error' => 'Email already exists']);
            return;
        }
    }

    $db->beginTransaction();

    try {
        // Insert student
        $insertQuery = "INSERT INTO students (
            school_id, student_id, admission_number, username, email, password_hash,
            first_name, middle_name, last_name, date_of_birth, gender, blood_group,
            caste, religion, nationality, mother_tongue, permanent_address, current_address,
            city, state, pincode, father_name, father_occupation, father_phone, father_email,
            father_income, mother_name, mother_occupation, mother_phone, mother_email,
            mother_income, guardian_name, guardian_relation, guardian_phone, guardian_email,
            emergency_contact_name, emergency_contact_phone, emergency_contact_relation,
            medical_conditions, allergies, medications, previous_school, previous_class,
            admission_date, fee_concession_type, fee_concession_percentage, aadhar_number,
            status, profile_image, created_at
        ) VALUES (
            :school_id, :student_id, :admission_number, :username, :email, :password_hash,
            :first_name, :middle_name, :last_name, :date_of_birth, :gender, :blood_group,
            :caste, :religion, :nationality, :mother_tongue, :permanent_address, :current_address,
            :city, :state, :pincode, :father_name, :father_occupation, :father_phone, :father_email,
            :father_income, :mother_name, :mother_occupation, :mother_phone, :mother_email,
            :mother_income, :guardian_name, :guardian_relation, :guardian_phone, :guardian_email,
            :emergency_contact_name, :emergency_contact_phone, :emergency_contact_relation,
            :medical_conditions, :allergies, :medications, :previous_school, :previous_class,
            :admission_date, :fee_concession_type, :fee_concession_percentage, :aadhar_number,
            'active', :profile_image, NOW()
        )";
        $email = $input['email'] ?? null;
        $caste = $input['caste'] ?? 'GENERAL';
        $nationality = $input['nationality'] ?? 'Indian';
        $fee_concession_type = $input['fee_concession_type'] ?? 'none';
        $stmt = $db->prepare($insertQuery);

        // Bind parameters
        $stmt->bindValue(':school_id', $user['school_id']);
        $stmt->bindValue(':student_id', $studentId);
        $stmt->bindValue(':admission_number', $admissionNumber);
        $stmt->bindValue(':username', $username);
        $stmt->bindValue(':email', $email);
        $stmt->bindValue(':password_hash', $hashedPassword);
        $stmt->bindValue(':first_name', $input['first_name']);
        $stmt->bindValue(':middle_name', $input['middle_name'] ?? null);
        $stmt->bindValue(':last_name', $input['last_name']);
        $stmt->bindValue(':date_of_birth', $input['date_of_birth']);
        $stmt->bindValue(':gender', $input['gender']);
        $stmt->bindValue(':blood_group', $input['blood_group'] ?? null);
        $stmt->bindValue(':caste', $caste);
        $stmt->bindValue(':religion', $input['religion'] ?? null);
        $stmt->bindValue(':nationality', $nationality);
        $stmt->bindValue(':mother_tongue', $input['mother_tongue'] ?? null);
        $stmt->bindValue(':permanent_address', $input['permanent_address'] ?? null);
        $stmt->bindValue(':current_address', $input['current_address'] ?? null);
        $stmt->bindValue(':city', $input['city'] ?? null);
        $stmt->bindValue(':state', $input['state'] ?? null);
        $stmt->bindValue(':pincode', $input['pincode'] ?? null);
        $stmt->bindValue(':father_name', $input['father_name']);
        $stmt->bindValue(':father_occupation', $input['father_occupation'] ?? null);
        $stmt->bindValue(':father_phone', $input['father_phone']);
        $stmt->bindValue(':father_email', $input['father_email'] ?? null);
        $stmt->bindValue(':father_income', $input['father_income'] ?? null);
        $stmt->bindValue(':mother_name', $input['mother_name'] ?? null);
        $stmt->bindValue(':mother_occupation', $input['mother_occupation'] ?? null);
        $stmt->bindValue(':mother_phone', $input['mother_phone'] ?? null);
        $stmt->bindValue(':mother_email', $input['mother_email'] ?? null);
        $stmt->bindValue(':mother_income', $input['mother_income'] ?? null);
        $stmt->bindValue(':guardian_name', $input['guardian_name'] ?? null);
        $stmt->bindValue(':guardian_relation', $input['guardian_relation'] ?? null);
        $stmt->bindValue(':guardian_phone', $input['guardian_phone'] ?? null);
        $stmt->bindValue(':guardian_email', $input['guardian_email'] ?? null);
        $stmt->bindValue(':emergency_contact_name', $input['emergency_contact_name'] ?? null);
        $stmt->bindValue(':emergency_contact_phone', $input['emergency_contact_phone'] ?? null);
        $stmt->bindValue(':emergency_contact_relation', $input['emergency_contact_relation'] ?? null);
        $stmt->bindValue(':medical_conditions', $input['medical_conditions'] ?? null);
        $stmt->bindValue(':allergies', $input['allergies'] ?? null);
        $stmt->bindValue(':medications', $input['medications'] ?? null);
        $stmt->bindValue(':previous_school', $input['previous_school'] ?? null);
        $stmt->bindValue(':previous_class', $input['previous_class'] ?? null);
        $stmt->bindValue(':admission_date', $input['admission_date']);
        $stmt->bindValue(':fee_concession_type', $fee_concession_type);
        $stmt->bindValue(':fee_concession_percentage', $input['fee_concession_percentage'] ?? 0);
        $stmt->bindValue(':aadhar_number', $input['aadhar_number'] ?? null);
        $stmt->bindValue(':profile_image', $profileImagePath);

        $stmt->execute();
        $studentDbId = $db->lastInsertId();

        // Create enrollment if class and section provided
        if (isset($input['class_id']) && isset($input['section_id'])) {
            // Get current academic year
            $ayQuery = "SELECT id FROM academic_years WHERE school_id = :school_id AND is_current = 1";
            $ayStmt = $db->prepare($ayQuery);
            $ayStmt->bindValue(':school_id', $user['school_id']);
            $ayStmt->execute();
            $academicYear = $ayStmt->fetch();

            if ($academicYear) {
                $rollNumber = $input['roll_number'] ?? null;
                if (empty($rollNumber)) {
                    // Auto-generate roll number
                    $rollQuery = "SELECT MAX(CAST(roll_number AS UNSIGNED)) as max_roll 
                                 FROM student_enrollments 
                                 WHERE class_id = :class_id AND section_id = :section_id AND academic_year_id = :ay_id";
                    $rollStmt = $db->prepare($rollQuery);
                    $rollStmt->execute([
                        ':class_id' => $input['class_id'],
                        ':section_id' => $input['section_id'],
                        ':ay_id' => $academicYear['id']
                    ]);
                    $maxRoll = $rollStmt->fetch();
                    $rollNumber = ($maxRoll['max_roll'] ?? 0) + 1;
                }

                $enrollQuery = "INSERT INTO student_enrollments (student_id, class_id, section_id, academic_year_id, enrollment_date, roll_number) 
                               VALUES (:student_id, :class_id, :section_id, :academic_year_id, :enrollment_date, :roll_number)";
                $enrollStmt = $db->prepare($enrollQuery);
                $enrollStmt->bindValue(':student_id', $studentDbId);
                $enrollStmt->bindValue(':class_id', $input['class_id']);
                $enrollStmt->bindValue(':section_id', $input['section_id']);
                $enrollStmt->bindValue(':academic_year_id', $academicYear['id']);
                $enrollStmt->bindValue(':enrollment_date', $input['admission_date']);
                $enrollStmt->bindValue(':roll_number', $rollNumber);
                $enrollStmt->execute();

                // Auto-provision fees if not a government school
                if (strtolower($schoolType) !== 'government') {
                    $feeQuery = "INSERT INTO student_fees (student_id, fee_category_id, academic_year_id, amount, due_date, status)
                                SELECT :student_id, id, :ay_id, amount, DATE_ADD(NOW(), INTERVAL 30 DAY), 'pending'
                                FROM fee_categories 
                                WHERE school_id = :school_id AND is_mandatory = 1";
                    $feeStmt = $db->prepare($feeQuery);
                    $feeStmt->execute([
                        ':student_id' => $studentDbId,
                        ':ay_id' => $academicYear['id'],
                        ':school_id' => $user['school_id']
                    ]);
                }
            }
        }

        // Send welcome email if email provided
        if (!empty($input['email'])) {
            $emailService = new EmailService();
            $schoolQuery = "SELECT * FROM schools WHERE id = :school_id";
            $schoolStmt = $db->prepare($schoolQuery);
            $schoolStmt->bindValue(':school_id', $user['school_id']);
            $schoolStmt->execute();
            $school = $schoolStmt->fetch();

            $studentData = [
                'username' => $username,
                'email' => $input['email'],
                'first_name' => $input['first_name'],
                'last_name' => $input['last_name']
            ];

            $emailService->sendWelcomeEmail('student', $studentData, $school, $password);
        }

        // Log activity
        $logQuery = "INSERT INTO activity_logs (user_id, user_type, school_id, action, entity_type, entity_id, ip_address) 
                     VALUES (:user_id, 'user', :school_id, 'create_student', 'student', :entity_id, :ip_address)";
        $logStmt = $db->prepare($logQuery);
        $logStmt->bindValue(':user_id', $user['id']);
        $logStmt->bindValue(':school_id', $user['school_id']);
        $logStmt->bindValue(':entity_id', $studentDbId);
        $logStmt->bindValue(':ip_address', $_SERVER['REMOTE_ADDR']);
        $logStmt->execute();

        $db->commit();

        echo json_encode([
            'success' => true,
            'message' => 'Student created successfully',
            'data' => [
                'id' => $studentDbId,
                'student_id' => $studentId,
                'admission_number' => $admissionNumber,
                'username' => $username,
                'password' => $password,
                'email_sent' => !empty($input['email'])
            ]
        ]);

    }
    catch (Exception $e) {
        $db->rollBack();
        throw $e;
    }
}

function handleUpdateStudent($db, $user)
{
    $input = json_decode(file_get_contents('php://input'), true);

    if (!isset($input['id'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Student ID is required']);
        return;
    }

    // Check if student exists and belongs to the school
    $checkQuery = "SELECT * FROM students WHERE id = :id AND school_id = :school_id";
    $checkStmt = $db->prepare($checkQuery);
    $checkStmt->bindValue(':id', $input['id']);
    $checkStmt->bindValue(':school_id', $user['school_id']);
    $checkStmt->execute();

    if ($checkStmt->rowCount() === 0) {
        http_response_code(404);
        echo json_encode(['error' => 'Student not found']);
        return;
    }

    $currentStudent = $checkStmt->fetch();

    // Check if email already exists (excluding current student)
    if (!empty($input['email']) && $input['email'] !== $currentStudent['email']) {
        $emailCheckQuery = "SELECT id FROM students WHERE email = :email AND school_id = :school_id AND id != :id";
        $emailStmt = $db->prepare($emailCheckQuery);
        $emailStmt->bindValue(':email', $input['email']);
        $emailStmt->bindValue(':school_id', $user['school_id']);
        $emailStmt->bindValue(':id', $input['id']);
        $emailStmt->execute();

        if ($emailStmt->rowCount() > 0) {
            http_response_code(400);
            echo json_encode(['error' => 'Email already exists']);
            return;
        }
    }

    // Build update query dynamically
    $updateFields = [];
    $params = [':id' => $input['id']];

    $allowedFields = [
        'first_name',
        'middle_name',
        'last_name',
        'email',
        'date_of_birth',
        'gender',
        'blood_group',
        'caste',
        'religion',
        'nationality',
        'mother_tongue',
        'permanent_address',
        'current_address',
        'city',
        'state',
        'pincode',
        'father_name',
        'father_occupation',
        'father_phone',
        'father_email',
        'father_income',
        'mother_name',
        'mother_occupation',
        'mother_phone',
        'mother_email',
        'mother_income',
        'guardian_name',
        'guardian_relation',
        'guardian_phone',
        'guardian_email',
        'emergency_contact_name',
        'emergency_contact_phone',
        'emergency_contact_relation',
        'medical_conditions',
        'allergies',
        'medications',
        'previous_school',
        'previous_class',
        'fee_concession_type',
        'fee_concession_percentage',
        'aadhar_number',
        'status',
        'is_active'
    ];

    foreach ($allowedFields as $field) {
        if (array_key_exists($field, $input)) {
            $updateFields[] = "{$field} = :{$field}";
            $params[":{$field}"] = $input[$field];
        }
    }

    if (empty($updateFields)) {
        http_response_code(400);
        echo json_encode(['error' => 'No valid fields to update']);
        return;
    }

    $updateFields[] = "updated_at = NOW()";

    $updateQuery = "UPDATE students SET " . implode(', ', $updateFields) . " WHERE id = :id";
    $updateStmt = $db->prepare($updateQuery);

    foreach ($params as $key => $value) {
        $updateStmt->bindValue($key, $value);
    }

    $updateStmt->execute();

    // Log activity
    $logQuery = "INSERT INTO activity_logs (user_id, user_type, school_id, action, entity_type, entity_id, old_values, new_values, ip_address) 
                 VALUES (:user_id, 'user', :school_id, 'update_student', 'student', :entity_id, :old_values, :new_values, :ip_address)";
    $logStmt = $db->prepare($logQuery);
    $logStmt->bindValue(':user_id', $user['id']);
    $logStmt->bindValue(':school_id', $user['school_id']);
    $logStmt->bindValue(':entity_id', $input['id']);
    $logStmt->bindValue(':old_values', json_encode($currentStudent));
    $logStmt->bindValue(':new_values', json_encode($input));
    $logStmt->bindValue(':ip_address', $_SERVER['REMOTE_ADDR'] ?? null);
    $logStmt->execute();

    echo json_encode([
        'success' => true,
        'message' => 'Student updated successfully'
    ]);
}

function handleDeleteStudent($db, $user)
{
    $input = json_decode(file_get_contents('php://input'), true);

    if (!isset($input['id'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Student ID is required']);
        return;
    }

    // Check if student exists and belongs to the school
    $checkQuery = "SELECT * FROM students WHERE id = :id AND school_id = :school_id";
    $checkStmt = $db->prepare($checkQuery);
    $checkStmt->bindValue(':id', $input['id']);
    $checkStmt->bindValue(':school_id', $user['school_id']);
    $checkStmt->execute();

    if ($checkStmt->rowCount() === 0) {
        http_response_code(404);
        echo json_encode(['error' => 'Student not found']);
        return;
    }

    $student = $checkStmt->fetch();

    // Soft delete - update status to 'deleted'
    $deleteQuery = "UPDATE students SET status = 'deleted', updated_at = NOW() WHERE id = :id";
    $deleteStmt = $db->prepare($deleteQuery);
    $deleteStmt->bindValue(':id', $input['id']);
    $deleteStmt->execute();

    // Also update enrollments
    $enrollQuery = "UPDATE student_enrollments SET status = 'inactive' WHERE student_id = :student_id";
    $enrollStmt = $db->prepare($enrollQuery);
    $enrollStmt->bindValue(':student_id', $input['id']);
    $enrollStmt->execute();

    // Log activity
    $logQuery = "INSERT INTO activity_logs (user_id, user_type, school_id, action, entity_type, entity_id, old_values, ip_address) 
                 VALUES (:user_id, 'user', :school_id, 'delete_student', 'student', :entity_id, :old_values, :ip_address)";
    $logStmt = $db->prepare($logQuery);
    $logStmt->bindValue(':user_id', $user['id']);
    $logStmt->bindValue(':school_id', $user['school_id']);
    $logStmt->bindValue(':entity_id', $input['id']);
    $logStmt->bindValue(':old_values', json_encode($student));
    $logStmt->bindValue(':ip_address', $_SERVER['REMOTE_ADDR'] ?? null);
    $logStmt->execute();

    echo json_encode([
        'success' => true,
        'message' => 'Student deleted successfully'
    ]);
}
?>