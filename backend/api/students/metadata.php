<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

require_once '../../middleware/auth.php';
require_once '../../config/database.php';
require_once '../../includes/response.php';

$auth = new AuthMiddleware();
$user = $auth->requireRole(['school_admin', 'admin']);

if (!$user) {
    exit;
}

$database = new Database();
$db = $database->getConnection();

try {
    $school_id = $user['school_id'];

    // Get all classes
    $classQuery = "SELECT id, name, grade_level FROM classes WHERE school_id = :school_id ORDER BY grade_level ASC, name ASC";
    $classStmt = $db->prepare($classQuery);
    $classStmt->bindValue(':school_id', $school_id);
    $classStmt->execute();
    $classes = $classStmt->fetchAll(PDO::FETCH_ASSOC);

    // Get all sections 
    $sectionQuery = "SELECT s.id, s.name, s.class_id, c.name as class_name 
                      FROM sections s 
                      JOIN classes c ON s.class_id = c.id 
                      WHERE c.school_id = :school_id 
                      ORDER BY c.grade_level ASC, s.name ASC";
    $sectionStmt = $db->prepare($sectionQuery);
    $sectionStmt->bindValue(':school_id', $school_id);
    $sectionStmt->execute();
    $sections = $sectionStmt->fetchAll(PDO::FETCH_ASSOC);

    Response::success([
        'classes' => $classes,
        'sections' => $sections
    ], 'Metadata retrieved successfully');

} catch (Exception $e) {
    Response::error('Server error: ' . $e->getMessage(), 500);
}
?>
