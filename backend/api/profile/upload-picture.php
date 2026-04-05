<?php
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(0);
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// ── Handle CORS preflight ───────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once '../../middleware/auth.php';
require_once '../../config/file_upload.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$auth = new AuthMiddleware();
$user = $auth->authenticate();

if (!$user) {
    exit;
}

$database = new Database();
$db = $database->getConnection();

try {
    if (!isset($_FILES['profile_picture']) || $_FILES['profile_picture']['error'] !== UPLOAD_ERR_OK) {
        http_response_code(400);
        echo json_encode(['error' => 'No file uploaded or upload error']);
        exit;
    }
    
    $file = $_FILES['profile_picture'];
    
    // Validate file type (only images)
    $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mimeType = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);
    
    if (!in_array($mimeType, $allowedTypes)) {
        http_response_code(400);
        echo json_encode(['error' => 'Only image files (JPEG, PNG, GIF, WebP) are allowed']);
        exit;
    }
    
    // Validate file size (max 5MB)
    $maxSize = 5 * 1024 * 1024; // 5MB
    if ($file['size'] > $maxSize) {
        http_response_code(400);
        echo json_encode(['error' => 'File size must be less than 5MB']);
        exit;
    }
    
    // Validate image dimensions
    $imageInfo = getimagesize($file['tmp_name']);
    if (!$imageInfo) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid image file']);
        exit;
    }
    
    $width = $imageInfo[0];
    $height = $imageInfo[1];
    
    if ($width > 2000 || $height > 2000) {
        http_response_code(400);
        echo json_encode(['error' => 'Image dimensions must be less than 2000x2000 pixels']);
        exit;
    }
    
    $db->beginTransaction();
    
    // Create upload directory if it doesn't exist
    $uploadDir = '../uploads/profile_pictures/';
    if (!file_exists($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }
    
    // Generate unique filename
    $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $fileName = 'profile_' . $user['id'] . '_' . time() . '.' . $extension;
    $filePath = $uploadDir . $fileName;
    $relativePath = 'profile_pictures/' . $fileName;
    
    // Move uploaded file
    if (!move_uploaded_file($file['tmp_name'], $filePath)) {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to save uploaded file']);
        exit;
    }
    
    // Deactivate old profile pictures
    $deactivateQuery = "UPDATE user_profile_pictures SET is_active = FALSE WHERE user_id = :user_id";
    $deactivateStmt = $db->prepare($deactivateQuery);
    $deactivateStmt->bindValue(':user_id', $user['id']);
    $deactivateStmt->execute();
    
    // Insert new profile picture record
    $insertQuery = "INSERT INTO user_profile_pictures (user_id, file_path, file_name, file_size, mime_type) 
                    VALUES (:user_id, :file_path, :file_name, :file_size, :mime_type)";
    $insertStmt = $db->prepare($insertQuery);
    $insertStmt->bindValue(':user_id', $user['id']);
    $insertStmt->bindValue(':file_path', $relativePath);
    $insertStmt->bindValue(':file_name', $file['name']);
    $insertStmt->bindValue(':file_size', $file['size']);
    $insertStmt->bindValue(':mime_type', $mimeType);
    $insertStmt->execute();
    
    // Log activity
    // $logQuery = "INSERT INTO activity_logs (user_id, school_id, action, table_name, new_values, ip_address, user_agent) 
    //              VALUES (:user_id, :school_id, 'upload_profile_picture', 'user_profile_pictures', :new_values, :ip, :user_agent)";
    // $logStmt = $db->prepare($logQuery);
    // $logStmt->bindValue(':user_id', $user['id']);
    // $logStmt->bindValue(':school_id', $user['school_id']);
    // $newValues = json_encode(['file_name' => $fileName]);
    // $logStmt->bindValue(':new_values', $newValues);
    // $logStmt->bindValue(':ip', $_SERVER['REMOTE_ADDR']);
    // $logStmt->bindValue(':user_agent', $_SERVER['HTTP_USER_AGENT']);
    // $logStmt->execute();
    
    $db->commit();
    
    echo json_encode([
        'success' => true,
        'message' => 'Profile picture uploaded successfully',
        'file_path' => $relativePath,
        'file_url' => '/api/profile/picture?user_id=' . $user['id']
    ]);
    
} catch (Exception $e) {
    if (isset($db) && $db->inTransaction()) {
        $db->rollBack();
    }
    
    // Clean up uploaded file if database operation failed
    if (isset($filePath) && file_exists($filePath)) {
        unlink($filePath);
    }
    
    http_response_code(500);
    echo json_encode(['error' => 'Internal server error']);
}
?>
