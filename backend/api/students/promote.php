<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once '../../middleware/auth.php';
require_once '../../config/database.php';

$auth = new AuthMiddleware();
$user = $auth->requireRole(['super_admin', 'school_admin']);

if (!$user) {
    exit;
}

$database = new Database();
$db = $database->getConnection();

try {
    switch ($_SERVER['REQUEST_METHOD']) {
        case 'GET':
            $action = $_GET['action'] ?? 'students';
            if ($action === 'sessions') {
                handleGetSessions($db, $user);
            } elseif ($action === 'classes') {
                handleGetClassesBySession($db, $user);
            } elseif ($action === 'sections') {
                handleGetSectionsByClass($db, $user);
            } else {
                handleGetStudents($db, $user);
            }
            break;
        case 'POST':
            handlePromoteStudents($db, $user);
            break;
        default:
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'error' => 'Internal server error',
        'details' => $e->getMessage()
    ]);
}

/**
 * GET ?action=sessions
 * Returns all academic sessions for the school, ordered newest first.
 * The current session is flagged with is_current = 1.
 */
function handleGetSessions($db, $user) {
    $schoolId = $user['role'] === 'super_admin'
        ? ($_GET['school_id'] ?? null)
        : $user['school_id'];

    if (!$schoolId) {
        http_response_code(400);
        echo json_encode(['error' => 'school_id is required']);
        return;
    }

    $query = "SELECT id, name, start_date, end_date, is_current
              FROM academic_years
              WHERE school_id = :school_id
              ORDER BY start_date DESC";

    $stmt = $db->prepare($query);
    $stmt->bindValue(':school_id', $schoolId);
    $stmt->execute();
    $sessions = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['success' => true, 'data' => $sessions]);
}

/**
 * GET ?action=classes
 * Returns ALL classes for the school (classes are one-time creations, not session-specific).
 * Optionally pass academic_year_id to show enrollment counts for that session.
 */
function handleGetClassesBySession($db, $user) {
    $academicYearId = $_GET['academic_year_id'] ?? null;

    $schoolId = $user['role'] === 'super_admin'
        ? ($_GET['school_id'] ?? null)
        : $user['school_id'];

    if (!$schoolId) {
        http_response_code(400);
        echo json_encode(['error' => 'school_id is required']);
        return;
    }

    // Classes are school-wide; optionally count students enrolled in a specific session
    $ayJoin = "";
    $params = [':school_id' => $schoolId];

    if ($academicYearId) {
        $ayJoin = "AND se.academic_year_id = :ay_id";
        $params[':ay_id'] = $academicYearId;
    }

    $query = "SELECT c.id, c.name, c.grade_level,
                     COUNT(DISTINCT se.student_id) as student_count
              FROM classes c
              LEFT JOIN student_enrollments se ON c.id = se.class_id AND se.status = 'active' $ayJoin
              WHERE c.school_id = :school_id
              GROUP BY c.id
              ORDER BY c.grade_level, c.name";

    $stmt = $db->prepare($query);
    foreach ($params as $k => $v) {
        $stmt->bindValue($k, $v);
    }
    $stmt->execute();
    $classes = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['success' => true, 'data' => $classes]);
}

/**
 * GET ?action=sections&class_id=X
 * Returns sections for a given class.
 */
function handleGetSectionsByClass($db, $user) {
    $classId = $_GET['class_id'] ?? null;

    if (!$classId) {
        http_response_code(400);
        echo json_encode(['error' => 'class_id is required']);
        return;
    }

    $query = "SELECT s.id, s.name, s.capacity,
                     COUNT(DISTINCT se.student_id) as student_count
              FROM sections s
              LEFT JOIN student_enrollments se ON s.id = se.section_id AND se.status = 'active'
              WHERE s.class_id = :class_id
              GROUP BY s.id
              ORDER BY s.name";

    $stmt = $db->prepare($query);
    $stmt->bindValue(':class_id', $classId);
    $stmt->execute();
    $sections = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['success' => true, 'data' => $sections]);
}

/**
 * GET ?action=students&class_id=X&section_id=Y&academic_year_id=Z
 * Returns students enrolled in a specific class/section for a given session.
 */
