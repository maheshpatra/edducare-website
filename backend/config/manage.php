<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

require_once '../../middleware/auth.php';
require_once '../../config/file_upload.php';
require_once '../../config/email.php';

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
            $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
            $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 20;
            $offset = ($page - 1) * $limit;
            
            $query = "SELECT s.*, p.name as package_name, p.price as package_price,
                      (SELECT COUNT(*) FROM students WHERE school_id = s.id) as student_count,
                      (SELECT COUNT(*) FROM users u 
                       JOIN user_roles ur ON u.role_id = ur.id 
                       WHERE u.school_id = s.id AND ur.name LIKE 'teacher%') as teacher_count
                      FROM schools s
                      LEFT JOIN packages p ON s.package_id = p.id
                      ORDER BY s.created_at DESC
                      LIMIT :limit OFFSET :offset";
            
            $stmt = $db->prepare($query);
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
            $stmt->execute();
            $schools = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $countQuery = "SELECT COUNT(*) as total FROM schools";
            $countStmt = $db->prepare($countQuery);
            $countStmt->execute();
            $total = (int)$countStmt->fetch(PDO::FETCH_ASSOC)['total'];
            
            echo json_encode([
                'success' => true,
                'data' => $schools,
                'pagination' => [
                    'current_page' => $page,
                    'per_page' => $limit,
                    'total' => $total,
                    'total_pages' => ceil($total / $limit)
                ]
            ]);
            break;
            
        case 'POST':
            $data = !empty($_POST) ? $_POST : json_decode(file_get_contents('php://input'), true);
            
            if (!$data) {
                http_response_code(400);
                echo json_encode(['error' => 'No data provided']);
                exit;
            }

            if (empty($data['code'])) {
                $data['code'] = 'EDU-' . strtoupper(substr(md5(uniqid(rand(), true)), 0, 6));
            }
            
            $required = ['name', 'package_id'];
            foreach ($required as $field) {
                if (!isset($data[$field]) || empty($data[$field])) {
                    http_response_code(400);
                    echo json_encode(['error' => "Field '$field' is required"]);
                    exit;
                }
            }
            
            $db->beginTransaction();
            
            $checkQuery = "SELECT id FROM schools WHERE code = :code";
            $checkStmt = $db->prepare($checkQuery);
            $checkStmt->bindValue(':code', $data['code']);
            $checkStmt->execute();
            
            if ($checkStmt->rowCount() > 0) {
                http_response_code(400);
                echo json_encode(['error' => 'School code already exists (' . $data['code'] . ')']);
                exit;
            }
            
            $logoPath = null;
            if (isset($_FILES['logo']) && $_FILES['logo']['error'] === UPLOAD_ERR_OK) {
                $uploader = new FileUpload();
                $uploaded = $uploader->uploadFile($_FILES['logo'], 'schools');
                $logoPath = $uploaded['relative_path'];
            }

            // Safety check for dynamic columns
            $cols = $db->query("DESCRIBE schools")->fetchAll(PDO::FETCH_COLUMN);
            $hasPrincipal = in_array('principal_name', $cols);
            $hasLogo = in_array('logo', $cols);
            $hasType = in_array('school_type', $cols);
            $hasYear = in_array('established_year', $cols);
            
            $fields = ['name', 'code', 'package_id', 'is_active'];
            $placeholders = [':name', ':code', ':package_id', '1'];
            
            if ($hasPrincipal) { $fields[] = 'principal_name'; $placeholders[] = ':principal_name'; }
            if ($hasLogo) { $fields[] = 'logo'; $placeholders[] = ':logo'; }
            if ($hasType) { $fields[] = 'school_type'; $placeholders[] = ':school_type'; }
            if ($hasYear) { $fields[] = 'established_year'; $placeholders[] = ':established_year'; }

            if (isset($data['email'])) { $fields[] = 'email'; $placeholders[] = ':email'; }
            if (isset($data['phone'])) { $fields[] = 'phone'; $placeholders[] = ':phone'; }
            if (isset($data['address'])) { $fields[] = 'address'; $placeholders[] = ':address'; }
            
            $insertQuery = "INSERT INTO schools (" . implode(', ', $fields) . ") VALUES (" . implode(', ', $placeholders) . ")";
            
            $insertStmt = $db->prepare($insertQuery);
            $insertStmt->bindValue(':name', $data['name']);
            $insertStmt->bindValue(':code', $data['code']);
            $insertStmt->bindValue(':package_id', $data['package_id']);
            
            if ($hasPrincipal) $insertStmt->bindValue(':principal_name', $data['principal_name'] ?? null);
            if ($hasLogo) $insertStmt->bindValue(':logo', $logoPath);
            if ($hasType) $insertStmt->bindValue(':school_type', $data['school_type'] ?? 'General');
            if ($hasYear) $insertStmt->bindValue(':established_year', $data['established_year'] ?? null);
            if (isset($data['email'])) $insertStmt->bindValue(':email', $data['email']);
            if (isset($data['phone'])) $insertStmt->bindValue(':phone', $data['phone']);
            if (isset($data['address'])) $insertStmt->bindValue(':address', $data['address']);
            
            $insertStmt->execute();
            $schoolId = $db->lastInsertId();
            
            // Create default academic year
            $academicQuery = "INSERT INTO academic_years (school_id, name, start_date, end_date, is_current)
                              VALUES (:school_id, :name, :start_date, :end_date, TRUE)";
            $academicStmt = $db->prepare($academicQuery);
            $academicStmt->bindValue(':school_id', $schoolId);
            $academicStmt->bindValue(':name', date('Y') . '-' . (date('Y') + 1));
            $academicStmt->bindValue(':start_date', date('Y-04-01'));
            $academicStmt->bindValue(':end_date', (date('Y') + 1) . '-03-31');
            $academicStmt->execute();
            
            $db->commit();

            // SEND THE EMAIL
            if (!empty($data['email'])) {
                try {
                    $emailSvc = new EmailService();
                    $emailSvc->sendSchoolWelcomeEmail([
                        'id' => $schoolId,
                        'name' => $data['name'],
                        'email' => $data['email'],
                        'code' => $data['code']
                    ]);
                } catch (Exception $e) {
                    error_log("Email failed: " . $e->getMessage());
                }
            }
            
            echo json_encode([
                'success' => true,
                'message' => 'School created successfully!',
                'school_id' => $schoolId,
                'code' => $data['code']
            ]);
            break;
            
        case 'PUT':
            $input = json_decode(file_get_contents('php://input'), true);
            if (!isset($input['id'])) {
                http_response_code(400);
                echo json_encode(['error' => 'School ID is required']);
                exit;
            }

            $cols = $db->query("DESCRIBE schools")->fetchAll(PDO::FETCH_COLUMN);
            $hasPrincipal = in_array('principal_name', $cols);
            $hasType = in_array('school_type', $cols);
            $hasYear = in_array('established_year', $cols);
            
            $updateFields = [];
            $params = [':id' => $input['id']];
            
            $allowedFields = ['name', 'address', 'phone', 'email', 'package_id', 'is_active', 'is_blocked'];
            if ($hasPrincipal) $allowedFields[] = 'principal_name';
            if ($hasType) $allowedFields[] = 'school_type';
            if ($hasYear) $allowedFields[] = 'established_year';

            foreach ($allowedFields as $field) {
                if (isset($input[$field])) {
                    $updateFields[] = "$field = :$field";
                    $params[":$field"] = $input[$field];
                }
            }
            
            if (empty($updateFields)) {
                http_response_code(400);
                echo json_encode(['error' => 'No fields to update']);
                exit;
            }
            
            $updateQuery = "UPDATE schools SET " . implode(', ', $updateFields) . " WHERE id = :id";
            $updateStmt = $db->prepare($updateQuery);
            foreach ($params as $key => $value) {
                $updateStmt->bindValue($key, $value);
            }
            $updateStmt->execute();
            
            echo json_encode(['success' => true, 'message' => 'School updated successfully']);
            break;
            
        case 'DELETE':
            $id = isset($_GET['id']) ? (int)$_GET['id'] : null;
            if (!$id) {
                http_response_code(400);
                echo json_encode(['error' => 'ID required']);
                exit;
            }
            $del = $db->prepare("DELETE FROM schools WHERE id = :id");
            $del->execute([':id' => $id]);
            echo json_encode(['success' => true, 'message' => 'School deleted']);
            break;

        default:
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
            break;
    }
    
} catch (Exception $e) {
    if (isset($db) && $db->inTransaction()) { $db->rollBack(); }
    http_response_code(500);
    echo json_encode(['error' => 'Internal server error: ' . $e->getMessage()]);
}
?>
