<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

require_once '../../middleware/auth.php';

$auth = new AuthMiddleware();
$user = $auth->requireRole(['super_admin']);

if (!$user) {
    exit;
}

$database = new Database();
$db = $database->getConnection();

try {
    switch ($_SERVER['REQUEST_METHOD']) {
        case 'GET':
            $query = "SELECT * FROM packages ORDER BY price ASC";
            $stmt = $db->prepare($query);
            $stmt->execute();
            $packages = $stmt->fetchAll();
            
            echo json_encode([
                'success' => true,
                'data' => $packages
            ]);
            break;
            
        case 'POST':
            $input = json_decode(file_get_contents('php://input'), true);
            
            $required = ['name', 'price', 'duration_months'];
            foreach ($required as $field) {
                if (!isset($input[$field]) || empty($input[$field])) {
                    http_response_code(400);
                    echo json_encode(['error' => "Field '$field' is required"]);
                    exit;
                }
            }
            
            $insertQuery = "INSERT INTO packages (name, description, price, duration_months, max_students, max_teachers, features)
                           VALUES (:name, :description, :price, :duration_months, :max_students, :max_teachers, :features)";
            
            $insertStmt = $db->prepare($insertQuery);
            $insertStmt->bindValue(':name', $input['name']);
            $insertStmt->bindValue(':description', $input['description'] ?? null);
            $insertStmt->bindValue(':price', $input['price']);
            $insertStmt->bindValue(':duration_months', $input['duration_months']);
            $insertStmt->bindValue(':max_students', $input['max_students'] ?? null);
            $insertStmt->bindValue(':max_teachers', $input['max_teachers'] ?? null);
            $insertStmt->bindValue(':features', isset($input['features']) ? json_encode($input['features']) : null);
            
            $insertStmt->execute();
            
            echo json_encode([
                'success' => true,
                'message' => 'Package created successfully',
                'package_id' => $db->lastInsertId()
            ]);
            break;
            
        case 'PUT':
            $input = json_decode(file_get_contents('php://input'), true);
            
            if (!isset($input['id'])) {
                http_response_code(400);
                echo json_encode(['error' => 'Package ID is required']);
                exit;
            }
            
            $updateFields = [];
            $params = [':id' => $input['id']];
            
            $allowedFields = ['name', 'description', 'price', 'duration_months', 'max_students', 'max_teachers', 'features', 'is_active'];
            foreach ($allowedFields as $field) {
                if (isset($input[$field])) {
                    $updateFields[] = "$field = :$field";
                    $params[":$field"] = $field === 'features' ? json_encode($input[$field]) : $input[$field];
                }
            }
            
            if (empty($updateFields)) {
                http_response_code(400);
                echo json_encode(['error' => 'No fields to update']);
                exit;
            }
            
            $updateQuery = "UPDATE packages SET " . implode(', ', $updateFields) . " WHERE id = :id";
            $updateStmt = $db->prepare($updateQuery);
            
            foreach ($params as $key => $value) {
                $updateStmt->bindValue($key, $value);
            }
            
            $updateStmt->execute();
            
            echo json_encode([
                'success' => true,
                'message' => 'Package updated successfully'
            ]);
            break;
            
        case 'DELETE':
            $id = isset($_GET['id']) ? (int)$_GET['id'] : null;
            if (!$id) {
                http_response_code(400);
                echo json_encode(['error' => 'Package ID is required']);
                exit;
            }
            
            // Check if any school is using this package
            $checkQuery = "SELECT COUNT(*) as count FROM schools WHERE package_id = :id";
            $checkStmt = $db->prepare($checkQuery);
            $checkStmt->bindValue(':id', $id);
            $checkStmt->execute();
            
            if ($checkStmt->fetch()['count'] > 0) {
                http_response_code(400);
                echo json_encode(['error' => 'Package is in use by schools and cannot be deleted']);
                exit;
            }
            
            $deleteQuery = "DELETE FROM packages WHERE id = :id";
            $deleteStmt = $db->prepare($deleteQuery);
            $deleteStmt->bindValue(':id', $id);
            $deleteStmt->execute();
            
            echo json_encode([
                'success' => true,
                'message' => 'Package deleted successfully'
            ]);
            break;
            
        default:
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
            break;
    }
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Internal server error: ' . $e->getMessage()]);
}
?>