function handleGetStudents($db, $user) {
    $classId = $_GET['class_id'] ?? null;
    $sectionId = $_GET['section_id'] ?? null;
    $academicYearId = $_GET['academic_year_id'] ?? null;

    if (!$classId || !$sectionId) {
        http_response_code(400);
        echo json_encode(['error' => 'class_id and section_id are required']);
        return;
    }

    $schoolId = $user['role'] === 'super_admin'
        ? ($_GET['school_id'] ?? null)
        : $user['school_id'];

    // Build query with optional academic_year_id filter
    $ayCondition = "";
    $params = [
        ':class_id' => $classId,
        ':section_id' => $sectionId,
        ':school_id' => $schoolId,
    ];

    if ($academicYearId) {
        $ayCondition = "AND se.academic_year_id = :ay_id";
        $params[':ay_id'] = $academicYearId;
    }

    $query = "SELECT s.id, s.first_name, s.last_name, s.admission_number,
                     s.student_id AS student_code, s.father_name, s.status AS student_status,
                     se.id AS enrollment_id, se.roll_number, se.status AS enrollment_status,
                     se.academic_year_id,
                     c.name AS class_name, sec.name AS section_name,
                     ay.name AS session_name
              FROM student_enrollments se
              JOIN students s ON se.student_id = s.id
              JOIN classes c ON se.class_id = c.id
              JOIN sections sec ON se.section_id = sec.id
              LEFT JOIN academic_years ay ON se.academic_year_id = ay.id
              WHERE se.class_id = :class_id
                AND se.section_id = :section_id
                AND s.school_id = :school_id
                AND se.status = 'active'
                AND s.status = 'active'
                $ayCondition
              ORDER BY CAST(se.roll_number AS UNSIGNED) ASC, s.first_name ASC";

    $stmt = $db->prepare($query);
    foreach ($params as $k => $v) {
        $stmt->bindValue($k, $v);
    }
    $stmt->execute();
    $students = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'data' => $students,
        'total' => count($students)
    ]);
}

/**
 * POST – Promote selected students to a new session/class/section
 *
 * Body: {
 *   student_ids: [1, 2, 3, ...],
 *   from_academic_year_id: 5,
 *   from_class_id: 10,
 *   from_section_id: 20,
 *   to_academic_year_id: 6,
 *   to_class_id: 11,
 *   to_section_id: 21
 * }
 */
