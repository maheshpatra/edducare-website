<?php
require_once '../../middleware/auth.php';

$auth = new AuthMiddleware();
$user = $auth->authenticate();

if (!$user) {
    exit;
}

$database = new Database();
$db = $database->getConnection();

try {
    $userId = isset($_GET['user_id']) ? $_GET['user_id'] : $user['id'];
    
    // Security check - users can only view their own pictures unless they're admin
    if ($userId != $user['id'] && !in_array($user['role'], ['super_admin', 'school_admin'])) {
        http_response_code(403);
        echo json_encode(['error' => 'Access denied']);
        exit;
    }
    
    // Get active profile picture
    $query = "SELECT file_path, file_name, mime_type FROM user_profile_pictures 
              WHERE user_id = :user_id AND is_active = TRUE 
              ORDER BY uploaded_at DESC LIMIT 1";
    $stmt = $db->prepare($query);
    $stmt->bindValue(':user_id', $userId);
    $stmt->execute();
    
    if ($stmt->rowCount() === 0) {
        // Return default avatar
        $defaultAvatar = '../assets/default-avatar.png';
        if (file_exists($defaultAvatar)) {
            header('Content-Type: image/png');
            header('Cache-Control: public, max-age=86400'); // 24 hours
            readfile($defaultAvatar);
        } else {
            http_response_code(404);
            echo json_encode(['error' => 'No profile picture found']);
        }
        exit;
    }
    
    $picture = $stmt->fetch();
    $filePath = '../uploads/' . $picture['file_path'];
    
    if (!file_exists($filePath)) {
        http_response_code(404);
        echo json_encode(['error' => 'Profile picture file not found']);
        exit;
    }
    
    // Serve the image
    header('Content-Type: ' . $picture['mime_type']);
    header('Content-Length: ' . filesize($filePath));
    header('Cache-Control: public, max-age=86400'); // 24 hours
    header('Content-Disposition: inline; filename="' . $picture['file_name'] . '"');
    
    readfile($filePath);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Internal server error']);
}
?>
