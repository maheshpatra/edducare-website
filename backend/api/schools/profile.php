<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

require_once '../../middleware/auth.php';
require_once '../../config/file_upload.php';

$auth = new AuthMiddleware();
// Allow school admins or super admins
$user = $auth->requireRole(['school_admin', 'super_admin', 'admin']);

if (!$user) {
    exit;
}

$database = new Database();
$db = $database->getConnection();

// If super_admin, they can specify a school id via GET, otherwise use the logged-in user's school
$schoolId = ($user['role'] === 'super_admin' && isset($_GET['id'])) ? (int)$_GET['id'] : $user['school_id'];

if (!$schoolId) {
    http_response_code(400);
    echo json_encode(['error' => 'No school associated with this account or ID missing.']);
    exit;
}

try {
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $stmt = $db->prepare("SELECT * FROM schools WHERE id = :id");
        $stmt->execute([':id' => $schoolId]);
        $school = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$school) {
            http_response_code(404);
            echo json_encode(['error' => 'School not found.']);
            exit;
        }
        
        echo json_encode(['success' => true, 'data' => $school]);
        
    } elseif ($_SERVER['REQUEST_METHOD'] === 'POST' || $_SERVER['REQUEST_METHOD'] === 'PUT') {
        $input = json_decode(file_get_contents('php://input'), true) ?? [];
        $input = array_merge($input, $_POST);
        
        // Handle Logo Upload
        $logoPath = null;
        if (isset($_FILES['logo']) && $_FILES['logo']['error'] === UPLOAD_ERR_OK) {
            $uploader = new FileUpload();
            try {
                $uploaded = $uploader->uploadFile($_FILES['logo'], 'schools');
                $logoPath = $uploaded['relative_path'];
            } catch (Exception $e) {
                // If it's just a logo upload error, we might log it but keep going if other fields are present?
                // Actually, if they try to upload and it fails, we should probably error.
                throw $e;
            }
        } elseif (isset($_FILES['logo']) && $_FILES['logo']['error'] !== UPLOAD_ERR_NO_FILE) {
            throw new Exception("File upload error code: " . $_FILES['logo']['error']);
        }

        // Use column names matching our schema
        $fieldMap = [
            'name' => 'name',
            'address' => 'address',
            'phone' => 'phone',
            'email' => 'email',
            'website' => 'website',
            'principal_name' => 'principal_name',
            'established_year' => 'established_year',
            'school_type' => 'school_type'
        ];
        
        $updateFields = [];
        $params = [':id' => $schoolId];
        
        foreach ($fieldMap as $apiKey => $dbCol) {
            if (array_key_exists($apiKey, $input)) {
                $updateFields[] = "$dbCol = :$dbCol";
                $params[":$dbCol"] = $input[$apiKey];
            }
        }

        if ($logoPath) {
            $updateFields[] = "logo = :logo";
            $params[':logo'] = $logoPath;
        }
        
        if (empty($updateFields)) {
            http_response_code(400);
            echo json_encode(['error' => 'No valid fields provided for update.']);
            exit;
        }
        
        $sql = "UPDATE schools SET " . implode(', ', $updateFields) . " WHERE id = :id";
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        
        echo json_encode([
            'success' => true, 
            'message' => 'School settings updated successfully.',
            'logo' => $logoPath
        ]);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Service error: ' . $e->getMessage()]);
}
?>
