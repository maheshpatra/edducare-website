<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

require_once '../../middleware/auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$auth = new AuthMiddleware();
$user = $auth->requireRole(['super_admin', 'school_admin']);

if (!$user) {
    exit;
}

$database = new Database();
$db = $database->getConnection();

try {
    $schoolCondition = "";
    $params = [];
    
    if ($user['role'] !== 'super_admin') {
        $schoolCondition = "WHERE school_id = :school_id";
        $params[':school_id'] = $user['school_id'];
    } else if (isset($_GET['school_id'])) {
        $schoolCondition = "WHERE school_id = :school_id";
        $params[':school_id'] = $_GET['school_id'];
    }
    
    $analytics = [];
    
    // Student statistics
    $studentQuery = "SELECT 
        COUNT(*) as total_students,
        COUNT(CASE WHEN gender = 'male' THEN 1 END) as male_students,
        COUNT(CASE WHEN gender = 'female' THEN 1 END) as female_students,
        COUNT(CASE WHEN caste = 'ST' THEN 1 END) as st_students,
        COUNT(CASE WHEN caste = 'SC' THEN 1 END) as sc_students,
        COUNT(CASE WHEN caste = 'OBC' THEN 1 END) as obc_students,
        COUNT(CASE WHEN caste = 'GENERAL' THEN 1 END) as general_students
        FROM students $schoolCondition";
    
    $studentStmt = $db->prepare($studentQuery);
    foreach ($params as $key => $value) {
        $studentStmt->bindValue($key, $value);
    }
    $studentStmt->execute();
    $analytics['students'] = $studentStmt->fetch();
    
    // Teacher statistics
    $teacherQuery = "SELECT 
        COUNT(*) as total_teachers,
        COUNT(CASE WHEN role = 'class_teacher' THEN 1 END) as class_teachers,
        COUNT(CASE WHEN role = 'payment_teacher' THEN 1 END) as payment_teachers
        FROM users 
        WHERE role IN ('class_teacher', 'payment_teacher') AND is_active = TRUE";
    
    if (!empty($schoolCondition)) {
        $teacherQuery .= " AND " . str_replace('WHERE ', '', $schoolCondition);
    }
    
    $teacherStmt = $db->prepare($teacherQuery);
    foreach ($params as $key => $value) {
        $teacherStmt->bindValue($key, $value);
    }
    $teacherStmt->execute();
    $analytics['teachers'] = $teacherStmt->fetch();
    
    // Attendance statistics (last 30 days)
    $attendanceQuery = "SELECT 
        COUNT(*) as total_attendance_records,
        COUNT(CASE WHEN status = 'present' THEN 1 END) as present_count,
        COUNT(CASE WHEN status = 'absent' THEN 1 END) as absent_count,
        ROUND((COUNT(CASE WHEN status = 'present' THEN 1 END) / COUNT(*)) * 100, 2) as attendance_percentage
        FROM attendance a
        INNER JOIN students s ON a.student_id = s.id
        WHERE a.date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)";
    
    if (!empty($schoolCondition)) {
        $attendanceQuery .= " AND s." . str_replace('WHERE ', '', $schoolCondition);
    }
    
    $attendanceStmt = $db->prepare($attendanceQuery);
    foreach ($params as $key => $value) {
        $attendanceStmt->bindValue($key, $value);
    }
    $attendanceStmt->execute();
    $analytics['attendance'] = $attendanceStmt->fetch();
    
    // Class-wise student distribution
    $classQuery = "SELECT c.name as class_name, COUNT(s.id) as student_count
                   FROM classes c
                   LEFT JOIN students s ON c.id = s.class_id
                   $schoolCondition
                   GROUP BY c.id, c.name
                   ORDER BY c.grade_level";
    
    $classStmt = $db->prepare($classQuery);
    foreach ($params as $key => $value) {
        $classStmt->bindValue($key, $value);
    }
    $classStmt->execute();
    $analytics['class_distribution'] = $classStmt->fetchAll();
    
    // Fee collection statistics (current month)
    $feeQuery = "SELECT 
        COUNT(*) as total_payments,
        SUM(amount_paid) as total_collected,
        AVG(amount_paid) as average_payment
        FROM fee_payments fp
        INNER JOIN students s ON fp.student_id = s.id
        WHERE MONTH(fp.payment_date) = MONTH(CURDATE()) 
        AND YEAR(fp.payment_date) = YEAR(CURDATE())";
    
    if (!empty($schoolCondition)) {
        $feeQuery .= " AND s." . str_replace('WHERE ', '', $schoolCondition);
    }
    
    $feeStmt = $db->prepare($feeQuery);
    foreach ($params as $key => $value) {
        $feeStmt->bindValue($key, $value);
    }
    $feeStmt->execute();
    $analytics['fee_collection'] = $feeStmt->fetch();
    
    // Library statistics
    $libraryQuery = "SELECT 
        COUNT(DISTINCT lb.id) as total_books,
        SUM(lb.total_copies) as total_copies,
        SUM(lb.available_copies) as available_copies,
        COUNT(CASE WHEN lt.status = 'issued' THEN 1 END) as books_issued
        FROM library_books lb
        LEFT JOIN library_transactions lt ON lb.id = lt.book_id AND lt.status = 'issued'";
    
    if (!empty($schoolCondition)) {
        $libraryQuery .= " " . str_replace('WHERE', 'WHERE lb.', $schoolCondition);
    }
    
    $libraryStmt = $db->prepare($libraryQuery);
    foreach ($params as $key => $value) {
        $libraryStmt->bindValue($key, $value);
    }
    $libraryStmt->execute();
    $analytics['library'] = $libraryStmt->fetch();
    
    // Recent activities (last 10)
    $activityQuery = "SELECT al.action, al.table_name, al.created_at, u.first_name, u.last_name
                      FROM activity_logs al
                      LEFT JOIN users u ON al.user_id = u.id";
    
    if (!empty($schoolCondition)) {
        $activityQuery .= " " . str_replace('WHERE', 'WHERE al.', $schoolCondition);
    }
    
    $activityQuery .= " ORDER BY al.created_at DESC LIMIT 10";
    
    $activityStmt = $db->prepare($activityQuery);
    foreach ($params as $key => $value) {
        $activityStmt->bindValue($key, $value);
    }
    $activityStmt->execute();
    $analytics['recent_activities'] = $activityStmt->fetchAll();
    
    echo json_encode([
        'success' => true,
        'analytics' => $analytics
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Internal server error']);
}
?>
