<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

require_once '../../middleware/auth.php';
require_once '../../config/database.php';

$auth = new AuthMiddleware();
$user = $auth->requireRole(['super_admin', 'school_admin']);

if (!$user) {
    exit;
}

$database = new Database();
$db = $database->getConnection();

try {
    switch ($_SERVER['REQUEST_METHOD']) {
        case 'GET':
            handleGetAcademicYears($db, $user);
            break;
        case 'POST':
            handleCreateAcademicYear($db, $user);
            break;
        case 'PUT':
            handleUpdateAcademicYear($db, $user);
            break;
        default:
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'error' => 'Internal server error',
        'details' => $e->getMessage()
    ]);
}

function handleGetAcademicYears($db, $user) {
    $schoolCondition = "";
    $params = [];
    
    if ($user['role'] !== 'super_admin') {
        $schoolCondition = "WHERE school_id = :school_id";
        $params[':school_id'] = $user['school_id'];
    } else if (isset($_GET['school_id'])) {
        $schoolCondition = "WHERE school_id = :school_id";
        $params[':school_id'] = $_GET['school_id'];
    }
    
    if (isset($_GET['current'])) {
        $schoolCondition .= ($schoolCondition ? " AND " : "WHERE ") . "is_current = 1";
    }
    
    $query = "SELECT * FROM academic_years $schoolCondition ORDER BY start_date DESC";
    
    $stmt = $db->prepare($query);
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->execute();
    
    $years = $stmt->fetchAll();
    
    echo json_encode([
        'success' => true,
        'data' => $years
    ]);
}

function handleCreateAcademicYear($db, $user) {
    $input = json_decode(file_get_contents('php://input'), true);
    
    $required = ['name', 'start_date', 'end_date'];
    foreach ($required as $field) {
        if (!isset($input[$field])) {
            http_response_code(400);
            echo json_encode(['error' => "Field '$field' is required"]);
            return;
        }
    }
    
    $schoolId = $user['role'] === 'super_admin' ? $input['school_id'] : $user['school_id'];
    
    $query = "INSERT INTO academic_years (school_id, name, start_date, end_date, is_current) 
              VALUES (:school_id, :name, :start_date, :end_date, :is_current)";
    
    $stmt = $db->prepare($query);
    $stmt->bindValue(':school_id', $schoolId);
    $stmt->bindValue(':name', $input['name']);
    $stmt->bindValue(':start_date', $input['start_date']);
    $stmt->bindValue(':end_date', $input['end_date']);
    $stmt->bindValue(':is_current', $input['is_current'] ?? false, PDO::PARAM_BOOL);
    
    $stmt->execute();
    
    echo json_encode([
        'success' => true,
        'message' => 'Academic year created successfully',
        'id' => $db->lastInsertId()
    ]);
}

function handleUpdateAcademicYear($db, $user) {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($input['id'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Academic year ID is required']);
        return;
    }
    
    // If setting as current, unset other current years for the school
    if (isset($input['is_current']) && $input['is_current']) {
        $schoolId = $user['role'] === 'super_admin' ? $input['school_id'] : $user['school_id'];
        $unsetQuery = "UPDATE academic_years SET is_current = FALSE WHERE school_id = :school_id";
        $unsetStmt = $db->prepare($unsetQuery);
        $unsetStmt->bindValue(':school_id', $schoolId);
        $unsetStmt->execute();
    }
    
    $updateFields = [];
    $params = [':id' => $input['id']];
    
    $allowedFields = ['name', 'start_date', 'end_date', 'is_current'];
    foreach ($allowedFields as $field) {
        if (isset($input[$field])) {
            $updateFields[] = "$field = :$field";
            $params[":$field"] = $input[$field];
        }
    }
    
    if (empty($updateFields)) {
        http_response_code(400);
        echo json_encode(['error' => 'No fields to update']);
        return;
    }
    
    $query = "UPDATE academic_years SET " . implode(', ', $updateFields) . " WHERE id = :id";
    
    if ($user['role'] !== 'super_admin') {
        $query .= " AND school_id = :school_id";
        $params[':school_id'] = $user['school_id'];
    }
    
    $stmt = $db->prepare($query);
    $stmt->execute($params);
    
    if ($stmt->rowCount() === 0) {
        http_response_code(404);
        echo json_encode(['error' => 'Academic year not found']);
        return;
    }
    
    echo json_encode([
        'success' => true,
        'message' => 'Academic year updated successfully'
    ]);
}
?>
