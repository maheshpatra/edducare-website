<?php
header('Access-Control-Allow-Origin: *');
header('Content-Type: application/json');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Access-Control-Allow-Headers,Content-Type,Access-Control-Allow-Methods,Authorization,X-Requested-With');

require_once '../../includes/enhanced_auth.php';
require_once '../../includes/response.php';
require_once '../../includes/analytics.php';
require_once '../../includes/validator.php';

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

if (!$user || !in_array($user['role_name'], ['admin', 'super_admin'])) {
    Response::forbidden('Insufficient permissions for advanced reports');
}

$reportType = $_GET['report_type'] ?? null;
$allowedReports = ['caste_wise_performance', 'attendance_trends', 'fee_defaulters', 'academic_performance'];

if (!$reportType || !in_array($reportType, $allowedReports)) {
    Response::error('Invalid or missing report type. Allowed: ' . implode(', ', $allowedReports));
}

try {
    $analytics = new Analytics();
    $filters = [];
    
    // Extract filters from query parameters
    if (isset($_GET['academic_year_id'])) $filters['academic_year_id'] = $_GET['academic_year_id'];
    if (isset($_GET['class_id'])) $filters['class_id'] = $_GET['class_id'];
    if (isset($_GET['caste'])) $filters['caste'] = $_GET['caste'];
    if (isset($_GET['start_date'])) $filters['start_date'] = $_GET['start_date'];
    if (isset($_GET['end_date'])) $filters['end_date'] = $_GET['end_date'];
    
    if ($user['role_name'] === 'super_admin') {
        $schoolId = $_GET['school_id'] ?? null;
        if (!$schoolId) {
            Response::error('School ID required for super admin');
        }
    } else {
        $schoolId = $user['school_id'];
    }

    $reportData = $analytics->getAdvancedReports($schoolId, $reportType, $filters);

    if ($reportData === false) {
        Response::error('Failed to generate report');
    }

    Response::success([
        'report_type' => $reportType,
        'filters' => $filters,
        'data' => $reportData,
        'generated_at' => date('Y-m-d H:i:s')
    ], 'Advanced report generated successfully');

} catch (Exception $e) {
    Response::error('Failed to generate report: ' . $e->getMessage());
}
?>
