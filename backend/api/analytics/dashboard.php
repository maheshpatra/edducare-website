<?php
header('Access-Control-Allow-Origin: *');
header('Content-Type: application/json');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Access-Control-Allow-Headers,Content-Type,Access-Control-Allow-Methods,Authorization,X-Requested-With');

require_once '../../includes/enhanced_auth.php';
require_once '../../includes/response.php';
require_once '../../includes/analytics.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    Response::error('Method not allowed', 405);
}

// Authenticate user
$headers = getallheaders();
$token = null;

if (isset($headers['Authorization'])) {
    $authHeader = $headers['Authorization'];
    if (preg_match('/Bearer\s+(.*)$/i', $authHeader, $matches)) {
        $token = $matches[1];
    }
}

if (!$token) {
    Response::unauthorized('Token required');
}

$auth = new EnhancedAuth();
$user = $auth->getCurrentUser($token);

if (!$user) {
    Response::unauthorized('Invalid token');
}

// Check permissions
if ($user['role_name'] === 'student') {
    Response::forbidden('Students cannot access analytics dashboard');
}

$startDate = $_GET['start_date'] ?? null;
$endDate = $_GET['end_date'] ?? null;

try {
    $analytics = new Analytics();
    
    if ($user['role_name'] === 'super_admin') {
        // Super admin can see all schools analytics
        $schoolId = $_GET['school_id'] ?? null;
        if (!$schoolId) {
            Response::error('School ID required for super admin');
        }
        $dashboardData = $analytics->getDashboardAnalytics($schoolId, $startDate, $endDate);
    } else {
        // School-specific analytics
        $dashboardData = $analytics->getDashboardAnalytics($user['school_id'], $startDate, $endDate);
    }

    if ($dashboardData === false) {
        Response::error('Failed to generate analytics');
    }

    Response::success($dashboardData, 'Dashboard analytics retrieved successfully');

} catch (Exception $e) {
    Response::error('Failed to retrieve analytics: ' . $e->getMessage());
}
?>
