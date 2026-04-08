<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

ini_set('display_errors', 0);
error_reporting(E_ALL);

require_once '../../middleware/auth.php';
require_once '../../config/database.php';
require_once '../../includes/response.php';

$auth = new AuthMiddleware();
$user = $auth->requireRole(['super_admin', 'admin']);

if (!$user) {
    exit;
}

$database = new Database();
$db = $database->getConnection();

$action = $_GET['action'] ?? null;

try {
    switch ($_SERVER['REQUEST_METHOD']) {
        case 'GET':
            switch ($action) {
                case 'dashboard':        handleDashboard($db, $user); break;
                case 'birthdays':        handleBirthdays($db, $user); break;
                case 'leaves_today':     handleLeavesToday($db, $user); break;
                case 'departments':      handleGetDepartments($db, $user); break;
                case 'designations':     handleGetDesignations($db, $user); break;
                case 'leave_types':      handleGetLeaveTypes($db, $user); break;
                case 'leave_requests':   handleGetLeaveRequests($db, $user); break;
                case 'payroll':          handleGetPayroll($db, $user); break;
                case 'staff':            handleGetStaff($db, $user); break;
                default:                 handleDashboard($db, $user);
            }
            break;
        case 'POST':
            switch ($action) {
                case 'department':       handleCreateDepartment($db, $user); break;
                case 'designation':      handleCreateDesignation($db, $user); break;
                case 'leave_type':       handleCreateLeaveType($db, $user); break;
                case 'leave_request':    handleCreateLeaveRequest($db, $user); break;
                case 'approve_leave':    handleApproveLeave($db, $user); break;
                case 'reject_leave':     handleRejectLeave($db, $user); break;
                case 'set_salary':       handleSetSalary($db, $user); break;
                case 'generate_payroll': handleGeneratePayroll($db, $user); break;
                case 'pay_salary':       handlePaySalary($db, $user); break;
                default: Response::error('Unknown action', 400);
            }
            break;
        case 'DELETE':
            switch ($action) {
                case 'department':       handleDeleteEntity($db, $user, 'departments'); break;
                case 'designation':      handleDeleteEntity($db, $user, 'designations'); break;
                case 'leave_type':       handleDeleteEntity($db, $user, 'leave_types'); break;
                default: Response::error('Unknown action', 400);
            }
            break;
        default:
            Response::error('Method not allowed', 405);
    }
} catch (Exception $e) {
    Response::error('Server error: ' . $e->getMessage(), 500);
}

