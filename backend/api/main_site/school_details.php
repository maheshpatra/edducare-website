<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

require_once '../../config/database.php';

$database = new Database();
$db = $database->getConnection();

$school_code = isset($_GET['code']) ? $_GET['code'] : null;

if (!$school_code) {
    http_response_code(400);
    echo json_encode(['error' => 'School code is required']);
    exit;
}

try {
    $query = "SELECT id, name, code, address, phone, email, website, logo, established_year, board_affiliation, school_type 
              FROM schools 
              WHERE code = :code AND is_active = 1 LIMIT 1";
    $stmt = $db->prepare($query);
    $stmt->execute([':code' => $school_code]);
    $school = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$school) {
        http_response_code(404);
        echo json_encode(['error' => 'School not found or inactive']);
        exit;
    }

    echo json_encode(['success' => true, 'data' => $school]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Server error: ' . $e->getMessage()]);
}
?>
