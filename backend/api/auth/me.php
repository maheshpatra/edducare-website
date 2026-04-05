<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

require_once '../../middleware/auth.php';

$auth = new AuthMiddleware();
$user = $auth->requireAuth();

if (!$user) {
    exit;
}

// Ensure role consistency for the frontend
$user['role'] = $user['role_name'] ?? $user['role'] ?? 'school_admin';

echo json_encode(['success' => true, 'user' => $user]);
?>
