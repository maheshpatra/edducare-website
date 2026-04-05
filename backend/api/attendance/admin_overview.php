<?php
/**
 * Admin Attendance Overview API
 * Returns today's attendance summary per class + last 7 days daily trend
 * Used by the admin dashboard — single lightweight query, no heavy joins
 * 
 * GET /api/attendance/admin_overview.php
 * Auth: admin / school_admin / super_admin
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

require_once '../../middleware/auth.php';
require_once '../../config/database.php';
require_once '../../includes/response.php';

$auth = new AuthMiddleware();
$user = $auth->requireRole(['super_admin', 'school_admin', 'admin']);

if (!$user) {
    exit;
}

$database = new Database();
$db = $database->getConnection();
$schoolId = $user['school_id'];
$today = date('Y-m-d');

try {

    /* ─────────────────────────────────────────────────────────────────────
       1. TODAY — per-class attendance summary
          Returns: class_name, section_name, total, present, absent, late, pct
       ───────────────────────────────────────────────────────────────────── */
    $todayQuery = "
        SELECT
            c.id            AS class_id,
            c.name          AS class_name,
            c.grade_level,
            COALESCE(sec.name, '-') AS section_name,
            COUNT(a.id)                                           AS total_marked,
            COUNT(CASE WHEN a.status = 'present' THEN 1 END)     AS present_count,
            COUNT(CASE WHEN a.status = 'absent'  THEN 1 END)     AS absent_count,
            COUNT(CASE WHEN a.status = 'late'    THEN 1 END)     AS late_count,
            ROUND(
                COUNT(CASE WHEN a.status = 'present' THEN 1 END)
                * 100.0
                / NULLIF(COUNT(a.id), 0)
            , 1)                                                  AS attendance_pct
        FROM attendance a
        JOIN classes  c   ON a.class_id   = c.id
        LEFT JOIN sections sec ON a.section_id = sec.id
        WHERE a.date      = :today
          AND c.school_id = :school_id
        GROUP BY a.class_id, a.section_id, c.id, c.name, c.grade_level, sec.name
        ORDER BY c.grade_level ASC, sec.name ASC
    ";
    $todayStmt = $db->prepare($todayQuery);
    $todayStmt->bindValue(':today',     $today);
    $todayStmt->bindValue(':school_id', $schoolId);
    $todayStmt->execute();
    $todayClasses = $todayStmt->fetchAll(PDO::FETCH_ASSOC);

    /* ─────────────────────────────────────────────────────────────────────
       2. SCHOOL-WIDE TODAY SUMMARY (single row for the hero card)
       ───────────────────────────────────────────────────────────────────── */
    $todaySummaryQuery = "
        SELECT
            COUNT(a.id)                                           AS total_marked,
            COUNT(CASE WHEN a.status = 'present' THEN 1 END)     AS present_count,
            COUNT(CASE WHEN a.status = 'absent'  THEN 1 END)     AS absent_count,
            COUNT(CASE WHEN a.status = 'late'    THEN 1 END)     AS late_count,
            ROUND(
                COUNT(CASE WHEN a.status = 'present' THEN 1 END)
                * 100.0
                / NULLIF(COUNT(a.id), 0)
            , 1)                                                  AS attendance_pct,
            COUNT(DISTINCT a.class_id)                           AS classes_reported
        FROM attendance a
        JOIN classes c ON a.class_id = c.id
        WHERE a.date      = :today
          AND c.school_id = :school_id
    ";
    $todaySumStmt = $db->prepare($todaySummaryQuery);
    $todaySumStmt->bindValue(':today',     $today);
    $todaySumStmt->bindValue(':school_id', $schoolId);
    $todaySumStmt->execute();
    $todaySummary = $todaySumStmt->fetch(PDO::FETCH_ASSOC);

    /* ─────────────────────────────────────────────────────────────────────
       3. LAST 7 DAYS — daily school-wide attendance % (for bar chart)
       ───────────────────────────────────────────────────────────────────── */
    $weeklyQuery = "
        SELECT
            a.date,
            DAYNAME(a.date)                                            AS day_name,
            ROUND(
                COUNT(CASE WHEN a.status = 'present' THEN 1 END)
                * 100.0
                / NULLIF(COUNT(a.id), 0)
            , 1)                                                       AS attendance_pct,
            COUNT(a.id)                                                AS total_marked
        FROM attendance a
        JOIN classes c ON a.class_id = c.id
        WHERE a.date      BETWEEN DATE_SUB(:today, INTERVAL 6 DAY) AND :today2
          AND c.school_id = :school_id
        GROUP BY a.date
        ORDER BY a.date ASC
    ";
    $weeklyStmt = $db->prepare($weeklyQuery);
    $weeklyStmt->bindValue(':today',     $today);
    $weeklyStmt->bindValue(':today2',    $today);
    $weeklyStmt->bindValue(':school_id', $schoolId);
    $weeklyStmt->execute();
    $weeklyData = $weeklyStmt->fetchAll(PDO::FETCH_ASSOC);

    /* ─────────────────────────────────────────────────────────────────────
       4. TOTAL CLASS COUNT (for context — how many classes in school)
       ───────────────────────────────────────────────────────────────────── */
    $classCountStmt = $db->prepare("SELECT COUNT(id) AS total FROM classes WHERE school_id = :school_id");
    $classCountStmt->bindValue(':school_id', $schoolId);
    $classCountStmt->execute();
    $classCount = (int) ($classCountStmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0);

    /* ─────────────────────────────────────────────────────────────────────
       Respond
       ───────────────────────────────────────────────────────────────────── */
    Response::success([
        'date'           => $today,
        'today_summary'  => $todaySummary ?: [
            'total_marked'     => 0,
            'present_count'    => 0,
            'absent_count'     => 0,
            'late_count'       => 0,
            'attendance_pct'   => 0,
            'classes_reported' => 0,
        ],
        'today_classes'  => $todayClasses,
        'weekly_trend'   => $weeklyData,
        'total_classes'  => $classCount,
    ], 'Attendance overview fetched successfully');

} catch (Exception $e) {
    error_log('admin_overview.php error: ' . $e->getMessage());
    Response::error('Internal server error: ' . $e->getMessage(), 500);
}
