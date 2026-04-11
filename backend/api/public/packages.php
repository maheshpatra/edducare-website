<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

require_once '../../config/database.php';

$database = new Database();
$db = $database->getConnection();

try {
    $query = "SELECT id, name, description, price, duration_months, max_students, max_teachers, features 
              FROM packages 
              WHERE is_active = 1 
              ORDER BY price ASC";
    
    $stmt = $db->prepare($query);
    $stmt->execute();
    $packages = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Decode features JSON for each package
    foreach ($packages as &$pkg) {
        if ($pkg['features']) {
            $pkg['features'] = json_decode($pkg['features'], true);
        } else {
            $pkg['features'] = [];
        }
    }
    
    echo json_encode([
        'success' => true,
        'data' => $packages
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Internal server error: ' . $e->getMessage()]);
}
?>
