<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

require_once '../../middleware/auth.php';

$auth = new AuthMiddleware();
$database = new Database();
$db = $database->getConnection();

try {
    switch ($_SERVER['REQUEST_METHOD']) {
        case 'GET':
            $user = $auth->requireRole(['super_admin', 'school_admin', 'accountant']);
            if (!$user) exit;
            
            $action = $_GET['action'] ?? 'summary';
            
            if (isset($_GET['student_id'])) {
                // Get fee details for specific student
                $query = "SELECT sf.*, fc.name as fee_name, fc.description as fee_description
                          FROM student_fees sf
                          INNER JOIN fee_categories fc ON sf.fee_category_id = fc.id
                          WHERE sf.student_id = :student_id
                          ORDER BY sf.due_date";
                
                $stmt = $db->prepare($query);
                $stmt->bindValue(':student_id', $_GET['student_id']);
                $stmt->execute();
                
                echo json_encode([
                    'success' => true,
                    'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)
                ]);
            } elseif ($action === 'students') {
                // Get current academic year for the school
                $ayQuery = "SELECT id FROM academic_years WHERE school_id = :school_id AND is_current = 1 LIMIT 1";
                $ayStmt = $db->prepare($ayQuery);
                $ayStmt->bindValue(':school_id', $user['school_id']);
                $ayStmt->execute();
                $currentAY = $ayStmt->fetch();
                $ayId = $currentAY ? $currentAY['id'] : 0;

                // Get student-wise fee balance for current academic year
                $query = "SELECT s.id, s.first_name, s.last_name, s.student_id as student_code,
                          c.name as class_name, sec.name as section_name,
                          COALESCE(sums.total_expected, 0) as total_expected,
                          COALESCE(sums.total_collected, 0) as total_collected,
                          (COALESCE(sums.total_expected, 0) - COALESCE(sums.total_collected, 0)) as total_pending,
                          COALESCE(sums.overdue_count, 0) as overdue_count
                          FROM students s
                          INNER JOIN student_enrollments se ON s.id = se.student_id AND se.status = 'active'
                          INNER JOIN classes c ON se.class_id = c.id
                          LEFT JOIN sections sec ON se.section_id = sec.id
                          LEFT JOIN (
                              SELECT student_id, 
                                     SUM(amount) as total_expected, 
                                     SUM(paid_amount) as total_collected,
                                     COUNT(CASE WHEN status = 'pending' AND due_date < CURDATE() THEN 1 END) as overdue_count
                              FROM student_fees 
                              WHERE academic_year_id = :ay_id
                              GROUP BY student_id
                          ) sums ON s.id = sums.student_id
                          WHERE s.school_id = :school_id
                          ORDER BY c.name, sec.name, s.first_name";
                
                $stmt = $db->prepare($query);
                $stmt->bindValue(':school_id', $user['school_id']);
                $stmt->bindValue(':ay_id', $ayId);
                $stmt->execute();
                
                echo json_encode([
                    'success' => true,
                    'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)
                ]);
            } else {
                // Get fee collection summary by category (default)
                $summaryQuery = "SELECT 
                    fc.id,
                    fc.name as fee_category,
                    fc.amount as unit_amount,
                    COUNT(DISTINCT sf.student_id) as total_students,
                    COUNT(CASE WHEN sf.status = 'paid' THEN 1 END) as payments_received,
                    SUM(COALESCE(sf.paid_amount, 0)) as total_collected,
                    SUM(COALESCE(sf.amount, 0)) as total_expected
                    FROM fee_categories fc
                    LEFT JOIN student_fees sf ON fc.id = sf.fee_category_id
                    WHERE fc.school_id = :school_id
                    GROUP BY fc.id, fc.name
                    ORDER BY fc.name";
                
                $summaryStmt = $db->prepare($summaryQuery);
                $summaryStmt->bindValue(':school_id', $user['school_id']);
                $summaryStmt->execute();
                
                echo json_encode([
                    'success' => true,
                    'data' => $summaryStmt->fetchAll(PDO::FETCH_ASSOC)
                ]);
            }
            break;
            
        case 'POST':
            $user = $auth->requireRole(['super_admin', 'school_admin', 'accountant']);
            if (!$user) exit;
            
            $input = json_decode(file_get_contents('php://input'), true);
            
            $required = ['fee_id', 'amount_paid', 'payment_method']; 
            foreach ($required as $field) {
                if (!isset($input[$field]) || empty($input[$field])) {
                    http_response_code(400);
                    echo json_encode(['error' => "Field '$field' is required"]);
                    exit;
                }
            }
            
            $db->beginTransaction();
            
            $verifyQuery = "SELECT sf.*, fc.school_id 
                           FROM student_fees sf
                           INNER JOIN fee_categories fc ON sf.fee_category_id = fc.id
                           WHERE sf.id = :fee_id AND fc.school_id = :school_id";
            
            $verifyStmt = $db->prepare($verifyQuery);
            $verifyStmt->bindValue(':fee_id', $input['fee_id']);
            $verifyStmt->bindValue(':school_id', $user['school_id']);
            $verifyStmt->execute();
            
            if ($verifyStmt->rowCount() === 0) {
                http_response_code(404);
                echo json_encode(['error' => 'Fee record not found or access denied']);
                exit;
            }
            
            $feeRecord = $verifyStmt->fetch(PDO::FETCH_ASSOC);
            $newPaidAmount = $feeRecord['paid_amount'] + $input['amount_paid'];
            $newStatus = ($newPaidAmount >= $feeRecord['amount']) ? 'paid' : 'pending';
            
            $updateQuery = "UPDATE student_fees SET 
                            paid_amount = :paid_amount,
                            paid_date = :paid_date,
                            status = :status,
                            payment_method = :payment_method,
                            transaction_id = :transaction_id,
                            handled_by = :handled_by
                            WHERE id = :id";
            
            $updateStmt = $db->prepare($updateQuery);
            $updateStmt->bindValue(':paid_amount', $newPaidAmount);
            $updateStmt->bindValue(':paid_date', $input['payment_date'] ?? date('Y-m-d'));
            $updateStmt->bindValue(':status', $newStatus);
            $updateStmt->bindValue(':payment_method', $input['payment_method']);
            $updateStmt->bindValue(':transaction_id', $input['transaction_id'] ?? null);
            $updateStmt->bindValue(':handled_by', $user['id']);
            $updateStmt->bindValue(':id', $input['fee_id']);
            
            $updateStmt->execute();
            
            $db->commit();
            
            echo json_encode([
                'success' => true,
                'message' => 'Payment recorded successfully',
                'new_status' => $newStatus,
                'total_paid' => $newPaidAmount
            ]);
            break;
            
        default:
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
            break;
    }
} catch (Exception $e) {
    if (isset($db) && $db->inTransaction()) {
        $db->rollBack();
    }
    http_response_code(500);
    echo json_encode([
        'error' => 'Internal server error',
        'details' => $e->getMessage()
    ]);
}
?>
