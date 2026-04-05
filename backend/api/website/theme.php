<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
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
        $query = "SELECT * FROM school_themes WHERE school_id = :school_id LIMIT 1";
        $stmt = $db->prepare($query);
        $stmt->execute([':school_id' => $school_id]);
        $theme = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$theme) {
            $theme = [
                'primary_color' => '#3b82f6',
                'secondary_color' => '#1e40af',
                'font_family' => 'Inter, sans-serif',
                'layout_style' => 'modern',
                'principal_message' => 'Welcome to our school. We are committed to fostering an inclusive environment for all students.',
                'about_text' => 'We are a premier educational institution with a legacy of excellence.',
            ];
        }

        echo json_encode(['success' => true, 'data' => $theme]);
    } 
    elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $data = !empty($_POST) ? $_POST : json_decode(file_get_contents("php://input"), true);
        
        $fields = [
            'primary_color', 'secondary_color', 'font_family', 
            'layout_style', 'principal_message', 'about_text'
        ];
        
        $updateData = [];
        foreach ($fields as $field) {
            if (isset($data[$field])) {
                $updateData[$field] = $data[$field];
            }
        }

        // Handle Image Uploads
        $upload_dir = __DIR__ . '/../../uploads/schools/';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }

        $images = [
            'hero_bg_image' => 'hero',
            'principal_image' => 'principal',
            'about_image' => 'about'
        ];

        foreach ($images as $key => $prefix) {
            if (isset($_FILES[$key]) && $_FILES[$key]['error'] === UPLOAD_ERR_OK) {
                $ext = pathinfo($_FILES[$key]['name'], PATHINFO_EXTENSION);
                $filename = 'school_' . $school_id . '_' . $prefix . '_' . uniqid() . '.' . $ext;
                if (move_uploaded_file($_FILES[$key]['tmp_name'], $upload_dir . $filename)) {
                    $updateData[$key] = 'schools/' . $filename;
                }
            }
        }

        if (empty($updateData)) {
            echo json_encode(['success' => true, 'message' => 'No changes made']);
            exit;
        }

        // Check if theme exists
        $check_query = "SELECT id FROM school_themes WHERE school_id = :school_id";
        $check_stmt = $db->prepare($check_query);
        $check_stmt->execute([':school_id' => $school_id]);
        
        if ($check_stmt->fetch()) {
            $set_part = [];
            foreach ($updateData as $k => $v) {
                $set_part[] = "$k = :$k";
            }
            $query = "UPDATE school_themes SET " . implode(', ', $set_part) . " WHERE school_id = :school_id";
        } else {
            $updateData['school_id'] = $school_id;
            $cols = implode(', ', array_keys($updateData));
            $params = ':' . implode(', :', array_keys($updateData));
            $query = "INSERT INTO school_themes ($cols) VALUES ($params)";
        }

        $stmt = $db->prepare($query);
        $updateData['school_id'] = $school_id;
        $stmt->execute($updateData);

        echo json_encode(['success' => true, 'message' => 'Theme updated successfully']);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Server error: ' . $e->getMessage()]);
}
?>
