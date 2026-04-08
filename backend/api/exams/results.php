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
$user = $auth->requireRole(['school_admin', 'class_teacher']);

if (!$user) {
    exit;
}

$database = new Database();
$db = $database->getConnection();

try {
    switch ($_SERVER['REQUEST_METHOD']) {
        case 'GET':
            handleGetResults($db, $user);
            break;
        case 'POST':
            handleUploadResults($db, $user);
            break;
        default:
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Internal server error: ' . $e->getMessage()]);
}

function getExamResultsSchema($db) {
    $erColumns = $db->query("SHOW COLUMNS FROM exam_results")->fetchAll(PDO::FETCH_COLUMN);
    return [
        'has_exam_id' => in_array('exam_id', $erColumns),
        'marks_col' => in_array('marks_obtained', $erColumns) ? 'marks_obtained' : 'marks',
        'has_exam_date' => in_array('exam_date', $erColumns),
        'columns' => $erColumns
    ];
}

function handleGetResults($db, $user) {
    $examId = $_GET['exam_id'] ?? null;
    $classId = $_GET['class_id'] ?? null;
    $sectionId = $_GET['section_id'] ?? null;
    $action = $_GET['action'] ?? 'list';

    if ($action === 'students') {
        if (!$classId || !$sectionId || !$examId) {
            http_response_code(400);
            echo json_encode(['error' => 'class_id, section_id and exam_id are required']);
            return;
        }

        $examQuery = "SELECT e.*, e.subject as exam_subject, c.name as class_name, sec.name as section_name
                      FROM exams e 
                      JOIN classes c ON e.class_id = c.id
                      LEFT JOIN sections sec ON e.section_id = sec.id
                      WHERE e.id = :exam_id AND e.school_id = :school_id";
        $examStmt = $db->prepare($examQuery);
        $examStmt->bindValue(':exam_id', $examId);
        $examStmt->bindValue(':school_id', $user['school_id']);
        $examStmt->execute();
        $exam = $examStmt->fetch(PDO::FETCH_ASSOC);

        if (!$exam) {
            http_response_code(404);
            echo json_encode(['error' => 'Exam not found']);
            return;
        }

        // 2. Pagination parameters
        $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 0; // 0 means no limit (compatible with old frontend)
        $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
        $offset = ($page - 1) * $limit;

        // 3. Count total students for the class/section
        $countQuery = "SELECT COUNT(*) FROM student_enrollments se 
                       JOIN students s ON se.student_id = s.id
                       WHERE se.class_id = :class_id AND se.section_id = :section_id 
                         AND se.status = 'active' AND s.status = 'active'";
        $countStmt = $db->prepare($countQuery);
        $countStmt->bindValue(':class_id', $classId);
        $countStmt->bindValue(':section_id', $sectionId);
        $countStmt->execute();
        $totalStudents = (int)$countStmt->fetchColumn();

        $schema = getExamResultsSchema($db);

        if ($schema['has_exam_id']) {
            $studentsQuery = "SELECT s.id as student_id, s.first_name, s.last_name, 
                                     s.admission_number, se.roll_number,
                                     er.id as result_id, er.{$schema['marks_col']} as marks_obtained
                              FROM student_enrollments se
                              JOIN students s ON se.student_id = s.id
                              LEFT JOIN exam_results er ON er.student_id = s.id AND er.exam_id = :exam_id
                              WHERE se.class_id = :class_id 
                                AND se.section_id = :section_id
                                AND se.status = 'active'
                                AND s.status = 'active'
                              ORDER BY CAST(se.roll_number AS UNSIGNED) ASC, s.first_name ASC";
        } else {
            $studentsQuery = "SELECT s.id as student_id, s.first_name, s.last_name, 
                                     s.admission_number, se.roll_number,
                                     er.id as result_id, er.{$schema['marks_col']} as marks_obtained
                              FROM student_enrollments se
                              JOIN students s ON se.student_id = s.id
                              LEFT JOIN exam_subjects es ON es.exam_id = :exam_id
                              LEFT JOIN exam_results er ON er.exam_subject_id = es.id AND er.student_id = s.id
                              WHERE se.class_id = :class_id 
                                AND se.section_id = :section_id
                                AND se.status = 'active'
                                AND s.status = 'active'
                              ORDER BY CAST(se.roll_number AS UNSIGNED) ASC, s.first_name ASC";
        }

        if ($limit > 0) {
            $studentsQuery .= " LIMIT :limit OFFSET :offset";
        }

        $stmt = $db->prepare($studentsQuery);
        $stmt->bindValue(':exam_id', $examId);
        $stmt->bindValue(':class_id', $classId);
        $stmt->bindValue(':section_id', $sectionId);
        if ($limit > 0) {
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        }
        $stmt->execute();
        $students = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode([
            'success' => true,
            'exam' => $exam,
            'students' => $students,
            'total_students' => $totalStudents,
            'page' => $page,
            'limit' => $limit,
            'total_pages' => ($limit > 0) ? ceil($totalStudents / $limit) : 1
        ]);
        return;
    }

    if ($examId) {
        $schema = getExamResultsSchema($db);

        if ($schema['has_exam_id']) {
            $query = "SELECT er.id, er.student_id, er.{$schema['marks_col']} as marks_obtained,
                             s.first_name, s.last_name, s.admission_number, se.roll_number
                      FROM exam_results er
                      JOIN students s ON er.student_id = s.id
                      LEFT JOIN student_enrollments se ON s.id = se.student_id AND se.status = 'active'
                      WHERE er.exam_id = :exam_id
                      ORDER BY se.roll_number ASC";
        } else {
            $query = "SELECT er.id, er.student_id, er.{$schema['marks_col']} as marks_obtained,
                             s.first_name, s.last_name, s.admission_number, se.roll_number
                      FROM exam_results er
                      JOIN exam_subjects es ON er.exam_subject_id = es.id
                      JOIN students s ON er.student_id = s.id
                      LEFT JOIN student_enrollments se ON s.id = se.student_id AND se.status = 'active'
                      WHERE es.exam_id = :exam_id
                      ORDER BY se.roll_number ASC";
        }

        $stmt = $db->prepare($query);
        $stmt->bindValue(':exam_id', $examId);
        $stmt->execute();
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode(['success' => true, 'data' => $results]);
        return;
    }

    $whereConditions = ["e.school_id = :school_id"];
    $params = [':school_id' => $user['school_id']];

    if ($classId) {
        $whereConditions[] = "e.class_id = :class_id";
        $params[':class_id'] = $classId;
    }
    if ($sectionId) {
        $whereConditions[] = "(e.section_id IS NULL OR e.section_id = :section_id)";
        $params[':section_id'] = $sectionId;
    }

    $whereClause = implode(' AND ', $whereConditions);

    $query = "SELECT e.id, e.name, e.subject, e.exam_type, e.exam_date, 
                     e.total_marks, e.class_id, e.section_id,
                     c.name as class_name, sec.name as section_name
              FROM exams e
              JOIN classes c ON e.class_id = c.id
              LEFT JOIN sections sec ON e.section_id = sec.id
              WHERE $whereClause
              ORDER BY e.exam_date DESC";

    $stmt = $db->prepare($query);
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->execute();
    $exams = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['success' => true, 'data' => $exams]);
}

