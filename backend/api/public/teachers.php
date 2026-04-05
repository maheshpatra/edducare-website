<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

require_once '../../config/database.php';

$database = new Database();
$db = $database->getConnection();

$school_id = isset($_GET['school_id']) ? (int)$_GET['school_id'] : null;

if (!$school_id) {
    http_response_code(400);
    echo json_encode(['error' => 'School ID is required']);
    exit;
}

try {
    $query = "SELECT u.id, u.first_name, u.last_name, u.email, u.profile_image, 
                     u.qualification, u.experience_years, u.teacher_type
              FROM users u
              JOIN user_roles r ON u.role_id = r.id
              WHERE u.school_id = :school_id 
              AND (r.name LIKE 'teacher%' OR r.name = 'teacher' OR r.name = 'principal')
              AND u.is_active = 1
              ORDER BY u.first_name ASC";
    $stmt = $db->prepare($query);
    $stmt->execute([':school_id' => $school_id]);
    $teachers = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['success' => true, 'data' => $teachers]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Server error: ' . $e->getMessage()]);
}
?>
