<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

require_once '../../middleware/auth.php';

$auth = new AuthMiddleware();
$user = $auth->requireRole(['super_admin']);

if (!$user) {
    exit;
}

$database = new Database();
$db = $database->getConnection();

try {
    // Total schools
    $schoolsQuery = "SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN is_active = 1 AND is_blocked = 0 THEN 1 ELSE 0 END) as active,
        SUM(CASE WHEN is_blocked = 1 THEN 1 ELSE 0 END) as blocked,
        SUM(CASE WHEN package_id IS NOT NULL THEN 1 ELSE 0 END) as purchased
    FROM schools";
    $schoolsStmt = $db->prepare($schoolsQuery);
    $schoolsStmt->execute();
    $schoolsStats = $schoolsStmt->fetch();

    // Total Revenue (Annualized based on current packages)
    $revenueQuery = "SELECT SUM(p.price) as total_revenue
                    FROM schools s
                    JOIN packages p ON s.package_id = p.id
                    WHERE s.is_active = 1 AND s.is_blocked = 0";
    $revenueStmt = $db->prepare($revenueQuery);
    $revenueStmt->execute();
    $revenueStats = $revenueStmt->fetch();

    // Total Students across all schools
    $studentsQuery = "SELECT COUNT(*) as total FROM students";
    $studentsStmt = $db->prepare($studentsQuery);
    $studentsStmt->execute();
    $studentsCount = $studentsStmt->fetch()['total'];

    // Recent schools
    $recentSchoolsQuery = "SELECT s.name, s.code, s.created_at, p.name as package_name
                          FROM schools s
                          LEFT JOIN packages p ON s.package_id = p.id
                          ORDER BY s.created_at DESC
                          LIMIT 5";
    $recentSchoolsStmt = $db->prepare($recentSchoolsQuery);
    $recentSchoolsStmt->execute();
    $recentSchools = $recentSchoolsStmt->fetchAll();

    echo json_encode([
        'success' => true,
        'data' => [
            'schools' => [
                'total' => (int)$schoolsStats['total'],
                'active' => (int)$schoolsStats['active'],
                'blocked' => (int)$schoolsStats['blocked'],
                'purchased' => (int)$schoolsStats['purchased']
            ],
            'revenue' => (float)($revenueStats['total_revenue'] ?? 0),
            'students' => (int)$studentsCount,
            'recent_schools' => $recentSchools
        ]
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Internal server error: ' . $e->getMessage()]);
}
?>