function handlePromoteStudents($db, $user) {
    $input = json_decode(file_get_contents('php://input'), true);

    $studentIds          = $input['student_ids'] ?? [];
    $fromAcademicYearId  = $input['from_academic_year_id'] ?? null;
    $fromClassId         = $input['from_class_id'] ?? null;
    $fromSectionId       = $input['from_section_id'] ?? null;
    $toAcademicYearId    = $input['to_academic_year_id'] ?? null;
    $toClassId           = $input['to_class_id'] ?? null;
    $toSectionId         = $input['to_section_id'] ?? null;

    // Validate required fields
    if (empty($studentIds) || !$toAcademicYearId || !$toClassId || !$toSectionId) {
        http_response_code(400);
        echo json_encode([
            'error' => 'student_ids, to_academic_year_id, to_class_id, and to_section_id are required'
        ]);
        return;
    }

    if (!$fromAcademicYearId || !$fromClassId || !$fromSectionId) {
        http_response_code(400);
        echo json_encode([
            'error' => 'from_academic_year_id, from_class_id, and from_section_id are required'
        ]);
        return;
    }

    // Prevent promoting to the same session + class + section
    if ($fromAcademicYearId == $toAcademicYearId &&
        $fromClassId == $toClassId &&
        $fromSectionId == $toSectionId) {
        http_response_code(400);
        echo json_encode([
            'error' => 'Cannot promote students to the same session, class, and section'
        ]);
        return;
    }

    $schoolId = $user['role'] === 'super_admin'
        ? ($input['school_id'] ?? null)
        : $user['school_id'];

    // Verify the target class belongs to the school (classes are NOT session-specific)
    $verifyClassQuery = "SELECT id FROM classes WHERE id = :class_id AND school_id = :school_id";
    $verifyStmt = $db->prepare($verifyClassQuery);
    $verifyStmt->bindValue(':class_id', $toClassId);
    $verifyStmt->bindValue(':school_id', $schoolId);
    $verifyStmt->execute();

    if (!$verifyStmt->fetch()) {
        http_response_code(400);
        echo json_encode([
            'error' => 'Target class does not belong to the selected session or school'
        ]);
        return;
    }

    // Verify section belongs to target class
    $verifySectionQuery = "SELECT id FROM sections WHERE id = :section_id AND class_id = :class_id";
    $verifySectionStmt = $db->prepare($verifySectionQuery);
    $verifySectionStmt->bindValue(':section_id', $toSectionId);
    $verifySectionStmt->bindValue(':class_id', $toClassId);
    $verifySectionStmt->execute();

    if (!$verifySectionStmt->fetch()) {
        http_response_code(400);
        echo json_encode([
            'error' => 'Target section does not belong to the selected class'
        ]);
        return;
    }

    $db->beginTransaction();

    try {
        $promoted = 0;
        $skipped = 0;
        $errors = [];

        foreach ($studentIds as $studentId) {
            // 1. Check if student already has an active enrollment in the target session
            $checkQuery = "SELECT id FROM student_enrollments
                           WHERE student_id = :student_id
                             AND academic_year_id = :ay_id
                             AND status = 'active'";
            $checkStmt = $db->prepare($checkQuery);
            $checkStmt->bindValue(':student_id', $studentId);
            $checkStmt->bindValue(':ay_id', $toAcademicYearId);
            $checkStmt->execute();
            $existingEnrollment = $checkStmt->fetch(PDO::FETCH_ASSOC);

            if ($existingEnrollment) {
                // Student already enrolled in target session — update their enrollment
                $updateQuery = "UPDATE student_enrollments
                                SET class_id = :class_id,
                                    section_id = :section_id,
                                    enrollment_date = CURDATE()
                                WHERE id = :id";
                $updateStmt = $db->prepare($updateQuery);
                $updateStmt->bindValue(':class_id', $toClassId);
                $updateStmt->bindValue(':section_id', $toSectionId);
                $updateStmt->bindValue(':id', $existingEnrollment['id']);
                $updateStmt->execute();
            } else {
                // 2. Create new enrollment in target session/class/section
                $insertQuery = "INSERT INTO student_enrollments
                                (student_id, class_id, section_id, academic_year_id, enrollment_date, status)
                                VALUES (:student_id, :class_id, :section_id, :ay_id, CURDATE(), 'active')";
                $insertStmt = $db->prepare($insertQuery);
                $insertStmt->bindValue(':student_id', $studentId);
                $insertStmt->bindValue(':class_id', $toClassId);
                $insertStmt->bindValue(':section_id', $toSectionId);
                $insertStmt->bindValue(':ay_id', $toAcademicYearId);

                try {
                    $insertStmt->execute();
                } catch (PDOException $e) {
                    // Unique constraint violation — student already has enrollment for this year
                    if ($e->getCode() == 23000) {
                        $skipped++;
                        $errors[] = "Student ID $studentId already has an enrollment for the target session";
                        continue;
                    }
                    throw $e;
                }
            }

            // 3. Mark old enrollment as 'inactive' (only the specific from-session enrollment)
            $deactivateQuery = "UPDATE student_enrollments
                                SET status = 'inactive'
                                WHERE student_id = :student_id
                                  AND class_id = :class_id
                                  AND section_id = :section_id
                                  AND academic_year_id = :ay_id
                                  AND status = 'active'";
            $deactivateStmt = $db->prepare($deactivateQuery);
            $deactivateStmt->bindValue(':student_id', $studentId);
            $deactivateStmt->bindValue(':class_id', $fromClassId);
            $deactivateStmt->bindValue(':section_id', $fromSectionId);
            $deactivateStmt->bindValue(':ay_id', $fromAcademicYearId);
            $deactivateStmt->execute();

            $promoted++;
        }

        // Log the promotion activity
        try {
            $logQuery = "INSERT INTO activity_logs
                         (user_id, user_type, school_id, action, entity_type, new_values, ip_address)
                         VALUES (:user_id, 'user', :school_id, 'promote_students', 'student_enrollment', :details, :ip)";
            $logStmt = $db->prepare($logQuery);
            $logStmt->bindValue(':user_id', $user['id']);
            $logStmt->bindValue(':school_id', $schoolId);
            $logStmt->bindValue(':details', json_encode([
                'promoted_count' => $promoted,
                'skipped_count' => $skipped,
                'from' => [
                    'academic_year_id' => $fromAcademicYearId,
                    'class_id' => $fromClassId,
                    'section_id' => $fromSectionId,
                ],
                'to' => [
                    'academic_year_id' => $toAcademicYearId,
                    'class_id' => $toClassId,
                    'section_id' => $toSectionId,
                ],
                'student_ids' => $studentIds
            ]));
            $logStmt->bindValue(':ip', $_SERVER['REMOTE_ADDR'] ?? '');
            $logStmt->execute();
        } catch (Exception $e) {
            // Activity log failure should not block the promotion
        }

        $db->commit();

        echo json_encode([
            'success' => true,
            'message' => "Promotion completed successfully",
            'stats' => [
                'promoted' => $promoted,
                'skipped' => $skipped,
                'total_requested' => count($studentIds),
            ],
            'errors' => $errors
        ]);

    } catch (Exception $e) {
        $db->rollBack();
        throw $e;
    }
}
?>
