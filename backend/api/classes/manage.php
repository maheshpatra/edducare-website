<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

require_once '../../middleware/auth.php';
require_once '../../config/database.php';

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
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
            if (isset($_GET['action']) && $_GET['action'] === 'sections') {
                handleGetSections($db, $user);
            } else {
                handleGetClasses($db, $user);
            }
            break;
        case 'POST':
            if (isset($_GET['action']) && $_GET['action'] === 'create_section') {
                handleCreateSection($db, $user);
            } else {
                handleCreateClass($db, $user);
            }
            break;
        case 'PUT':
            handleUpdateClass($db, $user);
            break;
        case 'DELETE':
            if (isset($_GET['action']) && $_GET['action'] === 'delete_section') {
                handleDeleteSection($db, $user);
            } else {
                handleDeleteClass($db, $user);
            }
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

function handleGetClasses($db, $user) {
    $schoolCondition = "";
    $params = [];
    
    if ($user['role'] !== 'super_admin') {
        $schoolCondition = "WHERE c.school_id = :school_id";
        $params[':school_id'] = $user['school_id'];
    } else if (isset($_GET['school_id'])) {
        $schoolCondition = "WHERE c.school_id = :school_id";
        $params[':school_id'] = $_GET['school_id'];
    }
    
    $query = "SELECT c.*, ay.name as academic_year_name, 
                     u.first_name as teacher_first_name, u.last_name as teacher_last_name,
                     s.name as school_name,
                     COUNT(DISTINCT sec.id) as section_count,
                     COUNT(DISTINCT se.student_id) as student_count
              FROM classes c
              LEFT JOIN academic_years ay ON c.academic_year_id = ay.id
              LEFT JOIN users u ON c.class_teacher_id = u.id
              LEFT JOIN schools s ON c.school_id = s.id
              LEFT JOIN sections sec ON c.id = sec.class_id
              LEFT JOIN student_enrollments se ON c.id = se.class_id AND se.status = 'active'
              $schoolCondition
              GROUP BY c.id
              ORDER BY c.grade_level, c.name";
    
    $stmt = $db->prepare($query);
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->execute();
    
    $classes = $stmt->fetchAll();
    
    echo json_encode([
        'success' => true,
        'data' => $classes
    ]);
}

function handleCreateClass($db, $user) {
    $input = json_decode(file_get_contents('php://input'), true);
    
    $required = ['name', 'grade_level'];
    foreach ($required as $field) {
        if (!isset($input[$field])) {
            http_response_code(400);
            echo json_encode(['error' => "Field '$field' is required"]);
            return;
        }
    }
    
    $schoolId = $user['role'] === 'super_admin' ? $input['school_id'] : $user['school_id'];
    
    $db->beginTransaction();
    
    try {
        $query = "INSERT INTO classes (school_id, name, grade_level, class_teacher_id, room_number, capacity) 
                  VALUES (:school_id, :name, :grade_level, :class_teacher_id, :room_number, :capacity)";
        
        $stmt = $db->prepare($query);
        $stmt->bindValue(':school_id', $schoolId);
        $stmt->bindValue(':name', $input['name']);
        $stmt->bindValue(':grade_level', $input['grade_level']);
        $stmt->bindValue(':class_teacher_id', $input['class_teacher_id'] ?? null);
        $stmt->bindValue(':room_number', $input['room_number'] ?? null);
        $stmt->bindValue(':capacity', $input['capacity'] ?? 30);
        
        $stmt->execute();
        $classId = $db->lastInsertId();
        
        // Create default sections if provided
        if (isset($input['sections']) && is_array($input['sections'])) {
            foreach ($input['sections'] as $section) {
                $sectionQuery = "INSERT INTO sections (class_id, name, teacher_id, capacity) 
                                VALUES (:class_id, :name, :teacher_id, :capacity)";
                $sectionStmt = $db->prepare($sectionQuery);
                $sectionStmt->bindValue(':class_id', $classId);
                $sectionStmt->bindValue(':name', $section['name']);
                $sectionStmt->bindValue(':teacher_id', $section['teacher_id'] ?? null);
                $sectionStmt->bindValue(':capacity', $section['capacity'] ?? 30);
                $sectionStmt->execute();
            }
        }
        
        $db->commit();
        
        echo json_encode([
            'success' => true,
            'message' => 'Class created successfully',
            'class_id' => $classId
        ]);
        
    } catch (Exception $e) {
        $db->rollBack();
        throw $e;
    }
}

