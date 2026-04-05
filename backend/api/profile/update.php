<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: PUT, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

require_once '../../middleware/auth.php';

$auth = new AuthMiddleware();
$user = $auth->requireAuth();

if (!$user) {
    exit;
}

$database = new Database();
$db = $database->getConnection();

try {
    $input = json_decode(file_get_contents('php://input'), true);
    if (!$input) {
        http_response_code(400);
        echo json_encode(['error' => 'No data provided']);
        exit;
    }

    $updateFields = [];
    $params = [':id' => $user['id']];
    $userType = $user['user_type']; // 'student' or 'user' (staff)
    $table = ($userType === 'student') ? 'students' : 'users';

    // 1. Update Profile Fields
    $fields = ['first_name', 'last_name', 'email', 'phone'];
    foreach ($fields as $field) {
        if (isset($input[$field])) {
            $updateFields[] = "$field = :$field";
            $params[":$field"] = $input[$field];
        }
    }

    // 2. Update Password if provided
    if (!empty($input['new_password'])) {
        if (empty($input['current_password'])) {
            http_response_code(400);
            echo json_encode(['error' => 'Current password is required to change password']);
            exit;
        }

        // Fetch current password from the correct table
        $pwStmt = $db->prepare("SELECT password_hash FROM $table WHERE id = :id");
        $pwStmt->execute([':id' => $user['id']]);
        $currentHash = $pwStmt->fetchColumn();

        if (!$currentHash || !password_verify($input['current_password'], $currentHash)) {
            http_response_code(401);
            echo json_encode(['error' => 'Incorrect current password']);
            exit;
        }

        if (strlen($input['new_password']) < 6) {
            http_response_code(400);
            echo json_encode(['error' => 'New password must be at least 6 characters']);
            exit;
        }

        $updateFields[] = "password_hash = :new_password_hash";
        $params[':new_password_hash'] = password_hash($input['new_password'], PASSWORD_DEFAULT);
    }

    if (empty($updateFields)) {
        echo json_encode(['success' => true, 'message' => 'No changes made']);
        exit;
    }

    $sql = "UPDATE $table SET " . implode(', ', $updateFields) . " WHERE id = :id";
    $stmt = $db->prepare($sql);
    $stmt->execute($params);

    echo json_encode(['success' => true, 'message' => 'Profile updated successfully']);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Update failed: ' . $e->getMessage()]);
}
?>