function handleUploadResults($db, $user) {
    $input = json_decode(file_get_contents('php://input'), true);
    
    $examId = $input['exam_id'] ?? null;
    $results = $input['results'] ?? [];

    if (!$examId || empty($results)) {
        http_response_code(400);
        echo json_encode(['error' => 'exam_id and results array are required']);
        return;
    }

    $examQuery = "SELECT * FROM exams WHERE id = :exam_id AND school_id = :school_id";
    $examStmt = $db->prepare($examQuery);
    $examStmt->bindValue(':exam_id', $examId);
    $examStmt->bindValue(':school_id', $user['school_id']);
    $examStmt->execute();
    $exam = $examStmt->fetch(PDO::FETCH_ASSOC);

    if (!$exam) {
        http_response_code(404);
        echo json_encode(['error' => 'Exam not found or access denied']);
        return;
    }

    $schema = getExamResultsSchema($db);
    $marksCol = $schema['marks_col'];

    $db->beginTransaction();

    try {
        $inserted = 0;
        $updated = 0;

        foreach ($results as $entry) {
            $studentId = $entry['student_id'] ?? null;
            $marksObtained = $entry['marks'] ?? null;

            if ($studentId === null || $marksObtained === null) continue;

            $maxMarks = (float)($exam['total_marks'] ?? 100);
            $marksObtained = max(0, min((float)$marksObtained, $maxMarks));

            if ($schema['has_exam_id']) {
                $checkQuery = "SELECT id FROM exam_results WHERE exam_id = :exam_id AND student_id = :student_id";
                $checkStmt = $db->prepare($checkQuery);
                $checkStmt->bindValue(':exam_id', $examId);
                $checkStmt->bindValue(':student_id', $studentId);
                $checkStmt->execute();
                $existing = $checkStmt->fetch();

                if ($existing) {
                    $updateQuery = "UPDATE exam_results SET {$marksCol} = :marks WHERE id = :id";
                    $updateStmt = $db->prepare($updateQuery);
                    $updateStmt->bindValue(':marks', $marksObtained);
                    $updateStmt->bindValue(':id', $existing['id']);
                    $updateStmt->execute();
                    $updated++;
                } else {
                    // Build INSERT dynamically based on actual columns
                    $cols = ['exam_id', 'student_id', $marksCol];
                    $vals = [':exam_id', ':student_id', ':marks'];
                    $binds = [
                        ':exam_id' => $examId,
                        ':student_id' => $studentId,
                        ':marks' => $marksObtained
                    ];

                    if ($schema['has_exam_date']) {
                        $cols[] = 'exam_date';
                        $vals[] = ':exam_date';
                        $binds[':exam_date'] = $exam['exam_date'] ?? date('Y-m-d');
                    }

                    $insertQuery = "INSERT INTO exam_results (" . implode(', ', $cols) . ") 
                                    VALUES (" . implode(', ', $vals) . ")";
                    $insertStmt = $db->prepare($insertQuery);
                    foreach ($binds as $k => $v) {
                        $insertStmt->bindValue($k, $v);
                    }
                    $insertStmt->execute();
                    $inserted++;
                }
            } else {
                // exam_subjects schema
                $esQuery = "SELECT id FROM exam_subjects WHERE exam_id = :exam_id LIMIT 1";
                $esStmt = $db->prepare($esQuery);
                $esStmt->bindValue(':exam_id', $examId);
                $esStmt->execute();
                $examSubject = $esStmt->fetch();

                if (!$examSubject) {
                    $createEsQuery = "INSERT INTO exam_subjects (exam_id, subject_id, class_id, max_marks, pass_marks) 
                                      SELECT :exam_id, sub.id, :class_id, :max_marks, :pass_marks
                                      FROM subjects sub 
                                      WHERE sub.school_id = :school_id AND sub.name = :subject_name
                                      LIMIT 1";
                    $createEsStmt = $db->prepare($createEsQuery);
                    $createEsStmt->bindValue(':exam_id', $examId);
                    $createEsStmt->bindValue(':class_id', $exam['class_id']);
                    $createEsStmt->bindValue(':max_marks', $maxMarks);
                    $createEsStmt->bindValue(':pass_marks', $exam['passing_marks'] ?? floor($maxMarks * 0.33));
                    $createEsStmt->bindValue(':school_id', $user['school_id']);
                    $createEsStmt->bindValue(':subject_name', $exam['subject'] ?? 'General');
                    $createEsStmt->execute();
                    $examSubjectId = $db->lastInsertId();

                    if (!$examSubjectId) {
                        $genericSubQuery = "SELECT id FROM subjects WHERE school_id = :school_id ORDER BY id LIMIT 1";
                        $genericSubStmt = $db->prepare($genericSubQuery);
                        $genericSubStmt->bindValue(':school_id', $user['school_id']);
                        $genericSubStmt->execute();
                        $genericSub = $genericSubStmt->fetchColumn();

                        if ($genericSub) {
                            $createEsQuery2 = "INSERT INTO exam_subjects (exam_id, subject_id, class_id, max_marks, pass_marks) 
                                               VALUES (:exam_id, :subject_id, :class_id, :max_marks, :pass_marks)";
                            $createEsStmt2 = $db->prepare($createEsQuery2);
                            $createEsStmt2->bindValue(':exam_id', $examId);
                            $createEsStmt2->bindValue(':subject_id', $genericSub);
                            $createEsStmt2->bindValue(':class_id', $exam['class_id']);
                            $createEsStmt2->bindValue(':max_marks', $maxMarks);
                            $createEsStmt2->bindValue(':pass_marks', $exam['passing_marks'] ?? floor($maxMarks * 0.33));
                            $createEsStmt2->execute();
                            $examSubjectId = $db->lastInsertId();
                        }
                    }
                } else {
                    $examSubjectId = $examSubject['id'];
                }

                if (!$examSubjectId) continue;

                $checkQuery = "SELECT id FROM exam_results WHERE exam_subject_id = :es_id AND student_id = :student_id";
                $checkStmt = $db->prepare($checkQuery);
                $checkStmt->bindValue(':es_id', $examSubjectId);
                $checkStmt->bindValue(':student_id', $studentId);
                $checkStmt->execute();
                $existing = $checkStmt->fetch();

                if ($existing) {
                    $updateQuery = "UPDATE exam_results SET {$marksCol} = :marks WHERE id = :id";
                    $updateStmt = $db->prepare($updateQuery);
                    $updateStmt->bindValue(':marks', $marksObtained);
                    $updateStmt->bindValue(':id', $existing['id']);
                    $updateStmt->execute();
                    $updated++;
                } else {
                    $cols = ['exam_subject_id', 'student_id', $marksCol];
                    $vals = [':es_id', ':student_id', ':marks'];
                    $binds = [
                        ':es_id' => $examSubjectId,
                        ':student_id' => $studentId,
                        ':marks' => $marksObtained
                    ];

                    if ($schema['has_exam_date']) {
                        $cols[] = 'exam_date';
                        $vals[] = ':exam_date';
                        $binds[':exam_date'] = $exam['exam_date'] ?? date('Y-m-d');
                    }

                    $insertQuery = "INSERT INTO exam_results (" . implode(', ', $cols) . ") 
                                    VALUES (" . implode(', ', $vals) . ")";
                    $insertStmt = $db->prepare($insertQuery);
                    foreach ($binds as $k => $v) {
                        $insertStmt->bindValue($k, $v);
                    }
                    $insertStmt->execute();
                    $inserted++;
                }
            }
        }

        $db->commit();

        echo json_encode([
            'success' => true,
            'message' => "Results saved successfully",
            'stats' => [
                'inserted' => $inserted,
                'updated' => $updated,
                'total' => $inserted + $updated
            ]
        ]);

    } catch (Exception $e) {
        $db->rollBack();
        throw $e;
    }
}