function handleUpdateClass($db, $user) {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($input['id'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Class ID is required']);
        return;
    }
    
    $updateFields = [];
    $params = [':id' => $input['id']];
    
    $allowedFields = ['name', 'grade_level', 'class_teacher_id', 'room_number', 'capacity'];
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
    
    $updateFields[] = "updated_at = NOW()";
    $query = "UPDATE classes SET " . implode(', ', $updateFields) . " WHERE id = :id";
    
    if ($user['role'] !== 'super_admin') {
        $query .= " AND school_id = :school_id";
        $params[':school_id'] = $user['school_id'];
    }
    
    $stmt = $db->prepare($query);
    $stmt->execute($params);
    
    if ($stmt->rowCount() === 0) {
        http_response_code(404);
        echo json_encode(['error' => 'Class not found']);
        return;
    }
    
    echo json_encode([
        'success' => true,
        'message' => 'Class updated successfully'
    ]);
}

function handleDeleteClass($db, $user) {
    $classId = $_GET['id'] ?? null;
    
    if (!$classId) {
        http_response_code(400);
        echo json_encode(['error' => 'Class ID is required']);
        return;
    }
    
    // Check if class has students
    $checkQuery = "SELECT COUNT(*) as student_count FROM student_enrollments WHERE class_id = :class_id AND status = 'active'";
    $checkStmt = $db->prepare($checkQuery);
    $checkStmt->bindValue(':class_id', $classId);
    $checkStmt->execute();
    
    $result = $checkStmt->fetch();
    if ($result['student_count'] > 0) {
        http_response_code(400);
        echo json_encode(['error' => 'Cannot delete class with active students']);
        return;
    }
    
    $query = "DELETE FROM classes WHERE id = :id";
    if ($user['role'] !== 'super_admin') {
        $query .= " AND school_id = :school_id";
    }
    
    $stmt = $db->prepare($query);
    $stmt->bindValue(':id', $classId);
    if ($user['role'] !== 'super_admin') {
        $stmt->bindValue(':school_id', $user['school_id']);
    }
    
    $stmt->execute();
    
    if ($stmt->rowCount() === 0) {
        http_response_code(404);
        echo json_encode(['error' => 'Class not found']);
        return;
    }
    
    echo json_encode([
        'success' => true,
        'message' => 'Class deleted successfully'
    ]);
}

function handleGetSections($db, $user) {
    if (!isset($_GET['class_id'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Class ID is required']);
        return;
    }
    
    $classId = intval($_GET['class_id']);
    
    $query = "SELECT s.*, CONCAT(u.first_name, ' ', u.last_name) as teacher_name
              FROM sections s
              LEFT JOIN users u ON s.teacher_id = u.id
              WHERE s.class_id = :class_id
              ORDER BY s.name";
              
    $stmt = $db->prepare($query);
    $stmt->bindValue(':class_id', $classId);
    $stmt->execute();
    
    $sections = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'data' => $sections
    ]);
}

function handleCreateSection($db, $user) {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($input['class_id']) || !isset($input['name'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Class ID and section name are required']);
        return;
    }
    
    $query = "INSERT INTO sections (class_id, name, capacity) VALUES (:class_id, :name, :capacity)";
    $stmt = $db->prepare($query);
    $stmt->bindValue(':class_id', $input['class_id']);
    $stmt->bindValue(':name', $input['name']);
    $stmt->bindValue(':capacity', $input['capacity'] ?? 30);
    
    try {
        $stmt->execute();
        echo json_encode([
            'success' => true,
            'message' => 'Section created successfully',
            'section_id' => $db->lastInsertId()
        ]);
    } catch (Exception $e) {
        http_response_code(400);
        echo json_encode(['error' => 'Failed to create section - might already exist']);
    }
}

function handleDeleteSection($db, $user) {
    $sectionId = $_GET['id'] ?? null;
    if (!$sectionId) {
        http_response_code(400);
        echo json_encode(['error' => 'Section ID is required']);
        return;
    }
    
    // Check if section has students
    $checkQuery = "SELECT COUNT(*) as st_count FROM student_enrollments WHERE section_id = :section_id AND status = 'active'";
    $checkStmt = $db->prepare($checkQuery);
    $checkStmt->bindValue(':section_id', $sectionId);
    $checkStmt->execute();
    $result = $checkStmt->fetch();
    
    if ($result['st_count'] > 0) {
        http_response_code(400);
        echo json_encode(['error' => 'Cannot delete section with active students']);
        return;
    }
    
    $query = "DELETE FROM sections WHERE id = :id";
    $stmt = $db->prepare($query);
    $stmt->bindValue(':id', $sectionId);
    $stmt->execute();
    
    if ($stmt->rowCount() === 0) {
        http_response_code(404);
        echo json_encode(['error' => 'Section not found']);
        return;
    }
    
    echo json_encode(['success' => true, 'message' => 'Section deleted successfully']);
}
?>
