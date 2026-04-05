<?php
header('Access-Control-Allow-Origin: *');
header('Content-Type: application/json');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Access-Control-Allow-Headers,Content-Type,Access-Control-Allow-Methods,Authorization,X-Requested-With');

require_once '../../includes/auth.php';
require_once '../../includes/response.php';
require_once '../../config/database.php';

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

$auth = new Auth();
$user = $auth->getCurrentUser($token);

if (!$user) {
    Response::unauthorized('Invalid token');
}

$classId = $_GET['class_id'] ?? null;
$sectionId = $_GET['section_id'] ?? null;
$startDate = $_GET['start_date'] ?? date('Y-m-01');
$endDate = $_GET['end_date'] ?? date('Y-m-t');

try {
    $db = new Database();
    $conn = $db->getConnection();

    $whereClause = "WHERE a.date BETWEEN :start_date AND :end_date";
    $params = [
        ':start_date' => $startDate,
        ':end_date' => $endDate
    ];

    // Add school filter for non-super admin users
    if ($user['role_name'] !== 'super_admin') {
        $whereClause .= " AND u.school_id = :school_id";
        $params[':school_id'] = $user['school_id'];
    }

    if ($classId) {
        $whereClause .= " AND a.class_id = :class_id";
        $params[':class_id'] = $classId;
    }

    if ($sectionId) {
        $whereClause .= " AND a.section_id = :section_id";
        $params[':section_id'] = $sectionId;
    }

    $query = "SELECT 
                s.first_name, s.last_name, s.id as student_id,
                c.name as class_name, sec.name as section_name,
                COUNT(CASE WHEN a.status = 'present' THEN 1 END) as present_days,
                COUNT(CASE WHEN a.status = 'absent' THEN 1 END) as absent_days,
                COUNT(CASE WHEN a.status = 'late' THEN 1 END) as late_days,
                COUNT(*) as total_days,
                ROUND((COUNT(CASE WHEN a.status = 'present' THEN 1 END) / COUNT(*)) * 100, 2) as attendance_percentage
              FROM attendance a
              JOIN students s ON a.student_id = s.id
              JOIN classes c ON a.class_id = c.id
              JOIN sections sec ON a.section_id = sec.id
              $whereClause
              GROUP BY a.student_id, a.class_id, a.section_id
              ORDER BY s.first_name, s.last_name";

    $stmt = $conn->prepare($query);
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->execute();

    $attendanceReport = $stmt->fetchAll();

    Response::success([
        'report' => $attendanceReport,
        'period' => [
            'start_date' => $startDate,
            'end_date' => $endDate
        ]
    ], 'Attendance report generated successfully');

} catch (Exception $e) {
    Response::error('Failed to generate report: ' . $e->getMessage());
}
?>
