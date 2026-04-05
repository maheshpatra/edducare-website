<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit;
}

require_once '../../middleware/auth.php';

$auth = new AuthMiddleware();
$user = $auth->requireRole(['super_admin', 'school_admin']);

if (!$user) {
    exit;
}

$database = new Database();
$db = $database->getConnection();

$school_id = $user['school_id'];
if ($user['role'] === 'super_admin' && isset($_GET['school_id'])) {
    $school_id = (int)$_GET['school_id'];
}

try {
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $query = "SELECT id, image_path, caption, category, is_active FROM school_gallery 
                  WHERE school_id = :school_id 
                  ORDER BY created_at DESC";
        $stmt = $db->prepare($query);
        $stmt->execute([':school_id' => $school_id]);
        $gallery = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode(['success' => true, 'data' => $gallery]);
    } 
    elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!isset($_FILES['image']) || $_FILES['image']['error'] !== UPLOAD_ERR_OK) {
            http_response_code(400);
            echo json_encode(['error' => 'No image uploaded']);
            exit;
        }

        $caption = $_POST['caption'] ?? '';
        $category = $_POST['category'] ?? 'General';
        
        $upload_dir = __DIR__ . '/../../uploads/gallery/';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }

        $ext = pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION);
        $filename = 'gallery_' . $school_id . '_' . uniqid() . '.' . $ext;
        
        if (move_uploaded_file($_FILES['image']['tmp_name'], $upload_dir . $filename)) {
            $image_path = 'gallery/' . $filename;
            $query = "INSERT INTO school_gallery (school_id, image_path, caption, category) 
                      VALUES (:school_id, :image_path, :caption, :category)";
            $stmt = $db->prepare($query);
            $stmt->execute([
                ':school_id' => $school_id,
                ':image_path' => $image_path,
                ':caption' => $caption,
                ':category' => $category
            ]);
            echo json_encode(['success' => true, 'message' => 'Image uploaded successfully']);
        } else {
            echo json_encode(['error' => 'Failed to move uploaded file']);
        }
    } 
    elseif ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
        $id = $_GET['id'] ?? null;
        if (!$id) {
            http_response_code(400);
            echo json_encode(['error' => 'ID is required']);
            exit;
        }

        $query = "DELETE FROM school_gallery WHERE id = :id AND school_id = :school_id";
        $stmt = $db->prepare($query);
        $stmt->execute([':id' => (int)$id, ':school_id' => $school_id]);
        
        if ($stmt->rowCount() > 0) {
            echo json_encode(['success' => true, 'message' => 'Image deleted']);
        } else {
            echo json_encode(['error' => 'Image not found or access denied']);
        }
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Server error: ' . $e->getMessage()]);
}
?>