// ─── Dashboard ────────────────────────────────────────────────────────────────
function handleDashboard($db, $user) {
    $schoolId = $user['role_name'] === 'super_admin' ? ($_GET['school_id'] ?? null) : $user['school_id'];
    if (!$schoolId) { Response::error('School ID required', 400); }

    $today = date('Y-m-d');

    // Staff count
    $staffQ = $db->prepare("SELECT COUNT(*) as total,
        COUNT(CASE WHEN is_active = 1 THEN 1 END) as active,
        COUNT(CASE WHEN gender = 'male' THEN 1 END) as male,
        COUNT(CASE WHEN gender = 'female' THEN 1 END) as female
        FROM users WHERE school_id = :sid AND role_id IN (3,4,5,6)");
    $staffQ->execute([':sid' => $schoolId]);
    $staffStats = $staffQ->fetch(PDO::FETCH_ASSOC);

    // Leaves today
    $leavesQ = $db->prepare("SELECT COUNT(*) as count FROM staff_leaves
        WHERE school_id = :sid AND status = 'approved'
        AND :today BETWEEN from_date AND to_date");
    $leavesQ->execute([':sid' => $schoolId, ':today' => $today]);
    $leavesToday = $leavesQ->fetch(PDO::FETCH_ASSOC)['count'];

    // Pending leave requests
    $pendingQ = $db->prepare("SELECT COUNT(*) as count FROM staff_leaves
        WHERE school_id = :sid AND status = 'pending'");
    $pendingQ->execute([':sid' => $schoolId]);
    $pendingLeaves = $pendingQ->fetch(PDO::FETCH_ASSOC)['count'];

    // Departments count
    $deptQ = $db->prepare("SELECT COUNT(*) as count FROM departments WHERE school_id = :sid AND is_active = 1");
    $deptQ->execute([':sid' => $schoolId]);
    $deptCount = $deptQ->fetch(PDO::FETCH_ASSOC)['count'];

    Response::success([
        'total_staff' => (int)$staffStats['total'],
        'active_staff' => (int)$staffStats['active'],
        'male_staff' => (int)$staffStats['male'],
        'female_staff' => (int)$staffStats['female'],
        'on_leave_today' => (int)$leavesToday,
        'pending_leaves' => (int)$pendingLeaves,
        'departments' => (int)$deptCount,
    ], 'HR Dashboard');
}

// ─── Upcoming Birthdays ───────────────────────────────────────────────────────
function handleBirthdays($db, $user) {
    $schoolId = $user['role_name'] === 'super_admin' ? ($_GET['school_id'] ?? null) : $user['school_id'];
    if (!$schoolId) { Response::error('School ID required', 400); }

    $type = $_GET['type'] ?? 'all'; // 'staff', 'student', 'all'
    $days = min(90, max(1, intval($_GET['days'] ?? 30)));
    $results = [];

    if ($type === 'staff' || $type === 'all') {
        $q = $db->prepare("SELECT u.id, u.first_name, u.last_name, u.date_of_birth, u.profile_image,
            'staff' as person_type,
            CONCAT(u.first_name, ' ', u.last_name) as full_name,
            DATE_FORMAT(u.date_of_birth, '%b %d') as birthday_display,
            DATEDIFF(
                DATE_ADD(u.date_of_birth, INTERVAL (YEAR(CURDATE()) - YEAR(u.date_of_birth) +
                    IF(DATE_FORMAT(CURDATE(), '%m%d') > DATE_FORMAT(u.date_of_birth, '%m%d'), 1, 0)) YEAR),
                CURDATE()
            ) as days_until
            FROM users u
            WHERE u.school_id = :sid AND u.role_id IN (3,4,5,6) AND u.is_active = 1
            AND u.date_of_birth IS NOT NULL
            HAVING days_until >= 0 AND days_until <= :days
            ORDER BY days_until ASC
            LIMIT 20");
        $q->execute([':sid' => $schoolId, ':days' => $days]);
        $results = array_merge($results, $q->fetchAll(PDO::FETCH_ASSOC));
    }

    if ($type === 'student' || $type === 'all') {
        $q = $db->prepare("SELECT s.id, s.first_name, s.last_name, s.date_of_birth, s.profile_image,
            'student' as person_type,
            CONCAT(s.first_name, ' ', s.last_name) as full_name,
            c.name as class_name, sec.name as section_name,
            DATE_FORMAT(s.date_of_birth, '%b %d') as birthday_display,
            DATEDIFF(
                DATE_ADD(s.date_of_birth, INTERVAL (YEAR(CURDATE()) - YEAR(s.date_of_birth) +
                    IF(DATE_FORMAT(CURDATE(), '%m%d') > DATE_FORMAT(s.date_of_birth, '%m%d'), 1, 0)) YEAR),
                CURDATE()
            ) as days_until
            FROM students s
            LEFT JOIN student_enrollments se ON s.id = se.student_id AND se.status = 'active'
            LEFT JOIN classes c ON se.class_id = c.id
            LEFT JOIN sections sec ON se.section_id = sec.id
            WHERE s.school_id = :sid AND s.is_active = 1
            AND s.date_of_birth IS NOT NULL
            HAVING days_until >= 0 AND days_until <= :days
            ORDER BY days_until ASC
            LIMIT 30");
        $q->execute([':sid' => $schoolId, ':days' => $days]);
        $results = array_merge($results, $q->fetchAll(PDO::FETCH_ASSOC));
    }

    // Sort combined results by days_until
    usort($results, fn($a, $b) => $a['days_until'] - $b['days_until']);

    Response::success(['birthdays' => $results], 'Upcoming birthdays');
}

// ─── Staff on Leave Today ─────────────────────────────────────────────────────
function handleLeavesToday($db, $user) {
    $schoolId = $user['role_name'] === 'super_admin' ? ($_GET['school_id'] ?? null) : $user['school_id'];
    if (!$schoolId) { Response::error('School ID required', 400); }

    $today = date('Y-m-d');
    $q = $db->prepare("SELECT sl.*, 
        CONCAT(u.first_name, ' ', u.last_name) as staff_name,
        u.employee_id, u.profile_image,
        lt.name as leave_type_name
        FROM staff_leaves sl
        JOIN users u ON sl.user_id = u.id
        JOIN leave_types lt ON sl.leave_type_id = lt.id
        WHERE sl.school_id = :sid AND sl.status = 'approved'
        AND :today BETWEEN sl.from_date AND sl.to_date
        ORDER BY u.first_name");
    $q->execute([':sid' => $schoolId, ':today' => $today]);

    Response::success(['staff_on_leave' => $q->fetchAll(PDO::FETCH_ASSOC)], 'Staff on leave today');
}

// ─── Departments ──────────────────────────────────────────────────────────────
function handleGetDepartments($db, $user) {
    $schoolId = $user['role_name'] === 'super_admin' ? ($_GET['school_id'] ?? null) : $user['school_id'];
    if (!$schoolId) { Response::error('School ID required', 400); }

    $q = $db->prepare("SELECT d.*,
        CONCAT(u.first_name, ' ', u.last_name) as head_name,
        (SELECT COUNT(*) FROM users u2 WHERE u2.school_id = d.school_id AND u2.role_id IN (3,4,5,6) AND u2.is_active = 1) as staff_count
        FROM departments d
        LEFT JOIN users u ON d.head_id = u.id
        WHERE d.school_id = :sid
        ORDER BY d.name");
    $q->execute([':sid' => $schoolId]);

    Response::success(['departments' => $q->fetchAll(PDO::FETCH_ASSOC)]);
}

function handleCreateDepartment($db, $user) {
    $input = json_decode(file_get_contents('php://input'), true);
    $schoolId = $user['role_name'] === 'super_admin' ? ($input['school_id'] ?? null) : $user['school_id'];
    if (!$schoolId || empty($input['name'])) { Response::error('School ID and name required', 400); }

    $q = $db->prepare("INSERT INTO departments (school_id, name, description, head_id) VALUES (:sid, :name, :desc, :head)");
    $q->execute([':sid' => $schoolId, ':name' => $input['name'], ':desc' => $input['description'] ?? null, ':head' => $input['head_id'] ?? null]);

    Response::success(['id' => $db->lastInsertId()], 'Department created');
}

// ─── Designations ─────────────────────────────────────────────────────────────
function handleGetDesignations($db, $user) {
    $schoolId = $user['role_name'] === 'super_admin' ? ($_GET['school_id'] ?? null) : $user['school_id'];
    if (!$schoolId) { Response::error('School ID required', 400); }

    $q = $db->prepare("SELECT * FROM designations WHERE school_id = :sid ORDER BY name");
    $q->execute([':sid' => $schoolId]);

    Response::success(['designations' => $q->fetchAll(PDO::FETCH_ASSOC)]);
}

function handleCreateDesignation($db, $user) {
    $input = json_decode(file_get_contents('php://input'), true);
    $schoolId = $user['role_name'] === 'super_admin' ? ($input['school_id'] ?? null) : $user['school_id'];
    if (!$schoolId || empty($input['name'])) { Response::error('School ID and name required', 400); }

    $q = $db->prepare("INSERT INTO designations (school_id, name, description) VALUES (:sid, :name, :desc)");
    $q->execute([':sid' => $schoolId, ':name' => $input['name'], ':desc' => $input['description'] ?? null]);

    Response::success(['id' => $db->lastInsertId()], 'Designation created');
}

// ─── Leave Types ──────────────────────────────────────────────────────────────
function handleGetLeaveTypes($db, $user) {
    $schoolId = $user['role_name'] === 'super_admin' ? ($_GET['school_id'] ?? null) : $user['school_id'];
    if (!$schoolId) { Response::error('School ID required', 400); }

    $q = $db->prepare("SELECT * FROM leave_types WHERE school_id = :sid ORDER BY name");
    $q->execute([':sid' => $schoolId]);

    Response::success(['leave_types' => $q->fetchAll(PDO::FETCH_ASSOC)]);
}

function handleCreateLeaveType($db, $user) {
    $input = json_decode(file_get_contents('php://input'), true);
    $schoolId = $user['role_name'] === 'super_admin' ? ($input['school_id'] ?? null) : $user['school_id'];
    if (!$schoolId || empty($input['name'])) { Response::error('School ID and name required', 400); }

    $q = $db->prepare("INSERT INTO leave_types (school_id, name, days_allowed, is_paid) VALUES (:sid, :name, :days, :paid)");
    $q->execute([':sid' => $schoolId, ':name' => $input['name'], ':days' => $input['days_allowed'] ?? 10, ':paid' => $input['is_paid'] ?? 1]);

    Response::success(['id' => $db->lastInsertId()], 'Leave type created');
}

// ─── Leave Requests ───────────────────────────────────────────────────────────
function handleGetLeaveRequests($db, $user) {
    $schoolId = $user['role_name'] === 'super_admin' ? ($_GET['school_id'] ?? null) : $user['school_id'];
    if (!$schoolId) { Response::error('School ID required', 400); }

    $status = $_GET['status'] ?? null;
    $where = "WHERE sl.school_id = :sid";
    $params = [':sid' => $schoolId];
    if ($status) { $where .= " AND sl.status = :status"; $params[':status'] = $status; }

    $q = $db->prepare("SELECT sl.*,
        CONCAT(u.first_name, ' ', u.last_name) as staff_name,
        u.employee_id, u.profile_image,
        lt.name as leave_type_name, lt.is_paid,
        CONCAT(a.first_name, ' ', a.last_name) as approved_by_name
        FROM staff_leaves sl
        JOIN users u ON sl.user_id = u.id
        JOIN leave_types lt ON sl.leave_type_id = lt.id
        LEFT JOIN users a ON sl.approved_by = a.id
        $where
        ORDER BY sl.created_at DESC");
    $q->execute($params);

    Response::success(['leave_requests' => $q->fetchAll(PDO::FETCH_ASSOC)]);
}

function handleCreateLeaveRequest($db, $user) {
    $input = json_decode(file_get_contents('php://input'), true);
    $schoolId = $user['role_name'] === 'super_admin' ? ($input['school_id'] ?? null) : $user['school_id'];

    $required = ['user_id', 'leave_type_id', 'from_date', 'to_date'];
    foreach ($required as $f) {
        if (empty($input[$f])) { Response::error("$f is required", 400); }
    }

    $days = max(1, (strtotime($input['to_date']) - strtotime($input['from_date'])) / 86400 + 1);

    $q = $db->prepare("INSERT INTO staff_leaves (school_id, user_id, leave_type_id, from_date, to_date, days, reason)
        VALUES (:sid, :uid, :ltid, :from, :to, :days, :reason)");
    $q->execute([
        ':sid' => $schoolId, ':uid' => $input['user_id'], ':ltid' => $input['leave_type_id'],
        ':from' => $input['from_date'], ':to' => $input['to_date'],
        ':days' => $days, ':reason' => $input['reason'] ?? null
    ]);

    Response::success(['id' => $db->lastInsertId()], 'Leave request created');
}

function handleApproveLeave($db, $user) {
    $input = json_decode(file_get_contents('php://input'), true);
    if (empty($input['id'])) { Response::error('Leave ID required', 400); }

    $q = $db->prepare("UPDATE staff_leaves SET status = 'approved', approved_by = :aid, remarks = :rem WHERE id = :id");
    $q->execute([':aid' => $user['id'], ':rem' => $input['remarks'] ?? null, ':id' => $input['id']]);

    Response::success(null, 'Leave approved');
}

function handleRejectLeave($db, $user) {
    $input = json_decode(file_get_contents('php://input'), true);
    if (empty($input['id'])) { Response::error('Leave ID required', 400); }

    $q = $db->prepare("UPDATE staff_leaves SET status = 'rejected', approved_by = :aid, remarks = :rem WHERE id = :id");
    $q->execute([':aid' => $user['id'], ':rem' => $input['remarks'] ?? null, ':id' => $input['id']]);

    Response::success(null, 'Leave rejected');
}

// ─── Payroll ──────────────────────────────────────────────────────────────────
function handleSetSalary($db, $user) {
    $input = json_decode(file_get_contents('php://input'), true);
    if (empty($input['user_id']) || !isset($input['salary'])) { Response::error('User ID and salary required', 400); }

    $q = $db->prepare("UPDATE users SET salary = :salary WHERE id = :uid");
    $q->execute([':salary' => $input['salary'], ':uid' => $input['user_id']]);

    Response::success(null, 'Salary updated');
}

function handleGeneratePayroll($db, $user) {
    $input = json_decode(file_get_contents('php://input'), true);
    $schoolId = $user['role_name'] === 'super_admin' ? ($input['school_id'] ?? null) : $user['school_id'];
    $month = intval($input['month'] ?? date('n'));
    $year = intval($input['year'] ?? date('Y'));

    if (!$schoolId) { Response::error('School ID required', 400); }

    // Get all staff with salary
    $staffQ = $db->prepare("SELECT id, salary FROM users WHERE school_id = :sid AND role_id IN (3,4,5,6) AND is_active = 1 AND salary > 0");
    $staffQ->execute([':sid' => $schoolId]);
    $staffList = $staffQ->fetchAll(PDO::FETCH_ASSOC);

    $db->beginTransaction();
    try {
        $insertQ = $db->prepare("INSERT INTO payroll (school_id, user_id, month, year, basic_salary, allowances, deductions, net_salary)
            VALUES (:sid, :uid, :month, :year, :basic, :allow, :deduct, :net)
            ON DUPLICATE KEY UPDATE basic_salary = :basic2, net_salary = :net2");

        $count = 0;
        foreach ($staffList as $staff) {
            $basic = $staff['salary'];
            $allowances = 0;
            $deductions = 0;

            // Calculate leave deductions for the month
            $leaveQ = $db->prepare("SELECT SUM(days) as leave_days FROM staff_leaves
                WHERE user_id = :uid AND status = 'approved'
                AND MONTH(from_date) = :month AND YEAR(from_date) = :year
                AND leave_type_id IN (SELECT id FROM leave_types WHERE is_paid = 0)");
            $leaveQ->execute([':uid' => $staff['id'], ':month' => $month, ':year' => $year]);
            $unpaidLeaves = (int)($leaveQ->fetch(PDO::FETCH_ASSOC)['leave_days'] ?? 0);

            $dailyRate = $basic / 30;
            $deductions = round($dailyRate * $unpaidLeaves, 2);
            $net = round($basic + $allowances - $deductions, 2);

            $insertQ->execute([
                ':sid' => $schoolId, ':uid' => $staff['id'], ':month' => $month, ':year' => $year,
                ':basic' => $basic, ':allow' => $allowances, ':deduct' => $deductions, ':net' => $net,
                ':basic2' => $basic, ':net2' => $net
            ]);
            $count++;
        }

        $db->commit();
        Response::success(['generated' => $count], "Payroll generated for $count staff");
    } catch (Exception $e) {
        $db->rollBack();
        throw $e;
    }
}

function handleGetPayroll($db, $user) {
    $schoolId = $user['role_name'] === 'super_admin' ? ($_GET['school_id'] ?? null) : $user['school_id'];
    $month = intval($_GET['month'] ?? date('n'));
    $year = intval($_GET['year'] ?? date('Y'));

    if (!$schoolId) { Response::error('School ID required', 400); }

    $q = $db->prepare("SELECT p.*,
        CONCAT(u.first_name, ' ', u.last_name) as staff_name,
        u.employee_id, u.profile_image, u.salary as base_salary
        FROM payroll p
        JOIN users u ON p.user_id = u.id
        WHERE p.school_id = :sid AND p.month = :month AND p.year = :year
        ORDER BY u.first_name");
    $q->execute([':sid' => $schoolId, ':month' => $month, ':year' => $year]);

    // Summary stats
    $sumQ = $db->prepare("SELECT
        COUNT(*) as total_staff,
        SUM(net_salary) as total_payable,
        SUM(CASE WHEN status = 'paid' THEN net_salary ELSE 0 END) as total_paid,
        SUM(CASE WHEN status = 'unpaid' THEN net_salary ELSE 0 END) as total_pending,
        COUNT(CASE WHEN status = 'paid' THEN 1 END) as paid_count,
        COUNT(CASE WHEN status = 'unpaid' THEN 1 END) as unpaid_count
        FROM payroll WHERE school_id = :sid AND month = :month AND year = :year");
    $sumQ->execute([':sid' => $schoolId, ':month' => $month, ':year' => $year]);

    Response::success([
        'payroll' => $q->fetchAll(PDO::FETCH_ASSOC),
        'summary' => $sumQ->fetch(PDO::FETCH_ASSOC),
        'month' => $month,
        'year' => $year
    ]);
}

function handlePaySalary($db, $user) {
    $input = json_decode(file_get_contents('php://input'), true);
    if (empty($input['payroll_id'])) { Response::error('Payroll ID required', 400); }

    $q = $db->prepare("UPDATE payroll SET status = 'paid', paid_date = CURDATE(), payment_method = :method, transaction_id = :txn WHERE id = :id");
    $q->execute([':method' => $input['payment_method'] ?? 'bank_transfer', ':txn' => $input['transaction_id'] ?? null, ':id' => $input['payroll_id']]);

    Response::success(null, 'Salary marked as paid');
}

// ─── Staff List ───────────────────────────────────────────────────────────────
function handleGetStaff($db, $user) {
    $schoolId = $user['role_name'] === 'super_admin' ? ($_GET['school_id'] ?? null) : $user['school_id'];
    if (!$schoolId) { Response::error('School ID required', 400); }

    $q = $db->prepare("SELECT u.id, u.first_name, u.last_name, u.email, u.phone, u.employee_id,
        u.gender, u.date_of_birth, u.qualification, u.experience_years, u.joining_date, u.salary,
        u.is_active, u.profile_image, u.teacher_type, u.address,
        r.name as role_name,
        CONCAT(u.first_name, ' ', u.last_name) as full_name
        FROM users u
        JOIN user_roles r ON u.role_id = r.id
        WHERE u.school_id = :sid AND u.role_id IN (3,4,5,6)
        ORDER BY u.first_name");
    $q->execute([':sid' => $schoolId]);

    Response::success(['staff' => $q->fetchAll(PDO::FETCH_ASSOC)]);
}

// ─── Generic Delete ───────────────────────────────────────────────────────────
function handleDeleteEntity($db, $user, $table) {
    $id = $_GET['id'] ?? null;
    if (!$id) { Response::error('ID required', 400); }

    $schoolId = $user['role_name'] === 'super_admin' ? ($_GET['school_id'] ?? $user['school_id']) : $user['school_id'];

    $q = $db->prepare("DELETE FROM $table WHERE id = :id AND school_id = :sid");
    $q->execute([':id' => $id, ':sid' => $schoolId]);

    Response::success(null, 'Deleted successfully');
}
